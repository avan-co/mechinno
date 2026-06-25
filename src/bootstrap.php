<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/XlsxReader.php';
require_once __DIR__ . '/Importer.php';
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

function require_database(): PDO
{
    $pdo = Database::connect();
    Schema::migrate($pdo);

    return $pdo;
}
