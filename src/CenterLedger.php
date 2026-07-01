<?php

declare(strict_types=1);

final class CenterLedger
{
    public const CAT_CHARGE = 'شارژ ماهانه';
    public const CAT_RENT = 'اجاره غیررسمی';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function syncFromCharges(): void
    {
        $charges = $this->pdo->query(
            'SELECT c.id, c.team_id, c.fiscal_year, c.month_index, c.month_name,
                    c.charge_amount, c.rent_amount, t.name AS team_name
             FROM charges c
             JOIN teams t ON t.id = c.team_id
             ORDER BY c.id'
        )->fetchAll();

        $activeKeys = [];
        foreach ($charges as $charge) {
            $chargeId = (int) ($charge['id'] ?? 0);
            $teamName = (string) ($charge['team_name'] ?? '—');
            $fiscalYear = (string) ($charge['fiscal_year'] ?? '');
            $monthName = (string) ($charge['month_name'] ?? '');
            $monthIndex = (int) ($charge['month_index'] ?? 0);
            $txDate = sprintf('%s/%02d/01', JalaliDate::normalizeDigits($fiscalYear), max(1, $monthIndex));

            $chargeAmount = (int) ($charge['charge_amount'] ?? 0);
            if ($chargeAmount > 0) {
                $key = $this->sourceKey($chargeId, 'charge');
                $activeKeys[] = $key;
                $this->upsertSystemEntry(
                    $key,
                    $txDate,
                    sprintf(
                        'شارژ ماهانه — نهاد «%s» — %s %s — %s ریال',
                        $teamName,
                        $monthName,
                        $fiscalYear,
                        number_format($chargeAmount)
                    ),
                    $chargeAmount,
                    self::CAT_CHARGE,
                    (int) ($charge['team_id'] ?? 0),
                    $fiscalYear,
                    $monthIndex
                );
            }

            $rentAmount = (int) ($charge['rent_amount'] ?? 0);
            if ($rentAmount > 0) {
                $key = $this->sourceKey($chargeId, 'rent');
                $activeKeys[] = $key;
                $this->upsertSystemEntry(
                    $key,
                    $txDate,
                    sprintf(
                        'اجاره غیررسمی میز — نهاد «%s» — %s %s — %s ریال',
                        $teamName,
                        $monthName,
                        $fiscalYear,
                        number_format($rentAmount)
                    ),
                    $rentAmount,
                    self::CAT_RENT,
                    (int) ($charge['team_id'] ?? 0),
                    $fiscalYear,
                    $monthIndex
                );
            }
        }

        $this->purgeStaleSystemEntries($activeKeys);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $this->syncFromCharges();
        $rows = $this->entries();
        $totals = $this->totals($rows);
        $balance = $totals['income_total'] - $totals['expense_total'];

        return [
            'balance' => $balance,
            'totals' => $totals,
            'rows' => $rows,
        ];
    }

    public function balance(): int
    {
        return (int) ($this->snapshot()['balance'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        $statement = $this->pdo->query(
            "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.team_id,
                    t.fiscal_year, t.month_index, t.confirmed, t.payment_status, t.source_file,
                    tm.name AS team_name
             FROM transactions t
             LEFT JOIN teams tm ON tm.id = t.team_id
             WHERE (t.source_file LIKE 'system:charge:%')
                OR (t.category IN ('درآمد', 'هزینه') AND t.confirmed = 1)
                OR (t.category = 'واریز تیم' AND t.payment_status = 'approved' AND t.confirmed = 1)
             ORDER BY t.tx_date DESC, t.id DESC"
        );

        return array_map(function (array $row): array {
            $source = (string) ($row['source_file'] ?? '');
            $category = (string) ($row['category'] ?? '');
            $amount = (int) ($row['amount'] ?? 0);
            $entryType = 'manual';
            $countsInBalance = true;
            if (str_starts_with($source, 'system:charge:')) {
                $entryType = 'system';
            } elseif ($category === 'واریز تیم') {
                $entryType = 'deposit';
                $countsInBalance = false;
            } elseif ($category === 'هزینه') {
                $entryType = 'expense';
            } elseif ($category === 'درآمد') {
                $entryType = 'income';
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'tx_date' => $row['tx_date'] ?? '',
                'description' => $row['description'] ?? '',
                'amount' => $amount,
                'category' => $category,
                'team_id' => $row['team_id'] ?? null,
                'team_name' => $row['team_name'] ?? '',
                'fiscal_year' => $row['fiscal_year'] ?? '',
                'month_index' => (int) ($row['month_index'] ?? 0),
                'entry_type' => $entryType,
                'counts_in_balance' => $countsInBalance,
                'signed_amount' => $category === 'هزینه' ? -abs($amount) : abs($amount),
            ];
        }, $statement->fetchAll());
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function totals(array $rows): array
    {
        $systemIncome = 0;
        $manualIncome = 0;
        $manualExpense = 0;
        $deposits = 0;

        foreach ($rows as $row) {
            $amount = abs((int) ($row['amount'] ?? 0));
            $type = (string) ($row['entry_type'] ?? '');
            if ($type === 'system' || $type === 'income') {
                if ($type === 'system') {
                    $systemIncome += $amount;
                } else {
                    $manualIncome += $amount;
                }
            } elseif ($type === 'expense') {
                $manualExpense += $amount;
            } elseif ($type === 'deposit') {
                $deposits += $amount;
            }
        }

        $incomeTotal = $systemIncome + $manualIncome;

        return [
            'system_income' => $systemIncome,
            'manual_income' => $manualIncome,
            'manual_expense' => $manualExpense,
            'deposits' => $deposits,
            'income_total' => $incomeTotal,
            'expense_total' => $manualExpense,
        ];
    }

    private function sourceKey(int $chargeId, string $kind): string
    {
        return sprintf('system:charge:%d:%s', $chargeId, $kind);
    }

    private function upsertSystemEntry(
        string $sourceFile,
        string $txDate,
        string $description,
        int $amount,
        string $category,
        int $teamId,
        string $fiscalYear,
        int $monthIndex
    ): void {
        $existing = $this->pdo->prepare('SELECT id FROM transactions WHERE source_file = :source_file LIMIT 1');
        $existing->execute(['source_file' => $sourceFile]);
        $id = $existing->fetchColumn();

        $payload = [
            'tx_date' => $txDate,
            'description' => $description,
            'amount' => $amount,
            'category' => $category,
            'team_id' => $teamId > 0 ? $teamId : null,
            'fiscal_year' => JalaliDate::normalizeDigits($fiscalYear),
            'month_index' => $monthIndex,
            'confirmed' => 1,
            'payment_status' => 'approved',
            'source_file' => $sourceFile,
        ];

        if ($id !== false) {
            $statement = $this->pdo->prepare(
                'UPDATE transactions
                 SET tx_date = :tx_date, description = :description, amount = :amount,
                     category = :category, team_id = :team_id, fiscal_year = :fiscal_year,
                     month_index = :month_index, confirmed = :confirmed, payment_status = :payment_status
                 WHERE id = :id'
            );
            $statement->execute([
                'tx_date' => $payload['tx_date'],
                'description' => $payload['description'],
                'amount' => $payload['amount'],
                'category' => $payload['category'],
                'team_id' => $payload['team_id'],
                'fiscal_year' => $payload['fiscal_year'],
                'month_index' => $payload['month_index'],
                'confirmed' => $payload['confirmed'],
                'payment_status' => $payload['payment_status'],
                'id' => (int) $id,
            ]);

            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO transactions
             (tx_date, description, amount, category, team_id, fiscal_year, month_index, confirmed, payment_status, source_file)
             VALUES
             (:tx_date, :description, :amount, :category, :team_id, :fiscal_year, :month_index, :confirmed, :payment_status, :source_file)'
        );
        $statement->execute($payload);
    }

    /**
     * @param list<string> $activeKeys
     */
    private function purgeStaleSystemEntries(array $activeKeys): void
    {
        $statement = $this->pdo->query(
            "SELECT id, source_file FROM transactions WHERE source_file LIKE 'system:charge:%'"
        );
        $activeLookup = array_fill_keys($activeKeys, true);
        $delete = $this->pdo->prepare('DELETE FROM transactions WHERE id = :id');
        foreach ($statement->fetchAll() as $row) {
            $source = (string) ($row['source_file'] ?? '');
            if (!isset($activeLookup[$source])) {
                $delete->execute(['id' => (int) ($row['id'] ?? 0)]);
            }
        }
    }

    public static function isSystemSource(?string $sourceFile): bool
    {
        return is_string($sourceFile) && str_starts_with($sourceFile, 'system:charge:');
    }
}
