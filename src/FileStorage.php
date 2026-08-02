<?php

declare(strict_types=1);

final class FileStorage
{
    public const MAX_BYTES = 10_485_760; // 10 MB
    public const MAX_IMAGE_BYTES = 2_097_152; // 2 MB

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

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

    /** Canonical upload categories used across the app. */
    public const CATEGORIES = [
        'members',
        'teams',
        'member-requests',
        'contracts',
        'performance',
    ];

    public static function ensureRoot(): void
    {
        $root = self::rootDir();
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('ساخت پوشه آپلود ممکن نشد.');
        }
        self::writeDenyHtaccess($root);
        self::ensureCategories();
    }

    /** Create known category folders and harden each with .htaccess. */
    public static function ensureCategories(): void
    {
        $root = self::rootDir();
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('ساخت پوشه آپلود ممکن نشد.');
        }
        self::writeDenyHtaccess($root);
        foreach (self::CATEGORIES as $category) {
            $dir = $root . '/' . $category;
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException('ساخت پوشه «' . $category . '» ممکن نشد.');
            }
            self::writeDenyHtaccess($dir);
        }
    }

    private static function writeDenyHtaccess(string $dir): void
    {
        $htaccess = rtrim($dir, '/\\') . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
            @chmod($htaccess, 0640);
        }
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{stored_name:string, original_name:string, mime:string, size_bytes:int, relative_path:string}
     */
    public static function storeUpload(array $file, string $category): array
    {
        return self::storeValidatedUpload($file, $category, self::ALLOWED_EXTENSIONS, self::MAX_BYTES, 'فرمت فایل مجاز نیست. فرمت‌های مجاز: PDF، تصویر، Word، Excel.');
    }

    /**
     * Image-only upload for member/team profile photos.
     *
     * @param array<string, mixed> $file $_FILES entry
     * @return array{stored_name:string, original_name:string, mime:string, size_bytes:int, relative_path:string}
     */
    public static function storeImageUpload(array $file, string $category): array
    {
        $stored = self::storeValidatedUpload(
            $file,
            $category,
            self::IMAGE_EXTENSIONS,
            self::MAX_IMAGE_BYTES,
            'فقط تصویر JPG، PNG یا WebP مجاز است.'
        );
        if (!str_starts_with($stored['mime'], 'image/')) {
            self::deleteRelative($stored['relative_path']);
            throw new InvalidArgumentException('فایل انتخاب‌شده تصویر معتبر نیست.');
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @param list<string> $allowedExtensions
     * @return array{stored_name:string, original_name:string, mime:string, size_bytes:int, relative_path:string}
     */
    private static function storeValidatedUpload(
        array $file,
        string $category,
        array $allowedExtensions,
        int $maxBytes,
        string $extensionError
    ): array {
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
        if ($size > $maxBytes) {
            $limitMb = (int) round($maxBytes / 1_048_576);
            throw new InvalidArgumentException("حجم فایل نباید بیشتر از {$limitMb} مگابایت باشد.");
        }

        $original = self::sanitizeOriginalName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException($extensionError);
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

    /** @var list<string> */
    private static array $queuedDeletes = [];

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

    /** Queue a delete until after a successful DB commit. */
    public static function queueDelete(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || in_array($relativePath, self::$queuedDeletes, true)) {
            return;
        }
        self::$queuedDeletes[] = $relativePath;
    }

    public static function flushQueuedDeletes(): void
    {
        $paths = self::$queuedDeletes;
        self::$queuedDeletes = [];
        foreach ($paths as $relativePath) {
            self::deleteRelative($relativePath);
        }
    }

    public static function clearQueuedDeletes(): void
    {
        self::$queuedDeletes = [];
    }

    public static function sendDownload(string $relativePath, string $originalName, string $mime): never
    {
        self::sendFile($relativePath, $originalName, $mime, false);
    }

    public static function sendInline(string $relativePath, string $originalName, string $mime): never
    {
        self::sendFile($relativePath, $originalName, $mime, true);
    }

    private static function sendFile(string $relativePath, string $originalName, string $mime, bool $inline): never
    {
        $path = self::absolutePath($relativePath);
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'فایل پیدا نشد.';
            exit;
        }

        $safeName = self::sanitizeOriginalName($originalName !== '' ? $originalName : basename($path));
        $disposition = $inline ? 'inline' : 'attachment';
        header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($safeName) . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
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

        // Office Open XML files are often reported as zip — only accept that for docx/xlsx.
        if (in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
            if (!in_array($extension, ['docx', 'xlsx'], true)) {
                throw new InvalidArgumentException('نوع فایل شناسایی‌شده مجاز نیست.');
            }

            return $fallback;
        }

        $allowedMimes = array_unique(array_values(self::MIME_BY_EXTENSION));
        if (!in_array($mime, $allowedMimes, true) && !str_starts_with($mime, 'image/')) {
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
