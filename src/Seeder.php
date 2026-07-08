<?php

declare(strict_types=1);

final class Seeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recalculateCharges(string $fiscalYear): void
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $contracts = new TeamContracts($this->pdo);
        $teamIds = array_unique(array_merge(
            $contracts->teamIdsWithContractInYear($fiscalYear),
            $this->teamIdsWithSystemChargesInYear($fiscalYear)
        ));
        foreach ($teamIds as $teamId) {
            $this->recalculateChargesForTeam((int) $teamId, $fiscalYear, true);
        }
    }

    /**
     * @return list<int>
     */
    private function teamIdsWithSystemChargesInYear(string $fiscalYear): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT team_id FROM charges
             WHERE fiscal_year = :fiscal_year AND source_file = :source AND team_id IS NOT NULL'
        );
        $statement->execute(['fiscal_year' => $fiscalYear, 'source' => 'system']);

        return array_map(static fn (array $row): int => (int) ($row['team_id'] ?? 0), $statement->fetchAll());
    }

    public function recalculateChargesForTeam(int $teamId, string $fiscalYear, bool $deleteExisting = true): void
    {
        if ($teamId <= 0) {
            return;
        }

        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        if ($deleteExisting) {
            $this->pdo->prepare(
                'DELETE FROM charges WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND source_file = :source'
            )->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear, 'source' => 'system']);
        }

        $manualCheck = $this->pdo->prepare(
            'SELECT id FROM charges
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND month_index = :month_index
               AND source_file = :source LIMIT 1'
        );

        $contracts = new TeamContracts($this->pdo);
        if (!$contracts->hasContractInYear($teamId, $fiscalYear) || !$contracts->hasDeskInFiscalYear($teamId, $fiscalYear)) {
            return;
        }

        $amounts = $this->monthlyAmountsForTeam($teamId, $fiscalYear);
        $existingSystem = $this->pdo->prepare(
            'SELECT id, month_index FROM charges
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND source_file = :source'
        );
        $existingSystem->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear, 'source' => 'system']);
        $systemByMonth = [];
        foreach ($existingSystem->fetchAll() as $row) {
            $systemByMonth[(int) ($row['month_index'] ?? 0)] = (int) ($row['id'] ?? 0);
        }

        $updateSystem = $this->pdo->prepare(
            'UPDATE charges
             SET month_name = :month_name, charge_amount = :charge_amount, rent_amount = :rent_amount, amount = :amount
             WHERE id = :id'
        );

        foreach ($amounts as $monthIndex => $parts) {
            if (($parts['amount'] ?? 0) <= 0) {
                continue;
            }
            $manualCheck->execute([
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
                'month_index' => $monthIndex,
                'source' => 'manual',
            ]);
            if ($manualCheck->fetchColumn() !== false) {
                continue;
            }
            $payload = [
                'month_name' => JalaliDate::monthName($monthIndex),
                'charge_amount' => $parts['charge_amount'],
                'rent_amount' => $parts['rent_amount'],
                'amount' => $parts['amount'],
            ];
            if (isset($systemByMonth[$monthIndex])) {
                $updateSystem->execute($payload + ['id' => $systemByMonth[$monthIndex]]);
                unset($systemByMonth[$monthIndex]);
                continue;
            }
            $this->insert('charges', [
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
                'month_index' => $monthIndex,
                'month_name' => $payload['month_name'],
                'charge_amount' => $payload['charge_amount'],
                'rent_amount' => $payload['rent_amount'],
                'amount' => $payload['amount'],
                'note' => '',
                'source_file' => 'system',
                'source_sheet' => 'auto',
            ]);
        }

        if ($systemByMonth !== []) {
            $deleteStale = $this->pdo->prepare('DELETE FROM charges WHERE id = :id');
            foreach ($systemByMonth as $systemId) {
                $deleteStale->execute(['id' => $systemId]);
            }
        }
    }

    /**
     * @return array<int, array{charge_amount:int, rent_amount:int, amount:int}>
     */
    public function monthlyAmountsForTeam(int $teamId, string $fiscalYear): array
    {
        $contracts = new TeamContracts($this->pdo);
        $rows = $contracts->deskAssignmentsForTeamInYear($teamId, $fiscalYear);
        if ($rows === []) {
            return [];
        }

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $desksInMonth = [];
            foreach ($rows as $assignment) {
                if (!$this->assignmentOverlapsMonth($assignment, $fiscalYear, $month)) {
                    continue;
                }
                $deskNumber = (int) ($assignment['desk_number'] ?? 0);
                $key = $deskNumber > 0 ? (string) $deskNumber : 'row:' . count($desksInMonth);
                $desksInMonth[$key] = (string) ($assignment['usage_type'] ?? 'formal');
            }
            $deskCount = count($desksInMonth);
            $informalDeskCount = 0;
            foreach ($desksInMonth as $usage) {
                if (in_array($usage, ['informal', 'mixed'], true)) {
                    $informalDeskCount++;
                }
            }
            if ($deskCount === 0) {
                continue;
            }
            $rates = $this->ratesForMonthInternal($fiscalYear, $month);
            $chargeRate = (int) ($rates['charge_rate'] ?? 0);
            $rentRate = (int) ($rates['informal_rent_rate'] ?? 0);
            $monthlyCharge = $deskCount * $chargeRate;
            $monthlyRent = $informalDeskCount * $rentRate;
            $months[$month] = [
                'charge_amount' => $monthlyCharge,
                'rent_amount' => $monthlyRent,
                'amount' => $monthlyCharge + $monthlyRent,
            ];
        }

        return $months;
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function assignmentOverlapsMonth(array $assignment, string $fiscalYear, int $monthIndex): bool
    {
        $monthStart = JalaliDate::monthStart($fiscalYear, $monthIndex);
        $monthEnd = JalaliDate::monthEnd($fiscalYear, $monthIndex);
        $from = JalaliDate::tryNormalize($assignment['assigned_from'] ?? '');
        $until = JalaliDate::tryNormalize($assignment['assigned_until'] ?? '');
        if ($from !== '' && JalaliDate::compare($monthEnd, $from) < 0) {
            return false;
        }
        if ($until !== '' && JalaliDate::compare($monthStart, $until) > 0) {
            return false;
        }

        return true;
    }

    /**
     * @return array{charge_rate:int, informal_rent_rate:int}
     */
    public function ratesForMonth(string $fiscalYear, int $monthIndex): array
    {
        return $this->ratesForMonthInternal($fiscalYear, $monthIndex);
    }

    /**
     * @return array{charge_rate:int, informal_rent_rate:int}
     */
    private function ratesForMonthInternal(string $fiscalYear, int $monthIndex): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $statement = $this->pdo->prepare(
            'SELECT charge_rate, informal_rent_rate, effective_from
             FROM rate_settings
             WHERE fiscal_year = :fiscal_year
             ORDER BY COALESCE(effective_from, :year_start) ASC, id ASC'
        );
        $yearStart = sprintf('%s/01/01', $fiscalYear);
        $statement->execute(['fiscal_year' => $fiscalYear, 'year_start' => $yearStart]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            return ['charge_rate' => 0, 'informal_rent_rate' => 0];
        }

        $monthStart = JalaliDate::monthStart($fiscalYear, $monthIndex);
        $applicable = null;
        foreach ($rows as $row) {
            $effectiveFrom = JalaliDate::tryNormalize($row['effective_from'] ?? '');
            if ($effectiveFrom === '') {
                $effectiveFrom = $yearStart;
            }
            if (JalaliDate::compare($effectiveFrom, $monthStart) <= 0) {
                $applicable = $row;
            }
        }

        if ($applicable === null) {
            return ['charge_rate' => 0, 'informal_rent_rate' => 0];
        }

        return [
            'charge_rate' => (int) ($applicable['charge_rate'] ?? 0),
            'informal_rent_rate' => (int) ($applicable['informal_rent_rate'] ?? 0),
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
        $this->pdo->prepare($sql)->execute($data);
    }
}
