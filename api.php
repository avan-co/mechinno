<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth_json();
    $pdo = require_database();
    $repository = new Repository($pdo);
    $crud = new Crud($pdo);

    $resource = (string) ($_GET['resource'] ?? 'summary');
    $action = (string) ($_GET['action'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'reimport') {
        require_csrf_json();
        $summary = (new Seeder($pdo, app_base_path()))->seedFromFile();
        json_response(['ok' => true, 'summary' => $summary]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'recalculate-charges') {
        require_csrf_json();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $fiscalYear = (string) ($payload['fiscal_year'] ?? $_GET['fiscal_year'] ?? '1404');
        (new Seeder($pdo, app_base_path()))->recalculateCharges($fiscalYear);
        json_response(['ok' => true, 'fiscal_year' => $fiscalYear]);
    }

    if ($resource === 'crud-meta') {
        json_response($crud->meta());
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM import_runs')->fetchColumn() === 0) {
        (new Importer($pdo, app_base_path()))->importAll();
    }

    if ($resource === 'summary') {
        json_response($repository->summary());
    }

    if ($resource === 'charges-matrix') {
        $year = (string) ($_GET['fiscal_year'] ?? '1404');
        json_response($repository->chargesMatrix($year));
    }

    if ($resource === 'team-profile') {
        json_response($repository->teamProfile((int) ($_GET['id'] ?? 0)));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update', 'delete', 'status'], true)) {
        require_csrf_json();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $id = (int) ($payload['id'] ?? 0);

        $result = match ($action) {
            'create' => $crud->create($resource, $payload),
            'update' => $crud->update($resource, $id, $payload),
            'delete' => (function () use ($crud, $resource, $id): array {
                $crud->delete($resource, $id);
                return ['deleted' => true, 'id' => $id];
            })(),
            'status' => $crud->updateStatus($resource, $id, (string) ($payload['status'] ?? '')),
        };

        json_response(['ok' => true, 'record' => $result]);
    }

    $paginatedResources = [
        'teams', 'members', 'desks', 'lockers', 'plans', 'charges',
        'transactions', 'rate_settings', 'team_rates', 'backups', 'warnings',
    ];
    if (in_array($resource, $paginatedResources, true)) {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 25);
        json_response($repository->paginatedResource($resource, $page, $perPage));
    }

    json_response($repository->resource($resource));
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    json_response(['error' => safe_error_message($exception)], 500);
}
