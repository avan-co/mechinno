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
                `row_number` INT NULL,
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
                team_id INT NULL,
                `row_number` INT NULL,
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
                source_sheet VARCHAR(255) NULL,
                INDEX idx_members_team_id (team_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS lockers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT NULL,
                locker_number INT NULL,
                status VARCHAR(64) NULL,
                assigned_to VARCHAR(255) NULL,
                delivered_at VARCHAR(32) NULL,
                key_number VARCHAR(64) NULL,
                spare_key VARCHAR(64) NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                INDEX idx_lockers_team_id (team_id)
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
                team_id INT NULL,
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
                INDEX idx_charges_team_id (team_id),
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
                `row_number` INT NULL,
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
                `row_number` INT NULL,
                message TEXT NOT NULL,
                payload TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS rate_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fiscal_year VARCHAR(32) NULL,
                title VARCHAR(255) NULL,
                charge_rate BIGINT NULL,
                rent_rate BIGINT NULL,
                effective_from VARCHAR(32) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rate_settings_year (fiscal_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS import_backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reason VARCHAR(255) NULL,
                summary TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS import_backup_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_id INT NOT NULL,
                table_name VARCHAR(64) NOT NULL,
                row_id INT NULL,
                payload LONGTEXT NOT NULL,
                INDEX idx_backup_items_backup (backup_id),
                INDEX idx_backup_items_table (table_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS team_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT NULL,
                fiscal_year VARCHAR(32) NULL,
                month_index INT NULL,
                month_name VARCHAR(32) NULL,
                amount_due BIGINT NOT NULL DEFAULT 0,
                amount_paid BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'بدهکار',
                paid_at VARCHAR(32) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_team_payments_team (team_id),
                INDEX idx_team_payments_period (fiscal_year, month_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
        self::ensureColumns($pdo);
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
            CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, row_number INTEGER, code TEXT, full_name TEXT NOT NULL, team_name TEXT, desks TEXT, lockers TEXT, power_strips TEXT, phone TEXT, national_id TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS lockers (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, locker_number INTEGER, status TEXT, assigned_to TEXT, delivered_at TEXT, key_number TEXT, spare_key TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS plans (id INTEGER PRIMARY KEY AUTOINCREMENT, plan_number INTEGER, status TEXT, title TEXT, proposed_budget INTEGER, cost_type TEXT, schedule TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS charges (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, fiscal_year TEXT, team_name TEXT, leader TEXT, desk_count INTEGER, month_index INTEGER, month_name TEXT, amount INTEGER, note TEXT, charge_rate INTEGER, rent_rate INTEGER, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS financial_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, sheet_name TEXT, petty_cash_holder TEXT, petty_cash_number TEXT, previous_balance INTEGER, new_deposit INTEGER, total_balance INTEGER, received_at TEXT, from_date TEXT, to_date TEXT, source_file TEXT);
            CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NOT NULL, row_number INTEGER, invoice_count TEXT, tx_date TEXT, description TEXT, amount INTEGER, notes TEXT, category TEXT, suspected_amount_note INTEGER);
            CREATE TABLE IF NOT EXISTS import_warnings (id INTEGER PRIMARY KEY AUTOINCREMENT, file_name TEXT, sheet_name TEXT, row_number INTEGER, message TEXT NOT NULL, payload TEXT);
            CREATE TABLE IF NOT EXISTS rate_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, fiscal_year TEXT, title TEXT, charge_rate INTEGER, rent_rate INTEGER, effective_from TEXT, notes TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS import_backups (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, reason TEXT, summary TEXT);
            CREATE TABLE IF NOT EXISTS import_backup_items (id INTEGER PRIMARY KEY AUTOINCREMENT, backup_id INTEGER NOT NULL, table_name TEXT NOT NULL, row_id INTEGER, payload TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS team_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, fiscal_year TEXT, month_index INTEGER, month_name TEXT, amount_due INTEGER NOT NULL DEFAULT 0, amount_paid INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'بدهکار', paid_at TEXT, notes TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);"
        );
        self::ensureColumns($pdo);
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            'members' => ['team_id' => 'INT NULL'],
            'lockers' => ['team_id' => 'INT NULL'],
            'charges' => ['team_id' => 'INT NULL'],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $definition) {
                if (!self::columnExists($pdo, $table, $column)) {
                    $type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                        ? str_replace('INT NULL', 'INTEGER', $definition)
                        : $definition;
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
                }
            }
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query("PRAGMA table_info({$table})");
            foreach ($statement->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $statement = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $statement->execute(['column' => $column]);

        return $statement->fetch() !== false;
    }
}
