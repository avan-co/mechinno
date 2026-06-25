<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sql.php';
require_once __DIR__ . '/Identifier.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/JalaliDate.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Seeder.php';
require_once __DIR__ . '/Crud.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/ExcelExporter.php';

function app_base_path(): string
{
    return dirname(__DIR__);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_configured(): bool
{
    return is_file(Database::configPath());
}

/**
 * @return array<string, mixed>
 */
function app_config(): array
{
    return Database::config();
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_path_with_query(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    return $uri === '' ? 'index.php' : $uri;
}

function require_auth(): void
{
    $config = app_config();
    if (!Auth::isEnabled($config) || Auth::check()) {
        return;
    }
    redirect_to('login.php?next=' . rawurlencode(current_path_with_query()));
}

function require_auth_json(): void
{
    $config = app_config();
    if (!Auth::isEnabled($config) || Auth::check()) {
        return;
    }
    json_response(['error' => 'برای دسترسی باید وارد پنل شوید.'], 401);
}

function csrf_token(): string
{
    return Auth::csrfToken();
}

function require_csrf_json(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
    if (!Auth::verifyCsrf(is_string($token) ? $token : null)) {
        json_response(['error' => 'درخواست امنیتی معتبر نیست. صفحه را refresh کنید و دوباره تلاش کنید.'], 403);
    }
}

function require_csrf_html(): ?string
{
    $token = $_POST['csrf_token'] ?? null;
    if (!Auth::verifyCsrf(is_string($token) ? $token : null)) {
        return 'درخواست امنیتی معتبر نیست. صفحه را refresh کنید و دوباره تلاش کنید.';
    }
    return null;
}

function safe_error_message(Throwable $exception): string
{
    error_log($exception);
    try {
        $config = app_configured() ? app_config() : [];
        if ((bool) ($config['debug'] ?? false)) {
            return $exception->getMessage();
        }
    } catch (Throwable) {
    }
    return 'خطای داخلی رخ داد. تنظیمات دیتابیس و فایل‌های راه‌اندازی را بررسی کنید.';
}

function require_database(): PDO
{
    $pdo = Database::connect();
    Schema::migrate($pdo);
    return $pdo;
}
