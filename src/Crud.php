<?php

declare(strict_types=1);

final class Crud
{
    private readonly Identifier $ids;

    public function __construct(private readonly PDO $pdo)
    {
        $this->ids = new Identifier($pdo);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'teams' => [
                'table' => 'teams',
                'title' => 'نهاد',
                'order' => 'entity_type, name',
                'status_field' => null,
                'source' => true,
                'fields' => [
                    'entity_type' => [
                        'label' => 'نوع',
                        'type' => 'select',
                        'options' => ['team' => 'تیم', 'company' => 'شرکت', 'student' => 'دانشجو'],
                        'required' => true,
                    ],
                    'name' => ['label' => 'نام', 'type' => 'text', 'required' => true],
                    'leader' => ['label' => 'سرگروه / مسئول', 'type' => 'text'],
                    'phone' => ['label' => 'تماس', 'type' => 'text'],
                    'joined_at' => ['label' => 'تاریخ عضویت', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'warning' => ['label' => 'اخطار', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'members' => [
                'table' => 'members',
                'title' => 'عضو',
                'order' => 'id',
                'status_field' => null,
                'source' => true,
                'fields' => [
                    'team_id' => ['label' => 'نهاد (تیم / شرکت / دانشجو)', 'type' => 'select', 'options' => [], 'required' => true],
                    'full_name' => ['label' => 'نام', 'type' => 'text', 'required' => true],
                    'access_code' => ['label' => 'کد دستگاه تردد', 'type' => 'text'],
                    'phone' => ['label' => 'تماس', 'type' => 'text'],
                    'national_id' => ['label' => 'کدملی', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'desks' => [
                'table' => 'desks',
                'title' => 'میز',
                'order' => 'number',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'team_id' => ['label' => 'تیم / شرکت', 'type' => 'select', 'options' => []],
                    'usage_type' => [
                        'label' => 'نوع استفاده',
                        'type' => 'select',
                        'options' => ['formal' => 'رسمی', 'informal' => 'غیررسمی', 'mixed' => 'ترکیبی'],
                        'required' => true,
                    ],
                    'formal_seats' => ['label' => 'صندلی رسمی', 'type' => 'number'],
                    'informal_seats' => ['label' => 'صندلی غیررسمی', 'type' => 'number'],
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
                    'locker_number' => ['label' => 'شماره کمد', 'type' => 'number', 'required' => true],
                    'team_id' => ['label' => 'نهاد', 'type' => 'select', 'options' => [], 'required' => true],
                    'status' => ['label' => 'وضعیت', 'type' => 'select', 'options' => ['تخصیص یافته', 'رزرو', 'خالی', 'خراب']],
                    'delivered_at' => ['label' => 'تاریخ تحویل', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'key_number' => ['label' => 'شماره کلید', 'type' => 'text'],
                    'spare_key' => ['label' => 'کلید یدک', 'type' => 'select', 'options' => ['دارد', 'ندارد']],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'transactions' => [
                'table' => 'transactions',
                'title' => 'تراکنش مالی',
                'order' => 'tx_date DESC, id DESC',
                'status_field' => 'category',
                'status_options' => ['درآمد', 'هزینه', 'واریز تیم'],
                'source' => false,
                'fields' => [
                    'tx_date' => ['label' => 'تاریخ', 'type' => 'date', 'placeholder' => '1404/01/01', 'required' => true],
                    'description' => ['label' => 'شرح', 'type' => 'textarea', 'required' => true],
                    'amount' => ['label' => 'مبلغ (مثبت درآمد / منفی هزینه)', 'type' => 'number', 'required' => true],
                    'category' => ['label' => 'دسته', 'type' => 'select', 'options' => [
                        'درآمد' => 'درآمد',
                        'هزینه' => 'هزینه',
                        'واریز تیم' => 'دریافت از نهاد',
                    ], 'required' => true],
                    'team_id' => ['label' => 'نهاد (برای دریافت شارژ)', 'type' => 'select', 'options' => []],
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text'],
                    'month_index' => ['label' => 'ماه', 'type' => 'select', 'options' => self::monthOptions()],
                    'confirmed' => ['label' => 'تأیید شده', 'type' => 'select', 'options' => ['1' => 'بله', '0' => 'خیر']],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'charges' => [
                'table' => 'charges',
                'title' => 'شارژ ماهانه',
                'order' => 'fiscal_year, month_index, team_id',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'team_id' => ['label' => 'نهاد', 'type' => 'select', 'options' => [], 'required' => true],
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text', 'required' => true],
                    'month_index' => ['label' => 'ماه', 'type' => 'select', 'options' => self::monthOptions(), 'required' => true],
                    'charge_amount' => ['label' => 'مبلغ شارژ', 'type' => 'number'],
                    'rent_amount' => ['label' => 'مبلغ اجاره غیررسمی', 'type' => 'number'],
                    'amount' => ['label' => 'جمع کل (دستی)', 'type' => 'number', 'required' => true],
                    'note' => ['label' => 'یادداشت', 'type' => 'textarea'],
                ],
            ],
            'rate_settings' => [
                'table' => 'rate_settings',
                'title' => 'نرخ پیش‌فرض',
                'order' => 'fiscal_year, id',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text', 'required' => true],
                    'title' => ['label' => 'عنوان', 'type' => 'text', 'required' => true],
                    'charge_rate' => ['label' => 'نرخ شارژ ماهانه هر میز (۲ صندلی)', 'type' => 'number', 'required' => true],
                    'informal_rent_rate' => ['label' => 'نرخ اجاره غیررسمی هر میز', 'type' => 'number', 'required' => true],
                    'effective_from' => ['label' => 'تاریخ اثر (شروع ماه)', 'type' => 'date', 'placeholder' => '1405/01/01'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    public function meta(): array
    {
        $resources = [];
        $teamOptions = $this->teamOptions();

        foreach (self::definitions() as $name => $definition) {
            foreach ($definition['fields'] as $field => $meta) {
                if ($field === 'team_id' || $field === 'owner_team_id') {
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
        $this->applyResourceRules($resource, $data, true);

        $columns = array_keys($data);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $definition['table'],
            Sql::columnList($columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        ));
        $statement->execute($data);
        $id = (int) $this->pdo->lastInsertId();
        if ($resource === 'transactions') {
            $this->syncTeamDepositIncome($id);
        }

        return $this->find($resource, $id);
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
        $this->applyResourceRules($resource, $data, false, $id);

        if ($data === []) {
            return $this->find($resource, $id);
        }

        $assignments = array_map(
            static fn (string $column): string => Sql::quoteIdentifier($column) . " = :{$column}",
            array_keys($data)
        );
        $data['id'] = $id;
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $definition['table'],
            implode(', ', $assignments)
        ));
        $statement->execute($data);

        if ($resource === 'transactions') {
            $this->syncTeamDepositIncome($id);
        }

        return $this->find($resource, $id);
    }

    public function delete(string $resource, int $id): void
    {
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $definition['table']))->execute(['id' => $id]);
    }

    public function updateStatus(string $resource, int $id, string $status): array
    {
        $definition = $this->definition($resource);
        $statusField = $definition['status_field'] ?? null;
        if (!is_string($statusField) || $statusField === '') {
            throw new InvalidArgumentException('این بخش تغییر وضعیت سریع ندارد.');
        }

        return $this->update($resource, $id, [$statusField => $status]);
    }

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

    /**
     * @param array<string, mixed> $data
     */
    private function applyResourceRules(string $resource, array &$data, bool $creating, int $recordId = 0): void
    {
        if (($creating || isset($data['source_file'])) && in_array($resource, ['teams', 'members', 'lockers'], true)) {
            $data['source_file'] = 'manual';
            $data['source_sheet'] = 'panel';
        }
        if ($resource === 'teams') {
            $type = (string) ($data['entity_type'] ?? 'team');
            if (!in_array($type, ['team', 'company', 'student'], true)) {
                throw new InvalidArgumentException('نوع نهاد معتبر نیست.');
            }
            if ($creating) {
                $data['entity_code'] = $this->ids->nextEntityCode($type);
            }
        }
        if ($resource === 'members' && $creating) {
            $data['member_code'] = $this->ids->nextMemberCode();
        }
        if ($resource === 'lockers') {
            if ($creating && empty($data['status'])) {
                $data['status'] = 'خالی';
            }
            $lockerNumber = (int) ($data['locker_number'] ?? 0);
            if ($lockerNumber > 0) {
                $statement = $this->pdo->prepare(
                    'SELECT id FROM lockers WHERE locker_number = :number' . ($creating ? '' : ' AND id <> :id')
                );
                $params = ['number' => $lockerNumber];
                if (!$creating && $recordId > 0) {
                    $params['id'] = $recordId;
                }
                $statement->execute($params);
                if ($statement->fetchColumn() !== false) {
                    throw new InvalidArgumentException('این شماره کمد قبلاً ثبت شده است.');
                }
            }
        }
        if ($resource === 'transactions') {
            $data['source_file'] = 'manual';
            if (($data['category'] ?? '') === 'واریز تیم' && (int) ($data['amount'] ?? 0) < 0) {
                $data['amount'] = abs((int) $data['amount']);
            }
            if (($data['category'] ?? '') !== 'واریز تیم') {
                $data['team_id'] = null;
                $data['fiscal_year'] = null;
                $data['month_index'] = null;
            }
        }
        if ($resource === 'charges') {
            $data['source_file'] = 'manual';
            $data['source_sheet'] = 'panel';
            if (isset($data['month_index'])) {
                $data['month_name'] = self::monthName((int) $data['month_index']);
            }
            $charge = (int) ($data['charge_amount'] ?? 0);
            $rent = (int) ($data['rent_amount'] ?? 0);
            if (empty($data['amount']) && ($charge > 0 || $rent > 0)) {
                $data['amount'] = $charge + $rent;
            }
        }
        if ($resource === 'desks') {
            $formal = (int) ($data['formal_seats'] ?? 0);
            $informal = (int) ($data['informal_seats'] ?? 0);
            if ($formal + $informal > 2) {
                throw new InvalidArgumentException('هر میز حداکثر ۲ صندلی دارد.');
            }
        }
        if ($resource === 'lockers' && !empty($data['team_id']) && empty($data['status'])) {
            $data['status'] = 'تخصیص یافته';
        }
    }

    private function syncTeamDepositIncome(int $transactionId): void
    {
        $row = $this->find('transactions', $transactionId);
        if (($row['category'] ?? '') !== 'واریز تیم' || (int) ($row['confirmed'] ?? 0) !== 1) {
            return;
        }
        $amount = (int) ($row['amount'] ?? 0);
        if ($amount <= 0) {
            $this->pdo->prepare('UPDATE transactions SET amount = :amount WHERE id = :id')
                ->execute(['amount' => abs($amount), 'id' => $transactionId]);
        }
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
        if ($type === 'select' && in_array($value, ['0', '1'], true)) {
            return (int) $value;
        }

        return (string) $value;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
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

    private function currentFiscalYear(): string
    {
        $year = $this->pdo->query('SELECT fiscal_year FROM rate_settings ORDER BY id DESC LIMIT 1')->fetchColumn();

        return $year === false ? '1404' : (string) $year;
    }

    /**
     * @return array<string, string>
     */
    private function teamOptions(): array
    {
        $options = [];
        $labels = ['team' => 'تیم', 'company' => 'شرکت', 'student' => 'دانشجو'];
        foreach ($this->pdo->query('SELECT id, entity_type, name, leader FROM teams ORDER BY entity_type, name')->fetchAll() as $team) {
            $type = $labels[$team['entity_type'] ?? 'team'] ?? 'نهاد';
            $label = $type . ' — ' . ($team['name'] ?? '');
            if (($team['leader'] ?? '') !== '') {
                $label .= ' (' . $team['leader'] . ')';
            }
            $options[(string) $team['id']] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function memberOptions(): array
    {
        $options = ['' => '—'];
        foreach ($this->pdo->query('SELECT id, full_name FROM members ORDER BY full_name')->fetchAll() as $member) {
            $options[(string) $member['id']] = (string) $member['full_name'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function deskOptions(): array
    {
        $options = [];
        foreach ($this->pdo->query(
            'SELECT d.id, d.number, d.team_id, t.name AS team_name
             FROM desks d
             LEFT JOIN teams t ON t.id = d.team_id
             ORDER BY d.number'
        )->fetchAll() as $desk) {
            $label = 'میز ' . $desk['number'];
            if (!empty($desk['team_name'])) {
                $label .= ' (' . $desk['team_name'] . ')';
            } elseif (empty($desk['team_id'])) {
                $label .= ' (آزاد)';
            }
            $options[(string) $desk['id']] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $options = ['' => '—'];
        foreach (self::months() as $index => $name) {
            $options[(string) $index] = $name;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function months(): array
    {
        return [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];
    }

    private static function monthName(int $index): string
    {
        return self::months()[$index] ?? '';
    }
}
