<?php

declare(strict_types=1);

/**
 * REST client aligned with the official MeliPayamak PHP SDK.
 *
 * @see https://github.com/Melipayamak/melipayamak-php
 * @see SmsRest::send(), SmsRest::sendByBaseNumber()
 */
final class MelliPayamak
{
    private const BASE = 'https://rest.payamak-panel.com/api/SendSMS/';

    /**
     * Parse MeliPayamak pattern message: body_id@text_data##param1##param2##key
     *
     * @return array{is_shared_pattern:bool, body_id:?string, text_data:string, vars_string:string, key:?string}
     */
    public static function parsePatternMessage(string $message): array
    {
        $message = trim($message);
        $parts = explode('@', $message);
        $textData = array_pop($parts);
        $bodyId = $parts !== [] ? array_pop($parts) : null;
        $params = explode('##', (string) $textData);
        $key = array_pop($params);
        $varsString = $params !== [] ? implode(';', $params) : '';

        return [
            'is_shared_pattern' => $bodyId !== null && $bodyId !== '' && trim((string) $key) === 'shared',
            'body_id' => $bodyId !== null && $bodyId !== '' ? $bodyId : null,
            'text_data' => (string) $textData,
            'vars_string' => $varsString,
            'key' => $key !== false ? (string) $key : null,
        ];
    }

    /**
     * @return array{ok:bool, rec_id:?string, error:?string, raw:mixed}
     */
    public function send(string $username, string $password, string $from, string $to, string $text): array
    {
        $to = TeamLeaders::normalizePhone($to);
        $text = trim($text);
        if ($to === '' || !preg_match('/^09\d{9}$/', $to)) {
            return ['ok' => false, 'rec_id' => null, 'error' => 'شماره موبایل گیرنده معتبر نیست.', 'raw' => ''];
        }
        if ($text === '') {
            return ['ok' => false, 'rec_id' => null, 'error' => 'متن پیامک خالی است.', 'raw' => ''];
        }

        $pattern = self::parsePatternMessage($text);
        if ($pattern['is_shared_pattern']) {
            return $this->sendBaseServiceNumber(
                $username,
                $password,
                $to,
                (int) $pattern['body_id'],
                $pattern['vars_string']
            );
        }

        $sendText = $pattern['body_id'] !== null ? $pattern['text_data'] : $text;

        return $this->sendSimpleSms($username, $password, $from, $to, $sendText);
    }

    /**
     * ارسال الگوی خط خدماتی اشتراکی — معادل SmsRest::sendByBaseNumber($text, $to, $bodyId).
     * متغیرها در $text با ; جدا می‌شوند.
     *
     * @return array{ok:bool, rec_id:?string, error:?string, raw:mixed}
     */
    public function sendByBaseNumber(string $username, string $password, string $to, int $bodyId, string $text): array
    {
        return $this->sendBaseServiceNumber(
            $username,
            $password,
            TeamLeaders::normalizePhone($to),
            $bodyId,
            trim($text)
        );
    }

    /**
     * @return array{ok:bool, rec_id:?string, error:?string, raw:mixed}
     */
    private function sendSimpleSms(string $username, string $password, string $from, string $to, string $text): array
    {
        $response = $this->request('SendSMS', [
            'username' => trim($username),
            'password' => trim($password),
            'to' => $to,
            'from' => trim($from),
            'text' => $text,
            'isflash' => false,
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'rec_id' => null, 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $parsed = self::parseSendResponse($response['data']);

        return $parsed['ok']
            ? $parsed
            : ['ok' => true, 'rec_id' => null, 'error' => null, 'raw' => $response['raw']];
    }

    /**
     * @return array{ok:bool, rec_id:?string, error:?string, raw:mixed}
     */
    private function sendBaseServiceNumber(string $username, string $password, string $to, int $bodyId, string $text): array
    {
        if ($bodyId <= 0) {
            return ['ok' => false, 'rec_id' => null, 'error' => 'شناسه الگوی پیامک نامعتبر است.', 'raw' => ''];
        }

        $response = $this->request('BaseServiceNumber', [
            'username' => trim($username),
            'password' => trim($password),
            'text' => $text,
            'to' => $to,
            'bodyId' => $bodyId,
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'rec_id' => null, 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $parsed = self::parseSendResponse($response['data']);

        return $parsed['ok']
            ? $parsed
            : ['ok' => true, 'rec_id' => null, 'error' => null, 'raw' => $response['raw']];
    }

    /**
     * @return array{ok:bool, numbers:list<string>, error:?string, raw:mixed}
     */
    public function getUserNumbers(string $username, string $password): array
    {
        $response = $this->request('GetUserNumbers', $this->authFields($username, $password));
        if (!$response['ok']) {
            return ['ok' => false, 'numbers' => [], 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $numbers = self::extractStringList($response['data']);
        if ($numbers === []) {
            $value = self::responseValue($response['data']);
            if (is_string($value) && trim($value) !== '' && !str_contains(strtolower($value), 'error')) {
                $numbers = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $value) ?: [])));
            }
        }

        return [
            'ok' => true,
            'numbers' => $numbers,
            'error' => $numbers === [] ? 'خط ارسالی یافت نشد.' : null,
            'raw' => $response['raw'],
        ];
    }

    /**
     * @return array{ok:bool, messages:list<array<string,mixed>>, error:?string, raw:mixed}
     */
    public function getMessages(string $username, string $password, int $location, int $index, int $count, string $from = ''): array
    {
        $response = $this->request('GetMessages', array_merge($this->authFields($username, $password), [
            'location' => (string) $location,
            'index' => (string) max(0, $index),
            'count' => (string) max(1, min(500, $count)),
            'from' => $from,
        ]));
        if (!$response['ok']) {
            return ['ok' => false, 'messages' => [], 'error' => $response['error'], 'raw' => $response['raw']];
        }

        return [
            'ok' => true,
            'messages' => self::extractMessageList($response['data']),
            'error' => null,
            'raw' => $response['raw'],
        ];
    }

    /**
     * @return array{ok:bool, status:?string, status_code:?int, error:?string, raw:mixed}
     */
    public function getDelivery(string $username, string $password, string $recId): array
    {
        $recId = trim($recId);
        if ($recId === '') {
            return ['ok' => false, 'status' => null, 'status_code' => null, 'error' => 'شناسه پیامک نامعتبر است.', 'raw' => ''];
        }

        $response = $this->request('GetDeliveries2', array_merge($this->authFields($username, $password), [
            'recId' => $recId,
        ]));
        if (!$response['ok']) {
            return ['ok' => false, 'status' => null, 'status_code' => null, 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $code = self::extractDeliveryCode($response['data']);
        if ($code !== null && $code < 0) {
            return [
                'ok' => false,
                'status' => null,
                'status_code' => $code,
                'error' => self::deliveryLabel($code),
                'raw' => $response['raw'],
            ];
        }

        return [
            'ok' => $code !== null,
            'status' => self::deliveryLabel($code),
            'status_code' => $code,
            'error' => $code === null ? 'وضعیت دلیوری نامشخص است.' : null,
            'raw' => $response['raw'],
        ];
    }

    /**
     * @return array{ok:bool, credit:?int, error:?string, raw:mixed}
     */
    public function getCredit(string $username, string $password): array
    {
        $response = $this->request('GetCredit', $this->authFields($username, $password));
        if (!$response['ok']) {
            return ['ok' => false, 'credit' => null, 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $credit = self::extractNumeric($response['data']);

        return [
            'ok' => $credit !== null,
            'credit' => $credit,
            'error' => $credit === null ? 'خواندن موجودی ممکن نشد.' : null,
            'raw' => $response['raw'],
        ];
    }

    /**
     * @return array{ok:bool, price:?int, error:?string, raw:mixed}
     */
    public function getBasePrice(string $username, string $password): array
    {
        $response = $this->request('GetBasePrice', $this->authFields($username, $password));
        if (!$response['ok']) {
            return ['ok' => false, 'price' => null, 'error' => $response['error'], 'raw' => $response['raw']];
        }

        $price = self::extractNumeric($response['data']);

        return [
            'ok' => $price !== null,
            'price' => $price,
            'error' => $price === null ? 'خواندن تعرفه ممکن نشد.' : null,
            'raw' => $response['raw'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authFields(string $username, string $password): array
    {
        return [
            'username' => trim($username),
            'password' => trim($password),
        ];
    }

    /**
     * @param array<string, scalar|null> $fields
     * @return array{ok:bool, data:mixed, error:?string, raw:string}
     */
    private function request(string $operation, array $fields): array
    {
        $username = trim((string) ($fields['username'] ?? $fields['UserName'] ?? ''));
        $password = trim((string) ($fields['password'] ?? $fields['PassWord'] ?? ''));
        if ($username === '' || $password === '') {
            return ['ok' => false, 'data' => null, 'error' => 'تنظیمات پیامک کامل نیست.', 'raw' => ''];
        }

        $payload = http_build_query($fields);
        $ch = curl_init(self::BASE . $operation);
        if ($ch === false) {
            return ['ok' => false, 'data' => null, 'error' => 'امکان اتصال به سرویس پیامک وجود ندارد.', 'raw' => ''];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'data' => null, 'error' => $curlError !== '' ? $curlError : 'خطا در ارتباط با ملی‌پیامک', 'raw' => ''];
        }

        $raw = trim((string) $response);
        if ($raw === '') {
            return ['ok' => false, 'data' => null, 'error' => 'پاسخ خالی از سرویس پیامک', 'raw' => ''];
        }

        $decoded = json_decode($raw, true);
        $data = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        if (is_array($data)) {
            if (isset($data['RetStatus']) && !self::isRestSuccess($data['RetStatus'])) {
                $message = trim((string) ($data['Value'] ?? $data['StrRetStatus'] ?? 'خطای سرویس پیامک'));
                if (isset($data['RetStatus'])) {
                    $message .= ' (کد وضعیت: ' . $data['RetStatus'] . ')';
                }

                return ['ok' => false, 'data' => $data, 'error' => $message !== '' ? $message : 'خطای سرویس پیامک', 'raw' => $raw];
            }
            if (isset($data['StrRetStatus']) && is_string($data['StrRetStatus'])) {
                $status = strtolower($data['StrRetStatus']);
                if (str_contains($status, 'error') || str_contains($status, 'err')) {
                    $message = trim((string) ($data['Value'] ?? $data['StrRetStatus']));

                    return ['ok' => false, 'data' => $data, 'error' => $message !== '' ? $message : 'خطای سرویس پیامک', 'raw' => $raw];
                }
            }
        }

        return ['ok' => true, 'data' => $data, 'error' => null, 'raw' => $raw];
    }

    /**
     * @param array<string, mixed> $result
     */
    private static function isRestSuccess(mixed $status): bool
    {
        return $status == 1 || (is_string($status) && strtolower($status) === 'success');
    }

    /**
     * @return array{ok:bool, rec_id:?string, error:?string, raw:mixed}
     */
    private static function parseSendResponse(mixed $data): array
    {
        if (is_numeric($data)) {
            $code = (int) $data;
            if ($code > 0) {
                return ['ok' => true, 'rec_id' => (string) $code, 'error' => null, 'raw' => $data];
            }

            return ['ok' => false, 'rec_id' => null, 'error' => 'خطای سرویس پیامک (کد ' . $code . ')', 'raw' => $data];
        }

        if (is_string($data)) {
            $trimmed = trim($data);
            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return self::parseSendResponse((int) $trimmed);
            }
            if ($trimmed !== '' && !str_contains(strtolower($trimmed), 'error')) {
                return ['ok' => true, 'rec_id' => $trimmed, 'error' => null, 'raw' => $data];
            }

            return ['ok' => false, 'rec_id' => null, 'error' => $trimmed, 'raw' => $data];
        }

        if (is_array($data)) {
            $value = self::responseValue($data);
            if ($value !== null) {
                return self::parseSendResponse($value);
            }
        }

        return ['ok' => false, 'rec_id' => null, 'error' => 'پاسخ نامعتبر از سرویس پیامک', 'raw' => $data];
    }

    private static function responseValue(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        foreach (['Value', 'value', 'RetStatus', 'retStatus'] as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function extractStringList(mixed $data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? self::extractStringList($decoded) : [];
        }
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['Value'])) {
            return self::extractStringList($data['Value']);
        }

        $numbers = [];
        foreach ($data as $item) {
            if (is_string($item) && trim($item) !== '') {
                $numbers[] = trim($item);
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            foreach (['Number', 'number', 'LineNumber', 'lineNumber', 'From', 'from'] as $key) {
                if (!empty($item[$key])) {
                    $numbers[] = trim((string) $item[$key]);
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($numbers)));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function extractMessageList(mixed $data): array
    {
        if (isset($data['Value']) && is_array($data)) {
            $data = $data['Value'];
        }
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? self::extractMessageList($decoded) : [];
        }
        if (!is_array($data)) {
            return [];
        }

        $messages = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $messages[] = [
                'id' => (string) ($item['ID'] ?? $item['Id'] ?? $item['id'] ?? $item['MsgID'] ?? ''),
                'body' => (string) ($item['Body'] ?? $item['MessageBody'] ?? $item['Text'] ?? $item['text'] ?? ''),
                'to' => (string) ($item['Receiver'] ?? $item['To'] ?? $item['Mobile'] ?? $item['to'] ?? ''),
                'from' => (string) ($item['Sender'] ?? $item['From'] ?? $item['from'] ?? ''),
                'date' => (string) ($item['Date'] ?? $item['SendDate'] ?? $item['date'] ?? ''),
                'status' => (string) ($item['Status'] ?? $item['status'] ?? ''),
            ];
        }

        return $messages;
    }

    private static function extractDeliveryCode(mixed $data): ?int
    {
        if (is_int($data) || is_float($data)) {
            return (int) $data;
        }
        if (is_string($data) && preg_match('/^-?\d+$/', trim($data)) === 1) {
            return (int) trim($data);
        }
        if (is_array($data)) {
            $value = self::responseValue($data);
            if ($value !== null) {
                return self::extractDeliveryCode($value);
            }
            if (isset($data[0])) {
                return self::extractDeliveryCode($data[0]);
            }
        }

        return null;
    }

    private static function extractNumeric(mixed $data): ?int
    {
        if (is_int($data) || is_float($data)) {
            return (int) round((float) $data);
        }
        if (is_string($data) && preg_match('/^-?\d+(\.\d+)?$/', trim($data)) === 1) {
            return (int) round((float) trim($data));
        }
        if (is_array($data)) {
            $value = self::responseValue($data);
            if ($value !== null) {
                return self::extractNumeric($value);
            }
        }

        return null;
    }

    public static function formatApiError(mixed $raw): string
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        if (!is_array($raw)) {
            return '';
        }

        $value = trim((string) ($raw['Value'] ?? ''));
        $status = trim((string) ($raw['StrRetStatus'] ?? ''));
        $retStatus = $raw['RetStatus'] ?? null;

        if ($status === 'InvalidData' || $value === '-4' || $retStatus === 35) {
            return 'داده نامعتبر: کد الگو (bodyId) در سامانه با پنل ملی‌پیامک مطابقت ندارد یا تعداد/ترتیب متغیرها اشتباه است.';
        }
        if ($value === '-5' || $status === 'InvalidBodyId') {
            return 'کد الگو (bodyId) در پنل ملی‌پیامک یافت نشد یا هنوز تأیید نشده است.';
        }
        if ($value !== '' && preg_match('/^-\d+$/', $value) === 1) {
            return 'خطای ملی‌پیامک (کد ' . $value . ')';
        }

        return '';
    }

    public static function deliveryLabel(?int $code): string
    {
        return match ($code) {
            0 => 'ارسال شده به مخابرات',
            1 => 'رسیده به گوشی',
            2 => 'نرسیده به گوشی',
            3 => 'خطای مخابراتی',
            4 => 'رسیده به گوشی',
            5 => 'خطای نامشخص',
            6 => 'ناموفق',
            7 => 'در حال ارسال',
            8 => 'رسیده به مخابرات',
            16 => 'نرسیده به مخابرات',
            35 => 'لیست سیاه',
            100 => 'نامشخص',
            200 => 'ارسال شده',
            300 => 'فیلتر شده',
            400 => 'در صف ارسال',
            500 => 'عدم پذیرش',
            -1 => 'خطای API',
            -2 => 'شناسه پیامک نامعتبر یا هنوز ثبت نشده',
            default => $code === null ? 'نامشخص' : 'کد ' . $code,
        };
    }
}
