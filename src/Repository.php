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
                'members' => $this->scalar('SELECT COUNT(*) FROM members'),
                'teams' => $this->scalar('SELECT COUNT(*) FROM teams'),
                'desks_occupied' => $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NOT NULL'),
                'desks_total' => 24,
                'lockers' => $this->scalar('SELECT COUNT(*) FROM lockers'),
                'available_lockers' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'"),
                'charge_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM charges'),
                'income_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0'),
                'expense_total' => $this->scalar('SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE amount < 0'),
                'debt_total' => $this->scalar($this->debtSql()),
                'paid_total' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE category = 'واریز تیم' AND confirmed = 1"),
            ],
            'locker_status' => $this->rows('SELECT status, COUNT(*) AS count FROM lockers GROUP BY status ORDER BY count DESC'),
            'monthly_charges' => $this->rows(
                'SELECT fiscal_year, month_index, month_name, SUM(amount) AS amount
                 FROM charges GROUP BY fiscal_year, month_index, month_name
                 ORDER BY fiscal_year, month_index'
            ),
            'finance_by_category' => $this->rows(
                'SELECT category, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount
                 FROM transactions GROUP BY category ORDER BY amount DESC'
            ),
            'debt_by_team' => $this->rows(
                "SELECT t.id AS team_id, t.name AS team_name, COALESCE(SUM(c.amount), 0) - COALESCE(p.paid, 0) AS debt
                 FROM teams t
                 LEFT JOIN charges c ON c.team_id = t.id
                 LEFT JOIN (
                    SELECT team_id, COALESCE(SUM(amount), 0) AS paid
                    FROM transactions
                    WHERE category = 'واریز تیم' AND confirmed = 1
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
                 WHERE tx_date IS NOT NULL AND tx_date <> ''
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
        $paidTotal = $this->preparedScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE category = 'واریز تیم' AND confirmed = 1 AND fiscal_year = :year AND month_index = :month",
            ['year' => $year, 'month' => $month]
        );

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
                        WHERE category = 'واریز تیم' AND confirmed = 1
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
                'type' => 'start',
                'label' => 'اولین نهاد را ثبت کنید',
                'detail' => 'تیم، شرکت یا دانشجوی مستقل',
                'section' => 'teams',
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
        foreach ($debtors as $row) {
            $items[] = [
                'type' => 'debt',
                'label' => (string) $row['team_name'],
                'detail' => 'مانده طلب ' . $today['month_name'] . ' به مرکز: ' . number_format((int) $row['debt']) . ' ریال',
                'section' => 'charges',
                'team_id' => (int) $row['team_id'],
            ];
        }

        $emptyLockers = $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'");
        if ($emptyLockers > 0) {
            $items[] = [
                'type' => 'locker',
                'label' => number_format($emptyLockers) . ' کمد خالی',
                'detail' => 'آماده تخصیص',
                'section' => 'lockers',
            ];
        }

        $freeDesks = $this->scalar('SELECT COUNT(*) FROM desks WHERE team_id IS NULL');
        if ($freeDesks > 0) {
            $items[] = [
                'type' => 'desk',
                'label' => number_format($freeDesks) . ' میز آزاد',
                'detail' => 'از ۲۴ میز',
                'section' => 'desks',
            ];
        }

        $hasRate = (int) $this->preparedScalar(
            'SELECT COUNT(*) FROM rate_settings WHERE fiscal_year = :year',
            ['year' => $year]
        ) > 0;
        if (!$hasRate) {
            $items[] = [
                'type' => 'rate',
                'label' => 'نرخ سال ' . $year . ' تنظیم نشده',
                'detail' => 'ابتدا نرخ شارژ را در بخش شارژ تعریف کنید',
                'section' => 'charges',
            ];
        }

        if ($this->scalar('SELECT COUNT(*) FROM lockers') === 0) {
            $items[] = [
                'type' => 'locker',
                'label' => 'هنوز کمدی ثبت نشده',
                'detail' => 'شماره کمدها را خودتان اضافه کنید',
                'section' => 'lockers',
            ];
        }

        return $items;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginatedResource(string $name, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $sql = $this->resourceSql($name);
        $total = $this->resourceCount($name);
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

    private function resourceCount(string $name): int
    {
        $teamId = Access::scopedTeamId();
        if ($teamId !== null) {
            return match ($name) {
                'teams' => 1,
                'members' => $this->preparedScalar('SELECT COUNT(*) FROM members WHERE team_id = :id', ['id' => $teamId]),
                'desks' => $this->preparedScalar('SELECT COUNT(*) FROM desks WHERE team_id = :id', ['id' => $teamId]),
                'lockers' => $this->preparedScalar('SELECT COUNT(*) FROM lockers WHERE team_id = :id', ['id' => $teamId]),
                'charges' => $this->preparedScalar('SELECT COUNT(*) FROM charges WHERE team_id = :id', ['id' => $teamId]),
                default => 0,
            };
        }

        $sql = match ($name) {
            'teams' => 'SELECT COUNT(*) FROM teams',
            'members' => 'SELECT COUNT(*) FROM members',
            'desks' => 'SELECT COUNT(*) FROM desks',
            'lockers' => 'SELECT COUNT(*) FROM lockers',
            'charges' => 'SELECT COUNT(*) FROM charges',
            'transactions' => 'SELECT COUNT(*) FROM transactions',
            'rate_settings' => 'SELECT COUNT(*) FROM rate_settings',
            'panel_users' => 'SELECT COUNT(*) FROM panel_users',
            default => throw new InvalidArgumentException('Unknown resource.'),
        };

        return (int) $this->pdo->query($sql)->fetchColumn();
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

    private function resourceSql(string $name): string
    {
        $teamId = Access::scopedTeamId();

        return match ($name) {
            'teams' => "SELECT t.id, t.entity_code, t.entity_type, t.name, t.leader, t.phone, t.joined_at, t.warning, t.notes,
                        (SELECT COUNT(*) FROM desks d WHERE d.team_id = t.id) AS desk_count,
                        (SELECT COALESCE(SUM(d.informal_seats), 0) FROM desks d WHERE d.team_id = t.id) AS informal_seats
                 FROM teams t"
                . ($teamId !== null ? " WHERE t.id = {$teamId}" : '')
                . ' ORDER BY t.entity_type, t.name',
            'members' => "SELECT m.id, m.member_code, m.team_id, m.access_code, m.full_name, m.phone, m.national_id, m.notes,
                        t.name AS team_label, t.entity_type,
                        (SELECT GROUP_CONCAT(d.number ORDER BY d.number)
                         FROM desks d WHERE d.team_id = m.team_id) AS desk_numbers
                 FROM members m
                 LEFT JOIN teams t ON t.id = m.team_id"
                . ($teamId !== null ? " WHERE m.team_id = {$teamId}" : '')
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
                        t.fiscal_year, t.month_index, t.confirmed, t.notes,
                        tm.name AS team_name
                 FROM transactions t
                 LEFT JOIN teams tm ON tm.id = t.team_id
                 ORDER BY t.tx_date DESC, t.id DESC",
            'rate_settings' => 'SELECT id, fiscal_year, title, charge_rate, informal_rent_rate, effective_from, notes
                 FROM rate_settings ORDER BY fiscal_year, effective_from, id',
            'panel_users' => 'SELECT u.id, u.username, u.role, u.team_id, u.full_name, u.is_active, t.name AS team_label
                 FROM panel_users u
                 LEFT JOIN teams t ON t.id = u.team_id
                 ORDER BY u.username',
            default => throw new InvalidArgumentException('Unknown resource.'),
        };
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
                'members' => count($profile['members']),
                'desks' => count($profile['desks']),
                'lockers' => count($profile['lockers']),
                'charge_total' => (int) ($profile['summary']['charge_total'] ?? 0),
                'paid_total' => (int) ($profile['summary']['paid_total'] ?? 0),
                'debt_total' => (int) ($profile['summary']['debt_total'] ?? 0),
            ],
            'current_month' => $this->currentMonthSummaryForTeam($teamId),
            'monthly_charges' => $this->preparedRows(
                'SELECT fiscal_year, month_index, month_name, amount
                 FROM charges WHERE team_id = :team_id
                 ORDER BY fiscal_year, month_index',
                ['team_id' => $teamId]
            ),
            'action_items' => [],
        ];
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
        $paidTotal = $this->preparedScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions
             WHERE team_id = :team_id AND category = 'واریز تیم' AND confirmed = 1 AND fiscal_year = :year AND month_index = :month",
            ['team_id' => $teamId, 'year' => $year, 'month' => $month]
        );

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
                'SELECT id, entity_code, entity_type, name FROM teams WHERE id = :id',
                ['id' => $teamId]
            );
        } else {
            $teams = $this->preparedRows(
                'SELECT id, entity_code, entity_type, name FROM teams ORDER BY entity_type, name'
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
        $payments = $this->preparedRows(
            "SELECT team_id, month_index, SUM(amount) AS paid
             FROM transactions
             WHERE category = 'واریز تیم' AND confirmed = 1 AND fiscal_year = :year"
            . ($teamId !== null ? ' AND team_id = :team_id' : '')
            . ' GROUP BY team_id, month_index',
            $teamId !== null ? ['year' => $fiscalYear, 'team_id' => $teamId] : ['year' => $fiscalYear]
        );

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
        $paymentMap = [];
        foreach ($payments as $row) {
            $paymentMap[$row['team_id']][$row['month_index']] = (int) $row['paid'];
        }

        $rows = [];
        foreach ($teams as $team) {
            $cells = [];
            foreach ($months as $month) {
                $idx = (int) $month['index'];
                $due = $chargeMap[$team['id']][$idx] ?? null;
                $paid = $paymentMap[$team['id']][$idx] ?? 0;
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
        return $this->rows(
            "SELECT t.name AS team_name, c.fiscal_year, c.month_name,
                    c.charge_amount, c.rent_amount, c.amount AS amount_due,
                    COALESCE(p.paid, 0) AS amount_paid,
                    CASE WHEN COALESCE(p.paid, 0) >= c.amount THEN 'پرداخت‌شده'
                         WHEN COALESCE(p.paid, 0) > 0 THEN 'ناقص' ELSE 'بدهکار به مرکز' END AS status
             FROM charges c
             JOIN teams t ON t.id = c.team_id
             LEFT JOIN (
                SELECT team_id, fiscal_year, month_index, SUM(amount) AS paid
                FROM transactions WHERE category = 'واریز تیم' AND confirmed = 1
                GROUP BY team_id, fiscal_year, month_index
             ) p ON p.team_id = c.team_id AND p.fiscal_year = c.fiscal_year AND p.month_index = c.month_index
             ORDER BY c.fiscal_year, t.name, c.month_index"
        );
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
                'SELECT m.id, m.member_code, m.full_name, m.access_code, m.phone, m.national_id, m.notes
                 FROM members m WHERE m.team_id = :id ORDER BY m.full_name',
                ['id' => $teamId]
            ),
            'lockers' => $this->preparedRows(
                'SELECT l.id, l.locker_number, l.status, l.delivered_at, l.key_number, l.spare_key, l.notes
                 FROM lockers l WHERE l.team_id = :id ORDER BY l.locker_number',
                ['id' => $teamId]
            ),
            'charges' => $this->preparedRows('SELECT * FROM charges WHERE team_id = :id ORDER BY fiscal_year, month_index', ['id' => $teamId]),
            'payments' => $this->preparedRows(
                "SELECT * FROM transactions
                 WHERE team_id = :id AND category = 'واریز تیم'
                 ORDER BY fiscal_year, month_index, tx_date",
                ['id' => $teamId]
            ),
            'summary' => [
                'charge_total' => $this->preparedScalar('SELECT COALESCE(SUM(amount), 0) FROM charges WHERE team_id = :id', ['id' => $teamId]),
                'paid_total' => $this->preparedScalar("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE team_id = :id AND category = 'واریز تیم' AND confirmed = 1", ['id' => $teamId]),
                'debt_total' => $this->preparedScalar(
                    'SELECT COALESCE((SELECT SUM(amount) FROM charges WHERE team_id = :id), 0)
                          - COALESCE((SELECT SUM(amount) FROM transactions WHERE team_id = :id2 AND category = \'واریز تیم\' AND confirmed = 1), 0)',
                    ['id' => $teamId, 'id2' => $teamId]
                ),
            ],
        ];
    }

    private function debtSql(): string
    {
        return 'SELECT COALESCE((SELECT SUM(amount) FROM charges), 0)
                     - COALESCE((SELECT SUM(amount) FROM transactions WHERE category = \'واریز تیم\' AND confirmed = 1), 0)';
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
