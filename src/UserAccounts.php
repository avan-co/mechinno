<?php

declare(strict_types=1);

final class UserAccounts
{
    /**
     * @param array<string, mixed> $config
     */
    public static function ensureBootstrapUsers(PDO $pdo, array $config): void
    {
        if (!self::tableExists($pdo)) {
            return;
        }

        $auth = $config['auth'] ?? [];
        $adminUser = trim((string) ($auth['username'] ?? 'admin'));
        $adminPassword = (string) ($auth['password'] ?? '');
        $adminHash = (string) ($auth['password_hash'] ?? '');

        if ($adminUser !== '' && ($adminHash !== '' || ($adminPassword !== '' && $adminPassword !== 'CHANGE_ME'))) {
            self::upsertUser(
                $pdo,
                $adminUser,
                $adminHash !== '' ? $adminHash : password_hash($adminPassword, PASSWORD_DEFAULT),
                Access::ROLE_ADMIN_EDITOR,
                null,
                'مدیر ویرایشگر',
                $adminHash === '' ? $adminPassword : null
            );
        }

        $viewerUser = trim((string) ($auth['viewer_username'] ?? 'viewer'));
        $viewerPassword = (string) ($auth['viewer_password'] ?? 'viewer');
        $viewerHash = (string) ($auth['viewer_password_hash'] ?? '');
        if ($viewerUser !== '' && ($viewerHash !== '' || ($viewerPassword !== '' && $viewerPassword !== 'CHANGE_ME_VIEWER'))) {
            self::upsertUser(
                $pdo,
                $viewerUser,
                $viewerHash !== '' ? $viewerHash : password_hash($viewerPassword, PASSWORD_DEFAULT),
                Access::ROLE_ADMIN_VIEWER,
                null,
                'مدیر مشاهده‌گر',
                $viewerHash === '' ? $viewerPassword : null
            );
        }
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private static function tableExists(PDO $pdo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'panel_users'");

            return $statement !== false && $statement->fetchColumn() !== false;
        }

        $statement = $pdo->query("SHOW TABLES LIKE 'panel_users'");

        return $statement !== false && $statement->fetchColumn() !== false;
    }

    private static function upsertUser(
        PDO $pdo,
        string $username,
        string $passwordHash,
        string $role,
        ?int $teamId,
        string $fullName,
        ?string $passwordPlain = null
    ): void {
        $existing = $pdo->prepare('SELECT id, password_hash FROM panel_users WHERE username = :username');
        $existing->execute(['username' => $username]);
        $row = $existing->fetch();

        if ($row !== false) {
            if (!hash_equals((string) ($row['password_hash'] ?? ''), $passwordHash)) {
                $update = $pdo->prepare(
                    'UPDATE panel_users SET password_hash = :password_hash, full_name = :full_name'
                    . ($passwordPlain !== null ? ', password_plain = :password_plain' : '')
                    . ' WHERE id = :id'
                );
                $params = [
                    'password_hash' => $passwordHash,
                    'full_name' => $fullName,
                    'id' => (int) $row['id'],
                ];
                if ($passwordPlain !== null) {
                    $params['password_plain'] = $passwordPlain;
                }
                $update->execute($params);
            }

            return;
        }

        $pdo->prepare(
            'INSERT INTO panel_users (username, password_hash, password_plain, role, team_id, full_name, is_active)
             VALUES (:username, :password_hash, :password_plain, :role, :team_id, :full_name, 1)'
        )->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'password_plain' => $passwordPlain,
            'role' => $role,
            'team_id' => $teamId,
            'full_name' => $fullName,
        ]);
    }
}
