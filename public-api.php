<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

try {
    if (!app_configured()) {
        json_response(['error' => 'سیستم هنوز راه‌اندازی نشده است.'], 503);
    }

    $pdo = public_database();
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

    if ($resource === 'month') {
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        $todayParts = JalaliDate::todayParts();
        $year = (int) ($_GET['year'] ?? $todayParts['year']);
        $month = (int) ($_GET['month'] ?? $todayParts['month']);
        json_response($rooms->monthPicker($year, $month));
    }

    if ($resource === 'week') {
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        $from = (string) ($_GET['from'] ?? JalaliDate::todayParts()['formatted']);
        $roomId = (int) ($_GET['room_id'] ?? 0);
        json_response($rooms->publicWeekStatus($from, $roomId));
    }

    if ($resource === 'lookup') {
        $token = trim((string) ($_GET['token'] ?? ''));
        json_response(['record' => $rooms->findByToken($token)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'book') {
        require_same_origin_json();
        if (!$settings['room_public_enabled']) {
            json_response(['error' => 'رزرو عمومی اتاق جلسه غیرفعال است.'], 403);
        }
        $payload = read_json_body();
        $record = $rooms->createFromPayload($payload, 'public');
        json_response(['ok' => true, 'record' => $record]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resource === 'cancel') {
        require_same_origin_json();
        $payload = read_json_body();
        $id = (int) ($payload['id'] ?? 0);
        $token = trim((string) ($payload['token'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($token === '') {
            json_response(['error' => 'کد پیگیری معتبر نیست.'], 422);
        }
        json_response([
            'ok' => true,
            'record' => $rooms->cancel($id, $reason, $token),
        ]);
    }

    json_response(['error' => 'درخواست نامعتبر است.'], 404);
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    json_response(['error' => safe_error_message($exception)], 500);
}
