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
            throw new RuntimeException('config.php not found. Copy config.sample.php to config.php and set your database credentials.');
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('config.php must return an array.');
        }

        date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Tehran'));

        return $config;
    }

    public static function connect(): PDO
    {
        $config = self::config();
        $db = $config['db'] ?? [];
        $driver = (string) ($db['driver'] ?? 'mysql');

        if ($driver === 'sqlite') {
            $path = (string) ($db['path'] ?? dirname(__DIR__) . '/data/mechinno.sqlite3');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path);
        } else {
            $host = (string) ($db['host'] ?? 'localhost');
            $port = (int) ($db['port'] ?? 3306);
            $database = (string) ($db['database'] ?? '');
            $charset = (string) ($db['charset'] ?? 'utf8mb4');
            $username = (string) ($db['username'] ?? '');
            $password = (string) ($db['password'] ?? '');
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            $pdo = new PDO($dsn, $username, $password);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
