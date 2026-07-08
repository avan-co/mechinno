<?php

declare(strict_types=1);

final class TeamContracts
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function currentFiscalYear(): string
    {
        return (string) JalaliDate::todayParts()['year'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contractForYear(int $teamId, string $fiscalYear): ?array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $row = $this->pdo->prepare(
            'SELECT * FROM team_contracts WHERE team_id = :team_id AND fiscal_year = :fiscal_year LIMIT 1'
        );
        $row->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        $contract = $row->fetch();

        return $contract === false ? null : $contract;
    }

    /**
     * @return array{start:string,end:string}
     */
    public function contractDatesForYear(int $teamId, string $fiscalYear): array
    {
        $contract = $this->contractForYear($teamId, $fiscalYear);
        if ($contract === null) {
            return ['start' => '', 'end' => ''];
        }

        return [
            'start' => (string) ($contract['contract_start'] ?? ''),
            'end' => (string) ($contract['contract_end'] ?? ''),
        ];
    }

    public function hasContractInYear(int $teamId, string $fiscalYear): bool
    {
        return $this->contractForYear($teamId, $fiscalYear) !== null;
    }

    /**
     * @return list<string>
     */
    public function contractYearsForTeam(int $teamId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT fiscal_year FROM team_contracts WHERE team_id = :team_id ORDER BY fiscal_year DESC'
        );
        $statement->execute(['team_id' => $teamId]);

        return array_map(static fn (array $row): string => (string) $row['fiscal_year'], $statement->fetchAll());
    }

    /**
     * @return list<int>
     */
    public function teamIdsWithContractInYear(string $fiscalYear): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $statement = $this->pdo->prepare('SELECT team_id FROM team_contracts WHERE fiscal_year = :fiscal_year');
        $statement->execute(['fiscal_year' => $fiscalYear]);

        return array_map(static fn (array $row): int => (int) $row['team_id'], $statement->fetchAll());
    }

    public function hasDeskInFiscalYear(int $teamId, string $fiscalYear): bool
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        for ($month = 1; $month <= 12; $month++) {
            if ($this->deskCountForMonth($teamId, $fiscalYear, $month) > 0) {
                return true;
            }
        }

        return false;
    }

    public function deskCountForMonth(int $teamId, string $fiscalYear, int $monthIndex): int
    {
        $deskNumbers = [];
        foreach ($this->deskAssignmentsForTeamInYear($teamId, $fiscalYear) as $assignment) {
            if (!$this->assignmentOverlapsMonth($assignment, $fiscalYear, $monthIndex)) {
                continue;
            }
            $deskNumber = (int) ($assignment['desk_number'] ?? 0);
            if ($deskNumber > 0) {
                $deskNumbers[$deskNumber] = true;
            }
        }

        return count($deskNumbers);
    }

    public function informalDeskCountForMonth(int $teamId, string $fiscalYear, int $monthIndex): int
    {
        $informalDesks = [];
        foreach ($this->deskAssignmentsForTeamInYear($teamId, $fiscalYear) as $assignment) {
            if (!$this->assignmentOverlapsMonth($assignment, $fiscalYear, $monthIndex)) {
                continue;
            }
            $usage = (string) ($assignment['usage_type'] ?? 'formal');
            if (!in_array($usage, ['informal', 'mixed'], true)) {
                continue;
            }
            $deskNumber = (int) ($assignment['desk_number'] ?? 0);
            if ($deskNumber > 0) {
                $informalDesks[$deskNumber] = true;
            }
        }

        return count($informalDesks);
    }

    public function hasInformalDeskInYear(int $teamId, string $fiscalYear): bool
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        for ($month = 1; $month <= 12; $month++) {
            if ($this->informalDeskCountForMonth($teamId, $fiscalYear, $month) > 0) {
                return true;
            }
        }

        return false;
    }

    public function assertCanAssignDesk(int $teamId): void
    {
        $this->assertCanAssignDeskForYear($teamId, $this->currentFiscalYear());
    }

    public function assertCanAssignDeskForYear(int $teamId, string $fiscalYear): void
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        if (!$this->hasContractInYear($teamId, $fiscalYear)) {
            throw new InvalidArgumentException(
                'برای تخصیص میز، نهاد باید قرارداد سال ' . $fiscalYear . ' داشته باشد.'
            );
        }
    }

    public function assertCanFreeDesk(int $teamId): void
    {
        $year = $this->currentFiscalYear();
        $contract = $this->contractForYear($teamId, $year);
        if ($contract === null) {
            return;
        }
        $end = (string) ($contract['contract_end'] ?? '');
        if ($end === '') {
            return;
        }
        $today = JalaliDate::todayParts()['formatted'];
        if (JalaliDate::compare($today, $end) <= 0) {
            throw new InvalidArgumentException(
                'تا پایان قرارداد سال ' . $year . ' (' . $end . ') امکان آزاد کردن میز وجود ندارد. میز را می‌توانید به نهاد دیگر منتقل کنید.'
            );
        }
    }

    public function syncTeamContractCache(int $teamId): void
    {
        $year = $this->currentFiscalYear();
        $contract = $this->contractForYear($teamId, $year);
        if ($contract === null) {
            $latest = $this->pdo->prepare(
                'SELECT contract_start, contract_end FROM team_contracts
                 WHERE team_id = :team_id ORDER BY fiscal_year DESC LIMIT 1'
            );
            $latest->execute(['team_id' => $teamId]);
            $contract = $latest->fetch() ?: null;
        }
        $start = $contract ? (string) ($contract['contract_start'] ?? '') : '';
        $end = $contract ? (string) ($contract['contract_end'] ?? '') : '';
        $this->pdo->prepare('UPDATE teams SET contract_start = :start, contract_end = :end WHERE id = :id')
            ->execute(['start' => $start !== '' ? $start : null, 'end' => $end !== '' ? $end : null, 'id' => $teamId]);
        $this->syncTeamActiveStatus($teamId);
    }

    public function syncTeamActiveStatus(int $teamId): void
    {
        $isActive = $this->hasContractInYear($teamId, $this->currentFiscalYear()) ? 1 : 0;
        $this->pdo->prepare('UPDATE teams SET is_active = :active WHERE id = :id')
            ->execute(['active' => $isActive, 'id' => $teamId]);
    }

    public function syncAllTeamActiveStatuses(): void
    {
        foreach ($this->pdo->query('SELECT id FROM teams')->fetchAll() as $row) {
            $this->syncTeamActiveStatus((int) $row['id']);
        }
    }

    public function migrateFromLegacyTeamDates(): void
    {
        if (!$this->tableExists() || $this->legacyContractsMigrated()) {
            return;
        }

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM team_contracts WHERE team_id = :team_id');
        $teams = $this->pdo->query('SELECT id, contract_start, contract_end FROM teams')->fetchAll();
        foreach ($teams as $team) {
            $teamId = (int) $team['id'];
            $countStatement->execute(['team_id' => $teamId]);
            if ((int) $countStatement->fetchColumn() > 0) {
                continue;
            }

            $start = JalaliDate::tryNormalize((string) ($team['contract_start'] ?? ''));
            $end = JalaliDate::tryNormalize((string) ($team['contract_end'] ?? ''));
            if ($start === '' && $end === '') {
                continue;
            }
            $fiscalYear = $start !== '' ? substr($start, 0, 4) : substr($end, 0, 4);
            if ($fiscalYear === '') {
                continue;
            }
            if ($start === '') {
                $start = sprintf('%s/01/01', $fiscalYear);
            }
            if ($end === '') {
                $end = sprintf('%s/12/29', (int) $fiscalYear);
            }
            $this->upsertContract($teamId, $fiscalYear, $start, $end, 'مهاجرت از تاریخ قرارداد قبلی');
        }

        $this->markLegacyContractsMigrated();
    }

    public function upsertContract(int $teamId, string $fiscalYear, string $start, string $end, ?string $notes = null): void
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $start = JalaliDate::normalize($start);
        $end = JalaliDate::normalize($end);
        $existing = $this->contractForYear($teamId, $fiscalYear);
        if ($existing !== null) {
            $this->pdo->prepare(
                'UPDATE team_contracts SET contract_start = :start, contract_end = :end, notes = :notes WHERE id = :id'
            )->execute([
                'start' => $start,
                'end' => $end,
                'notes' => $notes,
                'id' => (int) $existing['id'],
            ]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO team_contracts (team_id, fiscal_year, contract_start, contract_end, notes, created_at)
                 VALUES (:team_id, :fiscal_year, :start, :end, :notes, :created_at)'
            )->execute([
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
                'start' => $start,
                'end' => $end,
                'notes' => $notes,
                'created_at' => JalaliDate::todayParts()['formatted'],
            ]);
        }
        $this->syncTeamContractCache($teamId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deskAssignmentsForTeamInYear(int $teamId, string $fiscalYear): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = $fiscalYear . '/12/29';
        $assignments = $this->pdo->prepare(
            'SELECT desk_number, usage_type, assigned_from, assigned_until
             FROM desk_assignments
             WHERE team_id = :team_id
               AND assigned_from <= :year_end
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)
             ORDER BY assigned_from DESC'
        );
        $assignments->execute([
            'team_id' => $teamId,
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ]);
        $rows = $assignments->fetchAll();
        if ($rows !== []) {
            return $rows;
        }

        if ($fiscalYear !== $this->currentFiscalYear()) {
            return [];
        }

        return $this->deskAssignmentsFromCurrentDesks($teamId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deskAssignmentsFromCurrentDesks(int $teamId): array
    {
        $fiscalYear = $this->currentFiscalYear();
        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = $fiscalYear . '/12/29';
        $desks = $this->pdo->prepare('SELECT id, number, usage_type FROM desks WHERE team_id = :team_id');
        $desks->execute(['team_id' => $teamId]);
        $deskList = $desks->fetchAll();
        if ($deskList === []) {
            return [];
        }

        $assignmentStatement = $this->pdo->prepare(
            'SELECT assigned_from, assigned_until
             FROM desk_assignments
             WHERE desk_id = :desk_id
               AND team_id = :team_id
               AND assigned_from <= :year_end
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)
             ORDER BY assigned_from DESC, id DESC
             LIMIT 1'
        );

        $assignments = [];
        foreach ($deskList as $desk) {
            $assignmentStatement->execute([
                'desk_id' => (int) ($desk['id'] ?? 0),
                'team_id' => $teamId,
                'year_start' => $yearStart,
                'year_end' => $yearEnd,
            ]);
            $assignment = $assignmentStatement->fetch();
            if ($assignment === false) {
                continue;
            }

            $assignments[] = [
                'desk_number' => (int) ($desk['number'] ?? 0),
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'assigned_from' => (string) ($assignment['assigned_from'] ?? ''),
                'assigned_until' => (string) ($assignment['assigned_until'] ?? ''),
            ];
        }

        return $assignments;
    }

    private function legacyContractsMigrated(): bool
    {
        if (!$this->centerSettingsColumnExists('legacy_team_contracts_migrated')) {
            return false;
        }

        return (int) $this->pdo->query(
            'SELECT legacy_team_contracts_migrated FROM center_settings WHERE id = 1'
        )->fetchColumn() === 1;
    }

    private function markLegacyContractsMigrated(): void
    {
        if (!$this->centerSettingsColumnExists('legacy_team_contracts_migrated')) {
            return;
        }

        $this->pdo->exec('UPDATE center_settings SET legacy_team_contracts_migrated = 1 WHERE id = 1');
    }

    private function centerSettingsColumnExists(string $column): bool
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $rows = $this->pdo->query('PRAGMA table_info(center_settings)')->fetchAll();
                foreach ($rows as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }

                return false;
            }

            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => 'center_settings', 'column' => $column]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function assignmentOverlapsMonth(array $assignment, string $fiscalYear, int $monthIndex): bool
    {
        $monthStart = JalaliDate::monthStart($fiscalYear, $monthIndex);
        $monthEnd = JalaliDate::monthEnd($fiscalYear, $monthIndex);
        $from = JalaliDate::tryNormalize((string) ($assignment['assigned_from'] ?? ''));
        $until = JalaliDate::tryNormalize((string) ($assignment['assigned_until'] ?? ''));
        if ($from !== '' && JalaliDate::compare($monthEnd, $from) < 0) {
            return false;
        }
        if ($until !== '' && JalaliDate::compare($monthStart, $until) > 0) {
            return false;
        }

        return true;
    }

    private function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM team_contracts LIMIT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
