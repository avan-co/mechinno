<?php

declare(strict_types=1);

final class DatabaseBackup
{
    public const FORMAT = 'mechinno-backup';
    public const VERSION = 1;

    /** @var list<string> */
    private const TABLE_ORDER = [
        'center_settings',
        'teams',
        'rate_settings',
        'panel_users',
        'desks',
        'team_contracts',
        'members',
        'lockers',
        'desk_assignments',
        'charges',
        'transactions',
        'locker_requests',
        'member_requests',
        'development_plans',
        'meeting_rooms',
        'room_closed_days',
        'room_reservations',
        'sms_logs',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<string>
     */
    public static function tableOrder(): array
    {
        return self::TABLE_ORDER;
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $tables = [];
        $counts = [];
        foreach ($this->orderedTables() as $table) {
            $rows = $this->pdo->query('SELECT * FROM ' . Sql::quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
            $tables[$table] = array_map(
                static function (array $row) use ($table): array {
                    if ($table === 'panel_users') {
                        unset($row['password_plain']);
                    }

                    return $row;
                },
                $rows
            );
            $counts[$table] = count($rows);
        }

        $today = JalaliDate::todayParts();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => $today['formatted'],
            'exported_at_gregorian' => date('c'),
            'app' => 'Mechinno',
            'driver' => (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            'counts' => $counts,
            'tables' => $tables,
        ];
    }

    public function exportJson(): string
    {
        $json = json_encode($this->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!is_string($json)) {
            throw new RuntimeException('ساخت فایل پشتیبان ناموفق بود.');
        }

        return $json;
    }

    public function suggestedFilename(): string
    {
        $stamp = JalaliDate::todayParts()['formatted'];
        $stamp = str_replace('/', '-', $stamp);

        return 'mechinno-backup-' . $stamp . '.json';
    }

    /**
     * @return array<string, int>
     */
    public function import(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('فایل پشتیبان JSON معتبر نیست.');
            }
            $payload = $decoded;
        }
        if (!is_array($payload)) {
            throw new InvalidArgumentException('ساختار فایل پشتیبان معتبر نیست.');
        }
        if (($payload['format'] ?? '') !== self::FORMAT) {
            throw new InvalidArgumentException('این فایل پشتیبان Mechinno نیست.');
        }
        $version = (int) ($payload['version'] ?? 0);
        if ($version < 1 || $version > self::VERSION) {
            throw new InvalidArgumentException('نسخه فایل پشتیبان پشتیبانی نمی‌شود.');
        }

        /** @var array<string, list<array<string, mixed>>> $tables */
        $tables = $payload['tables'] ?? [];
        if ($tables === []) {
            throw new InvalidArgumentException('فایل پشتیبان خالی است.');
        }
        foreach ($tables as $tableName => $rows) {
            if (!is_string($tableName) || !preg_match('/^[a-z_][a-z0-9_]*$/', $tableName)) {
                throw new InvalidArgumentException('نام جدول در فایل پشتیبان معتبر نیست.');
            }
            if (!is_array($rows)) {
                throw new InvalidArgumentException('ساختار جدول «' . $tableName . '» در فایل پشتیبان معتبر نیست.');
            }
        }
        if (!isset($tables['teams']) || !is_array($tables['teams'])) {
            throw new InvalidArgumentException('فایل پشتیبان باید شامل جدول نهادها باشد.');
        }

        Schema::migrate($this->pdo);

        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$isSqlite) {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        // Only wipe/import tables present in the payload. Missing keys keep live data
        // so older backups (e.g. without meeting rooms) do not erase newer tables.
        $targets = [];
        foreach ($this->orderedTables() as $table) {
            if (array_key_exists($table, $tables)) {
                $targets[] = $table;
            }
        }
        if ($targets === []) {
            throw new InvalidArgumentException('هیچ جدول قابل بازیابی در فایل پشتیبان نیست.');
        }

        $imported = [];
        $autoIncrements = [];

        try {
            $this->pdo->beginTransaction();
            foreach (array_reverse($targets) as $table) {
                $this->clearTable($table);
            }
            foreach ($targets as $table) {
                $result = $this->importTable($table, $tables[$table] ?? []);
                $imported[$table] = $result['count'];
                if ($result['max_id'] > 0) {
                    $autoIncrements[$table] = $result['max_id'];
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } finally {
            if (!$isSqlite) {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        // MySQL DDL (ALTER AUTO_INCREMENT) must run outside the transaction — DDL commits implicitly.
        foreach ($autoIncrements as $table => $maxId) {
            $this->resetAutoIncrement($table, $maxId);
        }

        $this->pinSchemaVersion();
        Schema::reconcileDeskAssignments($this->pdo);
        // Do not auto-provision portal users or mutate leaders on restore — keep snapshot fidelity.
        // Admins can reset passwords from the panel if accounts are missing.

        return $imported;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{count:int,max_id:int}
     */
    private function importTable(string $table, array $rows): array
    {
        if ($rows === [] || !$this->tableExists($table)) {
            return ['count' => 0, 'max_id' => 0];
        }

        $columns = $this->tableColumns($table);
        if ($columns === []) {
            return ['count' => 0, 'max_id' => 0];
        }

        $inserted = 0;
        $maxId = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $data = [];
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $data[$column] = $row[$column];
                }
            }
            if ($data === []) {
                continue;
            }
            $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, array_keys($data)));
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                Sql::quoteIdentifier($table),
                Sql::columnList(array_keys($data)),
                $placeholders
            );
            $this->pdo->prepare($sql)->execute($data);
            $inserted++;
            if (isset($data['id'])) {
                $maxId = max($maxId, (int) $data['id']);
            }
        }

        return ['count' => $inserted, 'max_id' => $maxId];
    }

    private function clearTable(string $table): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $this->pdo->exec('DELETE FROM ' . Sql::quoteIdentifier($table));

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec('DELETE FROM sqlite_sequence WHERE name = ' . $this->pdo->quote($table));
        }
        // MySQL AUTO_INCREMENT is adjusted after commit via resetAutoIncrement().
    }

    private function resetAutoIncrement(string $table, int $maxId): void
    {
        if ($maxId <= 0 || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }
        if (!$this->tableExists($table)) {
            return;
        }

        $this->pdo->exec(
            'ALTER TABLE ' . Sql::quoteIdentifier($table) . ' AUTO_INCREMENT = ' . ($maxId + 1)
        );
    }

    private function pinSchemaVersion(): void
    {
        if (!$this->tableExists('center_settings') || !Schema::hasColumn($this->pdo, 'center_settings', 'schema_version')) {
            return;
        }

        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM center_settings WHERE id = 1')->fetchColumn();
        if ($exists === 0) {
            $this->pdo->prepare(
                'INSERT INTO center_settings (id, schema_version) VALUES (1, :version)'
            )->execute(['version' => Schema::VERSION]);

            return;
        }

        $this->pdo->prepare(
            'UPDATE center_settings SET schema_version = :version WHERE id = 1'
        )->execute(['version' => Schema::VERSION]);
    }

    /**
     * @return list<string>
     */
    private function orderedTables(): array
    {
        $existing = [];
        foreach (self::TABLE_ORDER as $table) {
            if ($this->tableExists($table)) {
                $existing[] = $table;
            }
        }

        return $existing;
    }

    /**
     * @return list<string>
     */
    private function tableColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = [];
            foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $columns[] = $name;
                }
            }

            return $columns;
        }

        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION'
        );
        $statement->execute(['table' => $table]);

        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            return false;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
            $statement->execute(['name' => $table]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name'
        );
        $statement->execute(['name' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
