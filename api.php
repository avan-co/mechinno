<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    $pdo = require_database();
    $repository = new Repository($pdo);

    if ((int) $pdo->query('SELECT COUNT(*) FROM import_runs')->fetchColumn() === 0) {
        (new Importer($pdo, app_base_path()))->importAll();
    }

    $resource = (string) ($_GET['resource'] ?? 'summary');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'reimport') {
        $summary = (new Importer($pdo, app_base_path()))->importAll();
        json_response(['ok' => true, 'summary' => $summary]);
    }

    if ($resource === 'summary') {
        json_response($repository->summary());
    }

    json_response($repository->resource($resource));
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    json_response(['error' => $exception->getMessage()], 500);
}
