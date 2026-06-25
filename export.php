<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth();
    $pdo = require_database();
    $report = (string) ($_GET['report'] ?? 'all');
    (new ExcelExporter($pdo))->output($report);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo safe_error_message($exception);
}
