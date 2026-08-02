<?php

declare(strict_types=1);

final class TeamLeaders
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureLeaderMember(int $teamId): ?int
    {
        $team = $this->teamRow($teamId);
        if ($team === null) {
            return null;
        }

        $leaderName = trim((string) ($team['leader'] ?? ''));
        if ($leaderName === '') {
            return null;
        }

        $existingLeader = $this->leaderMemberId($teamId);
        if ($existingLeader !== null) {
            $this->pdo->prepare(
                'UPDATE members SET full_name = :name, phone = :phone WHERE id = :id'
            )->execute([
                'name' => $leaderName,
                'phone' => trim((string) ($team['phone'] ?? '')) ?: null,
                'id' => $existingLeader,
            ]);

            return $existingLeader;
        }

        $phone = trim((string) ($team['phone'] ?? ''));
        $matched = $this->findMemberByPhone($teamId, $phone);
        if ($matched !== null) {
            $this->setLeaderMember($teamId, $matched);

            return $matched;
        }

        $memberCode = (new Identifier($this->pdo))->nextMemberCode();
        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            'INSERT INTO members (member_code, team_id, full_name, phone, wants_access, approval_status, is_leader, reviewed_at, joined_at, notes, source_file, source_sheet)
             VALUES (:member_code, :team_id, :full_name, :phone, 0, :approval_status, 1, :reviewed_at, :joined_at, :notes, :source_file, :source_sheet)'
        )->execute([
            'member_code' => $memberCode,
            'team_id' => $teamId,
            'full_name' => $leaderName,
            'phone' => $phone !== '' ? $phone : null,
            'approval_status' => 'approved',
            'reviewed_at' => $today,
            'joined_at' => $today,
            'notes' => 'مسئول نهاد — ایجاد خودکار',
            'source_file' => 'manual',
            'source_sheet' => 'leader',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function changeLeader(int $teamId, int $memberId): array
    {
        $member = $this->pdo->prepare(
            'SELECT id, team_id, full_name, phone, approval_status FROM members WHERE id = :id'
        );
        $member->execute(['id' => $memberId]);
        $row = $member->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('عضو انتخاب‌شده یافت نشد.');
        }
        if ((int) ($row['team_id'] ?? 0) !== $teamId) {
            throw new InvalidArgumentException('این عضو به نهاد انتخاب‌شده تعلق ندارد.');
        }
        if ((string) ($row['approval_status'] ?? '') !== 'approved') {
            throw new InvalidArgumentException('فقط اعضای تأیید‌شده می‌توانند مسئول نهاد شوند.');
        }

        $this->clearLeaderFlags($teamId);
        $this->pdo->prepare('UPDATE members SET is_leader = 1 WHERE id = :id')->execute(['id' => $memberId]);

        $fullName = trim((string) ($row['full_name'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $this->pdo->prepare('UPDATE teams SET leader = :leader, phone = :phone WHERE id = :id')->execute([
            'leader' => $fullName,
            'phone' => $phone !== '' ? $phone : null,
            'id' => $teamId,
        ]);
        EntityAccounts::syncLeaderName($this->pdo, $teamId, $fullName);

        return $this->teamRow($teamId) ?? [];
    }

    public function syncLeaderFromTeam(int $teamId): void
    {
        $leaderId = $this->leaderMemberId($teamId);
        if ($leaderId === null) {
            $this->ensureLeaderMember($teamId);

            return;
        }

        $team = $this->teamRow($teamId);
        if ($team === null) {
            return;
        }

        $this->pdo->prepare(
            'UPDATE members SET full_name = :name, phone = :phone WHERE id = :id'
        )->execute([
            'name' => trim((string) ($team['leader'] ?? '')),
            'phone' => trim((string) ($team['phone'] ?? '')) ?: null,
            'id' => $leaderId,
        ]);
    }

    public static function backfillAll(PDO $pdo): void
    {
        $service = new self($pdo);
        foreach ($pdo->query('SELECT id FROM teams')->fetchAll() as $row) {
            $service->ensureLeaderMember((int) $row['id']);
        }
    }

    private function setLeaderMember(int $teamId, int $memberId): void
    {
        $this->clearLeaderFlags($teamId);
        $this->pdo->prepare('UPDATE members SET is_leader = 1 WHERE id = :id AND team_id = :team_id')
            ->execute(['id' => $memberId, 'team_id' => $teamId]);
    }

    private function clearLeaderFlags(int $teamId): void
    {
        $this->pdo->prepare('UPDATE members SET is_leader = 0 WHERE team_id = :team_id')
            ->execute(['team_id' => $teamId]);
    }

    private function leaderMemberId(int $teamId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM members WHERE team_id = :team_id AND is_leader = 1 ORDER BY id LIMIT 1'
        );
        $statement->execute(['team_id' => $teamId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function findMemberByPhone(int $teamId, string $phone): ?int
    {
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            return null;
        }
        $statement = $this->pdo->prepare(
            "SELECT id FROM members
             WHERE team_id = :team_id
               AND REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+98', '0') = :phone
             ORDER BY id LIMIT 1"
        );
        $statement->execute(['team_id' => $teamId, 'phone' => $phone]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamRow(int $teamId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, leader, phone FROM teams WHERE id = :id');
        $statement->execute(['id' => $teamId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}
