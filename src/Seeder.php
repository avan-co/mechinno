<?php

declare(strict_types=1);

final class Seeder
{
    private readonly Identifier $ids;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $basePath,
    ) {
        $this->ids = new Identifier($pdo);
    }

    /**
     * @return array<string, int>
     */
    public function seedFromFile(?string $path = null): array
    {
        $path ??= $this->basePath . '/data/initial-seed.json';
        if (!is_file($path)) {
            throw new RuntimeException('فایل داده اولیه پیدا نشد: data/initial-seed.json');
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        Schema::migrate($this->pdo);
        Schema::reset($this->pdo);
        Schema::seedDesks($this->pdo);

        $meta = $payload['meta'] ?? [];
        $fiscalYear = (string) ($meta['fiscal_year'] ?? '1404');
        $excelLockers = LockerCatalog::bootstrap($this->pdo, $this->basePath);
        $seedLockerNumbers = array_map(static fn (array $row): int => (int) ($row['number'] ?? 0), $payload['lockers'] ?? []);
        $seedLockerNumbers = array_values(array_filter($seedLockerNumbers, static fn (int $n): bool => $n > 0));
        if ($excelLockers === [] && $seedLockerNumbers !== []) {
            Schema::ensureLockerNumbers($this->pdo, $seedLockerNumbers);
        }

        $this->insertRateSetting($fiscalYear, $meta);
        $teamMap = $this->seedEntities($payload['entities'] ?? [], $fiscalYear);
        $memberMap = $this->seedMembers($payload['members'] ?? [], $teamMap);
        $this->seedLockers($payload['lockers'] ?? [], $teamMap, $memberMap);
        $this->seedPlans($payload['plans'] ?? [], $teamMap);
        $this->seedFinance($payload['finance'] ?? [], $teamMap, $fiscalYear);

        $this->insert('import_runs', [
            'source_files' => json_encode(['initial-seed.json'], JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'teams' => $this->count('teams'),
            'members' => $this->count('members'),
            'desks' => $this->count('desks'),
            'lockers' => $this->count('lockers'),
            'plans' => $this->count('plans'),
            'charges' => $this->count('charges'),
            'transactions' => $this->count('transactions'),
        ];
    }

    public function recalculateCharges(string $fiscalYear): void
    {
        $this->pdo->prepare('DELETE FROM charges WHERE fiscal_year = :fiscal_year AND source_file = :source')
            ->execute(['fiscal_year' => $fiscalYear, 'source' => 'system']);

        $teams = $this->pdo->query('SELECT id FROM teams')->fetchAll();
        foreach ($teams as $team) {
            $teamId = (int) $team['id'];
            $amounts = $this->monthlyAmountsForTeam($teamId, $fiscalYear);
            foreach ($amounts as $monthIndex => $parts) {
                if (($parts['amount'] ?? 0) <= 0) {
                    continue;
                }
                $this->insert('charges', [
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'month_index' => $monthIndex,
                    'month_name' => $this->monthName($monthIndex),
                    'charge_amount' => $parts['charge_amount'],
                    'rent_amount' => $parts['rent_amount'],
                    'amount' => $parts['amount'],
                    'note' => '',
                    'source_file' => 'system',
                    'source_sheet' => 'auto',
                ]);
            }
        }
    }

    /**
     * @return array<int, array{charge_amount:int, rent_amount:int, amount:int}>
     */
    public function monthlyAmountsForTeam(int $teamId, string $fiscalYear): array
    {
        $deskStats = $this->pdo->prepare(
            'SELECT COUNT(*) AS desk_count,
                    COALESCE(SUM(formal_seats), 0) AS formal_seats,
                    COALESCE(SUM(informal_seats), 0) AS informal_seats
             FROM desks
             WHERE team_id = :team_id'
        );
        $deskStats->execute(['team_id' => $teamId]);
        $stats = $deskStats->fetch() ?: ['desk_count' => 0, 'informal_seats' => 0];
        $deskCount = (int) ($stats['desk_count'] ?? 0);
        $informalSeats = (int) ($stats['informal_seats'] ?? 0);
        if ($deskCount === 0) {
            return [];
        }

        $rates = $this->teamRates($teamId, $fiscalYear);
        $chargeRate = (int) ($rates['charge_rate'] ?? 0);
        $rentRate = (int) ($rates['informal_rent_rate'] ?? 0);
        $monthlyCharge = $deskCount * $chargeRate;
        $monthlyRent = $informalSeats * $rentRate;
        $total = $monthlyCharge + $monthlyRent;

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = [
                'charge_amount' => $monthlyCharge,
                'rent_amount' => $monthlyRent,
                'amount' => $total,
            ];
        }

        return $months;
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return array<string, int>
     */
    private function seedEntities(array $entities, string $fiscalYear): array
    {
        $map = [];
        foreach ($entities as $entity) {
            $type = (string) ($entity['entity_type'] ?? 'team');
            $name = (string) ($entity['name'] ?? '');
            $this->insert('teams', [
                'entity_code' => $this->ids->nextEntityCode($type),
                'entity_type' => $type,
                'name' => $name,
                'leader' => $entity['leader'] ?? null,
                'phone' => $entity['phone'] ?? null,
                'joined_at' => $entity['joined_at'] ?? null,
                'warning' => $entity['warning'] ?? null,
                'notes' => $entity['notes'] ?? null,
                'source_file' => 'seed',
                'source_sheet' => 'entities',
            ]);
            $teamId = (int) $this->pdo->lastInsertId();
            $map[$name] = $teamId;

            foreach ($entity['desks'] ?? [] as $desk) {
                $number = (int) ($desk['number'] ?? 0);
                if ($number < 1 || $number > 24) {
                    continue;
                }
                $formal = (int) ($desk['formal_seats'] ?? 0);
                $informal = (int) ($desk['informal_seats'] ?? 0);
                $usage = (string) ($desk['usage_type'] ?? 'informal');
                $this->pdo->prepare(
                    'UPDATE desks
                     SET team_id = :team_id,
                         usage_type = :usage_type,
                         formal_seats = :formal_seats,
                         informal_seats = :informal_seats
                     WHERE number = :number'
                )->execute([
                    'team_id' => $teamId,
                    'usage_type' => $usage,
                    'formal_seats' => $formal,
                    'informal_seats' => $informal,
                    'number' => $number,
                ]);
            }

            $rates = $entity['rates'] ?? [];
            if ($rates !== []) {
                $this->insert('team_rates', [
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'charge_rate' => $rates['charge_rate'] ?? null,
                    'informal_rent_rate' => $rates['informal_rent_rate'] ?? null,
                    'notes' => 'seed',
                ]);
            }
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $members
     * @param array<string, int> $teamMap
     * @return array<string, int>
     */
    private function seedMembers(array $members, array $teamMap): array
    {
        $map = [];
        foreach ($members as $member) {
            $teamName = (string) ($member['team'] ?? '');
            $teamId = $teamMap[$teamName] ?? null;
            $this->insert('members', [
                'member_code' => $this->ids->nextMemberCode(),
                'team_id' => $teamId,
                'access_code' => $member['access_code'] ?? null,
                'full_name' => $member['full_name'] ?? '',
                'phone' => $member['phone'] ?? null,
                'national_id' => $member['national_id'] ?? null,
                'notes' => $member['notes'] ?? null,
                'source_file' => 'seed',
                'source_sheet' => 'members',
            ]);
            $memberId = (int) $this->pdo->lastInsertId();
            $map[(string) ($member['full_name'] ?? '')] = $memberId;

            foreach ($member['desks'] ?? [] as $deskNumber) {
                $deskId = $this->deskIdByNumber((int) $deskNumber);
                if ($deskId === null) {
                    continue;
                }
                $this->attachMemberDesk($memberId, $deskId);
            }
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $lockers
     * @param array<string, int> $teamMap
     * @param array<string, int> $memberMap
     */
    private function seedLockers(array $lockers, array $teamMap, array $memberMap): void
    {
        foreach ($lockers as $locker) {
            $number = (int) ($locker['number'] ?? 0);
            if ($number < 1) {
                continue;
            }
            $teamName = (string) ($locker['team'] ?? '');
            $memberName = (string) ($locker['member'] ?? '');
            $existing = $this->pdo->prepare('SELECT id FROM lockers WHERE locker_number = :number LIMIT 1');
            $existing->execute(['number' => $number]);
            $lockerId = $existing->fetchColumn();
            $payload = [
                'team_id' => $teamMap[$teamName] ?? null,
                'member_id' => $memberMap[$memberName] ?? null,
                'status' => $locker['status'] ?? 'خالی',
                'delivered_at' => $locker['delivered_at'] ?? null,
                'key_number' => $locker['key_number'] ?? null,
                'spare_key' => $locker['spare_key'] ?? 'ندارد',
                'notes' => $locker['notes'] ?? null,
                'source_file' => 'seed',
                'source_sheet' => 'lockers',
            ];
            if ($lockerId === false) {
                $this->insert('lockers', array_merge(['locker_number' => $number], $payload));
            } else {
                $assignments = [];
                foreach ($payload as $column => $value) {
                    $assignments[] = Sql::quoteIdentifier($column) . ' = :' . $column;
                }
                $payload['number'] = $number;
                $this->pdo->prepare('UPDATE lockers SET ' . implode(', ', $assignments) . ' WHERE locker_number = :number')
                    ->execute($payload);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @param array<string, int> $teamMap
     */
    private function seedPlans(array $plans, array $teamMap): void
    {
        foreach ($plans as $plan) {
            $owner = (string) ($plan['owner_team'] ?? '');
            $this->insert('plans', [
                'plan_code' => $this->ids->nextPlanCode(),
                'status' => $plan['status'] ?? 'پیشنهادی',
                'priority' => $plan['priority'] ?? 'متوسط',
                'title' => $plan['title'] ?? '',
                'owner_team_id' => $teamMap[$owner] ?? null,
                'proposed_budget' => $plan['proposed_budget'] ?? null,
                'start_date' => $plan['start_date'] ?? null,
                'end_date' => $plan['end_date'] ?? null,
                'progress' => $plan['progress'] ?? 0,
                'notes' => $plan['notes'] ?? null,
                'source_file' => 'seed',
                'source_sheet' => 'plans',
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int> $teamMap
     */
    private function seedFinance(array $rows, array $teamMap, string $fiscalYear): void
    {
        foreach ($rows as $row) {
            $teamName = (string) ($row['team'] ?? '');
            $this->insert('transactions', [
                'tx_date' => $row['tx_date'] ?? null,
                'description' => $row['description'] ?? '',
                'amount' => $row['amount'] ?? 0,
                'category' => $row['category'] ?? 'درآمد',
                'team_id' => $teamMap[$teamName] ?? null,
                'fiscal_year' => $row['fiscal_year'] ?? $fiscalYear,
                'month_index' => $row['month_index'] ?? null,
                'confirmed' => 1,
                'notes' => $row['notes'] ?? null,
                'source_file' => 'seed',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function insertRateSetting(string $fiscalYear, array $meta): void
    {
        if (!isset($meta['default_charge_rate']) && !isset($meta['default_informal_rent_rate'])) {
            return;
        }

        $this->insert('rate_settings', [
            'fiscal_year' => $fiscalYear,
            'title' => 'نرخ پیش‌فرض مرکز',
            'charge_rate' => $meta['default_charge_rate'] ?? 3500000,
            'informal_rent_rate' => $meta['default_informal_rent_rate'] ?? 1200000,
            'effective_from' => $fiscalYear . '/01/01',
            'notes' => 'seed',
        ]);
    }

    /**
     * @return array{charge_rate:int, informal_rent_rate:int}
     */
    private function teamRates(int $teamId, string $fiscalYear): array
    {
        $statement = $this->pdo->prepare(
            'SELECT charge_rate, informal_rent_rate FROM team_rates
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year
             LIMIT 1'
        );
        $statement->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        $teamRate = $statement->fetch();
        if ($teamRate !== false) {
            return [
                'charge_rate' => (int) ($teamRate['charge_rate'] ?? 0),
                'informal_rent_rate' => (int) ($teamRate['informal_rent_rate'] ?? 0),
            ];
        }

        $default = $this->pdo->prepare(
            'SELECT charge_rate, informal_rent_rate FROM rate_settings
             WHERE fiscal_year = :fiscal_year
             ORDER BY id DESC
             LIMIT 1'
        );
        $default->execute(['fiscal_year' => $fiscalYear]);
        $row = $default->fetch() ?: [];

        return [
            'charge_rate' => (int) ($row['charge_rate'] ?? 0),
            'informal_rent_rate' => (int) ($row['informal_rent_rate'] ?? 0),
        ];
    }

    private function deskIdByNumber(int $number): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM desks WHERE number = :number LIMIT 1');
        $statement->execute(['number' => $number]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function monthName(int $index): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$index] ?? '';
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function attachMemberDesk(int $memberId, int $deskId): void
    {
        $exists = $this->pdo->prepare('SELECT 1 FROM member_desks WHERE member_id = :member_id AND desk_id = :desk_id');
        $exists->execute(['member_id' => $memberId, 'desk_id' => $deskId]);
        if ($exists->fetchColumn() !== false) {
            return;
        }
        $this->pdo->prepare('INSERT INTO member_desks (member_id, desk_id) VALUES (:member_id, :desk_id)')
            ->execute(['member_id' => $memberId, 'desk_id' => $deskId]);
    }

    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            Sql::columnList($columns),
            implode(', ', $placeholders)
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($data);
    }
}
