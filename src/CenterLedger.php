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
    public function snapshot(): array
    {
        $rows = $this->cashEntries();
        $totals = $this->totals($rows);

        return [
            'balance' => $totals['balance'],
            'totals' => $totals,
            'billing' => $this->billingSummary(),
            'rows' => $rows,
        ];
    }

    public function balance(): int
    {
        return $this->totals($this->cashEntries())['balance'];
    }

    public static function purgeAccrualMirrorEntries(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM transactions WHERE source_file LIKE 'system:charge:%'");
    }

    public static function isSystemSource(?string $sourceFile): bool
    {
        return is_string($sourceFile) && str_starts_with($sourceFile, 'system:charge:');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cashEntries(): array
    {
        $statement = $this->pdo->query(
            "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.team_id,
                    t.fiscal_year, t.month_index, t.confirmed, t.payment_status, t.source_file,
                    tm.name AS team_name
             FROM transactions t
             LEFT JOIN teams tm ON tm.id = t.team_id
             WHERE (t.category = 'واریز تیم' AND t.payment_status = 'approved' AND t.confirmed = 1)
                OR (t.category = 'درآمد' AND t.confirmed = 1)
                OR (t.category = 'هزینه' AND t.confirmed = 1)
             ORDER BY t.tx_date ASC, t.id ASC"
        );

        $running = 0;
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $category = (string) ($row['category'] ?? '');
            $amount = abs((int) ($row['amount'] ?? 0));
            $signed = $category === 'هزینه' ? -$amount : $amount;
            $entryType = match ($category) {
                'واریز تیم' => 'deposit',
                'درآمد' => 'income',
                'هزینه' => 'expense',
                default => 'other',
            };
            $running += $signed;

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
                'entry_type_label' => self::entryTypeLabel($entryType),
                'running_balance' => $running,
            ];
        }

        return array_reverse($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function totals(array $rows): array
    {
        $deposits = 0;
        $manualIncome = 0;
        $manualExpense = 0;

        foreach ($rows as $row) {
            $amount = abs((int) ($row['amount'] ?? 0));
            $type = (string) ($row['entry_type'] ?? '');
            if ($type === 'deposit') {
                $deposits += $amount;
            } elseif ($type === 'income') {
                $manualIncome += $amount;
            } elseif ($type === 'expense') {
                $manualExpense += $amount;
            }
        }

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
        $chargeTotal = (int) $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM charges')->fetchColumn();
        $receivedTotal = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1"
        )->fetchColumn();

        return [
            'charge_total' => $chargeTotal,
            'received_total' => $receivedTotal,
            'receivable' => max(0, $chargeTotal - $receivedTotal),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeDescription(array $row, string $category): string
    {
        $description = trim((string) ($row['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $teamName = trim((string) ($row['team_name'] ?? ''));
        if ($category === 'واریز تیم') {
            $month = JalaliDate::monthName((int) ($row['month_index'] ?? 0));
            $year = (string) ($row['fiscal_year'] ?? '');

            return trim(sprintf('دریافت شارژ%s%s', $teamName !== '' ? " — {$teamName}" : '', $month !== '' ? " — {$month} {$year}" : ''));
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

    public static function entryTypeLabel(string $entryType): string
    {
        return match ($entryType) {
            'deposit' => 'دریافت نقدی',
            'income' => 'درآمد',
            'expense' => 'هزینه',
            default => '—',
        };
    }
}
