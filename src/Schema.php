<?php

declare(strict_types=1);

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            self::migrateSqlite($pdo);
            return;
        }

        $sql = [
            "CREATE TABLE IF NOT EXISTS import_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source_files TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS teams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                row_number INT NULL,
                name VARCHAR(255) NULL,
                leader VARCHAR(255) NULL,
                phone VARCHAR(64) NULL,
                desk_count INT NULL,
                lockers TEXT NULL,
                power_strips TEXT NULL,
                joined_at VARCHAR(32) NULL,
                warning TEXT NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                row_number INT NULL,
                code VARCHAR(64) NULL,
                full_name VARCHAR(255) NOT NULL,
                team_name VARCHAR(255) NULL,
                desks TEXT NULL,
                lockers TEXT NULL,
                power_strips TEXT NULL,
                phone VARCHAR(64) NULL,
                national_id VARCHAR(64) NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS lockers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                locker_number INT NULL,
                status VARCHAR(64) NULL,
                assigned_to VARCHAR(255) NULL,
                delivered_at VARCHAR(32) NULL,
                key_number VARCHAR(64) NULL,
                spare_key VARCHAR(64) NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                plan_number INT NULL,
                status VARCHAR(64) NULL,
                title TEXT NULL,
                proposed_budget BIGINT NULL,
                cost_type VARCHAR(128) NULL,
                schedule VARCHAR(255) NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS charges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fiscal_year VARCHAR(32) NULL,
                team_name VARCHAR(255) NULL,
                leader VARCHAR(255) NULL,
                desk_count INT NULL,
                month_index INT NULL,
                month_name VARCHAR(32) NULL,
                amount BIGINT NULL,
                note TEXT NULL,
                charge_rate BIGINT NULL,
                rent_rate BIGINT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                INDEX idx_charges_year_month (fiscal_year, month_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS financial_batches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sheet_name VARCHAR(255) NULL,
                petty_cash_holder VARCHAR(255) NULL,
                petty_cash_number VARCHAR(64) NULL,
                previous_balance BIGINT NULL,
                new_deposit BIGINT NULL,
                total_balance BIGINT NULL,
                received_at VARCHAR(32) NULL,
                from_date VARCHAR(32) NULL,
                to_date VARCHAR(32) NULL,
                source_file VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                batch_id INT NOT NULL,
                row_number INT NULL,
                invoice_count VARCHAR(64) NULL,
                tx_date VARCHAR(32) NULL,
                description TEXT NULL,
                amount BIGINT NULL,
                notes TEXT NULL,
                category VARCHAR(64) NULL,
                suspected_amount_note BIGINT NULL,
                INDEX idx_transactions_batch (batch_id),
                INDEX idx_transactions_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS import_warnings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(255) NULL,
                sheet_name VARCHAR(255) NULL,
                row_number INT NULL,
                message TEXT NOT NULL,
                payload TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
    }

    public static function reset(PDO $pdo): void
    {
        $tables = [
            'transactions',
            'financial_batches',
            'charges',
            'plans',
            'lockers',
            'members',
            'teams',
            'import_warnings',
            'import_runs',
        ];

        foreach ($tables as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
    }

    private static function migrateSqlite(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS import_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, source_files TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS teams (id INTEGER PRIMARY KEY AUTOINCREMENT, row_number INTEGER, name TEXT, leader TEXT, phone TEXT, desk_count INTEGER, lockers TEXT, power_strips TEXT, joined_at TEXT, warning TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, row_number INTEGER, code TEXT, full_name TEXT NOT NULL, team_name TEXT, desks TEXT, lockers TEXT, power_strips TEXT, phone TEXT, national_id TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS lockers (id INTEGER PRIMARY KEY AUTOINCREMENT, locker_number INTEGER, status TEXT, assigned_to TEXT, delivered_at TEXT, key_number TEXT, spare_key TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS plans (id INTEGER PRIMARY KEY AUTOINCREMENT, plan_number INTEGER, status TEXT, title TEXT, proposed_budget INTEGER, cost_type TEXT, schedule TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS charges (id INTEGER PRIMARY KEY AUTOINCREMENT, fiscal_year TEXT, team_name TEXT, leader TEXT, desk_count INTEGER, month_index INTEGER, month_name TEXT, amount INTEGER, note TEXT, charge_rate INTEGER, rent_rate INTEGER, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS financial_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, sheet_name TEXT, petty_cash_holder TEXT, petty_cash_number TEXT, previous_balance INTEGER, new_deposit INTEGER, total_balance INTEGER, received_at TEXT, from_date TEXT, to_date TEXT, source_file TEXT);
            CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NOT NULL, row_number INTEGER, invoice_count TEXT, tx_date TEXT, description TEXT, amount INTEGER, notes TEXT, category TEXT, suspected_amount_note INTEGER);
            CREATE TABLE IF NOT EXISTS import_warnings (id INTEGER PRIMARY KEY AUTOINCREMENT, file_name TEXT, sheet_name TEXT, row_number INTEGER, message TEXT NOT NULL, payload TEXT);"
        );
    }
}
