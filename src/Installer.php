<?php

declare(strict_types=1);

final class Installer
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, int>
     */
    public function installFresh(): array
    {
        Schema::migrate($this->pdo);
        Schema::reset($this->pdo);
        Schema::seedDesks($this->pdo);

        $this->pdo->prepare('INSERT INTO import_runs (source_files) VALUES (:source)')
            ->execute(['source' => json_encode(['fresh_empty'], JSON_UNESCAPED_UNICODE)]);

        return [
            'teams' => 0,
            'members' => 0,
            'desks' => 24,
            'lockers' => 0,
            'charges' => 0,
            'transactions' => 0,
            'rate_settings' => 0,
        ];
    }
}
