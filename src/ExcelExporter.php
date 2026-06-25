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
                'query' => "SELECT m.member_code, m.access_code, m.full_name, t.name AS team_label,
                            GROUP_CONCAT(d.number ORDER BY d.number) AS desk_numbers, l.locker_number, m.phone, m.national_id
                            FROM members m
                            LEFT JOIN teams t ON t.id = m.team_id
                            LEFT JOIN lockers l ON l.id = m.locker_id
                            LEFT JOIN member_desks md ON md.member_id = m.id
                            LEFT JOIN desks d ON d.id = md.desk_id
                            GROUP BY m.id ORDER BY m.id",
                'headers' => ['کد عضو', 'کد تردد', 'نام', 'نهاد', 'میزها', 'کمد', 'تماس', 'کدملی'],
            ],
            'teams' => [
                'title' => 'نهادها',
                'query' => "SELECT entity_code, entity_type, name, leader, phone, joined_at, warning, notes FROM teams ORDER BY entity_type, name",
                'headers' => ['کد', 'نوع', 'نام', 'مسئول', 'تماس', 'عضویت', 'اخطار', 'توضیحات'],
            ],
            'desks' => [
                'title' => 'میزها',
                'query' => "SELECT d.number, d.usage_type, d.formal_seats, d.informal_seats, t.name AS team_name FROM desks d LEFT JOIN teams t ON t.id = d.team_id ORDER BY d.number",
                'headers' => ['شماره', 'نوع', 'صندلی رسمی', 'صندلی غیررسمی', 'نهاد'],
            ],
            'lockers' => [
                'title' => 'کمدها',
                'query' => 'SELECT l.locker_number, l.status, t.name AS team_label, m.full_name AS member_name, l.delivered_at, l.key_number, l.spare_key
                            FROM lockers l LEFT JOIN teams t ON t.id = l.team_id LEFT JOIN members m ON m.id = l.member_id ORDER BY l.locker_number',
                'headers' => ['شماره کمد', 'وضعیت', 'نهاد', 'عضو', 'تحویل', 'کلید', 'یدک'],
            ],
            'charges' => [
                'title' => 'شارژ و اجاره',
                'query' => 'SELECT c.fiscal_year, t.name AS team_name, c.month_name, c.charge_amount, c.rent_amount, c.amount, c.note
                            FROM charges c LEFT JOIN teams t ON t.id = c.team_id ORDER BY c.fiscal_year, t.name, c.month_index',
                'headers' => ['سال', 'نهاد', 'ماه', 'شارژ', 'اجاره غیررسمی', 'جمع', 'یادداشت'],
            ],
            'transactions' => [
                'title' => 'مالی',
                'query' => "SELECT t.tx_date, t.description, t.amount, t.category, tm.name AS team_name, t.fiscal_year, t.month_index, t.notes
                            FROM transactions t LEFT JOIN teams tm ON tm.id = t.team_id ORDER BY t.tx_date DESC, t.id DESC",
                'headers' => ['تاریخ', 'شرح', 'مبلغ', 'دسته', 'نهاد', 'سال', 'ماه', 'توضیحات'],
            ],
            'rate_settings' => [
                'title' => 'نرخ‌ها',
                'query' => 'SELECT fiscal_year, title, charge_rate, informal_rent_rate, effective_from, notes FROM rate_settings ORDER BY fiscal_year, id',
                'headers' => ['سال', 'عنوان', 'نرخ شارژ', 'نرخ اجاره غیررسمی', 'تاریخ اثر', 'توضیحات'],
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
                <Font ss:FontName="Tahoma" ss:Size="14" ss:Bold="1" ss:Color="#2563A8"/>
            </Style>
            <Style ss:ID="Header">
                <Interior ss:Color="#E8F0FA" ss:Pattern="Solid"/>
                <Font ss:FontName="Tahoma" ss:Bold="1" ss:Color="#1E293B"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>
            </Style>
            <Style ss:ID="Cell">
                <Font ss:FontName="Tahoma" ss:Size="10"/>
                <Alignment ss:Horizontal="Right" ss:Vertical="Top" ss:WrapText="1"/>
            </Style>
            <Style ss:ID="Money">
                <Font ss:FontName="Tahoma" ss:Size="10"/>
                <Alignment ss:Horizontal="Right"/>
                <NumberFormat ss:Format="#,##0"/>
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
        $xml .= '<Row ss:Height="26"><Cell ss:StyleID="Title" ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">' . $this->xml($title) . '</Data></Cell></Row>' . "\n";
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

        $xml .= '</Table></Worksheet>' . "\n";

        return $xml;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
