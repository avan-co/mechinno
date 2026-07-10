<?php

declare(strict_types=1);

final class CenterLedger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $page = 1, int $perPage = 100): array
    {
        $page = max(1, $page);
        $perPage = min(200, max(25, $perPage));
        $totals = $this->totalsFromDb();
        $totalRows = $this->cashEntryCount();
        $pages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->cashEntriesPage($offset, $perPage, $totalRows);

        return [
            'balance' => $totals['balance'],
            'totals' => $totals,
            'billing' => $this->billingSummary(),
            'rows' => $rows,
            'total' => $totalRows,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    public function balance(): int
    {
        return $this->totalsFromDb()['balance'];
    }

    public static function purgeAccrualMirrorEntries(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM transactions WHERE source_file LIKE 'system:charge:%'");
    }

    public static function isSystemSource(?string $sourceFile): bool
    {
        return is_string($sourceFile) && str_starts_with($sourceFile, 'system:charge:');
    }

    private function cashWhereSql(): string
    {
        return "(category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1)
                OR (category = 'درآمد' AND confirmed = 1)
                OR (category = 'هزینه' AND confirmed = 1)";
    }

    private function cashEntryCount(): int
    {
        return (int) $this->pdo->query(
            'SELECT COUNT(*) FROM transactions WHERE ' . $this->cashWhereSql()
        )->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cashEntriesPage(int $offset, int $limit, int $totalRows): array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.team_id,
                    t.fiscal_year, t.month_index, t.confirmed, t.payment_status, t.source_file,
                    tm.name AS team_name
             FROM transactions t
             LEFT JOIN teams tm ON tm.id = t.team_id
             WHERE (t.category = 'واریز تیم' AND t.payment_status = 'approved' AND t.confirmed = 1)
                OR (t.category = 'درآمد' AND t.confirmed = 1)
                OR (t.category = 'هزینه' AND t.confirmed = 1)
             ORDER BY t.tx_date ASC, t.id ASC
             LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $prefixBalance = $this->signedSumBeforeOffset($offset);
        $rows = [];
        $line = $offset;
        $running = $prefixBalance;
        foreach ($statement->fetchAll() as $row) {
            $line++;
            $category = (string) ($row['category'] ?? '');
            $amount = abs((int) ($row['amount'] ?? 0));
            $signed = $category === 'هزینه' ? -$amount : $amount;
            $running += $signed;
            $entryType = match ($category) {
                'واریز تیم' => 'deposit',
                'درآمد' => 'income',
                'هزینه' => 'expense',
                default => 'other',
            };

            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'tx_date' => $row['tx_date'] ?? '',
                'description' => $this->normalizeDescription($row, $category),
                'amount' => $amount,
                'signed_amount' => $signed,
                'category' => $category,
                'category_label' => self::categoryLabel($category),
                'team_id' => $row['team_id'] ?? null,
                'team_name' => $row['team_name'] ?? '',
                'fiscal_year' => $row['fiscal_year'] ?? '',
                'month_index' => (int) ($row['month_index'] ?? 0),
                'entry_type' => $entryType,
                'entry_type_label' => self::entryTypeLabel($entryType, $category),
                'line_no' => $line,
                'running_balance' => $running,
            ];
        }

        return $rows;
    }

    private function signedSumBeforeOffset(int $offset): int
    {
        if ($offset <= 0) {
            return 0;
        }

        $statement = $this->pdo->prepare(
            "SELECT amount, category FROM transactions
             WHERE (category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1)
                OR (category = 'درآمد' AND confirmed = 1)
                OR (category = 'هزینه' AND confirmed = 1)
             ORDER BY tx_date ASC, id ASC
             LIMIT :limit"
        );
        $statement->bindValue(':limit', $offset, PDO::PARAM_INT);
        $statement->execute();

        $sum = 0;
        foreach ($statement->fetchAll() as $row) {
            $amount = abs((int) ($row['amount'] ?? 0));
            $sum += ((string) ($row['category'] ?? '') === 'هزینه') ? -$amount : $amount;
        }

        return $sum;
    }

    /**
     * @return array<string, int>
     */
    private function totalsFromDb(): array
    {
        $deposits = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1"
        )->fetchColumn();
        $manualIncome = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions
             WHERE category = 'درآمد' AND confirmed = 1"
        )->fetchColumn();
        $manualExpense = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions
             WHERE category = 'هزینه' AND confirmed = 1"
        )->fetchColumn();

        return [
            'deposits' => $deposits,
            'manual_income' => $manualIncome,
            'manual_expense' => $manualExpense,
            'income_total' => $deposits + $manualIncome,
            'expense_total' => $manualExpense,
            'balance' => $deposits + $manualIncome - $manualExpense,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function billingSummary(): array
    {
        $repository = new Repository($this->pdo);
        $chargeTotal = $repository->totalContractCharge();
        $receivedTotal = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1"
        )->fetchColumn();

        return [
            'charge_total' => $chargeTotal,
            'received_total' => $receivedTotal,
            'receivable' => $repository->totalContractDebt(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeDescription(array $row, string $category): string
    {
        $description = trim((string) ($row['description'] ?? ''));
        $teamName = trim((string) ($row['team_name'] ?? ''));
        $month = JalaliDate::monthName((int) ($row['month_index'] ?? 0));
        $year = (string) ($row['fiscal_year'] ?? '');

        if ($category === 'واریز تیم') {
            $parts = ['دریافت شارژ'];
            if ($teamName !== '') {
                $parts[] = $teamName;
            }
            if ($month !== '') {
                $parts[] = "{$month} {$year}";
            }
            if ($description !== '' && !str_starts_with($description, 'ثبت مستقیم مدیر')) {
                $parts[] = $description;
            }

            return implode(' — ', $parts);
        }

        if ($description !== '') {
            return $description;
        }

        return self::categoryLabel($category);
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'واریز تیم' => 'دریافت از نهاد',
            'درآمد' => 'درآمد دستی',
            'هزینه' => 'هزینه',
            default => $category !== '' ? $category : '—',
        };
    }

    public static function entryTypeLabel(string $entryType, string $category = ''): string
    {
        return match ($entryType) {
            'deposit' => 'دریافت از نهاد',
            'income' => 'درآمد دستی',
            'expense' => 'هزینه',
            default => self::categoryLabel($category),
        };
    }
}
