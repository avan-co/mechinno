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
        'desks-map',
        'desk-assignments',
        'lockers',
        'locker-requests',
        'member-requests',
        'charges',
        'charges-matrix',
        'charge-fiscal-years',
        'team-profile',
        'transactions',
        'payment-history',
        'team_contracts',
        'contract-documents',
        'performance-reports',
        'performance-settings',
        'team-payable-months',
        'center-settings',
        'room-reservations',
        'room-availability',
        'room-settings',
        'room-calendar',
        'meeting-rooms',
        'room-month',
        'room-closed-days',
        'crud-meta',
        'health',
    ];

    /** @var list<string> */
    private const ADMIN_RESOURCES = [
        'summary',
        'teams',
        'members',
        'desks',
        'desks-map',
        'desk-assignments',
        'lockers',
        'locker-requests',
        'member-requests',
        'charges',
        'transactions',
        'rate_settings',
        'charges-matrix',
        'charge-fiscal-years',
        'team-profile',
        'panel_users',
        'development_plans',
        'pending-members',
        'pending-member-requests',
        'pending-payments',
        'pending-locker-requests',
        'payment-history',
        'team_contracts',
        'contract-documents',
        'pending-contract-proposals',
        'performance-reports',
        'pending-performance-reports',
        'performance-settings',
        'team-payable-months',
        'center-settings',
        'crud-meta',
        'recalculate-charges',
        'bulk-year-import',
        'ledger',
        'reports',
        'report-catalog',
        'sms-settings',
        'sms-stats',
        'sms-recipients',
        'sms-history',
        'sms-sync-history',
        'sms-check-deliveries',
        'sms-test',
        'sms-send',
        'sms-charge-debtors',
        'sms-send-charge-reminders',
        'sms-patterns',
        'health',
        'meeting-rooms',
        'meeting_rooms',
        'room-reservations',
        'pending-room-reservations',
        'room-availability',
        'room-settings',
        'room-calendar',
        'room-month',
        'room-closed-days',
    ];

    public static function role(): string
    {
        Auth::start();
        if (isset($_SESSION['mechinno_role'])) {
            return (string) $_SESSION['mechinno_role'];
        }
        if (Auth::check()) {
            return self::ROLE_ADMIN_VIEWER;
        }

        // Never imply write access when the session is anonymous.
        return '';
    }

    public static function roleLabel(?string $role = null): string
    {
        return match ($role ?? self::role()) {
            self::ROLE_ADMIN_EDITOR => 'مدیر ویرایشگر',
            self::ROLE_ADMIN_VIEWER => 'مدیر مشاهده‌گر',
            self::ROLE_TEAM => 'پورتال نهاد',
            default => 'کاربر',
        };
    }

    /**
     * @return list<string>
     */
    public static function allowedResources(): array
    {
        return self::isTeam() ? self::TEAM_RESOURCES : self::ADMIN_RESOURCES;
    }

    /**
     * @return list<string>
     */
    public static function allowedCrudResources(): array
    {
        if (self::isTeam()) {
            return ['members', 'transactions', 'locker_requests', 'member_requests'];
        }

        $resources = ['teams', 'members', 'desks', 'desk_assignments', 'lockers', 'locker_requests', 'member_requests', 'charges', 'transactions', 'rate_settings', 'development_plans', 'team_contracts', 'meeting_rooms'];
        if (self::isAdmin()) {
            $resources[] = 'panel_users';
        }

        return $resources;
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

    public static function canTeamSubmit(): bool
    {
        return self::isTeam();
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

    public static function requireWriteOrTeamSubmitJson(): void
    {
        if (!self::canWrite() && !self::canTeamSubmit()) {
            json_response(['error' => 'دسترسی ویرایش مجاز نیست.'], 403);
        }
    }

    public static function assertResourceAllowed(string $resource): void
    {
        $allowed = self::allowedResources();
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
     * @return array{role:string,canWrite:bool,canTeamSubmit:bool,panel:string,teamId:?int,username:string}
     */
    public static function clientContext(): array
    {
        return [
            'role' => self::role(),
            'canWrite' => self::canWrite(),
            'canTeamSubmit' => self::canTeamSubmit(),
            'panel' => self::isTeam() ? 'team' : 'admin',
            'teamId' => self::scopedTeamId(),
            'username' => self::username(),
        ];
    }
}
