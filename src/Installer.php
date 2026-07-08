<?php

declare(strict_types=1);

final class Installer
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Run schema migration and desk-assignment reconciliation without wiping data.
     *
     * @return array<string, int>
     */
    public function syncDatabase(): array
    {
        Schema::migrate($this->pdo);

        return $this->counts();
    }

    /**
     * @return array<string, int>
     */
    public function installFresh(): array
    {
        Schema::migrate($this->pdo);
        Schema::reset($this->pdo);
        Schema::seedDesks($this->pdo);

        return array_merge($this->counts(), [
            'teams' => 0,
            'members' => 0,
            'lockers' => 0,
            'charges' => 0,
            'transactions' => 0,
            'rate_settings' => 0,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'teams' => (int) $this->pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn(),
            'members' => (int) $this->pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
            'desks' => (int) $this->pdo->query('SELECT COUNT(*) FROM desks')->fetchColumn(),
            'desk_assignments' => (int) $this->pdo->query('SELECT COUNT(*) FROM desk_assignments')->fetchColumn(),
            'lockers' => (int) $this->pdo->query('SELECT COUNT(*) FROM lockers')->fetchColumn(),
            'charges' => (int) $this->pdo->query('SELECT COUNT(*) FROM charges')->fetchColumn(),
            'transactions' => (int) $this->pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
            'rate_settings' => (int) $this->pdo->query('SELECT COUNT(*) FROM rate_settings')->fetchColumn(),
        ];
    }
}
