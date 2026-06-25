<?php

declare(strict_types=1);

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            self::migrateSqlite($pdo);
        } else {
            self::migrateMysql($pdo);
        }
        self::ensureColumns($pdo);
        self::dropLegacyColumns($pdo);
        self::seedDesks($pdo);
    }

    public static function reset(PDO $pdo): void
    {
        $tables = [
            'import_backup_items',
            'import_backups',
            'member_desks',
            'transactions',
            'charges',
            'team_rates',
            'rate_settings',
            'plans',
            'lockers',
            'members',
            'desks',
            'teams',
            'import_warnings',
            'import_runs',
        ];

        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$isSqlite) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach ($tables as $table) {
            if ($isSqlite) {
                $pdo->exec("DELETE FROM {$table}");
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
            } else {
                $pdo->exec("TRUNCATE TABLE {$table}");
            }
        }

        if (!$isSqlite) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public static function hasData(PDO $pdo): bool
    {
        foreach (['teams', 'members', 'charges', 'transactions', 'lockers'] as $table) {
            if ((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() > 0) {
                return true;
            }
        }

        return (int) $pdo->query('SELECT COUNT(*) FROM import_runs')->fetchColumn() > 0;
    }

    private static function migrateMysql(PDO $pdo): void
    {
        $sql = [
            "CREATE TABLE IF NOT EXISTS import_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source_files TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS teams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_code VARCHAR(32) NULL,
                entity_type VARCHAR(32) NOT NULL DEFAULT 'team',
                name VARCHAR(255) NULL,
                leader VARCHAR(255) NULL,
                phone VARCHAR(64) NULL,
                joined_at VARCHAR(32) NULL,
                warning TEXT NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                UNIQUE KEY uniq_teams_entity_code (entity_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS desks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                number INT NOT NULL,
                row_index INT NOT NULL,
                col_index INT NOT NULL,
                team_id INT NULL,
                usage_type VARCHAR(32) NOT NULL DEFAULT 'informal',
                formal_seats INT NOT NULL DEFAULT 0,
                informal_seats INT NOT NULL DEFAULT 0,
                notes TEXT NULL,
                UNIQUE KEY uniq_desks_number (number),
                INDEX idx_desks_team (team_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_code VARCHAR(32) NULL,
                team_id INT NULL,
                access_code VARCHAR(64) NULL,
                full_name VARCHAR(255) NOT NULL,
                phone VARCHAR(64) NULL,
                national_id VARCHAR(64) NULL,
                locker_id INT NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                UNIQUE KEY uniq_members_member_code (member_code),
                INDEX idx_members_team_id (team_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS member_desks (
                member_id INT NOT NULL,
                desk_id INT NOT NULL,
                PRIMARY KEY (member_id, desk_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS lockers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                locker_number INT NOT NULL,
                team_id INT NULL,
                member_id INT NULL,
                status VARCHAR(64) NULL,
                delivered_at VARCHAR(32) NULL,
                key_number VARCHAR(64) NULL,
                spare_key VARCHAR(64) NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                UNIQUE KEY uniq_lockers_number (locker_number),
                INDEX idx_lockers_team_id (team_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                plan_code VARCHAR(32) NULL,
                status VARCHAR(64) NULL,
                priority VARCHAR(32) NULL,
                title TEXT NULL,
                owner_team_id INT NULL,
                proposed_budget BIGINT NULL,
                start_date VARCHAR(32) NULL,
                end_date VARCHAR(32) NULL,
                progress INT NULL,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                UNIQUE KEY uniq_plans_plan_code (plan_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS rate_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fiscal_year VARCHAR(32) NULL,
                title VARCHAR(255) NULL,
                charge_rate BIGINT NULL,
                informal_rent_rate BIGINT NULL,
                effective_from VARCHAR(32) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rate_settings_year (fiscal_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS team_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT NOT NULL,
                fiscal_year VARCHAR(32) NOT NULL,
                charge_rate BIGINT NULL,
                informal_rent_rate BIGINT NULL,
                notes TEXT NULL,
                UNIQUE KEY uniq_team_rates (team_id, fiscal_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS charges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT NULL,
                team_name VARCHAR(255) NULL,
                fiscal_year VARCHAR(32) NULL,
                month_index INT NULL,
                month_name VARCHAR(32) NULL,
                charge_amount BIGINT NULL,
                rent_amount BIGINT NULL,
                amount BIGINT NULL,
                note TEXT NULL,
                source_file VARCHAR(255) NULL,
                source_sheet VARCHAR(255) NULL,
                INDEX idx_charges_team_id (team_id),
                INDEX idx_charges_year_month (fiscal_year, month_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tx_date VARCHAR(32) NULL,
                description TEXT NULL,
                amount BIGINT NULL,
                category VARCHAR(64) NULL,
                team_id INT NULL,
                fiscal_year VARCHAR(32) NULL,
                month_index INT NULL,
                confirmed TINYINT NOT NULL DEFAULT 1,
                notes TEXT NULL,
                source_file VARCHAR(255) NULL,
                INDEX idx_transactions_team (team_id),
                INDEX idx_transactions_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS import_warnings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(255) NULL,
                sheet_name VARCHAR(255) NULL,
                source_row INT NULL,
                message TEXT NOT NULL,
                payload TEXT NULL
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
        ];

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function migrateSqlite(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS import_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, source_files TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS teams (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_code TEXT, entity_type TEXT NOT NULL DEFAULT 'team', name TEXT, leader TEXT, phone TEXT, joined_at TEXT, warning TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS desks (id INTEGER PRIMARY KEY AUTOINCREMENT, number INTEGER NOT NULL UNIQUE, row_index INTEGER NOT NULL, col_index INTEGER NOT NULL, team_id INTEGER, usage_type TEXT NOT NULL DEFAULT 'informal', formal_seats INTEGER NOT NULL DEFAULT 0, informal_seats INTEGER NOT NULL DEFAULT 0, notes TEXT);
            CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT, team_id INTEGER, access_code TEXT, full_name TEXT NOT NULL, phone TEXT, national_id TEXT, locker_id INTEGER, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS member_desks (member_id INTEGER NOT NULL, desk_id INTEGER NOT NULL, PRIMARY KEY (member_id, desk_id));
            CREATE TABLE IF NOT EXISTS lockers (id INTEGER PRIMARY KEY AUTOINCREMENT, locker_number INTEGER NOT NULL UNIQUE, team_id INTEGER, member_id INTEGER, status TEXT, delivered_at TEXT, key_number TEXT, spare_key TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS plans (id INTEGER PRIMARY KEY AUTOINCREMENT, plan_code TEXT, status TEXT, priority TEXT, title TEXT, owner_team_id INTEGER, proposed_budget INTEGER, start_date TEXT, end_date TEXT, progress INTEGER, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS rate_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, fiscal_year TEXT, title TEXT, charge_rate INTEGER, informal_rent_rate INTEGER, effective_from TEXT, notes TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS team_rates (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER NOT NULL, fiscal_year TEXT NOT NULL, charge_rate INTEGER, informal_rent_rate INTEGER, notes TEXT, UNIQUE(team_id, fiscal_year));
            CREATE TABLE IF NOT EXISTS charges (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, fiscal_year TEXT, team_name TEXT, month_index INTEGER, month_name TEXT, charge_amount INTEGER, rent_amount INTEGER, amount INTEGER, note TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, tx_date TEXT, description TEXT, amount INTEGER, category TEXT, team_id INTEGER, fiscal_year TEXT, month_index INTEGER, confirmed INTEGER NOT NULL DEFAULT 1, notes TEXT, source_file TEXT);
            CREATE TABLE IF NOT EXISTS import_warnings (id INTEGER PRIMARY KEY AUTOINCREMENT, file_name TEXT, sheet_name TEXT, source_row INTEGER, message TEXT NOT NULL, payload TEXT);
            CREATE TABLE IF NOT EXISTS import_backups (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, reason TEXT, summary TEXT);
            CREATE TABLE IF NOT EXISTS import_backup_items (id INTEGER PRIMARY KEY AUTOINCREMENT, backup_id INTEGER NOT NULL, table_name TEXT NOT NULL, row_id INTEGER, payload TEXT NOT NULL);"
        );
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            'teams' => [
                'entity_code' => 'VARCHAR(32) NULL',
                'entity_type' => "VARCHAR(32) NOT NULL DEFAULT 'team'",
            ],
            'members' => [
                'member_code' => 'VARCHAR(32) NULL',
                'access_code' => 'VARCHAR(64) NULL',
                'locker_id' => 'INT NULL',
            ],
            'lockers' => [
                'team_id' => 'INT NULL',
                'member_id' => 'INT NULL',
            ],
            'desks' => [
                'usage_type' => "VARCHAR(32) NOT NULL DEFAULT 'informal'",
                'formal_seats' => 'INT NOT NULL DEFAULT 0',
                'informal_seats' => 'INT NOT NULL DEFAULT 0',
            ],
            'plans' => [
                'plan_code' => 'VARCHAR(32) NULL',
                'priority' => 'VARCHAR(32) NULL',
                'owner_team_id' => 'INT NULL',
                'start_date' => 'VARCHAR(32) NULL',
                'end_date' => 'VARCHAR(32) NULL',
                'progress' => 'INT NULL',
            ],
            'rate_settings' => [
                'informal_rent_rate' => 'BIGINT NULL',
            ],
            'charges' => [
                'charge_amount' => 'BIGINT NULL',
                'rent_amount' => 'BIGINT NULL',
                'team_name' => 'VARCHAR(255) NULL',
            ],
            'transactions' => [
                'team_id' => 'INT NULL',
                'fiscal_year' => 'VARCHAR(32) NULL',
                'month_index' => 'INT NULL',
                'confirmed' => 'TINYINT NOT NULL DEFAULT 1',
                'source_file' => 'VARCHAR(255) NULL',
            ],
            'import_warnings' => [
                'source_row' => 'INT NULL',
            ],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $definition) {
                if (!self::columnExists($pdo, $table, $column)) {
                    $type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                        ? self::sqliteType($definition)
                        : $definition;
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . Sql::quoteIdentifier($column) . ' ' . $type);
                }
            }
        }

        if (self::columnExists($pdo, 'members', 'code') && self::columnExists($pdo, 'members', 'access_code')) {
            $pdo->exec("UPDATE members SET access_code = code WHERE (access_code IS NULL OR access_code = '') AND code IS NOT NULL");
        }
    }

    private static function dropLegacyColumns(PDO $pdo): void
    {
        $drops = [
            'teams' => ['row_number', 'lockers', 'power_strips'],
            'rate_settings' => ['rent_rate'],
        ];

        foreach ($drops as $table => $columns) {
            foreach ($columns as $column) {
                if (!self::columnExists($pdo, $table, $column)) {
                    continue;
                }
                try {
                    $pdo->exec(
                        'ALTER TABLE ' . $table . ' DROP COLUMN ' . Sql::quoteIdentifier($column)
                    );
                } catch (PDOException) {
                    // نسخه‌های قدیمی SQLite یا محدودیت میزبان — فیلتر API/UI همچنان فعال است.
                }
            }
        }
    }

    private static function sqliteType(string $definition): string
    {
        return str_replace(
            ['VARCHAR(32) NULL', 'VARCHAR(64) NULL', 'VARCHAR(255) NULL', 'BIGINT NULL', 'INT NULL', "VARCHAR(32) NOT NULL DEFAULT 'team'", 'TINYINT NOT NULL DEFAULT 1'],
            ['TEXT', 'TEXT', 'TEXT', 'INTEGER', 'INTEGER', "TEXT NOT NULL DEFAULT 'team'", 'INTEGER NOT NULL DEFAULT 1'],
            $definition
        );
    }

    public static function seedDesks(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM desks')->fetchColumn();
        if ($count >= 24) {
            return;
        }

        for ($number = 1; $number <= 24; $number++) {
            $rowIndex = (int) ceil($number / 8);
            $colIndex = (($number - 1) % 8) + 1;
            $statement = $pdo->prepare(
                'INSERT INTO desks (number, row_index, col_index, usage_type, formal_seats, informal_seats)
                 SELECT :number, :row_index, :col_index, :usage_type, 0, 0
                 WHERE NOT EXISTS (SELECT 1 FROM desks WHERE number = :number_check)'
            );
            $statement->execute([
                'number' => $number,
                'row_index' => $rowIndex,
                'col_index' => $colIndex,
                'usage_type' => 'informal',
                'number_check' => $number,
            ]);
        }
    }

    /**
     * @param list<int> $numbers
     */
    public static function ensureLockerNumbers(PDO $pdo, array $numbers): void
    {
        foreach ($numbers as $number) {
            if ($number < 1) {
                continue;
            }
            $statement = $pdo->prepare(
                "INSERT INTO lockers (locker_number, status, source_file, source_sheet)
                 SELECT :number, 'خالی', 'system', 'catalog'
                 WHERE NOT EXISTS (SELECT 1 FROM lockers WHERE locker_number = :number_check)"
            );
            $statement->execute(['number' => $number, 'number_check' => $number]);
        }
    }

    public static function seedLockerSlots(PDO $pdo, int $count = 30): void
    {
        $existing = (int) $pdo->query('SELECT COUNT(*) FROM lockers')->fetchColumn();
        for ($number = $existing + 1; $number <= $count; $number++) {
            $statement = $pdo->prepare(
                "INSERT INTO lockers (locker_number, status, source_file, source_sheet)
                 SELECT :number, 'خالی', 'system', 'seed'
                 WHERE NOT EXISTS (SELECT 1 FROM lockers WHERE locker_number = :number_check)"
            );
            $statement->execute(['number' => $number, 'number_check' => $number]);
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

        $statement = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $statement->execute(['column' => $column]);

        return $statement->fetch() !== false;
    }
}
