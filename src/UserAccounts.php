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
                Access::ROLE_ADMIN_EDITOR,
                null,
                'مدیر ویرایشگر',
                $adminHash !== '' ? null : $adminPassword,
                $adminHash !== '' ? $adminHash : null
            );
        }

        $viewerUser = trim((string) ($auth['viewer_username'] ?? 'viewer'));
        $viewerPassword = (string) ($auth['viewer_password'] ?? 'viewer');
        $viewerHash = (string) ($auth['viewer_password_hash'] ?? '');
        if ($viewerUser !== '' && ($viewerHash !== '' || ($viewerPassword !== '' && $viewerPassword !== 'CHANGE_ME_VIEWER'))) {
            self::upsertUser(
                $pdo,
                $viewerUser,
                Access::ROLE_ADMIN_VIEWER,
                null,
                'مدیر مشاهده‌گر',
                $viewerHash === '' ? $viewerPassword : null,
                $viewerHash !== '' ? $viewerHash : null
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
        string $role,
        ?int $teamId,
        string $fullName,
        ?string $passwordPlain = null,
        ?string $passwordHashFromConfig = null
    ): void {
        $existing = $pdo->prepare('SELECT id, password_hash FROM panel_users WHERE username = :username');
        $existing->execute(['username' => $username]);
        $row = $existing->fetch();

        $passwordHash = self::resolvePasswordHash(
            (string) ($row['password_hash'] ?? ''),
            $passwordPlain,
            $passwordHashFromConfig
        );

        if ($row !== false) {
            $storedHash = (string) ($row['password_hash'] ?? '');
            if ($passwordHash !== null && !hash_equals($storedHash, $passwordHash)) {
                $update = $pdo->prepare(
                    'UPDATE panel_users SET password_hash = :password_hash, full_name = :full_name'
                    . ($passwordPlain !== null && $passwordPlain !== '' ? ', password_plain = :password_plain' : '')
                    . ' WHERE id = :id'
                );
                $params = [
                    'password_hash' => $passwordHash,
                    'full_name' => $fullName,
                    'id' => (int) $row['id'],
                ];
                if ($passwordPlain !== null && $passwordPlain !== '') {
                    $params['password_plain'] = $passwordPlain;
                }
                $update->execute($params);
            }

            return;
        }

        if ($passwordHash === null) {
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

    private static function resolvePasswordHash(
        string $storedHash,
        ?string $passwordPlain,
        ?string $passwordHashFromConfig
    ): ?string {
        if ($passwordPlain !== null && $passwordPlain !== '') {
            if ($storedHash !== '' && password_verify($passwordPlain, $storedHash)) {
                return $storedHash;
            }

            return password_hash($passwordPlain, PASSWORD_DEFAULT);
        }

        if ($passwordHashFromConfig !== null && $passwordHashFromConfig !== '') {
            if ($storedHash !== '' && hash_equals($storedHash, $passwordHashFromConfig)) {
                return $storedHash;
            }

            return $passwordHashFromConfig;
        }

        return $storedHash !== '' ? $storedHash : null;
    }
}
