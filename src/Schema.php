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
        self::ensureWorkflowTables($pdo);
        self::dropLegacyColumns($pdo);
        self::dropUnusedTables($pdo);
        self::seedDesks($pdo);
        self::seedDeskAssignments($pdo);
    }

    public static function reset(PDO $pdo): void
    {
        $tables = [
            'development_plans',
            'panel_users',
            'transactions',
            'charges',
            'rate_settings',
            'locker_requests',
            'desk_assignments',
            'lockers',
            'members',
            'desks',
            'teams',
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

        return false;
    }

    private static function dropUnusedTables(PDO $pdo): void
    {
        foreach ([
            'import_backup_items',
            'import_backups',
            'import_warnings',
            'import_runs',
            'member_desks',
            'plans',
            'team_rates',
        ] as $table) {
            try {
                $pdo->exec('DROP TABLE IF EXISTS ' . $table);
            } catch (PDOException) {
            }
        }
    }

    private static function migrateMysql(PDO $pdo): void
    {
        $sql = [
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
            "CREATE TABLE IF NOT EXISTS panel_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                password_plain VARCHAR(64) NULL,
                role VARCHAR(32) NOT NULL,
                team_id INT NULL,
                full_name VARCHAR(255) NULL,
                is_active TINYINT NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_panel_users_username (username),
                UNIQUE KEY uniq_panel_users_team (team_id, role),
                INDEX idx_panel_users_team (team_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS development_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                category VARCHAR(32) NOT NULL DEFAULT 'idea',
                priority VARCHAR(16) NOT NULL DEFAULT 'medium',
                status VARCHAR(32) NOT NULL DEFAULT 'open',
                due_date VARCHAR(32) NULL,
                notes TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NULL,
                INDEX idx_dev_plans_status (status),
                INDEX idx_dev_plans_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS center_settings (
                id INT PRIMARY KEY,
                bank_name VARCHAR(255) NULL,
                account_holder VARCHAR(255) NULL,
                account_number VARCHAR(64) NULL,
                card_number VARCHAR(32) NULL,
                sheba VARCHAR(32) NULL,
                payment_guide TEXT NULL,
                updated_at VARCHAR(32) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function migrateSqlite(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS teams (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_code TEXT, entity_type TEXT NOT NULL DEFAULT 'team', name TEXT, leader TEXT, phone TEXT, joined_at TEXT, warning TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS desks (id INTEGER PRIMARY KEY AUTOINCREMENT, number INTEGER NOT NULL UNIQUE, row_index INTEGER NOT NULL, col_index INTEGER NOT NULL, team_id INTEGER, usage_type TEXT NOT NULL DEFAULT 'informal', formal_seats INTEGER NOT NULL DEFAULT 0, informal_seats INTEGER NOT NULL DEFAULT 0, notes TEXT);
            CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT, team_id INTEGER, access_code TEXT, full_name TEXT NOT NULL, phone TEXT, national_id TEXT, locker_id INTEGER, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS lockers (id INTEGER PRIMARY KEY AUTOINCREMENT, locker_number INTEGER NOT NULL UNIQUE, team_id INTEGER, member_id INTEGER, status TEXT, delivered_at TEXT, key_number TEXT, spare_key TEXT, notes TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS rate_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, fiscal_year TEXT, title TEXT, charge_rate INTEGER, informal_rent_rate INTEGER, effective_from TEXT, notes TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS charges (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, fiscal_year TEXT, team_name TEXT, month_index INTEGER, month_name TEXT, charge_amount INTEGER, rent_amount INTEGER, amount INTEGER, note TEXT, source_file TEXT, source_sheet TEXT);
            CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, tx_date TEXT, description TEXT, amount INTEGER, category TEXT, team_id INTEGER, fiscal_year TEXT, month_index INTEGER, confirmed INTEGER NOT NULL DEFAULT 1, notes TEXT, source_file TEXT);
            CREATE TABLE IF NOT EXISTS import_warnings (id INTEGER PRIMARY KEY AUTOINCREMENT, file_name TEXT, sheet_name TEXT, source_row INTEGER, message TEXT NOT NULL, payload TEXT);
            CREATE TABLE IF NOT EXISTS import_backups (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, reason TEXT, summary TEXT);
            CREATE TABLE IF NOT EXISTS import_backup_items (id INTEGER PRIMARY KEY AUTOINCREMENT, backup_id INTEGER NOT NULL, table_name TEXT NOT NULL, row_id INTEGER, payload TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS panel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, password_plain TEXT, role TEXT NOT NULL, team_id INTEGER, full_name TEXT, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(team_id, role));
            CREATE TABLE IF NOT EXISTS development_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT, category TEXT NOT NULL DEFAULT 'idea', priority TEXT NOT NULL DEFAULT 'medium', status TEXT NOT NULL DEFAULT 'open', due_date TEXT, notes TEXT, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT, depends_on_id INTEGER, estimated_cost INTEGER, estimated_revenue INTEGER, related_section TEXT);
            CREATE TABLE IF NOT EXISTS center_settings (id INTEGER PRIMARY KEY, bank_name TEXT, account_holder TEXT, account_number TEXT, card_number TEXT, sheba TEXT, payment_guide TEXT, updated_at TEXT);"
        );
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            'teams' => [
                'entity_code' => 'VARCHAR(32) NULL',
                'entity_type' => "VARCHAR(32) NOT NULL DEFAULT 'team'",
                'contract_start' => 'VARCHAR(32) NULL',
                'contract_end' => 'VARCHAR(32) NULL',
            ],
            'members' => [
                'member_code' => 'VARCHAR(32) NULL',
                'access_code' => 'VARCHAR(64) NULL',
                'locker_id' => 'INT NULL',
                'approval_status' => "VARCHAR(32) NOT NULL DEFAULT 'approved'",
                'submitted_at' => 'VARCHAR(32) NULL',
                'reviewed_at' => 'VARCHAR(32) NULL',
                'rejection_reason' => 'TEXT NULL',
                'wants_access' => 'TINYINT NOT NULL DEFAULT 0',
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
                'payment_status' => "VARCHAR(32) NULL DEFAULT 'approved'",
                'payment_reference' => 'VARCHAR(128) NULL',
                'announced_at' => 'VARCHAR(32) NULL',
                'reviewed_at' => 'VARCHAR(32) NULL',
            ],
            'panel_users' => [
                'password_plain' => 'VARCHAR(64) NULL',
            ],
            'development_plans' => [
                'depends_on_id' => 'INT NULL',
                'estimated_cost' => 'BIGINT NULL',
                'estimated_revenue' => 'BIGINT NULL',
                'related_section' => 'VARCHAR(32) NULL',
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

        if (self::columnExists($pdo, 'members', 'approval_status')) {
            $pdo->exec("UPDATE members SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = ''");
        }
        if (self::columnExists($pdo, 'transactions', 'payment_status')) {
            $pdo->exec("UPDATE transactions SET payment_status = 'approved' WHERE payment_status IS NULL OR payment_status = ''");
            $pdo->exec("UPDATE transactions SET payment_status = 'pending', confirmed = 0 WHERE category = 'واریز تیم' AND confirmed = 0");
            $pdo->exec("UPDATE transactions SET payment_status = 'approved' WHERE category = 'واریز تیم' AND confirmed = 1 AND payment_status = 'pending'");
        }

        self::seedCenterSettings($pdo);
        self::backfillTeamContracts($pdo);
    }

    private static function backfillTeamContracts(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'teams', 'contract_start')) {
            return;
        }
        $today = JalaliDate::todayParts();
        $yearStart = sprintf('%04d/01/01', $today['year']);
        $teams = $pdo->query('SELECT id, joined_at, contract_start, contract_end FROM teams')->fetchAll();
        foreach ($teams as $team) {
            $start = JalaliDate::tryNormalize((string) ($team['contract_start'] ?? ''));
            if ($start === '') {
                $joined = JalaliDate::tryNormalize((string) ($team['joined_at'] ?? ''));
                $start = $joined !== '' ? $joined : $yearStart;
            }
            $end = JalaliDate::tryNormalize((string) ($team['contract_end'] ?? ''));
            if ($end === '') {
                $endYear = (int) substr($start, 0, 4);
                $end = sprintf('%04d/12/29', $endYear);
            }
            $pdo->prepare('UPDATE teams SET contract_start = :start, contract_end = :end WHERE id = :id')
                ->execute(['start' => $start, 'end' => $end, 'id' => (int) $team['id']]);
        }
    }

    private static function ensureWorkflowTables(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS locker_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    member_id INTEGER,
                    notes TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    submitted_at TEXT,
                    reviewed_at TEXT,
                    rejection_reason TEXT,
                    locker_id INTEGER
                );
                CREATE TABLE IF NOT EXISTS desk_assignments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    desk_id INTEGER NOT NULL,
                    desk_number INTEGER NOT NULL,
                    team_id INTEGER NOT NULL,
                    usage_type TEXT NOT NULL DEFAULT 'formal',
                    assigned_from TEXT NOT NULL,
                    assigned_until TEXT,
                    notes TEXT
                );"
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS locker_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    team_id INT NOT NULL,
                    member_id INT NULL,
                    notes TEXT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'pending',
                    submitted_at VARCHAR(32) NULL,
                    reviewed_at VARCHAR(32) NULL,
                    rejection_reason TEXT NULL,
                    locker_id INT NULL,
                    INDEX idx_locker_requests_team (team_id),
                    INDEX idx_locker_requests_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS desk_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    desk_id INT NOT NULL,
                    desk_number INT NOT NULL,
                    team_id INT NOT NULL,
                    usage_type VARCHAR(32) NOT NULL DEFAULT 'formal',
                    assigned_from VARCHAR(32) NOT NULL,
                    assigned_until VARCHAR(32) NULL,
                    notes TEXT NULL,
                    INDEX idx_desk_assignments_desk (desk_id),
                    INDEX idx_desk_assignments_team (team_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private static function seedDeskAssignments(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return;
        }
        $today = JalaliDate::todayParts();
        $fallbackFrom = sprintf('%04d/01/01', $today['year']);
        $desks = $pdo->query(
            'SELECT d.id, d.number, d.team_id, d.usage_type, d.notes, t.contract_end
             FROM desks d
             INNER JOIN teams t ON t.id = d.team_id
             WHERE d.team_id IS NOT NULL'
        )->fetchAll();
        foreach ($desks as $desk) {
            $deskId = (int) $desk['id'];
            $exists = $pdo->prepare(
                'SELECT id FROM desk_assignments WHERE desk_id = :desk_id AND assigned_until IS NULL LIMIT 1'
            );
            $exists->execute(['desk_id' => $deskId]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes)
                 VALUES (:desk_id, :desk_number, :team_id, :usage_type, :assigned_from, :assigned_until, :notes)'
            )->execute([
                'desk_id' => $deskId,
                'desk_number' => (int) $desk['number'],
                'team_id' => (int) $desk['team_id'],
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'assigned_from' => $fallbackFrom,
                'assigned_until' => null,
                'notes' => $desk['notes'] ?? null,
            ]);
        }
        self::normalizeDeskAssignments($pdo);
    }

    private static function normalizeDeskAssignments(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return;
        }
        $pdo->exec(
            'UPDATE desk_assignments SET assigned_until = NULL
             WHERE id IN (
                SELECT da.id FROM desk_assignments da
                INNER JOIN desks d ON d.id = da.desk_id AND d.team_id = da.team_id
                WHERE da.assigned_until IS NOT NULL
             )'
        );
        $duplicates = $pdo->query(
            'SELECT desk_id, MAX(id) AS keep_id
             FROM desk_assignments
             GROUP BY desk_id
             HAVING COUNT(*) > 1'
        )->fetchAll();
        foreach ($duplicates as $row) {
            $statement = $pdo->prepare(
                'DELETE FROM desk_assignments WHERE desk_id = :desk_id AND id <> :keep_id'
            );
            $statement->execute([
                'desk_id' => (int) $row['desk_id'],
                'keep_id' => (int) $row['keep_id'],
            ]);
        }
    }

    private static function seedCenterSettings(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'center_settings')) {
            return;
        }
        $exists = (int) $pdo->query('SELECT COUNT(*) FROM center_settings WHERE id = 1')->fetchColumn();
        if ($exists > 0) {
            return;
        }
        $today = JalaliDate::todayParts()['formatted'];
        $pdo->prepare(
            "INSERT INTO center_settings (id, bank_name, account_holder, account_number, card_number, sheba, payment_guide, updated_at)
             VALUES (1, '', '', '', '', '', :guide, :updated_at)"
        )->execute([
            'guide' => 'پس از واریز شارژ، مبلغ، تاریخ، سال مالی و ماه را در بخش «اعلام واریز» ثبت کنید تا مدیر مرکز تأیید کند.',
            'updated_at' => $today,
        ]);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
            $statement->execute(['name' => $table]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name'
        );
        $statement->execute(['name' => $table]);

        return (int) $statement->fetchColumn() > 0;
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
