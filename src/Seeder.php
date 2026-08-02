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
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $manualCheck = $this->pdo->prepare(
                'SELECT id FROM charges
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND month_index = :month_index
                   AND source_file = :source LIMIT 1'
            );

            $contracts = new TeamContracts($this->pdo);
            // Guard before wipe: removing the last desk/contract must not erase historical system charges.
            // Still drop unsupported *future* months in the current year so debt does not inflate.
            if (!$contracts->hasContractInYear($teamId, $fiscalYear) || !$contracts->hasDeskInFiscalYear($teamId, $fiscalYear)) {
                $this->pruneFutureSystemChargesWithoutDesk($teamId, $fiscalYear);
                if ($startedTransaction) {
                    $this->pdo->commit();
                }

                return;
            }

            // Upsert in place — never wipe all system charges first. Shrinking desk coverage
            // must not erase already-accrued past/current months (payments would become overpay).
            // Callers may still pass $deleteExisting; it is ignored intentionally.
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
                 SET month_name = :month_name, charge_amount = :charge_amount, rent_amount = :rent_amount,
                     amount = :amount, note = :note
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
                    // Manual charge wins: remove any leftover system row for the same month.
                    if (isset($systemByMonth[$monthIndex])) {
                        $this->pdo->prepare('DELETE FROM charges WHERE id = :id')
                            ->execute(['id' => $systemByMonth[$monthIndex]]);
                        unset($systemByMonth[$monthIndex]);
                    }
                    continue;
                }
                $payload = [
                    'month_name' => JalaliDate::monthName($monthIndex),
                    'charge_amount' => $parts['charge_amount'],
                    'rent_amount' => $parts['rent_amount'],
                    'amount' => $parts['amount'],
                    'note' => (string) ($parts['note'] ?? ''),
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
                    'note' => $payload['note'],
                    'source_file' => 'system',
                    'source_sheet' => 'auto',
                ]);
            }

            if ($systemByMonth !== []) {
                $today = JalaliDate::todayParts();
                $currentYear = (string) ($today['year'] ?? '');
                $currentMonth = (int) ($today['month'] ?? 0);
                $deleteStale = $this->pdo->prepare('DELETE FROM charges WHERE id = :id');
                foreach ($systemByMonth as $monthIndex => $systemId) {
                    // Only prune uncovered *future* months; keep past/current accrued charges.
                    $isFuture = ($fiscalYear === $currentYear && (int) $monthIndex > $currentMonth)
                        || ($currentYear !== '' && strcmp($fiscalYear, $currentYear) > 0);
                    if ($isFuture) {
                        $deleteStale->execute(['id' => $systemId]);
                    }
                }
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Keep past/current system charges when desks disappear; drop future months that can no longer accrue.
     */
    private function pruneFutureSystemChargesWithoutDesk(int $teamId, string $fiscalYear): void
    {
        $today = JalaliDate::todayParts();
        $currentYear = (string) ($today['year'] ?? '');
        if ($fiscalYear !== $currentYear) {
            return;
        }
        $currentMonth = (int) ($today['month'] ?? 0);
        if ($currentMonth < 1 || $currentMonth > 12) {
            return;
        }
        $this->pdo->prepare(
            'DELETE FROM charges
             WHERE team_id = :team_id
               AND fiscal_year = :fiscal_year
               AND source_file = :source
               AND month_index > :month_index'
        )->execute([
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'source' => 'system',
            'month_index' => $currentMonth,
        ]);
    }

    /**
     * @return array<int, array{charge_amount:int, rent_amount:int, amount:int, note:string}>
     */
    public function monthlyAmountsForTeam(int $teamId, string $fiscalYear): array
    {
        $contracts = new TeamContracts($this->pdo);
        $rows = $contracts->deskAssignmentsForTeamInYear($teamId, $fiscalYear);
        if ($rows === []) {
            return [];
        }

        $billingSummary = $contracts->billingSummaryForTeamInYear($teamId, $fiscalYear);
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $desksInMonth = [];
            foreach ($rows as $assignment) {
                if (!$this->assignmentOverlapsMonth($assignment, $fiscalYear, $month)) {
                    continue;
                }
                $deskNumber = (int) ($assignment['desk_number'] ?? 0);
                $key = $deskNumber > 0 ? (string) $deskNumber : 'row:' . count($desksInMonth);
                $desksInMonth[$key] = [
                    'desk_number' => $deskNumber,
                    'usage_type' => (string) ($assignment['usage_type'] ?? 'formal'),
                    'charge_exempt' => $this->isExemptFlag($assignment['charge_exempt'] ?? 0),
                    'rent_exempt' => $this->isExemptFlag($assignment['rent_exempt'] ?? 0),
                ];
            }
            if ($desksInMonth === []) {
                continue;
            }

            $chargeableDeskCount = 0;
            $rentableDeskCount = 0;
            $monthExemptLabels = [];
            foreach ($desksInMonth as $desk) {
                if (!$desk['charge_exempt']) {
                    $chargeableDeskCount++;
                }
                if (in_array($desk['usage_type'], ['informal', 'mixed'], true) && !$desk['rent_exempt']) {
                    $rentableDeskCount++;
                }
                if ($desk['desk_number'] <= 0) {
                    continue;
                }
                if ($desk['charge_exempt'] && $desk['rent_exempt']) {
                    $monthExemptLabels[] = 'میز ' . $desk['desk_number'] . ' معاف';
                } elseif ($desk['charge_exempt']) {
                    $monthExemptLabels[] = 'میز ' . $desk['desk_number'] . ' معاف شارژ';
                } elseif ($desk['rent_exempt']) {
                    $monthExemptLabels[] = 'میز ' . $desk['desk_number'] . ' معاف اجاره';
                }
            }
            if ($chargeableDeskCount === 0 && $rentableDeskCount === 0) {
                continue;
            }

            $globalRates = $this->ratesForMonthInternal($fiscalYear, $month);
            $rates = $contracts->ratesForTeamInMonth($teamId, $fiscalYear, $globalRates);
            $chargeRate = (int) ($rates['charge_rate'] ?? 0);
            $rentRate = (int) ($rates['informal_rent_rate'] ?? 0);
            $monthlyCharge = $chargeableDeskCount * $chargeRate;
            $monthlyRent = $rentableDeskCount * $rentRate;
            $months[$month] = [
                'charge_amount' => $monthlyCharge,
                'rent_amount' => $monthlyRent,
                'amount' => $monthlyCharge + $monthlyRent,
                'note' => $this->buildChargeNote(
                    $chargeableDeskCount,
                    $rentableDeskCount,
                    $chargeRate,
                    $rentRate,
                    $monthExemptLabels,
                    (bool) ($rates['uses_custom_charge_rate'] ?? false),
                    (bool) ($rates['uses_custom_rent_rate'] ?? false),
                    (string) ($billingSummary['summary_text'] ?? '')
                ),
            ];
        }

        return $months;
    }

    /**
     * @param list<string> $monthExemptLabels
     */
    private function buildChargeNote(
        int $chargeableDeskCount,
        int $rentableDeskCount,
        int $chargeRate,
        int $rentRate,
        array $monthExemptLabels,
        bool $customChargeRate,
        bool $customRentRate,
        string $teamSummary
    ): string {
        $parts = [];
        if ($chargeableDeskCount > 0) {
            $parts[] = $chargeableDeskCount . ' میز × ' . number_format($chargeRate) . ' شارژ';
        }
        if ($rentableDeskCount > 0) {
            $parts[] = $rentableDeskCount . ' اجاره × ' . number_format($rentRate);
        }
        $note = 'خودکار: ' . implode(' + ', $parts);
        if ($customChargeRate || $customRentRate) {
            $rateBits = [];
            if ($customChargeRate) {
                $rateBits[] = 'نرخ شارژ اختصاصی';
            }
            if ($customRentRate) {
                $rateBits[] = 'نرخ اجاره اختصاصی';
            }
            $note .= ' | ' . implode(' · ', $rateBits);
        }
        if ($monthExemptLabels !== []) {
            $note .= ' | ' . implode(' · ', array_unique($monthExemptLabels));
        } elseif ($teamSummary !== '' && str_contains($teamSummary, 'معاف')) {
            $note .= ' | ' . $teamSummary;
        }

        return $note;
    }

    private function isExemptFlag(mixed $value): bool
    {
        return (int) $value === 1;
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
