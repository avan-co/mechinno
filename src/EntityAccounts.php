<?php

declare(strict_types=1);

final class EntityAccounts
{
    /**
     * @return array{username:string,password:string}
     */
    public static function provisionForTeam(
        PDO $pdo,
        int $teamId,
        string $entityCode,
        string $leaderName,
        ?string $password = null
    ): array {
        $username = self::usernameForCode($entityCode);
        $plainPassword = $password !== null && $password !== '' ? $password : self::generatePassword();
        $passwordHash = UserAccounts::hashPassword($plainPassword);
        $fullName = $leaderName !== '' ? $leaderName : 'مسئول نهاد';

        $existing = $pdo->prepare(
            "SELECT id, username FROM panel_users WHERE team_id = :team_id AND role = :role LIMIT 1"
        );
        $existing->execute(['team_id' => $teamId, 'role' => Access::ROLE_TEAM]);
        $row = $existing->fetch();

        if ($row !== false) {
            $username = self::ensureUniqueUsername($pdo, $username, (int) $row['id']);
            $pdo->prepare(
                'UPDATE panel_users
                 SET username = :username, password_hash = :password_hash, password_plain = :password_plain,
                     full_name = :full_name, is_active = 1
                 WHERE id = :id'
            )->execute([
                'username' => $username,
                'password_hash' => $passwordHash,
                'password_plain' => $plainPassword,
                'full_name' => $fullName,
                'id' => (int) $row['id'],
            ]);

            return ['username' => $username, 'password' => $plainPassword];
        }

        $username = self::ensureUniqueUsername($pdo, $username, 0);
        $pdo->prepare(
            'INSERT INTO panel_users (username, password_hash, password_plain, role, team_id, full_name, is_active)
             VALUES (:username, :password_hash, :password_plain, :role, :team_id, :full_name, 1)'
        )->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'password_plain' => $plainPassword,
            'role' => Access::ROLE_TEAM,
            'team_id' => $teamId,
            'full_name' => $fullName,
        ]);

        return ['username' => $username, 'password' => $plainPassword];
    }

    /**
     * @return array{username:string,password:string}
     */
    public static function resetPassword(PDO $pdo, int $teamId): array
    {
        $statement = $pdo->prepare('SELECT entity_code, leader FROM teams WHERE id = :id');
        $statement->execute(['id' => $teamId]);
        $team = $statement->fetch();
        if ($team === false) {
            throw new InvalidArgumentException('نهاد پیدا نشد.');
        }

        return self::provisionForTeam(
            $pdo,
            $teamId,
            (string) ($team['entity_code'] ?? ''),
            (string) ($team['leader'] ?? ''),
            self::generatePassword()
        );
    }

    public static function deleteForTeam(PDO $pdo, int $teamId): void
    {
        $pdo->prepare('DELETE FROM panel_users WHERE team_id = :team_id AND role = :role')
            ->execute(['team_id' => $teamId, 'role' => Access::ROLE_TEAM]);
    }

    public static function syncMissingTeams(PDO $pdo): void
    {
        if (!self::tableReady($pdo)) {
            return;
        }

        $statement = $pdo->prepare(
            'SELECT t.id, t.entity_code, t.leader
             FROM teams t
             LEFT JOIN panel_users u ON u.team_id = t.id AND u.role = :role
             WHERE u.id IS NULL'
        );
        $statement->execute(['role' => Access::ROLE_TEAM]);
        $teams = $statement->fetchAll();

        foreach ($teams as $team) {
            $code = (string) ($team['entity_code'] ?? '');
            if ($code === '') {
                continue;
            }
            self::provisionForTeam(
                $pdo,
                (int) $team['id'],
                $code,
                (string) ($team['leader'] ?? '')
            );
        }
    }

    public static function usernameForCode(string $entityCode): string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]/', '', $entityCode) ?? '');

        return $username !== '' ? $username : 'entity' . random_int(1000, 9999);
    }

    public static function generatePassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private static function ensureUniqueUsername(PDO $pdo, string $username, int $excludeId): string
    {
        $candidate = $username;
        $suffix = 1;
        while (self::usernameTaken($pdo, $candidate, $excludeId)) {
            $candidate = $username . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private static function usernameTaken(PDO $pdo, string $username, int $excludeId): bool
    {
        $statement = $pdo->prepare(
            'SELECT id FROM panel_users WHERE username = :username' . ($excludeId > 0 ? ' AND id <> :id' : '') . ' LIMIT 1'
        );
        $params = ['username' => $username];
        if ($excludeId > 0) {
            $params['id'] = $excludeId;
        }
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    private static function tableReady(PDO $pdo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'panel_users'");

            return $statement !== false && $statement->fetchColumn() !== false;
        }

        $statement = $pdo->query("SHOW TABLES LIKE 'panel_users'");

        return $statement !== false && $statement->fetchColumn() !== false;
    }
}
