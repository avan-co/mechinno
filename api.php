<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth_json();
    $pdo = require_database();
    $repository = new Repository($pdo);
    $crud = new Crud($pdo);
    $workflow = new Workflow($pdo);

    $resource = (string) ($_GET['resource'] ?? 'summary');
    $action = (string) ($_GET['action'] ?? '');

    if ($resource === 'panel_users' && Access::isTeam()) {
        json_response(['error' => 'دسترسی به مدیریت کاربران مجاز نیست.'], 403);
    }

    if (!in_array($resource, ['crud-meta'], true)) {
        Access::assertResourceAllowed($resource === 'recalculate-charges' ? 'recalculate-charges' : $resource);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'recalculate-charges') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $fiscalYear = JalaliDate::normalizeDigits((string) ($payload['fiscal_year'] ?? $_GET['fiscal_year'] ?? '1404'));
        (new Seeder($pdo))->recalculateCharges($fiscalYear);
        json_response(['ok' => true, 'fiscal_year' => $fiscalYear]);
    }

    if ($resource === 'crud-meta') {
        json_response($crud->meta());
    }

    if ($resource === 'summary') {
        json_response($repository->summary());
    }

    if ($resource === 'charges-matrix') {
        $year = JalaliDate::normalizeDigits((string) ($_GET['fiscal_year'] ?? '1404'));
        json_response($repository->chargesMatrix($year));
    }

    if ($resource === 'team-profile') {
        $teamId = (int) ($_GET['id'] ?? 0);
        if (Access::isTeam()) {
            $teamId = Access::scopedTeamId() ?? $teamId;
        }
        json_response($repository->teamProfile($teamId));
    }

    if ($resource === 'desks-map') {
        json_response($repository->deskMap());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'teams' && $action === 'reset-portal-password') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $teamId = (int) ($payload['id'] ?? 0);
        if ($teamId <= 0) {
            json_response(['error' => 'نهاد معتبر نیست.'], 422);
        }
        $credentials = EntityAccounts::resetPassword($pdo, $teamId);
        json_response(['ok' => true, 'credentials' => $credentials]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['approve', 'reject'], true)) {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $id = (int) ($payload['id'] ?? 0);
        $reason = trim((string) ($payload['reason'] ?? ''));

        $result = match ($resource . ':' . $action) {
            'members:approve', 'pending-members:approve' => $workflow->approveMember($id),
            'members:reject', 'pending-members:reject' => $workflow->rejectMember($id, $reason),
            'transactions:approve', 'pending-payments:approve' => $workflow->approvePayment($id),
            'transactions:reject', 'pending-payments:reject' => $workflow->rejectPayment($id, $reason),
            default => throw new InvalidArgumentException('عملیات تأیید/رد برای این بخش تعریف نشده است.'),
        };

        json_response(['ok' => true, 'record' => $result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update', 'delete', 'status'], true)) {
        require_csrf_json();
        if (in_array($resource, ['members', 'transactions'], true) && $action === 'create') {
            Access::requireWriteOrTeamSubmitJson();
        } else {
            Access::requireWriteJson();
        }
        if ($resource === 'panel_users' && !Access::canWrite()) {
            json_response(['error' => 'مدیریت کاربران فقط برای مدیر ویرایشگر مجاز است.'], 403);
        }
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
        'teams', 'members', 'desks', 'lockers', 'charges', 'transactions', 'rate_settings', 'panel_users',
        'development_plans', 'pending-members', 'pending-payments', 'payment-history',
    ];
    if (in_array($resource, $paginatedResources, true)) {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 25);
        $filters = [];
        if ($resource === 'transactions' && isset($_GET['category']) && $_GET['category'] !== '') {
            $filters['category'] = (string) $_GET['category'];
        }
        json_response($repository->paginatedResource($resource, $page, $perPage, $filters));
    }

    json_response($repository->resource($resource));
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    json_response(['error' => safe_error_message($exception)], 500);
}
