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
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resource(string $name): array
    {
        return match ($name) {
            'teams' => $this->rows('SELECT * FROM teams ORDER BY id'),
            'members' => $this->rows('SELECT * FROM members ORDER BY id'),
            'lockers' => $this->rows('SELECT * FROM lockers ORDER BY locker_number'),
            'plans' => $this->rows('SELECT * FROM plans ORDER BY plan_number'),
            'charges' => $this->rows(
                'SELECT fiscal_year, team_name, leader, desk_count, month_name,
                        amount, note, charge_rate, rent_rate
                 FROM charges
                 ORDER BY fiscal_year, team_name, month_index'
            ),
            'transactions' => $this->rows(
                'SELECT t.*, b.sheet_name, b.petty_cash_holder
                 FROM transactions t
                 JOIN financial_batches b ON b.id = t.batch_id
                 ORDER BY b.id, t.id'
            ),
            'warnings' => $this->rows('SELECT * FROM import_warnings ORDER BY id'),
            default => throw new InvalidArgumentException('Unknown resource.'),
        };
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
}
