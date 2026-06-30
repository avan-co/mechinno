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
                'مدیر ویرایشگر'
            );
        }

        $viewerUser = trim((string) ($auth['viewer_username'] ?? 'viewer'));
        $viewerPassword = (string) ($auth['viewer_password'] ?? 'viewer');
        $viewerHash = (string) ($auth['viewer_password_hash'] ?? '');
        if ($viewerUser !== '') {
            self::upsertUser(
                $pdo,
                $viewerUser,
                $viewerHash !== '' ? $viewerHash : password_hash($viewerPassword, PASSWORD_DEFAULT),
                Access::ROLE_ADMIN_VIEWER,
                null,
                'مدیر مشاهده‌گر'
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
        string $fullName
    ): void {
        $existing = $pdo->prepare('SELECT id FROM panel_users WHERE username = :username');
        $existing->execute(['username' => $username]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return;
        }

        $pdo->prepare(
            'INSERT INTO panel_users (username, password_hash, role, team_id, full_name, is_active)
             VALUES (:username, :password_hash, :role, :team_id, :full_name, 1)'
        )->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'team_id' => $teamId,
            'full_name' => $fullName,
        ]);
    }
}
