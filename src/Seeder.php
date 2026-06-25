<?php

declare(strict_types=1);

final class Seeder
{
    public function __construct(private readonly PDO $pdo)
    {
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
             FROM desks WHERE team_id = :team_id'
        );
        $deskStats->execute(['team_id' => $teamId]);
        $stats = $deskStats->fetch() ?: ['desk_count' => 0, 'informal_seats' => 0];
        $deskCount = (int) ($stats['desk_count'] ?? 0);
        $informalSeats = (int) ($stats['informal_seats'] ?? 0);
        if ($deskCount === 0) {
            return [];
        }

        $rates = $this->defaultRates($fiscalYear);
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
     * @return array{charge_rate:int, informal_rent_rate:int}
     */
    private function defaultRates(string $fiscalYear): array
    {
        $statement = $this->pdo->prepare(
            'SELECT charge_rate, informal_rent_rate FROM rate_settings
             WHERE fiscal_year = :fiscal_year ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['fiscal_year' => $fiscalYear]);
        $row = $statement->fetch() ?: [];

        return [
            'charge_rate' => (int) ($row['charge_rate'] ?? 0),
            'informal_rent_rate' => (int) ($row['informal_rent_rate'] ?? 0),
        ];
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

    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            Sql::columnList($columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $this->pdo->prepare($sql)->execute($data);
    }
}
