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
        $this->pdo->prepare('DELETE FROM charges WHERE fiscal_year = :fiscal_year AND source_file = :source')
            ->execute(['fiscal_year' => $fiscalYear, 'source' => 'system']);

        $manualCheck = $this->pdo->prepare(
            'SELECT id FROM charges
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND month_index = :month_index
               AND source_file = :source LIMIT 1'
        );

        $teams = $this->pdo->query('SELECT id, contract_start, contract_end FROM teams')->fetchAll();
        foreach ($teams as $team) {
            $teamId = (int) $team['id'];
            $contractStart = (string) ($team['contract_start'] ?? '');
            $contractEnd = (string) ($team['contract_end'] ?? '');
            $amounts = $this->monthlyAmountsForTeam($teamId, $fiscalYear, $contractStart, $contractEnd);
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
                $this->insert('charges', [
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'month_index' => $monthIndex,
                    'month_name' => JalaliDate::monthName($monthIndex),
                    'charge_amount' => $parts['charge_amount'],
                    'rent_amount' => $parts['rent_amount'],
                    'amount' => $parts['amount'],
                    'note' => '',
                    'source_file' => 'system',
                    'source_sheet' => 'auto',
                ]);
            }
        }
    }

    /**
     * @return array<int, array{charge_amount:int, rent_amount:int, amount:int}>
     */
    public function monthlyAmountsForTeam(
        int $teamId,
        string $fiscalYear,
        ?string $contractStart = null,
        ?string $contractEnd = null
    ): array {
        $assignments = $this->pdo->prepare(
            'SELECT desk_number, usage_type, assigned_from, assigned_until
             FROM desk_assignments WHERE team_id = :team_id'
        );
        $assignments->execute(['team_id' => $teamId]);
        $rows = $assignments->fetchAll();
        if ($rows === []) {
            $deskRows = $this->pdo->prepare('SELECT number, usage_type FROM desks WHERE team_id = :team_id');
            $deskRows->execute(['team_id' => $teamId]);
            $deskList = $deskRows->fetchAll();
            if ($deskList === []) {
                return [];
            }
            $today = JalaliDate::todayParts()['formatted'];
            $fallbackFrom = JalaliDate::tryNormalize($contractStart ?? '');
            if ($fallbackFrom === '') {
                $fallbackFrom = sprintf('%s/01/01', JalaliDate::normalizeDigits($fiscalYear));
            }
            $rows = array_map(static fn (array $desk): array => [
                'desk_number' => (int) $desk['number'],
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'assigned_from' => $fallbackFrom,
                'assigned_until' => $contractEnd,
            ], $deskList);
        }

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            if (!JalaliDate::monthInContract($fiscalYear, $month, $contractStart, $contractEnd)) {
                continue;
            }
            $deskCount = 0;
            $informalDeskCount = 0;
            foreach ($rows as $assignment) {
                if (!$this->assignmentOverlapsMonth($assignment, $fiscalYear, $month)) {
                    continue;
                }
                $deskCount++;
                $usage = (string) ($assignment['usage_type'] ?? 'formal');
                if (in_array($usage, ['informal', 'mixed'], true)) {
                    $informalDeskCount++;
                }
            }
            if ($deskCount === 0) {
                continue;
            }
            $rates = $this->ratesForMonth($fiscalYear, $month);
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
    private function ratesForMonth(string $fiscalYear, int $monthIndex): array
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
