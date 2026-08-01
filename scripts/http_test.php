<?php

declare(strict_types=1);

/**
 * HTTP-level API test via PHP built-in server.
 * Run: php scripts/http_test.php
 */

$root = dirname(__DIR__);
$base = getenv('MECHINNO_TEST_URL') ?: 'http://127.0.0.1:8765';
$cookieFile = sys_get_temp_dir() . '/mechinno_http_test_cookies.txt';
@unlink($cookieFile);

$config = is_file($root . '/config.php') ? require $root . '/config.php' : [];
$auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
$adminUser = (string) ($auth['username'] ?? 'admin');
$adminPass = (string) ($auth['password'] ?? '');
$viewerUser = (string) ($auth['viewer_username'] ?? 'viewer');
$viewerPass = (string) ($auth['viewer_password'] ?? '');

$serverStarted = false;
if (getenv('MECHINNO_TEST_URL') === false) {
    $probe = @file_get_contents($base . '/login.php');
    if ($probe === false) {
        $proc = proc_open(
            'php -S 127.0.0.1:8765 -t ' . escapeshellarg($root),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        if (!is_resource($proc)) {
            fwrite(STDERR, "Cannot start PHP server on 8765. Start manually: php -S 127.0.0.1:8765 -t .\n");
            exit(1);
        }
        $serverStarted = true;
        usleep(800000);
    }
}

$errors = [];
$assert = static function (bool $ok, string $msg) use (&$errors): void {
    if (!$ok) {
        $errors[] = $msg;
    }
};

$assertStatus = static function (int $actual, array $expected, string $msg) use (&$errors): void {
    if (!in_array($actual, $expected, true)) {
        $errors[] = $msg . ' (HTTP ' . $actual . ', expected ' . implode('/', $expected) . ')';
    }
};

$request = static function (string $method, string $path, ?string $body = null, array $extraHeaders = []) use ($base, $cookieFile): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $extraHeaders),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = explode("\r\n\r\n", $raw, 2);
    $responseBody = $parts[1] ?? '';
    $json = json_decode($responseBody, true);

    return ['status' => $status, 'body' => $responseBody, 'json' => is_array($json) ? $json : null];
};

$htmlRequest = static function (string $path) use ($base, $cookieFile): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body];
};

$formLogin = static function (string $username, string $password, string $next = 'index.php') use ($base, $cookieFile, $htmlRequest): bool {
    $page = $htmlRequest('/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $match)) {
        return false;
    }
    $ch = curl_init($base . '/login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $match[1],
            'username' => $username,
            'password' => $password,
            'next' => $next,
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return in_array($status, [302, 303], true);
};

// Login page loads
$r = $htmlRequest('/login.php');
$assert($r['status'] === 200 && str_contains($r['body'], 'ورود به پنل'), 'http: login page loads');

// Login as admin (requires config.php)
if (!is_file($root . '/config.php')) {
    $errors[] = 'http: config.php missing for login tests';
} elseif ($adminPass === '' || !$formLogin($adminUser, $adminPass)) {
    $errors[] = 'http: admin login redirects';
} else {
    $r = $request('GET', '/api.php?resource=summary');
    $assertStatus($r['status'], [200], 'http: admin summary API');
    $assert(isset($r['json']['cards']), 'http: admin summary payload');

    $r = $request('GET', '/api.php?resource=teams&page=1&per_page=25');
    $assert($r['status'] === 200 && isset($r['json']['rows']), 'http: teams list API');

    $r = $request('GET', '/index.php');
    $assert($r['status'] === 200 && str_contains($r['body'], 'Mechinno'), 'http: admin index loads');

    $r = $request('GET', '/report.php');
    $assert($r['status'] === 200 && str_contains($r['body'], 'گزارش'), 'http: report page for admin');

    $r = $request('GET', '/api.php?resource=desks-map');
    $assert($r['status'] === 200 && count($r['json']['rows'] ?? []) === 24, 'http: admin desks-map API');

    $r = $htmlRequest('/reserve.php');
    $assert($r['status'] === 200 && str_contains($r['body'], 'رزرو اتاق جلسه'), 'http: public reserve page loads');

    $r = $request('GET', '/public-api.php?resource=config');
    $assert($r['status'] === 200 && isset($r['json']['rooms']), 'http: public room config API');
    $publicRoomId = (int) ($r['json']['rooms'][0]['id'] ?? 0);
    $publicToday = (string) ($r['json']['today'] ?? '');

    $r = $request('GET', '/api.php?resource=meeting-rooms&page=1&per_page=25');
    $assert($r['status'] === 200 && isset($r['json']['rows']), 'http: admin meeting-rooms list');

    $r = $request('GET', '/api.php?resource=room-settings');
    $assert($r['status'] === 200 && isset($r['json']['room_auto_approve']), 'http: admin room-settings API');

    if ($publicRoomId > 0 && $publicToday !== '') {
        $r = $request('GET', '/public-api.php?resource=availability&room_id=' . $publicRoomId . '&date=' . rawurlencode($publicToday));
        $assert($r['status'] === 200 && isset($r['json']['slots']), 'http: public room availability');
        $slot = null;
        foreach ($r['json']['slots'] ?? [] as $candidate) {
            if (($candidate['status'] ?? '') === 'free') {
                $slot = $candidate;
                break;
            }
        }
        if ($slot !== null) {
            $book = $request('POST', '/public-api.php?resource=book', json_encode([
                'room_id' => $publicRoomId,
                'reserved_date' => $publicToday,
                'start_time' => $slot['time'],
                'end_time' => $slot['end'],
                'booker_name' => 'HTTP تست',
                'booker_phone' => '09120001111',
            ], JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
            $assert(($book['json']['ok'] ?? false) === true, 'http: public room book');
            $bookingId = (int) ($book['json']['record']['id'] ?? 0);
            $bookingToken = (string) ($book['json']['record']['public_token'] ?? '');
            $assert($bookingId > 0 && $bookingToken !== '', 'http: public room book returns token');
            $assert(preg_match('/^MN-\d{6}$/', $bookingToken) === 1, 'http: public token is short MN-###### format');

            $badCancel = $request('POST', '/public-api.php?resource=cancel', json_encode([
                'id' => $bookingId,
            ], JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
            $assert($badCancel['status'] === 422, 'http: public cancel requires token');

            $cancel = $request('POST', '/public-api.php?resource=cancel', json_encode([
                'id' => $bookingId,
                'token' => $bookingToken,
            ], JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
            $assert(($cancel['json']['ok'] ?? false) === true, 'http: public room cancel with token');

            // Two-hour booking: start + exclusive end (e.g. 10:00–12:00).
            $r = $request('GET', '/public-api.php?resource=availability&room_id=' . $publicRoomId . '&date=' . rawurlencode($publicToday));
            $slotMinutes = (int) ($r['json']['slot_minutes'] ?? $r['json']['room']['slot_minutes'] ?? 30);
            $maxHours = (int) ($r['json']['max_hours'] ?? 2);
            $needSlots = (int) (($maxHours * 60) / max(1, $slotMinutes));
            $toMinutes = static function (string $time): int {
                [$h, $m] = array_map('intval', explode(':', $time . ':0'));

                return ($h * 60) + $m;
            };
            $fromMinutes = static function (int $minutes): string {
                return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            };
            $freeTimes = [];
            foreach ($r['json']['slots'] ?? [] as $candidate) {
                if (($candidate['status'] ?? '') === 'free') {
                    $freeTimes[] = (string) ($candidate['time'] ?? '');
                }
            }
            $twoHourStart = null;
            $twoHourEnd = null;
            for ($i = 0; $i < count($freeTimes); $i++) {
                $startMin = $toMinutes($freeTimes[$i]);
                $ok = true;
                for ($j = 1; $j < $needSlots; $j++) {
                    $expected = $fromMinutes($startMin + ($j * $slotMinutes));
                    if (!in_array($expected, $freeTimes, true)) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $twoHourStart = $freeTimes[$i];
                    $twoHourEnd = $fromMinutes($startMin + ($maxHours * 60));
                    break;
                }
            }
            if ($twoHourStart !== null && $twoHourEnd !== null) {
                $book2 = $request('POST', '/public-api.php?resource=book', json_encode([
                    'room_id' => $publicRoomId,
                    'reserved_date' => $publicToday,
                    'start_time' => $twoHourStart,
                    'end_time' => $twoHourEnd,
                    'booker_name' => 'HTTP تست ۲ساعته',
                    'booker_phone' => '09120002222',
                ], JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
                $assert(($book2['json']['ok'] ?? false) === true, 'http: public 2-hour room book');
                $assert((int) ($book2['json']['record']['duration_minutes'] ?? 0) === ($maxHours * 60), 'http: public 2-hour duration stored');
                $request('POST', '/public-api.php?resource=cancel', json_encode([
                    'id' => (int) ($book2['json']['record']['id'] ?? 0),
                    'token' => (string) ($book2['json']['record']['public_token'] ?? ''),
                ], JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
            }
        }
    }

    $r = $request('GET', '/team.php');
    $assert(in_array($r['status'], [302, 303], true) || str_contains($r['body'], 'index.php'), 'http: admin redirected from team panel');

    // Create team via API
    $r = $request('GET', '/index.php');
    preg_match('/csrfToken: "([^"]+)"/', $r['body'], $apiCsrf);
    $apiToken = $apiCsrf[1] ?? '';

    $createTeam = $request('POST', '/api.php?resource=teams&action=create', json_encode([
        'entity_type' => 'team',
        'name' => 'تیم HTTP تست',
        'leader' => 'مسئول تست',
        'phone' => '09121234567',
    ], JSON_UNESCAPED_UNICODE), [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $apiToken,
    ]);
    $assert(($createTeam['json']['ok'] ?? false) === true, 'http: create team via API');
    $newTeamId = (int) ($createTeam['json']['record']['id'] ?? 0);

    $r = $request('GET', '/api.php?resource=teams&page=1&per_page=25');
    $teamRow = null;
    foreach ($r['json']['rows'] ?? [] as $row) {
        if ((int) ($row['id'] ?? 0) === $newTeamId) {
            $teamRow = $row;
            break;
        }
    }
    $assert($teamRow !== null && ($teamRow['portal_username'] ?? '') !== '', 'http: new team has portal username');
    $assert((int) ($teamRow['portal_has_password'] ?? 0) === 1, 'http: new team has portal password flag');
    $entityUser = (string) ($teamRow['portal_username'] ?? '');
    $resetPortal = $request('POST', '/api.php?resource=teams&action=reset-portal-password', json_encode([
        'id' => $newTeamId,
    ], JSON_UNESCAPED_UNICODE), [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $apiToken,
    ]);
    $entityPass = (string) ($resetPortal['json']['credentials']['password'] ?? '');
    $assert($entityPass !== '', 'http: reset portal password returns credentials');

    // Logout
    $request('GET', '/logout.php');

    // Entity login
    $assert($formLogin($entityUser, $entityPass, 'team.php'), 'http: entity login');

    $r = $htmlRequest('/team.php');
    $assert($r['status'] === 200 && str_contains($r['body'], 'تیم HTTP تست'), 'http: entity team panel loads');

    $r = $request('GET', '/api.php?resource=summary');
    $assert($r['status'] === 200 && isset($r['json']['cards']), 'http: entity summary API');

    $r = $request('GET', '/api.php?resource=desks');
    $assert($r['status'] === 200, 'http: entity can access desks list');

    $r = $request('GET', '/api.php?resource=transactions');
    $assert($r['status'] === 200, 'http: entity can access transactions for payment announcements');

    $r = $request('GET', '/api.php?resource=payment-history');
    $assert($r['status'] === 200, 'http: entity can access payment history');

    $r = $request('GET', '/api.php?resource=meeting-rooms&page=1&per_page=25');
    $assert($r['status'] === 200, 'http: entity can list meeting rooms');

    $r = $request('GET', '/api.php?resource=room-reservations&page=1&per_page=10');
    $assert(
        $r['status'] === 200
        && isset($r['json']['rows'], $r['json']['total'], $r['json']['page'], $r['json']['pages'])
        && count($r['json']['rows']) <= 10,
        'http: entity can access paginated room reservations'
    );

    $r = $request('GET', '/api.php?resource=center-settings');
    $assert($r['status'] === 200 && isset($r['json']['bank_name']), 'http: entity can read payment settings');

    $r = $request('GET', '/api.php?resource=pending-members');
    $assert($r['status'] === 403, 'http: entity blocked from pending-members');

    $r = $request('GET', '/api.php?resource=pending-payments');
    $assert($r['status'] === 403, 'http: entity blocked from pending-payments');

    $r = $request('GET', '/report.php');
    $assert(in_array($r['status'], [302, 303], true) || str_contains($r['body'], 'team.php'), 'http: entity blocked from report');

    // Viewer read-only
    $request('GET', '/logout.php');
    $assert($viewerPass !== '' && $formLogin($viewerUser, $viewerPass), 'http: viewer login');

    $r = $request('GET', '/api.php?resource=panel_users');
    $assert($r['status'] === 200, 'http: viewer can list panel_users');

    $viewerIndex = $htmlRequest('/index.php');
    preg_match('/csrfToken: "([^"]+)"/', $viewerIndex['body'], $viewerCsrf);
    $viewerToken = $viewerCsrf[1] ?? '';

    $writeAttempt = $request('POST', '/api.php?resource=teams&action=create', json_encode([
        'entity_type' => 'team',
        'name' => 'نباید ساخته شود',
    ], JSON_UNESCAPED_UNICODE), [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $viewerToken,
    ]);
    $assert($writeAttempt['status'] === 403, 'http: viewer cannot create team');
}

if ($serverStarted && isset($proc) && is_resource($proc)) {
    proc_terminate($proc);
    proc_close($proc);
}
@unlink($cookieFile);

if ($errors !== []) {
    fwrite(STDERR, "HTTP TEST FAILED:\n" . implode("\n", $errors) . "\n");
    exit(1);
}

echo "All HTTP tests passed\n";
