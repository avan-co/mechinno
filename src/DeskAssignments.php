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

        $active = $this->pdo->prepare(
            'SELECT id, team_id FROM desk_assignments
             WHERE desk_id = :desk_id
             ORDER BY CASE WHEN assigned_until IS NULL OR assigned_until = \'\' THEN 0 ELSE 1 END, id DESC
             LIMIT 1'
        );
        $active->execute(['desk_id' => $deskId]);
        $current = $active->fetch() ?: null;

        if ($teamId <= 0) {
            if ($current !== null && $current !== false) {
                $this->pdo->prepare(
                    'UPDATE desk_assignments SET assigned_until = :until WHERE id = :id'
                )->execute(['until' => $today, 'id' => (int) $current['id']]);
            }

            return;
        }

        $assignedFrom = JalaliDate::tryNormalize($desk['assignment_from'] ?? '');
        $assignedUntil = JalaliDate::tryNormalize($desk['assignment_until'] ?? '');
        if ($assignedFrom === '') {
            $assignedFrom = $today;
        }

        if ($current !== null && (int) ($current['team_id'] ?? 0) === $teamId) {
            $this->pdo->prepare(
                'UPDATE desk_assignments SET usage_type = :usage_type, notes = :notes,
                 assigned_from = :assigned_from, assigned_until = :assigned_until WHERE id = :id'
            )->execute([
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'notes' => $desk['notes'] ?? null,
                'assigned_from' => $assignedFrom,
                'assigned_until' => $assignedUntil !== '' ? $assignedUntil : null,
                'id' => (int) $current['id'],
            ]);

            return;
        }

        if ($current !== null) {
            $closeUntil = $assignedFrom !== '' ? $assignedFrom : $today;
            $this->pdo->prepare(
                'UPDATE desk_assignments SET assigned_until = :until WHERE id = :id'
            )->execute(['until' => $closeUntil, 'id' => (int) $current['id']]);
        }

        $this->pdo->prepare(
            'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes)
             VALUES (:desk_id, :desk_number, :team_id, :usage_type, :assigned_from, :assigned_until, :notes)'
        )->execute([
            'desk_id' => $deskId,
            'desk_number' => (int) ($desk['number'] ?? 0),
            'team_id' => $teamId,
            'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
            'assigned_from' => $assignedFrom,
            'assigned_until' => $assignedUntil !== '' ? $assignedUntil : null,
            'notes' => $desk['notes'] ?? null,
        ]);
    }
}
