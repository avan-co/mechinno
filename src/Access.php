<?php

declare(strict_types=1);

final class Access
{
    public const ROLE_ADMIN_EDITOR = 'admin_editor';
    public const ROLE_ADMIN_VIEWER = 'admin_viewer';
    public const ROLE_TEAM = 'team';

    /** @var list<string> */
    private const TEAM_RESOURCES = [
        'summary',
        'members',
        'desks',
        'lockers',
        'charges',
        'charges-matrix',
        'team-profile',
        'crud-meta',
    ];

    /** @var list<string> */
    private const ADMIN_RESOURCES = [
        'summary',
        'teams',
        'members',
        'desks',
        'lockers',
        'charges',
        'transactions',
        'rate_settings',
        'charges-matrix',
        'team-profile',
        'panel_users',
        'crud-meta',
        'recalculate-charges',
    ];

    public static function role(): string
    {
        Auth::start();
        return (string) ($_SESSION['mechinno_role'] ?? self::ROLE_ADMIN_EDITOR);
    }

    public static function userId(): int
    {
        Auth::start();
        return (int) ($_SESSION['mechinno_user_id'] ?? 0);
    }

    public static function username(): string
    {
        Auth::start();
        return (string) ($_SESSION['mechinno_user'] ?? '');
    }

    public static function scopedTeamId(): ?int
    {
        if (!self::isTeam()) {
            return null;
        }
        $teamId = (int) ($_SESSION['mechinno_team_id'] ?? 0);

        return $teamId > 0 ? $teamId : null;
    }

    public static function canWrite(): bool
    {
        return self::role() === self::ROLE_ADMIN_EDITOR;
    }

    public static function isTeam(): bool
    {
        return self::role() === self::ROLE_TEAM;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), [self::ROLE_ADMIN_EDITOR, self::ROLE_ADMIN_VIEWER], true);
    }

    public static function homePath(): string
    {
        return self::isTeam() ? 'team.php' : 'index.php';
    }

    public static function requireAdminHtml(): void
    {
        if (!Auth::check()) {
            require_auth();
        }
        if (self::isTeam()) {
            redirect_to('team.php');
        }
    }

    public static function requireTeamHtml(): void
    {
        if (!Auth::check()) {
            require_auth();
        }
        if (!self::isTeam()) {
            redirect_to('index.php');
        }
        if (self::scopedTeamId() === null) {
            throw new RuntimeException('حساب نهاد به هیچ تیمی متصل نیست. با مدیر تماس بگیرید.');
        }
    }

    public static function requireWriteJson(): void
    {
        if (!self::canWrite()) {
            json_response(['error' => 'شما فقط دسترسی مشاهده دارید.'], 403);
        }
    }

    public static function assertResourceAllowed(string $resource): void
    {
        $allowed = self::isTeam() ? self::TEAM_RESOURCES : self::ADMIN_RESOURCES;
        if (!in_array($resource, $allowed, true)) {
            json_response(['error' => 'دسترسی به این بخش مجاز نیست.'], 403);
        }
    }

    public static function assertTeamAccess(int $teamId): void
    {
        $scope = self::scopedTeamId();
        if ($scope !== null && $scope !== $teamId) {
            json_response(['error' => 'دسترسی به این نهاد مجاز نیست.'], 403);
        }
    }

    public static function sanitizeNext(string $next): string
    {
        if ($next === '' || str_starts_with($next, 'http://') || str_starts_with($next, 'https://') || str_starts_with($next, '//')) {
            return self::homePath();
        }

        if (self::isTeam() && !str_starts_with($next, 'team.php')) {
            return 'team.php';
        }

        if (self::isAdmin() && str_starts_with($next, 'team.php')) {
            return 'index.php';
        }

        if (self::isTeam() && in_array($next, ['install.php', 'index.php'], true)) {
            return 'team.php';
        }

        if (!self::canWrite() && $next === 'install.php') {
            return self::homePath();
        }

        return $next;
    }

    /**
     * @return array{role:string,canWrite:bool,panel:string,teamId:?int,username:string}
     */
    public static function clientContext(): array
    {
        return [
            'role' => self::role(),
            'canWrite' => self::canWrite(),
            'panel' => self::isTeam() ? 'team' : 'admin',
            'teamId' => self::scopedTeamId(),
            'username' => self::username(),
        ];
    }
}
