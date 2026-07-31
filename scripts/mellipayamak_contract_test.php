#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL: {$message}\n";
};

$calls = [];
$client = new MelliPayamak(static function (string $operation, array $fields, string $json) use (&$calls): string {
    $calls[$operation] = ['fields' => $fields, 'json' => json_decode($json, true)];

    return match ($operation) {
        'SendSMS' => '{"RetStatus":1,"StrRetStatus":"Success","Value":12345}',
        'BaseServiceNumber' => '{"RetStatus":"success","StrRetStatus":"Success"}',
        'GetCredit' => '{"RetStatus":1,"Value":100000}',
        'GetBasePrice' => '{"RetStatus":1,"Value":2500}',
        'GetUserNumbers' => '{"RetStatus":1,"Value":["30001234"]}',
        'GetMessages' => '{"RetStatus":1,"Value":[]}',
        'GetDeliveries2' => '{"RetStatus":1,"Value":1}',
        default => '{"RetStatus":-1,"StrRetStatus":"Unknown operation"}',
    };
});

$send = $client->send(' user ', ' pass ', '30001234', '09123456789', 'پیام آزمایشی');
$assert($send['ok'] === true && $send['rec_id'] === '12345', 'SendSMS accepts RetStatus success and returns RecId');
$assert(
    ($calls['SendSMS']['fields'] ?? []) === [
        'username' => ' user ',
        'password' => ' pass ',
        'to' => '09123456789',
        'from' => '30001234',
        'text' => 'پیام آزمایشی',
        'isflash' => 'false',
    ],
    'SendSMS uses documented JSON field names'
);
$assert(($calls['SendSMS']['json'] ?? null) === ($calls['SendSMS']['fields'] ?? null), 'SendSMS JSON body matches fields');

$pattern = $client->sendByBaseNumber('user', 'pass', '09123456789', 42, 'نام تیم');
$assert($pattern['ok'] === true && $pattern['rec_id'] === null, 'BaseServiceNumber accepts successful pattern response without RecId');
$assert(
    ($calls['BaseServiceNumber']['fields'] ?? []) === [
        'username' => 'user',
        'password' => 'pass',
        'text' => 'نام تیم',
        'to' => '09123456789',
        'bodyId' => 42,
    ],
    'BaseServiceNumber sends documented JSON fields'
);

$client->getCredit('user', 'pass');
$client->getBasePrice('user', 'pass');
$client->getUserNumbers('user', 'pass');
$client->getMessages('user', 'pass', 2, 0, 200, '30001234');
$client->getDelivery('user', 'pass', '12345');

foreach (['GetCredit', 'GetBasePrice', 'GetUserNumbers', 'GetMessages', 'GetDeliveries2'] as $operation) {
    $fields = $calls[$operation]['fields'] ?? [];
    $assert(isset($fields['username'], $fields['password']), "{$operation} uses lowercase credentials");
    $assert(!isset($fields['UserName'], $fields['PassWord']), "{$operation} omits legacy credential fields");
}
$assert(
    ($calls['GetMessages']['fields'] ?? []) === [
        'username' => 'user',
        'password' => 'pass',
        'location' => 2,
        'index' => 0,
        'count' => 200,
        'from' => '30001234',
    ],
    'GetMessages uses documented lowercase fields'
);

$invalidPattern = $client->sendByBaseNumber('user', 'pass', '09123456789', 0, 'متغیر');
$assert($invalidPattern['ok'] === false, 'BaseServiceNumber rejects an invalid template id');

$errorClient = new MelliPayamak(static fn (): string => '{"RetStatus":-1,"StrRetStatus":"Invalid credentials"}');
$error = $errorClient->send('user', 'pass', '30001234', '09123456789', 'پیام');
$assert($error['ok'] === false && $error['error'] === 'Invalid credentials', 'API errors preserve StrRetStatus');

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " MelliPayamak contract test(s) failed.\n");
    exit(1);
}

echo "\nAll MelliPayamak contract tests passed.\n";
