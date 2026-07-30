<?php

declare(strict_types=1);

/**
 * Simple file-based login rate limiting per IP + username.
 */
final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public static function isBlocked(string $username): bool
    {
        $key = self::key($username);
        $data = self::read();
        $entry = $data[$key] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $now = time();
        $attempts = array_values(array_filter(
            (array) ($entry['attempts'] ?? []),
            static fn (int $ts): bool => ($now - $ts) < self::WINDOW_SECONDS
        ));

        return count($attempts) >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $username): void
    {
        $key = self::key($username);
        $data = self::read();
        $entry = is_array($data[$key] ?? null) ? $data[$key] : ['attempts' => []];
        $now = time();
        $attempts = array_values(array_filter(
            (array) ($entry['attempts'] ?? []),
            static fn (int $ts): bool => ($now - $ts) < self::WINDOW_SECONDS
        ));
        $attempts[] = $now;
        $data[$key] = ['attempts' => $attempts];
        self::write($data);
    }

    public static function clear(string $username): void
    {
        $key = self::key($username);
        $data = self::read();
        unset($data[$key]);
        self::write($data);
    }

    public static function retryAfterSeconds(string $username): int
    {
        $key = self::key($username);
        $data = self::read();
        $attempts = array_values((array) (($data[$key]['attempts'] ?? [])));
        if (count($attempts) < self::MAX_ATTEMPTS) {
            return 0;
        }
        $oldest = min($attempts);
        $remaining = self::WINDOW_SECONDS - (time() - $oldest);

        return max(0, $remaining);
    }

    private static function key(string $username): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return hash('sha256', strtolower(trim($username)) . '|' . $ip);
    }

    private static function storagePath(): string
    {
        $dir = app_base_path() . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/login_throttle.json';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function read(): array
    {
        $path = self::storagePath();
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private static function write(array $data): void
    {
        $now = time();
        foreach ($data as $key => $entry) {
            $attempts = array_values(array_filter(
                (array) ($entry['attempts'] ?? []),
                static fn (int $ts): bool => ($now - $ts) < self::WINDOW_SECONDS
            ));
            if ($attempts === []) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = ['attempts' => $attempts];
        }

        file_put_contents(
            self::storagePath(),
            json_encode($data, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
