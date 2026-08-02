<?php

declare(strict_types=1);

final class UploadFileManager
{
    /**
     * Canonical folders in display order.
     *
     * @var array<string, array{label:string, description:string, kind:string}>
     */
    public const FOLDERS = [
        'members' => [
            'label' => 'تصاویر اعضا',
            'description' => 'عکس پروفایل اعضای تأییدشده',
            'kind' => 'image',
        ],
        'teams' => [
            'label' => 'تصاویر نهادها',
            'description' => 'لوگوی تیم‌ها، شرکت‌ها و دانشجویان',
            'kind' => 'image',
        ],
        'member-requests' => [
            'label' => 'تصاویر درخواست عضو',
            'description' => 'عکس‌های پیوست‌شده به درخواست ویرایش/حذف',
            'kind' => 'image',
        ],
        'contracts' => [
            'label' => 'قراردادها',
            'description' => 'فایل‌های قرارداد عضویت و تسویه',
            'kind' => 'document',
        ],
        'performance' => [
            'label' => 'گزارش عملکرد',
            'description' => 'فایل‌های گزارش شش‌ماهه نهادها',
            'kind' => 'document',
        ],
    ];

    /** @deprecated Use FOLDERS[*][label] */
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
     * @return list<array{
     *   name:string,
     *   label:string,
     *   description:string,
     *   kind:string,
     *   file_count:int,
     *   orphan_count:int,
     *   total_bytes:int,
     *   known:bool
     * }>
     */
    public function listFolders(): array
    {
        $this->assertAdmin();
        FileStorage::ensureCategories();

        $root = FileStorage::rootDir();
        $referenced = $this->allReferencedPaths();
        $folders = [];

        foreach (self::FOLDERS as $name => $meta) {
            $folders[] = $this->folderStats($root . '/' . $name, $name, $meta, $referenced, true);
        }

        // Unexpected upload subfolders stay visible at the end.
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($name === '' || $name[0] === '.' || isset(self::FOLDERS[$name])) {
                continue;
            }
            $folders[] = $this->folderStats($dir, $name, [
                'label' => $name,
                'description' => 'پوشه غیررسمی — بررسی یا انتقال توصیه می‌شود',
                'kind' => 'other',
            ], $referenced, false);
        }

        return $folders;
    }

    /**
     * @return array{
     *   folder:string,
     *   label:string,
     *   description:string,
     *   kind:string,
     *   known:bool,
     *   file_count:int,
     *   orphan_count:int,
     *   files:list<array<string, mixed>>
     * }
     */
    public function listFiles(string $folder): array
    {
        $this->assertAdmin();
        FileStorage::ensureCategories();
        $folder = $this->sanitizeFolder($folder);
        $meta = self::FOLDERS[$folder] ?? [
            'label' => $folder,
            'description' => '',
            'kind' => 'other',
        ];
        $dir = FileStorage::rootDir() . '/' . $folder;
        $referenced = $this->allReferencedPaths();
        $files = [];
        $orphanCount = 0;
        if (is_dir($dir)) {
            foreach ($this->scanFiles($dir) as $fileMeta) {
                $relative = $folder . '/' . $fileMeta['name'];
                $links = $this->findReferences($relative);
                $isOrphan = $links === [] && !isset($referenced[$relative]);
                if ($isOrphan) {
                    $orphanCount++;
                }
                $originalName = '';
                foreach ($links as $link) {
                    if (($link['original_name'] ?? '') !== '') {
                        $originalName = (string) $link['original_name'];
                        break;
                    }
                }
                $files[] = [
                    'name' => $fileMeta['name'],
                    'original_name' => $originalName,
                    'relative_path' => $relative,
                    'size_bytes' => $fileMeta['size_bytes'],
                    'modified_at' => $fileMeta['modified_at'],
                    'mime' => $fileMeta['mime'],
                    'is_image' => str_starts_with($fileMeta['mime'], 'image/'),
                    'is_orphan' => $isOrphan,
                    'download_url' => 'download.php?resource=upload-file&path=' . rawurlencode($relative),
                    'preview_url' => str_starts_with($fileMeta['mime'], 'image/')
                        ? 'download.php?resource=upload-file&path=' . rawurlencode($relative) . '&inline=1'
                        : '',
                    'references' => $links,
                    'reference_count' => count($links),
                ];
            }
        }
        usort($files, static function (array $a, array $b): int {
            // Orphans first, then newest.
            if ((bool) $a['is_orphan'] !== (bool) $b['is_orphan']) {
                return $a['is_orphan'] ? -1 : 1;
            }

            return strcmp((string) $b['modified_at'], (string) $a['modified_at']);
        });

        return [
            'folder' => $folder,
            'label' => $meta['label'],
            'description' => $meta['description'],
            'kind' => $meta['kind'],
            'known' => isset(self::FOLDERS[$folder]),
            'file_count' => count($files),
            'orphan_count' => $orphanCount,
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

    /**
     * Delete upload files that are not referenced by any database row.
     *
     * @return array{deleted:int, paths:list<string>}
     */
    public function purgeOrphanFiles(?string $folder = null): array
    {
        $this->assertAdmin();
        if (!Access::canWrite()) {
            throw new InvalidArgumentException('حذف فایل‌های یتیم فقط برای مدیر ویرایشگر مجاز است.');
        }

        FileStorage::ensureCategories();
        $referenced = $this->allReferencedPaths();
        $targets = $folder !== null && $folder !== ''
            ? [$this->sanitizeFolder($folder)]
            : array_keys(self::FOLDERS);

        // Also allow purging unexpected folders when scanning all.
        if ($folder === null || $folder === '') {
            $root = FileStorage::rootDir();
            foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if ($name !== '' && $name[0] !== '.' && !in_array($name, $targets, true)) {
                    $targets[] = $name;
                }
            }
        }

        $deleted = [];
        foreach ($targets as $name) {
            $dir = FileStorage::rootDir() . '/' . $name;
            if (!is_dir($dir)) {
                continue;
            }
            foreach ($this->scanFiles($dir) as $fileMeta) {
                $relative = $name . '/' . $fileMeta['name'];
                if (isset($referenced[$relative])) {
                    continue;
                }
                FileStorage::deleteRelative($relative);
                $deleted[] = $relative;
            }
        }

        return [
            'deleted' => count($deleted),
            'paths' => $deleted,
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
        // Prefer original filename from DB when available.
        foreach ($this->findReferences($relativePath) as $link) {
            if (($link['original_name'] ?? '') !== '') {
                $name = (string) $link['original_name'];
                break;
            }
        }
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
     * @param array{label:string, description:string, kind:string} $meta
     * @param array<string, true> $referenced
     * @return array{
     *   name:string,
     *   label:string,
     *   description:string,
     *   kind:string,
     *   file_count:int,
     *   orphan_count:int,
     *   total_bytes:int,
     *   known:bool
     * }
     */
    private function folderStats(string $dir, string $name, array $meta, array $referenced, bool $known): array
    {
        $count = 0;
        $bytes = 0;
        $orphans = 0;
        if (is_dir($dir)) {
            foreach ($this->scanFiles($dir) as $file) {
                $count++;
                $bytes += (int) ($file['size_bytes'] ?? 0);
                $relative = $name . '/' . $file['name'];
                if (!isset($referenced[$relative])) {
                    $orphans++;
                }
            }
        }

        return [
            'name' => $name,
            'label' => $meta['label'],
            'description' => $meta['description'],
            'kind' => $meta['kind'],
            'file_count' => $count,
            'orphan_count' => $orphans,
            'total_bytes' => $bytes,
            'known' => $known,
        ];
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
     * @return array<string, true>
     */
    private function allReferencedPaths(): array
    {
        $paths = [];
        foreach ($this->referenceQueries() as $spec) {
            $statement = $this->pdo->query($spec['select']);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $path = trim((string) ($row['path'] ?? ''));
                if ($path !== '') {
                    $paths[$path] = true;
                }
            }
        }

        return $paths;
    }

    /**
     * @return list<array{table:string, id:int, label:string, original_name:string}>
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
                    'original_name' => (string) ($row['original_name'] ?? ''),
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
                'find' => 'SELECT id, full_name AS label, COALESCE(avatar_original_name, \'\') AS original_name FROM members WHERE avatar_path = :path',
                'clear' => 'UPDATE members SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE members SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE avatar_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'teams') && Schema::hasColumn($this->pdo, 'teams', 'logo_path')) {
            $queries[] = [
                'table' => 'teams',
                'select' => 'SELECT id, logo_path AS path FROM teams WHERE logo_path IS NOT NULL AND logo_path <> \'\'',
                'find' => 'SELECT id, name AS label, COALESCE(logo_original_name, \'\') AS original_name FROM teams WHERE logo_path = :path',
                'clear' => 'UPDATE teams SET logo_path = NULL, logo_original_name = NULL, logo_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE teams SET logo_path = NULL, logo_original_name = NULL, logo_mime = NULL WHERE logo_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'member_requests') && Schema::hasColumn($this->pdo, 'member_requests', 'avatar_path')) {
            $queries[] = [
                'table' => 'member_requests',
                'select' => 'SELECT id, avatar_path AS path FROM member_requests WHERE avatar_path IS NOT NULL AND avatar_path <> \'\'',
                'find' => 'SELECT id, COALESCE(full_name, \'درخواست عضو\') AS label, COALESCE(avatar_original_name, \'\') AS original_name FROM member_requests WHERE avatar_path = :path',
                'clear' => 'UPDATE member_requests SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE id = :id',
                'clear_by_path' => 'UPDATE member_requests SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL WHERE avatar_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $queries[] = [
                'table' => 'team_contract_files',
                'select' => 'SELECT id, stored_path AS path FROM team_contract_files WHERE stored_path IS NOT NULL AND stored_path <> \'\'',
                'find' => 'SELECT id, COALESCE(original_name, doc_type, \'قرارداد\') AS label, COALESCE(original_name, \'\') AS original_name FROM team_contract_files WHERE stored_path = :path',
                'clear' => 'DELETE FROM team_contract_files WHERE id = :id',
                'clear_by_path' => 'DELETE FROM team_contract_files WHERE stored_path = :path',
            ];
        }
        if (Schema::tableExists($this->pdo, 'team_performance_reports')) {
            $queries[] = [
                'table' => 'team_performance_reports',
                'select' => 'SELECT id, stored_path AS path FROM team_performance_reports WHERE stored_path IS NOT NULL AND stored_path <> \'\'',
                'find' => 'SELECT id, COALESCE(original_name, period, \'گزارش عملکرد\') AS label, COALESCE(original_name, \'\') AS original_name FROM team_performance_reports WHERE stored_path = :path',
                'clear' => 'DELETE FROM team_performance_reports WHERE id = :id',
                'clear_by_path' => 'DELETE FROM team_performance_reports WHERE stored_path = :path',
            ];
        }

        return $queries;
    }
}
