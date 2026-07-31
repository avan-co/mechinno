<?php

declare(strict_types=1);

final class Database
{
    public static function configPath(): string
    {
        return dirname(__DIR__) . '/config.php';
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $path = self::configPath();
        if (!is_file($path)) {
            throw new RuntimeException('فایل config.php پیدا نشد. ابتدا config.sample.php را به config.php کپی کنید.');
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('فایل config.php باید یک آرایه برگرداند.');
        }

        date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Tehran'));

        return $config;
    }

    public static function connect(): PDO
    {
        $config = self::config();
        $db = is_array($config['db'] ?? null) ? $config['db'] : [];
        $driver = strtolower((string) ($db['driver'] ?? 'mysql'));

        if ($driver === 'sqlite') {
            $path = (string) ($db['path'] ?? dirname(__DIR__) . '/data/mechinno.sqlite3');
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('پوشه data برای SQLite ساخته نشد. مجوز نوشتن سرور را بررسی کنید.');
            }
            try {
                $pdo = new PDO('sqlite:' . $path);
            } catch (PDOException $exception) {
                throw new RuntimeException('اتصال به SQLite برقرار نشد: مسیر فایل یا مجوز نوشتن را بررسی کنید.', 0, $exception);
            }
        } else {
            $host = trim((string) ($db['host'] ?? 'localhost'));
            $port = (int) ($db['port'] ?? 3306);
            $database = trim((string) ($db['database'] ?? ''));
            $charset = trim((string) ($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
            $username = trim((string) ($db['username'] ?? ''));
            $password = (string) ($db['password'] ?? '');

            self::assertMysqlConfigReady($host, $database, $username, $password);

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            try {
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException $exception) {
                throw new RuntimeException(self::mysqlConnectionHint($exception, $host, $port, $database, $username), 0, $exception);
            }
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private static function assertMysqlConfigReady(string $host, string $database, string $username, string $password): void
    {
        if ($host === '') {
            throw new RuntimeException('مقدار db.host در config.php خالی است.');
        }
        if ($database === '' || self::isPlaceholder($database, ['YOUR_DB_NAME', 'DATABASE', 'DB_NAME'])) {
            throw new RuntimeException('نام دیتابیس (db.database) هنوز مقدار نمونه است. نام واقعی دیتابیس cPanel را در config.php بگذارید.');
        }
        if ($username === '' || self::isPlaceholder($username, ['YOUR_DB_USER', 'USERNAME', 'DB_USER'])) {
            throw new RuntimeException('نام کاربری دیتابیس (db.username) هنوز مقدار نمونه است. کاربر MySQL واقعی را در config.php بگذارید.');
        }
        if ($password === '' || str_starts_with($password, 'CHANGE_ME') || self::isPlaceholder($password, ['YOUR_DB_PASSWORD', 'PASSWORD'])) {
            throw new RuntimeException('رمز دیتابیس (db.password) هنوز مقدار نمونه یا خالی است. رمز واقعی MySQL را در config.php بگذارید.');
        }
    }

    /**
     * @param list<string> $tokens
     */
    private static function isPlaceholder(string $value, array $tokens): bool
    {
        $upper = strtoupper($value);
        if (str_starts_with($upper, 'YOUR_')) {
            return true;
        }
        foreach ($tokens as $token) {
            if ($upper === strtoupper($token)) {
                return true;
            }
        }

        return false;
    }

    private static function mysqlConnectionHint(
        PDOException $exception,
        string $host,
        int $port,
        string $database,
        string $username
    ): string {
        $detail = $exception->getMessage();
        $lower = strtolower($detail);

        if (str_contains($lower, 'access denied')) {
            return "دسترسی MySQL رد شد. کاربر «{$username}» یا رمز عبور برای دیتابیس «{$database}» نادرست است.";
        }
        if (str_contains($lower, 'unknown database')) {
            return "دیتابیس «{$database}» روی سرور پیدا نشد. ابتدا آن را در cPanel بسازید یا نام را در config.php اصلاح کنید.";
        }
        if (str_contains($lower, 'could not find driver')) {
            return 'افزونه PDO MySQL روی سرور فعال نیست. در cPanel گزینه PDO و pdo_mysql را فعال کنید.';
        }
        if (str_contains($lower, 'connection refused') || str_contains($lower, 'timed out') || str_contains($lower, 'getaddrinfo')) {
            return "اتصال به میزبان «{$host}:{$port}» برقرار نشد. معمولاً در cPanel مقدار host برابر localhost است.";
        }

        $config = [];
        try {
            $config = self::config();
        } catch (Throwable) {
        }
        if ((bool) ($config['debug'] ?? false)) {
            return 'اتصال به دیتابیس برقرار نشد: ' . $detail;
        }

        return 'اتصال به دیتابیس برقرار نشد. تنظیمات db در config.php (host، database، username، password) را بررسی کنید.';
    }
}
