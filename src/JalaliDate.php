<?php

declare(strict_types=1);

final class JalaliDate
{
    /**
     * Normalizes Persian/Arabic digits and validates yyyy/mm/dd Jalali dates.
     */
    public static function normalize(mixed $value): string
    {
        $text = trim(strtr((string) ($value ?? ''), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '-' => '/',
            '.' => '/',
        ]));

        if ($text === '' || $text === '/') {
            return '';
        }

        if (!preg_match('/^(\d{2,4})\/(\d{1,2})\/(\d{1,2})$/', $text, $matches)) {
            throw new InvalidArgumentException('فرمت تاریخ باید به صورت 1404/01/01 باشد.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if ($year < 100) {
            $year += 1400;
        }

        if (!self::isValidParts($year, $month, $day)) {
            throw new InvalidArgumentException('تاریخ شمسی معتبر نیست.');
        }

        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    public static function tryNormalize(mixed $value): string
    {
        try {
            return self::normalize($value);
        } catch (InvalidArgumentException) {
            return trim((string) ($value ?? ''));
        }
    }

    public static function isValid(string $date): bool
    {
        try {
            self::normalize($date);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function compose(mixed $day, mixed $month, mixed $year): string
    {
        $parts = array_map(static fn (mixed $value): string => trim((string) $value), [$year, $month, $day]);
        if (in_array('', $parts, true)) {
            return '';
        }

        return self::normalize(implode('/', $parts));
    }

    private static function isValidParts(int $year, int $month, int $day): bool
    {
        if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12) {
            return false;
        }

        return $day >= 1 && $day <= self::daysInMonth($year, $month);
    }

    private static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }

    /**
     * Jalali leap-year calculation based on the 33-year cycle used by common Persian calendars.
     */
    private static function isLeapYear(int $year): bool
    {
        return in_array($year % 33, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    /**
     * @return array{year:int,month:int,day:int,month_name:string,formatted:string}
     */
    public static function todayParts(): array
    {
        [$year, $month, $day] = self::gregorianToJalali(
            (int) date('Y'),
            (int) date('n'),
            (int) date('j')
        );

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'month_name' => self::monthName($month),
            'formatted' => sprintf('%04d/%02d/%02d', $year, $month, $day),
        ];
    }

    public static function monthName(int $index): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$index] ?? '';
    }

    /** Compare two Jalali dates (YYYY/MM/DD). Returns -1, 0, or 1. */
    public static function compare(string $left, string $right): int
    {
        $left = self::tryNormalize($left);
        $right = self::tryNormalize($right);
        if ($left === '' || $right === '') {
            return strcmp($left, $right);
        }

        return strcmp($left, $right);
    }

    public static function monthStart(string $fiscalYear, int $monthIndex): string
    {
        $year = (int) self::normalizeDigits($fiscalYear);
        return sprintf('%04d/%02d/01', $year, $monthIndex);
    }

    public static function normalizeDigits(mixed $value): string
    {
        return strtr(trim((string) ($value ?? '')), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy)
            + (int) (($gy2 + 3) / 4)
            - (int) (($gy2 + 99) / 100)
            + (int) (($gy2 + 399) / 400)
            - 80 + $gd + array_sum(array_slice($gDaysInMonth, 0, $gm - 1));
        $jy += 33 * (int) ($days / 12053);
        $days %= 12053;
        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}
