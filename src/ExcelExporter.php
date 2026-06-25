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
                    (SELECT COALESCE(SUM(d.informal_seats), 0) FROM desks d WHERE d.team_id = t.id),
                    t.joined_at, t.warning, t.notes
                    FROM teams t ORDER BY t.entity_type, t.name",
                'headers' => ['کد', 'نوع', 'نام', 'مسئول', 'تماس', 'تعداد میز', 'صندلی غیررسمی', 'عضویت', 'اخطار', 'توضیحات'],
            ],
            'members' => [
                'title' => 'اعضا',
                'query' => "SELECT m.member_code, m.full_name, t.name AS team_label,
                    CASE t.entity_type WHEN 'team' THEN 'تیم' WHEN 'company' THEN 'شرکت' WHEN 'student' THEN 'دانشجو' ELSE t.entity_type END,
                    (SELECT GROUP_CONCAT(d.number ORDER BY d.number) FROM desks d WHERE d.team_id = m.team_id),
                    m.access_code, l.locker_number, m.phone, m.national_id, m.notes
                    FROM members m
                    LEFT JOIN teams t ON t.id = m.team_id
                    LEFT JOIN lockers l ON l.id = m.locker_id
                    ORDER BY t.name, m.full_name",
                'headers' => ['کد عضو', 'نام', 'نهاد', 'نوع نهاد', 'میزهای نهاد', 'کد تردد', 'کمد', 'تماس', 'کدملی', 'توضیحات'],
            ],
            'desks' => [
                'title' => 'میزها',
                'query' => "SELECT d.number,
                    CASE d.usage_type WHEN 'formal' THEN 'رسمی' WHEN 'informal' THEN 'غیررسمی' WHEN 'mixed' THEN 'ترکیبی' ELSE d.usage_type END,
                    d.formal_seats, d.informal_seats,
                    COALESCE(t.name, 'آزاد'),
                    CASE t.entity_type WHEN 'team' THEN 'تیم' WHEN 'company' THEN 'شرکت' WHEN 'student' THEN 'دانشجو' ELSE COALESCE(t.entity_type, '') END,
                    d.row_index, d.col_index
                    FROM desks d
                    LEFT JOIN teams t ON t.id = d.team_id
                    ORDER BY d.number",
                'headers' => ['شماره میز', 'نوع استفاده', 'صندلی رسمی', 'صندلی غیررسمی', 'نهاد', 'نوع نهاد', 'ردیف', 'ستون'],
            ],
            'lockers' => [
                'title' => 'کمدها',
                'query' => 'SELECT l.locker_number, l.status, t.name AS team_label, m.full_name AS member_name,
                            l.delivered_at, l.key_number, l.spare_key, l.notes
                            FROM lockers l
                            LEFT JOIN teams t ON t.id = l.team_id
                            LEFT JOIN members m ON m.id = l.member_id
                            ORDER BY l.locker_number',
                'headers' => ['شماره کمد', 'وضعیت', 'نهاد', 'عضو', 'تاریخ تحویل', 'شماره کلید', 'کلید یدک', 'توضیحات'],
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
                'query' => "SELECT t.name AS team_name, c.fiscal_year, c.month_name,
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
                     ORDER BY c.fiscal_year, t.name, c.month_index",
                'headers' => ['نهاد', 'سال', 'ماه', 'شارژ', 'اجاره', 'مبلغ مستحق', 'دریافت‌شده', 'وضعیت'],
            ],
            'transactions' => [
                'title' => 'مالی',
                'query' => "SELECT t.tx_date, t.description, t.amount,
                            CASE t.category WHEN 'واریز تیم' THEN 'دریافت از نهاد' ELSE t.category END,
                            tm.name AS team_name,
                            t.fiscal_year, t.month_index, t.confirmed, t.notes
                            FROM transactions t
                            LEFT JOIN teams tm ON tm.id = t.team_id
                            ORDER BY t.tx_date DESC, t.id DESC",
                'headers' => ['تاریخ', 'شرح', 'مبلغ', 'دسته', 'نهاد', 'سال مالی', 'ماه', 'تأیید', 'توضیحات'],
            ],
        ];
    }

    /** @var list<string> */
    private const EXPORT_ORDER = [
        'summary', 'teams', 'members', 'desks', 'lockers', 'rate_settings', 'charges', 'debts', 'transactions',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function output(string $reportKey): void
    {
        $reports = self::reports();
        if ($reportKey !== 'all' && !isset($reports[$reportKey])) {
            http_response_code(404);
            echo 'Report not found';
            return;
        }

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
            $rows = $this->pdo->query($report['query'])->fetchAll(PDO::FETCH_NUM);
            $xml .= $this->worksheetXml($report['title'], $report['headers'], $rows, $generatedAt);
        }

        $xml .= '</Workbook>';

        return $xml;
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
            ['میز اشغال', (int) $cards['desks_occupied'] . ' از 24'],
            ['کمدها', (int) $cards['lockers']],
            ['کمد آزاد', (int) $cards['available_lockers']],
            ['جمع شارژ (ریال)', (int) $cards['charge_total']],
            ['دریافتی (ریال)', (int) $cards['income_total']],
            ['هزینه (ریال)', (int) $cards['expense_total']],
            ['دریافت از نهادها (ریال)', (int) $cards['paid_total']],
            ['طلب کل از نهادها (ریال)', (int) $cards['debt_total']],
            ['شارژ ماه ' . ($month['month_name'] ?? ''), (int) ($month['charge_total'] ?? 0)],
            ['واریز ماه ' . ($month['month_name'] ?? ''), (int) ($month['paid_total'] ?? 0)],
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
