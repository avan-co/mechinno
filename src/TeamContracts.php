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
            'SELECT desk_number, usage_type, assigned_from, assigned_until' . $this->deskAssignmentExemptSelect() . '
             FROM desk_assignments
             WHERE team_id = :team_id
               AND assigned_from <= :year_end
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)
             ORDER BY assigned_from DESC, CASE WHEN assigned_until IS NULL OR assigned_until = \'\' THEN 1 ELSE 0 END, assigned_until DESC'
        );
        $assignments->execute([
            'team_id' => $teamId,
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ]);
        $rows = $this->normalizeDeskAssignmentRows($assignments->fetchAll());
        if ($rows !== []) {
            // Keep sequential segments for the same desk (e.g. Farvardin–Shahrivar then Mehr–Esfand).
            // Month-level billing/counts already unique by desk_number within each month.
            return $rows;
        }

        if ($fiscalYear !== $this->currentFiscalYear()) {
            return [];
        }

        return $this->deskAssignmentsFromCurrentDesks($teamId);
    }

    /**
     * @return array{
     *   has_custom_rates:bool,
     *   has_exemptions:bool,
     *   has_billing_adjustments:bool,
     *   charge_rate_override:?int,
     *   informal_rent_rate_override:?int,
     *   exempt_desks:list<array{desk_number:int, charge_exempt:bool, rent_exempt:bool}>,
     *   labels:list<string>,
     *   summary_text:string
     * }
     */
    public function billingSummaryForTeamInYear(int $teamId, string $fiscalYear): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $contract = $this->contractForYear($teamId, $fiscalYear);
        $chargeOverride = $this->nullableRate(is_array($contract) ? ($contract['charge_rate_override'] ?? null) : null);
        $rentOverride = $this->nullableRate(is_array($contract) ? ($contract['informal_rent_rate_override'] ?? null) : null);
        $labels = [];
        if ($chargeOverride !== null) {
            $labels[] = 'نرخ شارژ اختصاصی: ' . number_format($chargeOverride);
        }
        if ($rentOverride !== null) {
            $labels[] = 'نرخ اجاره اختصاصی: ' . number_format($rentOverride);
        }

        $exemptDesks = [];
        foreach ($this->deskAssignmentsForTeamInYear($teamId, $fiscalYear) as $assignment) {
            $deskNumber = (int) ($assignment['desk_number'] ?? 0);
            if ($deskNumber <= 0) {
                continue;
            }
            $chargeExempt = $this->isExemptFlag($assignment['charge_exempt'] ?? 0);
            $rentExempt = $this->isExemptFlag($assignment['rent_exempt'] ?? 0);
            if (!$chargeExempt && !$rentExempt) {
                continue;
            }
            $exemptDesks[$deskNumber] = [
                'desk_number' => $deskNumber,
                'charge_exempt' => $chargeExempt,
                'rent_exempt' => $rentExempt,
            ];
            if ($chargeExempt && $rentExempt) {
                $labels[] = 'میز ' . $deskNumber . ': معاف شارژ و اجاره';
            } elseif ($chargeExempt) {
                $labels[] = 'میز ' . $deskNumber . ': معاف شارژ';
            } else {
                $labels[] = 'میز ' . $deskNumber . ': معاف اجاره';
            }
        }

        return [
            'has_custom_rates' => $chargeOverride !== null || $rentOverride !== null,
            'has_exemptions' => $exemptDesks !== [],
            'has_billing_adjustments' => $chargeOverride !== null || $rentOverride !== null || $exemptDesks !== [],
            'charge_rate_override' => $chargeOverride,
            'informal_rent_rate_override' => $rentOverride,
            'exempt_desks' => array_values($exemptDesks),
            'labels' => $labels,
            'summary_text' => $labels === [] ? '' : implode(' · ', $labels),
        ];
    }

    /**
     * @param array{charge_rate:int, informal_rent_rate:int} $globalRates
     * @return array{charge_rate:int, informal_rent_rate:int, uses_custom_charge_rate:bool, uses_custom_rent_rate:bool}
     */
    public function ratesForTeamInMonth(int $teamId, string $fiscalYear, array $globalRates): array
    {
        $contract = $this->contractForYear($teamId, $fiscalYear);
        $chargeOverride = $this->nullableRate(is_array($contract) ? ($contract['charge_rate_override'] ?? null) : null);
        $rentOverride = $this->nullableRate(is_array($contract) ? ($contract['informal_rent_rate_override'] ?? null) : null);

        return [
            'charge_rate' => $chargeOverride ?? (int) ($globalRates['charge_rate'] ?? 0),
            'informal_rent_rate' => $rentOverride ?? (int) ($globalRates['informal_rent_rate'] ?? 0),
            'uses_custom_charge_rate' => $chargeOverride !== null,
            'uses_custom_rent_rate' => $rentOverride !== null,
        ];
    }

    private function nullableRate(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function isExemptFlag(mixed $value): bool
    {
        return (int) $value === 1;
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
            'SELECT assigned_from, assigned_until' . $this->deskAssignmentExemptSelect() . '
             FROM desk_assignments
             WHERE desk_id = :desk_id
               AND team_id = :team_id
               AND assigned_from <= :year_end
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)
             ORDER BY CASE WHEN assigned_until IS NULL OR assigned_until = \'\' THEN 1 ELSE 0 END, assigned_from DESC, id DESC
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
            $assignment = $this->normalizeDeskAssignmentRows([$assignment])[0];

            $assignments[] = [
                'desk_number' => (int) ($desk['number'] ?? 0),
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'assigned_from' => (string) ($assignment['assigned_from'] ?? ''),
                'assigned_until' => (string) ($assignment['assigned_until'] ?? ''),
                'charge_exempt' => (int) ($assignment['charge_exempt'] ?? 0),
                'rent_exempt' => (int) ($assignment['rent_exempt'] ?? 0),
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

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function dedupeAssignmentsByDesk(array $rows): array
    {
        $byDesk = [];
        foreach ($rows as $row) {
            $deskNumber = (int) ($row['desk_number'] ?? 0);
            if ($deskNumber <= 0) {
                continue;
            }
            $key = (string) $deskNumber;
            if (!isset($byDesk[$key])) {
                $byDesk[$key] = $row;
                continue;
            }
            $byDesk[$key] = $this->preferAssignmentRow($byDesk[$key], $row);
        }

        return array_values($byDesk);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private function preferAssignmentRow(array $left, array $right): array
    {
        $leftOpen = $this->isOpenEndedAssignment($left);
        $rightOpen = $this->isOpenEndedAssignment($right);
        if ($leftOpen !== $rightOpen) {
            return $leftOpen ? $right : $left;
        }

        $leftFrom = JalaliDate::tryNormalize((string) ($left['assigned_from'] ?? ''));
        $rightFrom = JalaliDate::tryNormalize((string) ($right['assigned_from'] ?? ''));
        if ($leftFrom !== $rightFrom) {
            return JalaliDate::compare($rightFrom, $leftFrom) > 0 ? $right : $left;
        }

        return $right;
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function isOpenEndedAssignment(array $assignment): bool
    {
        $until = JalaliDate::tryNormalize((string) ($assignment['assigned_until'] ?? ''));

        return $until === '';
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

    public function contractRateOverrideSelect(string $alias = ''): string
    {
        if (!Schema::hasColumn($this->pdo, 'team_contracts', 'charge_rate_override')
            || !Schema::hasColumn($this->pdo, 'team_contracts', 'informal_rent_rate_override')) {
            return '';
        }

        $prefix = $alias !== '' ? $alias . '.' : '';

        return ', ' . $prefix . 'charge_rate_override, ' . $prefix . 'informal_rent_rate_override';
    }

    private function deskAssignmentExemptSelect(): string
    {
        return Schema::deskAssignmentExemptSelect($this->pdo);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeDeskAssignmentRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => Schema::normalizeDeskAssignmentRow($row),
            $rows
        );
    }
}
