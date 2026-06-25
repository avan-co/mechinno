<?php

declare(strict_types=1);

final class Repository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'cards' => [
                'members' => $this->scalar('SELECT COUNT(*) FROM members'),
                'teams' => $this->scalar('SELECT COUNT(*) FROM teams'),
                'lockers' => $this->scalar('SELECT COUNT(*) FROM lockers'),
                'available_lockers' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'خالی'"),
                'reserved_lockers' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'رزرو'"),
                'charge_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM charges'),
                'income_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0'),
                'expense_total' => $this->scalar('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount < 0'),
                'warnings' => $this->scalar('SELECT COUNT(*) FROM import_warnings'),
                'backups' => $this->scalar('SELECT COUNT(*) FROM import_backups'),
                'debt_total' => $this->scalar("SELECT COALESCE(SUM(amount_due - amount_paid), 0) FROM team_payments WHERE status <> 'پرداخت‌شده'"),
                'paid_total' => $this->scalar('SELECT COALESCE(SUM(amount_paid), 0) FROM team_payments'),
                'occupied_lockers' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'تخصیص یافته'"),
                'reserved_lockers_count' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'رزرو'"),
            ],
            'locker_status' => $this->rows('SELECT status, COUNT(*) AS count FROM lockers GROUP BY status ORDER BY count DESC'),
            'plan_status' => $this->rows('SELECT status, COUNT(*) AS count FROM plans GROUP BY status ORDER BY count DESC'),
            'monthly_charges' => $this->rows(
                'SELECT fiscal_year, month_index, month_name, SUM(amount) AS amount
                 FROM charges
                 GROUP BY fiscal_year, month_index, month_name
                 ORDER BY fiscal_year, month_index'
            ),
            'finance_by_category' => $this->rows(
                'SELECT category, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount
                 FROM transactions
                 GROUP BY category
                 ORDER BY amount DESC'
            ),
            'latest_import' => $this->row('SELECT imported_at, source_files FROM import_runs ORDER BY id DESC LIMIT 1'),
            'debt_by_team' => $this->rows(
                "SELECT t.name AS team_name, COALESCE(SUM(p.amount_due - p.amount_paid), 0) AS debt
                 FROM team_payments p
                 LEFT JOIN teams t ON t.id = p.team_id
                 WHERE p.status <> 'پرداخت‌شده'
                 GROUP BY p.team_id, t.name
                 HAVING debt > 0
                 ORDER BY debt DESC
                 LIMIT 10"
            ),
            'finance_monthly' => $this->rows(
                "SELECT substr(tx_date, 1, 7) AS period,
                        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS income,
                        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) AS expense
                 FROM transactions
                 WHERE tx_date IS NOT NULL AND tx_date <> ''
                 GROUP BY substr(tx_date, 1, 7)
                 ORDER BY period"
            ),
            'occupancy' => [
                'lockers_total' => $this->scalar('SELECT COUNT(*) FROM lockers'),
                'lockers_assigned' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'تخصیص یافته'"),
                'lockers_reserved' => $this->scalar("SELECT COUNT(*) FROM lockers WHERE status = 'رزرو'"),
                'desks_used' => $this->scalar('SELECT COALESCE(SUM(desk_count), 0) FROM teams'),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resource(string $name): array
    {
        return match ($name) {
            'teams' => $this->rows('SELECT * FROM teams ORDER BY id'),
            'members' => $this->rows(
                'SELECT m.*, t.name AS related_team
                 FROM members m
                 LEFT JOIN teams t ON t.id = m.team_id
                 ORDER BY m.id'
            ),
            'lockers' => $this->rows(
                'SELECT l.*, t.name AS related_team
                 FROM lockers l
                 LEFT JOIN teams t ON t.id = l.team_id
                 ORDER BY l.locker_number'
            ),
            'plans' => $this->rows('SELECT * FROM plans ORDER BY plan_number'),
            'charges' => $this->rows(
                'SELECT c.id, c.team_id, t.name AS related_team, c.fiscal_year, c.team_name, c.leader,
                        c.desk_count, c.month_index, c.month_name, c.amount, c.note,
                        c.charge_rate, c.rent_rate, c.source_file, c.source_sheet
                 FROM charges c
                 LEFT JOIN teams t ON t.id = c.team_id
                 ORDER BY c.fiscal_year, c.team_name, c.month_index'
            ),
            'transactions' => $this->rows(
                'SELECT t.*, b.sheet_name, b.petty_cash_holder
                 FROM transactions t
                 JOIN financial_batches b ON b.id = t.batch_id
                 ORDER BY b.id, t.id'
            ),
            'rate_settings' => $this->rows('SELECT * FROM rate_settings ORDER BY fiscal_year, effective_from, id'),
            'backups' => $this->rows('SELECT * FROM import_backups ORDER BY id DESC'),
            'team_payments' => $this->rows(
                'SELECT p.*, t.name AS related_team
                 FROM team_payments p
                 LEFT JOIN teams t ON t.id = p.team_id
                 ORDER BY p.fiscal_year, p.month_index, t.name'
            ),
            'warnings' => $this->rows('SELECT * FROM import_warnings ORDER BY id'),
            default => throw new InvalidArgumentException('Unknown resource.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function teamProfile(int $teamId): array
    {
        $team = $this->preparedRow('SELECT * FROM teams WHERE id = :id', ['id' => $teamId]);
        if ($team === null) {
            throw new InvalidArgumentException('تیم پیدا نشد.');
        }
        $name = (string) ($team['name'] ?? '');
        $leader = (string) ($team['leader'] ?? '');

        return [
            'team' => $team,
            'members' => $this->preparedRows('SELECT * FROM members WHERE team_id = :id ORDER BY id', ['id' => $teamId]),
            'lockers' => $this->preparedRows('SELECT * FROM lockers WHERE team_id = :id ORDER BY locker_number', ['id' => $teamId]),
            'charges' => $this->preparedRows('SELECT * FROM charges WHERE team_id = :id ORDER BY fiscal_year, month_index', ['id' => $teamId]),
            'payments' => $this->preparedRows('SELECT * FROM team_payments WHERE team_id = :id ORDER BY fiscal_year, month_index', ['id' => $teamId]),
            'transactions' => $this->preparedRows(
                'SELECT t.*, b.sheet_name
                 FROM transactions t
                 JOIN financial_batches b ON b.id = t.batch_id
                 WHERE t.description LIKE :name OR t.description LIKE :leader OR t.notes LIKE :name OR t.notes LIKE :leader
                 ORDER BY t.id',
                ['name' => '%' . $name . '%', 'leader' => '%' . $leader . '%']
            ),
            'summary' => [
                'charge_total' => $this->preparedScalar('SELECT COALESCE(SUM(amount), 0) FROM charges WHERE team_id = :id', ['id' => $teamId]),
                'debt_total' => $this->preparedScalar("SELECT COALESCE(SUM(amount_due - amount_paid), 0) FROM team_payments WHERE team_id = :id AND status <> 'پرداخت‌شده'", ['id' => $teamId]),
                'paid_total' => $this->preparedScalar('SELECT COALESCE(SUM(amount_paid), 0) FROM team_payments WHERE team_id = :id', ['id' => $teamId]),
            ],
        ];
    }

    public function scalar(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function row(string $sql): ?array
    {
        $row = $this->pdo->query($sql)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function preparedScalar(string $sql, array $params): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function preparedRow(string $sql, array $params): ?array
    {
        $rows = $this->preparedRows($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function preparedRows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
