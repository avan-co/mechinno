<?php

declare(strict_types=1);

final class Repository
{
    /** @var list<string> Legacy DB columns that must never appear in API/UI. */
    private const LEGACY_COLUMNS = ['row_number', 'lockers', 'power_strips', 'rent_rate'];

    /** @var array<int, array<string, int>>|null */
    private ?array $paymentAllocationCache = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $teamId = Access::scopedTeamId();
        if ($teamId !== null) {
            return $this->teamSummary($teamId);
        }

        return [
            'cards' => [
                'members' => $this->scalar("SELECT COUNT(*) FROM members WHERE approval_status = 'approved' OR approval_status IS NULL"),
                'teams' => $this->scalar('SELECT COUNT(*) FROM teams'),
                'desks_occupied' => $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NOT NULL'),
                'desks_total' => 24,
                'lockers' => $this->scalar('SELECT COUNT(*) FROM lockers'),
                'available_lockers' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'"),
                'income_year' => $this->incomeForPeriod($this->currentFiscalYear()),
                'income_month' => $this->incomeForPeriod($this->currentFiscalYear(), $this->currentMonthIndex()),
                'expense_year' => $this->expenseForPeriod($this->currentFiscalYear()),
                'expense_month' => $this->expenseForPeriod($this->currentFiscalYear(), $this->currentMonthIndex()),
                'debt_total' => $this->scalar($this->debtSql()),
                'charge_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM charges'),
                'income_total' => $this->incomeForPeriod($this->currentFiscalYear()),
                'expense_total' => abs($this->expenseForPeriod($this->currentFiscalYear())),
                'paid_total' => $this->scalar(
                    "SELECT COALESCE(SUM(amount), 0) FROM transactions
                     WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1"
                ),
                'ledger_balance' => (new CenterLedger($this->pdo))->balance(),
                'pending_members' => $this->scalar("SELECT COUNT(*) FROM members WHERE approval_status = 'pending'"),
                'pending_payments' => $this->scalar("SELECT COUNT(*) FROM transactions WHERE category = 'واریز تیم' AND payment_status = 'pending'"),
                'pending_locker_requests' => $this->scalar("SELECT COUNT(*) FROM locker_requests WHERE status = 'pending'"),
            ],
            'locker_status' => $this->rows('SELECT status, COUNT(*) AS count FROM lockers GROUP BY status ORDER BY count DESC'),
            'monthly_charges' => $this->rows(
                'SELECT fiscal_year, month_index, month_name, SUM(amount) AS amount
                 FROM charges GROUP BY fiscal_year, month_index, month_name
                 ORDER BY fiscal_year, month_index'
            ),
            'finance_by_category' => $this->rows(
                "SELECT category, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount
                 FROM transactions
                 WHERE confirmed = 1
                   AND (category <> 'واریز تیم' OR payment_status = 'approved')
                 GROUP BY category ORDER BY amount DESC"
            ),
            'debt_by_team' => $this->rows(
                "SELECT t.id AS team_id, t.name AS team_name, COALESCE(SUM(c.amount), 0) - COALESCE(p.paid, 0) AS debt
                 FROM teams t
                 LEFT JOIN charges c ON c.team_id = t.id
                 LEFT JOIN (
                    SELECT team_id, COALESCE(SUM(amount), 0) AS paid
                    FROM transactions
                    WHERE category = 'واریز تیم' AND confirmed = 1 AND payment_status = 'approved'
                    GROUP BY team_id
                 ) p ON p.team_id = t.id
                 GROUP BY t.id, t.name, p.paid
                 HAVING debt > 0
                 ORDER BY debt DESC
                 LIMIT 10"
            ),
            'finance_monthly' => $this->rows(
                "SELECT substr(tx_date, 1, 7) AS period,
                        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS income,
                        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) AS expense
                 FROM transactions
                 WHERE tx_date IS NOT NULL AND tx_date <> '' AND confirmed = 1
                   AND (category <> 'واریز تیم' OR payment_status = 'approved')
                 GROUP BY substr(tx_date, 1, 7)
                 ORDER BY period"
            ),
            'occupancy' => [
                'desks_total' => 24,
                'desks_occupied' => $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NOT NULL'),
                'desks_free' => $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NULL'),
                'lockers_assigned' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'تخصیص یافته'"),
            ],
            'current_month' => $this->currentMonthSummary(),
            'action_items' => $this->actionItems(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentMonthSummary(): array
    {
        $today = JalaliDate::todayParts();
        $year = (string) $today['year'];
        $month = (int) $today['month'];

        $chargeTotal = $this->preparedScalar(
            'SELECT COALESCE(SUM(amount), 0) FROM charges WHERE fiscal_year = :year AND month_index = :month',
            ['year' => $year, 'month' => $month]
        );
        $allocationMap = $this->paymentAllocationByTeamMonth();
        $paidTotal = 0;
        foreach ($allocationMap as $teamAllocations) {
            $paidTotal += (int) ($teamAllocations[$year . '-' . $month] ?? 0);
        }

        return [
            'fiscal_year' => $year,
            'month_index' => $month,
            'month_name' => $today['month_name'],
            'today' => $today['formatted'],
            'charge_total' => $chargeTotal,
            'paid_total' => $paidTotal,
            'debt_total' => max(0, $chargeTotal - $paidTotal),
            'debtor_count' => $this->preparedScalar(
                "SELECT COUNT(*) FROM (
                    SELECT c.team_id,
                           COALESCE(SUM(c.amount), 0) - COALESCE(p.paid, 0) AS debt
                    FROM charges c
                    LEFT JOIN (
                        SELECT team_id, SUM(amount) AS paid
                        FROM transactions
                        WHERE category = 'واریز تیم' AND confirmed = 1 AND payment_status = 'approved'
                          AND fiscal_year = :year AND month_index = :month
                        GROUP BY team_id
                    ) p ON p.team_id = c.team_id
                    WHERE c.fiscal_year = :year2 AND c.month_index = :month2
                    GROUP BY c.team_id, p.paid
                    HAVING debt > 0
                 ) AS debtors",
                ['year' => $year, 'year2' => $year, 'month' => $month, 'month2' => $month]
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actionItems(): array
    {
        $items = [];
        $today = JalaliDate::todayParts();
        $year = (string) $today['year'];
        $month = (int) $today['month'];

        if ($this->scalar('SELECT COUNT(*) FROM teams') === 0) {
            $items[] = [
                'priority' => 5,
                'type' => 'start',
                'label' => 'اولین نهاد را ثبت کنید',
                'detail' => 'تیم، شرکت یا دانشجوی مستقر',
                'section' => 'teams',
            ];
        }

        $pendingPayments = $this->scalar("SELECT COUNT(*) FROM transactions WHERE category = 'واریز تیم' AND payment_status = 'pending'");
        if ($pendingPayments > 0) {
            $items[] = [
                'priority' => 10,
                'type' => 'payment',
                'label' => number_format($pendingPayments) . ' واریز در انتظار تأیید',
                'detail' => 'بررسی و تأیید اعلام پرداخت نهادها',
                'section' => 'transactions',
                'target' => 'pending-payments',
            ];
        }

        $pendingMembers = $this->scalar("SELECT COUNT(*) FROM members WHERE approval_status = 'pending'");
        if ($pendingMembers > 0) {
            $items[] = [
                'priority' => 20,
                'type' => 'member',
                'label' => number_format($pendingMembers) . ' عضو در انتظار تأیید',
                'detail' => 'بررسی درخواست‌های ثبت عضو',
                'section' => 'members',
                'target' => 'pending-members',
            ];
        }

        $pendingLockers = $this->scalar("SELECT COUNT(*) FROM locker_requests WHERE status = 'pending'");
        if ($pendingLockers > 0) {
            $items[] = [
                'priority' => 15,
                'type' => 'locker',
                'label' => number_format($pendingLockers) . ' درخواست کمد در انتظار',
                'detail' => 'تخصیص کمد به نهادهای درخواست‌کننده',
                'section' => 'lockers',
                'target' => 'pending-locker-requests',
            ];
        }

        $totalDebt = $this->scalar($this->debtSql());
        if ($totalDebt > 0) {
            $items[] = [
                'priority' => 25,
                'type' => 'debt',
                'label' => 'مجموع طلب از نهادها: ' . number_format($totalDebt) . ' ریال',
                'detail' => 'مشاهده کلاژ شارژ و پیگیری دریافت',
                'section' => 'charges',
            ];
        }

        $debtors = $this->preparedRows(
            "SELECT t.id AS team_id, t.name AS team_name,
                    COALESCE(SUM(c.amount), 0) - COALESCE(p.paid, 0) AS debt
             FROM charges c
             JOIN teams t ON t.id = c.team_id
             LEFT JOIN (
                SELECT team_id, SUM(amount) AS paid
                FROM transactions
                WHERE category = 'واریز تیم' AND confirmed = 1
                  AND fiscal_year = :year AND month_index = :month
                GROUP BY team_id
             ) p ON p.team_id = c.team_id
             WHERE c.fiscal_year = :year2 AND c.month_index = :month2 AND c.amount > 0
             GROUP BY t.id, t.name, p.paid
             HAVING debt > 0
             ORDER BY debt DESC
             LIMIT 5",
            ['year' => $year, 'year2' => $year, 'month' => $month, 'month2' => $month]
        );
        foreach ($debtors as $index => $row) {
            $items[] = [
                'priority' => 30 + $index,
                'type' => 'debt',
                'label' => (string) $row['team_name'],
                'detail' => 'مانده طلب ' . $today['month_name'] . ': ' . number_format((int) $row['debt']) . ' ریال',
                'section' => 'charges',
                'team_id' => (int) $row['team_id'],
            ];
        }

        $hasRate = (int) $this->preparedScalar(
            'SELECT COUNT(*) FROM rate_settings WHERE fiscal_year = :year',
            ['year' => $year]
        ) > 0;
        if (!$hasRate) {
            $items[] = [
                'priority' => 50,
                'type' => 'rate',
                'label' => 'نرخ سال ' . $year . ' تنظیم نشده',
                'detail' => 'تعریف نرخ شارژ در بخش شارژ',
                'section' => 'charges',
            ];
        }

        if ($this->scalar('SELECT COUNT(*) FROM lockers') === 0) {
            $items[] = [
                'priority' => 55,
                'type' => 'locker',
                'label' => 'هنوز کمدی ثبت نشده',
                'detail' => 'شماره کمدها را اضافه کنید',
                'section' => 'lockers',
            ];
        }

        $emptyLockers = $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'");
        if ($emptyLockers > 0) {
            $items[] = [
                'priority' => 60,
                'type' => 'locker',
                'label' => number_format($emptyLockers) . ' کمد خالی',
                'detail' => 'آماده تخصیص به نهادها',
                'section' => 'lockers',
            ];
        }

        $freeDesks = $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NULL');
        if ($freeDesks > 0) {
            $items[] = [
                'priority' => 70,
                'type' => 'desk',
                'label' => number_format($freeDesks) . ' میز آزاد',
                'detail' => 'از ۲۴ میز قابل تخصیص',
                'section' => 'desks',
            ];
        }

        usort($items, static fn (array $a, array $b): int => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

        return array_map(static function (array $item): array {
            unset($item['priority']);

            return $item;
        }, $items);
    }

    /**
     * @param array<string, string> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginatedResource(string $name, int $page = 1, int $perPage = 25, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $sql = $this->resourceSql($name, $filters);
        $total = $this->resourceCount($name, $filters);
        $offset = ($page - 1) * $perPage;
        $rows = array_map(
            fn (array $row): array => $this->stripLegacyRow($row),
            $this->rows($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset)
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string, string> $filters
     */
    private function resourceCount(string $name, array $filters = []): int
    {
        $teamId = Access::scopedTeamId();
        if ($teamId !== null) {
            return match ($name) {
                'teams' => 1,
                'members' => $this->preparedScalar('SELECT COUNT(*) FROM members WHERE team_id = :id', ['id' => $teamId]),
                'desks' => $this->preparedScalar('SELECT COUNT(*) FROM desks WHERE team_id = :id', ['id' => $teamId]),
                'lockers' => $this->preparedScalar('SELECT COUNT(*) FROM lockers WHERE team_id = :id', ['id' => $teamId]),
                'charges' => $this->preparedScalar('SELECT COUNT(*) FROM charges WHERE team_id = :id', ['id' => $teamId]),
                'transactions' => $this->teamTransactionCount($teamId, $filters),
                'payment-history' => $this->preparedScalar(
                    "SELECT COUNT(*) FROM transactions WHERE team_id = :id AND category = 'واریز تیم' AND payment_status IN ('approved', 'rejected')",
                    ['id' => $teamId]
                ),
                'locker-requests' => $this->preparedScalar('SELECT COUNT(*) FROM locker_requests WHERE team_id = :id', ['id' => $teamId]),
                'desk-assignments' => $this->preparedScalar(
                    'SELECT COUNT(*) FROM desk_assignments WHERE team_id = :id AND (assigned_until IS NULL OR assigned_until = \'\')',
                    ['id' => $teamId]
                ),
                default => 0,
            };
        }

        $sql = match ($name) {
            'teams' => 'SELECT COUNT(*) FROM teams',
            'members' => "SELECT COUNT(*) FROM members WHERE approval_status IN ('approved', 'rejected') OR approval_status IS NULL",
            'desks' => 'SELECT COUNT(*) FROM desks',
            'lockers' => 'SELECT COUNT(*) FROM lockers',
            'charges' => 'SELECT COUNT(*) FROM charges',
            'transactions' => $this->transactionCountSql($filters),
            'rate_settings' => 'SELECT COUNT(*) FROM rate_settings',
            'panel_users' => "SELECT COUNT(*) FROM panel_users WHERE role IN ('admin_editor', 'admin_viewer')",
            'development_plans' => 'SELECT COUNT(*) FROM development_plans',
            'pending-members' => "SELECT COUNT(*) FROM members WHERE approval_status = 'pending'",
            'pending-payments' => "SELECT COUNT(*) FROM transactions WHERE category = 'واریز تیم' AND payment_status = 'pending'",
            'pending-locker-requests' => "SELECT COUNT(*) FROM locker_requests WHERE status = 'pending'",
            'locker-requests' => 'SELECT COUNT(*) FROM locker_requests',
            'desk-assignments' => 'SELECT COUNT(*) FROM desk_assignments',
            'payment-history' => "SELECT COUNT(*) FROM transactions WHERE category = 'واریز تیم' AND payment_status IN ('approved', 'rejected')",
            default => throw new InvalidArgumentException('Unknown resource.'),
        };

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * @param array<string, string> $filters
     */
    private function transactionCountSql(array $filters): string
    {
        $clauses = [];
        $category = $filters['category'] ?? '';
        if ($category !== '') {
            $clauses[] = 'category = ' . $this->pdo->quote($category);
        }
        $paymentStatus = $filters['payment_status'] ?? '';
        if ($paymentStatus !== '') {
            $clauses[] = 'payment_status = ' . $this->pdo->quote($paymentStatus);
        }

        if ($clauses === []) {
            return 'SELECT COUNT(*) FROM transactions';
        }

        return 'SELECT COUNT(*) FROM transactions WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @param array<string, string> $filters
     */
    private function teamTransactionCount(int $teamId, array $filters): int
    {
        $clauses = ['team_id = :team_id', "category = 'واریز تیم'"];
        $params = ['team_id' => $teamId];
        $paymentStatus = $filters['payment_status'] ?? '';
        if ($paymentStatus !== '') {
            $clauses[] = 'payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        return $this->preparedScalar(
            'SELECT COUNT(*) FROM transactions WHERE ' . implode(' AND ', $clauses),
            $params
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resource(string $name): array
    {
        return array_map(
            fn (array $row): array => $this->stripLegacyRow($row),
            $this->rows($this->resourceSql($name))
        );
    }

    /**
     * @param array<string, string> $filters
     */
    private function resourceSql(string $name, array $filters = []): string
    {
        $teamId = Access::scopedTeamId();

        return match ($name) {
            'teams' => "SELECT t.id, t.entity_code, t.entity_type, t.name, t.leader, t.phone, t.joined_at,
                        t.contract_start, t.contract_end, t.warning, t.notes,
                        u.username AS portal_username,
                        u.password_plain AS portal_password,
                        (SELECT COUNT(*) FROM desks d WHERE d.team_id = t.id) AS desk_count,
                        (SELECT COALESCE(SUM(d.informal_seats), 0) FROM desks d WHERE d.team_id = t.id) AS informal_seats
                 FROM teams t
                 LEFT JOIN panel_users u ON u.team_id = t.id AND u.role = 'team'"
                . ($teamId !== null ? " WHERE t.id = {$teamId}" : '')
                . ' ORDER BY t.entity_type, t.name',
            'members' => "SELECT m.id, m.member_code, m.team_id, m.access_code, m.full_name, m.phone, m.national_id, m.notes,
                        m.approval_status, m.submitted_at, m.reviewed_at, m.rejection_reason, m.wants_access,
                        t.name AS team_label, t.entity_type,
                        (SELECT GROUP_CONCAT(d.number ORDER BY d.number)
                         FROM desks d WHERE d.team_id = m.team_id) AS desk_numbers
                 FROM members m
                 LEFT JOIN teams t ON t.id = m.team_id"
                . ($teamId !== null
                    ? " WHERE m.team_id = {$teamId}"
                    : " WHERE m.approval_status IN ('approved', 'rejected') OR m.approval_status IS NULL")
                . ' ORDER BY m.id',
            'desks' => "SELECT d.id, d.number, d.team_id, d.usage_type, d.formal_seats, d.informal_seats,
                        d.row_index, d.col_index, d.notes, t.name AS team_name, t.entity_type
                 FROM desks d
                 LEFT JOIN teams t ON t.id = d.team_id"
                . ($teamId !== null ? " WHERE d.team_id = {$teamId}" : '')
                . ' ORDER BY d.number',
            'lockers' => "SELECT l.id, l.locker_number, l.team_id, l.status, l.delivered_at, l.key_number, l.spare_key, l.notes,
                        t.name AS team_label
                 FROM lockers l
                 LEFT JOIN teams t ON t.id = l.team_id"
                . ($teamId !== null ? " WHERE l.team_id = {$teamId}" : '')
                . ' ORDER BY l.locker_number',
            'charges' => 'SELECT c.id, c.team_id, c.fiscal_year, c.month_index, c.month_name,
                        c.charge_amount, c.rent_amount, c.amount, c.note,
                        t.name AS team_name, t.entity_type
                 FROM charges c
                 LEFT JOIN teams t ON t.id = c.team_id'
                . ($teamId !== null ? " WHERE c.team_id = {$teamId}" : '')
                . ' ORDER BY c.fiscal_year, t.name, c.month_index',
            'transactions' => "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.team_id,
                        t.fiscal_year, t.month_index, t.confirmed, t.notes, t.payment_status, t.payment_reference, t.announced_at,
                        tm.name AS team_name,
                        CASE t.month_index
                            WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                            WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                            WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                            WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                            ELSE ''
                        END AS month_name
                 FROM transactions t
                 LEFT JOIN teams tm ON tm.id = t.team_id"
                . $this->transactionWhereClause($teamId, $filters)
                . ' ORDER BY t.tx_date DESC, t.id DESC',
            'pending-members' => "SELECT m.id, m.member_code, m.full_name, m.phone, m.national_id, m.wants_access, m.submitted_at,
                        t.name AS team_label, t.id AS team_id
                 FROM members m
                 INNER JOIN teams t ON t.id = m.team_id
                 WHERE m.approval_status = 'pending'
                 ORDER BY m.submitted_at DESC, m.id DESC",
            'pending-payments' => "SELECT t.id, t.tx_date, t.amount, t.description, t.payment_reference, t.announced_at, t.notes,
                        t.fiscal_year, t.month_index, tm.name AS team_name, tm.id AS team_id,
                        CASE t.month_index
                            WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                            WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                            WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                            WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                            ELSE ''
                        END AS month_name
                 FROM transactions t
                 INNER JOIN teams tm ON tm.id = t.team_id
                 WHERE t.category = 'واریز تیم' AND t.payment_status = 'pending'
                 ORDER BY t.announced_at DESC, t.id DESC",
            'pending-locker-requests' => "SELECT lr.id, lr.team_id, lr.notes, lr.submitted_at,
                        t.name AS team_label, t.entity_code
                 FROM locker_requests lr
                 INNER JOIN teams t ON t.id = lr.team_id
                 WHERE lr.status = 'pending'
                 ORDER BY lr.submitted_at DESC, lr.id DESC",
            'locker-requests' => "SELECT lr.id, lr.team_id, lr.notes, lr.status, lr.submitted_at, lr.reviewed_at,
                        lr.rejection_reason, lr.locker_id, l.locker_number,
                        t.name AS team_label
                 FROM locker_requests lr
                 LEFT JOIN teams t ON t.id = lr.team_id
                 LEFT JOIN lockers l ON l.id = lr.locker_id"
                . ($teamId !== null ? " WHERE lr.team_id = {$teamId}" : '')
                . ' ORDER BY lr.submitted_at DESC, lr.id DESC',
            'desk-assignments' => "SELECT da.id, da.desk_id, da.desk_number, da.team_id, da.usage_type,
                        da.assigned_from, da.assigned_until, da.notes, t.name AS team_name
                 FROM desk_assignments da
                 LEFT JOIN teams t ON t.id = da.team_id"
                . ($teamId !== null
                    ? " WHERE da.team_id = {$teamId} AND (da.assigned_until IS NULL OR da.assigned_until = '')"
                    : '')
                . ' ORDER BY da.desk_number, da.assigned_from DESC',
            'payment-history' => "SELECT t.id, t.tx_date, t.amount, t.description, t.payment_reference, t.payment_status, t.notes,
                        t.fiscal_year, t.month_index, t.confirmed, t.announced_at, t.reviewed_at,
                        tm.name AS team_name,
                        CASE t.month_index
                            WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                            WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                            WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                            WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                            ELSE ''
                        END AS month_name
                 FROM transactions t
                 LEFT JOIN teams tm ON tm.id = t.team_id
                 WHERE t.category = 'واریز تیم' AND t.payment_status IN ('approved', 'rejected')"
                . ($teamId !== null ? " AND t.team_id = {$teamId}" : '')
                . ' ORDER BY COALESCE(t.reviewed_at, t.tx_date) DESC, t.id DESC',
            'development_plans' => 'SELECT p.id, p.title, p.description, p.category, p.priority, p.status, p.due_date, p.notes,
                        p.sort_order, p.created_at, p.updated_at, p.depends_on_id, p.estimated_cost, p.estimated_revenue,
                        p.related_section, d.title AS depends_on_title
                 FROM development_plans p
                 LEFT JOIN development_plans d ON d.id = p.depends_on_id
                 ORDER BY p.sort_order, p.id DESC',
            'rate_settings' => 'SELECT id, fiscal_year, title, charge_rate, informal_rent_rate, effective_from, notes
                 FROM rate_settings ORDER BY fiscal_year, effective_from, id',
            'panel_users' => "SELECT u.id, u.username, u.role, u.team_id, u.full_name, u.is_active, t.name AS team_label
                 FROM panel_users u
                 LEFT JOIN teams t ON t.id = u.team_id
                 WHERE u.role IN ('admin_editor', 'admin_viewer')
                 ORDER BY u.username",
            default => throw new InvalidArgumentException('Unknown resource.'),
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function transactionWhereClause(?int $teamId, array $filters): string
    {
        $clauses = [];
        if ($teamId !== null) {
            $clauses[] = "t.team_id = {$teamId}";
            $clauses[] = "t.category = 'واریز تیم'";
        }
        $category = $filters['category'] ?? '';
        if ($category !== '') {
            $clauses[] = 't.category = ' . $this->pdo->quote($category);
        }
        $paymentStatus = $filters['payment_status'] ?? '';
        if ($paymentStatus !== '') {
            $clauses[] = 't.payment_status = ' . $this->pdo->quote($paymentStatus);
        }

        return $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @return array<string, mixed>
     */
    private function teamSummary(int $teamId): array
    {
        $profile = $this->teamProfile($teamId);

        return [
            'team' => $profile['team'],
            'cards' => [
                'members' => count(array_filter($profile['members'], static fn ($m) => ($m['approval_status'] ?? 'approved') === 'approved')),
                'desks' => count($profile['desks']),
                'desk_numbers' => implode('، ', array_map(static fn ($d) => (string) ($d['number'] ?? ''), $profile['desks'])),
                'lockers' => count($profile['lockers']),
                'charge_total' => $this->contractChargeTotalForTeam($teamId),
                'debt_total' => $this->contractDebtForTeam($teamId),
                'paid_total' => $this->contractPaidTotalForTeam($teamId),
                'pending_payments' => $this->preparedScalar(
                    "SELECT COUNT(*) FROM transactions
                     WHERE team_id = :id AND category = 'واریز تیم'
                     AND payment_status = 'pending' AND confirmed = 0",
                    ['id' => $teamId]
                ),
            ],
            'payment_history' => $this->preparedRows(
                "SELECT id, tx_date, fiscal_year, month_index, month_name, amount, payment_status, payment_reference, announced_at, reviewed_at
                 FROM (
                    SELECT t.id, t.tx_date, t.fiscal_year, t.month_index, t.amount, t.payment_status, t.payment_reference, t.announced_at, t.reviewed_at,
                           CASE t.month_index
                               WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                               WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                               WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                               WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                               ELSE ''
                           END AS month_name
                    FROM transactions t
                    WHERE t.team_id = :team_id AND t.category = 'واریز تیم'
                      AND t.payment_status IN ('approved', 'rejected')
                 ) q
                 ORDER BY fiscal_year DESC, month_index DESC, tx_date DESC",
                ['team_id' => $teamId]
            ),
            'current_month' => $this->currentMonthSummaryForTeam($teamId),
            'monthly_charges' => $this->preparedRows(
                'SELECT fiscal_year, month_index, month_name, amount
                 FROM charges WHERE team_id = :team_id
                 ORDER BY fiscal_year, month_index',
                ['team_id' => $teamId]
            ),
            'action_items' => [],
            'recent_approvals' => $this->recentApprovalsForTeam($teamId),
            'payment_settings' => (new CenterSettings($this->pdo))->get(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentApprovalsForTeam(int $teamId): array
    {
        $items = [];

        foreach ($this->preparedRows(
            "SELECT id, full_name, approval_status, reviewed_at, rejection_reason, access_code, wants_access
             FROM members
             WHERE team_id = :team_id AND reviewed_at IS NOT NULL AND reviewed_at != ''
             ORDER BY reviewed_at DESC
             LIMIT 8",
            ['team_id' => $teamId]
        ) as $row) {
            $status = (string) ($row['approval_status'] ?? '');
            $detail = (string) ($row['full_name'] ?? '');
            if ($status === 'approved' && ($row['access_code'] ?? '') !== '') {
                $detail .= ' — دسترسی تردد فعال است';
            } elseif ($status === 'approved' && (int) ($row['wants_access'] ?? 0) === 1) {
                $detail .= ' — در انتظار ثبت کد تردد';
            }
            $items[] = [
                'type' => 'member',
                'status' => $status,
                'label' => $status === 'approved' ? 'تأیید عضو' : 'رد عضو',
                'detail' => $detail,
                'reason' => $row['rejection_reason'] ?? null,
                'date' => (string) ($row['reviewed_at'] ?? ''),
                'section' => 'members',
            ];
        }

        foreach ($this->preparedRows(
            "SELECT id, tx_date, amount, payment_status, reviewed_at, fiscal_year, month_index, notes
             FROM transactions
             WHERE team_id = :team_id AND category = 'واریز تیم'
               AND payment_status IN ('approved', 'rejected')
               AND reviewed_at IS NOT NULL AND reviewed_at != ''
             ORDER BY reviewed_at DESC
             LIMIT 8",
            ['team_id' => $teamId]
        ) as $row) {
            $status = (string) ($row['payment_status'] ?? '');
            $monthName = $this->monthName((int) ($row['month_index'] ?? 0));
            $items[] = [
                'type' => 'payment',
                'status' => $status,
                'label' => $status === 'approved' ? 'تأیید واریز' : 'رد واریز',
                'detail' => trim(sprintf(
                    '%s %s — %s ریال',
                    $row['fiscal_year'] ?? '',
                    $monthName,
                    number_format((int) ($row['amount'] ?? 0))
                )),
                'reason' => $status === 'rejected' ? ($row['notes'] ?? null) : null,
                'date' => (string) ($row['reviewed_at'] ?? ''),
                'section' => 'payments',
            ];
        }

        foreach ($this->preparedRows(
            "SELECT lr.id, lr.status, lr.reviewed_at, lr.rejection_reason, l.locker_number
             FROM locker_requests lr
             LEFT JOIN lockers l ON l.id = lr.locker_id
             WHERE lr.team_id = :team_id
               AND lr.status IN ('approved', 'rejected')
               AND lr.reviewed_at IS NOT NULL AND lr.reviewed_at != ''
             ORDER BY lr.reviewed_at DESC
             LIMIT 8",
            ['team_id' => $teamId]
        ) as $row) {
            $status = (string) ($row['status'] ?? '');
            $detail = $status === 'approved' && ($row['locker_number'] ?? '') !== ''
                ? 'کمد شماره ' . $row['locker_number']
                : 'درخواست کمد';
            $items[] = [
                'type' => 'locker',
                'status' => $status,
                'label' => $status === 'approved' ? 'تأیید کمد' : 'رد درخواست کمد',
                'detail' => $detail,
                'reason' => $row['rejection_reason'] ?? null,
                'date' => (string) ($row['reviewed_at'] ?? ''),
                'section' => 'lockers',
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return array_slice($items, 0, 12);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentMonthSummaryForTeam(int $teamId): array
    {
        $today = JalaliDate::todayParts();
        $year = (string) $today['year'];
        $month = (int) $today['month'];
        $chargeTotal = $this->preparedScalar(
            'SELECT COALESCE(SUM(amount), 0) FROM charges WHERE team_id = :team_id AND fiscal_year = :year AND month_index = :month',
            ['team_id' => $teamId, 'year' => $year, 'month' => $month]
        );
        $allocation = $this->allocatedPaymentsForTeam($teamId);
        $paidTotal = (int) ($allocation['by_month'][$year . '-' . $month] ?? 0);

        return [
            'fiscal_year' => $year,
            'month_index' => $month,
            'month_name' => $today['month_name'],
            'today' => $today['formatted'],
            'charge_total' => $chargeTotal,
            'paid_total' => $paidTotal,
            'debt_total' => max(0, $chargeTotal - $paidTotal),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chargesMatrix(string $fiscalYear): array
    {
        $teamId = Access::scopedTeamId();
        if ($teamId !== null) {
            $teams = $this->preparedRows(
                'SELECT id, entity_code, entity_type, name, contract_start, contract_end FROM teams WHERE id = :id',
                ['id' => $teamId]
            );
        } else {
            $teams = $this->preparedRows(
                'SELECT id, entity_code, entity_type, name, contract_start, contract_end FROM teams ORDER BY entity_type, name'
            );
        }
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = ['index' => $i, 'name' => $this->monthName($i)];
        }

        $charges = $this->preparedRows(
            'SELECT team_id, month_index, charge_amount, rent_amount, amount
             FROM charges WHERE fiscal_year = :year' . ($teamId !== null ? ' AND team_id = :team_id' : ''),
            $teamId !== null ? ['year' => $fiscalYear, 'team_id' => $teamId] : ['year' => $fiscalYear]
        );
        $allocationMap = $this->paymentAllocationByTeamMonth();

        $chargeMap = [];
        foreach ($charges as $row) {
            $teamKey = (int) $row['team_id'];
            $monthKey = (int) $row['month_index'];
            if (!isset($chargeMap[$teamKey][$monthKey])) {
                $chargeMap[$teamKey][$monthKey] = [
                    'charge_amount' => (int) ($row['charge_amount'] ?? 0),
                    'rent_amount' => (int) ($row['rent_amount'] ?? 0),
                    'amount' => (int) ($row['amount'] ?? 0),
                ];
                continue;
            }
            $chargeMap[$teamKey][$monthKey]['charge_amount'] += (int) ($row['charge_amount'] ?? 0);
            $chargeMap[$teamKey][$monthKey]['rent_amount'] += (int) ($row['rent_amount'] ?? 0);
            $chargeMap[$teamKey][$monthKey]['amount'] += (int) ($row['amount'] ?? 0);
        }
        $rows = [];
        foreach ($teams as $team) {
            $cells = [];
            $contractStart = (string) ($team['contract_start'] ?? '');
            $contractEnd = (string) ($team['contract_end'] ?? '');
            $teamAllocations = $allocationMap[(int) $team['id']] ?? [];
            foreach ($months as $month) {
                $idx = (int) $month['index'];
                if (!JalaliDate::monthInContract($fiscalYear, $idx, $contractStart, $contractEnd)) {
                    $cells[] = [
                        'month_index' => $idx,
                        'charge_amount' => 0,
                        'rent_amount' => 0,
                        'amount_due' => 0,
                        'amount_paid' => 0,
                        'status' => 'خارج از قرارداد',
                    ];
                    continue;
                }
                $due = $chargeMap[$team['id']][$idx] ?? null;
                $paid = (int) ($teamAllocations[$fiscalYear . '-' . $idx] ?? 0);
                $amountDue = (int) ($due['amount'] ?? 0);
                $cells[] = [
                    'month_index' => $idx,
                    'charge_amount' => (int) ($due['charge_amount'] ?? 0),
                    'rent_amount' => (int) ($due['rent_amount'] ?? 0),
                    'amount_due' => $amountDue,
                    'amount_paid' => $paid,
                    'status' => $amountDue <= 0 ? '—' : ($paid >= $amountDue ? 'پرداخت‌شده' : ($paid > 0 ? 'ناقص' : 'بدهکار به مرکز')),
                ];
            }
            $rows[] = ['team' => $team, 'cells' => $cells];
        }

        return ['fiscal_year' => $fiscalYear, 'months' => $months, 'rows' => $rows];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chargeDebtRows(): array
    {
        $allocationMap = $this->paymentAllocationByTeamMonth();
        $rows = [];
        foreach ($this->rows(
            'SELECT c.team_id, t.name AS team_name, c.fiscal_year, c.month_index, c.month_name,
                    c.charge_amount, c.rent_amount, c.amount AS amount_due
             FROM charges c
             JOIN teams t ON t.id = c.team_id
             ORDER BY c.fiscal_year, t.name, c.month_index'
        ) as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            $key = ($row['fiscal_year'] ?? '') . '-' . (int) ($row['month_index'] ?? 0);
            $paid = (int) ($allocationMap[$teamId][$key] ?? 0);
            $due = (int) ($row['amount_due'] ?? 0);
            $rows[] = [
                'team_name' => $row['team_name'] ?? '',
                'fiscal_year' => $row['fiscal_year'] ?? '',
                'month_name' => $row['month_name'] ?? '',
                'charge_amount' => (int) ($row['charge_amount'] ?? 0),
                'rent_amount' => (int) ($row['rent_amount'] ?? 0),
                'amount_due' => $due,
                'amount_paid' => $paid,
                'status' => $due <= 0 ? '—' : ($paid >= $due ? 'پرداخت‌شده' : ($paid > 0 ? 'ناقص' : 'بدهکار به مرکز')),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teamChargeSummaryForReport(?string $fiscalYear = null): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear ?? $this->currentFiscalYear());
        $rows = [];
        foreach ($this->rows('SELECT id, name FROM teams ORDER BY name') as $team) {
            $teamId = (int) ($team['id'] ?? 0);
            $paidYear = $this->preparedScalar(
                "SELECT COALESCE(SUM(amount), 0) FROM transactions
                 WHERE team_id = :id AND category = 'واریز تیم'
                   AND payment_status = 'approved' AND confirmed = 1
                   AND fiscal_year = :year",
                ['id' => $teamId, 'year' => $fiscalYear]
            );
            $rows[] = [
                'team_name' => (string) ($team['name'] ?? ''),
                'paid_year' => $paidYear,
                'debt_total' => $this->contractDebtForTeam($teamId),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function teamProfile(int $teamId): array
    {
        Access::assertTeamAccess($teamId);
        $team = $this->preparedRow('SELECT * FROM teams WHERE id = :id', ['id' => $teamId]);
        if ($team === null) {
            throw new InvalidArgumentException('تیم پیدا نشد.');
        }

        return [
            'team' => self::stripLegacyColumns($team),
            'desks' => array_map(
                fn (array $row): array => $this->stripLegacyRow($row),
                $this->preparedRows('SELECT id, number, team_id, usage_type, formal_seats, informal_seats, row_index, col_index, notes FROM desks WHERE team_id = :id ORDER BY number', ['id' => $teamId])
            ),
            'members' => $this->preparedRows(
                'SELECT m.id, m.member_code, m.full_name, m.access_code, m.wants_access, m.phone, m.national_id, m.notes, m.approval_status
                 FROM members m WHERE m.team_id = :id ORDER BY m.full_name',
                ['id' => $teamId]
            ),
            'lockers' => $this->preparedRows(
                'SELECT l.id, l.locker_number, l.status, l.delivered_at, l.key_number, l.spare_key, l.notes
                 FROM lockers l WHERE l.team_id = :id ORDER BY l.locker_number',
                ['id' => $teamId]
            ),
            'desk_assignments' => $this->preparedRows(
                'SELECT da.id, da.desk_id, da.desk_number, da.usage_type, da.assigned_from, da.assigned_until, da.notes
                 FROM desk_assignments da
                 WHERE da.team_id = :id AND (da.assigned_until IS NULL OR da.assigned_until = \'\')
                 ORDER BY da.desk_number, da.assigned_from DESC',
                ['id' => $teamId]
            ),
            'locker_requests' => $this->preparedRows(
                'SELECT lr.id, lr.notes, lr.status, lr.submitted_at, lr.reviewed_at, lr.rejection_reason, l.locker_number
                 FROM locker_requests lr
                 LEFT JOIN lockers l ON l.id = lr.locker_id
                 WHERE lr.team_id = :id
                 ORDER BY lr.submitted_at DESC',
                ['id' => $teamId]
            ),
            'charges' => $this->preparedRows(
                'SELECT id, fiscal_year, month_index, month_name, charge_amount, rent_amount, amount, note
                 FROM charges WHERE team_id = :id ORDER BY fiscal_year, month_index',
                ['id' => $teamId]
            ),
            'payments' => $this->preparedRows(
                "SELECT id, tx_date, description, amount, category, fiscal_year, month_index, confirmed, notes,
                        payment_status, payment_reference, announced_at, reviewed_at,
                        CASE month_index
                            WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                            WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                            WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                            WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                            ELSE ''
                        END AS month_name
                 FROM transactions
                 WHERE team_id = :id AND category = 'واریز تیم'
                 ORDER BY fiscal_year, month_index, tx_date",
                ['id' => $teamId]
            ),
            'summary' => [
                'charge_total' => $this->contractChargeTotalForTeam($teamId),
                'paid_total' => $this->contractPaidTotalForTeam($teamId),
                'debt_total' => $this->contractDebtForTeam($teamId),
            ],
        ];
    }

    /**
     * @return array{rows:list<array<string,mixed>>}
     */
    public function deskMap(): array
    {
        $scope = Access::scopedTeamId();
        $rows = $this->rows(
            'SELECT d.id, d.number, d.team_id, d.usage_type, d.formal_seats, d.informal_seats,
                    d.row_index, d.col_index, t.name AS team_name
             FROM desks d
             LEFT JOIN teams t ON t.id = d.team_id
             ORDER BY d.number'
        );

        if ($scope !== null) {
            $rows = array_map(static function (array $row) use ($scope): array {
                $teamId = (int) ($row['team_id'] ?? 0);
                $isOwn = $teamId === $scope;
                $row['is_own'] = $isOwn;
                if ($teamId > 0 && !$isOwn) {
                    $row['team_name'] = 'نهاد دیگر';
                    $row['team_id'] = null;
                    $row['foreign_occupied'] = true;
                } else {
                    $row['foreign_occupied'] = false;
                }

                return $row;
            }, $rows);
        }

        return ['rows' => array_map(fn (array $row): array => $this->stripLegacyRow($row), $rows)];
    }

    private function debtSql(): string
    {
        return 'SELECT COALESCE((SELECT SUM(amount) FROM charges), 0)
                     - COALESCE((SELECT SUM(amount) FROM transactions WHERE category = \'واریز تیم\' AND confirmed = 1 AND payment_status = \'approved\'), 0)';
    }

    private function contractDebtForTeam(int $teamId): int
    {
        return max(0, $this->contractChargeTotalForTeam($teamId) - $this->contractPaidTotalForTeam($teamId));
    }

    private function contractChargeTotalForTeam(int $teamId): int
    {
        $team = $this->preparedRow('SELECT contract_start, contract_end FROM teams WHERE id = :id', ['id' => $teamId]);
        if ($team === null) {
            return 0;
        }
        $total = 0;
        foreach ($this->preparedRows(
            'SELECT fiscal_year, month_index, amount FROM charges WHERE team_id = :id',
            ['id' => $teamId]
        ) as $row) {
            if (JalaliDate::monthInContract(
                (string) ($row['fiscal_year'] ?? ''),
                (int) ($row['month_index'] ?? 0),
                (string) ($team['contract_start'] ?? ''),
                (string) ($team['contract_end'] ?? '')
            )) {
                $total += (int) ($row['amount'] ?? 0);
            }
        }

        return $total;
    }

    private function contractPaidTotalForTeam(int $teamId): int
    {
        return $this->preparedScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE team_id = :id AND category = 'واریز تیم'
             AND payment_status = 'approved' AND confirmed = 1",
            ['id' => $teamId]
        );
    }

    /**
     * @return array{by_month: array<string, int>, remaining: int}
     */
    private function allocatedPaymentsForTeam(int $teamId): array
    {
        $team = $this->preparedRow('SELECT contract_start, contract_end FROM teams WHERE id = :id', ['id' => $teamId]);
        if ($team === null) {
            return ['by_month' => [], 'remaining' => 0];
        }

        $charges = [];
        foreach ($this->preparedRows(
            'SELECT fiscal_year, month_index, amount FROM charges WHERE team_id = :id ORDER BY fiscal_year, month_index',
            ['id' => $teamId]
        ) as $row) {
            if (!JalaliDate::monthInContract(
                (string) ($row['fiscal_year'] ?? ''),
                (int) ($row['month_index'] ?? 0),
                (string) ($team['contract_start'] ?? ''),
                (string) ($team['contract_end'] ?? '')
            )) {
                continue;
            }
            $key = ($row['fiscal_year'] ?? '') . '-' . (int) ($row['month_index'] ?? 0);
            $charges[$key] = ($charges[$key] ?? 0) + (int) ($row['amount'] ?? 0);
        }

        $remaining = $this->contractPaidTotalForTeam($teamId);
        $byMonth = [];
        foreach ($charges as $key => $due) {
            $allocated = min($remaining, $due);
            $byMonth[$key] = $allocated;
            $remaining -= $allocated;
        }

        return ['by_month' => $byMonth, 'remaining' => max(0, $remaining)];
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function paymentAllocationByTeamMonth(): array
    {
        if ($this->paymentAllocationCache !== null) {
            return $this->paymentAllocationCache;
        }

        $map = [];
        foreach ($this->pdo->query('SELECT id, contract_start, contract_end FROM teams')->fetchAll() as $team) {
            $teamId = (int) $team['id'];
            $allocation = $this->allocatedPaymentsForTeam($teamId);
            $map[$teamId] = $allocation['by_month'];
        }

        $this->paymentAllocationCache = $map;

        return $map;
    }

    private function currentFiscalYear(): string
    {
        return (string) JalaliDate::todayParts()['year'];
    }

    private function currentMonthIndex(): int
    {
        return (int) JalaliDate::todayParts()['month'];
    }

    private function incomeForPeriod(string $year, ?int $month = null): int
    {
        if ($month !== null) {
            return $this->preparedScalar(
                "SELECT COALESCE(SUM(amount), 0) FROM transactions
                 WHERE confirmed = 1 AND amount > 0
                 AND (
                    (category = 'واریز تیم' AND payment_status = 'approved' AND fiscal_year = :year AND month_index = :month)
                    OR (category = 'درآمد' AND tx_date LIKE :date_prefix)
                 )",
                ['year' => $year, 'month' => $month, 'date_prefix' => sprintf('%s/%02d', $year, $month) . '%']
            );
        }

        return $this->preparedScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE confirmed = 1 AND amount > 0
             AND (
                (category = 'واریز تیم' AND fiscal_year = :year)
                OR (category = 'درآمد' AND tx_date LIKE :year_prefix)
             )",
            ['year' => $year, 'year_prefix' => $year . '%']
        );
    }

    private function expenseForPeriod(string $year, ?int $month = null): int
    {
        if ($month !== null) {
            return $this->preparedScalar(
                "SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions
                 WHERE category = 'هزینه' AND confirmed = 1
                 AND tx_date LIKE :date_prefix",
                ['date_prefix' => sprintf('%s/%02d', $year, $month) . '%']
            );
        }

        return $this->preparedScalar(
            "SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions
             WHERE category = 'هزینه' AND confirmed = 1
             AND tx_date LIKE :year_prefix",
            ['year_prefix' => $year . '%']
        );
    }

    private function monthName(int $index): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$index] ?? '';
    }

    public function scalar(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function row(string $sql): ?array
    {
        $row = $this->pdo->query($sql)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function preparedScalar(string $sql, array $params): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function preparedRow(string $sql, array $params = []): ?array
    {
        $rows = $this->preparedRows($sql, $params);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function preparedRows(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function stripLegacyColumns(array $row): array
    {
        foreach (self::LEGACY_COLUMNS as $column) {
            unset($row[$column]);
        }

        return $row;
    }

    private function stripLegacyRow(array $row): array
    {
        return self::stripLegacyColumns($row);
    }
}
