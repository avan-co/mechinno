<?php

declare(strict_types=1);

final class YearBackfill
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Crud $crud,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{imported:int, skipped:int, results:list<array<string,mixed>>}
     */
    public function import(array $payload): array
    {
        $fiscalYear = JalaliDate::normalizeDigits((string) ($payload['fiscal_year'] ?? ''));
        if ($fiscalYear === '') {
            throw new InvalidArgumentException('سال مالی الزامی است.');
        }

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            throw new InvalidArgumentException('حداقل یک ردیف برای ورود لازم است.');
        }

        $recalculate = !empty($payload['recalculate']);
        $defaultStart = $fiscalYear . '/01/01';
        $defaultEnd = JalaliDate::monthEnd($fiscalYear, 12);
        $imported = 0;
        $skipped = 0;
        $results = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId <= 0) {
                $teamName = trim((string) ($row['team_name'] ?? ''));
                if ($teamName !== '') {
                    $teamId = $this->resolveTeamIdByName($teamName);
                }
            }
            if ($teamId <= 0) {
                $results[] = ['index' => $index, 'status' => 'skipped', 'message' => 'نهاد نامعتبر'];
                $skipped++;
                continue;
            }

            try {
                $contractId = $this->ensureContract(
                    $teamId,
                    $fiscalYear,
                    (string) ($row['contract_start'] ?? $defaultStart),
                    (string) ($row['contract_end'] ?? $defaultEnd),
                    (string) ($row['notes'] ?? ''),
                    (int) preg_replace('/\D+/', '', (string) ($row['formal_contract_amount'] ?? '0'))
                );

                $deskRows = $row['desks'] ?? null;
                if (!is_array($deskRows)) {
                    $deskRows = isset($row['desk_numbers'])
                        ? $this->parseDeskNumbers((string) $row['desk_numbers'], $fiscalYear)
                        : [];
                }
                $assignmentCount = 0;
                if (is_array($deskRows)) {
                    foreach ($deskRows as $deskRow) {
                        if (!is_array($deskRow)) {
                            continue;
                        }
                        $this->createDeskAssignment($teamId, $fiscalYear, $deskRow, $defaultStart, $defaultEnd);
                        $assignmentCount++;
                    }
                }

                if ($recalculate) {
                    (new Seeder($this->pdo))->recalculateChargesForTeam($teamId, $fiscalYear);
                }

                $imported++;
                $results[] = [
                    'index' => $index,
                    'status' => 'ok',
                    'team_id' => $teamId,
                    'contract_id' => $contractId,
                    'desk_assignments' => $assignmentCount,
                ];
            } catch (Throwable $exception) {
                $skipped++;
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'team_id' => $teamId,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'results' => $results];
    }

    private function resolveTeamIdByName(string $name): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM teams WHERE name = :name ORDER BY id ASC');
        $statement->execute(['name' => $name]);
        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $statement->fetchAll());
        if ($ids === []) {
            return 0;
        }
        if (count($ids) > 1) {
            throw new InvalidArgumentException(
                'چند نهاد با نام «' . $name . '» وجود دارد؛ به‌جای نام، شناسه نهاد (team_id) را وارد کنید.'
            );
        }

        return $ids[0];
    }

    private function ensureContract(
        int $teamId,
        string $fiscalYear,
        string $contractStart,
        string $contractEnd,
        string $notes,
        int $formalContractAmount = 0
    ): int {
        $check = $this->pdo->prepare(
            'SELECT id FROM team_contracts WHERE team_id = :team_id AND fiscal_year = :year LIMIT 1'
        );
        $check->execute(['team_id' => $teamId, 'year' => $fiscalYear]);
        $existingId = (int) ($check->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $payload = [
                'contract_start' => $contractStart,
                'contract_end' => $contractEnd,
                'notes' => $notes,
            ];
            // Only overwrite amount when CSV provided a positive value (missing → 0 must not wipe).
            if ($formalContractAmount > 0) {
                $payload['formal_contract_amount'] = (string) $formalContractAmount;
            }
            $this->crud->update('team_contracts', $existingId, $payload);

            return $existingId;
        }

        if ($formalContractAmount <= 0) {
            throw new InvalidArgumentException('مبلغ کل قرارداد رسمی الزامی است.');
        }

        $record = $this->crud->create('team_contracts', [
            'team_id' => (string) $teamId,
            'fiscal_year' => $fiscalYear,
            'contract_start' => $contractStart,
            'contract_end' => $contractEnd,
            'formal_contract_amount' => (string) $formalContractAmount,
            'notes' => $notes,
        ]);

        return (int) ($record['id'] ?? 0);
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseDeskNumbers(string $deskNumbers, string $fiscalYear): array
    {
        $parts = preg_split('/[\s,،]+/u', trim($deskNumbers)) ?: [];
        $defaultStart = $fiscalYear . '/01/01';
        $defaultEnd = JalaliDate::monthEnd($fiscalYear, 12);
        $rows = [];
        foreach ($parts as $part) {
            $number = (int) preg_replace('/\D+/', '', $part);
            if ($number <= 0) {
                continue;
            }
            $rows[] = [
                'desk_number' => (string) $number,
                'usage_type' => 'formal',
                'assigned_from' => $defaultStart,
                'assigned_until' => $defaultEnd,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $deskRow
     */
    private function createDeskAssignment(
        int $teamId,
        string $fiscalYear,
        array $deskRow,
        string $defaultStart,
        string $defaultEnd
    ): void {
        $deskNumber = (int) ($deskRow['desk_number'] ?? $deskRow['number'] ?? 0);
        if ($deskNumber <= 0) {
            throw new InvalidArgumentException('شماره میز نامعتبر است.');
        }

        $deskId = $this->deskIdForNumber($deskNumber);
        $assignedFrom = JalaliDate::tryNormalize((string) ($deskRow['assigned_from'] ?? $defaultStart));
        $assignedUntil = JalaliDate::tryNormalize((string) ($deskRow['assigned_until'] ?? $defaultEnd));
        if ($assignedFrom === '') {
            $assignedFrom = $defaultStart;
        }
        if ($assignedUntil === '') {
            $assignedUntil = $defaultEnd;
        }

        $fromMonth = (int) ($deskRow['assigned_from_month'] ?? JalaliDate::monthIndexFromDate($assignedFrom));
        $untilMonth = (int) ($deskRow['assigned_until_month'] ?? JalaliDate::monthIndexFromDate($assignedUntil));
        if ($fromMonth < 1 || $fromMonth > 12) {
            $fromMonth = 1;
        }
        if ($untilMonth < 1 || $untilMonth > 12) {
            $untilMonth = 12;
        }

        $this->crud->create('desk_assignments', [
            'desk_id' => (string) $deskId,
            'team_id' => (string) $teamId,
            'usage_type' => (string) ($deskRow['usage_type'] ?? 'formal'),
            'fiscal_year' => $fiscalYear,
            'assigned_from_month' => (string) $fromMonth,
            'assigned_until_month' => (string) $untilMonth,
            'notes' => (string) ($deskRow['notes'] ?? ''),
        ]);
    }

    private function deskIdForNumber(int $number): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM desks WHERE number = :number LIMIT 1');
        $statement->execute(['number' => $number]);
        $deskId = (int) ($statement->fetchColumn() ?: 0);
        if ($deskId <= 0) {
            throw new InvalidArgumentException("میز شماره {$number} پیدا نشد.");
        }

        return $deskId;
    }
}
