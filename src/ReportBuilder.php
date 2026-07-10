<?php

declare(strict_types=1);

final class ReportBuilder
{
    public const TYPE_OVERVIEW = 'overview';
    public const TYPE_FINANCE = 'finance';
    public const TYPE_CHARGES = 'charges';
    public const TYPE_DEBTS = 'debts';
    public const TYPE_TEAMS = 'teams';
    public const TYPE_MEMBERS = 'members';
    public const TYPE_DESKS = 'desks';
    public const TYPE_LOCKERS = 'lockers';
    public const TYPE_TRANSACTIONS = 'transactions';
    public const TYPE_FULL = 'full';

    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_QUARTERLY = 'quarterly';
    public const PERIOD_ANNUAL = 'annual';
    public const PERIOD_CUSTOM = 'custom';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $today = JalaliDate::todayParts();
        $years = (new Repository($this->pdo))->chargeFiscalYears();
        if ($years === []) {
            $years = [(string) $today['year']];
        }
        if (!in_array((string) $today['year'], $years, true)) {
            array_unshift($years, (string) $today['year']);
        }

        $teams = [];
        foreach ($this->pdo->query('SELECT id, name, entity_type FROM teams ORDER BY name')->fetchAll() as $row) {
            $teams[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'entity_type' => (string) ($row['entity_type'] ?? 'team'),
            ];
        }

        return [
            'types' => [
                ['id' => self::TYPE_OVERVIEW, 'label' => 'خلاصه مدیریتی', 'description' => 'شاخص‌های کلیدی مرکز در بازه انتخابی', 'supports_period' => true],
                ['id' => self::TYPE_FINANCE, 'label' => 'گزارش مالی', 'description' => 'درآمد، هزینه، واریز نهادها و موجودی نقد', 'supports_period' => true],
                ['id' => self::TYPE_CHARGES, 'label' => 'شارژ ماهانه', 'description' => 'شارژهای ثبت‌شده به تفکیک نهاد و ماه', 'supports_period' => true],
                ['id' => self::TYPE_DEBTS, 'label' => 'مطالبات و بدهی‌ها', 'description' => 'وضعیت پرداخت هر نهاد در بازه', 'supports_period' => true],
                ['id' => self::TYPE_TRANSACTIONS, 'label' => 'گردش تراکنش‌ها', 'description' => 'لیست تراکنش‌های تأییدشده', 'supports_period' => true],
                ['id' => self::TYPE_TEAMS, 'label' => 'نهادها', 'description' => 'فهرست تیم‌ها، شرکت‌ها و دانشجویان', 'supports_period' => false],
                ['id' => self::TYPE_MEMBERS, 'label' => 'اعضا', 'description' => 'فهرست اعضای تأییدشده', 'supports_period' => false],
                ['id' => self::TYPE_DESKS, 'label' => 'میزها', 'description' => 'وضعیت ۲۴ میز و تخصیص فعلی', 'supports_period' => false],
                ['id' => self::TYPE_LOCKERS, 'label' => 'کمدها', 'description' => 'وضعیت و تخصیص کمدها', 'supports_period' => false],
                ['id' => self::TYPE_FULL, 'label' => 'گزارش جامع', 'description' => 'همه بخش‌ها در یک سند', 'supports_period' => true],
            ],
            'periods' => [
                ['id' => self::PERIOD_MONTHLY, 'label' => 'ماهانه'],
                ['id' => self::PERIOD_QUARTERLY, 'label' => 'سه‌ماهه'],
                ['id' => self::PERIOD_ANNUAL, 'label' => 'سالانه'],
                ['id' => self::PERIOD_CUSTOM, 'label' => 'بازه سفارشی'],
            ],
            'quarters' => [
                ['id' => 1, 'label' => 'بهار (فروردین تا خرداد)', 'months' => [1, 2, 3]],
                ['id' => 2, 'label' => 'تابستان (تیر تا شهریور)', 'months' => [4, 5, 6]],
                ['id' => 3, 'label' => 'پاییز (مهر تا آذر)', 'months' => [7, 8, 9]],
                ['id' => 4, 'label' => 'زمستان (دی تا اسفند)', 'months' => [10, 11, 12]],
            ],
            'months' => array_map(
                static fn (int $index): array => ['id' => $index, 'label' => JalaliDate::monthName($index)],
                range(1, 12)
            ),
            'fiscal_years' => $years,
            'teams' => $teams,
            'defaults' => [
                'type' => self::TYPE_FINANCE,
                'period' => self::PERIOD_MONTHLY,
                'fiscal_year' => (string) $today['year'],
                'month' => (int) $today['month'],
                'quarter' => (int) ceil(((int) $today['month']) / 3),
                'team_id' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $period = $this->resolvePeriod($normalized);
        $type = $normalized['type'];
        $repo = new Repository($this->pdo);
        $today = JalaliDate::todayParts();

        $sections = match ($type) {
            self::TYPE_OVERVIEW => ['kpis', 'finance_summary', 'debts'],
            self::TYPE_FINANCE => ['kpis', 'finance_summary', 'monthly_breakdown', 'transactions'],
            self::TYPE_CHARGES => ['kpis', 'charges'],
            self::TYPE_DEBTS => ['kpis', 'debts'],
            self::TYPE_TRANSACTIONS => ['kpis', 'transactions'],
            self::TYPE_TEAMS => ['teams'],
            self::TYPE_MEMBERS => ['members'],
            self::TYPE_DESKS => ['desks'],
            self::TYPE_LOCKERS => ['lockers'],
            self::TYPE_FULL => ['kpis', 'finance_summary', 'monthly_breakdown', 'debts', 'charges', 'transactions', 'teams', 'members', 'desks', 'lockers'],
            default => throw new InvalidArgumentException('نوع گزارش معتبر نیست.'),
        };

        $data = [
            'meta' => [
                'title' => $this->typeLabel($type),
                'subtitle' => 'مرکز نوآوری مکانیک — Mechinno',
                'type' => $type,
                'type_label' => $this->typeLabel($type),
                'period' => $normalized['period'],
                'period_label' => $this->periodLabel($normalized['period']),
                'period_title' => $period['label'],
                'fiscal_year' => $period['fiscal_year'],
                'month_from' => $period['month_from'],
                'month_to' => $period['month_to'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
                'team_id' => $normalized['team_id'],
                'team_name' => $this->teamName($normalized['team_id']),
                'generated_at' => $today['formatted'],
                'generated_time' => date('H:i'),
                'sections' => $sections,
            ],
            'filters' => $normalized,
            'period' => $period,
        ];

        if (in_array('kpis', $sections, true) || in_array('finance_summary', $sections, true)) {
            $finance = $this->financeTotals($period, $normalized['team_id']);
            $data['kpis'] = $this->buildKpis($finance, $period, $normalized['team_id']);
            $data['finance_summary'] = $finance;
        }
        if (in_array('monthly_breakdown', $sections, true)) {
            $data['monthly_breakdown'] = $this->monthlyBreakdown($period, $normalized['team_id']);
        }
        if (in_array('charges', $sections, true)) {
            $data['charges'] = $this->chargesInPeriod($period, $normalized['team_id']);
        }
        if (in_array('debts', $sections, true)) {
            $data['debts'] = $this->debtsInPeriod($period, $normalized['team_id']);
        }
        if (in_array('transactions', $sections, true)) {
            $data['transactions'] = $this->transactionsInPeriod($period, $normalized['team_id']);
        }
        if (in_array('teams', $sections, true)) {
            $teams = $repo->resource('teams');
            if ($normalized['team_id'] > 0) {
                $teams = array_values(array_filter(
                    $teams,
                    static fn (array $row): bool => (int) ($row['id'] ?? 0) === $normalized['team_id']
                ));
            }
            $data['teams'] = $teams;
        }
        if (in_array('members', $sections, true)) {
            $members = $repo->resource('members');
            if ($normalized['team_id'] > 0) {
                $members = array_values(array_filter(
                    $members,
                    static fn (array $row): bool => (int) ($row['team_id'] ?? 0) === $normalized['team_id']
                ));
            }
            $data['members'] = $members;
        }
        if (in_array('desks', $sections, true)) {
            $desks = $repo->resource('desks');
            if ($normalized['team_id'] > 0) {
                $desks = array_values(array_filter(
                    $desks,
                    static fn (array $row): bool => (int) ($row['team_id'] ?? 0) === $normalized['team_id']
                ));
            }
            $data['desks'] = $desks;
        }
        if (in_array('lockers', $sections, true)) {
            $lockers = $repo->resource('lockers');
            if ($normalized['team_id'] > 0) {
                $lockers = array_values(array_filter(
                    $lockers,
                    static fn (array $row): bool => (int) ($row['team_id'] ?? 0) === $normalized['team_id']
                ));
            }
            $data['lockers'] = $lockers;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $catalog = $this->catalog();
        $defaults = $catalog['defaults'];
        $type = (string) ($filters['type'] ?? $defaults['type']);
        $validTypes = array_column($catalog['types'], 'id');
        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException('نوع گزارش معتبر نیست.');
        }

        $period = (string) ($filters['period'] ?? $defaults['period']);
        $validPeriods = array_column($catalog['periods'], 'id');
        if (!in_array($period, $validPeriods, true)) {
            throw new InvalidArgumentException('بازه زمانی معتبر نیست.');
        }

        $supportsPeriod = true;
        foreach ($catalog['types'] as $item) {
            if ($item['id'] === $type) {
                $supportsPeriod = (bool) ($item['supports_period'] ?? false);
                break;
            }
        }
        if (!$supportsPeriod) {
            $period = self::PERIOD_ANNUAL;
        }

        $fiscalYear = JalaliDate::normalizeDigits((string) ($filters['fiscal_year'] ?? $defaults['fiscal_year']));
        if ($fiscalYear === '' || !preg_match('/^\d{4}$/', $fiscalYear)) {
            throw new InvalidArgumentException('سال مالی معتبر نیست.');
        }

        $month = (int) ($filters['month'] ?? $defaults['month']);
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('ماه معتبر نیست.');
        }

        $quarter = (int) ($filters['quarter'] ?? $defaults['quarter']);
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('فصل معتبر نیست.');
        }

        $monthFrom = (int) ($filters['month_from'] ?? 1);
        $monthTo = (int) ($filters['month_to'] ?? 12);
        if ($period === self::PERIOD_CUSTOM) {
            if ($monthFrom < 1 || $monthFrom > 12 || $monthTo < 1 || $monthTo > 12 || $monthFrom > $monthTo) {
                throw new InvalidArgumentException('بازه ماه سفارشی معتبر نیست.');
            }
        }

        $teamId = (int) ($filters['team_id'] ?? 0);
        if ($teamId < 0) {
            $teamId = 0;
        }

        return [
            'type' => $type,
            'period' => $period,
            'fiscal_year' => $fiscalYear,
            'month' => $month,
            'quarter' => $quarter,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'team_id' => $teamId,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   fiscal_year:string,
     *   month_from:int,
     *   month_to:int,
     *   date_from:string,
     *   date_to:string,
     *   label:string,
     *   months:list<int>
     * }
     */
    public function resolvePeriod(array $filters): array
    {
        $year = (string) $filters['fiscal_year'];
        $period = (string) $filters['period'];

        [$from, $to, $label] = match ($period) {
            self::PERIOD_MONTHLY => [
                (int) $filters['month'],
                (int) $filters['month'],
                JalaliDate::monthName((int) $filters['month']) . ' ' . $year,
            ],
            self::PERIOD_QUARTERLY => (static function () use ($filters, $year): array {
                $quarter = (int) $filters['quarter'];
                $from = (($quarter - 1) * 3) + 1;
                $to = $from + 2;
                $names = [
                    1 => 'بهار',
                    2 => 'تابستان',
                    3 => 'پاییز',
                    4 => 'زمستان',
                ];

                return [
                    $from,
                    $to,
                    ($names[$quarter] ?? 'فصل') . ' ' . $year
                        . ' (' . JalaliDate::monthName($from) . ' تا ' . JalaliDate::monthName($to) . ')',
                ];
            })(),
            self::PERIOD_CUSTOM => [
                (int) $filters['month_from'],
                (int) $filters['month_to'],
                'از ' . JalaliDate::monthName((int) $filters['month_from'])
                    . ' تا ' . JalaliDate::monthName((int) $filters['month_to']) . ' ' . $year,
            ],
            default => [1, 12, 'سال مالی ' . $year],
        };

        $months = range($from, $to);

        return [
            'fiscal_year' => $year,
            'month_from' => $from,
            'month_to' => $to,
            'date_from' => JalaliDate::monthStart($year, $from),
            'date_to' => JalaliDate::monthEnd($year, $to),
            'label' => $label,
            'months' => $months,
        ];
    }

    /**
     * @param array{fiscal_year:string,month_from:int,month_to:int,date_from:string,date_to:string,months:list<int>} $period
     * @return array<string, int>
     */
    private function financeTotals(array $period, int $teamId): array
    {
        $deposits = $this->sumTransactions($period, ['واریز تیم'], $teamId, true);
        $income = $this->sumTransactions($period, ['درآمد'], 0, false);
        $expense = abs($this->sumTransactions($period, ['هزینه'], 0, false));
        $chargeTotal = $this->sumCharges($period, $teamId);
        $debtRows = $this->debtsInPeriod($period, $teamId);
        $debtTotal = 0;
        $paidAllocated = 0;
        foreach ($debtRows as $row) {
            $debtTotal += max(0, (int) ($row['amount_due'] ?? 0) - (int) ($row['amount_paid'] ?? 0));
            $paidAllocated += (int) ($row['amount_paid'] ?? 0);
        }

        return [
            'deposits' => $deposits,
            'manual_income' => $income,
            'income_total' => $deposits + $income,
            'expense_total' => $expense,
            'net' => $deposits + $income - $expense,
            'charge_total' => $chargeTotal,
            'paid_allocated' => $paidAllocated,
            'debt_total' => $debtTotal,
            'transaction_count' => $this->countTransactions($period, $teamId),
        ];
    }

    /**
     * @param array<string, int> $finance
     * @param array{label:string,fiscal_year:string} $period
     * @return list<array{label:string,value:int|string,tone?:string}>
     */
    private function buildKpis(array $finance, array $period, int $teamId): array
    {
        $kpis = [
            ['label' => 'بازه گزارش', 'value' => $period['label']],
            ['label' => 'درآمد کل (واریز+دستی)', 'value' => $finance['income_total'], 'tone' => 'success'],
            ['label' => 'واریز نهادها', 'value' => $finance['deposits']],
            ['label' => 'درآمد دستی', 'value' => $finance['manual_income']],
            ['label' => 'هزینه‌ها', 'value' => $finance['expense_total'], 'tone' => 'danger'],
            ['label' => 'خالص نقدی بازه', 'value' => $finance['net'], 'tone' => $finance['net'] < 0 ? 'danger' : 'success'],
            ['label' => 'جمع شارژ بازه', 'value' => $finance['charge_total']],
            ['label' => 'واریز تخصیص‌یافته', 'value' => $finance['paid_allocated']],
            ['label' => 'مانده طلب بازه', 'value' => $finance['debt_total'], 'tone' => 'danger'],
            ['label' => 'تعداد تراکنش', 'value' => $finance['transaction_count']],
        ];
        if ($teamId > 0) {
            array_unshift($kpis, ['label' => 'نهاد', 'value' => $this->teamName($teamId)]);
        }

        return $kpis;
    }

    /**
     * @param array{fiscal_year:string,months:list<int>,date_from:string,date_to:string} $period
     * @return list<array<string, mixed>>
     */
    private function monthlyBreakdown(array $period, int $teamId): array
    {
        $rows = [];
        foreach ($period['months'] as $month) {
            $slice = [
                'fiscal_year' => $period['fiscal_year'],
                'month_from' => $month,
                'month_to' => $month,
                'months' => [$month],
                'date_from' => JalaliDate::monthStart($period['fiscal_year'], $month),
                'date_to' => JalaliDate::monthEnd($period['fiscal_year'], $month),
            ];
            $totals = $this->financeTotals($slice, $teamId);
            $rows[] = [
                'month_index' => $month,
                'month_name' => JalaliDate::monthName($month),
                'fiscal_year' => $period['fiscal_year'],
                'deposits' => $totals['deposits'],
                'manual_income' => $totals['manual_income'],
                'income_total' => $totals['income_total'],
                'expense_total' => $totals['expense_total'],
                'net' => $totals['net'],
                'charge_total' => $totals['charge_total'],
                'debt_total' => $totals['debt_total'],
            ];
        }

        return $rows;
    }

    /**
     * @param array{fiscal_year:string,months:list<int>} $period
     * @return list<array<string, mixed>>
     */
    private function chargesInPeriod(array $period, int $teamId): array
    {
        $placeholders = implode(',', array_fill(0, count($period['months']), '?'));
        $sql = "SELECT c.fiscal_year, c.month_index, c.month_name, c.charge_amount, c.rent_amount, c.amount,
                       c.note, c.source_file, t.name AS team_name, t.id AS team_id
                FROM charges c
                LEFT JOIN teams t ON t.id = c.team_id
                WHERE c.fiscal_year = ?
                  AND c.month_index IN ({$placeholders})";
        $params = array_merge([$period['fiscal_year']], $period['months']);
        if ($teamId > 0) {
            $sql .= ' AND c.team_id = ?';
            $params[] = $teamId;
        }
        $sql .= ' ORDER BY c.month_index, t.name';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(static function (array $row): array {
            return [
                'team_id' => (int) ($row['team_id'] ?? 0),
                'team_name' => (string) ($row['team_name'] ?? ''),
                'fiscal_year' => (string) ($row['fiscal_year'] ?? ''),
                'month_index' => (int) ($row['month_index'] ?? 0),
                'month_name' => (string) ($row['month_name'] ?? JalaliDate::monthName((int) ($row['month_index'] ?? 0))),
                'charge_amount' => (int) ($row['charge_amount'] ?? 0),
                'rent_amount' => (int) ($row['rent_amount'] ?? 0),
                'amount' => (int) ($row['amount'] ?? 0),
                'note' => (string) ($row['note'] ?? ''),
                'source_file' => (string) ($row['source_file'] ?? ''),
            ];
        }, $statement->fetchAll());
    }

    /**
     * @param array{fiscal_year:string,months:list<int>} $period
     * @return list<array<string, mixed>>
     */
    private function debtsInPeriod(array $period, int $teamId): array
    {
        $months = array_fill_keys($period['months'], true);
        $year = $period['fiscal_year'];
        $rows = [];
        foreach ((new Repository($this->pdo))->chargeDebtRows() as $row) {
            if ((string) ($row['fiscal_year'] ?? '') !== $year) {
                continue;
            }
            $monthIndex = (int) ($row['month_index'] ?? 0);
            if ($monthIndex <= 0 || !isset($months[$monthIndex])) {
                continue;
            }
            if ($teamId > 0 && (int) ($row['team_id'] ?? 0) !== $teamId) {
                continue;
            }
            $row['amount_remaining'] = max(0, (int) ($row['amount_due'] ?? 0) - (int) ($row['amount_paid'] ?? 0));
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array{date_from:string,date_to:string,fiscal_year:string,months:list<int>} $period
     * @return list<array<string, mixed>>
     */
    private function transactionsInPeriod(array $period, int $teamId): array
    {
        $sql = "SELECT t.id, t.tx_date, t.description, t.amount, t.category, t.finance_subtype,
                       t.fiscal_year, t.month_index, t.payment_status, t.confirmed, t.notes,
                       tm.name AS team_name, tm.id AS team_id
                FROM transactions t
                LEFT JOIN teams tm ON tm.id = t.team_id
                WHERE t.confirmed = 1
                  AND (
                    (t.category = 'واریز تیم' AND t.payment_status = 'approved')
                    OR t.category IN ('درآمد', 'هزینه')
                  )
                  AND t.tx_date >= :date_from AND t.tx_date <= :date_to";
        $params = [
            'date_from' => $period['date_from'],
            'date_to' => $period['date_to'],
        ];
        if ($teamId > 0) {
            $sql .= ' AND t.team_id = :team_id';
            $params['team_id'] = $teamId;
        }
        $sql .= ' ORDER BY t.tx_date ASC, t.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(static function (array $row): array {
            $monthIndex = (int) ($row['month_index'] ?? 0);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'tx_date' => (string) ($row['tx_date'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'amount' => (int) ($row['amount'] ?? 0),
                'category' => (string) ($row['category'] ?? ''),
                'category_label' => CenterLedger::categoryLabel((string) ($row['category'] ?? '')),
                'finance_subtype' => (string) ($row['finance_subtype'] ?? ''),
                'team_id' => (int) ($row['team_id'] ?? 0),
                'team_name' => (string) ($row['team_name'] ?? ''),
                'fiscal_year' => (string) ($row['fiscal_year'] ?? ''),
                'month_index' => $monthIndex,
                'month_name' => JalaliDate::monthName($monthIndex),
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }, $statement->fetchAll());
    }

    /**
     * @param array{date_from:string,date_to:string,fiscal_year:string,months:list<int>} $period
     * @param list<string> $categories
     */
    private function sumTransactions(array $period, array $categories, int $teamId, bool $approvedDepositsOnly): int
    {
        if ($categories === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM transactions
                WHERE confirmed = 1
                  AND category IN ({$placeholders})
                  AND tx_date >= ? AND tx_date <= ?";
        $params = array_merge($categories, [$period['date_from'], $period['date_to']]);
        if ($approvedDepositsOnly) {
            $sql .= " AND payment_status = 'approved'";
        }
        if ($teamId > 0) {
            $sql .= ' AND team_id = ?';
            $params[] = $teamId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{date_from:string,date_to:string} $period
     */
    private function countTransactions(array $period, int $teamId): int
    {
        $sql = "SELECT COUNT(*) FROM transactions
                WHERE confirmed = 1
                  AND (
                    (category = 'واریز تیم' AND payment_status = 'approved')
                    OR category IN ('درآمد', 'هزینه')
                  )
                  AND tx_date >= ? AND tx_date <= ?";
        $params = [$period['date_from'], $period['date_to']];
        if ($teamId > 0) {
            $sql .= ' AND team_id = ?';
            $params[] = $teamId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{fiscal_year:string,months:list<int>} $period
     */
    private function sumCharges(array $period, int $teamId): int
    {
        $placeholders = implode(',', array_fill(0, count($period['months']), '?'));
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM charges
                WHERE fiscal_year = ? AND month_index IN ({$placeholders})";
        $params = array_merge([$period['fiscal_year']], $period['months']);
        if ($teamId > 0) {
            $sql .= ' AND team_id = ?';
            $params[] = $teamId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function teamName(int $teamId): string
    {
        if ($teamId <= 0) {
            return 'همه نهادها';
        }
        $statement = $this->pdo->prepare('SELECT name FROM teams WHERE id = :id');
        $statement->execute(['id' => $teamId]);
        $name = $statement->fetchColumn();

        return $name === false || $name === null || $name === '' ? ('نهاد #' . $teamId) : (string) $name;
    }

    private function typeLabel(string $type): string
    {
        foreach ($this->catalog()['types'] as $item) {
            if ($item['id'] === $type) {
                return (string) $item['label'];
            }
        }

        return 'گزارش';
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            self::PERIOD_MONTHLY => 'ماهانه',
            self::PERIOD_QUARTERLY => 'سه‌ماهه',
            self::PERIOD_ANNUAL => 'سالانه',
            self::PERIOD_CUSTOM => 'بازه سفارشی',
            default => $period,
        };
    }
}
