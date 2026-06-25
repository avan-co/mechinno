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

        if ($username === '') {
            return false;
        }
        if ($passwordHash !== '') {
            return true;
        }

        return $password !== '' && $password !== 'CHANGE_ME';
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function attempt(array $config, string $username, string $password): bool
    {
        if (!self::isEnabled($config)) {
            self::login('disabled');
            return true;
        }
        if (!self::configured($config)) {
            return false;
        }

        $auth = $config['auth'] ?? [];
        $expectedUsername = (string) ($auth['username'] ?? '');
        if (!hash_equals($expectedUsername, $username)) {
            return false;
        }

        $passwordHash = (string) ($auth['password_hash'] ?? '');
        $validPassword = $passwordHash !== ''
            ? password_verify($password, $passwordHash)
            : hash_equals((string) ($auth['password'] ?? ''), $password);

        if (!$validPassword) {
            return false;
        }

        self::login($expectedUsername);
        return true;
    }

    public static function check(): bool
    {
        self::start();
        return (bool) ($_SESSION['mechinno_authenticated'] ?? false);
    }

    public static function login(string $username): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['mechinno_authenticated'] = true;
        $_SESSION['mechinno_user'] = $username;
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
}
