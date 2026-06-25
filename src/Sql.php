<?php

declare(strict_types=1);

final class Sql
{
    public static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param list<string> $columns
     */
    public static function columnList(array $columns): string
    {
        return implode(', ', array_map(self::quoteIdentifier(...), $columns));
    }
}
