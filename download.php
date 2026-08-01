<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth();
    $pdo = require_database();
    $resource = (string) ($_GET['resource'] ?? '');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'شناسه فایل نامعتبر است.';
        exit;
    }

    match ($resource) {
        'contract-file' => (new ContractDocuments($pdo))->downloadFile($id),
        'performance-report' => (new PerformanceReports($pdo))->download($id),
        default => (static function (): never {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'منبع دانلود نامعتبر است.';
            exit;
        })(),
    };
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $error->getMessage();
    exit;
} catch (Throwable $error) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo safe_error_message($error);
    exit;
}
