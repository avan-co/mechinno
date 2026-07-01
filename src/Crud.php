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
                    'contract_start' => ['label' => 'شروع قرارداد', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'contract_end' => ['label' => 'پایان قرارداد', 'type' => 'date', 'placeholder' => '1404/12/29'],
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
                    'wants_access' => [
                        'label' => 'دسترسی تردد',
                        'type' => 'select',
                        'options' => ['0' => 'خیر', '1' => 'بله — نیاز به کد تردد دارد'],
                    ],
                    'phone' => ['label' => 'تماس', 'type' => 'text'],
                    'national_id' => ['label' => 'کدملی', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'locker_requests' => [
                'table' => 'locker_requests',
                'title' => 'درخواست کمد',
                'order' => 'submitted_at DESC, id DESC',
                'status_field' => 'status',
                'status_options' => ['pending', 'approved', 'rejected'],
                'source' => false,
                'fields' => [
                    'notes' => ['label' => 'توضیحات درخواست', 'type' => 'textarea'],
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
                        'options' => ['formal' => 'رسمی', 'informal' => 'غیررسمی'],
                        'required' => true,
                    ],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                    'assignment_from' => ['label' => 'تاریخ شروع تخصیص', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'assignment_until' => ['label' => 'تاریخ پایان تخصیص', 'type' => 'date', 'placeholder' => '1404/12/29'],
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
                    'team_id' => ['label' => 'نهاد', 'type' => 'select', 'options' => []],
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
                    'payment_reference' => ['label' => 'شماره پیگیری / مرجع واریز', 'type' => 'text'],
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
            'development_plans' => [
                'table' => 'development_plans',
                'title' => 'برنامه توسعه',
                'order' => 'sort_order, id DESC',
                'status_field' => 'status',
                'status_options' => ['open', 'in_progress', 'done', 'cancelled'],
                'source' => false,
                'fields' => [
                    'title' => ['label' => 'عنوان', 'type' => 'text', 'required' => true],
                    'description' => ['label' => 'شرح', 'type' => 'textarea'],
                    'category' => [
                        'label' => 'نوع',
                        'type' => 'select',
                        'options' => [
                            'idea' => 'ایده',
                            'action' => 'اقدام',
                            'planned' => 'برنامه‌ریزی‌شده',
                        ],
                        'required' => true,
                    ],
                    'priority' => [
                        'label' => 'اولویت',
                        'type' => 'select',
                        'options' => ['high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'],
                        'required' => true,
                    ],
                    'status' => [
                        'label' => 'وضعیت',
                        'type' => 'select',
                        'options' => [
                            'open' => 'باز',
                            'in_progress' => 'در حال اجرا',
                            'done' => 'انجام‌شده',
                            'cancelled' => 'لغو‌شده',
                        ],
                        'required' => true,
                    ],
                    'due_date' => ['label' => 'موعد هدف', 'type' => 'date', 'placeholder' => '1405/06/01'],
                    'depends_on_id' => ['label' => 'وابسته به برنامه', 'type' => 'select', 'options' => []],
                    'estimated_cost' => ['label' => 'برآورد هزینه (ریال)', 'type' => 'number'],
                    'estimated_revenue' => ['label' => 'برآورد درآمد (ریال)', 'type' => 'number'],
                    'related_section' => [
                        'label' => 'بخش مرتبط',
                        'type' => 'select',
                        'options' => [
                            '' => '—',
                            'teams' => 'نهادها',
                            'members' => 'اعضا',
                            'desks' => 'میزها',
                            'lockers' => 'کمدها',
                            'charges' => 'شارژ',
                            'transactions' => 'مالی',
                        ],
                    ],
                    'notes' => ['label' => 'یادداشت', 'type' => 'textarea'],
                    'sort_order' => ['label' => 'ترتیب', 'type' => 'number'],
                ],
            ],
            'panel_users' => [
                'table' => 'panel_users',
                'title' => 'کاربر پنل',
                'order' => 'username',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'username' => ['label' => 'نام کاربری', 'type' => 'text', 'required' => true],
                    'password' => ['label' => 'رمز عبور', 'type' => 'password', 'required' => false],
                    'role' => [
                        'label' => 'نقش',
                        'type' => 'select',
                        'options' => [
                            'admin_editor' => 'مدیر — ویرایشگر',
                            'admin_viewer' => 'مدیر — مشاهده‌گر',
                        ],
                        'required' => true,
                    ],
                    'full_name' => ['label' => 'نام نمایشی', 'type' => 'text'],
                    'is_active' => ['label' => 'فعال', 'type' => 'select', 'options' => ['1' => 'بله', '0' => 'خیر'], 'required' => true],
                ],
            ],
        ];
    }

    public function meta(): array
    {
        $resources = [];
        $teamOptions = $this->teamOptions();
        $allowed = array_flip(Access::allowedCrudResources());

        foreach (self::definitions() as $name => $definition) {
            if (!isset($allowed[$name])) {
                continue;
            }
            foreach ($definition['fields'] as $field => $meta) {
                if ($field === 'team_id' || $field === 'owner_team_id') {
                    $definition['fields'][$field]['options'] = $teamOptions;
                }
                if ($name === 'development_plans' && $field === 'depends_on_id') {
                    $planOptions = ['' => '—'];
                    $statement = $this->pdo->query('SELECT id, title FROM development_plans ORDER BY title');
                    foreach ($statement->fetchAll() as $planRow) {
                        $planOptions[(string) $planRow['id']] = (string) $planRow['title'];
                    }
                    $definition['fields'][$field]['options'] = $planOptions;
                }
                if ($field === 'team_id' && Access::isTeam()) {
                    $scopedTeamId = Access::scopedTeamId();
                    if ($scopedTeamId !== null) {
                        $definition['fields'][$field]['options'] = array_intersect_key(
                            $teamOptions,
                            [(string) $scopedTeamId => true]
                        );
                    }
                }
            }
            $resources[$name] = [
                'title' => $definition['title'],
                'fields' => $this->fieldsForContext($name, $definition['fields']),
                'status_field' => $definition['status_field'],
                'status_options' => $definition['status_options'] ?? [],
            ];
        }

        return ['resources' => $resources];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private function fieldsForContext(string $resource, array $fields): array
    {
        if (Access::isTeam() && $resource === 'members') {
            return [
                'full_name' => array_merge($fields['full_name'], ['required' => true]),
                'phone' => array_merge($fields['phone'], ['required' => true]),
                'national_id' => array_merge($fields['national_id'], ['required' => true]),
                'wants_access' => array_merge($fields['wants_access'], ['required' => true]),
                'notes' => $fields['notes'],
            ];
        }
        if (!Access::isTeam() && $resource === 'members') {
            $optional = [];
            foreach ($fields as $key => $meta) {
                $optional[$key] = array_merge($meta, ['required' => false]);
            }

            return $optional;
        }
        if (Access::isTeam() && $resource === 'transactions') {
            return [
                'tx_date' => $fields['tx_date'],
                'fiscal_year' => array_merge($fields['fiscal_year'], ['required' => true]),
                'month_index' => array_merge($fields['month_index'], ['required' => true]),
                'amount' => ['label' => 'مبلغ واریز (ریال)', 'type' => 'number', 'required' => true],
                'payment_reference' => $fields['payment_reference'],
                'description' => ['label' => 'توضیح واریز', 'type' => 'textarea', 'required' => true],
                'notes' => $fields['notes'],
            ];
        }
        if (Access::isTeam() && $resource === 'desks') {
            unset($fields['assignment_from'], $fields['assignment_until']);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        if (Access::isTeam() && !in_array($resource, ['members', 'transactions', 'locker_requests'], true)) {
            throw new InvalidArgumentException('نهاد فقط می‌تواند عضو، درخواست کمد یا اعلام واریز ثبت کند.');
        }
        $definition = $this->definition($resource);
        $fields = $this->fieldsForContext($resource, $definition['fields']);
        $deskAssignmentDates = $this->extractDeskAssignmentDates($resource, $payload);
        $data = $this->sanitizePayload(['fields' => $fields], $payload, true);
        $this->stripDeskAssignmentColumns($resource, $data);
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
        if ($resource === 'desks') {
            (new DeskAssignments($this->pdo))->syncDeskAssignment(
                $id,
                array_merge($this->find($resource, $id), $deskAssignmentDates)
            );
        }
        if ($resource === 'teams') {
            $record = $this->find($resource, $id);
            EntityAccounts::provisionForTeam(
                $this->pdo,
                $id,
                (string) ($record['entity_code'] ?? ''),
                (string) ($record['leader'] ?? '')
            );
        }

        return $this->find($resource, $id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $resource, int $id, array $payload): array
    {
        if (Access::isTeam()) {
            throw new InvalidArgumentException('نهاد نمی‌تواند رکوردها را ویرایش کند.');
        }
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        $fields = $this->fieldsForContext($resource, $definition['fields']);
        $deskAssignmentDates = $this->extractDeskAssignmentDates($resource, $payload);
        $data = $this->sanitizePayload(['fields' => $fields], $payload, false);
        $this->stripDeskAssignmentColumns($resource, $data);
        $this->applyResourceRules($resource, $data, false, $id);

        if ($data === [] && $deskAssignmentDates === [] && $resource !== 'desks') {
            return $this->find($resource, $id);
        }

        if ($data !== []) {
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
        }

        if ($resource === 'transactions') {
            $this->syncTeamDepositIncome($id);
        }
        if ($resource === 'desks') {
            (new DeskAssignments($this->pdo))->syncDeskAssignment(
                $id,
                array_merge($this->find($resource, $id), $deskAssignmentDates)
            );
        }
        if ($resource === 'teams' && isset($data['leader'])) {
            EntityAccounts::syncLeaderName($this->pdo, $id, (string) $data['leader']);
        }

        return $this->find($resource, $id);
    }

    public function delete(string $resource, int $id): void
    {
        if (Access::isTeam()) {
            throw new InvalidArgumentException('نهاد نمی‌تواند رکوردها را حذف کند.');
        }
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        if ($resource === 'panel_users') {
            $this->assertPanelUserDeletable($id);
        }
        if ($resource === 'teams') {
            EntityAccounts::deleteForTeam($this->pdo, $id);
        }
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

        $row = $this->stripSensitiveFields($resource, Repository::stripLegacyColumns($row));
        if ($resource === 'desks') {
            $row = $this->enrichDeskAssignment($row);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractDeskAssignmentDates(string $resource, array $payload): array
    {
        if ($resource !== 'desks') {
            return [];
        }
        $dates = [];
        foreach (['assignment_from', 'assignment_until'] as $field) {
            if (array_key_exists($field, $payload)) {
                $dates[$field] = $payload[$field];
            }
        }

        return $dates;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stripDeskAssignmentColumns(string $resource, array &$data): void
    {
        if ($resource !== 'desks') {
            return;
        }
        unset($data['assignment_from'], $data['assignment_until']);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichDeskAssignment(array $row): array
    {
        $statement = $this->pdo->prepare(
            'SELECT assigned_from, assigned_until FROM desk_assignments
             WHERE desk_id = :desk_id
             ORDER BY CASE WHEN assigned_until IS NULL THEN 0 ELSE 1 END, id DESC
             LIMIT 1'
        );
        $statement->execute(['desk_id' => (int) ($row['id'] ?? 0)]);
        $assignment = $statement->fetch();
        if ($assignment !== false) {
            $row['assignment_from'] = $assignment['assigned_from'] ?? '';
            $row['assignment_until'] = $assignment['assigned_until'] ?? '';
        } else {
            $row['assignment_from'] = '';
            $row['assignment_until'] = '';
        }

        return $row;
    }

    private function stripSensitiveFields(string $resource, array $row): array
    {
        if ($resource === 'panel_users') {
            unset($row['password_hash'], $row['password_plain']);
        }

        return $row;
    }

    private function assertPanelUserDeletable(int $id): void
    {
        if (Access::userId() > 0 && $id === Access::userId()) {
            throw new InvalidArgumentException('نمی‌توانید حساب کاربری خود را حذف کنید.');
        }

        $statement = $this->pdo->prepare('SELECT role FROM panel_users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $role = (string) ($statement->fetchColumn() ?: '');
        if ($role !== Access::ROLE_ADMIN_EDITOR) {
            return;
        }

        $countStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM panel_users WHERE role = :role AND is_active = 1'
        );
        $countStatement->execute(['role' => Access::ROLE_ADMIN_EDITOR]);
        $count = (int) $countStatement->fetchColumn();
        if ($count <= 1) {
            throw new InvalidArgumentException('حداقل یک مدیر ویرایشگر باید فعال بماند.');
        }
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
            if (Access::isTeam()) {
                $teamId = Access::scopedTeamId();
                if ($teamId === null) {
                    throw new InvalidArgumentException('حساب نهاد معتبر نیست.');
                }
                $data['team_id'] = $teamId;
                $data['approval_status'] = 'pending';
                $data['submitted_at'] = JalaliDate::todayParts()['formatted'];
                unset($data['access_code']);
            } else {
                $data['approval_status'] = 'approved';
                if ($this->blank($data['full_name'] ?? null)) {
                    $data['full_name'] = 'بدون نام';
                }
            }
            if (isset($data['wants_access'])) {
                $data['wants_access'] = (int) $data['wants_access'];
            } else {
                $data['wants_access'] = 0;
            }
        }
        if ($resource === 'locker_requests' && $creating) {
            $teamId = Access::scopedTeamId();
            if ($teamId === null) {
                throw new InvalidArgumentException('حساب نهاد معتبر نیست.');
            }
            $data['team_id'] = $teamId;
            $data['status'] = 'pending';
            $data['submitted_at'] = JalaliDate::todayParts()['formatted'];
        }
        if ($resource === 'teams' && $creating) {
            if (empty($data['contract_start'])) {
                $today = JalaliDate::todayParts();
                $data['contract_start'] = sprintf('%04d/%02d/01', $today['year'], $today['month']);
            }
            if (empty($data['contract_end'])) {
                $start = JalaliDate::tryNormalize($data['contract_start'] ?? '');
                if ($start !== '') {
                    $year = (int) substr($start, 0, 4);
                    $data['contract_end'] = sprintf('%04d/12/29', $year);
                }
            }
        }
        if ($resource === 'lockers') {
            if ($creating && empty($data['status'])) {
                $data['status'] = 'خالی';
            }
            $current = [];
            if (!$creating && $recordId > 0) {
                $statement = $this->pdo->prepare('SELECT status, team_id FROM lockers WHERE id = :id');
                $statement->execute(['id' => $recordId]);
                $current = $statement->fetch() ?: [];
            }
            $status = (string) ($data['status'] ?? $current['status'] ?? 'خالی');
            if (in_array($status, ['خالی', 'خراب'], true)) {
                $data['team_id'] = null;
            } elseif (in_array($status, ['تخصیص یافته', 'رزرو'], true)) {
                $teamId = $data['team_id'] ?? $current['team_id'] ?? null;
                if ($this->blank($teamId)) {
                    throw new InvalidArgumentException('برای وضعیت «تخصیص یافته» یا «رزرو» انتخاب نهاد الزامی است.');
                }
                $data['team_id'] = (int) $teamId;
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
            if (!empty($data['team_id']) && empty($data['status']) && empty($current['status'] ?? null)) {
                $data['status'] = 'تخصیص یافته';
            }
        }
        if ($resource === 'transactions') {
            $data['source_file'] = 'manual';
            if (isset($data['fiscal_year'])) {
                $data['fiscal_year'] = JalaliDate::normalizeDigits($data['fiscal_year']);
            }
            if (($data['category'] ?? '') === 'واریز تیم' && (int) ($data['amount'] ?? 0) < 0) {
                $data['amount'] = abs((int) $data['amount']);
            }
            if (Access::isTeam()) {
                $teamId = Access::scopedTeamId();
                if ($teamId === null) {
                    throw new InvalidArgumentException('حساب نهاد معتبر نیست.');
                }
                $data['category'] = 'واریز تیم';
                $data['team_id'] = $teamId;
                $data['confirmed'] = 0;
                $data['payment_status'] = 'pending';
                $data['announced_at'] = JalaliDate::todayParts()['formatted'];
                $data['amount'] = abs((int) ($data['amount'] ?? 0));
            } elseif (($data['category'] ?? '') !== 'واریز تیم') {
                $data['team_id'] = null;
                $data['fiscal_year'] = null;
                $data['month_index'] = null;
                $data['payment_status'] = 'approved';
                $data['confirmed'] = 1;
                if (($data['category'] ?? '') === 'هزینه' && (int) ($data['amount'] ?? 0) > 0) {
                    $data['amount'] = -abs((int) $data['amount']);
                }
                if (($data['category'] ?? '') === 'درآمد' && (int) ($data['amount'] ?? 0) < 0) {
                    $data['amount'] = abs((int) $data['amount']);
                }
            } else {
                $data['payment_status'] = 'approved';
                $data['confirmed'] = (int) ($data['confirmed'] ?? 1);
                $description = trim((string) ($data['description'] ?? ''));
                if ($description !== '' && !str_starts_with($description, 'ثبت مستقیم مدیر')) {
                    $data['description'] = 'ثبت مستقیم مدیر — ' . $description;
                } elseif ($description === '') {
                    $data['description'] = 'ثبت مستقیم مدیر — دریافت شارژ';
                }
            }
        }
        if ($resource === 'charges') {
            $data['source_file'] = 'manual';
            $data['source_sheet'] = 'panel';
            if (isset($data['fiscal_year'])) {
                $data['fiscal_year'] = JalaliDate::normalizeDigits($data['fiscal_year']);
            }
            if (isset($data['month_index'])) {
                $data['month_name'] = self::monthName((int) $data['month_index']);
            }
            $teamId = (int) ($data['team_id'] ?? 0);
            if ($teamId > 0) {
                $statement = $this->pdo->prepare('SELECT name FROM teams WHERE id = :id');
                $statement->execute(['id' => $teamId]);
                $teamName = $statement->fetchColumn();
                if ($teamName !== false) {
                    $data['team_name'] = (string) $teamName;
                }
            }
            $charge = (int) ($data['charge_amount'] ?? 0);
            $rent = (int) ($data['rent_amount'] ?? 0);
            if (empty($data['amount']) && ($charge > 0 || $rent > 0)) {
                $data['amount'] = $charge + $rent;
            }
        }
        if ($resource === 'rate_settings' && isset($data['fiscal_year'])) {
            $data['fiscal_year'] = JalaliDate::normalizeDigits($data['fiscal_year']);
        }
        if ($resource === 'panel_users') {
            $role = (string) ($data['role'] ?? '');
            if ($role === Access::ROLE_TEAM) {
                throw new InvalidArgumentException('کاربر نهاد هنگام ثبت نهاد خودکار ساخته می‌شود.');
            }
            if (!in_array($role, [Access::ROLE_ADMIN_EDITOR, Access::ROLE_ADMIN_VIEWER], true)) {
                throw new InvalidArgumentException('نقش کاربر معتبر نیست.');
            }
            $data['team_id'] = null;
            $plainPassword = trim((string) ($data['password'] ?? ''));
            unset($data['password']);
            if ($creating) {
                if ($plainPassword === '') {
                    throw new InvalidArgumentException('رمز عبور الزامی است.');
                }
                $data['password_hash'] = UserAccounts::hashPassword($plainPassword);
                $data['password_plain'] = $plainPassword;
            } elseif ($plainPassword !== '') {
                $data['password_hash'] = UserAccounts::hashPassword($plainPassword);
                $data['password_plain'] = $plainPassword;
            }
            if (!isset($data['is_active'])) {
                $data['is_active'] = 1;
            }
        }
        if ($resource === 'development_plans') {
            $today = JalaliDate::todayParts()['formatted'];
            if ($creating) {
                $data['created_at'] = $today;
                $data['status'] = $data['status'] ?? 'open';
                $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
            }
            if (isset($data['depends_on_id']) && $this->blank($data['depends_on_id'])) {
                $data['depends_on_id'] = null;
            } elseif (isset($data['depends_on_id'])) {
                $data['depends_on_id'] = (int) $data['depends_on_id'];
            }
            if (isset($data['estimated_cost'])) {
                $data['estimated_cost'] = $this->blank($data['estimated_cost']) ? null : (int) $data['estimated_cost'];
            }
            if (isset($data['estimated_revenue'])) {
                $data['estimated_revenue'] = $this->blank($data['estimated_revenue']) ? null : (int) $data['estimated_revenue'];
            }
            if (isset($data['related_section']) && $this->blank($data['related_section'])) {
                $data['related_section'] = null;
            }
            $data['updated_at'] = $today;
        }
        if ($resource === 'desks') {
            if (isset($data['usage_type']) && ($data['usage_type'] ?? '') === 'mixed') {
                $data['usage_type'] = 'formal';
            }
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
        if ($type === 'select' && (in_array($value, ['0', '1'], true) || (is_numeric($value) && in_array((int) $value, [0, 1], true)))) {
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
