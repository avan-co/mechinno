<?php

declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function isEnabled(array $config): bool
    {
        $auth = $config['auth'] ?? [];
        return (bool) ($auth['enabled'] ?? true);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function configured(array $config): bool
    {
        if (!self::isEnabled($config)) {
            return true;
        }

        $auth = $config['auth'] ?? [];
        $username = trim((string) ($auth['username'] ?? ''));
        $password = (string) ($auth['password'] ?? '');
        $passwordHash = (string) ($auth['password_hash'] ?? '');

        if ($username !== '' && ($passwordHash !== '' || ($password !== '' && $password !== 'CHANGE_ME'))) {
            return true;
        }

        if (trim((string) ($auth['viewer_username'] ?? 'viewer')) !== '') {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function attempt(PDO $pdo, array $config, string $username, string $password): bool
    {
        if (!self::isEnabled($config)) {
            self::establishSession('disabled', Access::ROLE_ADMIN_EDITOR, null, 0);
            return true;
        }

        $username = trim($username);
        if ($username === '') {
            return false;
        }

        if (self::attemptDatabaseUser($pdo, $username, $password)) {
            return true;
        }

        return self::attemptConfigUser($config, $username, $password);
    }

    public static function check(): bool
    {
        self::start();
        return (bool) ($_SESSION['mechinno_authenticated'] ?? false);
    }

    public static function login(string $username): void
    {
        self::establishSession($username, Access::ROLE_ADMIN_EDITOR, null, 0);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['mechinno_csrf'])) {
            $_SESSION['mechinno_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['mechinno_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        $expected = (string) ($_SESSION['mechinno_csrf'] ?? '');
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    private static function attemptDatabaseUser(PDO $pdo, string $username, string $password): bool
    {
        try {
            $statement = $pdo->prepare(
                'SELECT id, username, password_hash, role, team_id, full_name
                 FROM panel_users
                 WHERE username = :username AND is_active = 1
                 LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $row = $statement->fetch();
        } catch (PDOException) {
            return false;
        }

        if ($row === false || !password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            return false;
        }

        $role = (string) ($row['role'] ?? '');
        if (!in_array($role, [Access::ROLE_ADMIN_EDITOR, Access::ROLE_ADMIN_VIEWER, Access::ROLE_TEAM], true)) {
            return false;
        }

        $teamId = isset($row['team_id']) ? (int) $row['team_id'] : null;
        if ($role === Access::ROLE_TEAM && ($teamId === null || $teamId <= 0)) {
            return false;
        }

        self::establishSession(
            (string) $row['username'],
            $role,
            $role === Access::ROLE_TEAM ? $teamId : null,
            (int) $row['id']
        );

        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function attemptConfigUser(array $config, string $username, string $password): bool
    {
        $auth = $config['auth'] ?? [];
        $expectedUsername = (string) ($auth['username'] ?? '');
        if ($expectedUsername !== '' && hash_equals($expectedUsername, $username)) {
            $passwordHash = (string) ($auth['password_hash'] ?? '');
            $validPassword = $passwordHash !== ''
                ? password_verify($password, $passwordHash)
                : hash_equals((string) ($auth['password'] ?? ''), $password);
            if ($validPassword && ($passwordHash !== '' || ((string) ($auth['password'] ?? '')) !== 'CHANGE_ME')) {
                self::establishSession($expectedUsername, Access::ROLE_ADMIN_EDITOR, null, 0);
                return true;
            }
        }

        $viewerUsername = trim((string) ($auth['viewer_username'] ?? 'viewer'));
        if ($viewerUsername !== '' && hash_equals($viewerUsername, $username)) {
            $viewerHash = (string) ($auth['viewer_password_hash'] ?? '');
            $viewerPassword = (string) ($auth['viewer_password'] ?? 'viewer');
            $validViewer = $viewerHash !== ''
                ? password_verify($password, $viewerHash)
                : hash_equals($viewerPassword, $password);
            if ($validViewer) {
                self::establishSession($viewerUsername, Access::ROLE_ADMIN_VIEWER, null, 0);
                return true;
            }
        }

        return false;
    }

    private static function establishSession(string $username, string $role, ?int $teamId, int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['mechinno_authenticated'] = true;
        $_SESSION['mechinno_user'] = $username;
        $_SESSION['mechinno_role'] = $role;
        $_SESSION['mechinno_team_id'] = $teamId;
        $_SESSION['mechinno_user_id'] = $userId;
    }
}
