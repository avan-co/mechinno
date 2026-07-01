<?php

declare(strict_types=1);

final class ReportData
{
    private const ENTITY_LABELS = [
        'team' => 'تیم',
        'company' => 'شرکت',
        'student' => 'دانشجو',
    ];

    private const USAGE_LABELS = [
        'formal' => 'رسمی',
        'informal' => 'غیررسمی',
        'mixed' => 'ترکیبی',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $repo = new Repository($this->pdo);
        $today = JalaliDate::todayParts();

        return [
            'meta' => [
                'title' => 'گزارش جامع مرکز نوآوری مکانیک',
                'subtitle' => 'Mechinno Innovation Center',
                'generated_at' => $today['formatted'],
                'generated_time' => date('H:i'),
            ],
            'summary' => $repo->summary(),
            'teams' => $repo->resource('teams'),
            'members' => $repo->resource('members'),
            'desks' => $repo->resource('desks'),
            'lockers' => $repo->resource('lockers'),
            'rate_settings' => $repo->resource('rate_settings'),
            'charges' => $repo->resource('charges'),
            'debts' => $repo->chargeDebtRows(),
            'desk_assignments' => $repo->resource('desk-assignments'),
            'transactions' => array_values(array_filter(
                $repo->resource('transactions'),
                static fn (array $row): bool => (int) ($row['confirmed'] ?? 0) === 1
            )),
        ];
    }

    public static function entityLabel(?string $type): string
    {
        return self::ENTITY_LABELS[$type ?? ''] ?? ($type ?: '—');
    }

    public static function usageLabel(?string $type): string
    {
        return self::USAGE_LABELS[$type ?? ''] ?? ($type ?: '—');
    }

    public static function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_string($value)) {
            $digits = preg_replace('/[^\d-]/', '', $value);
            $value = $digits === '' || $digits === '-' ? 0 : (int) $digits;
        }

        return number_format((int) $value);
    }

    public static function plain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return preg_replace('/[^\d]/', '', (string) $value) ?: (string) $value;
    }

    public static function wantsAccessLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ((int) $value === 1 || $value === '1' || $value === true) {
            return 'بله';
        }

        return 'خیر';
    }

    public static function cell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }
}
