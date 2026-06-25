<?php

declare(strict_types=1);

final class Importer
{
    private const MONTHS = [
        ['E', 1, 'فروردین'],
        ['F', 2, 'اردیبهشت'],
        ['G', 3, 'خرداد'],
        ['H', 4, 'تیر'],
        ['I', 5, 'مرداد'],
        ['J', 6, 'شهریور'],
        ['K', 7, 'مهر'],
        ['L', 8, 'آبان'],
        ['M', 9, 'آذر'],
        ['N', 10, 'دی'],
        ['O', 11, 'بهمن'],
        ['P', 12, 'اسفند'],
    ];

    public function __construct(private readonly PDO $pdo, private readonly string $basePath)
    {
    }

    /**
     * @return array<string, int>
     */
    public function importAll(): array
    {
        Schema::migrate($this->pdo);
        $backupId = $this->hasImportedData() ? (new BackupManager($this->pdo))->create('before_excel_reimport') : null;

        $this->pdo->beginTransaction();
        try {
            Schema::reset($this->pdo);
            $this->importInnovationCenter($this->basePath . '/Innovation Center.xlsx');
            $this->importCharges($this->basePath . '/CHARGE.xlsx');
            $this->importFinance($this->basePath . '/finance.xlsx');
            $this->resolveTeamRelations();
            if ($backupId !== null) {
                (new BackupManager($this->pdo))->restoreManualRows($backupId);
                $this->resolveTeamRelations();
            }
            $this->insert('import_runs', [
                'source_files' => json_encode(
                    ['Innovation Center.xlsx', 'CHARGE.xlsx', 'finance.xlsx'],
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'members' => $this->count('members'),
            'teams' => $this->count('teams'),
            'lockers' => $this->count('lockers'),
            'plans' => $this->count('plans'),
            'charges' => $this->count('charges'),
            'transactions' => $this->count('transactions'),
            'warnings' => $this->count('import_warnings'),
            'backup_id' => $backupId ?? 0,
        ];
    }

    private function importInnovationCenter(string $path): void
    {
        $reader = new XlsxReader($path);
        $file = basename($path);

        for ($row = 4; $row <= $reader->maxRow('Members'); $row++) {
            $fullName = $this->clean($reader->value('Members', "C{$row}"));
            if ($fullName === '') {
                continue;
            }
            $this->insert('members', [
                'row_number' => $row,
                'code' => $this->clean($reader->value('Members', "B{$row}")),
                'full_name' => $fullName,
                'team_name' => $this->clean($reader->value('Members', "D{$row}")),
                'desks' => $this->clean($reader->value('Members', "E{$row}")),
                'lockers' => $this->clean($reader->value('Members', "F{$row}")),
                'power_strips' => $this->clean($reader->value('Members', "G{$row}")),
                'phone' => $this->clean($reader->value('Members', "H{$row}")),
                'national_id' => $this->clean($reader->value('Members', "I{$row}")),
                'notes' => $this->clean($reader->value('Members', "J{$row}")),
                'source_file' => $file,
                'source_sheet' => 'Members',
            ]);
        }

        for ($row = 6; $row < 20; $row++) {
            $name = $this->clean($reader->value('Teams', "B{$row}"));
            $leader = $this->clean($reader->value('Teams', "C{$row}"));
            if ($name === '' && $leader === '') {
                continue;
            }
            $this->insert('teams', [
                'row_number' => $row,
                'name' => $name,
                'leader' => $leader,
                'phone' => $this->clean($reader->value('Teams', "D{$row}")),
                'desk_count' => $this->parseInt($reader->value('Teams', "E{$row}")),
                'lockers' => $this->clean($reader->value('Teams', "F{$row}")),
                'power_strips' => $this->clean($reader->value('Teams', "G{$row}")),
                'joined_at' => $this->parseJalaliDate($reader->value('Teams', "H{$row}")),
                'warning' => $this->clean($reader->value('Teams', "I{$row}")),
                'notes' => $this->clean($reader->value('Teams', "J{$row}")),
                'source_file' => $file,
                'source_sheet' => 'Teams',
            ]);
        }

        for ($row = 6; $row <= $reader->maxRow('lockers'); $row++) {
            $lockerNumber = $this->parseInt($reader->value('lockers', "A{$row}"));
            if ($lockerNumber === null) {
                continue;
            }
            $deliveredAt = $this->parseJalaliDate($reader->value('lockers', "D{$row}"));
            if ($deliveredAt !== '' && !$this->isValidJalaliDate($deliveredAt)) {
                $this->warn($file, 'lockers', $row, 'تاریخ تحویل کمد معتبر به نظر نمی‌رسد.', [
                    'delivered_at' => $deliveredAt,
                    'locker_number' => $lockerNumber,
                ]);
            }
            $this->insert('lockers', [
                'locker_number' => $lockerNumber,
                'status' => $this->clean($reader->value('lockers', "B{$row}")),
                'assigned_to' => $this->clean($reader->value('lockers', "C{$row}")),
                'delivered_at' => $deliveredAt,
                'key_number' => $this->clean($reader->value('lockers', "E{$row}")),
                'spare_key' => $this->clean($reader->value('lockers', "F{$row}")),
                'notes' => '',
                'source_file' => $file,
                'source_sheet' => 'lockers',
            ]);
        }

        for ($row = 6; $row <= $reader->maxRow('plans'); $row++) {
            $planNumber = $this->parseInt($reader->value('plans', "A{$row}"));
            $title = $this->clean($reader->value('plans', "C{$row}"));
            if ($planNumber === null && $title === '') {
                continue;
            }
            $this->insert('plans', [
                'plan_number' => $planNumber,
                'status' => $this->clean($reader->value('plans', "B{$row}")),
                'title' => $title,
                'proposed_budget' => $this->parseInt($reader->value('plans', "D{$row}")),
                'cost_type' => $this->clean($reader->value('plans', "E{$row}")),
                'schedule' => $this->clean($reader->value('plans', "F{$row}")),
                'notes' => '',
                'source_file' => $file,
                'source_sheet' => 'plans',
            ]);
        }
    }

    private function importCharges(string $path): void
    {
        $reader = new XlsxReader($path);
        $file = basename($path);

        foreach ($reader->sheetNames() as $sheet) {
            $chargeRate = $this->parseInt($reader->value($sheet, 'T1'));
            $rentRate = $this->parseInt($reader->value($sheet, 'T3'));
            $this->ensureRateSetting($sheet, $chargeRate, $rentRate);
            for ($row = 6; $row <= $reader->maxRow($sheet); $row++) {
                $teamName = $this->clean($reader->value($sheet, "B{$row}"));
                $leader = $this->clean($reader->value($sheet, "C{$row}"));
                if ($teamName === '' && $leader === '') {
                    continue;
                }

                $note = $this->clean($reader->value($sheet, "Q{$row}"));
                foreach (self::MONTHS as [$column, $monthIndex, $monthName]) {
                    $amount = $this->parseInt($reader->value($sheet, "{$column}{$row}"));
                    if ($amount === null) {
                        continue;
                    }
                    $this->insert('charges', [
                        'fiscal_year' => $sheet,
                        'team_name' => $teamName,
                        'leader' => $leader,
                        'desk_count' => $this->parseInt($reader->value($sheet, "D{$row}")),
                        'month_index' => $monthIndex,
                        'month_name' => $monthName,
                        'amount' => $amount,
                        'note' => $note,
                        'charge_rate' => $chargeRate,
                        'rent_rate' => $rentRate,
                        'source_file' => $file,
                        'source_sheet' => $sheet,
                    ]);
                }
            }
        }
    }

    private function importFinance(string $path): void
    {
        $reader = new XlsxReader($path);
        $file = basename($path);

        foreach ($reader->sheetNames() as $sheet) {
            $this->insert('financial_batches', [
                'sheet_name' => $sheet,
                'petty_cash_holder' => $this->clean($reader->value($sheet, 'I1')),
                'petty_cash_number' => $this->clean($reader->value($sheet, 'K1')),
                'previous_balance' => $this->parseInt($reader->value($sheet, 'I2')),
                'new_deposit' => $this->parseInt($reader->value($sheet, 'I3')),
                'total_balance' => $this->parseInt($reader->value($sheet, 'I4')),
                'received_at' => $this->parseJalaliDate($reader->value($sheet, 'K2')),
                'from_date' => $this->parseJalaliDate($reader->value($sheet, 'K3')),
                'to_date' => $this->parseJalaliDate($reader->value($sheet, 'K4')),
                'source_file' => $file,
            ]);
            $batchId = (int) $this->pdo->lastInsertId();

            for ($row = 6; $row <= 27; $row++) {
                $rowNumber = $this->clean($reader->value($sheet, "A{$row}"));
                $description = $this->clean($reader->value($sheet, "F{$row}"));
                $amount = $this->parseInt($reader->value($sheet, "I{$row}"));
                $notes = $this->clean($reader->value($sheet, "J{$row}"));
                if ($rowNumber === '' || ($description === '' && $amount === null && $notes === '')) {
                    continue;
                }

                $suspectedAmountNote = null;
                $noteAmount = $this->parseInt($notes);
                if ($noteAmount !== null && ($amount === null || $amount === 0)) {
                    $suspectedAmountNote = $noteAmount;
                    $this->warn($file, $sheet, $row, 'یک مبلغ احتمالی در ستون توضیحات پیدا شد.', [
                        'description' => $description,
                        'amount_column' => $amount,
                        'notes_column' => $notes,
                    ]);
                }

                $this->insert('transactions', [
                    'batch_id' => $batchId,
                    'row_number' => $this->parseInt($rowNumber),
                    'invoice_count' => $this->clean($reader->value($sheet, "B{$row}")),
                    'tx_date' => $this->composeJalaliDate(
                        $reader->value($sheet, "C{$row}"),
                        $reader->value($sheet, "D{$row}"),
                        $reader->value($sheet, "E{$row}")
                    ),
                    'description' => $description,
                    'amount' => $amount,
                    'notes' => $notes,
                    'category' => $this->categorizeTransaction($description, $amount),
                    'suspected_amount_note' => $suspectedAmountNote,
                ]);
            }
        }
    }

    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $statement = $this->pdo->prepare($sql);
        foreach ($data as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }
        $statement->execute();
    }

    private function warn(string $file, string $sheet, int $row, string $message, array $payload = []): void
    {
        $this->insert('import_warnings', [
            'file_name' => $file,
            'sheet_name' => $sheet,
            'row_number' => $row,
            'message' => $message,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function hasImportedData(): bool
    {
        foreach (['teams', 'members', 'lockers', 'charges', 'transactions'] as $table) {
            if ($this->count($table) > 0) {
                return true;
            }
        }

        return false;
    }

    private function ensureRateSetting(string $fiscalYear, ?int $chargeRate, ?int $rentRate): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM rate_settings WHERE fiscal_year = :fiscal_year ORDER BY id LIMIT 1');
        $statement->execute(['fiscal_year' => $fiscalYear]);
        if ($statement->fetchColumn() !== false) {
            return;
        }

        $this->insert('rate_settings', [
            'fiscal_year' => $fiscalYear,
            'title' => 'نرخ واردشده از Excel',
            'charge_rate' => $chargeRate,
            'rent_rate' => $rentRate,
            'effective_from' => '',
            'notes' => 'ایجاد خودکار هنگام import فایل شارژ',
        ]);
    }

    private function resolveTeamRelations(): void
    {
        $teams = $this->pdo->query('SELECT id, name, leader FROM teams')->fetchAll();
        $teamByName = [];
        $teamByLeader = [];
        foreach ($teams as $team) {
            $nameKey = $this->normalizeName($team['name'] ?? '');
            $leaderKey = $this->normalizeName($team['leader'] ?? '');
            if ($nameKey !== '') {
                $teamByName[$nameKey] = (int) $team['id'];
            }
            if ($leaderKey !== '') {
                $teamByLeader[$leaderKey] = (int) $team['id'];
            }
        }

        $this->resolveTableTeam('members', 'team_name', $teamByName, $teamByLeader);
        $this->resolveTableTeam('lockers', 'assigned_to', $teamByName, $teamByLeader);
        $this->resolveTableTeam('charges', 'team_name', $teamByName, $teamByLeader);
    }

    /**
     * @param array<string, int> $teamByName
     * @param array<string, int> $teamByLeader
     */
    private function resolveTableTeam(string $table, string $nameColumn, array $teamByName, array $teamByLeader): void
    {
        $rows = $this->pdo->query("SELECT id, {$nameColumn} FROM {$table}")->fetchAll();
        $update = $this->pdo->prepare("UPDATE {$table} SET team_id = :team_id WHERE id = :id");
        foreach ($rows as $row) {
            $key = $this->normalizeName($row[$nameColumn] ?? '');
            if ($key === '') {
                continue;
            }
            $teamId = $teamByName[$key] ?? $teamByLeader[$key] ?? null;
            if ($teamId === null) {
                foreach ($teamByName as $teamName => $candidateId) {
                    if (str_contains($key, $teamName) || str_contains($teamName, $key)) {
                        $teamId = $candidateId;
                        break;
                    }
                }
            }
            if ($teamId !== null) {
                $update->execute(['team_id' => $teamId, 'id' => $row['id']]);
            }
        }
    }

    private function normalizeName(mixed $value): string
    {
        $text = $this->clean($value);
        $text = str_replace(['ي', 'ك', '‌', '(', ')', '-'], ['ی', 'ک', ' ', ' ', ' ', ' '], $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string) $value);
        return strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function parseInt(mixed $value): ?int
    {
        $text = str_replace([',', '٬'], '', $this->clean($value));
        if ($text === '' || $text === '-') {
            return null;
        }
        if (!preg_match('/^-?\d+(\.0+)?$/', $text)) {
            return null;
        }

        return (int) (float) $text;
    }

    private function parseJalaliDate(mixed $value): string
    {
        return JalaliDate::tryNormalize($this->clean($value));
    }

    private function composeJalaliDate(mixed $day, mixed $month, mixed $year): string
    {
        try {
            return JalaliDate::compose($day, $month, $year);
        } catch (InvalidArgumentException) {
            return '';
        }
    }

    private function isValidJalaliDate(string $date): bool
    {
        return JalaliDate::isValid($date);
    }

    private function categorizeTransaction(string $description, ?int $amount): string
    {
        if ($amount === null) {
            return 'نامشخص';
        }
        if ($amount < 0) {
            return 'هزینه';
        }
        foreach (['آبونمان', 'شارژ', 'سود', 'سهم مرکز', 'واریز'] as $keyword) {
            if (str_contains($description, $keyword)) {
                return 'درآمد';
            }
        }

        return 'دریافت';
    }
}
