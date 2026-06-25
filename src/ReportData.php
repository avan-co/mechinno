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
            'transactions' => $repo->resource('transactions'),
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

    public static function money(int|float|null $value): string
    {
        return number_format((int) ($value ?? 0));
    }

    public static function cell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }
}
