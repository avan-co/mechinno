<?php

declare(strict_types=1);

final class Schema
{
    public const VERSION = 20;

    public static function migrate(PDO $pdo): void
    {
        if (self::storedVersion($pdo) >= self::VERSION) {
            self::ensureColumns($pdo);
            self::ensureMeetingRoomTables($pdo);
            self::ensureRoomClosedDaysTable($pdo);
            self::reconcileDeskAssignments($pdo);
            self::seedSmsPatterns($pdo);
            self::applyKnownPatternBodyIds($pdo);

            return;
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            self::migrateSqlite($pdo);
        } else {
            self::migrateMysql($pdo);
        }
        self::ensureColumns($pdo);
        self::ensureWorkflowTables($pdo);
        self::ensureMemberRequestsTable($pdo);
        self::ensureMeetingRoomTables($pdo);
        self::ensureRoomClosedDaysTable($pdo);
        self::migrateRoomSlotDefaults($pdo);
        self::ensureTeamContractsTable($pdo);
        self::ensureColumns($pdo);
        self::dropLegacyColumns($pdo);
        self::dropUnusedTables($pdo);
        self::ensureDataIntegrity($pdo);
        self::seedDesks($pdo);
        self::seedDeskAssignments($pdo);
        self::reconcileDeskAssignments($pdo);
        self::applySecurityHardening($pdo);
        self::seedSmsPatterns($pdo);
        self::applyKnownPatternBodyIds($pdo);
        self::setVersion($pdo, self::VERSION);
    }

    private static function storedVersion(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'center_settings')
            || !self::columnExists($pdo, 'center_settings', 'schema_version')) {
            return 0;
        }

        return (int) $pdo->query('SELECT schema_version FROM center_settings WHERE id = 1')->fetchColumn();
    }

    private static function setVersion(PDO $pdo, int $version): void
    {
        if (!self::columnExists($pdo, 'center_settings', 'schema_version')) {
            return;
        }

        $pdo->prepare('UPDATE center_settings SET schema_version = :version WHERE id = 1')
            ->execute(['version' => $version]);
    }

    private static function applySecurityHardening(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'panel_users')) {
            $pdo->exec("UPDATE panel_users SET password_plain = NULL WHERE password_plain IS NOT NULL AND password_plain <> ''");
        }

        if (!self::tableExists($pdo, 'center_settings')
            || !self::columnExists($pdo, 'center_settings', 'sms_password')
            || !app_configured()) {
            return;
        }

        $config = app_config();
        $stored = (string) $pdo->query('SELECT sms_password FROM center_settings WHERE id = 1')->fetchColumn();
        if ($stored !== '' && !SecretVault::isEncrypted($stored)) {
            $pdo->prepare('UPDATE center_settings SET sms_password = :password WHERE id = 1')
                ->execute(['password' => SecretVault::encrypt($stored, $config)]);
        }
    }

    /**
     * Align desk_assignments with current code: fill missing end dates and remove duplicates once.
     */
    public static function reconcileDeskAssignments(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return;
        }

        self::backfillDeskAssignmentEnds($pdo);
        if (self::needsDeskAssignmentNormalization($pdo)) {
            self::normalizeDeskAssignments($pdo);
            self::markDeskAssignmentNormalizationDone($pdo);
        }
    }

    public static function reset(PDO $pdo): void
    {
        $tables = [
            'sms_logs',
            'room_reservations',
            'room_closed_days',
            'meeting_rooms',
            'development_plans',
            'panel_users',
            'transactions',
            'charges',
            'rate_settings',
            'locker_requests',
            'member_requests',
            'desk_assignments',
            'team_contracts',
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

        if (self::columnExists($pdo, 'center_settings', 'legacy_team_contracts_migrated')) {
            $pdo->exec('UPDATE center_settings SET legacy_team_contracts_migrated = 1 WHERE id = 1');
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
                INDEX idx_charges_year_month (fiscal_year, month_index),
                UNIQUE KEY uniq_charges_team_year_month (team_id, fiscal_year, month_index)
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
                'is_active' => 'TINYINT NOT NULL DEFAULT 1',
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
                'is_leader' => 'TINYINT NOT NULL DEFAULT 0',
            ],
            'center_settings' => [
                'schema_version' => 'INT NOT NULL DEFAULT 0',
                'sms_username' => 'VARCHAR(128) NULL',
                'sms_password' => 'VARCHAR(255) NULL',
                'sms_from_number' => 'VARCHAR(32) NULL',
                'sms_daily_limit' => 'INT NOT NULL DEFAULT 500',
                'sms_unit_cost' => 'BIGINT NOT NULL DEFAULT 0',
                'sms_updated_at' => 'VARCHAR(32) NULL',
                'sms_line_numbers' => 'TEXT NULL',
                'sms_lines_queried_at' => 'VARCHAR(32) NULL',
                'sms_charge_template' => 'TEXT NULL',
                'sms_workflow_templates' => 'TEXT NULL',
                'sms_history_synced_at' => 'VARCHAR(32) NULL',
                'sms_panel_credit' => 'BIGINT NULL',
                'sms_live_synced_at' => 'VARCHAR(32) NULL',
                'legacy_team_contracts_migrated' => 'TINYINT NOT NULL DEFAULT 0',
                'desk_assignments_normalized' => 'TINYINT NOT NULL DEFAULT 0',
                'room_auto_approve' => 'TINYINT NOT NULL DEFAULT 1',
                'room_max_advance_days' => 'INT NOT NULL DEFAULT 14',
                'room_max_hours_per_day' => 'INT NOT NULL DEFAULT 2',
                'room_slot_minutes' => 'INT NOT NULL DEFAULT 30',
                'room_public_enabled' => 'TINYINT NOT NULL DEFAULT 1',
            ],
            'lockers' => [
                'team_id' => 'INT NULL',
                'member_id' => 'INT NULL',
                'key_number' => 'VARCHAR(64) NULL',
                'spare_key' => 'VARCHAR(64) NULL',
            ],
            'desks' => [
                'usage_type' => "VARCHAR(32) NOT NULL DEFAULT 'informal'",
                'formal_seats' => 'INT NOT NULL DEFAULT 0',
                'informal_seats' => 'INT NOT NULL DEFAULT 0',
            ],
            'rate_settings' => [
                'informal_rent_rate' => 'BIGINT NULL',
                'effective_from' => 'VARCHAR(32) NULL',
            ],
            'team_contracts' => [
                'charge_rate_override' => 'BIGINT NULL',
                'informal_rent_rate_override' => 'BIGINT NULL',
                'formal_contract_amount' => 'BIGINT NOT NULL DEFAULT 0',
            ],
            'desk_assignments' => [
                'charge_exempt' => 'TINYINT NOT NULL DEFAULT 0',
                'rent_exempt' => 'TINYINT NOT NULL DEFAULT 0',
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
                'payment_plan' => 'TEXT NULL',
                'finance_subtype' => 'VARCHAR(64) NULL',
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
            if (!self::tableExists($pdo, $table)) {
                continue;
            }
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
        if (self::columnExists($pdo, 'teams', 'is_active')) {
            $pdo->exec('UPDATE teams SET is_active = 1 WHERE is_active IS NULL');
        }
        if (self::columnExists($pdo, 'transactions', 'payment_status')) {
            $pdo->exec("UPDATE transactions SET payment_status = 'approved' WHERE payment_status IS NULL OR payment_status = ''");
            $pdo->exec("UPDATE transactions SET payment_status = 'pending', confirmed = 0 WHERE category = 'واریز تیم' AND confirmed = 0");
            $pdo->exec("UPDATE transactions SET payment_status = 'approved' WHERE category = 'واریز تیم' AND confirmed = 1 AND payment_status = 'pending'");
        }

        self::seedCenterSettings($pdo);
        self::ensureSmsTables($pdo);
        (new TeamContracts($pdo))->syncAllTeamActiveStatuses();
        TeamLeaders::backfillAll($pdo);
        CenterLedger::purgeAccrualMirrorEntries($pdo);
    }

    /**
     * Deduplicate critical rows and ensure uniqueness / performance indexes.
     */
    private static function ensureDataIntegrity(PDO $pdo): void
    {
        self::deduplicateCharges($pdo);
        self::ensureUniqueIndex(
            $pdo,
            'charges',
            'uniq_charges_team_year_month',
            ['team_id', 'fiscal_year', 'month_index']
        );
        self::ensureIndex($pdo, 'transactions', 'idx_transactions_team', ['team_id']);
        self::ensureIndex($pdo, 'transactions', 'idx_transactions_category', ['category']);
        if (self::columnExists($pdo, 'transactions', 'payment_status')) {
            self::ensureIndex($pdo, 'transactions', 'idx_transactions_status', ['payment_status']);
            self::ensureIndex($pdo, 'transactions', 'idx_transactions_team_status', ['team_id', 'payment_status']);
            if (self::columnExists($pdo, 'transactions', 'confirmed')) {
                self::ensureIndex(
                    $pdo,
                    'transactions',
                    'idx_transactions_cash',
                    ['category', 'payment_status', 'confirmed']
                );
            }
        }
        self::ensureIndex($pdo, 'charges', 'idx_charges_team_id', ['team_id']);
        self::ensureIndex($pdo, 'charges', 'idx_charges_year_month', ['fiscal_year', 'month_index']);
        self::ensureIndex($pdo, 'members', 'idx_members_team_id', ['team_id']);
        self::ensureIndex($pdo, 'desk_assignments', 'idx_desk_assignments_team', ['team_id']);
        self::ensureIndex($pdo, 'desk_assignments', 'idx_desk_assignments_desk', ['desk_id']);
    }

    private static function deduplicateCharges(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'charges')) {
            return;
        }

        $groups = $pdo->query(
            "SELECT team_id, fiscal_year, month_index, COUNT(*) AS cnt
             FROM charges
             WHERE team_id IS NOT NULL AND fiscal_year IS NOT NULL AND month_index IS NOT NULL
             GROUP BY team_id, fiscal_year, month_index
             HAVING COUNT(*) > 1"
        )->fetchAll();

        if ($groups === []) {
            return;
        }

        $select = $pdo->prepare(
            'SELECT id, source_file, amount, charge_amount, rent_amount, note
             FROM charges
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND month_index = :month_index
             ORDER BY id ASC'
        );
        $delete = $pdo->prepare('DELETE FROM charges WHERE id = :id');
        $update = $pdo->prepare(
            'UPDATE charges
             SET source_file = :source_file, amount = :amount, charge_amount = :charge_amount,
                 rent_amount = :rent_amount, note = :note
             WHERE id = :id'
        );

        foreach ($groups as $group) {
            $select->execute([
                'team_id' => (int) ($group['team_id'] ?? 0),
                'fiscal_year' => (string) ($group['fiscal_year'] ?? ''),
                'month_index' => (int) ($group['month_index'] ?? 0),
            ]);
            $rows = $select->fetchAll();
            if (count($rows) <= 1) {
                continue;
            }

            $keep = null;
            foreach ($rows as $row) {
                if ((string) ($row['source_file'] ?? '') === 'manual') {
                    $keep = $row;
                    break;
                }
            }
            if ($keep === null) {
                $keep = $rows[count($rows) - 1];
            }

            $keepId = (int) ($keep['id'] ?? 0);
            $update->execute([
                'source_file' => (string) ($keep['source_file'] ?? 'manual') === 'system' ? 'system' : 'manual',
                'amount' => (int) ($keep['amount'] ?? 0),
                'charge_amount' => (int) ($keep['charge_amount'] ?? 0),
                'rent_amount' => (int) ($keep['rent_amount'] ?? 0),
                'note' => (string) ($keep['note'] ?? ''),
                'id' => $keepId,
            ]);

            foreach ($rows as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0 && $rowId !== $keepId) {
                    $delete->execute(['id' => $rowId]);
                }
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, array $columns): void
    {
        if (!self::tableExists($pdo, $table) || self::indexExists($pdo, $table, $indexName)) {
            return;
        }

        $columnSql = implode(', ', array_map(
            static fn (string $column): string => Sql::quoteIdentifier($column),
            $columns
        ));

        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec(
                    'CREATE UNIQUE INDEX IF NOT EXISTS ' . $indexName
                    . ' ON ' . Sql::quoteIdentifier($table) . ' (' . $columnSql . ')'
                );
            } else {
                $pdo->exec(
                    'ALTER TABLE ' . Sql::quoteIdentifier($table)
                    . ' ADD UNIQUE KEY ' . $indexName . ' (' . $columnSql . ')'
                );
            }
        } catch (PDOException) {
            // Host may lack ALTER privilege; application upsert still prevents new duplicates.
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function ensureIndex(PDO $pdo, string $table, string $indexName, array $columns): void
    {
        if (!self::tableExists($pdo, $table) || self::indexExists($pdo, $table, $indexName)) {
            return;
        }

        $columnSql = implode(', ', array_map(
            static fn (string $column): string => Sql::quoteIdentifier($column),
            $columns
        ));

        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec(
                    'CREATE INDEX IF NOT EXISTS ' . $indexName
                    . ' ON ' . Sql::quoteIdentifier($table) . ' (' . $columnSql . ')'
                );
            } else {
                $pdo->exec(
                    'ALTER TABLE ' . Sql::quoteIdentifier($table)
                    . ' ADD INDEX ' . $indexName . ' (' . $columnSql . ')'
                );
            }
        } catch (PDOException) {
            // Best-effort on restricted hosts.
        }
    }

    private static function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND name = :name");
                $statement->execute(['name' => $indexName]);

                return $statement->fetchColumn() !== false;
            }

            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $indexName]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function needsDeskAssignmentNormalization(PDO $pdo): bool
    {
        if (!self::tableExists($pdo, 'center_settings')
            || !self::columnExists($pdo, 'center_settings', 'desk_assignments_normalized')) {
            return true;
        }

        $flag = (int) $pdo->query(
            'SELECT desk_assignments_normalized FROM center_settings WHERE id = 1'
        )->fetchColumn();
        if ($flag !== 1) {
            return true;
        }

        // Re-run only when duplicate desk-year rows appear (cheap existence check).
        return self::hasDuplicateDeskAssignments($pdo);
    }

    private static function hasDuplicateDeskAssignments(PDO $pdo): bool
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return false;
        }

        $rows = $pdo->query(
            'SELECT desk_id, assigned_from FROM desk_assignments ORDER BY desk_id, assigned_from, id'
        )->fetchAll();
        $seen = [];
        foreach ($rows as $row) {
            $deskId = (int) ($row['desk_id'] ?? 0);
            $fiscalYear = JalaliDate::fiscalYearFromDate((string) ($row['assigned_from'] ?? ''));
            if ($deskId <= 0 || $fiscalYear === '') {
                continue;
            }
            $key = $deskId . ':' . $fiscalYear;
            if (isset($seen[$key])) {
                return true;
            }
            $seen[$key] = true;
        }

        return false;
    }

    private static function markDeskAssignmentNormalizationDone(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'center_settings')
            || !self::columnExists($pdo, 'center_settings', 'desk_assignments_normalized')) {
            return;
        }

        $pdo->exec('UPDATE center_settings SET desk_assignments_normalized = 1 WHERE id = 1');
    }

    private static function ensureTeamContractsTable(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS team_contracts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    fiscal_year TEXT NOT NULL,
                    contract_start TEXT NOT NULL,
                    contract_end TEXT NOT NULL,
                    formal_contract_amount INTEGER NOT NULL DEFAULT 0,
                    notes TEXT,
                    created_at TEXT,
                    UNIQUE(team_id, fiscal_year)
                )'
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS team_contracts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    team_id INT NOT NULL,
                    fiscal_year VARCHAR(8) NOT NULL,
                    contract_start VARCHAR(32) NOT NULL,
                    contract_end VARCHAR(32) NOT NULL,
                    formal_contract_amount BIGINT NOT NULL DEFAULT 0,
                    notes TEXT NULL,
                    created_at VARCHAR(32) NULL,
                    UNIQUE KEY uniq_team_contract_year (team_id, fiscal_year),
                    INDEX idx_team_contracts_year (fiscal_year)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
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
                );
                CREATE TABLE IF NOT EXISTS member_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    member_id INTEGER NOT NULL,
                    request_type TEXT NOT NULL,
                    full_name TEXT,
                    phone TEXT,
                    national_id TEXT,
                    wants_access INTEGER,
                    notes TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    submitted_at TEXT,
                    reviewed_at TEXT,
                    rejection_reason TEXT
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
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS member_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    team_id INT NOT NULL,
                    member_id INT NOT NULL,
                    request_type VARCHAR(16) NOT NULL,
                    full_name VARCHAR(255) NULL,
                    phone VARCHAR(64) NULL,
                    national_id VARCHAR(32) NULL,
                    wants_access TINYINT NULL,
                    notes TEXT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'pending',
                    submitted_at VARCHAR(32) NULL,
                    reviewed_at VARCHAR(32) NULL,
                    rejection_reason TEXT NULL,
                    INDEX idx_member_requests_team (team_id),
                    INDEX idx_member_requests_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private static function ensureMemberRequestsTable(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'member_requests')) {
            return;
        }
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS member_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    member_id INTEGER NOT NULL,
                    request_type TEXT NOT NULL,
                    full_name TEXT,
                    phone TEXT,
                    national_id TEXT,
                    wants_access INTEGER,
                    notes TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    submitted_at TEXT,
                    reviewed_at TEXT,
                    rejection_reason TEXT
                )"
            );

            return;
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS member_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT NOT NULL,
                member_id INT NOT NULL,
                request_type VARCHAR(16) NOT NULL,
                full_name VARCHAR(255) NULL,
                phone VARCHAR(64) NULL,
                national_id VARCHAR(32) NULL,
                wants_access TINYINT NULL,
                notes TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                submitted_at VARCHAR(32) NULL,
                reviewed_at VARCHAR(32) NULL,
                rejection_reason TEXT NULL,
                INDEX idx_member_requests_team (team_id),
                INDEX idx_member_requests_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureMeetingRoomTables(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS meeting_rooms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    code TEXT,
                    capacity INTEGER NOT NULL DEFAULT 10,
                    floor TEXT,
                    equipment TEXT,
                    open_time TEXT NOT NULL DEFAULT '08:00',
                    close_time TEXT NOT NULL DEFAULT '20:00',
                    slot_minutes INTEGER NOT NULL DEFAULT 30,
                    notes TEXT,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT,
                    updated_at TEXT
                );
                CREATE TABLE IF NOT EXISTS room_reservations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    reserved_date TEXT NOT NULL,
                    start_time TEXT NOT NULL,
                    end_time TEXT NOT NULL,
                    duration_minutes INTEGER NOT NULL,
                    team_id INTEGER,
                    member_id INTEGER,
                    booker_name TEXT NOT NULL,
                    booker_phone TEXT NOT NULL,
                    booker_org TEXT,
                    purpose TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    source TEXT NOT NULL DEFAULT 'public',
                    public_token TEXT NOT NULL,
                    submitted_at TEXT,
                    reviewed_at TEXT,
                    reviewed_by INTEGER,
                    rejection_reason TEXT,
                    cancel_reason TEXT,
                    created_at TEXT,
                    updated_at TEXT
                );"
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS meeting_rooms (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    code VARCHAR(32) NULL,
                    capacity INT NOT NULL DEFAULT 10,
                    floor VARCHAR(32) NULL,
                    equipment TEXT NULL,
                    open_time VARCHAR(8) NOT NULL DEFAULT '08:00',
                    close_time VARCHAR(8) NOT NULL DEFAULT '20:00',
                    slot_minutes INT NOT NULL DEFAULT 30,
                    notes TEXT NULL,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    created_at VARCHAR(32) NULL,
                    updated_at VARCHAR(32) NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS room_reservations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    room_id INT NOT NULL,
                    reserved_date VARCHAR(32) NOT NULL,
                    start_time VARCHAR(8) NOT NULL,
                    end_time VARCHAR(8) NOT NULL,
                    duration_minutes INT NOT NULL,
                    team_id INT NULL,
                    member_id INT NULL,
                    booker_name VARCHAR(255) NOT NULL,
                    booker_phone VARCHAR(32) NOT NULL,
                    booker_org VARCHAR(255) NULL,
                    purpose TEXT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'pending',
                    source VARCHAR(16) NOT NULL DEFAULT 'public',
                    public_token VARCHAR(64) NOT NULL,
                    submitted_at VARCHAR(32) NULL,
                    reviewed_at VARCHAR(32) NULL,
                    reviewed_by INT NULL,
                    rejection_reason TEXT NULL,
                    cancel_reason TEXT NULL,
                    created_at VARCHAR(32) NULL,
                    updated_at VARCHAR(32) NULL,
                    INDEX idx_room_res_room_date (room_id, reserved_date),
                    INDEX idx_room_res_phone_date (booker_phone, reserved_date),
                    INDEX idx_room_res_status (status),
                    UNIQUE KEY uniq_room_res_token (public_token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        self::ensureIndex($pdo, 'room_reservations', 'idx_room_res_room_date', ['room_id', 'reserved_date']);
        self::ensureIndex($pdo, 'room_reservations', 'idx_room_res_phone_date', ['booker_phone', 'reserved_date']);
        self::ensureIndex($pdo, 'room_reservations', 'idx_room_res_status', ['status']);
        self::ensureUniqueIndex($pdo, 'room_reservations', 'uniq_room_res_token', ['public_token']);

        if (!self::tableExists($pdo, 'meeting_rooms')) {
            return;
        }

        $count = (int) $pdo->query('SELECT COUNT(*) FROM meeting_rooms')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $today = JalaliDate::todayParts()['formatted'];
        $pdo->prepare(
            "INSERT INTO meeting_rooms (name, code, capacity, floor, equipment, open_time, close_time, slot_minutes, is_active, created_at, updated_at)
             VALUES ('اتاق جلسه اصلی', 'MR-1', 12, 'طبقه اول', 'ویدئو پروژکتور، وایت‌برد', '08:00', '20:00', 30, 1, :today, :today)"
        )->execute(['today' => $today]);
    }

    public static function ensureRoomClosedDaysTable(PDO $pdo): void
    {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($isSqlite) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS room_closed_days (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    closed_date TEXT NOT NULL UNIQUE,
                    note TEXT,
                    created_by INTEGER,
                    created_at TEXT
                )"
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS room_closed_days (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    closed_date VARCHAR(32) NOT NULL,
                    note TEXT NULL,
                    created_by INT NULL,
                    created_at VARCHAR(32) NULL,
                    UNIQUE KEY uniq_room_closed_date (closed_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        self::ensureUniqueIndex($pdo, 'room_closed_days', 'uniq_room_closed_date', ['closed_date']);
    }

    /** One-time slot alignment when upgrading to schema v18. */
    public static function migrateRoomSlotDefaults(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'meeting_rooms')) {
            try {
                $pdo->exec('UPDATE meeting_rooms SET slot_minutes = 30 WHERE slot_minutes IS NULL OR slot_minutes <= 0 OR slot_minutes = 60');
            } catch (PDOException) {
            }
        }
        if (self::tableExists($pdo, 'center_settings') && self::columnExists($pdo, 'center_settings', 'room_slot_minutes')) {
            try {
                $pdo->exec('UPDATE center_settings SET room_slot_minutes = 30 WHERE id = 1 AND (room_slot_minutes IS NULL OR room_slot_minutes <= 0 OR room_slot_minutes = 60)');
            } catch (PDOException) {
            }
        }
    }

    private static function seedDeskAssignments(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return;
        }
        $today = JalaliDate::todayParts();
        $fiscalYear = (string) $today['year'];
        $yearStart = $fiscalYear . '/01/01';
        $yearEnd = JalaliDate::monthEnd($fiscalYear, 12);
        $desks = $pdo->query(
            'SELECT d.id, d.number, d.team_id, d.usage_type, d.notes
             FROM desks d
             WHERE d.team_id IS NOT NULL'
        )->fetchAll();
        $contractStatement = $pdo->prepare(
            'SELECT contract_start, contract_end
             FROM team_contracts
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year
             LIMIT 1'
        );
        foreach ($desks as $desk) {
            $deskId = (int) $desk['id'];
            $teamId = (int) $desk['team_id'];
            $exists = $pdo->prepare(
                'SELECT id FROM desk_assignments
                 WHERE desk_id = :desk_id
                   AND assigned_from <= :year_end
                   AND (assigned_until IS NULL OR assigned_until = \'\' OR assigned_until >= :year_start)
                 LIMIT 1'
            );
            $exists->execute([
                'desk_id' => $deskId,
                'year_start' => $yearStart,
                'year_end' => $yearEnd,
            ]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }

            $assignedFrom = $yearStart;
            $assignedUntil = $yearEnd;
            $contractStatement->execute([
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
            ]);
            $contract = $contractStatement->fetch();
            if ($contract !== false) {
                $contractStart = JalaliDate::tryNormalize((string) ($contract['contract_start'] ?? ''));
                $contractEnd = JalaliDate::tryNormalize((string) ($contract['contract_end'] ?? ''));
                if ($contractStart !== '') {
                    $assignedFrom = $contractStart;
                }
                if ($contractEnd !== '') {
                    $assignedUntil = $contractEnd;
                }
            }

            $pdo->prepare(
                'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until, notes)
                 VALUES (:desk_id, :desk_number, :team_id, :usage_type, :assigned_from, :assigned_until, :notes)'
            )->execute([
                'desk_id' => $deskId,
                'desk_number' => (int) $desk['number'],
                'team_id' => $teamId,
                'usage_type' => (string) ($desk['usage_type'] ?? 'formal'),
                'assigned_from' => $assignedFrom,
                'assigned_until' => $assignedUntil,
                'notes' => $desk['notes'] ?? null,
            ]);
        }
    }

    private static function backfillDeskAssignmentEnds(PDO $pdo): void
    {
        $rows = $pdo->query(
            "SELECT da.id, da.team_id, da.assigned_from
             FROM desk_assignments da
             WHERE da.assigned_until IS NULL OR da.assigned_until = ''"
        )->fetchAll();
        if ($rows === []) {
            return;
        }

        $contractStatement = $pdo->prepare(
            'SELECT contract_end FROM team_contracts
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year
             LIMIT 1'
        );
        $update = $pdo->prepare('UPDATE desk_assignments SET assigned_until = :until WHERE id = :id');
        foreach ($rows as $row) {
            $fiscalYear = JalaliDate::fiscalYearFromDate((string) ($row['assigned_from'] ?? ''));
            if ($fiscalYear === '') {
                continue;
            }
            $contractStatement->execute([
                'team_id' => (int) ($row['team_id'] ?? 0),
                'fiscal_year' => $fiscalYear,
            ]);
            $contractEnd = $contractStatement->fetchColumn();
            $until = JalaliDate::tryNormalize((string) ($contractEnd !== false ? $contractEnd : ''));
            if ($until === '') {
                $until = JalaliDate::monthEnd($fiscalYear, 12);
            }
            $update->execute([
                'until' => $until,
                'id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private static function normalizeDeskAssignments(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'desk_assignments')) {
            return;
        }

        $rows = $pdo->query(
            'SELECT id, desk_id, assigned_from, assigned_until
             FROM desk_assignments
             ORDER BY desk_id, assigned_from, id'
        )->fetchAll();
        $groups = [];
        foreach ($rows as $row) {
            $deskId = (int) ($row['desk_id'] ?? 0);
            $fiscalYear = JalaliDate::fiscalYearFromDate((string) ($row['assigned_from'] ?? ''));
            if ($deskId <= 0 || $fiscalYear === '') {
                continue;
            }
            $groups[$deskId . ':' . $fiscalYear][] = $row;
        }

        $delete = $pdo->prepare('DELETE FROM desk_assignments WHERE id = :id');
        foreach ($groups as $group) {
            if (count($group) <= 1) {
                continue;
            }
            // Preferred survivors first; keep non-overlapping segments, drop true overlaps.
            usort($group, static function (array $a, array $b): int {
                $preferred = self::preferDeskAssignmentPair($a, $b);

                return (int) ($preferred['id'] ?? 0) === (int) ($a['id'] ?? 0) ? -1 : 1;
            });
            $kept = [];
            foreach ($group as $row) {
                $from = JalaliDate::tryNormalize((string) ($row['assigned_from'] ?? ''));
                $until = JalaliDate::tryNormalize((string) ($row['assigned_until'] ?? ''));
                $overlapsKept = false;
                foreach ($kept as $keptRow) {
                    $keptFrom = JalaliDate::tryNormalize((string) ($keptRow['assigned_from'] ?? ''));
                    $keptUntil = JalaliDate::tryNormalize((string) ($keptRow['assigned_until'] ?? ''));
                    $endA = $until !== '' ? $until : '9999/12/29';
                    $endB = $keptUntil !== '' ? $keptUntil : '9999/12/29';
                    if ($from !== '' && $keptFrom !== ''
                        && JalaliDate::compare($from, $endB) <= 0
                        && JalaliDate::compare($keptFrom, $endA) <= 0) {
                        $overlapsKept = true;
                        break;
                    }
                }
                if ($overlapsKept) {
                    $rowId = (int) ($row['id'] ?? 0);
                    if ($rowId > 0) {
                        $delete->execute(['id' => $rowId]);
                    }
                    continue;
                }
                $kept[] = $row;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private static function preferDeskAssignmentRow(array $rows): array
    {
        $best = $rows[0];
        foreach (array_slice($rows, 1) as $row) {
            $best = self::preferDeskAssignmentPair($best, $row);
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private static function preferDeskAssignmentPair(array $left, array $right): array
    {
        $leftOpen = self::isOpenEndedDeskAssignment($left);
        $rightOpen = self::isOpenEndedDeskAssignment($right);
        if ($leftOpen !== $rightOpen) {
            return $leftOpen ? $right : $left;
        }

        $leftFrom = JalaliDate::tryNormalize((string) ($left['assigned_from'] ?? ''));
        $rightFrom = JalaliDate::tryNormalize((string) ($right['assigned_from'] ?? ''));
        if ($leftFrom !== $rightFrom) {
            return JalaliDate::compare($rightFrom, $leftFrom) > 0 ? $right : $left;
        }

        return (int) ($right['id'] ?? 0) > (int) ($left['id'] ?? 0) ? $right : $left;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function isOpenEndedDeskAssignment(array $row): bool
    {
        return JalaliDate::tryNormalize((string) ($row['assigned_until'] ?? '')) === '';
    }

    private static function ensureSmsTables(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS sms_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    batch_uid TEXT NOT NULL,
                    message_type TEXT NOT NULL,
                    member_id INTEGER NULL,
                    team_id INTEGER NULL,
                    team_name TEXT NULL,
                    recipient_name TEXT NULL,
                    phone TEXT NULL,
                    is_leader INTEGER NOT NULL DEFAULT 0,
                    message_text TEXT NOT NULL,
                    status TEXT NOT NULL,
                    error_message TEXT NULL,
                    provider_rec_id TEXT NULL,
                    provider_response TEXT NULL,
                    cost_rial INTEGER NOT NULL DEFAULT 0,
                    sent_by TEXT NULL,
                    created_at TEXT NOT NULL,
                    sent_at TEXT NULL,
                    delivery_status TEXT NULL,
                    delivery_checked_at TEXT NULL,
                    api_confirmed INTEGER NOT NULL DEFAULT 0
                )'
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sms_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    batch_uid VARCHAR(64) NOT NULL,
                    message_type VARCHAR(32) NOT NULL,
                    member_id INT NULL,
                    team_id INT NULL,
                    team_name VARCHAR(255) NULL,
                    recipient_name VARCHAR(255) NULL,
                    phone VARCHAR(32) NULL,
                    is_leader TINYINT NOT NULL DEFAULT 0,
                    message_text TEXT NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    error_message TEXT NULL,
                    provider_rec_id VARCHAR(64) NULL,
                    provider_response TEXT NULL,
                    cost_rial BIGINT NOT NULL DEFAULT 0,
                    sent_by VARCHAR(64) NULL,
                    created_at VARCHAR(32) NOT NULL,
                    sent_at VARCHAR(32) NULL,
                    delivery_status VARCHAR(64) NULL,
                    delivery_checked_at VARCHAR(32) NULL,
                    api_confirmed TINYINT NOT NULL DEFAULT 0,
                    INDEX idx_sms_logs_batch (batch_uid),
                    INDEX idx_sms_logs_created (created_at),
                    INDEX idx_sms_logs_status (status),
                    INDEX idx_sms_logs_provider (provider_rec_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        self::ensureSmsLogColumns($pdo);
        self::ensureSmsPatternsTable($pdo);
    }

    private static function ensureSmsPatternsTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS sms_patterns (
                    pattern_key TEXT PRIMARY KEY,
                    body_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    panel_text TEXT NOT NULL,
                    variables_json TEXT NOT NULL,
                    system_template TEXT NOT NULL,
                    workflow_key TEXT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sms_patterns (
                    pattern_key VARCHAR(64) PRIMARY KEY,
                    body_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    panel_text TEXT NOT NULL,
                    variables_json TEXT NOT NULL,
                    system_template TEXT NOT NULL,
                    workflow_key VARCHAR(64) NULL,
                    updated_at VARCHAR(32) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }
    }

    public static function seedSmsPatterns(PDO $pdo): void
    {
        self::ensureSmsPatternsTable($pdo);
        if (!self::tableExists($pdo, 'center_settings')) {
            return;
        }

        $today = JalaliDate::todayParts()['formatted'];
        $insert = $pdo->prepare(
            'INSERT INTO sms_patterns (pattern_key, body_id, title, panel_text, variables_json, system_template, workflow_key, updated_at)
             VALUES (:pattern_key, :body_id, :title, :panel_text, :variables_json, :system_template, :workflow_key, :updated_at)
             ON CONFLICT(pattern_key) DO NOTHING'
        );
        $updateMeta = $pdo->prepare(
            'UPDATE sms_patterns SET
                title = :title,
                panel_text = :panel_text,
                variables_json = :variables_json,
                workflow_key = :workflow_key,
                updated_at = :updated_at
             WHERE pattern_key = :pattern_key'
        );
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO sms_patterns (pattern_key, body_id, title, panel_text, variables_json, system_template, workflow_key, updated_at)
                 VALUES (:pattern_key, :body_id, :title, :panel_text, :variables_json, :system_template, :workflow_key, :updated_at)'
            );
            $updateMeta = $pdo->prepare(
                'UPDATE sms_patterns SET
                    title = :title,
                    panel_text = :panel_text,
                    variables_json = :variables_json,
                    workflow_key = :workflow_key,
                    updated_at = :updated_at
                 WHERE pattern_key = :pattern_key'
            );
        }

        $bodyIds = [];
        foreach (SmsPatterns::definitions() as $key => $definition) {
            $bodyId = (int) $definition['body_id'];
            $insert->execute([
                'pattern_key' => $key,
                'body_id' => $bodyId,
                'title' => (string) $definition['title'],
                'panel_text' => (string) $definition['panel_text'],
                'variables_json' => json_encode($definition['variables'], JSON_UNESCAPED_UNICODE),
                'system_template' => SmsPatterns::systemTemplate($key, $bodyId),
                'workflow_key' => $definition['workflow_key'],
                'updated_at' => $today,
            ]);

            $existingBodyId = 0;
            if (Schema::tableExists($pdo, 'sms_patterns')) {
                $existingBodyId = (int) $pdo->query(
                    "SELECT body_id FROM sms_patterns WHERE pattern_key = " . $pdo->quote($key)
                )->fetchColumn();
            }
            $bodyIds[$key] = $existingBodyId > 0 ? $existingBodyId : $bodyId;

            $updateMeta->execute([
                'pattern_key' => $key,
                'title' => (string) $definition['title'],
                'panel_text' => (string) $definition['panel_text'],
                'variables_json' => json_encode($definition['variables'], JSON_UNESCAPED_UNICODE),
                'workflow_key' => $definition['workflow_key'],
                'updated_at' => $today,
            ]);
        }

        try {
            $row = $pdo->query('SELECT sms_charge_template, sms_workflow_templates FROM center_settings WHERE id = 1')->fetch();
        } catch (PDOException) {
            return;
        }
        if ($row === false) {
            return;
        }

        $charge = trim((string) ($row['sms_charge_template'] ?? ''));
        $workflowJson = trim((string) ($row['sms_workflow_templates'] ?? ''));
        $workflow = $workflowJson !== '' ? json_decode($workflowJson, true) : [];
        $needsSeed = $charge === '' || !str_contains($charge, '##shared');
        if (!$needsSeed && is_array($workflow)) {
            foreach (SmsPatterns::workflowTemplateDefaults() as $workflowKey => $template) {
                $current = trim((string) ($workflow[$workflowKey] ?? ''));
                if ($current === '' || !str_contains($current, '##shared') || SmsPatterns::templateUsesPlaceholder($current)) {
                    $needsSeed = true;
                    break;
                }
            }
        } elseif (!is_array($workflow) || $workflow === []) {
            $needsSeed = true;
        }

        if (!$needsSeed) {
            return;
        }

        $workflowTemplates = [];
        foreach (SmsPatterns::definitions() as $key => $definition) {
            $workflowKey = $definition['workflow_key'] ?? null;
            if ($workflowKey === null) {
                continue;
            }
            $bodyId = (int) ($bodyIds[$key] ?? $definition['body_id']);
            $workflowTemplates[$workflowKey] = SmsPatterns::systemTemplate($key, $bodyId);
        }

        $pdo->prepare(
            'UPDATE center_settings SET
                sms_charge_template = :charge,
                sms_workflow_templates = :workflow,
                sms_updated_at = :updated
             WHERE id = 1'
        )->execute([
            'charge' => SmsPatterns::chargeTemplate($bodyIds['charge_reminder'] ?? null),
            'workflow' => json_encode($workflowTemplates, JSON_UNESCAPED_UNICODE),
            'updated' => $today,
        ]);
    }

    private static function applyKnownPatternBodyIds(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sms_patterns') || !self::tableExists($pdo, 'center_settings')) {
            return;
        }

        /** @var array<string, int> $known */
        $known = [
            'room_pending' => 507317,
        ];

        $today = JalaliDate::todayParts()['formatted'];
        foreach ($known as $patternKey => $bodyId) {
            if (!isset(SmsPatterns::definitions()[$patternKey])) {
                continue;
            }
            $statement = $pdo->prepare('SELECT body_id FROM sms_patterns WHERE pattern_key = :pattern_key LIMIT 1');
            $statement->execute(['pattern_key' => $patternKey]);
            $current = (int) ($statement->fetchColumn() ?: 0);
            if ($current > 0 && !SmsPatterns::isPlaceholderBodyId($current)) {
                continue;
            }

            $systemTemplate = SmsPatterns::systemTemplate($patternKey, $bodyId);
            $pdo->prepare(
                'UPDATE sms_patterns SET body_id = :body_id, system_template = :system_template, updated_at = :updated_at
                 WHERE pattern_key = :pattern_key'
            )->execute([
                'pattern_key' => $patternKey,
                'body_id' => $bodyId,
                'system_template' => $systemTemplate,
                'updated_at' => $today,
            ]);

            $workflowKey = SmsPatterns::definitions()[$patternKey]['workflow_key'] ?? null;
            if ($workflowKey === null) {
                continue;
            }

            try {
                $row = $pdo->query('SELECT sms_workflow_templates FROM center_settings WHERE id = 1')->fetch();
            } catch (PDOException) {
                return;
            }
            if ($row === false) {
                return;
            }

            $workflowJson = trim((string) ($row['sms_workflow_templates'] ?? ''));
            $workflow = $workflowJson !== '' ? json_decode($workflowJson, true) : [];
            if (!is_array($workflow)) {
                $workflow = [];
            }
            $currentTemplate = trim((string) ($workflow[$workflowKey] ?? ''));
            if ($currentTemplate !== '' && !SmsPatterns::templateUsesPlaceholder($currentTemplate)) {
                continue;
            }
            $workflow[$workflowKey] = $systemTemplate;
            $pdo->prepare('UPDATE center_settings SET sms_workflow_templates = :workflow, sms_updated_at = :updated WHERE id = 1')
                ->execute([
                    'workflow' => json_encode($workflow, JSON_UNESCAPED_UNICODE),
                    'updated' => $today,
                ]);
        }
    }

    private static function ensureSmsLogColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sms_logs')) {
            return;
        }
        $columns = [
            'delivery_status' => 'VARCHAR(64) NULL',
            'delivery_checked_at' => 'VARCHAR(32) NULL',
            'api_confirmed' => 'TINYINT NOT NULL DEFAULT 0',
        ];
        foreach ($columns as $column => $definition) {
            if (!self::columnExists($pdo, 'sms_logs', $column)) {
                $type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                    ? self::sqliteType($definition)
                    : $definition;
                $pdo->exec('ALTER TABLE sms_logs ADD COLUMN ' . Sql::quoteIdentifier($column) . ' ' . $type);
            }
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

    public static function tableExists(PDO $pdo, string $table): bool
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

    public static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        return self::columnExists($pdo, $table, $column);
    }

    public static function deskAssignmentExemptSelect(PDO $pdo, string $alias = ''): string
    {
        if (!self::hasColumn($pdo, 'desk_assignments', 'charge_exempt')
            || !self::hasColumn($pdo, 'desk_assignments', 'rent_exempt')) {
            return '';
        }

        $prefix = $alias !== '' ? $alias . '.' : '';

        return ', ' . $prefix . 'charge_exempt, ' . $prefix . 'rent_exempt';
    }

    public static function deskAssignmentExemptWritable(PDO $pdo): bool
    {
        return self::hasColumn($pdo, 'desk_assignments', 'charge_exempt')
            && self::hasColumn($pdo, 'desk_assignments', 'rent_exempt');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeDeskAssignmentRow(array $row): array
    {
        $row['charge_exempt'] = (int) ($row['charge_exempt'] ?? 0);
        $row['rent_exempt'] = (int) ($row['rent_exempt'] ?? 0);

        return $row;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $statement = $pdo->query("PRAGMA table_info({$table})");
                foreach ($statement->fetchAll() as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }

                return false;
            }

            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
