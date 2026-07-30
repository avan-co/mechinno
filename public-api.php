<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    if (!app_configured()) {
        json_response(['error' => 'سیستم هنوز راه‌اندازی نشده است.'], 503);
    }

    $pdo = require_database();
    $rooms = new RoomReservations($pdo);
    $settings = $rooms->settings();

    $resource = (string) ($_GET['resource'] ?? '');
    $action = (string) ($_GET['action'] ?? '');

    if ($resource === 'config') {
        json_response([
            'today' => JalaliDate::todayParts()['formatted'],
            'settings' => [
                'room_public_enabled' => $settings['room_public_enabled'],
                'room_max_advance_days' => $settings['room_max_advance_days'],
                'room_max_hours_per_day' => $settings['room_max_hours_per_day'],
                'room_slot_minutes' => $settings['room_slot_minutes'],
            ],
            'rooms' => $rooms->listActiveRooms(),
        ]);
    }

    if ($resource === 'rooms') {
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        json_response(['rooms' => $rooms->listActiveRooms()]);
    }

    if ($resource === 'availability') {
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        $roomId = (int) ($_GET['room_id'] ?? 0);
        $date = (string) ($_GET['date'] ?? '');
        json_response($rooms->availability($roomId, $date));
    }

    if ($resource === 'lookup') {
        $token = trim((string) ($_GET['token'] ?? ''));
        json_response(['record' => $rooms->findByToken($token)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'book') {
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $record = $rooms->createFromPayload($payload, 'public');
        json_response(['ok' => true, 'record' => $record]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'cancel') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $id = (int) ($payload['id'] ?? 0);
        $token = trim((string) ($payload['token'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? ''));
        json_response([
            'ok' => true,
            'record' => $rooms->cancel($id, $reason, $token !== '' ? $token : null),
        ]);
    }

    json_response(['error' => 'درخواست نامعتبر است.'], 404);
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    json_response(['error' => safe_error_message($exception)], 500);
}
