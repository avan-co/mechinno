<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sql.php';
require_once __DIR__ . '/Identifier.php';
require_once __DIR__ . '/SecretVault.php';
require_once __DIR__ . '/LoginThrottle.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Access.php';
require_once __DIR__ . '/UserAccounts.php';
require_once __DIR__ . '/EntityAccounts.php';
require_once __DIR__ . '/Workflow.php';
require_once __DIR__ . '/CenterSettings.php';
require_once __DIR__ . '/JalaliDate.php';
require_once __DIR__ . '/TeamContracts.php';
require_once __DIR__ . '/DeskAssignments.php';
require_once __DIR__ . '/TeamLeaders.php';
require_once __DIR__ . '/MelliPayamak.php';
require_once __DIR__ . '/SmsService.php';
require_once __DIR__ . '/RoomReservations.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Seeder.php';
require_once __DIR__ . '/Crud.php';
require_once __DIR__ . '/YearBackfill.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/ExcelExporter.php';
require_once __DIR__ . '/ReportData.php';
require_once __DIR__ . '/ReportBuilder.php';
require_once __DIR__ . '/CenterLedger.php';
require_once __DIR__ . '/Brand.php';

function app_base_path(): string
{
    return dirname(__DIR__);
}

function app_web_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    if (app_configured()) {
        $config = app_config();
        $configured = $config['base_path'] ?? null;
        if (is_string($configured) && $configured !== '') {
            $base = rtrim(str_replace('\\', '/', $configured), '/') . '/';
            return $base;
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        $base = '';
        return $base;
    }

    $base = rtrim($dir, '/') . '/';
    return $base;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function avatar_initial(string $text, string $fallback = 'م'): string
{
    $text = trim($text);
    if ($text === '') {
        return $fallback;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 1);
    }
    if (preg_match('/^\X/u', $text, $match)) {
        return $match[0];
    }

    return $fallback;
}

/**
 * Convert Latin digits in a display string to Persian digits so that
 * dates/years shown as chips match the Persian-formatted numbers used
 * elsewhere in the panel. Use only for display, never for input values.
 */
function fa_digits(string $value): string
{
    return strtr($value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

function json_response(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * @return array<string, mixed>
 */
function read_json_body(): array
{
    $raw = (string) file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    return is_array($payload) ? $payload : [];
}

/** Same-origin guard for public mutation endpoints (CSRF mitigation without session). */
function require_same_origin_json(): void
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return;
    }

    $candidates = [
        (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
        (string) ($_SERVER['HTTP_REFERER'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $parts = parse_url($candidate);
        $candidateHost = strtolower((string) ($parts['host'] ?? ''));
        if ($candidateHost === '') {
            continue;
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $full = $candidateHost . $port;
        if ($full === $host || $candidateHost === explode(':', $host)[0]) {
            return;
        }
    }

    // Allow non-browser clients (curl/tests) that send neither Origin nor Referer.
    if ($candidates[0] === '' && $candidates[1] === '') {
        return;
    }

    json_response(['error' => 'درخواست از مبدأ نامعتبر رد شد.'], 403);
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

function log_exception(Throwable $exception): void
{
    $message = sprintf(
        '[%s] %s in %s:%d',
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
    error_log($message);
}

function safe_error_message(Throwable $exception): string
{
    log_exception($exception);
    try {
        $config = app_configured() ? app_config() : [];
        if ((bool) ($config['debug'] ?? false)) {
            return $exception->getMessage();
        }
    } catch (Throwable) {
    }
    if ($exception instanceof PDOException) {
        return 'اتصال به دیتابیس برقرار نشد. تنظیمات db در config.php را بررسی کنید.';
    }
    return 'خطای داخلی رخ داد. تنظیمات دیتابیس و فایل‌های راه‌اندازی را بررسی کنید.';
}

function require_database(): PDO
{
    $pdo = Database::connect();
    Schema::migrate($pdo);
    if (app_configured()) {
        UserAccounts::ensureBootstrapUsers($pdo, app_config());
        EntityAccounts::syncMissingTeams($pdo);
    }

    return $pdo;
}

/** Lightweight DB bootstrap for public pages (no auth user sync). */
function public_database(): PDO
{
    $pdo = Database::connect();
    Schema::migrate($pdo);

    return $pdo;
}

function public_page_error(Throwable $exception): string
{
    log_exception($exception);
    if ($exception instanceof PDOException) {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'no such column')
            || str_contains($message, 'unknown column')
            || str_contains($message, 'room_')) {
            return 'ماژول رزرو اتاق هنوز روی سرور به‌روز نشده است. لطفاً با مدیر فنی تماس بگیرید.';
        }

        return 'در حال حاضر امکان اتصال به سامانه رزرو وجود ندارد. چند دقیقه بعد دوباره تلاش کنید.';
    }

    try {
        $config = app_configured() ? app_config() : [];
        if ((bool) ($config['debug'] ?? false)) {
            return $exception->getMessage();
        }
    } catch (Throwable) {
    }

    return 'خطایی رخ داد. لطفاً بعداً دوباره تلاش کنید.';
}
