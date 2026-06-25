<?php

declare(strict_types=1);

/**
 * Builds data/install-bundle.json from Excel source files (dev/build time only).
 * Run: php tools/build-install-bundle.php
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

final class InstallBundleBuilder
{
    private const MONTHS = [
        ['E', 1, 'فروردین'], ['F', 2, 'اردیبهشت'], ['G', 3, 'خرداد'], ['H', 4, 'تیر'],
        ['I', 5, 'مرداد'], ['J', 6, 'شهریور'], ['K', 7, 'مهر'], ['L', 8, 'آبان'],
        ['M', 9, 'آذر'], ['N', 10, 'دی'], ['O', 11, 'بهمن'], ['P', 12, 'اسفند'],
    ];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $innovation = $this->basePath . '/Innovation Center.xlsx';
        $charge = $this->basePath . '/CHARGE.xlsx';
        $finance = $this->basePath . '/finance.xlsx';

        foreach ([$innovation, $charge, $finance] as $path) {
            if (!is_file($path)) {
                throw new RuntimeException('Missing file: ' . basename($path));
            }
        }

        $lockerNumbers = LockerCatalog::numbersFromExcel($innovation);
        $lockers = $this->extractLockers($innovation);
        $entities = $this->extractTeams($innovation);
        $members = $this->extractMembers($innovation);
        $plans = $this->extractPlans($innovation);
        $charges = $this->extractCharges($charge);
        $financeRows = $this->extractFinance($finance);
        $rates = $this->extractRates($charge);

        return [
            'meta' => [
                'built_at' => date('c'),
                'fiscal_years' => array_values(array_unique(array_column($charges, 'fiscal_year'))),
                'locker_numbers' => $lockerNumbers,
            ],
            'lockers' => $lockers,
            'entities' => $entities,
            'members' => $members,
            'plans' => $plans,
            'charges' => $charges,
            'finance' => $financeRows,
            'rate_settings' => $rates,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractLockers(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        for ($row = 6; $row <= $reader->maxRow('lockers'); $row++) {
            $number = $this->parseInt($reader->value('lockers', "A{$row}"));
            if ($number === null) {
                continue;
            }
            $rows[] = [
                'number' => $number,
                'status' => $this->clean($reader->value('lockers', "B{$row}")) ?: 'خالی',
                'assigned_to' => $this->clean($reader->value('lockers', "C{$row}")),
                'delivered_at' => JalaliDate::tryNormalize($this->clean($reader->value('lockers', "D{$row}"))),
                'key_number' => $this->clean($reader->value('lockers', "E{$row}")),
                'spare_key' => $this->clean($reader->value('lockers', "F{$row}")),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractTeams(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        for ($row = 6; $row <= $reader->maxRow('Teams'); $row++) {
            $name = $this->clean($reader->value('Teams', "B{$row}"));
            $leader = $this->clean($reader->value('Teams', "C{$row}"));
            if ($name === '' && $leader === '') {
                continue;
            }
            $rows[] = [
                'entity_type' => $this->inferEntityType($name),
                'name' => $name,
                'leader' => $leader,
                'phone' => $this->clean($reader->value('Teams', "D{$row}")),
                'desk_count' => $this->parseInt($reader->value('Teams', "E{$row}")),
                'joined_at' => JalaliDate::tryNormalize($this->clean($reader->value('Teams', "H{$row}"))),
                'warning' => $this->clean($reader->value('Teams', "I{$row}")),
                'notes' => $this->clean($reader->value('Teams', "J{$row}")),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractMembers(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        for ($row = 4; $row <= $reader->maxRow('Members'); $row++) {
            $fullName = $this->clean($reader->value('Members', "C{$row}"));
            if ($fullName === '') {
                continue;
            }
            $teamName = $this->clean($reader->value('Members', "D{$row}"));
            $desksRaw = $this->clean($reader->value('Members', "E{$row}"));
            preg_match_all('/\d+/', $desksRaw, $deskMatches);
            $rows[] = [
                'team' => $teamName,
                'full_name' => $fullName,
                'access_code' => $this->clean($reader->value('Members', "B{$row}")),
                'desks' => array_map('intval', $deskMatches[0] ?? []),
                'phone' => $this->clean($reader->value('Members', "H{$row}")),
                'national_id' => $this->clean($reader->value('Members', "I{$row}")),
                'notes' => $this->clean($reader->value('Members', "J{$row}")),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractPlans(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        for ($row = 6; $row <= $reader->maxRow('plans'); $row++) {
            $title = $this->clean($reader->value('plans', "C{$row}"));
            if ($title === '') {
                continue;
            }
            $rows[] = [
                'title' => $title,
                'status' => $this->mapPlanStatus($this->clean($reader->value('plans', "B{$row}"))),
                'priority' => 'متوسط',
                'proposed_budget' => $this->parseInt($reader->value('plans', "D{$row}")),
                'schedule' => $this->clean($reader->value('plans', "E{$row}")),
                'end_date' => $this->clean($reader->value('plans', "F{$row}")),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractCharges(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        foreach ($reader->sheetNames() as $sheet) {
            for ($row = 6; $row <= $reader->maxRow($sheet); $row++) {
                $teamName = $this->clean($reader->value($sheet, "B{$row}"));
                if ($teamName === '') {
                    continue;
                }
                foreach (self::MONTHS as [$column, $monthIndex, $monthName]) {
                    $amount = $this->parseInt($reader->value($sheet, "{$column}{$row}"));
                    if ($amount === null) {
                        continue;
                    }
                    $chargePart = (int) round($amount * 0.7);
                    $rows[] = [
                        'fiscal_year' => $sheet,
                        'team' => $teamName,
                        'month_index' => $monthIndex,
                        'month_name' => $monthName,
                        'charge_amount' => $chargePart,
                        'rent_amount' => $amount - $chargePart,
                        'amount' => $amount,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractFinance(string $path): array
    {
        $reader = new XlsxReader($path);
        $rows = [];
        foreach ($reader->sheetNames() as $sheet) {
            for ($row = 6; $row <= 27; $row++) {
                $description = $this->clean($reader->value($sheet, "F{$row}"));
                $amount = $this->parseInt($reader->value($sheet, "I{$row}"));
                if ($description === '' && $amount === null) {
                    continue;
                }
                try {
                    $txDate = JalaliDate::compose(
                        $reader->value($sheet, "C{$row}"),
                        $reader->value($sheet, "D{$row}"),
                        $reader->value($sheet, "E{$row}")
                    );
                } catch (InvalidArgumentException) {
                    $txDate = '';
                }
                $category = $amount !== null && $amount < 0 ? 'هزینه' : 'درآمد';
                if ($amount !== null && $amount > 0 && str_contains($description, 'واریز')) {
                    $category = 'واریز تیم';
                }
                $rows[] = [
                    'tx_date' => $txDate,
                    'description' => $description,
                    'amount' => $amount,
                    'category' => $category,
                    'notes' => $this->clean($reader->value($sheet, "J{$row}")),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRates(string $path): array
    {
        $reader = new XlsxReader($path);
        $rates = [];
        foreach ($reader->sheetNames() as $sheet) {
            $chargeRate = $this->parseInt($reader->value($sheet, 'T1'));
            $rentRate = $this->parseInt($reader->value($sheet, 'T3'));
            if ($chargeRate === null && $rentRate === null) {
                continue;
            }
            $rates[] = [
                'fiscal_year' => $sheet,
                'title' => 'نرخ استخراج‌شده از Excel',
                'charge_rate' => $chargeRate,
                'informal_rent_rate' => $rentRate,
                'effective_from' => $sheet . '/01/01',
            ];
        }

        return $rates;
    }

    private function inferEntityType(string $name): string
    {
        if (str_contains($name, 'شرکت')) {
            return 'company';
        }
        if (str_contains($name, 'دانشجو')) {
            return 'student';
        }
        return 'team';
    }

    private function mapPlanStatus(string $status): string
    {
        return match ($status) {
            'در حال اجرا' => 'در حال اجرا',
            'انجام شده' => 'انجام‌شده',
            'لغو شده' => 'لغو شده',
            default => 'پیشنهادی',
        };
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    private function parseInt(mixed $value): ?int
    {
        $text = str_replace([',', '٬'], '', $this->clean($value));
        if ($text === '' || !preg_match('/^-?\d+(\.0+)?$/', $text)) {
            return null;
        }
        return (int) (float) $text;
    }
}

$out = (new InstallBundleBuilder(dirname(__DIR__)))->build();
$path = dirname(__DIR__) . '/data/install-bundle.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Wrote ' . $path . PHP_EOL;
echo 'lockers: ' . count($out['lockers']) . ', entities: ' . count($out['entities']) . ', members: ' . count($out['members']) . ', charges: ' . count($out['charges']) . PHP_EOL;
