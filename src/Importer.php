<?php

declare(strict_types=1);

final class Importer
{
    public function __construct(private readonly PDO $pdo, private readonly string $basePath)
    {
    }

    /**
     * @return array<string, int>
     */
    public function importAll(bool $preferExcel = false): array
    {
        Schema::migrate($this->pdo);

        if ($preferExcel && $this->excelFilesExist()) {
            return $this->importFromExcel();
        }

        return (new Seeder($this->pdo, $this->basePath))->seedFromFile();
    }

    /**
     * @return array<string, int>
     */
    public function importFromExcel(): array
    {
        $backupId = $this->hasImportedData() ? (new BackupManager($this->pdo))->create('before_excel_import') : null;

        $this->pdo->beginTransaction();
        try {
            Schema::reset($this->pdo);
            Schema::seedDesks($this->pdo);
            Schema::seedLockerSlots($this->pdo, 30);

            $this->importInnovationCenter($this->basePath . '/Innovation Center.xlsx');
            $this->importCharges($this->basePath . '/CHARGE.xlsx');
            $this->importFinance($this->basePath . '/finance.xlsx');
            $this->normalizeImportedData();
            if ($backupId !== null) {
                (new BackupManager($this->pdo))->restoreManualRows($backupId);
                $this->normalizeImportedData();
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

        return $this->summaryCounts();
    }

    private function normalizeImportedData(): void
    {
        $ids = new Identifier($this->pdo);
        foreach ($this->pdo->query('SELECT id, entity_type FROM teams WHERE entity_code IS NULL OR entity_code = \'\'')->fetchAll() as $team) {
            $type = (string) ($team['entity_type'] ?? 'team');
            $this->pdo->prepare('UPDATE teams SET entity_code = :code WHERE id = :id')
                ->execute(['code' => $ids->nextEntityCode($type ?: 'team'), 'id' => $team['id']]);
        }
        foreach ($this->pdo->query('SELECT id FROM members WHERE member_code IS NULL OR member_code = \'\'')->fetchAll() as $member) {
            $this->pdo->prepare('UPDATE members SET member_code = :code WHERE id = :id')
                ->execute(['code' => $ids->nextMemberCode(), 'id' => $member['id']]);
        }
        $this->resolveTeamRelations();
        $this->mapDeskAssignmentsFromLegacyText();
        $fiscalYear = (string) ($this->pdo->query('SELECT fiscal_year FROM rate_settings ORDER BY id DESC LIMIT 1')->fetchColumn() ?: '1404');
        (new Seeder($this->pdo, $this->basePath))->recalculateCharges($fiscalYear);
    }

    private function importInnovationCenter(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('فایل Innovation Center.xlsx پیدا نشد.');
        }
        $reader = new XlsxReader($path);
        $file = basename($path);
        $ids = new Identifier($this->pdo);

        for ($row = 6; $row < 20; $row++) {
            $name = $this->clean($reader->value('Teams', "B{$row}"));
            $leader = $this->clean($reader->value('Teams', "C{$row}"));
            if ($name === '' && $leader === '') {
                continue;
            }
            $entityType = $this->inferEntityType($name);
            $this->insert('teams', [
                'entity_code' => $ids->nextEntityCode($entityType),
                'entity_type' => $entityType,
                'name' => $name,
                'leader' => $leader,
                'phone' => $this->clean($reader->value('Teams', "D{$row}")),
                'joined_at' => $this->parseJalaliDate($reader->value('Teams', "H{$row}")),
                'warning' => $this->clean($reader->value('Teams', "I{$row}")),
                'notes' => $this->clean($reader->value('Teams', "J{$row}")),
                'source_file' => $file,
                'source_sheet' => 'Teams',
            ]);
        }

        for ($row = 4; $row <= $reader->maxRow('Members'); $row++) {
            $fullName = $this->clean($reader->value('Members', "C{$row}"));
            if ($fullName === '') {
                continue;
            }
            $this->insert('members', [
                'member_code' => $ids->nextMemberCode(),
                'access_code' => $this->clean($reader->value('Members', "B{$row}")),
                'full_name' => $fullName,
                'phone' => $this->clean($reader->value('Members', "H{$row}")),
                'national_id' => $this->clean($reader->value('Members', "I{$row}")),
                'notes' => $this->clean($reader->value('Members', "J{$row}")),
                'source_file' => $file,
                'source_sheet' => 'Members',
            ]);
        }

        for ($row = 6; $row <= $reader->maxRow('lockers'); $row++) {
            $lockerNumber = $this->parseInt($reader->value('lockers', "A{$row}"));
            if ($lockerNumber === null) {
                continue;
            }
            $this->insert('lockers', [
                'locker_number' => $lockerNumber,
                'status' => $this->clean($reader->value('lockers', "B{$row}")) ?: 'خالی',
                'delivered_at' => $this->parseJalaliDate($reader->value('lockers', "D{$row}")),
                'key_number' => $this->clean($reader->value('lockers', "E{$row}")),
                'spare_key' => $this->clean($reader->value('lockers', "F{$row}")),
                'notes' => $this->clean($reader->value('lockers', "C{$row}")),
                'source_file' => $file,
                'source_sheet' => 'lockers',
            ]);
        }

        for ($row = 6; $row <= $reader->maxRow('plans'); $row++) {
            $title = $this->clean($reader->value('plans', "C{$row}"));
            if ($title === '') {
                continue;
            }
            $this->insert('plans', [
                'plan_code' => $ids->nextPlanCode(),
                'status' => $this->mapPlanStatus($this->clean($reader->value('plans', "B{$row}"))),
                'priority' => 'متوسط',
                'title' => $title,
                'proposed_budget' => $this->parseInt($reader->value('plans', "D{$row}")),
                'start_date' => '',
                'end_date' => $this->clean($reader->value('plans', "F{$row}")),
                'progress' => 0,
                'notes' => $this->clean($reader->value('plans', "E{$row}")),
                'source_file' => $file,
                'source_sheet' => 'plans',
            ]);
        }
    }

    private function importCharges(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('فایل CHARGE.xlsx پیدا نشد.');
        }
        $reader = new XlsxReader($path);
        $file = basename($path);
        $months = [
            ['E', 1], ['F', 2], ['G', 3], ['H', 4], ['I', 5], ['J', 6],
            ['K', 7], ['L', 8], ['M', 9], ['N', 10], ['O', 11], ['P', 12],
        ];

        foreach ($reader->sheetNames() as $sheet) {
            $chargeRate = $this->parseInt($reader->value($sheet, 'T1'));
            $rentRate = $this->parseInt($reader->value($sheet, 'T3'));
            $this->ensureRateSetting($sheet, $chargeRate, $rentRate);
            for ($row = 6; $row <= $reader->maxRow($sheet); $row++) {
                $teamName = $this->clean($reader->value($sheet, "B{$row}"));
                if ($teamName === '') {
                    continue;
                }
                $deskCount = max(1, (int) ($this->parseInt($reader->value($sheet, "D{$row}")) ?? 1));
                foreach ($months as [$column, $monthIndex]) {
                    $amount = $this->parseInt($reader->value($sheet, "{$column}{$row}"));
                    if ($amount === null) {
                        continue;
                    }
                    $chargePart = (int) round($amount * 0.7);
                    $rentPart = $amount - $chargePart;
                    $this->insert('charges', [
                        'team_name' => $teamName,
                        'fiscal_year' => $sheet,
                        'month_index' => $monthIndex,
                        'month_name' => $this->monthName($monthIndex),
                        'charge_amount' => $chargePart,
                        'rent_amount' => $rentPart,
                        'amount' => $amount,
                        'note' => $this->clean($reader->value($sheet, "Q{$row}")),
                        'source_file' => $file,
                        'source_sheet' => $sheet,
                    ]);
                    unset($deskCount);
                }
            }
        }
    }

    private function importFinance(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('فایل finance.xlsx پیدا نشد.');
        }
        $reader = new XlsxReader($path);
        $file = basename($path);

        foreach ($reader->sheetNames() as $sheet) {
            for ($row = 6; $row <= 27; $row++) {
                $description = $this->clean($reader->value($sheet, "F{$row}"));
                $amount = $this->parseInt($reader->value($sheet, "I{$row}"));
                if ($description === '' && $amount === null) {
                    continue;
                }
                $this->insert('transactions', [
                    'tx_date' => $this->composeJalaliDate(
                        $reader->value($sheet, "C{$row}"),
                        $reader->value($sheet, "D{$row}"),
                        $reader->value($sheet, "E{$row}")
                    ),
                    'description' => $description,
                    'amount' => $amount,
                    'notes' => $this->clean($reader->value($sheet, "J{$row}")),
                    'category' => $this->categorizeTransaction($description, $amount),
                    'confirmed' => 1,
                    'source_file' => $file,
                ]);
            }
        }
    }

    private function mapDeskAssignmentsFromLegacyText(): void
    {
        if (!$this->columnExists('members', 'desks')) {
            return;
        }
        $members = $this->pdo->query('SELECT id, desks, team_id FROM members WHERE desks IS NOT NULL AND desks <> \'\'')->fetchAll();
        foreach ($members as $member) {
            preg_match_all('/\d+/', (string) $member['desks'], $matches);
            foreach ($matches[0] ?? [] as $deskNumber) {
                $number = (int) $deskNumber;
                if ($number < 1 || $number > 24) {
                    continue;
                }
                $desk = $this->pdo->prepare('SELECT id, team_id FROM desks WHERE number = :number LIMIT 1');
                $desk->execute(['number' => $number]);
                $deskRow = $desk->fetch();
                if ($deskRow === false) {
                    continue;
                }
                if (empty($deskRow['team_id']) && !empty($member['team_id'])) {
                    $this->pdo->prepare('UPDATE desks SET team_id = :team_id, informal_seats = 2 WHERE id = :id')
                        ->execute(['team_id' => $member['team_id'], 'id' => $deskRow['id']]);
                }
                $this->pdo->prepare('INSERT INTO member_desks (member_id, desk_id) SELECT :member_id, :desk_id WHERE NOT EXISTS (SELECT 1 FROM member_desks WHERE member_id = :member_id2 AND desk_id = :desk_id2)')
                    ->execute([
                        'member_id' => $member['id'],
                        'desk_id' => $deskRow['id'],
                        'member_id2' => $member['id'],
                        'desk_id2' => $deskRow['id'],
                    ]);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query("PRAGMA table_info({$table})")->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }
        $statement = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $statement->execute(['column' => $column]);
        return $statement->fetch() !== false;
    }

    private function excelFilesExist(): bool
    {
        foreach (['Innovation Center.xlsx', 'CHARGE.xlsx', 'finance.xlsx'] as $file) {
            if (!is_file($this->basePath . '/' . $file)) {
                return false;
            }
        }
        return true;
    }

    private function inferEntityType(string $name): string
    {
        if (str_contains($name, 'شرکت')) {
            return 'company';
        }
        if (str_contains($name, 'دانشجو')) {
            return 'student';
        }
        return 'team';
    }

    private function mapPlanStatus(string $status): string
    {
        return match ($status) {
            'در حال اجرا' => 'در حال اجرا',
            'انجام شده' => 'انجام‌شده',
            'لغو شده' => 'لغو شده',
            default => 'پیشنهادی',
        };
    }

    private function resolveTeamRelations(): void
    {
        $teams = $this->pdo->query('SELECT id, name, leader FROM teams')->fetchAll();
        $teamByName = [];
        foreach ($teams as $team) {
            $key = $this->normalizeName($team['name'] ?? '');
            if ($key !== '') {
                $teamByName[$key] = (int) $team['id'];
            }
        }

        if ($this->columnExists('members', 'team_name')) {
            $rows = $this->pdo->query('SELECT id, team_name FROM members')->fetchAll();
            $update = $this->pdo->prepare('UPDATE members SET team_id = :team_id WHERE id = :id');
            foreach ($rows as $row) {
                $key = $this->normalizeName($row['team_name'] ?? '');
                if ($key !== '' && isset($teamByName[$key])) {
                    $update->execute(['team_id' => $teamByName[$key], 'id' => $row['id']]);
                }
            }
        }

        $rows = $this->pdo->query("SELECT id, team_name FROM charges WHERE team_name IS NOT NULL AND team_name <> ''")->fetchAll();
        $update = $this->pdo->prepare('UPDATE charges SET team_id = :team_id WHERE id = :id');
        foreach ($rows as $row) {
            $key = $this->normalizeName($row['team_name'] ?? '');
            if ($key !== '' && isset($teamByName[$key])) {
                $update->execute(['team_id' => $teamByName[$key], 'id' => $row['id']]);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function summaryCounts(): array
    {
        return [
            'members' => $this->count('members'),
            'teams' => $this->count('teams'),
            'desks' => $this->count('desks'),
            'lockers' => $this->count('lockers'),
            'plans' => $this->count('plans'),
            'charges' => $this->count('charges'),
            'transactions' => $this->count('transactions'),
        ];
    }

    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            Sql::columnList($columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($data);
    }

    private function ensureRateSetting(string $fiscalYear, ?int $chargeRate, ?int $rentRate): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM rate_settings WHERE fiscal_year = :fiscal_year LIMIT 1');
        $statement->execute(['fiscal_year' => $fiscalYear]);
        if ($statement->fetchColumn() !== false) {
            return;
        }
        $this->insert('rate_settings', [
            'fiscal_year' => $fiscalYear,
            'title' => 'نرخ واردشده از Excel',
            'charge_rate' => $chargeRate,
            'informal_rent_rate' => $rentRate,
            'effective_from' => $fiscalYear . '/01/01',
            'notes' => 'ایجاد خودکار هنگام import',
        ]);
    }

    private function hasImportedData(): bool
    {
        return $this->count('teams') > 0 || $this->count('members') > 0;
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function monthName(int $index): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];
        return $months[$index] ?? '';
    }

    private function normalizeName(mixed $value): string
    {
        $text = $this->clean($value);
        $text = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $text);
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    private function parseInt(mixed $value): ?int
    {
        $text = str_replace([',', '٬'], '', $this->clean($value));
        if ($text === '' || !preg_match('/^-?\d+(\.0+)?$/', $text)) {
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

    private function categorizeTransaction(string $description, ?int $amount): string
    {
        if ($amount === null) {
            return 'نامشخص';
        }
        if ($amount < 0) {
            return 'هزینه';
        }
        foreach (['واریز', 'شارژ', 'آبونمان', 'سود'] as $keyword) {
            if (str_contains($description, $keyword)) {
                return str_contains($description, 'واریز') ? 'واریز تیم' : 'درآمد';
            }
        }
        return 'درآمد';
    }
}
