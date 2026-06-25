<?php

declare(strict_types=1);

final class Crud
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'members' => [
                'table' => 'members',
                'title' => 'عضو',
                'order' => 'id',
                'status_field' => null,
                'source' => true,
                'fields' => [
                    'team_id' => ['label' => 'تیم مرتبط', 'type' => 'select', 'options' => []],
                    'code' => ['label' => 'کد', 'type' => 'text'],
                    'full_name' => ['label' => 'نام', 'type' => 'text', 'required' => true],
                    'team_name' => ['label' => 'تیم/شرکت', 'type' => 'text'],
                    'desks' => ['label' => 'میز', 'type' => 'text'],
                    'lockers' => ['label' => 'کمد', 'type' => 'text'],
                    'power_strips' => ['label' => 'سه‌راهی', 'type' => 'text'],
                    'phone' => ['label' => 'تماس', 'type' => 'text'],
                    'national_id' => ['label' => 'کدملی', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'teams' => [
                'table' => 'teams',
                'title' => 'تیم',
                'order' => 'id',
                'status_field' => null,
                'source' => true,
                'fields' => [
                    'name' => ['label' => 'نام تیم', 'type' => 'text', 'required' => true],
                    'leader' => ['label' => 'سرگروه', 'type' => 'text'],
                    'phone' => ['label' => 'تماس', 'type' => 'text'],
                    'desk_count' => ['label' => 'تعداد میز', 'type' => 'number'],
                    'lockers' => ['label' => 'کمد', 'type' => 'text'],
                    'power_strips' => ['label' => 'سه‌راهی', 'type' => 'text'],
                    'joined_at' => ['label' => 'تاریخ عضویت', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'warning' => ['label' => 'اخطار', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'lockers' => [
                'table' => 'lockers',
                'title' => 'کمد',
                'order' => 'locker_number',
                'status_field' => 'status',
                'status_options' => ['تخصیص یافته', 'رزرو', 'خالی', 'خراب'],
                'source' => true,
                'fields' => [
                    'team_id' => ['label' => 'تیم مرتبط', 'type' => 'select', 'options' => []],
                    'locker_number' => ['label' => 'شماره کمد', 'type' => 'number', 'required' => true],
                    'status' => ['label' => 'وضعیت', 'type' => 'select', 'options' => ['تخصیص یافته', 'رزرو', 'خالی', 'خراب']],
                    'assigned_to' => ['label' => 'اختصاص به', 'type' => 'text'],
                    'delivered_at' => ['label' => 'تاریخ تحویل', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'key_number' => ['label' => 'شماره کلید', 'type' => 'text'],
                    'spare_key' => ['label' => 'کلید یدک', 'type' => 'select', 'options' => ['دارد', 'ندارد']],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'plans' => [
                'table' => 'plans',
                'title' => 'برنامه',
                'order' => 'plan_number',
                'status_field' => 'status',
                'status_options' => ['تخصیص یافته', 'در حال اجرا', 'انجام شده', 'خالی', 'خراب', 'لغو شده'],
                'source' => true,
                'fields' => [
                    'plan_number' => ['label' => 'شماره', 'type' => 'number'],
                    'status' => ['label' => 'وضعیت', 'type' => 'select', 'options' => ['تخصیص یافته', 'در حال اجرا', 'انجام شده', 'خالی', 'خراب', 'لغو شده']],
                    'title' => ['label' => 'عنوان', 'type' => 'textarea', 'required' => true],
                    'proposed_budget' => ['label' => 'بودجه پیشنهادی', 'type' => 'number'],
                    'cost_type' => ['label' => 'نوع هزینه', 'type' => 'text'],
                    'schedule' => ['label' => 'زمان‌بندی', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'charges' => [
                'table' => 'charges',
                'title' => 'شارژ',
                'order' => 'fiscal_year, month_index, id',
                'status_field' => null,
                'source' => true,
                'fields' => [
                    'team_id' => ['label' => 'تیم مرتبط', 'type' => 'select', 'options' => []],
                    'fiscal_year' => ['label' => 'سال', 'type' => 'text', 'required' => true],
                    'team_name' => ['label' => 'تیم', 'type' => 'text'],
                    'leader' => ['label' => 'سرگروه', 'type' => 'text'],
                    'desk_count' => ['label' => 'تعداد میز', 'type' => 'number'],
                    'month_index' => ['label' => 'ماه', 'type' => 'select', 'options' => self::monthOptions(), 'required' => true],
                    'amount' => ['label' => 'مبلغ', 'type' => 'number', 'required' => true],
                    'note' => ['label' => 'یادداشت', 'type' => 'textarea'],
                    'charge_rate' => ['label' => 'نرخ شارژ', 'type' => 'number'],
                    'rent_rate' => ['label' => 'نرخ اجاره', 'type' => 'number'],
                ],
            ],
            'transactions' => [
                'table' => 'transactions',
                'title' => 'تراکنش',
                'order' => 'id',
                'status_field' => 'category',
                'status_options' => ['درآمد', 'هزینه', 'دریافت', 'نامشخص'],
                'source' => false,
                'fields' => [
                    'tx_date' => ['label' => 'تاریخ', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'description' => ['label' => 'شرح', 'type' => 'textarea', 'required' => true],
                    'amount' => ['label' => 'مبلغ', 'type' => 'number', 'required' => true],
                    'category' => ['label' => 'دسته', 'type' => 'select', 'options' => ['درآمد', 'هزینه', 'دریافت', 'نامشخص']],
                    'invoice_count' => ['label' => 'تعداد فاکتور', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                    'suspected_amount_note' => ['label' => 'مبلغ مشکوک در توضیحات', 'type' => 'number'],
                ],
            ],
            'rate_settings' => [
                'table' => 'rate_settings',
                'title' => 'نرخ شارژ/اجاره',
                'order' => 'fiscal_year, id',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text', 'required' => true],
                    'title' => ['label' => 'عنوان', 'type' => 'text', 'required' => true],
                    'charge_rate' => ['label' => 'نرخ شارژ هر میز', 'type' => 'number', 'required' => true],
                    'rent_rate' => ['label' => 'نرخ اجاره هر میز', 'type' => 'number'],
                    'effective_from' => ['label' => 'تاریخ اثرگذاری', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $resources = [];
        $teamOptions = $this->teamOptions();
        foreach (self::definitions() as $name => $definition) {
            foreach ($definition['fields'] as $field => $meta) {
                if ($field === 'team_id') {
                    $definition['fields'][$field]['options'] = $teamOptions;
                }
            }
            $resources[$name] = [
                'title' => $definition['title'],
                'fields' => $definition['fields'],
                'status_field' => $definition['status_field'],
                'status_options' => $definition['status_options'] ?? [],
            ];
        }

        return ['resources' => $resources];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        $definition = $this->definition($resource);
        $data = $this->sanitizePayload($definition, $payload, true);
        $this->applyRelations($resource, $data);
        if ($resource === 'charges') {
            $data['month_name'] = self::monthName((int) ($data['month_index'] ?? 0));
            $this->applyDefaultRates($data);
        }
        if ($resource === 'transactions') {
            $data['batch_id'] = $this->manualBatchId();
        }
        if (($definition['source'] ?? false) === true) {
            $data['source_file'] = 'manual';
            $data['source_sheet'] = 'panel';
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $definition['table'],
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));
        $statement->execute($data);

        return $this->find($resource, (int) $this->pdo->lastInsertId());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $resource, int $id, array $payload): array
    {
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        $data = $this->sanitizePayload($definition, $payload, false);
        $this->applyRelations($resource, $data);
        if ($resource === 'charges' && isset($data['month_index'])) {
            $data['month_name'] = self::monthName((int) $data['month_index']);
        }
        if ($resource === 'transactions') {
            $data['batch_id'] = $this->manualBatchId();
        }
        if (($definition['source'] ?? false) === true) {
            $data['source_file'] = 'manual';
            $data['source_sheet'] = 'panel';
        }
        if ($data === []) {
            return $this->find($resource, $id);
        }

        $assignments = array_map(static fn (string $column): string => "{$column} = :{$column}", array_keys($data));
        $data['id'] = $id;
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $definition['table'],
            implode(', ', $assignments)
        ));
        $statement->execute($data);

        return $this->find($resource, $id);
    }

    public function delete(string $resource, int $id): void
    {
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        $statement = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $definition['table']));
        $statement->execute(['id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(string $resource, int $id, string $status): array
    {
        $definition = $this->definition($resource);
        $statusField = $definition['status_field'] ?? null;
        if (!is_string($statusField) || $statusField === '') {
            throw new InvalidArgumentException('این بخش تغییر وضعیت سریع ندارد.');
        }
        $options = $definition['status_options'] ?? [];
        if ($options !== [] && !in_array($status, $options, true)) {
            throw new InvalidArgumentException('وضعیت انتخاب‌شده معتبر نیست.');
        }

        return $this->update($resource, $id, [$statusField => $status]);
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $resource, int $id): array
    {
        $definition = $this->definition($resource);
        $statement = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id = :id', $definition['table']));
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('رکورد پیدا نشد.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resource): array
    {
        $definitions = self::definitions();
        if (!isset($definitions[$resource])) {
            throw new InvalidArgumentException('این بخش قابل ویرایش نیست.');
        }

        return $definitions[$resource];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $definition, array $payload, bool $creating): array
    {
        $data = [];
        foreach ($definition['fields'] as $field => $meta) {
            if (!$creating && !array_key_exists($field, $payload)) {
                continue;
            }
            $value = $payload[$field] ?? null;
            if (($meta['required'] ?? false) && $this->blank($value)) {
                throw new InvalidArgumentException(sprintf('فیلد «%s» الزامی است.', $meta['label']));
            }
            $data[$field] = $this->normalizeValue($value, (string) ($meta['type'] ?? 'text'));
        }

        return $data;
    }

    private function normalizeValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '') {
            return null;
        }
        if ($type === 'number') {
            $number = str_replace([',', '٬'], '', (string) $value);
            if (!preg_match('/^-?\d+$/', $number)) {
                throw new InvalidArgumentException('مقدار عددی معتبر نیست.');
            }
            return (int) $number;
        }
        if ($type === 'date') {
            return JalaliDate::normalize($value);
        }

        return (string) $value;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertExists(array $definition, int $id): void
    {
        $statement = $this->pdo->prepare(sprintf('SELECT id FROM %s WHERE id = :id', $definition['table']));
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('رکورد پیدا نشد.');
        }
    }

    private function manualBatchId(): int
    {
        $statement = $this->pdo->prepare("SELECT id FROM financial_batches WHERE sheet_name = 'manual' ORDER BY id LIMIT 1");
        $statement->execute();
        $existing = $statement->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO financial_batches
                (sheet_name, petty_cash_holder, petty_cash_number, previous_balance, new_deposit,
                 total_balance, received_at, from_date, to_date, source_file)
             VALUES ('manual', 'پنل', 'manual', 0, 0, 0, '', '', '', 'manual')"
        );
        $insert->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyRelations(string $resource, array &$data): void
    {
        if (!in_array($resource, ['members', 'lockers', 'charges'], true) || empty($data['team_id'])) {
            return;
        }
        $team = $this->teamById((int) $data['team_id']);
        if ($team === null) {
            throw new InvalidArgumentException('تیم انتخاب‌شده معتبر نیست.');
        }
        if ($resource === 'members') {
            $data['team_name'] = $team['name'];
        }
        if ($resource === 'lockers' && empty($data['assigned_to'])) {
            $data['assigned_to'] = $team['name'];
        }
        if ($resource === 'charges') {
            $data['team_name'] = $team['name'];
            $data['leader'] = $team['leader'];
            if (empty($data['desk_count'])) {
                $data['desk_count'] = $team['desk_count'];
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyDefaultRates(array &$data): void
    {
        if (!empty($data['charge_rate']) && !empty($data['rent_rate'])) {
            return;
        }
        $statement = $this->pdo->prepare(
            'SELECT charge_rate, rent_rate FROM rate_settings
             WHERE fiscal_year = :fiscal_year
             ORDER BY effective_from DESC, id DESC
             LIMIT 1'
        );
        $statement->execute(['fiscal_year' => $data['fiscal_year'] ?? '']);
        $rate = $statement->fetch();
        if ($rate === false) {
            return;
        }
        $data['charge_rate'] = $data['charge_rate'] ?: $rate['charge_rate'];
        $data['rent_rate'] = $data['rent_rate'] ?: $rate['rent_rate'];
    }

    /**
     * @return array<string, string>
     */
    private function teamOptions(): array
    {
        $options = [];
        foreach ($this->pdo->query('SELECT id, name, leader FROM teams ORDER BY name')->fetchAll() as $team) {
            $label = trim((string) ($team['name'] ?? ''));
            if (($team['leader'] ?? '') !== '') {
                $label .= ' - ' . $team['leader'];
            }
            $options[(string) $team['id']] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, leader, desk_count FROM teams WHERE id = :id');
        $statement->execute(['id' => $id]);
        $team = $statement->fetch();

        return $team === false ? null : $team;
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $options = [];
        foreach (self::months() as $index => $name) {
            $options[(string) $index] = $name;
        }

        return $options;
    }

    private static function monthName(int $index): string
    {
        return self::months()[$index] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private static function months(): array
    {
        return [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];
    }
}
