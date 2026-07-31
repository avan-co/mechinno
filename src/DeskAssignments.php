<?php

declare(strict_types=1);

final class DeskAssignments
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $desk
     */
    public function syncDeskAssignment(int $deskId, array $desk): void
    {
        $teamId = (int) ($desk['team_id'] ?? 0);
        $today = JalaliDate::todayParts()['formatted'];
        $assignedFrom = JalaliDate::tryNormalize($desk['assignment_from'] ?? $desk['assigned_from'] ?? '');
        $assignedUntil = JalaliDate::tryNormalize($desk['assignment_until'] ?? $desk['assigned_until'] ?? '');

        if ($teamId <= 0) {
            $current = $this->findCurrentAssignment($deskId);
            if ($current !== null) {
                $this->closeAssignment((int) $current['id'], $today);
            } else {
                $open = $this->findOpenAssignment($deskId);
                if ($open !== null) {
                    $this->closeAssignment((int) $open['id'], $today);
                }
            }

            return;
        }

        if ($assignedFrom === '') {
            $assignedFrom = $today;
        }

        $fiscalYear = $this->fiscalYearFrom($assignedFrom);
        if ($fiscalYear === '') {
            $fiscalYear = (new TeamContracts($this->pdo))->currentFiscalYear();
        }

        $includesUntil = $this->deskPayloadIncludesUntil($desk);
        $payload = [
            'desk_id' => $deskId,
            'desk_number' => $this->deskNumber($deskId, $desk),
            'team_id' => $teamId,
            'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
            'assigned_from' => $assignedFrom,
            'assigned_until' => $assignedUntil !== '' ? $assignedUntil : null,
            'notes' => $desk['notes'] ?? null,
            'charge_exempt' => (int) ($desk['charge_exempt'] ?? 0),
            'rent_exempt' => (int) ($desk['rent_exempt'] ?? 0),
        ];
        if (!$this->exemptWritable()) {
            unset($payload['charge_exempt'], $payload['rent_exempt']);
        }

        $yearRecord = $this->findAssignmentForYear($deskId, $fiscalYear, $teamId);
        if ($yearRecord !== null) {
            if (!array_key_exists('charge_exempt', $desk)) {
                $payload['charge_exempt'] = (int) ($yearRecord['charge_exempt'] ?? 0);
            }
            if (!array_key_exists('rent_exempt', $desk)) {
                $payload['rent_exempt'] = (int) ($yearRecord['rent_exempt'] ?? 0);
            }
            $this->updateAssignment(
                (int) $yearRecord['id'],
                $this->mergeUntilFromExisting($payload, $yearRecord, $includesUntil)
            );

            return;
        }

        $current = $this->findCurrentAssignment($deskId);
        if ($current !== null) {
            $currentTeamId = (int) ($current['team_id'] ?? 0);
            if ($currentTeamId !== $teamId) {
                $closeDate = $this->handoverDate($assignedFrom);
                $this->closeAssignment((int) $current['id'], $closeDate);
                $this->insertAssignment($payload);

                return;
            }

            $currentYear = $this->fiscalYearFrom((string) ($current['assigned_from'] ?? ''));
            if ($currentYear !== '' && $currentYear !== $fiscalYear) {
                $this->closeAssignment((int) $current['id'], $this->handoverDate($assignedFrom));
                $this->insertAssignment($payload);

                return;
            }

            $this->updateAssignment(
                (int) $current['id'],
                $this->mergeUntilFromExisting($payload, $current, $includesUntil)
            );

            return;
        }

        $this->insertAssignment($payload);
    }

    /**
     * @param array<string, mixed> $record
     * @return list<int> team ids that lost overlapping assignment rows
     */
    public function applyAssignmentRecord(array $record, ?int $excludeId = null): array
    {
        $deskId = (int) ($record['desk_id'] ?? 0);
        $assignmentId = (int) ($record['id'] ?? 0);
        if ($deskId <= 0) {
            return [];
        }

        $this->assertNoOverlap(
            $deskId,
            (string) ($record['assigned_from'] ?? ''),
            $record['assigned_until'] ?? null,
            $assignmentId > 0 ? $assignmentId : $excludeId
        );

        if (!$this->isOpenEnded((string) ($record['assigned_until'] ?? ''))) {
            $until = JalaliDate::tryNormalize((string) ($record['assigned_until'] ?? ''));
            $today = JalaliDate::todayParts()['formatted'];
            if ($until !== '' && JalaliDate::compare($until, $today) < 0) {
                $current = $this->findCurrentAssignment($deskId);
                if ($current !== null && (int) ($current['id'] ?? 0) === $assignmentId) {
                    $this->clearDeskIfMatches($deskId, (int) ($record['team_id'] ?? 0));
                }

                return [];
            }

            $this->closeOtherActiveAssignments(
                $deskId,
                $assignmentId,
                $this->handoverDate((string) ($record['assigned_from'] ?? ''))
            );
            $this->syncDeskFromAssignment($deskId, $record);

            return $this->reconcileDeskYearAssignments($record);
        }

        $this->closeOtherActiveAssignments(
            $deskId,
            $assignmentId,
            $this->handoverDate((string) ($record['assigned_from'] ?? ''))
        );
        $this->syncDeskFromAssignment($deskId, $record);

        return $this->reconcileDeskYearAssignments($record);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function handleAssignmentDeleted(array $record): void
    {
        $deskId = (int) ($record['desk_id'] ?? 0);
        $teamId = (int) ($record['team_id'] ?? 0);
        if ($deskId <= 0 || !$this->isOpenEnded((string) ($record['assigned_until'] ?? ''))) {
            return;
        }

        $this->clearDeskIfMatches($deskId, $teamId);
        $fallback = $this->findActiveAssignment($deskId);
        if ($fallback !== null) {
            $this->syncDeskFromAssignment($deskId, $fallback);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function assignmentForDeskForm(int $deskId, ?int $teamId = null): ?array
    {
        $fiscalYear = (new TeamContracts($this->pdo))->currentFiscalYear();
        if ($teamId !== null && $teamId > 0) {
            $yearRecord = $this->findAssignmentForYear($deskId, $fiscalYear, $teamId);
            if ($yearRecord !== null) {
                return $this->assignmentFormFields($yearRecord);
            }
        }

        $yearRecord = $this->findAssignmentForYear($deskId, $fiscalYear, null);
        if ($yearRecord !== null) {
            return $this->assignmentFormFields($yearRecord);
        }

        $current = $this->findCurrentAssignment($deskId);
        if ($current !== null && ($teamId === null || $teamId <= 0 || (int) ($current['team_id'] ?? 0) === $teamId)) {
            return $this->assignmentFormFields($current);
        }

        return null;
    }

    public function findExistingRecordId(
        int $deskId,
        string $fiscalYear,
        int $teamId,
        string $assignedFrom = '',
        string $assignedUntil = ''
    ): ?int {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        if ($deskId <= 0 || $fiscalYear === '') {
            return null;
        }

        $from = JalaliDate::tryNormalize($assignedFrom);
        $until = $this->isOpenEnded($assignedUntil) ? '' : JalaliDate::tryNormalize($assignedUntil);

        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = $fiscalYear . '/12/29';
        $sql = 'SELECT id, assigned_from, assigned_until FROM desk_assignments
                WHERE desk_id = :desk_id
                  AND assigned_from <= :year_end
                  AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)';
        $params = [
            'desk_id' => $deskId,
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ];
        if ($teamId > 0) {
            $sql .= ' AND team_id = :team_id';
            $params['team_id'] = $teamId;
        }
        $sql .= ' ORDER BY assigned_from DESC, id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        foreach ($statement->fetchAll() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            // No explicit range → legacy behavior: latest year row for desk/team.
            if ($from === '') {
                return $id;
            }
            $otherFrom = JalaliDate::tryNormalize((string) ($row['assigned_from'] ?? ''));
            $otherUntil = $this->isOpenEnded((string) ($row['assigned_until'] ?? ''))
                ? ''
                : JalaliDate::tryNormalize((string) ($row['assigned_until'] ?? ''));
            if ($this->rangesOverlap($from, $until, $otherFrom, $otherUntil)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Remove only date-overlapping duplicates for the same desk.
     * Non-overlapping sequential segments (e.g. 1–6 then 7–12) are kept.
     *
     * @param array<string, mixed> $record
     * @return list<int> team ids whose rows were deleted
     */
    public function reconcileDeskYearAssignments(array $record): array
    {
        $deskId = (int) ($record['desk_id'] ?? 0);
        $keepId = (int) ($record['id'] ?? 0);
        $fiscalYear = $this->fiscalYearFrom((string) ($record['assigned_from'] ?? ''));
        if ($deskId <= 0 || $keepId <= 0 || $fiscalYear === '') {
            return [];
        }

        $keepFrom = JalaliDate::tryNormalize((string) ($record['assigned_from'] ?? ''));
        $keepUntil = $this->isOpenEnded((string) ($record['assigned_until'] ?? ''))
            ? ''
            : JalaliDate::tryNormalize((string) ($record['assigned_until'] ?? ''));
        if ($keepFrom === '') {
            return [];
        }

        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = $fiscalYear . '/12/29';
        $statement = $this->pdo->prepare(
            'SELECT id, team_id, assigned_from, assigned_until
             FROM desk_assignments
             WHERE desk_id = :desk_id AND id <> :keep_id
               AND assigned_from <= :year_end
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)'
        );
        $statement->execute([
            'desk_id' => $deskId,
            'keep_id' => $keepId,
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ]);

        $affectedTeams = [];
        $delete = $this->pdo->prepare('DELETE FROM desk_assignments WHERE id = :id');
        foreach ($statement->fetchAll() as $row) {
            $otherFrom = JalaliDate::tryNormalize((string) ($row['assigned_from'] ?? ''));
            $otherUntil = $this->isOpenEnded((string) ($row['assigned_until'] ?? ''))
                ? ''
                : JalaliDate::tryNormalize((string) ($row['assigned_until'] ?? ''));
            if (!$this->rangesOverlap($keepFrom, $keepUntil, $otherFrom, $otherUntil)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId > 0) {
                $affectedTeams[$teamId] = true;
            }
            $delete->execute(['id' => $id]);
        }

        return array_map('intval', array_keys($affectedTeams));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAssignmentForYear(int $deskId, string $fiscalYear, ?int $teamId = null): ?array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        if ($fiscalYear === '') {
            return null;
        }

        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = $fiscalYear . '/12/29';
        $sql = 'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes'
            . $this->exemptSelect()
            . ' FROM desk_assignments
                WHERE desk_id = :desk_id
                  AND assigned_from <= :year_end
                  AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)';
        $params = ['desk_id' => $deskId, 'year_start' => $yearStart, 'year_end' => $yearEnd];
        if ($teamId !== null && $teamId > 0) {
            $sql .= ' AND team_id = :team_id';
            $params['team_id'] = $teamId;
        }
        $sql .= ' ORDER BY CASE WHEN assigned_until IS NULL OR assigned_until = \'\' THEN 1 ELSE 0 END, assigned_from DESC, id DESC LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCurrentAssignment(int $deskId): ?array
    {
        $today = JalaliDate::todayParts()['formatted'];
        $statement = $this->pdo->prepare(
            'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes'
            . $this->exemptSelect()
            . ' FROM desk_assignments
             WHERE desk_id = :desk_id
               AND assigned_from <= :today
               AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :today)
             ORDER BY assigned_from DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['desk_id' => $deskId, 'today' => $today]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOpenAssignment(int $deskId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes'
            . $this->exemptSelect()
            . ' FROM desk_assignments
             WHERE desk_id = :desk_id
               AND (assigned_until IS NULL OR assigned_until = \'\')
             ORDER BY assigned_from DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['desk_id' => $deskId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActiveAssignment(int $deskId): ?array
    {
        return $this->findOpenAssignment($deskId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertAssignment(array $payload): void
    {
        $this->assertNoOverlap(
            (int) $payload['desk_id'],
            (string) $payload['assigned_from'],
            $payload['assigned_until'],
            null
        );

        $columns = ['desk_id', 'desk_number', 'team_id', 'usage_type', 'assigned_from', 'assigned_until', 'notes'];
        $params = [
            'desk_id' => (int) $payload['desk_id'],
            'desk_number' => (int) $payload['desk_number'],
            'team_id' => (int) $payload['team_id'],
            'usage_type' => (string) $payload['usage_type'],
            'assigned_from' => (string) $payload['assigned_from'],
            'assigned_until' => $payload['assigned_until'],
            'notes' => $payload['notes'] ?? null,
        ];
        if ($this->exemptWritable()) {
            $columns[] = 'charge_exempt';
            $columns[] = 'rent_exempt';
            $params['charge_exempt'] = (int) ($payload['charge_exempt'] ?? 0);
            $params['rent_exempt'] = (int) ($payload['rent_exempt'] ?? 0);
        }

        $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns));
        $this->pdo->prepare(
            'INSERT INTO desk_assignments (' . implode(', ', $columns) . ')
             VALUES (' . $placeholders . ')'
        )->execute($params);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $record = $this->findAssignment($id);
            if ($record !== null) {
                $this->applyAssignmentRecord($record, $id);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateAssignment(int $id, array $payload): void
    {
        $this->assertNoOverlap(
            (int) $payload['desk_id'],
            (string) $payload['assigned_from'],
            $payload['assigned_until'],
            $id
        );

        $setClauses = [
            'team_id = :team_id',
            'usage_type = :usage_type',
            'notes = :notes',
            'assigned_from = :assigned_from',
            'assigned_until = :assigned_until',
            'desk_number = :desk_number',
        ];
        $params = [
            'team_id' => (int) $payload['team_id'],
            'usage_type' => (string) $payload['usage_type'],
            'notes' => $payload['notes'] ?? null,
            'assigned_from' => (string) $payload['assigned_from'],
            'assigned_until' => $payload['assigned_until'],
            'desk_number' => (int) $payload['desk_number'],
            'id' => $id,
        ];
        if ($this->exemptWritable()) {
            $setClauses[] = 'charge_exempt = :charge_exempt';
            $setClauses[] = 'rent_exempt = :rent_exempt';
            $params['charge_exempt'] = (int) ($payload['charge_exempt'] ?? 0);
            $params['rent_exempt'] = (int) ($payload['rent_exempt'] ?? 0);
        }

        $this->pdo->prepare(
            'UPDATE desk_assignments SET ' . implode(', ', $setClauses) . ' WHERE id = :id'
        )->execute($params);
        $record = $this->findAssignment($id);
        if ($record !== null) {
            $this->applyAssignmentRecord($record, $id);
        }
    }

    private function closeAssignment(int $id, string $until): void
    {
        $this->pdo->prepare(
            'UPDATE desk_assignments SET assigned_until = :until WHERE id = :id'
        )->execute([
            'until' => JalaliDate::tryNormalize($until),
            'id' => $id,
        ]);
    }

    private function closeOtherActiveAssignments(int $deskId, int $keepId, ?string $handoverDate = null): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM desk_assignments
             WHERE desk_id = :desk_id AND id <> :keep_id
               AND (assigned_until IS NULL OR assigned_until = \'\')'
        );
        $statement->execute(['desk_id' => $deskId, 'keep_id' => $keepId]);
        $closeAt = $handoverDate ?? JalaliDate::todayParts()['formatted'];
        foreach ($statement->fetchAll() as $row) {
            $this->closeAssignment((int) $row['id'], $closeAt);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function syncDeskFromAssignment(int $deskId, array $record): void
    {
        $this->pdo->prepare(
            'UPDATE desks SET team_id = :team_id, usage_type = :usage_type, notes = :notes WHERE id = :id'
        )->execute([
            'team_id' => (int) ($record['team_id'] ?? 0),
            'usage_type' => (string) ($record['usage_type'] ?? 'formal'),
            'notes' => $record['notes'] ?? null,
            'id' => $deskId,
        ]);
    }

    private function clearDeskIfMatches(int $deskId, int $teamId): void
    {
        $statement = $this->pdo->prepare('SELECT team_id FROM desks WHERE id = :id');
        $statement->execute(['id' => $deskId]);
        $currentTeamId = (int) ($statement->fetchColumn() ?: 0);
        if ($currentTeamId === $teamId) {
            $this->pdo->prepare(
                'UPDATE desks SET team_id = NULL, usage_type = :usage_type, notes = NULL WHERE id = :id'
            )->execute(['usage_type' => 'formal', 'id' => $deskId]);
        }
    }

    private function assertNoOverlap(int $deskId, string $from, mixed $until, ?int $excludeId = null): void
    {
        $from = JalaliDate::tryNormalize($from);
        if ($from === '') {
            throw new InvalidArgumentException('تاریخ شروع تخصیص معتبر نیست.');
        }
        $untilNorm = $this->isOpenEnded((string) $until) ? '' : JalaliDate::tryNormalize((string) $until);
        if ($untilNorm !== '' && JalaliDate::compare($from, $untilNorm) > 0) {
            throw new InvalidArgumentException('تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
        }

        $params = ['desk_id' => $deskId];
        $excludeSql = '';
        if ($excludeId !== null && $excludeId > 0) {
            $excludeSql = ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = $this->pdo->prepare(
            "SELECT id, assigned_from, assigned_until FROM desk_assignments
             WHERE desk_id = :desk_id{$excludeSql}"
        );
        $statement->execute($params);
        foreach ($statement->fetchAll() as $row) {
            $otherFrom = JalaliDate::tryNormalize((string) ($row['assigned_from'] ?? ''));
            $otherUntil = $this->isOpenEnded((string) ($row['assigned_until'] ?? ''))
                ? ''
                : JalaliDate::tryNormalize((string) ($row['assigned_until'] ?? ''));
            if ($this->rangesOverlap($from, $untilNorm, $otherFrom, $otherUntil)) {
                throw new InvalidArgumentException('این میز در بازه زمانی انتخاب‌شده قبلاً به نهاد دیگری تخصیص داده شده است.');
            }
        }
    }

    private function rangesOverlap(string $fromA, string $untilA, string $fromB, string $untilB): bool
    {
        if ($fromA === '' || $fromB === '') {
            return false;
        }
        $endA = $untilA !== '' ? $untilA : '9999/12/29';
        $endB = $untilB !== '' ? $untilB : '9999/12/29';

        return JalaliDate::compare($fromA, $endB) <= 0 && JalaliDate::compare($fromB, $endA) <= 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAssignment(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes'
            . $this->exemptSelect()
            . ' FROM desk_assignments WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{assigned_from:string, assigned_until:string, usage_type:string, notes:?string}
     */
    private function assignmentFormFields(array $row): array
    {
        return [
            'assigned_from' => (string) ($row['assigned_from'] ?? ''),
            'assigned_until' => (string) ($row['assigned_until'] ?? ''),
            'usage_type' => (string) ($row['usage_type'] ?? 'formal'),
            'notes' => $row['notes'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $desk
     */
    private function deskPayloadIncludesUntil(array $desk): bool
    {
        return array_key_exists('assignment_until', $desk)
            || array_key_exists('assigned_until', $desk)
            || array_key_exists('assignment_until_month', $desk);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function mergeUntilFromExisting(array $payload, array $existing, bool $includesUntil): array
    {
        if ($includesUntil || $payload['assigned_until'] !== null) {
            return $payload;
        }

        $existingUntil = JalaliDate::tryNormalize((string) ($existing['assigned_until'] ?? ''));
        if ($existingUntil !== '') {
            $payload['assigned_until'] = $existingUntil;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $desk
     */
    private function deskNumber(int $deskId, array $desk): int
    {
        $number = (int) ($desk['number'] ?? 0);
        if ($number > 0) {
            return $number;
        }
        $statement = $this->pdo->prepare('SELECT number FROM desks WHERE id = :id');
        $statement->execute(['id' => $deskId]);
        $fetched = $statement->fetchColumn();

        return $fetched === false ? 0 : (int) $fetched;
    }

    private function fiscalYearFrom(string $date): string
    {
        $normalized = JalaliDate::tryNormalize($date);

        return $normalized !== '' ? substr($normalized, 0, 4) : '';
    }

    private function handoverDate(string $nextStart): string
    {
        $normalized = JalaliDate::tryNormalize($nextStart);
        if ($normalized === '') {
            return JalaliDate::todayParts()['formatted'];
        }
        if (preg_match('/^(\d{4})\/01\/01$/', $normalized, $matches) === 1) {
            return ((int) $matches[1] - 1) . '/12/29';
        }

        return $normalized;
    }

    private function isOpenEnded(string $until): bool
    {
        return trim($until) === '';
    }

    private function exemptSelect(string $alias = ''): string
    {
        return Schema::deskAssignmentExemptSelect($this->pdo, $alias);
    }

    private function exemptWritable(): bool
    {
        return Schema::deskAssignmentExemptWritable($this->pdo);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return Schema::normalizeDeskAssignmentRow($row);
    }
}
