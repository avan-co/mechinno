<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth();
    if (Access::isTeam()) {
        redirect_to('team.php');
    }
    $pdo = require_database();
    $report = (string) ($_GET['report'] ?? 'all');
    $filters = [
        'fiscal_year' => (string) ($_GET['fiscal_year'] ?? ''),
        'month_from' => (int) ($_GET['month_from'] ?? 0),
        'month_to' => (int) ($_GET['month_to'] ?? 0),
        'team_id' => (int) ($_GET['team_id'] ?? 0),
    ];
    (new ExcelExporter($pdo))->output($report, $filters);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo safe_error_message($exception);
}
