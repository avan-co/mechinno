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
}
