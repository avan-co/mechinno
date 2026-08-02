<?php

declare(strict_types=1);

final class UploadFileManager
{
    /** @var array<string, string> */
    public const FOLDER_LABELS = [
        'members' => 'تصاویر اعضا',
        'teams' => 'تصاویر نهادها',
        'member-requests' => 'تصاویر درخواست عضو',
        'contracts' => 'قراردادها',
        'performance' => 'گزارش عملکرد',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        FileStorage::ensureRoot();
    }

    /**
     * @return list<array{name:string, label:string, file_count:int, total_bytes:int}>
     */
    public function listFolders(): array
    {
        $this->assertAdmin();
        $root = FileStorage::rootDir();
        $folders = [];
        foreach (self::FOLDER_LABELS as $name => $label) {
            $dir = $root . '/' . $name;
            $count = 0;
            $bytes = 0;
            if (is_dir($dir)) {
                foreach ($this->scanFiles($dir) as $file) {
                    $count++;
                    $bytes += (int) ($file['size_bytes'] ?? 0);
                }
            }
            $folders[] = [
                'name' => $name,
                'label' => $label,
                'file_count' => $count,
                'total_bytes' => $bytes,
            ];
        }

        // Include any unexpected upload subfolders so nothing is hidden.
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($name === '' || $name[0] === '.' || isset(self::FOLDER_LABELS[$name])) {
                continue;
            }
            $count = 0;
            $bytes = 0;
            foreach ($this->scanFiles($dir) as $file) {
                $count++;
                $bytes += (int) ($file['size_bytes'] ?? 0);
            }
            $folders[] = [
                'name' => $name,
                'label' => $name,
                'file_count' => $count,
                'total_bytes' => $bytes,
            ];
        }

        usort($folders, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $folders;
    }

    /**
     * @return array{folder:string, label:string, files:list<array<string, mixed>>}
     */
    public function listFiles(string $folder): array
    {
        $this->assertAdmin();
        $folder = $this->sanitizeFolder($folder);
        $dir = FileStorage::rootDir() . '/' . $folder;
        $files = [];
        if (is_dir($dir)) {
            foreach ($this->scanFiles($dir) as $meta) {
                $relative = $folder . '/' . $meta['name'];
                $links = $this->findReferences($relative);
                $files[] = [
                    'name' => $meta['name'],
                    'relative_path' => $relative,
                    'size_bytes' => $meta['size_bytes'],
                    'modified_at' => $meta['modified_at'],
                    'mime' => $meta['mime'],
                    'is_image' => str_starts_with($meta['mime'], 'image/'),
                    'download_url' => 'download.php?resource=upload-file&path=' . rawurlencode($relative),
                    'preview_url' => str_starts_with($meta['mime'], 'image/')
                        ? 'download.php?resource=upload-file&path=' . rawurlencode($relative) . '&inline=1'
                        : '',
                    'references' => $links,
                    'reference_count' => count($links),
                ];
            }
        }
        usort($files, static fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));

        return [
            'folder' => $folder,
            'label' => self::FOLDER_LABELS[$folder] ?? $folder,
            'files' => $files,
        ];
    }

    /**
     * @return array{deleted:bool, path:string, cleared_references:int}
     */
    public function deleteFile(string $relativePath): array
    {
        $this->assertAdmin();
        if (!Access::canWrite()) {
            throw new InvalidArgumentException('حذف فایل فقط برای مدیر ویرایشگر مجاز است.');
        }
        $relativePath = $this->sanitizeRelativePath($relativePath);
        $absolute = FileStorage::absolutePath($relativePath);
        if (!is_file($absolute)) {
            throw new InvalidArgumentException('فایل پیدا نشد.');
        }
        $cleared = $this->clearReferences($relativePath);
        FileStorage::deleteRelative($relativePath);

        return [
            'deleted' => true,
            'path' => $relativePath,
            'cleared_references' => $cleared,
        ];
    }

    /**
     * Clear DB paths that point to missing files (broken links).
     *
     * @return array{cleared:int, items:list<array{table:string, id:int, path:string}>}
     */
    public function clearBrokenReferences(): array
    {
        $this->assertAdmin();
        if (!Access::canWrite()) {
            throw new InvalidArgumentException('پاکسازی فقط برای مدیر ویرایشگر مجاز است.');
        }

        $clearedItems = [];
        foreach ($this->referenceQueries() as $spec) {
            $statement = $this->pdo->query($spec['select']);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $path = trim((string) ($row['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                try {
                    $absolute = FileStorage::absolutePath($path);
                } catch (InvalidArgumentException) {
                    $absolute = '';
                }
                if ($absolute !== '' && is_file($absolute)) {
                    continue;
                }
                $update = $this->pdo->prepare($spec['clear']);
                $update->execute(['id' => (int) $row['id']]);
                $clearedItems[] = [
                    'table' => $spec['table'],
                    'id' => (int) $row['id'],
                    'path' => $path,
                ];
            }
        }

        return [
            'cleared' => count($clearedItems),
            'items' => $clearedItems,
        ];
    }

    public function download(string $relativePath, bool $inline = false): never
    {
        $this->assertAdmin();
        $relativePath = $this->sanitizeRelativePath($relativePath);
        $absolute = FileStorage::absolutePath($relativePath);
        if (!is_file($absolute)) {
            throw new InvalidArgumentException('فایل پیدا نشد.');
        }
        $mime = $this->detectMimeFromPath($absolute);
        $name = basename($relativePath);
        if ($inline) {
            FileStorage::sendInline($relativePath, $name, $mime);
        }
        FileStorage::sendDownload($relativePath, $name, $mime);
    }

    private function assertAdmin(): void
    {
        if (!Access::isAdmin()) {
            throw new InvalidArgumentException('مدیریت فایل فقط برای مدیر مجاز است.');
        }
    }

    private function sanitizeFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        if ($folder === '' || str_contains($folder, '/') || str_contains($folder, '..') || !preg_match('/^[a-z0-9_-]+$/i', $folder)) {
            throw new InvalidArgumentException('پوشه نامعتبر است.');
        }

        return $folder;
    }

    private function sanitizeRelativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new InvalidArgumentException('مسیر فایل نامعتبر است.');
        }
        $parts = explode('/', $relativePath);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('فقط فایل‌های داخل پوشه‌های آپلود قابل مدیریت هستند.');
        }
        $this->sanitizeFolder($parts[0]);
        if ($parts[1] === '' || $parts[1] === '.' || str_contains($parts[1], '/') || str_starts_with($parts[1], '.')) {
            throw new InvalidArgumentException('نام فایل نامعتبر است.');
        }

        return $parts[0] . '/' . $parts[1];
    }

    /**
     * @return list<array{name:string, size_bytes:int, modified_at:string, mime:string}>
     */
    private function scanFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size_bytes' => (int) filesize($path),
                'modified_at' => date('c', (int) filemtime($path)),
                'mime' => $this->detectMimeFromPath($path),
            ];
        }

        return $files;
    }

    private function detectMimeFromPath(string $absolute): string
    {
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $fallback = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
        if (!function_exists('finfo_open')) {
            return $fallback;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $fallback;
        }
        $mime = (string) finfo_file($finfo, $absolute);
        finfo_close($finfo);

        return $mime !== '' ? $mime : $fallback;
    }

    /**
     * @return list<array{table:string, id:int, label:string}>
     */
    private function findReferences(string $relativePath): array
    {
        $links = [];
        foreach ($this->referenceQueries() as $spec) {
            $statement = $this->pdo->prepare($spec['find']);
            $statement->execute(['path' => $relativePath]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $links[] = [
                    'table' => $spec['table'],
                    'id' => (int) $row['id'],
                    'label' => (string) ($row['label'] ?? ($spec['table'] . ' #' . $row['id'])),
                ];
            }
        }

        return $links;
    }

    private function clearReferences(string $relativePath): int
    {
        $count = 0;
        foreach ($this->referenceQueries() as $spec) {
            $statement = $this->pdo->prepare($spec['clear_by_path']);
            $statement->execute(['path' => $relativePath]);
            $count += $statement->rowCount();
        }

        return $count;
    }

    /**
     * @return list<array{table:string, select:string, find:string, clear:string, clear_by_path:string}>
     */
    private function referenceQueries(): array
    {
        $queries = [];
        if (Schema::tableExists($this->pdo, 'members') && Schema::hasColumn($this->pdo, 'members', 'avatar_path')) {
            $queries[] = [
                'table' => 'members',
                'select' => 'SELECT id, avatar_path AS path FROM members WHERE avatar_path IS NOT NULL AND avatar_path <> \'\'',
                'find' => 'SELECT id, full_name AS label FROM members WHERE avatar_path = :path',
                'clear' => 'UPDATE members SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE members SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE avatar_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'teams') && Schema::hasColumn($this->pdo, 'teams', 'logo_path')) {
            $queries[] = [
                'table' => 'teams',
                'select' => 'SELECT id, logo_path AS path FROM teams WHERE logo_path IS NOT NULL AND logo_path <> \'\'',
                'find' => 'SELECT id, name AS label FROM teams WHERE logo_path = :path',
                'clear' => 'UPDATE teams SET logo_path = NULL, logo_original_name = NULL, logo_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE teams SET logo_path = NULL, logo_original_name = NULL, logo_mime = NULL WHERE logo_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'member_requests') && Schema::hasColumn($this->pdo, 'member_requests', 'avatar_path')) {
            $queries[] = [
                'table' => 'member_requests',
                'select' => 'SELECT id, avatar_path AS path FROM member_requests WHERE avatar_path IS NOT NULL AND avatar_path <> \'\'',
                'find' => 'SELECT id, COALESCE(full_name, \'درخواست عضو\') AS label FROM member_requests WHERE avatar_path = :path',
                'clear' => 'UPDATE member_requests SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE member_requests SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE avatar_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $queries[] = [
                'table' => 'team_contract_files',
                'select' => 'SELECT id, stored_path AS path FROM team_contract_files WHERE stored_path IS NOT NULL AND stored_path <> \'\'',
                'find' => 'SELECT id, COALESCE(original_name, doc_type, \'قرارداد\') AS label FROM team_contract_files WHERE stored_path = :path',
                'clear' => 'DELETE FROM team_contract_files WHERE id = :id',
                'clear_by_path' => 'DELETE FROM team_contract_files WHERE stored_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'team_performance_reports')) {
            $queries[] = [
                'table' => 'team_performance_reports',
                'select' => 'SELECT id, stored_path AS path FROM team_performance_reports WHERE stored_path IS NOT NULL AND stored_path <> \'\'',
                'find' => 'SELECT id, COALESCE(original_name, period, \'گزارش عملکرد\') AS label FROM team_performance_reports WHERE stored_path = :path',
                'clear' => 'DELETE FROM team_performance_reports WHERE id = :id',
                'clear_by_path' => 'DELETE FROM team_performance_reports WHERE stored_path = :path',
            ];
        }

        return $queries;
    }
}
