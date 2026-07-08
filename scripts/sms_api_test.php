#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Manual MelliPayamak API connectivity test.
 *
 * Usage:
 *   php scripts/sms_api_test.php
 *   php scripts/sms_api_test.php YOUR_USERNAME YOUR_PASSWORD
 *
 * Without arguments, reads saved credentials from center_settings (requires config.php).
 */

require __DIR__ . '/../src/bootstrap.php';

$username = trim((string) ($argv[1] ?? ''));
$password = trim((string) ($argv[2] ?? ''));

if ($username === '' || $password === '') {
    if (!app_configured()) {
        fwrite(STDERR, "Usage: php scripts/sms_api_test.php USERNAME PASSWORD\n");
        fwrite(STDERR, "Or configure config.php and save SMS credentials in the panel first.\n");
        exit(1);
    }
    $pdo = require_database();
    $send = (new CenterSettings($pdo))->smsSettingsForSend();
    $username = trim((string) ($send['sms_username'] ?? ''));
    $password = trim((string) ($send['sms_password'] ?? ''));
    if ($username === '' || $password === '') {
        fwrite(STDERR, "No saved SMS credentials found. Pass username/password or save them in SMS settings.\n");
        exit(1);
    }
    echo "Using saved credentials for: {$username}\n\n";
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP curl extension is required.\n");
    exit(1);
}

$client = new MelliPayamak();

$print = static function (string $label, array $result): void {
    echo "== {$label} ==\n";
    if (($result['ok'] ?? false) === true) {
        $value = $result['credit'] ?? $result['price'] ?? $result['numbers'] ?? $result['raw'] ?? 'OK';
        if (is_array($value)) {
            echo 'OK — ' . count($value) . " item(s)\n";
            echo implode(', ', array_slice($value, 0, 10)) . (count($value) > 10 ? '…' : '') . "\n";
        } else {
            echo 'OK — ' . (string) $value . "\n";
        }
    } else {
        echo 'FAIL — ' . (string) ($result['error'] ?? 'unknown error') . "\n";
        if (!empty($result['raw'])) {
            echo 'Raw: ' . (is_string($result['raw']) ? $result['raw'] : json_encode($result['raw'], JSON_UNESCAPED_UNICODE)) . "\n";
        }
    }
    echo "\n";
};

$credit = $client->getCredit($username, $password);
$print('GetCredit', $credit);

$price = $client->getBasePrice($username, $password);
$print('GetBasePrice', $price);

$lines = $client->getUserNumbers($username, $password);
$print('GetUserNumbers', $lines);

$ok = ($credit['ok'] ?? false) || ($price['ok'] ?? false) || (($lines['ok'] ?? false) && ($lines['numbers'] ?? []) !== []);
echo $ok ? "RESULT: API connection looks healthy.\n" : "RESULT: API connection failed. Check username/password and REST access in MelliPayamak panel.\n";
exit($ok ? 0 : 1);
