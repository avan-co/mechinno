<?php

declare(strict_types=1);

final class ProfileImages
{
    public const MEMBER_CATEGORY = 'members';
    public const TEAM_CATEGORY = 'teams';
    public const REQUEST_CATEGORY = 'member-requests';

    public const DEFAULT_MEMBER_AVATAR = 'assets/brand/default-member.svg';
    public const DEFAULT_TEAM_LOGO = 'assets/brand/default-team.svg';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function defaultMemberAvatarUrl(): string
    {
        return self::DEFAULT_MEMBER_AVATAR;
    }

    public static function defaultTeamLogoUrl(): string
    {
        return self::DEFAULT_TEAM_LOGO;
    }

    public static function relativeFileExists(string $relativePath): bool
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return false;
        }
        try {
            return is_file(FileStorage::absolutePath($relativePath));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{avatar_path:string, avatar_original_name:string, avatar_mime:string}
     */
    public function storeMemberAvatar(array $file): array
    {
        $stored = FileStorage::storeImageUpload($file, self::MEMBER_CATEGORY);

        return [
            'avatar_path' => $stored['relative_path'],
            'avatar_original_name' => $stored['original_name'],
            'avatar_mime' => $stored['mime'],
        ];
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{logo_path:string, logo_original_name:string, logo_mime:string}
     */
    public function storeTeamLogo(array $file): array
    {
        $stored = FileStorage::storeImageUpload($file, self::TEAM_CATEGORY);

        return [
            'logo_path' => $stored['relative_path'],
            'logo_original_name' => $stored['original_name'],
            'logo_mime' => $stored['mime'],
        ];
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{avatar_path:string, avatar_original_name:string, avatar_mime:string}
     */
    public function storeRequestAvatar(array $file): array
    {
        $stored = FileStorage::storeImageUpload($file, self::REQUEST_CATEGORY);

        return [
            'avatar_path' => $stored['relative_path'],
            'avatar_original_name' => $stored['original_name'],
            'avatar_mime' => $stored['mime'],
        ];
    }

    /**
     * @param array{avatar_path:string, avatar_original_name:string, avatar_mime:string} $stored
     */
    public function setMemberAvatarFields(int $memberId, array $stored, bool $replaceOld = true): void
    {
        $oldPath = '';
        if ($replaceOld) {
            $member = $this->memberRow($memberId);
            $oldPath = (string) ($member['avatar_path'] ?? '');
        }
        $this->pdo->prepare(
            'UPDATE members
             SET avatar_path = :avatar_path,
                 avatar_original_name = :avatar_original_name,
                 avatar_mime = :avatar_mime
             WHERE id = :id'
        )->execute([
            'avatar_path' => $stored['avatar_path'],
            'avatar_original_name' => $stored['avatar_original_name'],
            'avatar_mime' => $stored['avatar_mime'],
            'id' => $memberId,
        ]);
        if ($replaceOld && $oldPath !== '' && $oldPath !== $stored['avatar_path']) {
            $this->forgetStoredFile($oldPath);
        }
    }

    /**
     * Copy a request avatar into members/ and retarget the member row.
     * Clears request avatar columns. Old files are queued for post-commit delete.
     *
     * @param array<string, mixed> $requestRow
     */
    public function transferRequestAvatarToMember(int $memberId, int $requestId, array $requestRow): void
    {
        $requestPath = trim((string) ($requestRow['avatar_path'] ?? ''));
        if ($requestPath === '') {
            return;
        }
        if (!self::relativeFileExists($requestPath)) {
            $this->pdo->prepare(
                'UPDATE member_requests
                 SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL
                 WHERE id = :id'
            )->execute(['id' => $requestId]);

            return;
        }

        $source = FileStorage::absolutePath($requestPath);
        $extension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }
        FileStorage::ensureRoot();
        $dir = FileStorage::rootDir() . '/' . self::MEMBER_CATEGORY;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('ساخت پوشه تصاویر اعضا ممکن نشد.');
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolute = $dir . '/' . $storedName;
        if (!copy($source, $absolute)) {
            throw new RuntimeException('انتقال تصویر پروفایل عضو انجام نشد.');
        }
        @chmod($absolute, 0640);

        $member = $this->memberRow($memberId);
        $oldPath = trim((string) ($member['avatar_path'] ?? ''));
        $newPath = self::MEMBER_CATEGORY . '/' . $storedName;
        // If the surrounding DB transaction rolls back, remove this newly copied file.
        FileStorage::queueCreatedForRollback($newPath);
        $this->setMemberAvatarFields($memberId, [
            'avatar_path' => $newPath,
            'avatar_original_name' => (string) ($requestRow['avatar_original_name'] ?? ('avatar.' . $extension)),
            'avatar_mime' => (string) ($requestRow['avatar_mime'] ?? 'image/jpeg'),
        ], false);

        $this->pdo->prepare(
            'UPDATE member_requests
             SET avatar_path = NULL, avatar_original_name = NULL, avatar_mime = NULL
             WHERE id = :id'
        )->execute(['id' => $requestId]);

        $this->forgetStoredFile($requestPath);
        if ($oldPath !== '' && $oldPath !== $requestPath && $oldPath !== $newPath) {
            $this->forgetStoredFile($oldPath);
        }
    }

    public function forgetStoredFile(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }
        if ($this->pdo->inTransaction()) {
            FileStorage::queueDelete($relativePath);
        } else {
            FileStorage::deleteRelative($relativePath);
        }
    }

    public function deleteMemberRequestAvatarFiles(int $requestId): void
    {
        $statement = $this->pdo->prepare('SELECT avatar_path FROM member_requests WHERE id = :id');
        $statement->execute(['id' => $requestId]);
        $path = (string) ($statement->fetchColumn() ?: '');
        if ($path !== '') {
            $this->forgetStoredFile($path);
        }
    }

    /**
     * @param array{avatar_path:string, avatar_original_name:string, avatar_mime:string} $stored
     */
    public function setMemberRequestAvatarFields(int $requestId, array $stored): void
    {
        $this->pdo->prepare(
            'UPDATE member_requests
             SET avatar_path = :avatar_path,
                 avatar_original_name = :avatar_original_name,
                 avatar_mime = :avatar_mime
             WHERE id = :id'
        )->execute([
            'avatar_path' => $stored['avatar_path'],
            'avatar_original_name' => $stored['avatar_original_name'],
            'avatar_mime' => $stored['avatar_mime'],
            'id' => $requestId,
        ]);
    }

    /**
     * @param array{logo_path:string, logo_original_name:string, logo_mime:string} $stored
     */
    public function setTeamLogoFields(int $teamId, array $stored, bool $replaceOld = true): void
    {
        $oldPath = '';
        if ($replaceOld) {
            $team = $this->teamRow($teamId);
            $oldPath = (string) ($team['logo_path'] ?? '');
        }
        $this->pdo->prepare(
            'UPDATE teams
             SET logo_path = :logo_path,
                 logo_original_name = :logo_original_name,
                 logo_mime = :logo_mime
             WHERE id = :id'
        )->execute([
            'logo_path' => $stored['logo_path'],
            'logo_original_name' => $stored['logo_original_name'],
            'logo_mime' => $stored['logo_mime'],
            'id' => $teamId,
        ]);
        if ($replaceOld && $oldPath !== '' && $oldPath !== $stored['logo_path']) {
            $this->forgetStoredFile($oldPath);
        }
    }

    public function attachMemberAvatar(int $memberId, array $file): array
    {
        $member = $this->memberRow($memberId);
        Access::assertTeamAccess((int) ($member['team_id'] ?? 0));
        $stored = $this->storeMemberAvatar($file);
        $this->setMemberAvatarFields($memberId, $stored, true);

        return (new Crud($this->pdo))->find('members', $memberId);
    }

    public function attachTeamLogo(int $teamId, array $file): array
    {
        Access::assertTeamAccess($teamId);
        $stored = $this->storeTeamLogo($file);
        $this->setTeamLogoFields($teamId, $stored, true);

        return (new Crud($this->pdo))->find('teams', $teamId);
    }

    public static function hasUploadedFile(mixed $file): bool
    {
        return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function downloadMemberAvatar(int $memberId): never
    {
        $member = $this->memberRow($memberId);
        Access::assertTeamAccess((int) ($member['team_id'] ?? 0));
        $path = (string) ($member['avatar_path'] ?? '');
        if (!self::relativeFileExists($path)) {
            self::sendDefaultAsset(self::DEFAULT_MEMBER_AVATAR, 'default-member.svg');
        }
        FileStorage::sendInline(
            $path,
            (string) ($member['avatar_original_name'] ?? 'avatar.jpg'),
            (string) ($member['avatar_mime'] ?? 'image/jpeg')
        );
    }

    public function downloadTeamLogo(int $teamId): never
    {
        Access::assertTeamAccess($teamId);
        $team = $this->teamRow($teamId);
        $path = (string) ($team['logo_path'] ?? '');
        if (!self::relativeFileExists($path)) {
            self::sendDefaultAsset(self::DEFAULT_TEAM_LOGO, 'default-team.svg');
        }
        FileStorage::sendInline(
            $path,
            (string) ($team['logo_original_name'] ?? 'logo.jpg'),
            (string) ($team['logo_mime'] ?? 'image/jpeg')
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enrichMemberRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $hasAvatar = self::relativeFileExists((string) ($row['avatar_path'] ?? ''));
        $row['has_avatar'] = $hasAvatar ? 1 : 0;
        $row['avatar_is_default'] = $hasAvatar ? 0 : 1;
        $row['avatar_url'] = $hasAvatar && $id > 0
            ? 'download.php?resource=member-avatar&id=' . $id
            : self::defaultMemberAvatarUrl();
        unset($row['avatar_path'], $row['avatar_mime'], $row['avatar_original_name']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enrichTeamRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $hasLogo = self::relativeFileExists((string) ($row['logo_path'] ?? ''));
        $row['has_logo'] = $hasLogo ? 1 : 0;
        $row['logo_is_default'] = $hasLogo ? 0 : 1;
        $row['logo_url'] = $hasLogo && $id > 0
            ? 'download.php?resource=team-logo&id=' . $id
            : self::defaultTeamLogoUrl();
        unset($row['logo_path'], $row['logo_mime'], $row['logo_original_name']);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enrichMemberRequestRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $hasAvatar = self::relativeFileExists((string) ($row['avatar_path'] ?? ''));
        $row['has_avatar'] = $hasAvatar ? 1 : 0;
        $row['avatar_is_default'] = $hasAvatar ? 0 : 1;
        $row['avatar_url'] = $hasAvatar && $id > 0
            ? 'download.php?resource=member-request-avatar&id=' . $id
            : self::defaultMemberAvatarUrl();
        unset($row['avatar_path'], $row['avatar_mime'], $row['avatar_original_name']);

        return $row;
    }

    public function downloadMemberRequestAvatar(int $requestId): never
    {
        $statement = $this->pdo->prepare('SELECT * FROM member_requests WHERE id = :id');
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('درخواست عضو پیدا نشد.');
        }
        Access::assertTeamAccess((int) ($row['team_id'] ?? 0));
        $path = (string) ($row['avatar_path'] ?? '');
        if (!self::relativeFileExists($path)) {
            self::sendDefaultAsset(self::DEFAULT_MEMBER_AVATAR, 'default-member.svg');
        }
        FileStorage::sendInline(
            $path,
            (string) ($row['avatar_original_name'] ?? 'avatar.jpg'),
            (string) ($row['avatar_mime'] ?? 'image/jpeg')
        );
    }

    private static function sendDefaultAsset(string $webPath, string $downloadName): never
    {
        $absolute = app_base_path() . '/' . ltrim($webPath, '/');
        if (!is_file($absolute)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'تصویر پیش‌فرض پیدا نشد.';
            exit;
        }
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Length: ' . (string) filesize($absolute));
        header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        readfile($absolute);
        exit;
    }

    /**
     * Collect avatar/logo paths for a team before cascade delete.
     *
     * @return list<string>
     */
    public function orphanPathsForTeam(int $teamId): array
    {
        $paths = [];
        $team = $this->pdo->prepare('SELECT logo_path FROM teams WHERE id = :id');
        $team->execute(['id' => $teamId]);
        $logo = (string) ($team->fetchColumn() ?: '');
        if ($logo !== '') {
            $paths[] = $logo;
        }

        $members = $this->pdo->prepare('SELECT avatar_path FROM members WHERE team_id = :id');
        $members->execute(['id' => $teamId]);
        foreach ($members->fetchAll() ?: [] as $row) {
            $path = (string) ($row['avatar_path'] ?? '');
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        $requests = $this->pdo->prepare('SELECT avatar_path FROM member_requests WHERE team_id = :id');
        $requests->execute(['id' => $teamId]);
        foreach ($requests->fetchAll() ?: [] as $row) {
            $path = (string) ($row['avatar_path'] ?? '');
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    public function deleteMemberAvatarFiles(int $memberId): void
    {
        $statement = $this->pdo->prepare('SELECT avatar_path FROM members WHERE id = :id');
        $statement->execute(['id' => $memberId]);
        $path = (string) ($statement->fetchColumn() ?: '');
        if ($path !== '') {
            $this->forgetStoredFile($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function memberRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM members WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('عضو پیدا نشد.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function teamRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM teams WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('نهاد پیدا نشد.');
        }

        return $row;
    }
}
