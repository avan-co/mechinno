<?php

declare(strict_types=1);

final class MelliPayamak
{
    private const ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';

    /**
     * @return array{ok:bool, rec_id:?string, error:?string, raw:string}
     */
    public function send(string $username, string $password, string $from, string $to, string $text): array
    {
        $username = trim($username);
        $password = trim($password);
        $from = trim($from);
        $to = TeamLeaders::normalizePhone($to);
        $text = trim($text);

        if ($username === '' || $password === '' || $from === '') {
            return ['ok' => false, 'rec_id' => null, 'error' => 'تنظیمات پیامک کامل نیست.', 'raw' => ''];
        }
        if ($to === '' || !preg_match('/^09\d{9}$/', $to)) {
            return ['ok' => false, 'rec_id' => null, 'error' => 'شماره موبایل گیرنده معتبر نیست.', 'raw' => ''];
        }
        if ($text === '') {
            return ['ok' => false, 'rec_id' => null, 'error' => 'متن پیامک خالی است.', 'raw' => ''];
        }

        $payload = http_build_query([
            'username' => $username,
            'password' => $password,
            'to' => $to,
            'from' => $from,
            'text' => $text,
            'isflash' => 'false',
        ]);

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return ['ok' => false, 'rec_id' => null, 'error' => 'امکان اتصال به سرویس پیامک وجود ندارد.', 'raw' => ''];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'rec_id' => null, 'error' => $curlError !== '' ? $curlError : 'خطا در ارسال پیامک', 'raw' => ''];
        }

        $raw = trim((string) $response);
        if (preg_match('/^-?\d+$/', $raw) === 1) {
            $code = (int) $raw;
            if ($code > 0) {
                return ['ok' => true, 'rec_id' => (string) $code, 'error' => null, 'raw' => $raw];
            }

            return ['ok' => false, 'rec_id' => null, 'error' => 'خطای سرویس پیامک (کد ' . $code . ')', 'raw' => $raw];
        }

        if ($raw !== '' && !str_contains(strtolower($raw), 'error')) {
            return ['ok' => true, 'rec_id' => $raw, 'error' => null, 'raw' => $raw];
        }

        return ['ok' => false, 'rec_id' => null, 'error' => $raw !== '' ? $raw : 'پاسخ نامعتبر از سرویس پیامک', 'raw' => $raw];
    }
}
