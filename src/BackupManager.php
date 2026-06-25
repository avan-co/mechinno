<?php

declare(strict_types=1);

final class BackupManager
{
    private const TABLES = ['teams', 'members', 'member_desks', 'desks', 'lockers', 'plans', 'charges', 'transactions', 'team_rates'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $reason): int
    {
        $summary = [];
        $this->pdo->prepare('INSERT INTO import_backups (reason, summary) VALUES (:reason, :summary)')
            ->execute(['reason' => $reason, 'summary' => '{}']);
        $backupId = (int) $this->pdo->lastInsertId();

        foreach (self::TABLES as $table) {
            $rows = $this->pdo->query("SELECT * FROM {$table}")->fetchAll();
            $summary[$table] = count($rows);
            $statement = $this->pdo->prepare(
                'INSERT INTO import_backup_items (backup_id, table_name, row_id, payload)
                 VALUES (:backup_id, :table_name, :row_id, :payload)'
            );
            foreach ($rows as $row) {
                $statement->execute([
                    'backup_id' => $backupId,
                    'table_name' => $table,
                    'row_id' => $row['id'] ?? null,
                    'payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $this->pdo->prepare('UPDATE import_backups SET summary = :summary WHERE id = :id')
            ->execute([
                'id' => $backupId,
                'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            ]);

        return $backupId;
    }

    public function restoreManualRows(int $backupId): void
    {
        $items = $this->pdo->prepare(
            "SELECT table_name, payload
             FROM import_backup_items
             WHERE backup_id = :backup_id
               AND table_name IN ('teams', 'members', 'member_desks', 'desks', 'lockers', 'plans', 'charges', 'transactions', 'team_rates')
             ORDER BY id"
        );
        $items->execute(['backup_id' => $backupId]);
        foreach ($items->fetchAll() as $item) {
            $table = (string) $item['table_name'];
            $row = json_decode((string) $item['payload'], true);
            if (!is_array($row) || !$this->isManualRow($table, $row)) {
                continue;
            }
            unset($row['id']);
            if ($table === 'transactions') {
                unset($row['batch_id']);
            }
            $this->insertRow($table, $row);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isManualRow(string $table, array $row): bool
    {
        if ($table === 'transactions') {
            return ($row['source_file'] ?? null) === 'manual';
        }

        return ($row['source_file'] ?? null) === 'manual';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(string $table, array $row): int
    {
        if ($row === []) {
            return 0;
        }
        $columns = array_keys($row);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            Sql::columnList($columns),
            implode(', ', $placeholders)
        ));
        $statement->execute($row);

        return (int) $this->pdo->lastInsertId();
    }
}
