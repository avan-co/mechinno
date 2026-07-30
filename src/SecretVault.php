<?php

declare(strict_types=1);

/**
 * Encrypts sensitive values at rest using AES-256-GCM and app_secret from config.
 */
final class SecretVault
{
    private const PREFIX = 'enc:v1:';

    /**
     * @param array<string, mixed> $config
     */
    public static function encrypt(string $plain, array $config): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }

        $key = self::deriveKey($config);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('رمزنگاری مقدار حساس ناموفق بود.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function decrypt(string $stored, array $config): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) < 28) {
            return '';
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipher = substr($payload, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::deriveKey($config), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function isEncrypted(string $stored): bool
    {
        return str_starts_with(trim($stored), self::PREFIX);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function deriveKey(array $config): string
    {
        $secret = trim((string) ($config['app_secret'] ?? ''));
        if ($secret === '' || str_starts_with($secret, 'CHANGE_ME')) {
            $db = $config['db'] ?? [];
            $driver = (string) ($db['driver'] ?? 'mysql');
            $fingerprint = $driver === 'sqlite'
                ? (string) ($db['path'] ?? Database::configPath())
                : ((string) ($db['database'] ?? '')) . '@' . ((string) ($db['host'] ?? 'localhost'));
            $secret = 'mechinno-fallback:' . $fingerprint;
        }

        return hash('sha256', $secret, true);
    }
}
