<?php

declare(strict_types=1);

final class FileStorage
{
    public const MAX_BYTES = 10_485_760; // 10 MB

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** @var array<string, string> */
    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public static function rootDir(): string
    {
        return app_base_path() . '/data/uploads';
    }

    public static function ensureRoot(): void
    {
        $root = self::rootDir();
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('ساخت پوشه آپلود ممکن نشد.');
        }
        $htaccess = $root . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{stored_name:string, original_name:string, mime:string, size_bytes:int, relative_path:string}
     */
    public static function storeUpload(array $file, string $category): array
    {
        self::ensureRoot();
        $category = preg_replace('/[^a-z0-9_-]+/i', '', $category) ?: 'files';
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(self::uploadErrorMessage($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $isHttpUpload = is_uploaded_file($tmp);
        $isCliFixture = PHP_SAPI === 'cli' && $tmp !== '' && is_file($tmp);
        if ($tmp === '' || (!$isHttpUpload && !$isCliFixture)) {
            throw new InvalidArgumentException('فایل آپلود معتبر نیست.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException('فایل خالی است.');
        }
        if ($size > self::MAX_BYTES) {
            throw new InvalidArgumentException('حجم فایل نباید بیشتر از ۱۰ مگابایت باشد.');
        }

        $original = self::sanitizeOriginalName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('فرمت فایل مجاز نیست. فرمت‌های مجاز: PDF، تصویر، Word، Excel.');
        }

        $detectedMime = self::detectMime($tmp, $extension);
        $dir = self::rootDir() . '/' . $category;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('ساخت پوشه دسته‌بندی آپلود ممکن نشد.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolute = $dir . '/' . $stored;
        $moved = $isHttpUpload ? move_uploaded_file($tmp, $absolute) : rename($tmp, $absolute);
        if (!$moved && $isCliFixture) {
            $moved = copy($tmp, $absolute);
        }
        if (!$moved) {
            throw new RuntimeException('ذخیره فایل روی سرور انجام نشد.');
        }
        @chmod($absolute, 0640);

        return [
            'stored_name' => $stored,
            'original_name' => $original,
            'mime' => $detectedMime,
            'size_bytes' => $size,
            'relative_path' => $category . '/' . $stored,
        ];
    }

    public static function absolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new InvalidArgumentException('مسیر فایل نامعتبر است.');
        }

        return self::rootDir() . '/' . $relativePath;
    }

    public static function deleteRelative(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }
        try {
            $path = self::absolutePath($relativePath);
        } catch (InvalidArgumentException) {
            return;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function sendDownload(string $relativePath, string $originalName, string $mime): never
    {
        $path = self::absolutePath($relativePath);
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'فایل پیدا نشد.';
            exit;
        }

        $safeName = self::sanitizeOriginalName($originalName !== '' ? $originalName : basename($path));
        header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($safeName) . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private static function sanitizeOriginalName(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', $name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        if ($name === '' || $name === '.' || $name === '..') {
            return 'file.bin';
        }

        return mb_substr($name, 0, 180);
    }

    private static function detectMime(string $tmpPath, string $extension): string
    {
        $fallback = self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream';
        if (!function_exists('finfo_open')) {
            return $fallback;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $fallback;
        }
        $mime = (string) finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if ($mime === '' || $mime === 'application/octet-stream') {
            return $fallback;
        }

        $allowedMimes = array_unique(array_values(self::MIME_BY_EXTENSION));
        $allowedMimes[] = 'application/zip'; // some docx/xlsx
        if (!in_array($mime, $allowedMimes, true) && !str_starts_with($mime, 'image/')) {
            // Keep extension-based mime for office formats that finfo may report as zip.
            if (in_array($extension, ['docx', 'xlsx', 'doc', 'xls'], true)) {
                return $fallback;
            }
            throw new InvalidArgumentException('نوع فایل شناسایی‌شده مجاز نیست.');
        }

        return $mime;
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز سرور است.',
            UPLOAD_ERR_PARTIAL => 'آپلود ناقص بود. دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشده است.',
            default => 'خطا در آپلود فایل.',
        };
    }
}
