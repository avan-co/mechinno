<?php

declare(strict_types=1);

final class BackupManager
{
    private const TABLES = ['teams', 'members', 'lockers', 'plans', 'charges', 'financial_batches', 'transactions'];

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
               AND table_name IN ('teams', 'members', 'lockers', 'plans', 'charges', 'financial_batches', 'transactions')
             ORDER BY id"
        );
        $items->execute(['backup_id' => $backupId]);
        $manualBatchMap = [];

        foreach ($items->fetchAll() as $item) {
            $table = (string) $item['table_name'];
            $row = json_decode((string) $item['payload'], true);
            if (!is_array($row) || !$this->isManualRow($table, $row)) {
                continue;
            }
            $oldId = (int) ($row['id'] ?? 0);
            unset($row['id']);
            if ($table === 'transactions') {
                $oldBatchId = (int) ($row['batch_id'] ?? 0);
                if ($oldBatchId > 0 && isset($manualBatchMap[$oldBatchId])) {
                    $row['batch_id'] = $manualBatchMap[$oldBatchId];
                } else {
                    continue;
                }
            }
            $newId = $this->insertRow($table, $row);
            if ($table === 'financial_batches' && $oldId > 0) {
                $manualBatchMap[$oldId] = $newId;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isManualRow(string $table, array $row): bool
    {
        if ($table === 'transactions') {
            return true;
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
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));
        $statement->execute($row);

        return (int) $this->pdo->lastInsertId();
    }
}
