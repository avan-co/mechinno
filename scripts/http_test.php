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
} elseif (!$formLogin('admin', 'TestAdmin123')) {
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
    $entityUser = (string) ($teamRow['portal_username'] ?? '');
    $entityPass = (string) ($teamRow['portal_password'] ?? '');

    // Logout
    $request('GET', '/logout.php');

    // Entity login
    $assert($formLogin($entityUser, $entityPass, 'team.php'), 'http: entity login');

    $r = $htmlRequest('/team.php');
    $assert($r['status'] === 200 && str_contains($r['body'], 'تیم HTTP تست'), 'http: entity team panel loads');

    $r = $request('GET', '/api.php?resource=desks');
    $assert($r['status'] === 200, 'http: entity can access desks list');

    $r = $request('GET', '/api.php?resource=transactions');
    $assert($r['status'] === 200, 'http: entity can access transactions for payment announcements');

    $r = $request('GET', '/api.php?resource=payment-history');
    $assert($r['status'] === 200, 'http: entity can access payment history');

    $r = $request('GET', '/api.php?resource=pending-members');
    $assert($r['status'] === 403, 'http: entity blocked from pending-members');

    $r = $request('GET', '/api.php?resource=pending-payments');
    $assert($r['status'] === 403, 'http: entity blocked from pending-payments');

    $r = $request('GET', '/report.php');
    $assert(in_array($r['status'], [302, 303], true) || str_contains($r['body'], 'team.php'), 'http: entity blocked from report');

    // Viewer read-only
    $request('GET', '/logout.php');
    $assert($formLogin('viewer', 'TestViewer123'), 'http: viewer login');

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
