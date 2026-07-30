<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    require_auth_json();
    $pdo = require_database();
    $repository = new Repository($pdo);
    $crud = new Crud($pdo);
    $workflow = new Workflow($pdo);
    $rooms = new RoomReservations($pdo);

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
        $teamId = (int) ($payload['team_id'] ?? 0);
        $seeder = new Seeder($pdo);
        if ($teamId > 0) {
            $seeder->recalculateChargesForTeam($teamId, $fiscalYear);
        } else {
            $seeder->recalculateCharges($fiscalYear);
        }
        json_response(['ok' => true, 'fiscal_year' => $fiscalYear, 'team_id' => $teamId > 0 ? $teamId : null]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'bulk-year-import') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $result = (new YearBackfill($pdo, $crud))->import($payload);
        json_response(['ok' => true] + $result);
    }

    if ($resource === 'crud-meta') {
        json_response($crud->meta());
    }

    if ($resource === 'summary') {
        json_response($repository->summary());
    }

    if ($resource === 'ledger') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(200, max(25, (int) ($_GET['per_page'] ?? 100)));
        json_response((new CenterLedger($pdo))->snapshot($page, $perPage));
    }

    if ($resource === 'report-catalog') {
        json_response((new ReportBuilder($pdo))->catalog());
    }

    if ($resource === 'reports') {
        $filters = [
            'type' => (string) ($_GET['type'] ?? 'finance'),
            'period' => (string) ($_GET['period'] ?? 'monthly'),
            'fiscal_year' => (string) ($_GET['fiscal_year'] ?? ''),
            'month' => (int) ($_GET['month'] ?? 0),
            'quarter' => (int) ($_GET['quarter'] ?? 0),
            'month_from' => (int) ($_GET['month_from'] ?? 0),
            'month_to' => (int) ($_GET['month_to'] ?? 0),
            'team_id' => (int) ($_GET['team_id'] ?? 0),
        ];
        // Keep empty numeric filters unset so defaults apply.
        foreach (['month', 'quarter', 'month_from', 'month_to'] as $key) {
            if (($filters[$key] ?? 0) <= 0) {
                unset($filters[$key]);
            }
        }
        if ($filters['fiscal_year'] === '') {
            unset($filters['fiscal_year']);
        }
        json_response((new ReportBuilder($pdo))->build($filters));
    }

    if ($resource === 'charges-matrix') {
        $year = JalaliDate::normalizeDigits((string) ($_GET['fiscal_year'] ?? '1404'));
        json_response($repository->chargesMatrix($year));
    }

    if ($resource === 'charge-fiscal-years') {
        json_response(['years' => $repository->chargeFiscalYears()]);
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

    if ($resource === 'team-payable-months') {
        $teamId = Access::scopedTeamId() ?? (int) ($_GET['team_id'] ?? 0);
        if ($teamId <= 0) {
            json_response(['error' => 'نهاد معتبر نیست.'], 422);
        }
        json_response(['months' => $repository->teamPayableMonths($teamId)]);
    }

    if ($resource === 'teams' && $action === 'portal-credentials' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        Access::requireWriteJson();
        $teamId = (int) ($_GET['id'] ?? 0);
        if ($teamId <= 0) {
            json_response(['error' => 'نهاد معتبر نیست.'], 422);
        }
        json_response($repository->teamPortalCredentials($teamId));
    }

    if ($resource === 'center-settings') {
        $settings = new CenterSettings($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_json();
            Access::requireWriteJson();
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }
            json_response(['ok' => true, 'settings' => $settings->update($payload)]);
        }
        json_response($settings->get());
    }

    if ($resource === 'room-settings') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_json();
            Access::requireWriteJson();
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }
            json_response(['ok' => true, 'settings' => $rooms->updateSettings($payload)]);
        }
        json_response($rooms->settings());
    }

    if ($resource === 'room-availability') {
        $roomId = (int) ($_GET['room_id'] ?? 0);
        $date = (string) ($_GET['date'] ?? '');
        json_response($rooms->availability($roomId, $date));
    }

    if ($resource === 'room-calendar') {
        if (isset($_GET['shift_from'], $_GET['shift_days'])) {
            $from = JalaliDate::addDays((string) $_GET['shift_from'], (int) $_GET['shift_days']);
        } else {
            $from = (string) ($_GET['from'] ?? JalaliDate::todayParts()['formatted']);
        }
        $to = (string) ($_GET['to'] ?? JalaliDate::addDays($from, 6));
        $roomId = (int) ($_GET['room_id'] ?? 0);
        json_response($rooms->calendarRange($from, $to, $roomId));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'room-reservations' && $action === 'create') {
        require_csrf_json();
        Access::requireWriteOrTeamSubmitJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $source = Access::isTeam() ? 'team' : 'admin';
        json_response(['ok' => true, 'record' => $rooms->createFromPayload($payload, $source)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'room-reservations' && $action === 'cancel') {
        require_csrf_json();
        Access::requireWriteOrTeamSubmitJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $id = (int) ($payload['id'] ?? 0);
        $reason = trim((string) ($payload['reason'] ?? ''));
        json_response(['ok' => true, 'record' => $rooms->cancel($id, $reason)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'teams' && $action === 'change-leader') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $teamId = (int) ($payload['id'] ?? 0);
        $memberId = (int) ($payload['member_id'] ?? 0);
        if ($teamId <= 0 || $memberId <= 0) {
            json_response(['error' => 'نهاد و عضو معتبر انتخاب کنید.'], 422);
        }
        $team = (new TeamLeaders($pdo))->changeLeader($teamId, $memberId);
        json_response(['ok' => true, 'team' => $team]);
    }

    if ($resource === 'sms-settings') {
        $sms = new SmsService($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_json();
            Access::requireWriteJson();
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }
            json_response(['ok' => true, 'settings' => $sms->updateSettings($payload)]);
        }
        json_response($sms->settings(isset($_GET['live']) && (string) $_GET['live'] === '1'));
    }

    if ($resource === 'sms-stats') {
        json_response((new SmsService($pdo))->stats(isset($_GET['live']) && (string) $_GET['live'] === '1'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'sms-query-lines') {
        require_csrf_json();
        Access::requireWriteJson();
        json_response(['ok' => true, 'result' => (new SmsService($pdo))->queryLines()]);
    }

    if ($resource === 'sms-test') {
        $sms = new SmsService($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_json();
            Access::requireWriteJson();
            json_response(['ok' => true, 'result' => $sms->testConnection()]);
        }
        json_response(['error' => 'Method not allowed'], 405);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'sms-sync-history') {
        require_csrf_json();
        Access::requireWriteJson();
        json_response(['ok' => true, 'result' => (new SmsService($pdo))->syncHistoryFromApi()]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'sms-check-deliveries') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $logIds = array_map('intval', (array) ($payload['log_ids'] ?? []));
        $batchUid = trim((string) ($payload['batch_uid'] ?? ''));
        json_response([
            'ok' => true,
            'result' => (new SmsService($pdo))->checkDeliveries(
                $logIds !== [] ? $logIds : null,
                $batchUid !== '' ? $batchUid : null
            ),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'sms-send') {
        require_csrf_json();
        Access::requireWriteJson();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $message = trim((string) ($payload['message'] ?? ''));
        $memberIds = array_map('intval', (array) ($payload['member_ids'] ?? []));
        json_response(['ok' => true, 'result' => (new SmsService($pdo))->sendAnnouncement($message, $memberIds)]);
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
        $password = array_key_exists('password', $payload) ? (string) $payload['password'] : null;
        $credentials = EntityAccounts::resetPassword($pdo, $teamId, $password);
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
        $accessCode = trim((string) ($payload['access_code'] ?? ''));
        $lockerNumber = (int) ($payload['locker_number'] ?? 0);

        $result = match ($resource . ':' . $action) {
            'members:approve', 'pending-members:approve' => $workflow->approveMember($id, $accessCode),
            'members:reject', 'pending-members:reject' => $workflow->rejectMember($id, $reason),
            'transactions:approve', 'pending-payments:approve' => $workflow->approvePayment($id),
            'transactions:reject', 'pending-payments:reject' => $workflow->rejectPayment($id, $reason),
            'pending-locker-requests:approve', 'locker-requests:approve' => $workflow->approveLockerRequest($id, $lockerNumber),
            'pending-locker-requests:reject', 'locker-requests:reject' => $workflow->rejectLockerRequest($id, $reason),
            'pending-member-requests:approve', 'member-requests:approve' => $workflow->approveMemberRequest($id),
            'pending-member-requests:reject', 'member-requests:reject' => $workflow->rejectMemberRequest($id, $reason),
            'pending-room-reservations:approve', 'room-reservations:approve' => $rooms->approve($id),
            'pending-room-reservations:reject', 'room-reservations:reject' => $rooms->reject($id, $reason),
            default => throw new InvalidArgumentException('عملیات تأیید/رد برای این بخش تعریف نشده است.'),
        };

        json_response(['ok' => true, 'record' => $result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update', 'delete', 'status'], true)) {
        require_csrf_json();
        if (in_array($resource, ['members', 'transactions', 'locker-requests', 'member-requests'], true) && $action === 'create') {
            Access::requireWriteOrTeamSubmitJson();
        } elseif (
            Access::isTeam()
            && in_array($action, ['update', 'delete'], true)
            && in_array($resource, ['transactions', 'locker-requests', 'member-requests', 'members'], true)
        ) {
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
        $crudResource = match ($resource) {
            'locker-requests' => 'locker_requests',
            'member-requests' => 'member_requests',
            'desk-assignments' => 'desk_assignments',
            'meeting-rooms' => 'meeting_rooms',
            default => $resource,
        };

        $result = match ($action) {
            'create' => $crud->create($crudResource, $payload),
            'update' => $crud->update($crudResource, $id, $payload),
            'delete' => (function () use ($crud, $crudResource, $id): array {
                $crud->delete($crudResource, $id);
                return ['deleted' => true, 'id' => $id];
            })(),
            'status' => $crud->updateStatus($crudResource, $id, (string) ($payload['status'] ?? '')),
        };

        json_response(['ok' => true, 'record' => $result]);
    }

    $paginatedResources = [
        'teams', 'members', 'desks', 'lockers', 'charges', 'transactions', 'rate_settings', 'panel_users',
        'development_plans', 'pending-members', 'pending-member-requests', 'pending-payments', 'pending-locker-requests',
        'locker-requests', 'member-requests', 'desk-assignments', 'payment-history', 'team_contracts',
        'sms-recipients', 'sms-history', 'meeting-rooms', 'room-reservations', 'pending-room-reservations',
    ];
    if (in_array($resource, $paginatedResources, true)) {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 25);
        $filters = [];
        if ($resource === 'transactions' && isset($_GET['category']) && $_GET['category'] !== '') {
            $filters['category'] = (string) $_GET['category'];
        }
        if ($resource === 'transactions' && isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
            $filters['payment_status'] = (string) $_GET['payment_status'];
        }
        if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
            $filters['q'] = trim((string) $_GET['q']);
        }
        if ($resource === 'payment-history' && isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
            $filters['payment_status'] = (string) $_GET['payment_status'];
        }
        if ($resource === 'members' && isset($_GET['approval_status']) && $_GET['approval_status'] !== '') {
            $filters['approval_status'] = (string) $_GET['approval_status'];
        }
        if (in_array($resource, ['members', 'sms-recipients'], true)) {
            if (isset($_GET['team_id']) && $_GET['team_id'] !== '') {
                $filters['team_id'] = (string) $_GET['team_id'];
            }
            if (isset($_GET['entity_type']) && $_GET['entity_type'] !== '') {
                $filters['entity_type'] = (string) $_GET['entity_type'];
            }
            if (isset($_GET['is_leader']) && $_GET['is_leader'] !== '') {
                $filters['is_leader'] = (string) $_GET['is_leader'];
            }
            if (isset($_GET['wants_access']) && $_GET['wants_access'] !== '') {
                $filters['wants_access'] = (string) $_GET['wants_access'];
            }
        }
        if ($resource === 'sms-history') {
            if (isset($_GET['message_type']) && $_GET['message_type'] !== '') {
                $filters['message_type'] = (string) $_GET['message_type'];
            }
            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $filters['status'] = (string) $_GET['status'];
            }
            if (isset($_GET['team_id']) && $_GET['team_id'] !== '') {
                $filters['team_id'] = (string) $_GET['team_id'];
            }
        }
        if ($resource === 'desk-assignments' && isset($_GET['fiscal_year']) && $_GET['fiscal_year'] !== '') {
            $filters['fiscal_year'] = (string) $_GET['fiscal_year'];
        }
        if ($resource === 'desk-assignments' && isset($_GET['team_id']) && $_GET['team_id'] !== '') {
            $filters['team_id'] = (string) $_GET['team_id'];
        }
        if ($resource === 'team_contracts' && isset($_GET['team_id']) && $_GET['team_id'] !== '') {
            $filters['team_id'] = (string) $_GET['team_id'];
        }
        if ($resource === 'desk-assignments' && isset($_GET['assignment_status']) && $_GET['assignment_status'] !== '') {
            $filters['assignment_status'] = (string) $_GET['assignment_status'];
        }
        if ($resource === 'room-reservations' && isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = (string) $_GET['status'];
        }
        json_response($repository->paginatedResource($resource, $page, $perPage, $filters));
    }

    json_response($repository->resource($resource));
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    json_response(['error' => safe_error_message($exception)], 500);
}
