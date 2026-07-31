<?php

declare(strict_types=1);

final class ExcelExporter
{
    /**
     * @return array<string, array{title:string,query:string,headers:list<string>}>
     */
    public static function reports(): array
    {
        return [
            'summary' => [
                'title' => 'خلاصه',
                'query' => '',
                'headers' => ['شاخص', 'مقدار'],
            ],
            'teams' => [
                'title' => 'نهادها',
                'query' => "SELECT t.entity_code,
                    CASE t.entity_type WHEN 'team' THEN 'تیم' WHEN 'company' THEN 'شرکت' WHEN 'student' THEN 'دانشجو' ELSE t.entity_type END,
                    t.name, t.leader, t.phone,
                    (SELECT COUNT(*) FROM desks d WHERE d.team_id = t.id),
                    t.contract_start, t.contract_end,
                    t.joined_at, t.warning, t.notes,
                    u.username
                    FROM teams t
                    LEFT JOIN panel_users u ON u.team_id = t.id AND u.role = 'team'
                    ORDER BY t.entity_type, t.name",
                'headers' => ['کد', 'نوع', 'نام', 'مسئول', 'تماس', 'تعداد میز', 'شروع قرارداد', 'پایان قرارداد', 'عضویت', 'اخطار', 'توضیحات', 'نام کاربری نهاد'],
            ],
            'members' => [
                'title' => 'اعضا',
                'query' => "SELECT m.member_code, m.full_name, t.name AS team_label,
                    CASE t.entity_type WHEN 'team' THEN 'تیم' WHEN 'company' THEN 'شرکت' WHEN 'student' THEN 'دانشجو' ELSE t.entity_type END,
                    (SELECT GROUP_CONCAT(d.number ORDER BY d.number) FROM desks d WHERE d.team_id = m.team_id),
                    CASE m.wants_access WHEN 1 THEN 'بله' ELSE 'خیر' END,
                    m.access_code, m.phone, m.national_id, m.notes
                    FROM members m
                    LEFT JOIN teams t ON t.id = m.team_id
                    WHERE m.approval_status = 'approved' OR m.approval_status IS NULL
                    ORDER BY t.name, m.full_name",
                'headers' => ['کد عضو', 'نام', 'نهاد', 'نوع نهاد', 'میزهای نهاد', 'درخواست تردد', 'کد تردد', 'تماس', 'کدملی', 'توضیحات'],
            ],
            'desks' => [
                'title' => 'میزها',
                'query' => "SELECT d.number,
                    CASE d.usage_type WHEN 'formal' THEN 'رسمی' WHEN 'informal' THEN 'غیررسمی' WHEN 'mixed' THEN 'ترکیبی' ELSE d.usage_type END,
                    COALESCE(t.name, 'آزاد'),
                    CASE t.entity_type WHEN 'team' THEN 'تیم' WHEN 'company' THEN 'شرکت' WHEN 'student' THEN 'دانشجو' ELSE COALESCE(t.entity_type, '') END,
                    d.notes, d.row_index, d.col_index
                    FROM desks d
                    LEFT JOIN teams t ON t.id = d.team_id
                    ORDER BY d.number",
                'headers' => ['شماره میز', 'نوع استفاده', 'نهاد', 'نوع نهاد', 'توضیحات', 'ردیف', 'ستون'],
            ],
            'desk_assignments' => [
                'title' => 'تخصیص میز',
                'query' => "SELECT da.desk_number, t.name AS team_name,
                    CASE da.usage_type WHEN 'formal' THEN 'رسمی' WHEN 'informal' THEN 'غیررسمی' ELSE da.usage_type END,
                    da.assigned_from, da.assigned_until, da.notes
                    FROM desk_assignments da
                    LEFT JOIN teams t ON t.id = da.team_id
                    ORDER BY da.desk_number, da.assigned_from",
                'headers' => ['شماره میز', 'نهاد', 'نوع استفاده', 'شروع تخصیص', 'پایان تخصیص', 'توضیحات'],
            ],
            'lockers' => [
                'title' => 'کمدها',
                'query' => 'SELECT l.locker_number, l.status, t.name AS team_label,
                            l.delivered_at, l.key_number, l.spare_key, l.notes
                            FROM lockers l
                            LEFT JOIN teams t ON t.id = l.team_id
                            ORDER BY l.locker_number',
                'headers' => ['شماره کمد', 'وضعیت', 'نهاد', 'تاریخ تحویل', 'شماره کلید', 'کلید یدک', 'توضیحات'],
            ],
            'rate_settings' => [
                'title' => 'نرخ‌ها',
                'query' => 'SELECT fiscal_year, title, charge_rate, informal_rent_rate, effective_from, notes
                            FROM rate_settings ORDER BY fiscal_year, id',
                'headers' => ['سال مالی', 'عنوان', 'نرخ شارژ هر میز', 'نرخ اجاره غیررسمی', 'تاریخ اثر', 'توضیحات'],
            ],
            'charges' => [
                'title' => 'شارژ ماهانه',
                'query' => 'SELECT c.fiscal_year, t.name AS team_name,
                            CASE t.entity_type WHEN \'team\' THEN \'تیم\' WHEN \'company\' THEN \'شرکت\' WHEN \'student\' THEN \'دانشجو\' ELSE t.entity_type END,
                            c.month_name, c.month_index, c.charge_amount, c.rent_amount, c.amount, c.note
                            FROM charges c
                            LEFT JOIN teams t ON t.id = c.team_id
                            ORDER BY c.fiscal_year, t.name, c.month_index',
                'headers' => ['سال', 'نهاد', 'نوع نهاد', 'ماه', 'شماره ماه', 'شارژ', 'اجاره غیررسمی', 'جمع', 'یادداشت'],
            ],
            'debts' => [
                'title' => 'مطالبات مرکز',
                'query' => '',
                'headers' => ['نهاد', 'سال', 'ماه', 'شارژ', 'اجاره', 'مبلغ مستحق', 'دریافت‌شده', 'وضعیت'],
            ],
            'transactions' => [
                'title' => 'مالی',
                'query' => "SELECT t.tx_date, t.description, t.amount,
                            CASE t.category WHEN 'واریز تیم' THEN 'دریافت از نهاد' ELSE t.category END,
                            tm.name AS team_name,
                            t.fiscal_year,
                            CASE t.month_index
                                WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                                WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                                WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                                WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                                ELSE ''
                            END,
                            t.confirmed, t.notes
                            FROM transactions t
                            LEFT JOIN teams tm ON tm.id = t.team_id
                            WHERE t.confirmed = 1
                              AND (t.category <> 'واریز تیم' OR t.payment_status = 'approved')
                            ORDER BY t.tx_date DESC, t.id DESC",
                'headers' => ['تاریخ', 'شرح', 'مبلغ', 'دسته', 'نهاد', 'سال مالی', 'ماه', 'تأیید', 'توضیحات'],
            ],
        ];
    }

    /** @var list<string> */
    private const EXPORT_ORDER = [
        'summary', 'teams', 'members', 'desks', 'desk_assignments', 'lockers', 'rate_settings', 'charges', 'debts', 'transactions',
    ];

    /** @var array{fiscal_year?:string,month_from?:int,month_to?:int,team_id?:int} */
    private array $filters = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{fiscal_year?:string,month_from?:int,month_to?:int,team_id?:int} $filters
     */
    public function output(string $reportKey, array $filters = []): void
    {
        $reports = self::reports();
        if ($reportKey !== 'all' && !isset($reports[$reportKey])) {
            http_response_code(404);
            echo 'Report not found';
            return;
        }

        $this->filters = $this->normalizeFilters($filters);
        $today = JalaliDate::todayParts();
        $fileName = $reportKey === 'all'
            ? 'mechinno-report-' . str_replace('/', '-', $today['formatted']) . '.xls'
            : "mechinno-{$reportKey}-" . str_replace('/', '-', $today['formatted']) . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $keys = $reportKey === 'all'
            ? self::EXPORT_ORDER
            : [$reportKey];

        echo $this->workbookXml($keys, $today['formatted']);
    }

    /**
     * @param array{fiscal_year?:string,month_from?:int,month_to?:int,team_id?:int} $filters
     * @return array{fiscal_year?:string,month_from?:int,month_to?:int,team_id?:int}
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        $year = JalaliDate::normalizeDigits((string) ($filters['fiscal_year'] ?? ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
            $normalized['fiscal_year'] = $year;
        }
        $from = (int) ($filters['month_from'] ?? 0);
        $to = (int) ($filters['month_to'] ?? 0);
        if ($from >= 1 && $from <= 12) {
            $normalized['month_from'] = $from;
        }
        if ($to >= 1 && $to <= 12) {
            $normalized['month_to'] = $to;
        }
        $teamId = (int) ($filters['team_id'] ?? 0);
        if ($teamId > 0) {
            $normalized['team_id'] = $teamId;
        }

        return $normalized;
    }

    /**
     * @param list<string> $keys
     */
    private function workbookXml(array $keys, string $generatedAt): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= $this->documentPropertiesXml($generatedAt);
        $xml .= $this->stylesXml();

        $reports = self::reports();
        foreach ($keys as $key) {
            $report = $reports[$key];
            if ($key === 'summary') {
                $xml .= $this->summaryWorksheetXml($generatedAt);
                continue;
            }
            if ($key === 'debts') {
                $debtRows = (new Repository($this->pdo))->chargeDebtRows();
                $debtRows = array_values(array_filter($debtRows, fn (array $row): bool => $this->rowMatchesFilters($row)));
                $rows = array_map(static fn (array $row): array => [
                    $row['team_name'] ?? '',
                    $row['fiscal_year'] ?? '',
                    $row['month_name'] ?? '',
                    $row['charge_amount'] ?? 0,
                    $row['rent_amount'] ?? 0,
                    $row['amount_due'] ?? 0,
                    $row['amount_paid'] ?? 0,
                    $row['status'] ?? '',
                ], $debtRows);
                $xml .= $this->worksheetXml($report['title'], $report['headers'], $rows, $generatedAt);
                continue;
            }
            if (in_array($key, ['charges', 'transactions'], true) && $this->filters !== []) {
                $rows = $this->filteredQueryRows($key, $report['query']);
                $xml .= $this->worksheetXml($report['title'], $report['headers'], $rows, $generatedAt);
                continue;
            }
            $rows = $this->pdo->query($report['query'])->fetchAll(PDO::FETCH_NUM);
            $xml .= $this->worksheetXml($report['title'], $report['headers'], $rows, $generatedAt);
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    /**
     * @return list<list<mixed>>
     */
    private function filteredQueryRows(string $key, string $baseQuery): array
    {
        if ($key === 'charges') {
            $sql = 'SELECT c.fiscal_year, t.name AS team_name,
                           CASE t.entity_type WHEN \'team\' THEN \'تیم\' WHEN \'company\' THEN \'شرکت\' WHEN \'student\' THEN \'دانشجو\' ELSE t.entity_type END,
                           c.month_name, c.month_index, c.charge_amount, c.rent_amount, c.amount, c.note
                    FROM charges c
                    LEFT JOIN teams t ON t.id = c.team_id
                    WHERE 1=1';
            $params = [];
            if (isset($this->filters['fiscal_year'])) {
                $sql .= ' AND c.fiscal_year = :fiscal_year';
                $params['fiscal_year'] = $this->filters['fiscal_year'];
            }
            if (isset($this->filters['month_from'], $this->filters['month_to'])) {
                $sql .= ' AND c.month_index BETWEEN :month_from AND :month_to';
                $params['month_from'] = $this->filters['month_from'];
                $params['month_to'] = $this->filters['month_to'];
            }
            if (isset($this->filters['team_id'])) {
                $sql .= ' AND c.team_id = :team_id';
                $params['team_id'] = $this->filters['team_id'];
            }
            $sql .= ' ORDER BY c.fiscal_year, t.name, c.month_index';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll(PDO::FETCH_NUM);
        }

        if ($key === 'transactions') {
            $sql = "SELECT t.tx_date, t.description, t.amount,
                           CASE t.category WHEN 'واریز تیم' THEN 'دریافت از نهاد' ELSE t.category END,
                           tm.name AS team_name,
                           t.fiscal_year,
                           CASE t.month_index
                               WHEN 1 THEN 'فروردین' WHEN 2 THEN 'اردیبهشت' WHEN 3 THEN 'خرداد'
                               WHEN 4 THEN 'تیر' WHEN 5 THEN 'مرداد' WHEN 6 THEN 'شهریور'
                               WHEN 7 THEN 'مهر' WHEN 8 THEN 'آبان' WHEN 9 THEN 'آذر'
                               WHEN 10 THEN 'دی' WHEN 11 THEN 'بهمن' WHEN 12 THEN 'اسفند'
                               ELSE ''
                           END,
                           t.confirmed, t.notes
                    FROM transactions t
                    LEFT JOIN teams tm ON tm.id = t.team_id
                    WHERE t.confirmed = 1
                      AND (t.category <> 'واریز تیم' OR t.payment_status = 'approved')";
            $params = [];
            if (isset($this->filters['fiscal_year'], $this->filters['month_from'], $this->filters['month_to'])) {
                $sql .= ' AND t.tx_date >= :date_from AND t.tx_date <= :date_to';
                $params['date_from'] = JalaliDate::monthStart(
                    $this->filters['fiscal_year'],
                    (int) $this->filters['month_from']
                );
                $params['date_to'] = JalaliDate::monthEnd(
                    $this->filters['fiscal_year'],
                    (int) $this->filters['month_to']
                );
            } elseif (isset($this->filters['fiscal_year'])) {
                $sql .= ' AND t.fiscal_year = :fiscal_year';
                $params['fiscal_year'] = $this->filters['fiscal_year'];
            }
            if (isset($this->filters['team_id'])) {
                $sql .= ' AND t.team_id = :team_id';
                $params['team_id'] = $this->filters['team_id'];
            }
            $sql .= ' ORDER BY t.tx_date DESC, t.id DESC';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll(PDO::FETCH_NUM);
        }

        return $this->pdo->query($baseQuery)->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowMatchesFilters(array $row): bool
    {
        if (isset($this->filters['fiscal_year'])
            && (string) ($row['fiscal_year'] ?? '') !== $this->filters['fiscal_year']) {
            return false;
        }
        $monthIndex = (int) ($row['month_index'] ?? 0);
        if (isset($this->filters['month_from']) && $monthIndex < $this->filters['month_from']) {
            return false;
        }
        if (isset($this->filters['month_to']) && $monthIndex > $this->filters['month_to']) {
            return false;
        }
        if (isset($this->filters['team_id']) && (int) ($row['team_id'] ?? 0) !== $this->filters['team_id']) {
            return false;
        }

        return true;
    }

    private function documentPropertiesXml(string $generatedAt): string
    {
        return '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
            <Title>گزارش مرکز نوآوری Mechinno</Title>
            <Author>Mechinno Panel</Author>
            <Created>' . $this->xml(gmdate('Y-m-d\TH:i:s\Z')) . '</Created>
            <LastSaved>' . $this->xml(gmdate('Y-m-d\TH:i:s\Z')) . '</LastSaved>
            <Comments>تولید: ' . $this->xml($generatedAt) . '</Comments>
        </DocumentProperties>' . "\n";
    }

    private function stylesXml(): string
    {
        return '<Styles>
            <Style ss:ID="DocTitle">
                <Font ss:FontName="Tahoma" ss:Size="16" ss:Bold="1" ss:Color="#1D4ED8"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="Meta">
                <Font ss:FontName="Tahoma" ss:Size="9" ss:Color="#64748B"/>
                <Alignment ss:Horizontal="Right"/>
            </Style>
            <Style ss:ID="Title">
                <Font ss:FontName="Tahoma" ss:Size="13" ss:Bold="1" ss:Color="#1E3A5F"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="Header">
                <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
                <Font ss:FontName="Tahoma" ss:Bold="1" ss:Size="10" ss:Color="#1E3A5F"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#93C5FD"/>
                </Borders>
            </Style>
            <Style ss:ID="Cell">
                <Font ss:FontName="Tahoma" ss:Size="10"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Top" ss:WrapText="1"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
                </Borders>
            </Style>
            <Style ss:ID="Money">
                <Font ss:FontName="Tahoma" ss:Size="10"/>
                <Alignment ss:Horizontal="Left" ss:Vertical="Top"/>
                <NumberFormat ss:Format="#,##0"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
                </Borders>
            </Style>
            <Style ss:ID="Label">
                <Font ss:FontName="Tahoma" ss:Size="10" ss:Bold="1" ss:Color="#334155"/>
                <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
                <Alignment ss:Horizontal="Right"/>
            </Style>
        </Styles>' . "\n";
    }

    private function summaryWorksheetXml(string $generatedAt): string
    {
        $repo = new Repository($this->pdo);
        $summary = $repo->summary();
        $cards = $summary['cards'];
        $month = $summary['current_month'];

        $rows = [
            ['نهادها', (int) $cards['teams']],
            ['اعضا', (int) $cards['members']],
            ['میز اشغال', (int) $cards['desks_occupied'] . ' از ' . (int) ($cards['desks_total'] ?? 0)],
            ['کمدها', (int) $cards['lockers']],
            ['کمد آزاد', (int) $cards['available_lockers']],
            ['جمع شارژ قراردادها (ریال)', (int) $cards['charge_total']],
            ['درآمد سال — واریز+دستی (ریال)', (int) $cards['income_total']],
            ['هزینه سال (ریال)', (int) $cards['expense_total']],
            ['واریز تأییدشده نهادها (ریال)', (int) $cards['paid_total']],
            ['طلب کل از نهادها (ریال)', (int) $cards['debt_total']],
            ['شارژ ماه ' . ($month['month_name'] ?? ''), (int) ($month['charge_total'] ?? 0)],
            ['واریز تخصیص‌یافته ماه ' . ($month['month_name'] ?? ''), (int) ($month['paid_total'] ?? 0)],
            ['مانده طلب ماه ' . ($month['month_name'] ?? ''), (int) ($month['debt_total'] ?? 0)],
        ];

        $xml = '<Worksheet ss:Name="خلاصه" ss:RightToLeft="1">' . "\n";
        $xml .= '<Table>' . "\n";
        $xml .= '<Column ss:Width="180"/><Column ss:Width="140"/>' . "\n";
        $xml .= '<Row ss:Height="28"><Cell ss:StyleID="DocTitle" ss:MergeAcross="1"><Data ss:Type="String">گزارش جامع مرکز نوآوری</Data></Cell></Row>' . "\n";
        $xml .= '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="1"><Data ss:Type="String">تاریخ تولید: ' . $this->xml($generatedAt) . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row ss:Height="8"><Cell/></Row>' . "\n";
        $xml .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">شاخص</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">مقدار</Data></Cell></Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            $xml .= '<Cell ss:StyleID="Label"><Data ss:Type="String">' . $this->xml((string) $row[0]) . '</Data></Cell>';
            $value = $row[1];
            if (is_int($value)) {
                $xml .= '<Cell ss:StyleID="Money"><Data ss:Type="Number">' . $value . '</Data></Cell>';
            } else {
                $xml .= '<Cell ss:StyleID="Cell"><Data ss:Type="String">' . $this->xml((string) $value) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet>' . "\n";

        return $xml;
    }

    /**
     * @param list<string> $headers
     * @param list<array<int, mixed>> $rows
     */
    private function worksheetXml(string $title, array $headers, array $rows, string $generatedAt): string
    {
        $columnCount = max(1, count($headers));
        $safeName = substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?: 'Sheet', 0, 31);

        $xml = '<Worksheet ss:Name="' . $this->xml($safeName) . '" ss:RightToLeft="1">' . "\n";
        $xml .= '<Table>' . "\n";

        foreach ($headers as $header) {
            $width = max(60, min(200, strlen($header) * 10 + 30));
            $xml .= '<Column ss:Width="' . $width . '"/>' . "\n";
        }

        $xml .= '<Row ss:Height="24"><Cell ss:StyleID="Title" ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">' . $this->xml($title) . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">تولید: ' . $this->xml($generatedAt) . ' — ' . count($rows) . ' ردیف</Data></Cell></Row>' . "\n";
        $xml .= '<Row ss:Height="6"><Cell/></Row>' . "\n";
        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $this->xml($header) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        if ($rows === []) {
            $xml .= '<Row><Cell ss:StyleID="Cell" ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">داده‌ای ثبت نشده است.</Data></Cell></Row>' . "\n";
        }

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $value) {
                $isNumber = $this->isNumericCell($value);
                $style = $isNumber ? 'Money' : 'Cell';
                $type = $isNumber ? 'Number' : 'String';
                $display = $isNumber ? (int) $value : (string) ($value ?? '');
                $xml .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="' . $type . '">' . $this->xml((string) $display) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet>' . "\n";

        return $xml;
    }

    private function isNumericCell(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
