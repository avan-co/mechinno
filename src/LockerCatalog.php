<?php

declare(strict_types=1);

final class LockerCatalog
{
    public static function bootstrap(PDO $pdo, string $basePath): array
    {
        $path = $basePath . '/Innovation Center.xlsx';
        if (!is_file($path)) {
            return [];
        }

        $numbers = self::numbersFromExcel($path);
        Schema::ensureLockerNumbers($pdo, $numbers);

        return $numbers;
    }

    /**
     * @return list<int>
     */
    public static function numbersFromExcel(string $path): array
    {
        $reader = new XlsxReader($path);
        $numbers = [];
        $maxRow = $reader->maxRow('lockers');
        for ($row = 6; $row <= $maxRow; $row++) {
            $value = trim((string) $reader->value('lockers', "A{$row}"));
            if ($value === '') {
                continue;
            }
            $number = (int) preg_replace('/[^\d]/', '', strtr($value, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]));
            if ($number > 0) {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }
}
