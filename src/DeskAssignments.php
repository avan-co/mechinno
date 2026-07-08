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
        $assignedFrom = JalaliDate::tryNormalize($desk['assignment_from'] ?? '');
        $assignedUntil = JalaliDate::tryNormalize($desk['assignment_until'] ?? '');
        $active = $this->findActiveAssignment($deskId);

        if ($teamId <= 0) {
            if ($active !== null) {
                $this->closeAssignment((int) $active['id'], $today);
            }

            return;
        }

        if ($assignedFrom === '') {
            $assignedFrom = $today;
        }

        $payload = [
            'desk_id' => $deskId,
            'desk_number' => $this->deskNumber($deskId, $desk),
            'team_id' => $teamId,
            'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
            'assigned_from' => $assignedFrom,
            'assigned_until' => $assignedUntil !== '' ? $assignedUntil : null,
            'notes' => $desk['notes'] ?? null,
        ];

        if ($active === null) {
            $this->insertAssignment($payload);

            return;
        }

        $activeTeamId = (int) ($active['team_id'] ?? 0);
        $activeFrom = (string) ($active['assigned_from'] ?? '');
        $activeOpen = $this->isOpenEnded((string) ($active['assigned_until'] ?? ''));

        if ($activeTeamId === $teamId) {
            $newYear = $this->fiscalYearFrom($assignedFrom);
            $activeYear = $this->fiscalYearFrom($activeFrom);
            if ($activeOpen && $newYear !== '' && $activeYear !== '' && $newYear !== $activeYear) {
                $this->closeAssignment((int) $active['id'], $this->handoverDate($assignedFrom));
                $this->insertAssignment($payload);

                return;
            }
            $this->updateAssignment((int) $active['id'], $payload);

            return;
        }

        $closeDate = $assignedFrom !== '' ? $this->handoverDate($assignedFrom) : $today;
        $this->closeAssignment((int) $active['id'], $closeDate);
        $this->insertAssignment($payload);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function applyAssignmentRecord(array $record, ?int $excludeId = null): void
    {
        $deskId = (int) ($record['desk_id'] ?? 0);
        $assignmentId = (int) ($record['id'] ?? 0);
        if ($deskId <= 0) {
            return;
        }

        $this->assertNoOverlap(
            $deskId,
            (string) ($record['assigned_from'] ?? ''),
            $record['assigned_until'] ?? null,
            $assignmentId > 0 ? $assignmentId : $excludeId
        );

        if (!$this->isOpenEnded((string) ($record['assigned_until'] ?? ''))) {
            $active = $this->findActiveAssignment($deskId);
            if ($active !== null && (int) ($active['id'] ?? 0) === $assignmentId) {
                $this->clearDeskIfMatches($deskId, (int) ($record['team_id'] ?? 0));
            }

            return;
        }

        $this->closeOtherActiveAssignments(
            $deskId,
            $assignmentId,
            $this->handoverDate((string) ($record['assigned_from'] ?? ''))
        );
        $this->syncDeskFromAssignment($deskId, $record);
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
    private function findActiveAssignment(int $deskId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes
             FROM desk_assignments
             WHERE desk_id = :desk_id
               AND (assigned_until IS NULL OR assigned_until = \'\')
             ORDER BY assigned_from DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['desk_id' => $deskId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertAssignment(array $payload): void
    {
        $this->pdo->prepare(
            'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes)
             VALUES (:desk_id, :desk_number, :team_id, :usage_type, :assigned_from, :assigned_until, :notes)'
        )->execute([
            'desk_id' => (int) $payload['desk_id'],
            'desk_number' => (int) $payload['desk_number'],
            'team_id' => (int) $payload['team_id'],
            'usage_type' => (string) $payload['usage_type'],
            'assigned_from' => (string) $payload['assigned_from'],
            'assigned_until' => $payload['assigned_until'],
            'notes' => $payload['notes'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $record = $this->findAssignment($id);
            if ($record !== null) {
                $this->applyAssignmentRecord($record);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateAssignment(int $id, array $payload): void
    {
        $this->pdo->prepare(
            'UPDATE desk_assignments
             SET team_id = :team_id, usage_type = :usage_type, notes = :notes,
                 assigned_from = :assigned_from, assigned_until = :assigned_until, desk_number = :desk_number
             WHERE id = :id'
        )->execute([
            'team_id' => (int) $payload['team_id'],
            'usage_type' => (string) $payload['usage_type'],
            'notes' => $payload['notes'] ?? null,
            'assigned_from' => (string) $payload['assigned_from'],
            'assigned_until' => $payload['assigned_until'],
            'desk_number' => (int) $payload['desk_number'],
            'id' => $id,
        ]);
        $record = $this->findAssignment($id);
        if ($record !== null) {
            $this->applyAssignmentRecord($record);
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
            'SELECT id, desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes
             FROM desk_assignments WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
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
}
