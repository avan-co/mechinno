<?php

declare(strict_types=1);

final class Repository
{
    /** @var list<string> Legacy DB columns that must never appear in API/UI. */
    private const LEGACY_COLUMNS = ['row_number', 'lockers', 'power_strips', 'rent_rate'];

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
                'debt_total' => $this->totalContractDebt(),
                'charge_total' => $this->totalContractCharge(),
                'income_total' => $this->incomeForPeriod($this->currentFiscalYear()),
                'expense_total' => abs($this->expenseForPeriod($this->currentFiscalYear())),
                'paid_total' => $this->scalar(
                    "SELECT COALESCE(SUM(amount), 0) FROM transactions
                     WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1"
                ),
                'paid_total_year' => $this->incomeTeamDepositsForPeriod($this->currentFiscalYear()),
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
            'debt_by_team' => $this->debtByTeamRows(),
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
            'debt_total' => $this->currentMonthDebtTotal($year, $month),
            'debtor_count' => $this->currentMonthDebtorCount($year, $month),
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

        $pendingMemberRequests = $this->scalar("SELECT COUNT(*) FROM member_requests WHERE status = 'pending'");
        if ($pendingMemberRequests > 0) {
            $items[] = [
                'priority' => 18,
                'type' => 'member',
                'label' => number_format($pendingMemberRequests) . ' درخواست تغییر عضو',
                'detail' => 'ویرایش یا حذف اعضای تأیید‌شده',
                'section' => 'members',
                'target' => 'pending-member-requests',
            ];
        }

        $totalDebt = $this->totalContractDebt();
        if ($totalDebt > 0) {
            $items[] = [
                'priority' => 25,
                'type' => 'debt',
                'label' => 'مجموع طلب از نهادها: ' . number_format($totalDebt) . ' ریال',
                'detail' => 'مشاهده کلاژ شارژ و پیگیری دریافت',
                'section' => 'charges',
            ];
        }

        $debtors = $this->currentMonthDebtors($year, $month, 5);
        foreach ($debtors as $index => $row) {
            $items[] = [
                'priority' => 30 + $index,
                'type' => 'debt',
                'label' => (string) $row['team_name'],
                'detail' => 'مانده مطالبه ' . $today['month_name'] . ': ' . number_format((int) $row['debt']) . ' ریال',
                'section' => 'charges',
                'team_id' => (int) $row['team_id'],
            ];
        }

        foreach ($this->teamsWithExpiringContracts(60) as $team) {
            $items[] = [
                'priority' => 22,
                'type' => 'rate',
                'label' => 'پایان قرارداد نزدیک: ' . (string) $team['name'],
                'detail' => 'پایان ' . (string) ($team['contract_end'] ?? '—'),
                'section' => 'teams',
                'team_id' => (int) $team['id'],
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
            $this->rows($sql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset)
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
            if (trim((string) ($filters['q'] ?? '')) !== '' && in_array($name, [
                'members', 'desks', 'lockers', 'charges', 'transactions',
            ], true)) {
                return (int) $this->pdo->query(
                    'SELECT COUNT(*) FROM (' . $this->resourceSql($name, $filters) . ') AS filtered_rows'
                )->fetchColumn();
            }

            return match ($name) {
                'teams' => 1,
                'members' => $this->preparedScalar('SELECT COUNT(*) FROM members WHERE team_id = :id', ['id' => $teamId]),
                'desks' => $this->preparedScalar('SELECT COUNT(*) FROM desks WHERE team_id = :id', ['id' => $teamId]),
                'lockers' => $this->preparedScalar('SELECT COUNT(*) FROM lockers WHERE team_id = :id', ['id' => $teamId]),
                'charges' => $this->preparedScalar('SELECT COUNT(*) FROM charges WHERE team_id = :id', ['id' => $teamId]),
                'transactions' => $this->teamTransactionCount($teamId, $filters),
                'payment-history' => $this->preparedScalar(
                    "SELECT COUNT(*) FROM transactions WHERE team_id = :id AND category = 'واریز تیم'"
                    . $this->paymentHistoryStatusSql($filters, true),
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

        if (trim((string) ($filters['q'] ?? '')) !== '' && in_array($name, [
            'teams', 'members', 'desks', 'lockers', 'charges', 'transactions', 'rate_settings', 'panel_users', 'development_plans', 'sms-recipients',
        ], true)) {
            return (int) $this->pdo->query(
                'SELECT COUNT(*) FROM (' . $this->resourceSql($name, $filters) . ') AS filtered_rows'
            )->fetchColumn();
        }

        $sql = match ($name) {
            'teams' => 'SELECT COUNT(*) FROM teams',
            'members' => "SELECT COUNT(*) FROM members m LEFT JOIN teams t ON t.id = m.team_id
                WHERE (m.approval_status IN ('approved', 'rejected') OR m.approval_status IS NULL)"
                . $this->memberApprovalClause($filters, true)
                . $this->memberListFilterClause($filters, true),
            'sms-recipients' => "SELECT COUNT(*) FROM members m INNER JOIN teams t ON t.id = m.team_id
                WHERE m.approval_status = 'approved'"
                . $this->memberListFilterClause($filters, true),
            'sms-history' => 'SELECT COUNT(*) FROM sms_logs WHERE 1=1' . $this->smsHistoryFilterClause($filters, true),
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
            'pending-member-requests' => "SELECT COUNT(*) FROM member_requests WHERE status = 'pending'",
            'locker-requests' => 'SELECT COUNT(*) FROM locker_requests',
            'member-requests' => 'SELECT COUNT(*) FROM member_requests',
            'desk-assignments' => 'SELECT COUNT(*) FROM desk_assignments',
            'team_contracts' => 'SELECT COUNT(*) FROM team_contracts',
            'payment-history' => "SELECT COUNT(*) FROM transactions WHERE category = 'واریز تیم'"
                . $this->paymentHistoryStatusSql($filters, true),
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
                        t.contract_start, t.contract_end, t.is_active, t.warning, t.notes,
                        u.username AS portal_username,
                        CASE WHEN u.password_plain IS NOT NULL AND u.password_plain <> '' THEN 1 ELSE 0 END AS portal_has_password,
                        (SELECT COUNT(*) FROM desks d WHERE d.team_id = t.id) AS desk_count,
                        (SELECT COALESCE(SUM(d.informal_seats), 0) FROM desks d WHERE d.team_id = t.id) AS informal_seats
                 FROM teams t
                 LEFT JOIN panel_users u ON u.team_id = t.id AND u.role = 'team'"
                . ($teamId !== null ? " WHERE t.id = {$teamId}" : '')
                . $this->searchClause('teams', $filters)
                . ' ORDER BY t.is_active DESC, t.entity_type, t.name',
            'team_contracts' => "SELECT tc.id, tc.team_id, tc.fiscal_year, tc.contract_start, tc.contract_end, tc.notes, tc.created_at,
                        t.name AS team_name, t.entity_type
                 FROM team_contracts tc
                 INNER JOIN teams t ON t.id = tc.team_id"
                . ($teamId !== null ? " WHERE tc.team_id = {$teamId}" : '')
                . $this->searchClause('team_contracts', $filters, $teamId !== null)
                . ' ORDER BY tc.fiscal_year DESC, t.name',
            'members' => "SELECT m.id, m.member_code, m.team_id, m.access_code, m.full_name, m.phone, m.national_id, m.notes,
                        m.approval_status, m.submitted_at, m.reviewed_at, m.rejection_reason, m.wants_access, m.is_leader,
                        t.name AS team_label, t.entity_type,
                        (SELECT GROUP_CONCAT(d.number ORDER BY d.number)
                         FROM desks d WHERE d.team_id = m.team_id) AS desk_numbers
                 FROM members m
                 LEFT JOIN teams t ON t.id = m.team_id"
                . ($teamId !== null
                    ? " WHERE m.team_id = {$teamId}"
                    : " WHERE m.approval_status IN ('approved', 'rejected') OR m.approval_status IS NULL")
                . $this->memberApprovalClause($filters)
                . $this->memberListFilterClause($filters)
                . $this->searchClause('members', $filters, true)
                . ' ORDER BY m.is_leader DESC, t.name, m.full_name, m.id',
            'sms-recipients' => "SELECT m.id, m.member_code, m.team_id, m.full_name, m.phone, m.national_id,
                        m.wants_access, m.is_leader, t.name AS team_label, t.entity_type
                 FROM members m
                 INNER JOIN teams t ON t.id = m.team_id
                 WHERE m.approval_status = 'approved'"
                . $this->memberListFilterClause($filters)
                . $this->searchClause('members', $filters, true)
                . ' ORDER BY m.is_leader DESC, t.name, m.full_name, m.id',
            'sms-history' => 'SELECT id, batch_uid, message_type, member_id, team_id, team_name, recipient_name, phone,
                        is_leader, message_text, status, error_message, provider_rec_id, cost_rial, sent_by, created_at, sent_at,
                        delivery_status, delivery_checked_at, api_confirmed
                 FROM sms_logs WHERE 1=1'
                . $this->smsHistoryFilterClause($filters)
                . ' ORDER BY created_at DESC, id DESC',
            'desks' => "SELECT d.id, d.number, d.team_id, d.usage_type, d.formal_seats, d.informal_seats,
                        d.row_index, d.col_index, d.notes, t.name AS team_name, t.entity_type, t.is_active AS team_is_active
                 FROM desks d
                 LEFT JOIN teams t ON t.id = d.team_id"
                . ($teamId !== null ? " WHERE d.team_id = {$teamId}" : '')
                . $this->searchClause('desks', $filters, $teamId !== null)
                . ' ORDER BY d.number',
            'lockers' => "SELECT l.id, l.locker_number, l.team_id, l.status, l.delivered_at, l.key_number, l.spare_key, l.notes,
                        t.name AS team_label, t.is_active AS team_is_active
                 FROM lockers l
                 LEFT JOIN teams t ON t.id = l.team_id"
                . ($teamId !== null ? " WHERE l.team_id = {$teamId}" : '')
                . $this->searchClause('lockers', $filters, $teamId !== null)
                . ' ORDER BY l.locker_number',
            'charges' => 'SELECT c.id, c.team_id, c.fiscal_year, c.month_index, c.month_name,
                        c.charge_amount, c.rent_amount, c.amount, c.note,
                        t.name AS team_name, t.entity_type
                 FROM charges c
                 LEFT JOIN teams t ON t.id = c.team_id'
                . ($teamId !== null ? " WHERE c.team_id = {$teamId}" : '')
                . $this->searchClause('charges', $filters, $teamId !== null)
                . ' ORDER BY c.fiscal_year, t.name, c.month_index',
            'transactions' => "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.finance_subtype, t.team_id,
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
                . $this->searchClause('transactions', $filters, $teamId !== null || ($filters['category'] ?? '') !== '' || ($filters['payment_status'] ?? '') !== '')
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
            'pending-member-requests' => "SELECT mr.id, mr.team_id, mr.member_id, mr.request_type, mr.full_name, mr.phone,
                        mr.national_id, mr.wants_access, mr.notes, mr.submitted_at,
                        m.full_name AS current_full_name, m.member_code, t.name AS team_label
                 FROM member_requests mr
                 INNER JOIN teams t ON t.id = mr.team_id
                 INNER JOIN members m ON m.id = mr.member_id
                 WHERE mr.status = 'pending'
                 ORDER BY mr.submitted_at DESC, mr.id DESC",
            'member-requests' => "SELECT mr.id, mr.team_id, mr.member_id, mr.request_type, mr.full_name, mr.phone,
                        mr.national_id, mr.wants_access, mr.notes, mr.status, mr.submitted_at, mr.reviewed_at,
                        mr.rejection_reason, m.full_name AS current_full_name, m.member_code, t.name AS team_label
                 FROM member_requests mr
                 LEFT JOIN teams t ON t.id = mr.team_id
                 LEFT JOIN members m ON m.id = mr.member_id"
                . ($teamId !== null ? " WHERE mr.team_id = {$teamId}" : '')
                . ' ORDER BY mr.submitted_at DESC, mr.id DESC',
            'locker-requests' => "SELECT lr.id, lr.team_id, lr.notes, lr.status, lr.submitted_at, lr.reviewed_at,
                        lr.rejection_reason, lr.locker_id, l.locker_number,
                        t.name AS team_label
                 FROM locker_requests lr
                 LEFT JOIN teams t ON t.id = lr.team_id
                 LEFT JOIN lockers l ON l.id = lr.locker_id"
                . ($teamId !== null ? " WHERE lr.team_id = {$teamId}" : '')
                . ' ORDER BY lr.submitted_at DESC, lr.id DESC',
            'desk-assignments' => "SELECT da.id, da.desk_id, da.desk_number, da.team_id, da.usage_type,
                        da.assigned_from, da.assigned_until, da.notes,
                        SUBSTR(da.assigned_from, 1, 4) AS fiscal_year, t.name AS team_name
                 FROM desk_assignments da
                 LEFT JOIN teams t ON t.id = da.team_id"
                . ($teamId !== null
                    ? " WHERE da.team_id = {$teamId}"
                    : $this->deskAssignmentYearClause($filters))
                . ' ORDER BY da.assigned_from DESC, da.desk_number',
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
                 WHERE t.category = 'واریز تیم'"
                . $this->paymentHistoryStatusSql($filters, false, 't')
                . ($teamId !== null ? " AND t.team_id = {$teamId}" : '')
                . ' ORDER BY COALESCE(t.reviewed_at, t.tx_date) DESC, t.id DESC',
            'development_plans' => 'SELECT p.id, p.title, p.description, p.category, p.priority, p.status, p.due_date, p.notes,
                        p.sort_order, p.created_at, p.updated_at, p.depends_on_id, p.estimated_cost, p.estimated_revenue,
                        p.related_section, d.title AS depends_on_title
                 FROM development_plans p
                 LEFT JOIN development_plans d ON d.id = p.depends_on_id'
                . $this->searchClause('development_plans', $filters, false)
                . ' ORDER BY p.sort_order, p.id DESC',
            'rate_settings' => 'SELECT id, fiscal_year, title, charge_rate, informal_rent_rate, effective_from, notes
                 FROM rate_settings'
                . $this->searchClause('rate_settings', $filters, false)
                . ' ORDER BY fiscal_year, effective_from, id',
            'panel_users' => "SELECT u.id, u.username, u.role, u.team_id, u.full_name, u.is_active, t.name AS team_label
                 FROM panel_users u
                 LEFT JOIN teams t ON t.id = u.team_id
                 WHERE u.role IN ('admin_editor', 'admin_viewer')"
                . $this->searchClause('panel_users', $filters, true)
                . ' ORDER BY u.username',
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
            'action_items' => $this->teamActionItems($teamId),
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
        $paidTotal = (int) ($allocation['by_month'][JalaliDate::normalizeDigits($year) . '-' . $month] ?? 0);

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

    private function contracts(): TeamContracts
    {
        return new TeamContracts($this->pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function chargesMatrix(string $fiscalYear): array
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $contracts = $this->contracts();
        $teamId = Access::scopedTeamId();
        if ($teamId !== null) {
            $teams = $this->preparedRows(
                'SELECT id, entity_code, entity_type, name, is_active FROM teams WHERE id = :id',
                ['id' => $teamId]
            );
        } else {
            $teamIds = $contracts->teamIdsWithContractInYear($fiscalYear);
            if ($teamIds === []) {
                $teams = [];
            } else {
                $idList = implode(',', array_map('intval', $teamIds));
                $teams = $this->rows(
                    "SELECT id, entity_code, entity_type, name, is_active
                     FROM teams WHERE id IN ({$idList})
                     ORDER BY is_active DESC, entity_type, name"
                );
            }
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
            $tid = (int) $team['id'];
            if (!$contracts->hasContractInYear($tid, $fiscalYear)) {
                continue;
            }
            if (!$contracts->hasDeskInFiscalYear($tid, $fiscalYear)) {
                continue;
            }
            $dates = $contracts->contractDatesForYear($tid, $fiscalYear);
            $hasInformal = $contracts->hasInformalDeskInYear($tid, $fiscalYear);
            $cells = [];
            $teamAllocations = $allocationMap[$tid] ?? [];
            foreach ($months as $month) {
                $idx = (int) $month['index'];
                if (!JalaliDate::monthInContract($fiscalYear, $idx, $dates['start'], $dates['end'])) {
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
                if ($contracts->deskCountForMonth($tid, $fiscalYear, $idx) <= 0) {
                    $cells[] = [
                        'month_index' => $idx,
                        'charge_amount' => 0,
                        'rent_amount' => 0,
                        'amount_due' => 0,
                        'amount_paid' => 0,
                        'status' => '—',
                    ];
                    continue;
                }
                $due = $chargeMap[$tid][$idx] ?? null;
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
            $rows[] = [
                'team' => array_merge($team, [
                    'has_informal_desk' => $hasInformal,
                    'contract_start' => $dates['start'],
                    'contract_end' => $dates['end'],
                ]),
                'cells' => $cells,
            ];
        }

        return [
            'fiscal_year' => $fiscalYear,
            'months' => $months,
            'rows' => $rows,
            'show_rent_column' => array_reduce(
                $rows,
                static fn (bool $carry, array $row): bool => $carry || (bool) ($row['team']['has_informal_desk'] ?? false),
                false
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teamPayableMonths(int $teamId): array
    {
        Access::assertTeamAccess($teamId);
        $contracts = $this->contracts();
        $allocation = $this->allocatedPaymentsForTeam($teamId);
        $byMonth = $allocation['by_month'];
        $chargeMap = [];
        foreach ($this->preparedRows(
            'SELECT fiscal_year, month_index, month_name, amount FROM charges
             WHERE team_id = :id ORDER BY fiscal_year, month_index',
            ['id' => $teamId]
        ) as $charge) {
            $fy = JalaliDate::normalizeDigits((string) ($charge['fiscal_year'] ?? ''));
            $mi = (int) ($charge['month_index'] ?? 0);
            $key = $fy . '-' . $mi;
            if (!isset($chargeMap[$key])) {
                $chargeMap[$key] = [
                    'fiscal_year' => $fy,
                    'month_index' => $mi,
                    'month_name' => (string) ($charge['month_name'] ?? $this->monthName($mi)),
                    'amount' => 0,
                ];
            }
            $chargeMap[$key]['amount'] += (int) ($charge['amount'] ?? 0);
        }

        $rows = [];
        foreach ($chargeMap as $charge) {
            $fy = (string) ($charge['fiscal_year'] ?? '');
            $mi = (int) ($charge['month_index'] ?? 0);
            $dates = $contracts->contractDatesForYear($teamId, $fy);
            if (!JalaliDate::monthInContract($fy, $mi, $dates['start'], $dates['end'])) {
                continue;
            }
            if ($contracts->deskCountForMonth($teamId, $fy, $mi) <= 0) {
                continue;
            }
            $key = $fy . '-' . $mi;
            $due = (int) ($charge['amount'] ?? 0);
            $paid = (int) ($byMonth[$key] ?? 0);
            $remaining = max(0, $due - $paid);
            if ($remaining <= 0) {
                continue;
            }
            $rows[] = [
                'fiscal_year' => $fy,
                'month_index' => $mi,
                'month_name' => (string) ($charge['month_name'] ?? $this->monthName($mi)),
                'amount_due' => $due,
                'amount_paid' => $paid,
                'amount_remaining' => $remaining,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['fiscal_year'], $a['month_index']] <=> [$b['fiscal_year'], $b['month_index']]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function teamPortalCredentials(int $teamId): array
    {
        if (!Access::canWrite()) {
            throw new InvalidArgumentException('دسترسی مجاز نیست.');
        }
        $row = $this->preparedRow(
            "SELECT u.username, u.password_plain FROM panel_users u
             WHERE u.team_id = :team_id AND u.role = 'team' LIMIT 1",
            ['team_id' => $teamId]
        );
        if ($row === null) {
            throw new InvalidArgumentException('حساب ورود نهاد یافت نشد.');
        }

        return [
            'username' => (string) ($row['username'] ?? ''),
            'password' => (string) ($row['password_plain'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chargeDebtRows(): array
    {
        $contracts = $this->contracts();
        $allocationMap = $this->paymentAllocationByTeamMonth();
        $aggregated = [];
        foreach ($this->rows(
            'SELECT c.team_id, t.name AS team_name,
                    c.fiscal_year, c.month_index, c.month_name,
                    c.charge_amount, c.rent_amount, c.amount AS amount_due
             FROM charges c
             JOIN teams t ON t.id = c.team_id
             ORDER BY c.fiscal_year, t.name, c.month_index'
        ) as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            $fiscalYear = (string) ($row['fiscal_year'] ?? '');
            $monthIndex = (int) ($row['month_index'] ?? 0);
            $key = $teamId . '-' . $fiscalYear . '-' . $monthIndex;
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'team_id' => $teamId,
                    'team_name' => $row['team_name'] ?? '',
                    'fiscal_year' => $fiscalYear,
                    'month_index' => $monthIndex,
                    'month_name' => $row['month_name'] ?? '',
                    'charge_amount' => 0,
                    'rent_amount' => 0,
                    'amount_due' => 0,
                ];
            }
            $aggregated[$key]['charge_amount'] += (int) ($row['charge_amount'] ?? 0);
            $aggregated[$key]['rent_amount'] += (int) ($row['rent_amount'] ?? 0);
            $aggregated[$key]['amount_due'] += (int) ($row['amount_due'] ?? 0);
        }

        $rows = [];
        foreach ($aggregated as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            $fiscalYear = (string) ($row['fiscal_year'] ?? '');
            $monthIndex = (int) ($row['month_index'] ?? 0);
            $dates = $contracts->contractDatesForYear($teamId, $fiscalYear);
            if (!JalaliDate::monthInContract($fiscalYear, $monthIndex, $dates['start'], $dates['end'])) {
                continue;
            }
            if ($contracts->deskCountForMonth($teamId, $fiscalYear, $monthIndex) <= 0) {
                continue;
            }
            $key = $fiscalYear . '-' . $monthIndex;
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
            $rows[] = [
                'team_name' => (string) ($team['name'] ?? ''),
                'paid_year' => $this->contractPaidAllocatedForTeamInYear($teamId, $fiscalYear),
                'debt_year' => $this->contractDebtForTeamInYear($teamId, $fiscalYear),
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
            'contracts' => $this->preparedRows(
                'SELECT id, fiscal_year, contract_start, contract_end, notes, created_at
                 FROM team_contracts WHERE team_id = :id ORDER BY fiscal_year DESC',
                ['id' => $teamId]
            ),
            'has_informal_desk' => $this->contracts()->hasInformalDeskInYear($teamId, $this->currentFiscalYear()),
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
                'SELECT da.id, da.desk_id, da.desk_number, da.usage_type, da.assigned_from, da.assigned_until, da.notes,
                        SUBSTR(da.assigned_from, 1, 4) AS fiscal_year
                 FROM desk_assignments da
                 WHERE da.team_id = :id
                 ORDER BY da.assigned_from DESC, da.desk_number',
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
            'summary' => array_merge([
                'charge_total' => $this->contractChargeTotalForTeam($teamId),
                'paid_total' => $this->contractPaidTotalForTeam($teamId),
                'debt_total' => $this->contractDebtForTeam($teamId),
            ], $this->paymentOverpaymentForTeam($teamId)),
            'current_month' => $this->currentMonthSummaryForTeam($teamId),
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
                    d.row_index, d.col_index, t.name AS team_name, t.is_active AS team_is_active
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

    public function totalContractDebt(): int
    {
        $total = 0;
        foreach ($this->rows('SELECT id FROM teams') as $team) {
            $total += $this->contractDebtForTeam((int) $team['id']);
        }

        return $total;
    }

    public function totalContractCharge(): int
    {
        $total = 0;
        foreach ($this->rows('SELECT id FROM teams') as $team) {
            $total += $this->contractChargeTotalForTeam((int) $team['id']);
        }

        return $total;
    }

    /**
     * @return list<array{team_id:int, team_name:string, debt:int}>
     */
    private function debtByTeamRows(): array
    {
        $rows = [];
        foreach ($this->rows('SELECT id, name FROM teams ORDER BY name') as $team) {
            $debt = $this->contractDebtForTeam((int) $team['id']);
            if ($debt <= 0) {
                continue;
            }
            $rows[] = [
                'team_id' => (int) $team['id'],
                'team_name' => (string) ($team['name'] ?? ''),
                'debt' => $debt,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['debt'] ?? 0) <=> ($a['debt'] ?? 0));

        return array_slice($rows, 0, 10);
    }

    private function currentMonthDebtTotal(string $year, int $month): int
    {
        $total = 0;
        foreach ($this->currentMonthTeamDebts($year, $month) as $debt) {
            $total += $debt;
        }

        return $total;
    }

    private function currentMonthDebtorCount(string $year, int $month): int
    {
        $count = 0;
        foreach ($this->currentMonthTeamDebts($year, $month) as $debt) {
            if ($debt > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array{team_id:int, team_name:string, debt:int}>
     */
    private function currentMonthDebtors(string $year, int $month, int $limit = 5): array
    {
        $rows = [];
        foreach ($this->rows('SELECT id, name FROM teams ORDER BY name') as $team) {
            $teamId = (int) $team['id'];
            $debt = $this->teamMonthDebt($teamId, $year, $month);
            if ($debt <= 0) {
                continue;
            }
            $rows[] = [
                'team_id' => $teamId,
                'team_name' => (string) ($team['name'] ?? ''),
                'debt' => $debt,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['debt'] ?? 0) <=> ($a['debt'] ?? 0));

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<int, int>
     */
    private function currentMonthTeamDebts(string $year, int $month): array
    {
        $debts = [];
        foreach ($this->rows('SELECT id FROM teams') as $team) {
            $teamId = (int) $team['id'];
            $debts[$teamId] = $this->teamMonthDebt($teamId, $year, $month);
        }

        return $debts;
    }

    private function teamMonthDebt(int $teamId, string $year, int $month): int
    {
        $chargeTotal = $this->preparedScalar(
            'SELECT COALESCE(SUM(amount), 0) FROM charges WHERE team_id = :team_id AND fiscal_year = :year AND month_index = :month',
            ['team_id' => $teamId, 'year' => $year, 'month' => $month]
        );
        if ($chargeTotal <= 0) {
            return 0;
        }
        $allocation = $this->allocatedPaymentsForTeam($teamId);
        $paidTotal = (int) ($allocation['by_month'][JalaliDate::normalizeDigits($year) . '-' . $month] ?? 0);

        return max(0, $chargeTotal - $paidTotal);
    }

    private function contractDebtForTeamInYear(int $teamId, string $fiscalYear): int
    {
        return $this->contractDebtBreakdown($teamId, $fiscalYear)['debt_total'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamActionItems(int $teamId): array
    {
        $items = [];
        $pendingPayments = $this->preparedScalar(
            "SELECT COUNT(*) FROM transactions
             WHERE team_id = :id AND category = 'واریز تیم' AND payment_status = 'pending' AND confirmed = 0",
            ['id' => $teamId]
        );
        if ($pendingPayments > 0) {
            $items[] = [
                'type' => 'payment',
                'label' => number_format($pendingPayments) . ' واریز در انتظار تأیید',
                'detail' => 'پیگیری وضعیت اعلام پرداخت',
                'section' => 'payments',
            ];
        }

        $month = $this->currentMonthSummaryForTeam($teamId);
        if ((int) ($month['debt_total'] ?? 0) > 0) {
            $items[] = [
                'type' => 'debt',
                'label' => 'مانده پرداخت ' . ($month['month_name'] ?? ''),
                'detail' => number_format((int) $month['debt_total']) . ' ریال — پس از کسر واریزهای تأییدشده',
                'section' => 'charges',
            ];
        }

        $currentYear = $this->currentFiscalYear();
        $contract = $this->contracts()->contractForYear($teamId, $currentYear);
        $end = $contract ? (string) ($contract['contract_end'] ?? '') : '';
        if ($end !== '') {
            $today = JalaliDate::todayParts()['formatted'];
            if (JalaliDate::compare($end, $today) >= 0) {
                $endYear = (int) substr($end, 0, 4);
                $endMonth = (int) substr($end, 5, 2);
                $todayYear = (int) substr($today, 0, 4);
                $todayMonth = (int) substr($today, 5, 2);
                $monthDiff = ($endYear - $todayYear) * 12 + ($endMonth - $todayMonth);
                if ($monthDiff <= 2) {
                    $items[] = [
                        'type' => 'rate',
                        'label' => 'پایان قرارداد نزدیک',
                        'detail' => 'تاریخ پایان: ' . $end,
                        'section' => 'profile',
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function chargeFiscalYears(): array
    {
        $years = [];
        foreach ($this->rows('SELECT DISTINCT fiscal_year FROM charges WHERE fiscal_year IS NOT NULL AND fiscal_year <> \'\'') as $row) {
            $years[] = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
        }
        foreach ($this->rows('SELECT DISTINCT fiscal_year FROM rate_settings WHERE fiscal_year IS NOT NULL AND fiscal_year <> \'\'') as $row) {
            $years[] = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
        }
        foreach ($this->rows('SELECT DISTINCT fiscal_year FROM team_contracts WHERE fiscal_year IS NOT NULL AND fiscal_year <> \'\'') as $row) {
            $years[] = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
        }
        $years[] = $this->currentFiscalYear();
        $years = array_values(array_unique(array_filter($years)));
        rsort($years, SORT_STRING);

        return $years;
    }

    /**
     * @param array<string, string> $filters
     */
    private function deskAssignmentYearClause(array $filters): string
    {
        $year = JalaliDate::normalizeDigits(trim((string) ($filters['fiscal_year'] ?? '')));
        if ($year === '') {
            return '';
        }

        $yearStart = $this->pdo->quote($year . '/01/01');
        $yearEnd = $this->pdo->quote($year . '/12/29');

        return " WHERE da.assigned_from <= {$yearEnd}
                 AND (da.assigned_until IS NULL OR da.assigned_until = '' OR da.assigned_until >= {$yearStart})";
    }

    /**
     * @param array<string, string> $filters
     */
    private function searchClause(string $name, array $filters, bool $hasWhere = false): string
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q === '') {
            return '';
        }

        $like = '%' . addcslashes($q, '%_\\') . '%';
        $quoted = $this->pdo->quote($like);
        $expr = match ($name) {
            'teams' => "t.name LIKE {$quoted} OR t.leader LIKE {$quoted} OR t.phone LIKE {$quoted} OR t.entity_code LIKE {$quoted} OR COALESCE(u.username, '') LIKE {$quoted}",
            'team_contracts' => "t.name LIKE {$quoted} OR COALESCE(tc.fiscal_year, '') LIKE {$quoted} OR COALESCE(tc.notes, '') LIKE {$quoted}",
            'members' => "m.full_name LIKE {$quoted} OR COALESCE(m.phone, '') LIKE {$quoted} OR COALESCE(m.national_id, '') LIKE {$quoted} OR COALESCE(m.member_code, '') LIKE {$quoted} OR COALESCE(t.name, '') LIKE {$quoted}",
            'desks' => "CAST(d.number AS TEXT) LIKE {$quoted} OR COALESCE(t.name, '') LIKE {$quoted} OR COALESCE(d.notes, '') LIKE {$quoted}",
            'lockers' => "CAST(l.locker_number AS TEXT) LIKE {$quoted} OR COALESCE(t.name, '') LIKE {$quoted} OR COALESCE(l.notes, '') LIKE {$quoted}",
            'charges' => "COALESCE(t.name, '') LIKE {$quoted} OR COALESCE(c.note, '') LIKE {$quoted} OR COALESCE(c.fiscal_year, '') LIKE {$quoted}",
            'transactions' => "COALESCE(t.description, '') LIKE {$quoted} OR COALESCE(t.notes, '') LIKE {$quoted} OR COALESCE(tm.name, '') LIKE {$quoted} OR COALESCE(t.payment_reference, '') LIKE {$quoted}",
            'rate_settings' => "COALESCE(title, '') LIKE {$quoted} OR COALESCE(fiscal_year, '') LIKE {$quoted} OR COALESCE(notes, '') LIKE {$quoted}",
            'panel_users' => "u.username LIKE {$quoted} OR COALESCE(u.full_name, '') LIKE {$quoted} OR COALESCE(t.name, '') LIKE {$quoted}",
            'development_plans' => "COALESCE(p.title, '') LIKE {$quoted} OR COALESCE(p.description, '') LIKE {$quoted} OR COALESCE(p.notes, '') LIKE {$quoted}",
            default => '',
        };
        if ($expr === '') {
            return '';
        }

        return ($hasWhere ? ' AND ' : ' WHERE ') . '(' . $expr . ')';
    }

    private function contractDebtForTeam(int $teamId): int
    {
        return $this->contractDebtBreakdown($teamId)['debt_total'];
    }

    /**
     * @return array{debt_total: int, unpaid_labels: list<string>}
     */
    private function contractDebtBreakdown(int $teamId, ?string $fiscalYear = null): array
    {
        $contracts = $this->contracts();
        $allocation = $this->allocatedPaymentsForTeam($teamId);
        $chargeByMonth = [];
        $sql = 'SELECT fiscal_year, month_index, amount FROM charges WHERE team_id = :id';
        $params = ['id' => $teamId];
        if ($fiscalYear !== null) {
            $sql .= ' AND fiscal_year = :year';
            $params['year'] = JalaliDate::normalizeDigits($fiscalYear);
        }
        $sql .= ' ORDER BY fiscal_year, month_index';
        foreach ($this->preparedRows($sql, $params) as $row) {
            $fy = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
            $mi = (int) ($row['month_index'] ?? 0);
            $dates = $contracts->contractDatesForYear($teamId, $fy);
            if (!JalaliDate::monthInContract($fy, $mi, $dates['start'], $dates['end'])) {
                continue;
            }
            if ($contracts->deskCountForMonth($teamId, $fy, $mi) <= 0) {
                continue;
            }
            $key = $fy . '-' . $mi;
            $chargeByMonth[$key] = ($chargeByMonth[$key] ?? 0) + (int) ($row['amount'] ?? 0);
        }

        $debt = 0;
        $labels = [];
        foreach ($chargeByMonth as $key => $due) {
            $paid = (int) ($allocation['by_month'][$key] ?? 0);
            $remaining = max(0, $due - $paid);
            if ($remaining <= 0) {
                continue;
            }
            $debt += $remaining;
            [$fy, $mi] = explode('-', (string) $key, 2);
            $labels[] = $this->monthName((int) $mi) . ' ' . $fy;
        }

        return ['debt_total' => $debt, 'unpaid_labels' => $labels];
    }

    private function contractChargeTotalForTeam(int $teamId): int
    {
        $contracts = $this->contracts();
        $total = 0;
        foreach ($this->preparedRows(
            'SELECT fiscal_year, month_index, amount FROM charges WHERE team_id = :id',
            ['id' => $teamId]
        ) as $row) {
            $fy = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
            $mi = (int) ($row['month_index'] ?? 0);
            $dates = $contracts->contractDatesForYear($teamId, $fy);
            if (!JalaliDate::monthInContract($fy, $mi, $dates['start'], $dates['end'])) {
                continue;
            }
            if ($contracts->deskCountForMonth($teamId, $fy, $mi) <= 0) {
                continue;
            }
            $total += (int) ($row['amount'] ?? 0);
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
        $contracts = $this->contracts();
        $charges = [];
        foreach ($this->preparedRows(
            'SELECT fiscal_year, month_index, amount FROM charges WHERE team_id = :id ORDER BY fiscal_year, month_index',
            ['id' => $teamId]
        ) as $row) {
            $fy = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
            $mi = (int) ($row['month_index'] ?? 0);
            $dates = $contracts->contractDatesForYear($teamId, $fy);
            if (!JalaliDate::monthInContract($fy, $mi, $dates['start'], $dates['end'])) {
                continue;
            }
            if ($contracts->deskCountForMonth($teamId, $fy, $mi) <= 0) {
                continue;
            }
            $key = $fy . '-' . $mi;
            $charges[$key] = ($charges[$key] ?? 0) + (int) ($row['amount'] ?? 0);
        }

        $byMonth = [];
        foreach (array_keys($charges) as $key) {
            $byMonth[$key] = 0;
        }

        $payments = $this->preparedRows(
            "SELECT amount, payment_plan FROM transactions
             WHERE team_id = :id AND category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1
             ORDER BY COALESCE(reviewed_at, tx_date), id",
            ['id' => $teamId]
        );

        foreach ($payments as $payment) {
            $remaining = (int) ($payment['amount'] ?? 0);
            $plan = $this->decodePaymentPlan($payment['payment_plan'] ?? null);
            if ($plan !== []) {
                foreach ($plan as $item) {
                    $key = JalaliDate::normalizeDigits((string) ($item['fiscal_year'] ?? ''))
                        . '-' . (int) ($item['month_index'] ?? 0);
                    if (!isset($charges[$key])) {
                        continue;
                    }
                    $dueLeft = $charges[$key] - ($byMonth[$key] ?? 0);
                    $planned = (int) ($item['amount'] ?? 0);
                    $alloc = min($remaining, $dueLeft, $planned > 0 ? $planned : $dueLeft);
                    if ($alloc <= 0) {
                        continue;
                    }
                    $byMonth[$key] = ($byMonth[$key] ?? 0) + $alloc;
                    $remaining -= $alloc;
                }
            }
            if ($remaining > 0) {
                foreach ($charges as $key => $due) {
                    $dueLeft = $due - ($byMonth[$key] ?? 0);
                    if ($dueLeft <= 0) {
                        continue;
                    }
                    $alloc = min($remaining, $dueLeft);
                    $byMonth[$key] = ($byMonth[$key] ?? 0) + $alloc;
                    $remaining -= $alloc;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }
        }

        $totalPaid = $this->contractPaidTotalForTeam($teamId);
        $allocated = array_sum($byMonth);

        return ['by_month' => $byMonth, 'remaining' => max(0, $totalPaid - $allocated)];
    }

    /**
     * @return list<array{fiscal_year:string,month_index:int,amount:int}>
     */
    private function decodePaymentPlan(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            $raw = $decoded;
        }
        if (!is_array($raw)) {
            return [];
        }
        $items = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'fiscal_year' => JalaliDate::normalizeDigits((string) ($item['fiscal_year'] ?? '')),
                'month_index' => (int) ($item['month_index'] ?? 0),
                'amount' => (int) ($item['amount'] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @return array{overpayment_total:int}
     */
    private function paymentOverpaymentForTeam(int $teamId): array
    {
        return ['overpayment_total' => $this->allocatedPaymentsForTeam($teamId)['remaining']];
    }

    private function contractPaidAllocatedForTeamInYear(int $teamId, string $fiscalYear): int
    {
        $allocation = $this->allocatedPaymentsForTeam($teamId);
        $sum = 0;
        foreach ($allocation['by_month'] as $key => $amount) {
            if (str_starts_with((string) $key, $fiscalYear . '-')) {
                $sum += (int) $amount;
            }
        }

        return $sum;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamsWithExpiringContracts(int $withinDays = 60): array
    {
        unset($withinDays);
        $today = JalaliDate::todayParts()['formatted'];
        $todayYear = (int) substr($today, 0, 4);
        $todayMonth = (int) substr($today, 5, 2);
        $currentYear = $this->currentFiscalYear();
        $rows = [];
        foreach ($this->rows(
            'SELECT tc.team_id AS id, t.name, tc.contract_end
             FROM team_contracts tc
             INNER JOIN teams t ON t.id = tc.team_id
             WHERE tc.fiscal_year = ' . $this->pdo->quote($currentYear) . '
             ORDER BY tc.contract_end'
        ) as $team) {
            $end = (string) ($team['contract_end'] ?? '');
            if ($end === '' || JalaliDate::compare($end, $today) < 0) {
                continue;
            }
            $endYear = (int) substr($end, 0, 4);
            $endMonth = (int) substr($end, 5, 2);
            $monthDiff = ($endYear - $todayYear) * 12 + ($endMonth - $todayMonth);
            if ($monthDiff <= 2) {
                $rows[] = $team;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $filters
     */
    private function memberApprovalClause(array $filters, bool $forCount = false): string
    {
        $status = (string) ($filters['approval_status'] ?? '');
        $prefix = ' AND ';

        return match ($status) {
            'approved' => $prefix . "m.approval_status = 'approved'",
            'rejected' => $prefix . "m.approval_status = 'rejected'",
            'all' => '',
            default => '',
        };
    }

    /**
     * @param array<string, string> $filters
     */
    private function memberListFilterClause(array $filters, bool $forCount = false): string
    {
        $clauses = [];
        $teamId = (int) ($filters['team_id'] ?? 0);
        if ($teamId > 0) {
            $clauses[] = 'm.team_id = ' . $teamId;
        }
        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $clauses[] = 't.entity_type = ' . $this->pdo->quote($entityType);
        }
        if (($filters['is_leader'] ?? '') !== '') {
            $clauses[] = 'm.is_leader = ' . ((int) $filters['is_leader'] === 1 ? 1 : 0);
        }
        if (($filters['wants_access'] ?? '') !== '') {
            $clauses[] = 'm.wants_access = ' . ((int) $filters['wants_access'] === 1 ? 1 : 0);
        }

        return $clauses === [] ? '' : ' AND ' . implode(' AND ', $clauses);
    }

    /**
     * @param array<string, string> $filters
     */
    private function smsHistoryFilterClause(array $filters, bool $forCount = false): string
    {
        $clauses = [];
        $type = trim((string) ($filters['message_type'] ?? ''));
        if ($type !== '') {
            $clauses[] = 'message_type = ' . $this->pdo->quote($type);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $clauses[] = 'status = ' . $this->pdo->quote($status);
        }
        $teamId = (int) ($filters['team_id'] ?? 0);
        if ($teamId > 0) {
            $clauses[] = 'team_id = ' . $teamId;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $quoted = $this->pdo->quote($like);
            $clauses[] = "(recipient_name LIKE {$quoted} OR phone LIKE {$quoted} OR team_name LIKE {$quoted} OR message_text LIKE {$quoted})";
        }

        return $clauses === [] ? '' : ' AND ' . implode(' AND ', $clauses);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function debtorTeamsForSms(): array
    {
        $rows = [];
        foreach ($this->rows('SELECT id, name FROM teams ORDER BY name') as $team) {
            $teamId = (int) ($team['id'] ?? 0);
            $breakdown = $this->contractDebtBreakdown($teamId);
            if ($breakdown['debt_total'] <= 0) {
                continue;
            }
            $rows[] = [
                'team_id' => $teamId,
                'team_name' => (string) ($team['name'] ?? ''),
                'debt_total' => $breakdown['debt_total'],
                'debt_summary' => implode('، ', array_slice($breakdown['unpaid_labels'], 0, 6)),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['debt_total'] ?? 0) <=> ($a['debt_total'] ?? 0));

        return $rows;
    }

    /**
     * @param array<string, string> $filters
     */
    private function paymentHistoryStatusSql(array $filters, bool $forCount = false, string $alias = ''): string
    {
        $status = (string) ($filters['payment_status'] ?? '');
        $prefix = ' AND ';
        $column = ($alias !== '' ? $alias . '.' : '') . 'payment_status';

        return match ($status) {
            'approved', 'rejected', 'pending' => $prefix . $column . ' = ' . $this->pdo->quote($status),
            default => $prefix . $column . " IN ('approved', 'rejected')",
        };
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function paymentAllocationByTeamMonth(): array
    {
        $map = [];
        foreach ($this->pdo->query('SELECT id, contract_start, contract_end FROM teams')->fetchAll() as $team) {
            $teamId = (int) $team['id'];
            $allocation = $this->allocatedPaymentsForTeam($teamId);
            $map[$teamId] = $allocation['by_month'];
        }

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

    private function incomeTeamDepositsForPeriod(string $year, ?int $month = null): int
    {
        if ($month !== null) {
            return $this->preparedScalar(
                "SELECT COALESCE(SUM(amount), 0) FROM transactions
                 WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1
                 AND fiscal_year = :year AND month_index = :month",
                ['year' => $year, 'month' => $month]
            );
        }

        return $this->preparedScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE category = 'واریز تیم' AND payment_status = 'approved' AND confirmed = 1
             AND fiscal_year = :year",
            ['year' => $year]
        );
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
                (category = 'واریز تیم' AND payment_status = 'approved' AND fiscal_year = :year)
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
