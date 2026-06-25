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
            'members' => [
                'title' => 'اعضا',
                'query' => 'SELECT code, full_name, team_name, desks, lockers, power_strips, phone, national_id, notes FROM members ORDER BY id',
                'headers' => ['کد', 'نام', 'تیم/شرکت', 'میز', 'کمد', 'سه‌راهی', 'تماس', 'کدملی', 'توضیحات'],
            ],
            'teams' => [
                'title' => 'تیم‌ها',
                'query' => 'SELECT name, leader, phone, desk_count, lockers, power_strips, joined_at, warning, notes FROM teams ORDER BY id',
                'headers' => ['نام تیم', 'سرگروه', 'تماس', 'تعداد میز', 'کمد', 'سه‌راهی', 'عضویت', 'اخطار', 'توضیحات'],
            ],
            'lockers' => [
                'title' => 'کمدها',
                'query' => 'SELECT locker_number, status, assigned_to, delivered_at, key_number, spare_key, notes FROM lockers ORDER BY locker_number',
                'headers' => ['شماره کمد', 'وضعیت', 'اختصاص به', 'تاریخ تحویل', 'شماره کلید', 'کلید یدک', 'توضیحات'],
            ],
            'charges' => [
                'title' => 'شارژ و اجاره',
                'query' => 'SELECT fiscal_year, team_name, leader, desk_count, month_name, amount, note, charge_rate, rent_rate FROM charges ORDER BY fiscal_year, team_name, month_index',
                'headers' => ['سال', 'تیم', 'سرگروه', 'میز', 'ماه', 'مبلغ', 'یادداشت', 'نرخ شارژ', 'نرخ اجاره'],
            ],
            'transactions' => [
                'title' => 'مالی',
                'query' => 'SELECT b.sheet_name, t.tx_date, t.description, t.amount, t.category, t.notes, t.suspected_amount_note, b.petty_cash_holder FROM transactions t JOIN financial_batches b ON b.id = t.batch_id ORDER BY b.id, t.id',
                'headers' => ['دوره', 'تاریخ', 'شرح', 'مبلغ', 'دسته', 'توضیحات', 'مبلغ مشکوک در توضیحات', 'دارنده تنخواه'],
            ],
            'plans' => [
                'title' => 'برنامه‌ها',
                'query' => 'SELECT plan_number, status, title, proposed_budget, cost_type, schedule, notes FROM plans ORDER BY plan_number',
                'headers' => ['شماره', 'وضعیت', 'عنوان', 'بودجه پیشنهادی', 'نوع هزینه', 'زمان‌بندی', 'توضیحات'],
            ],
            'warnings' => [
                'title' => 'هشدارهای داده',
                'query' => 'SELECT file_name, sheet_name, `row_number`, message, payload FROM import_warnings ORDER BY id',
                'headers' => ['فایل', 'شیت', 'ردیف', 'پیام', 'جزئیات'],
            ],
            'rate_settings' => [
                'title' => 'نرخ‌ها',
                'query' => 'SELECT fiscal_year, title, charge_rate, rent_rate, effective_from, notes, created_at FROM rate_settings ORDER BY fiscal_year, effective_from, id',
                'headers' => ['سال مالی', 'عنوان', 'نرخ شارژ', 'نرخ اجاره', 'تاریخ اثرگذاری', 'توضیحات', 'تاریخ ایجاد'],
            ],
            'backups' => [
                'title' => 'پشتیبان‌ها',
                'query' => 'SELECT id, created_at, reason, summary FROM import_backups ORDER BY id DESC',
                'headers' => ['شناسه', 'تاریخ ایجاد', 'دلیل', 'خلاصه'],
            ],
            'team_payments' => [
                'title' => 'بدهی و پرداخت تیم‌ها',
                'query' => 'SELECT t.name, p.fiscal_year, p.month_name, p.amount_due, p.amount_paid, p.status, p.paid_at, p.notes FROM team_payments p LEFT JOIN teams t ON t.id = p.team_id ORDER BY p.fiscal_year, p.month_index, t.name',
                'headers' => ['تیم', 'سال', 'ماه', 'بدهی', 'پرداخت', 'وضعیت', 'تاریخ پرداخت', 'توضیحات'],
            ],
        ];
    }

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

        $fileName = $reportKey === 'all' ? 'mechinno-management-report.xls' : "mechinno-{$reportKey}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo $this->workbookXml($reportKey === 'all' ? array_keys($reports) : [$reportKey]);
    }

    /**
     * @param list<string> $keys
     */
    private function workbookXml(array $keys): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= $this->stylesXml();

        $reports = self::reports();
        foreach ($keys as $key) {
            $report = $reports[$key];
            $rows = $this->pdo->query($report['query'])->fetchAll(PDO::FETCH_NUM);
            $xml .= $this->worksheetXml($report['title'], $report['headers'], $rows);
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    private function stylesXml(): string
    {
        return '<Styles>
            <Style ss:ID="Title">
                <Font ss:FontName="Tahoma" ss:Size="16" ss:Bold="1" ss:Color="#C9A44C"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="Header">
                <Interior ss:Color="#111827" ss:Pattern="Solid"/>
                <Font ss:FontName="Tahoma" ss:Bold="1" ss:Color="#FFFFFF"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C9A44C"/>
                </Borders>
            </Style>
            <Style ss:ID="Cell">
                <Font ss:FontName="Tahoma" ss:Size="10" ss:Color="#141414"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Top" ss:WrapText="1"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8DDAE"/>
                </Borders>
            </Style>
            <Style ss:ID="Money">
                <Font ss:FontName="Tahoma" ss:Size="10" ss:Color="#141414"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Top"/>
                <NumberFormat ss:Format="#,##0"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8DDAE"/>
                </Borders>
            </Style>
        </Styles>' . "\n";
    }

    /**
     * @param list<string> $headers
     * @param list<array<int, mixed>> $rows
     */
    private function worksheetXml(string $title, array $headers, array $rows): string
    {
        $columnCount = max(1, count($headers));
        $xml = '<Worksheet ss:Name="' . $this->xml($title) . '" ss:RightToLeft="1">' . "\n";
        $xml .= '<Table>' . "\n";
        for ($i = 0; $i < $columnCount; $i++) {
            $xml .= '<Column ss:AutoFitWidth="1" ss:Width="' . ($i === 2 ? '220' : '110') . '"/>' . "\n";
        }

        $xml .= '<Row ss:Height="30"><Cell ss:StyleID="Title" ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">' . $this->xml($title) . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $this->xml($header) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $value) {
                $isNumber = is_numeric($value) && $value !== '' && $value !== null;
                $style = $isNumber ? 'Money' : 'Cell';
                $type = $isNumber ? 'Number' : 'String';
                $xml .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="' . $type . '">' . $this->xml((string) ($value ?? '')) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><DisplayRightToLeft/><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane></WorksheetOptions></Worksheet>' . "\n";

        return $xml;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
