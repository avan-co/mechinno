<?php

declare(strict_types=1);

final class PerformanceReports
{
    public const PERIOD_H1 = 'h1';
    public const PERIOD_H2 = 'h2';

    /** @var list<string> */
    public const PERIODS = [self::PERIOD_H1, self::PERIOD_H2];

    /** @var array<string, string> */
    public const PERIOD_LABELS = [
        self::PERIOD_H1 => 'نیمه اول (فروردین تا شهریور)',
        self::PERIOD_H2 => 'نیمه دوم (مهر تا اسفند)',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $defaults = [
            'performance_reports_enabled' => false,
            'performance_h1_open_from' => '',
            'performance_h1_open_until' => '',
            'performance_h2_open_from' => '',
            'performance_h2_open_until' => '',
            'performance_report_guide' => 'گزارش عملکرد را طبق فرمت اعلام‌شده مرکز به‌صورت فایل (ترجیحاً PDF) بارگذاری کنید.',
        ];
        $this->ensureSettingsColumns();
        try {
            $row = $this->pdo->query(
                'SELECT performance_reports_enabled, performance_h1_open_from, performance_h1_open_until,
                        performance_h2_open_from, performance_h2_open_until, performance_report_guide
                 FROM center_settings WHERE id = 1'
            )->fetch() ?: [];
        } catch (PDOException) {
            return $defaults;
        }

        return [
            'performance_reports_enabled' => (int) ($row['performance_reports_enabled'] ?? 0) === 1,
            'performance_h1_open_from' => (string) ($row['performance_h1_open_from'] ?? ''),
            'performance_h1_open_until' => (string) ($row['performance_h1_open_until'] ?? ''),
            'performance_h2_open_from' => (string) ($row['performance_h2_open_from'] ?? ''),
            'performance_h2_open_until' => (string) ($row['performance_h2_open_until'] ?? ''),
            'performance_report_guide' => (string) ($row['performance_report_guide'] ?? $defaults['performance_report_guide']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSettings(array $payload): array
    {
        $this->ensureSettingsColumns();
        $current = $this->settings();
        $enabled = array_key_exists('performance_reports_enabled', $payload)
            ? ((int) $payload['performance_reports_enabled'] === 1)
            : $current['performance_reports_enabled'];
        $h1From = $this->optionalDate($payload['performance_h1_open_from'] ?? $current['performance_h1_open_from']);
        $h1Until = $this->optionalDate($payload['performance_h1_open_until'] ?? $current['performance_h1_open_until']);
        $h2From = $this->optionalDate($payload['performance_h2_open_from'] ?? $current['performance_h2_open_from']);
        $h2Until = $this->optionalDate($payload['performance_h2_open_until'] ?? $current['performance_h2_open_until']);
        $guide = trim((string) ($payload['performance_report_guide'] ?? $current['performance_report_guide']));

        if ($h1From !== '' && $h1Until !== '' && $h1Until < $h1From) {
            throw new InvalidArgumentException('پایان بازه نیمه اول نباید قبل از شروع باشد.');
        }
        if ($h2From !== '' && $h2Until !== '' && $h2Until < $h2From) {
            throw new InvalidArgumentException('پایان بازه نیمه دوم نباید قبل از شروع باشد.');
        }
        if ($enabled && ($h1From === '' || $h1Until === '' || $h2From === '' || $h2Until === '')) {
            throw new InvalidArgumentException('برای فعال‌سازی، بازه ارسال هر دو نیمه را کامل وارد کنید.');
        }

        $this->pdo->prepare(
            'UPDATE center_settings SET
                performance_reports_enabled = :enabled,
                performance_h1_open_from = :h1_from,
                performance_h1_open_until = :h1_until,
                performance_h2_open_from = :h2_from,
                performance_h2_open_until = :h2_until,
                performance_report_guide = :guide,
                updated_at = :updated
             WHERE id = 1'
        )->execute([
            'enabled' => $enabled ? 1 : 0,
            'h1_from' => $h1From !== '' ? $h1From : null,
            'h1_until' => $h1Until !== '' ? $h1Until : null,
            'h2_from' => $h2From !== '' ? $h2From : null,
            'h2_until' => $h2Until !== '' ? $h2Until : null,
            'guide' => $guide !== '' ? $guide : null,
            'updated' => date('c'),
        ]);

        return $this->settings();
    }

    public function assertFeatureEnabled(): void
    {
        if (!$this->settings()['performance_reports_enabled'] && !Access::isAdmin()) {
            throw new InvalidArgumentException('بخش گزارش عملکرد فعلاً غیرفعال است.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function teamOverview(int $teamId, ?string $fiscalYear = null): array
    {
        Access::assertTeamAccess($teamId);
        $settings = $this->settings();
        if (!$settings['performance_reports_enabled'] && Access::isTeam()) {
            return [
                'enabled' => false,
                'settings' => $settings,
                'fiscal_year' => $fiscalYear ?: $this->currentFiscalYear(),
                'periods' => [],
            ];
        }

        $defaultYear = JalaliDate::normalizeDigits($fiscalYear ?: $this->currentFiscalYear());
        $activeYears = [];
        foreach (self::PERIODS as $period) {
            $activeYears[$this->reportFiscalYearForPeriod($period)] = true;
        }
        $periods = [];
        foreach (self::PERIODS as $period) {
            $periodYear = $fiscalYear
                ? JalaliDate::normalizeDigits($fiscalYear)
                : $this->reportFiscalYearForPeriod($period);
            $row = $this->reportRow($teamId, $periodYear, $period);
            $window = $this->windowForPeriod($period, $settings);
            $windowConfigured = ($window['open_from'] ?? '') !== '' && ($window['open_until'] ?? '') !== '';
            $status = $row ? (string) ($row['status'] ?? '') : '';
            $canSubmit = $this->canSubmitNow($period, $settings)
                || ($status === 'rejected' && (bool) ($settings['performance_reports_enabled'] ?? false));
            $periods[] = [
                'period' => $period,
                'period_label' => self::PERIOD_LABELS[$period],
                'fiscal_year' => $periodYear,
                'report' => $row ? $this->present($row) : null,
                'window' => $window,
                'window_configured' => $windowConfigured,
                'can_submit' => $canSubmit,
            ];
        }

        // Past-year reports (from older contracts) for read-only history.
        $history = [];
        if ($fiscalYear === null && Schema::tableExists($this->pdo, 'team_performance_reports')) {
            $statement = $this->pdo->prepare(
                'SELECT * FROM team_performance_reports
                 WHERE team_id = :team_id
                 ORDER BY fiscal_year DESC, period ASC, id DESC'
            );
            $statement->execute(['team_id' => $teamId]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $year = (string) ($row['fiscal_year'] ?? '');
                $period = (string) ($row['period'] ?? '');
                $isActiveSlot = isset($activeYears[$year])
                    && (($period === self::PERIOD_H1 && $this->reportFiscalYearForPeriod(self::PERIOD_H1) === $year)
                        || ($period === self::PERIOD_H2 && $this->reportFiscalYearForPeriod(self::PERIOD_H2) === $year));
                if ($isActiveSlot) {
                    continue;
                }
                $history[] = $this->present($row);
            }
        }

        return [
            'enabled' => (bool) $settings['performance_reports_enabled'],
            'settings' => $settings,
            'fiscal_year' => $defaultYear,
            'periods' => $periods,
            'history' => $history,
            'period_labels' => self::PERIOD_LABELS,
        ];
    }

    /**
     * @param array<string, mixed> $upload
     * @return array<string, mixed>
     */
    public function submit(int $teamId, string $fiscalYear, string $period, array $upload, string $notes = ''): array
    {
        if (!Access::canWrite() && !Access::canTeamSubmit()) {
            throw new InvalidArgumentException('ارسال گزارش مجاز نیست.');
        }
        Access::assertTeamAccess($teamId);
        $settings = $this->settings();
        if (!$settings['performance_reports_enabled'] && Access::isTeam()) {
            throw new InvalidArgumentException('بخش گزارش عملکرد فعلاً غیرفعال است.');
        }

        $period = $this->normalizePeriod($period);
        if (Access::isTeam()) {
            // Teams cannot choose an arbitrary year; bind to the period's target fiscal year.
            $fiscalYear = $this->reportFiscalYearForPeriod($period);
        } else {
            $fiscalYear = $this->normalizeYear($fiscalYear);
        }

        $this->assertTeamExists($teamId);
        $existing = $this->reportRow($teamId, $fiscalYear, $period);
        $isRejectedResubmit = false;
        if ($existing && Access::isTeam()) {
            $currentStatus = (string) ($existing['status'] ?? '');
            if ($currentStatus === 'pending') {
                throw new InvalidArgumentException('گزارش این دوره در انتظار تأیید مرکز است و ارسال مجدد مجاز نیست.');
            }
            if ($currentStatus === 'approved') {
                throw new InvalidArgumentException('گزارش تأییدشده قابل جایگزینی توسط نهاد نیست. در صورت رد توسط مرکز می‌توانید اصلاحیه بفرستید.');
            }
            if ($currentStatus !== 'rejected') {
                throw new InvalidArgumentException('وضعیت گزارش برای ارسال مجدد مناسب نیست.');
            }
            $isRejectedResubmit = true;
        }
        // First submit must be inside the open window; rejected corrections may resubmit outside it.
        if (Access::isTeam() && !$isRejectedResubmit && !$this->canSubmitNow($period, $settings)) {
            $window = $this->windowForPeriod($period, $settings);
            $from = $window['open_from'] !== '' ? $window['open_from'] : '—';
            $until = $window['open_until'] !== '' ? $window['open_until'] : '—';
            throw new InvalidArgumentException("ارسال این گزارش فعلاً ممکن نیست. بازه مجاز: از {$from} تا {$until}.");
        }

        $stored = FileStorage::storeUpload($upload, 'performance');
        $now = date('c');
        $status = Access::canWrite() && !Access::isTeam() ? 'approved' : 'pending';
        $oldPath = $existing ? (string) ($existing['stored_path'] ?? '') : '';
        $id = 0;

        try {
            if ($existing) {
                $this->pdo->prepare(
                    'UPDATE team_performance_reports SET
                        original_name = :original_name,
                        stored_path = :stored_path,
                        mime = :mime,
                        size_bytes = :size_bytes,
                        notes = :notes,
                        status = :status,
                        rejection_reason = NULL,
                        uploaded_by_role = :uploaded_by_role,
                        uploaded_by_user_id = :uploaded_by_user_id,
                        submitted_at = :submitted_at,
                        reviewed_at = :reviewed_at,
                        updated_at = :updated_at
                     WHERE id = :id'
                )->execute([
                    'original_name' => $stored['original_name'],
                    'stored_path' => $stored['relative_path'],
                    'mime' => $stored['mime'],
                    'size_bytes' => $stored['size_bytes'],
                    'notes' => $notes !== '' ? $notes : null,
                    'status' => $status,
                    'uploaded_by_role' => Access::role(),
                    'uploaded_by_user_id' => Access::userId() > 0 ? Access::userId() : null,
                    'submitted_at' => $now,
                    'reviewed_at' => $status === 'approved' ? $now : null,
                    'updated_at' => $now,
                    'id' => (int) $existing['id'],
                ]);
                $id = (int) $existing['id'];
            } else {
                $this->pdo->prepare(
                    'INSERT INTO team_performance_reports
                        (team_id, fiscal_year, period, original_name, stored_path, mime, size_bytes, notes, status,
                         uploaded_by_role, uploaded_by_user_id, submitted_at, reviewed_at, created_at, updated_at)
                     VALUES
                        (:team_id, :fiscal_year, :period, :original_name, :stored_path, :mime, :size_bytes, :notes, :status,
                         :uploaded_by_role, :uploaded_by_user_id, :submitted_at, :reviewed_at, :created_at, :updated_at)'
                )->execute([
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'period' => $period,
                    'original_name' => $stored['original_name'],
                    'stored_path' => $stored['relative_path'],
                    'mime' => $stored['mime'],
                    'size_bytes' => $stored['size_bytes'],
                    'notes' => $notes !== '' ? $notes : null,
                    'status' => $status,
                    'uploaded_by_role' => Access::role(),
                    'uploaded_by_user_id' => Access::userId() > 0 ? Access::userId() : null,
                    'submitted_at' => $now,
                    'reviewed_at' => $status === 'approved' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            }
        } catch (Throwable $error) {
            FileStorage::deleteRelative((string) ($stored['relative_path'] ?? ''));
            throw $error;
        }

        if ($oldPath !== '' && $oldPath !== $stored['relative_path']) {
            FileStorage::deleteRelative($oldPath);
        }

        return $this->present($this->reportById($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(int $id): array
    {
        Access::requireWriteJson();
        $row = $this->reportById($id);
        if (!$row) {
            throw new InvalidArgumentException('گزارش پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط گزارش در انتظار قابل تأیید است.');
        }
        $now = date('c');
        $statement = $this->pdo->prepare(
            "UPDATE team_performance_reports
             SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute(['reviewed_at' => $now, 'updated_at' => $now, 'id' => $id]);
        if ($statement->rowCount() < 1) {
            throw new InvalidArgumentException('فقط گزارش در انتظار قابل تأیید است.');
        }

        return $this->present($this->reportById($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(int $id, string $reason): array
    {
        Access::requireWriteJson();
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('دلیل رد الزامی است.');
        }
        $row = $this->reportById($id);
        if (!$row) {
            throw new InvalidArgumentException('گزارش پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط گزارش در انتظار قابل رد است.');
        }
        $now = date('c');
        $statement = $this->pdo->prepare(
            "UPDATE team_performance_reports
             SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute([
            'reason' => $reason,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
        if ($statement->rowCount() < 1) {
            throw new InvalidArgumentException('فقط گزارش در انتظار قابل رد است.');
        }

        return $this->present($this->reportById($id));
    }

    public function download(int $id): never
    {
        $row = $this->reportById($id);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'فایل پیدا نشد.';
            exit;
        }
        Access::assertTeamAccess((int) $row['team_id']);
        if (Access::isTeam() && !$this->settings()['performance_reports_enabled']) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'بخش گزارش عملکرد غیرفعال است.';
            exit;
        }
        FileStorage::sendDownload(
            (string) $row['stored_path'],
            (string) $row['original_name'],
            (string) $row['mime']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingList(): array
    {
        if (!Schema::tableExists($this->pdo, 'team_performance_reports')) {
            return [];
        }
        $statement = $this->pdo->query(
            "SELECT r.*, t.name AS team_name, t.entity_code
             FROM team_performance_reports r
             INNER JOIN teams t ON t.id = r.team_id
             WHERE r.status = 'pending'
             ORDER BY r.submitted_at DESC, r.id DESC"
        );

        return array_map(fn (array $row): array => $this->present($row), $statement->fetchAll() ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminList(?string $fiscalYear = null, string $status = ''): array
    {
        if (!Schema::tableExists($this->pdo, 'team_performance_reports')) {
            return [];
        }
        $sql = 'SELECT r.*, t.name AS team_name, t.entity_code
                FROM team_performance_reports r
                INNER JOIN teams t ON t.id = r.team_id
                WHERE 1=1';
        $params = [];
        if ($fiscalYear) {
            $sql .= ' AND r.fiscal_year = :year';
            $params['year'] = JalaliDate::normalizeDigits($fiscalYear);
        }
        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.fiscal_year DESC, r.period ASC, t.name ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row): array => $this->present($row), $statement->fetchAll() ?: []);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{open_from:string, open_until:string, is_open:bool}
     */
    private function windowForPeriod(string $period, array $settings): array
    {
        $from = $period === self::PERIOD_H1
            ? (string) ($settings['performance_h1_open_from'] ?? '')
            : (string) ($settings['performance_h2_open_from'] ?? '');
        $until = $period === self::PERIOD_H1
            ? (string) ($settings['performance_h1_open_until'] ?? '')
            : (string) ($settings['performance_h2_open_until'] ?? '');

        return [
            'open_from' => $from,
            'open_until' => $until,
            'is_open' => $this->canSubmitNow($period, $settings),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function canSubmitNow(string $period, array $settings): bool
    {
        if (!(bool) ($settings['performance_reports_enabled'] ?? false)) {
            return false;
        }
        $from = $period === self::PERIOD_H1
            ? (string) ($settings['performance_h1_open_from'] ?? '')
            : (string) ($settings['performance_h2_open_from'] ?? '');
        $until = $period === self::PERIOD_H1
            ? (string) ($settings['performance_h1_open_until'] ?? '')
            : (string) ($settings['performance_h2_open_until'] ?? '');

        // If no window configured, keep closed to prevent early submissions.
        if ($from === '' || $until === '') {
            return false;
        }

        $today = (string) JalaliDate::todayParts()['formatted'];
        try {
            $fromN = JalaliDate::normalize($from);
            $untilN = JalaliDate::normalize($until);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $today >= $fromN && $today <= $untilN;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportRow(int $teamId, string $fiscalYear, string $period): ?array
    {
        if (!Schema::tableExists($this->pdo, 'team_performance_reports')) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM team_performance_reports
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND period = :period
             LIMIT 1'
        );
        $statement->execute([
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'period' => $period,
        ]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, t.name AS team_name, t.entity_code
             FROM team_performance_reports r
             LEFT JOIN teams t ON t.id = r.team_id
             WHERE r.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $period = (string) ($row['period'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'team_id' => (int) ($row['team_id'] ?? 0),
            'team_name' => (string) ($row['team_name'] ?? ''),
            'entity_code' => (string) ($row['entity_code'] ?? ''),
            'fiscal_year' => (string) ($row['fiscal_year'] ?? ''),
            'period' => $period,
            'period_label' => self::PERIOD_LABELS[$period] ?? $period,
            'original_name' => (string) ($row['original_name'] ?? ''),
            'mime' => (string) ($row['mime'] ?? ''),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'notes' => (string) ($row['notes'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'rejection_reason' => (string) ($row['rejection_reason'] ?? ''),
            'submitted_at' => (string) ($row['submitted_at'] ?? ''),
            'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
            'download_url' => 'download.php?resource=performance-report&id=' . (int) ($row['id'] ?? 0),
        ];
    }

    private function normalizeYear(string $year): string
    {
        $year = JalaliDate::normalizeDigits(trim($year));
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException('سال مالی نامعتبر است.');
        }

        return $year;
    }

    private function normalizePeriod(string $period): string
    {
        $period = strtolower(trim($period));
        if (!in_array($period, self::PERIODS, true)) {
            throw new InvalidArgumentException('نیمه گزارش نامعتبر است.');
        }

        return $period;
    }

    private function optionalDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        return JalaliDate::normalize($text);
    }

    private function assertTeamExists(int $teamId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM teams WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $teamId]);
        if (!$statement->fetch()) {
            throw new InvalidArgumentException('نهاد پیدا نشد.');
        }
    }

    private function currentFiscalYear(): string
    {
        return (string) JalaliDate::todayParts()['year'];
    }

    /**
     * H2 of year Y is typically submitted in early Y+1 (Farvardin–Khordad).
     * H1 of year Y is submitted later in the same year.
     */
    private function reportFiscalYearForPeriod(string $period): string
    {
        $parts = JalaliDate::todayParts();
        $year = (int) $parts['year'];
        $month = (int) $parts['month'];
        if ($period === self::PERIOD_H2 && $month <= 6) {
            return (string) ($year - 1);
        }

        return (string) $year;
    }

    private function ensureSettingsColumns(): void
    {
        if (!Schema::tableExists($this->pdo, 'center_settings')) {
            return;
        }
        Schema::migrate($this->pdo);
    }
}
