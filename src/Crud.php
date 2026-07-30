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
                'order' => 'is_active DESC, entity_type, name',
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
                    'leader' => ['label' => 'سرگروه / مسئول', 'type' => 'text', 'required' => true],
                    'phone' => ['label' => 'تماس مسئول', 'type' => 'text', 'required' => true],
                    'joined_at' => ['label' => 'تاریخ عضویت', 'type' => 'date', 'placeholder' => '1404/01/01'],
                    'is_active' => [
                        'label' => 'وضعیت نهاد',
                        'type' => 'select',
                        'options' => ['1' => 'فعال', '0' => 'غیرفعال'],
                    ],
                    'warning' => ['label' => 'اخطار', 'type' => 'text'],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                ],
            ],
            'team_contracts' => [
                'table' => 'team_contracts',
                'title' => 'قرارداد سالانه',
                'order' => 'fiscal_year DESC, team_id',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'team_id' => ['label' => 'نهاد', 'type' => 'select', 'options' => [], 'required' => true],
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text', 'required' => true, 'placeholder' => '1404'],
                    'contract_start' => ['label' => 'شروع قرارداد', 'type' => 'date', 'required' => true, 'placeholder' => '1404/01/01'],
                    'contract_end' => ['label' => 'پایان قرارداد', 'type' => 'date', 'required' => true, 'placeholder' => '1404/12/29'],
                    'formal_contract_amount' => ['label' => 'مبلغ کل قرارداد رسمی (ریال)', 'type' => 'number', 'required' => true, 'placeholder' => 'مبلغ کل قرارداد رسمی'],
                    'charge_rate_override' => ['label' => 'نرخ شارژ اختصاصی (اختیاری)', 'type' => 'number', 'placeholder' => 'خالی = نرخ عمومی'],
                    'informal_rent_rate_override' => ['label' => 'نرخ اجاره اختصاصی (اختیاری)', 'type' => 'number', 'placeholder' => 'خالی = نرخ عمومی'],
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
                'status_field' => null,
                'status_options' => [],
                'source' => false,
                'fields' => [
                    'notes' => ['label' => 'توضیحات درخواست', 'type' => 'textarea'],
                ],
            ],
            'member_requests' => [
                'table' => 'member_requests',
                'title' => 'درخواست تغییر عضو',
                'order' => 'submitted_at DESC, id DESC',
                'status_field' => null,
                'status_options' => [],
                'source' => false,
                'fields' => [
                    'member_id' => ['label' => 'عضو', 'type' => 'select', 'options' => [], 'required' => true],
                    'request_type' => [
                        'label' => 'نوع درخواست',
                        'type' => 'select',
                        'options' => ['update' => 'ویرایش اطلاعات', 'delete' => 'حذف عضو'],
                        'required' => true,
                    ],
                    'full_name' => ['label' => 'نام جدید', 'type' => 'text'],
                    'phone' => ['label' => 'موبایل جدید', 'type' => 'text'],
                    'national_id' => ['label' => 'کد ملی جدید', 'type' => 'text'],
                    'wants_access' => [
                        'label' => 'دسترسی تردد',
                        'type' => 'select',
                        'options' => ['0' => 'خیر', '1' => 'بله — نیاز به کد تردد دارد'],
                    ],
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
                    'team_id' => ['label' => 'نهاد (تیم / شرکت / دانشجو)', 'type' => 'select', 'options' => []],
                    'usage_type' => [
                        'label' => 'نوع استفاده',
                        'type' => 'select',
                        'options' => ['formal' => 'رسمی', 'informal' => 'غیررسمی', 'mixed' => 'ترکیبی'],
                        'required' => true,
                    ],
                    'notes' => ['label' => 'توضیحات', 'type' => 'textarea'],
                    'assignment_from_month' => ['label' => 'از ماه', 'type' => 'select', 'options' => self::monthOptionsRequired(), 'required' => true],
                    'assignment_until_month' => ['label' => 'تا ماه', 'type' => 'select', 'options' => self::monthOptionsRequired(), 'required' => true],
                ],
            ],
            'desk_assignments' => [
                'table' => 'desk_assignments',
                'title' => 'تخصیص میز (تاریخچه)',
                'order' => 'assigned_from DESC, desk_number',
                'status_field' => null,
                'source' => false,
                'fields' => [
                    'desk_id' => ['label' => 'میز', 'type' => 'select', 'options' => [], 'required' => true],
                    'team_id' => ['label' => 'نهاد', 'type' => 'select', 'options' => [], 'required' => true],
                    'usage_type' => [
                        'label' => 'نوع استفاده',
                        'type' => 'select',
                        'options' => ['formal' => 'رسمی', 'informal' => 'غیررسمی', 'mixed' => 'ترکیبی'],
                        'required' => true,
                    ],
                    'fiscal_year' => ['label' => 'سال مالی', 'type' => 'text', 'required' => true],
                    'assigned_from_month' => ['label' => 'از ماه', 'type' => 'select', 'options' => self::monthOptionsRequired(), 'required' => true],
                    'assigned_until_month' => ['label' => 'تا ماه', 'type' => 'select', 'options' => self::monthOptionsRequired(), 'required' => true],
                    'charge_exempt' => [
                        'label' => 'معاف از شارژ',
                        'type' => 'select',
                        'options' => ['0' => 'خیر', '1' => 'بله'],
                    ],
                    'rent_exempt' => [
                        'label' => 'معاف از اجاره',
                        'type' => 'select',
                        'options' => ['0' => 'خیر', '1' => 'بله'],
                    ],
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
                'status_field' => null,
                'status_options' => [],
                'source' => false,
                'fields' => [
                    'tx_date' => ['label' => 'تاریخ', 'type' => 'date', 'placeholder' => '1404/01/01', 'required' => true],
                    'description' => ['label' => 'شرح', 'type' => 'textarea', 'required' => true],
                    'amount' => ['label' => 'مبلغ (ریال)', 'type' => 'number', 'required' => true, 'placeholder' => 'درآمد مثبت / هزینه منفی'],
                    'category' => ['label' => 'دسته', 'type' => 'select', 'options' => [
                        'درآمد' => 'درآمد',
                        'هزینه' => 'هزینه',
                        'واریز تیم' => 'دریافت از نهاد',
                    ], 'required' => true],
                    'finance_subtype' => ['label' => 'نوع', 'type' => 'select', 'options' => []],
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
                    'charge_rate' => ['label' => 'نرخ شارژ ماهانه هر میز', 'type' => 'number', 'required' => true],
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
                if ($name === 'desk_assignments' && $field === 'desk_id') {
                    $definition['fields'][$field]['options'] = $this->deskOptions();
                }
                if ($name === 'member_requests' && $field === 'member_id') {
                    $definition['fields'][$field]['options'] = $this->approvedMemberOptionsForTeam(Access::scopedTeamId());
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
                $optional[$key] = array_merge($meta, ['required' => $key === 'team_id' ? true : false]);
            }

            return $optional;
        }
        if (Access::isTeam() && $resource === 'transactions') {
            return [
                'tx_date' => $fields['tx_date'],
                'payment_reference' => $fields['payment_reference'],
                'description' => ['label' => 'توضیح واریز', 'type' => 'textarea', 'required' => true],
                'notes' => $fields['notes'],
            ];
        }
        if ($resource === 'teams' && !Access::isTeam()) {
            unset($fields['is_active']);
        }
        if (Access::isTeam() && $resource === 'member_requests') {
            return [
                'request_type' => $fields['request_type'],
                'full_name' => array_merge($fields['full_name'], ['required' => true]),
                'phone' => array_merge($fields['phone'], ['required' => true]),
                'national_id' => array_merge($fields['national_id'], ['required' => true]),
                'wants_access' => $fields['wants_access'],
                'notes' => $fields['notes'],
            ];
        }
        if (Access::isTeam() && $resource === 'desks') {
            unset($fields['assignment_from'], $fields['assignment_until']);
        }
        if ($resource === 'development_plans' && !Access::isTeam()) {
            return array_intersect_key($fields, array_flip(['title', 'status', 'priority', 'due_date', 'notes']));
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $resource, array $payload): array
    {
        if (Access::isTeam() && !in_array($resource, ['members', 'transactions', 'locker_requests', 'member_requests'], true)) {
            throw new InvalidArgumentException('نهاد فقط می‌تواند عضو، درخواست کمد، درخواست تغییر عضو یا اعلام واریز ثبت کند.');
        }
        if ($resource === 'desks') {
            throw new InvalidArgumentException('میز جدید قابل ثبت نیست. فقط تخصیص میزهای موجود مجاز است.');
        }
        $definition = $this->definition($resource);
        $fields = $this->fieldsForContext($resource, $definition['fields']);
        $deskAssignmentDates = $this->extractDeskAssignmentDates($resource, $payload);
        $data = $this->sanitizePayload(['fields' => $fields], $payload, true);
        $this->stripDeskAssignmentColumns($resource, $data);
        if (Access::isTeam() && $resource === 'transactions') {
            $data['payment_plan'] = $payload['payment_plan'] ?? '';
        }
        if (Access::isTeam() && $resource === 'member_requests') {
            $data['member_id'] = $payload['member_id'] ?? '';
            $data['request_type'] = $payload['request_type'] ?? '';
        }
        $this->applyResourceRules($resource, $data, true);

        if ($resource === 'charges') {
            $existingId = $this->findChargeId(
                (int) ($data['team_id'] ?? 0),
                (string) ($data['fiscal_year'] ?? ''),
                (int) ($data['month_index'] ?? 0)
            );
            if ($existingId !== null) {
                return $this->update($resource, $existingId, $payload);
            }
        }
        if ($resource === 'desk_assignments') {
            $deskAssignments = new DeskAssignments($this->pdo);
            $existingId = $deskAssignments->findExistingRecordId(
                (int) ($data['desk_id'] ?? 0),
                JalaliDate::fiscalYearFromDate((string) ($data['assigned_from'] ?? '')),
                (int) ($data['team_id'] ?? 0)
            );
            if ($existingId !== null) {
                return $this->update($resource, $existingId, $payload);
            }
        }

        $lockTeamPayment = $resource === 'transactions'
            && Access::isTeam()
            && (string) ($data['category'] ?? '') === 'واریز تیم'
            && (string) ($data['payment_status'] ?? '') === 'pending';
        $startedTransaction = false;
        if ($lockTeamPayment && !$this->pdo->inTransaction()) {
            $this->beginImmediateTransaction();
            $startedTransaction = true;
            $this->lockTeamRow((int) ($data['team_id'] ?? 0));
            if ($this->countPendingTeamPayment((int) ($data['team_id'] ?? 0)) > 0) {
                if ($startedTransaction) {
                    $this->pdo->rollBack();
                }
                throw new InvalidArgumentException('اعلام واریز دیگری در انتظار تأیید است. ابتدا آن را پیگیری یا حذف کنید.');
            }
        }

        try {
            $columns = array_keys($data);
            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $definition['table'],
                Sql::columnList($columns),
                implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
            ));
            $statement->execute($data);
            $id = (int) $this->pdo->lastInsertId();
            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        if ($resource === 'transactions') {
            $this->syncTeamDepositIncome($id);
        }
        if ($resource === 'desks') {
            (new DeskAssignments($this->pdo))->syncDeskAssignment(
                $id,
                array_merge($this->find($resource, $id), $deskAssignmentDates)
            );
            $this->syncChargesForTeam((int) ($data['team_id'] ?? 0), $deskAssignmentDates);
        }
        if ($resource === 'desk_assignments') {
            $record = $this->find($resource, $id);
            (new DeskAssignments($this->pdo))->applyAssignmentRecord($record);
            $this->syncChargesForTeam((int) ($record['team_id'] ?? 0), $record);
        }
        if ($resource === 'teams') {
            $record = $this->find($resource, $id);
            $portalPassword = trim((string) ($payload['portal_password'] ?? ''));
            EntityAccounts::provisionForTeam(
                $this->pdo,
                $id,
                (string) ($record['entity_code'] ?? ''),
                (string) ($record['leader'] ?? ''),
                $portalPassword !== '' ? $portalPassword : null
            );
            (new TeamLeaders($this->pdo))->ensureLeaderMember($id);
        }
        if ($resource === 'team_contracts') {
            $record = $this->find($resource, $id);
            (new TeamContracts($this->pdo))->syncTeamContractCache((int) ($data['team_id'] ?? 0));
            $this->syncChargesForTeam(
                (int) ($record['team_id'] ?? $data['team_id'] ?? 0),
                [
                    'fiscal_year' => (string) ($record['fiscal_year'] ?? $data['fiscal_year'] ?? ''),
                ]
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
            $this->assertTeamCanMutate($resource, $id, 'update');
        }
        if ($resource === 'transactions') {
            $existing = $this->find($resource, $id);
            if (CenterLedger::isSystemSource($existing['source_file'] ?? null)) {
                throw new InvalidArgumentException('این ردیف سیستمی منسوخ است و قابل ویرایش نیست.');
            }
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
            $existingDesk = $this->find($resource, $id);
            $deskRecord = array_merge($existingDesk, $data, $deskAssignmentDates);
            (new DeskAssignments($this->pdo))->syncDeskAssignment($id, $deskRecord);
            $teamIds = array_filter([
                (int) ($existingDesk['team_id'] ?? 0),
                (int) ($deskRecord['team_id'] ?? 0),
            ]);
            foreach (array_unique($teamIds) as $teamId) {
                $this->syncChargesForTeam($teamId, $deskRecord);
            }
        }
        if ($resource === 'desk_assignments') {
            $record = $this->find($resource, $id);
            (new DeskAssignments($this->pdo))->applyAssignmentRecord($record, $id);
            $this->syncChargesForTeam((int) ($record['team_id'] ?? 0), $record);
        }
        if ($resource === 'teams' && isset($data['leader'])) {
            EntityAccounts::syncLeaderName($this->pdo, $id, (string) $data['leader']);
        }
        if ($resource === 'teams' && (isset($data['leader']) || isset($data['phone']))) {
            (new TeamLeaders($this->pdo))->syncLeaderFromTeam($id);
        }
        if ($resource === 'team_contracts') {
            $record = $this->find($resource, $id);
            (new TeamContracts($this->pdo))->syncTeamContractCache((int) ($record['team_id'] ?? $data['team_id'] ?? 0));
            $this->syncChargesForTeam(
                (int) ($record['team_id'] ?? $data['team_id'] ?? 0),
                [
                    'fiscal_year' => (string) ($record['fiscal_year'] ?? $data['fiscal_year'] ?? ''),
                ]
            );
        }

        return $this->find($resource, $id);
    }

    public function delete(string $resource, int $id): void
    {
        if (Access::isTeam()) {
            $this->assertTeamCanMutate($resource, $id, 'delete');
        }
        $definition = $this->definition($resource);
        $this->assertExists($definition, $id);
        if ($resource === 'members') {
            $row = $this->find($resource, $id);
            if ((int) ($row['is_leader'] ?? 0) === 1) {
                throw new InvalidArgumentException('ابتدا مسئول نهاد را از دکمه «تغییر مسئول» به عضو دیگر منتقل کنید.');
            }
        }
        if ($resource === 'panel_users') {
            $this->assertPanelUserDeletable($id);
        }
        if ($resource === 'teams') {
            $this->deleteTeamCascade($id);

            return;
        }
        if ($resource === 'team_contracts') {
            $record = $this->find($resource, $id);
            $teamId = (int) ($record['team_id'] ?? 0);
            $fiscalYear = (string) ($record['fiscal_year'] ?? '');
            $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $definition['table']))->execute(['id' => $id]);
            if ($teamId > 0) {
                (new TeamContracts($this->pdo))->syncTeamContractCache($teamId);
                $this->syncChargesForTeam($teamId, ['fiscal_year' => $fiscalYear]);
            }

            return;
        }
        if ($resource === 'desks') {
            throw new InvalidArgumentException('حذف میز مجاز نیست.');
        }
        if ($resource === 'transactions') {
            $row = $this->find($resource, $id);
            if (CenterLedger::isSystemSource($row['source_file'] ?? null)) {
                throw new InvalidArgumentException('این ردیف سیستمی منسوخ است و قابل حذف نیست.');
            }
        }
        if ($resource === 'desk_assignments') {
            $record = $this->find($resource, $id);
            $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $definition['table']))->execute(['id' => $id]);
            (new DeskAssignments($this->pdo))->handleAssignmentDeleted($record);
            $this->syncChargesForTeam((int) ($record['team_id'] ?? 0), $record);

            return;
        }
        $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $definition['table']))->execute(['id' => $id]);
    }

    private function deleteTeamCascade(int $teamId): void
    {
        if ($teamId <= 0) {
            throw new InvalidArgumentException('نهاد معتبر نیست.');
        }

        $pendingPayments = $this->countPendingTeamPayment($teamId);
        if ($pendingPayments > 0) {
            throw new InvalidArgumentException('ابتدا اعلام واریزهای در انتظار تأیید این نهاد را تأیید یا رد کنید.');
        }

        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $this->pdo->prepare('UPDATE desks SET team_id = NULL WHERE team_id = :id')->execute(['id' => $teamId]);
            $this->pdo->prepare(
                "UPDATE lockers SET team_id = NULL, member_id = NULL, status = 'خالی' WHERE team_id = :id"
            )->execute(['id' => $teamId]);
            $this->pdo->prepare('UPDATE members SET locker_id = NULL WHERE team_id = :id')->execute(['id' => $teamId]);

            foreach ([
                'DELETE FROM locker_requests WHERE team_id = :id',
                'DELETE FROM member_requests WHERE team_id = :id',
                'DELETE FROM desk_assignments WHERE team_id = :id',
                'DELETE FROM charges WHERE team_id = :id',
                'DELETE FROM transactions WHERE team_id = :id',
                'DELETE FROM members WHERE team_id = :id',
                'DELETE FROM team_contracts WHERE team_id = :id',
                'DELETE FROM sms_logs WHERE team_id = :id',
            ] as $sql) {
                try {
                    $this->pdo->prepare($sql)->execute(['id' => $teamId]);
                } catch (PDOException) {
                    // Table may be absent on older installs mid-migrate.
                }
            }

            EntityAccounts::deleteForTeam($this->pdo, $teamId);
            $this->pdo->prepare('DELETE FROM teams WHERE id = :id')->execute(['id' => $teamId]);

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateStatus(string $resource, int $id, string $status): array
    {
        if (in_array($resource, ['locker_requests', 'locker-requests'], true)) {
            throw new InvalidArgumentException('وضعیت درخواست کمد فقط از طریق تأیید/رد مدیر تغییر می‌کند.');
        }
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
        $contracts = new TeamContracts($this->pdo);
        $fiscalYear = $contracts->currentFiscalYear();
        $dates = [];
        $fromMonth = (int) ($payload['assignment_from_month'] ?? 0);
        $untilMonth = (int) ($payload['assignment_until_month'] ?? 0);
        if ($fromMonth >= 1 && $fromMonth <= 12) {
            $dates['assignment_from'] = JalaliDate::monthStart($fiscalYear, $fromMonth);
        } elseif (array_key_exists('assignment_from', $payload)) {
            $dates['assignment_from'] = $payload['assignment_from'];
        }
        if ($untilMonth >= 1 && $untilMonth <= 12) {
            $dates['assignment_until'] = JalaliDate::monthEnd($fiscalYear, $untilMonth);
        } elseif (array_key_exists('assignment_until', $payload)) {
            $dates['assignment_until'] = $payload['assignment_until'];
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
        unset($data['assignment_from'], $data['assignment_until'], $data['assignment_from_month'], $data['assignment_until_month']);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichDeskAssignment(array $row): array
    {
        $deskId = (int) ($row['id'] ?? 0);
        $assignment = (new DeskAssignments($this->pdo))->assignmentForDeskForm($deskId, null);
        if ($assignment !== null) {
            $row['assignment_from'] = $assignment['assigned_from'] ?? '';
            $row['assignment_until'] = $assignment['assigned_until'] ?? '';
        } else {
            $row['assignment_from'] = '';
            $row['assignment_until'] = '';
        }
        $fromMonth = JalaliDate::monthIndexFromDate($row['assignment_from']);
        $untilMonth = JalaliDate::monthIndexFromDate($row['assignment_until']);
        $row['assignment_from_month'] = $fromMonth > 0 ? (string) $fromMonth : '';
        $row['assignment_until_month'] = $untilMonth > 0 ? (string) $untilMonth : '';
        $row['assignment_period'] = JalaliDate::monthRangeLabel($row['assignment_from'], $row['assignment_until']);

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
            if (isset($data['is_active'])) {
                $data['is_active'] = (int) $data['is_active'];
            } elseif ($creating) {
                $data['is_active'] = 1;
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
        if ($resource === 'members' && !$creating) {
            if (!empty($data['access_code'])) {
                $data['wants_access'] = 1;
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
        if ($resource === 'member_requests' && $creating) {
            $teamId = Access::scopedTeamId();
            if ($teamId === null) {
                throw new InvalidArgumentException('حساب نهاد معتبر نیست.');
            }
            $data['team_id'] = $teamId;
            $data['status'] = 'pending';
            $data['submitted_at'] = JalaliDate::todayParts()['formatted'];
            $memberId = (int) ($data['member_id'] ?? 0);
            $type = (string) ($data['request_type'] ?? '');
            if ($memberId <= 0) {
                throw new InvalidArgumentException('عضو را انتخاب کنید.');
            }
            if (!in_array($type, ['update', 'delete'], true)) {
                throw new InvalidArgumentException('نوع درخواست معتبر نیست.');
            }
            $member = $this->pdo->prepare(
                "SELECT id FROM members WHERE id = :id AND team_id = :team_id AND approval_status = 'approved'"
            );
            $member->execute(['id' => $memberId, 'team_id' => $teamId]);
            if ($member->fetchColumn() === false) {
                throw new InvalidArgumentException('فقط اعضای تأیید‌شده قابل درخواست تغییر هستند.');
            }
            $pending = $this->pdo->prepare(
                "SELECT COUNT(*) FROM member_requests WHERE member_id = :member_id AND status = 'pending'"
            );
            $pending->execute(['member_id' => $memberId]);
            if ((int) $pending->fetchColumn() > 0) {
                throw new InvalidArgumentException('برای این عضو درخواست دیگری در انتظار تأیید است.');
            }
            if ($type === 'delete') {
                $data['full_name'] = null;
                $data['phone'] = null;
                $data['national_id'] = null;
                $data['wants_access'] = null;
            } else {
                if ($this->blank($data['full_name'] ?? null) || $this->blank($data['phone'] ?? null) || $this->blank($data['national_id'] ?? null)) {
                    throw new InvalidArgumentException('نام، موبایل و کد ملی برای ویرایش الزامی است.');
                }
                $data['wants_access'] = (int) ($data['wants_access'] ?? 0);
            }
        }
        if ($resource === 'teams' && $creating) {
            // قرارداد سالانه جداگانه در بخش «قراردادهای نهاد» ثبت می‌شود.
        }
        if ($resource === 'team_contracts') {
            if (isset($data['fiscal_year'])) {
                $data['fiscal_year'] = JalaliDate::normalizeDigits($data['fiscal_year']);
            }
            if (array_key_exists('formal_contract_amount', $data)) {
                if ($this->blank($data['formal_contract_amount'])) {
                    throw new InvalidArgumentException('مبلغ کل قرارداد رسمی الزامی است.');
                }
                $amount = (int) $data['formal_contract_amount'];
                if ($amount < 0) {
                    throw new InvalidArgumentException('مبلغ قرارداد رسمی نمی‌تواند منفی باشد.');
                }
                $data['formal_contract_amount'] = $amount;
            } elseif ($creating) {
                throw new InvalidArgumentException('مبلغ کل قرارداد رسمی الزامی است.');
            }
            foreach (['charge_rate_override', 'informal_rent_rate_override'] as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = $this->blank($data[$field]) ? null : (int) $data[$field];
                }
            }
            if ($creating) {
                $data['created_at'] = JalaliDate::todayParts()['formatted'];
            }
            $teamId = (int) ($data['team_id'] ?? 0);
            $year = (string) ($data['fiscal_year'] ?? '');
            if ($teamId > 0 && $year !== '') {
                $check = $this->pdo->prepare(
                    'SELECT id FROM team_contracts WHERE team_id = :team_id AND fiscal_year = :year'
                    . ($creating ? '' : ' AND id <> :id')
                );
                $params = ['team_id' => $teamId, 'year' => $year];
                if (!$creating && $recordId > 0) {
                    $params['id'] = $recordId;
                }
                $check->execute($params);
                if ($check->fetchColumn() !== false) {
                    throw new InvalidArgumentException('برای این نهاد در این سال مالی قبلاً قرارداد ثبت شده است.');
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
            $existingCategory = '';
            if (!$creating && $recordId > 0) {
                $statement = $this->pdo->prepare('SELECT category FROM transactions WHERE id = :id');
                $statement->execute(['id' => $recordId]);
                $existingCategory = (string) ($statement->fetchColumn() ?: '');
            }
            $category = (string) ($data['category'] ?? $existingCategory);
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
                if ($creating) {
                    $plan = $this->normalizeTeamPaymentPlan($teamId, $data['payment_plan'] ?? null);
                    $data['payment_plan'] = json_encode($plan, JSON_UNESCAPED_UNICODE);
                    $data['amount'] = array_sum(array_column($plan, 'amount'));
                    $data['fiscal_year'] = $plan[0]['fiscal_year'];
                    $data['month_index'] = $plan[0]['month_index'];
                    $monthNames = self::monthOptions();
                    $labels = array_map(
                        static fn (array $item): string => ($monthNames[(string) $item['month_index']] ?? '') . ' ' . $item['fiscal_year'],
                        $plan
                    );
                    $data['description'] = 'اعلام واریز — ' . implode('، ', $labels);
                    $dup = $this->countPendingTeamPayment($teamId);
                    if ($dup > 0) {
                        throw new InvalidArgumentException('اعلام واریز دیگری در انتظار تأیید است. ابتدا آن را پیگیری یا حذف کنید.');
                    }
                }
            } elseif ($category !== 'واریز تیم') {
                $data['team_id'] = null;
                $data['fiscal_year'] = null;
                $data['month_index'] = null;
                $data['payment_status'] = 'approved';
                $data['confirmed'] = 1;
                $data['payment_plan'] = null;
                if (in_array($category, ['درآمد', 'هزینه'], true)) {
                    $subtype = trim((string) ($data['finance_subtype'] ?? ''));
                    $allowed = $category === 'درآمد' ? self::incomeSubtypeOptions() : self::expenseSubtypeOptions();
                    if ($subtype === '' || !isset($allowed[$subtype])) {
                        throw new InvalidArgumentException('نوع ' . ($category === 'درآمد' ? 'درآمد' : 'هزینه') . ' را انتخاب کنید.');
                    }
                    $detail = trim((string) ($data['description'] ?? ''));
                    if ($detail === '') {
                        $data['description'] = $subtype;
                    }
                } else {
                    $data['finance_subtype'] = null;
                }
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
            if (!isset($data['amount']) && ($charge > 0 || $rent > 0)) {
                $data['amount'] = $charge + $rent;
            } elseif (isset($data['amount']) && $data['amount'] === '' && ($charge > 0 || $rent > 0)) {
                $data['amount'] = $charge + $rent;
            }
        }
        if ($resource === 'rate_settings' && isset($data['fiscal_year'])) {
            $data['fiscal_year'] = JalaliDate::normalizeDigits($data['fiscal_year']);
        }
        if ($resource === 'desk_assignments') {
            $deskId = (int) ($data['desk_id'] ?? 0);
            if ($deskId <= 0 && $recordId > 0) {
                $existing = $this->find($resource, $recordId);
                $deskId = (int) ($existing['desk_id'] ?? 0);
                if ($deskId > 0 && !isset($data['desk_id'])) {
                    $data['desk_id'] = $deskId;
                }
            }
            if ($deskId <= 0) {
                throw new InvalidArgumentException('میز را انتخاب کنید.');
            }
            $deskStatement = $this->pdo->prepare('SELECT number FROM desks WHERE id = :id');
            $deskStatement->execute(['id' => $deskId]);
            $deskNumber = $deskStatement->fetchColumn();
            if ($deskNumber === false) {
                throw new InvalidArgumentException('میز یافت نشد.');
            }
            $data['desk_number'] = (int) $deskNumber;

            $fiscalYear = JalaliDate::normalizeDigits((string) ($data['fiscal_year'] ?? ''));
            $existing = null;
            if ($recordId > 0) {
                $existing = $this->find($resource, $recordId);
            }
            if ($fiscalYear === '' && $existing !== null) {
                $fiscalYear = JalaliDate::fiscalYearFromDate((string) ($existing['assigned_from'] ?? ''));
            }
            if ($fiscalYear === '') {
                throw new InvalidArgumentException('سال مالی الزامی است.');
            }

            $fromMonth = (int) ($data['assigned_from_month'] ?? 0);
            $untilMonth = (int) ($data['assigned_until_month'] ?? 0);
            $hasFromMonth = array_key_exists('assigned_from_month', $data);
            $hasUntilMonth = array_key_exists('assigned_until_month', $data);
            if ($fromMonth < 1 && $existing !== null && !$hasFromMonth) {
                $fromMonth = JalaliDate::monthIndexFromDate((string) ($existing['assigned_from'] ?? ''));
            }
            if ($untilMonth < 1 && $existing !== null && !$hasUntilMonth) {
                $untilMonth = JalaliDate::monthIndexFromDate((string) ($existing['assigned_until'] ?? ''));
            }
            if ($fromMonth < 1 || $fromMonth > 12) {
                throw new InvalidArgumentException('ماه شروع تخصیص معتبر نیست.');
            }
            if ($untilMonth < 1 || $untilMonth > 12) {
                throw new InvalidArgumentException('ماه پایان تخصیص معتبر نیست.');
            }
            if ($untilMonth < $fromMonth) {
                throw new InvalidArgumentException('ماه پایان نمی‌تواند قبل از ماه شروع باشد.');
            }
            $data['assigned_from'] = JalaliDate::monthStart($fiscalYear, $fromMonth);
            $data['assigned_until'] = JalaliDate::monthEnd($fiscalYear, $untilMonth);
            unset($data['assigned_from_month'], $data['assigned_until_month'], $data['fiscal_year']);

            $teamId = (int) ($data['team_id'] ?? 0);
            if ($teamId <= 0 && $existing !== null) {
                $teamId = (int) ($existing['team_id'] ?? 0);
            }
            if ($teamId > 0) {
                (new TeamContracts($this->pdo))->assertCanAssignDeskForYear($teamId, $fiscalYear);
            }
            foreach (['charge_exempt', 'rent_exempt'] as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = (int) (!empty($data[$field]) && (string) $data[$field] !== '0');
                }
            }
            if (!Schema::deskAssignmentExemptWritable($this->pdo)) {
                unset($data['charge_exempt'], $data['rent_exempt']);
            }
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
            } elseif ($plainPassword !== '') {
                $data['password_hash'] = UserAccounts::hashPassword($plainPassword);
            }
            unset($data['password_plain']);
            if (!isset($data['is_active'])) {
                $data['is_active'] = 1;
            }
        }
        if ($resource === 'development_plans') {
            $today = JalaliDate::todayParts()['formatted'];
            if ($creating) {
                $data['created_at'] = $today;
                $data['status'] = $data['status'] ?? 'open';
                $data['category'] = $data['category'] ?? 'action';
                $data['priority'] = $data['priority'] ?? 'medium';
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
            if (isset($data['usage_type']) && !in_array((string) $data['usage_type'], ['formal', 'informal', 'mixed'], true)) {
                $data['usage_type'] = 'formal';
            }
            $contracts = new TeamContracts($this->pdo);
            $existingDesk = $recordId > 0 ? $this->find('desks', $recordId) : [];
            $oldTeamId = (int) ($existingDesk['team_id'] ?? 0);
            $newTeamId = array_key_exists('team_id', $data)
                ? (int) ($data['team_id'] ?? 0)
                : $oldTeamId;
            if ($newTeamId > 0 && $newTeamId !== $oldTeamId) {
                $contracts->assertCanAssignDesk($newTeamId);
            }
            if ($newTeamId <= 0 && $oldTeamId > 0) {
                $contracts->assertCanFreeDesk($oldTeamId);
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
        foreach ($this->pdo->query('SELECT id, entity_type, name, leader, is_active FROM teams ORDER BY is_active DESC, entity_type, name')->fetchAll() as $team) {
            $type = $labels[$team['entity_type'] ?? 'team'] ?? 'نهاد';
            $label = $type . ' — ' . ($team['name'] ?? '');
            if (($team['leader'] ?? '') !== '') {
                $label .= ' (' . $team['leader'] . ')';
            }
            if ((int) ($team['is_active'] ?? 1) === 0) {
                $label .= ' — غیرفعال';
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
    private function approvedMemberOptionsForTeam(?int $teamId): array
    {
        if ($teamId === null) {
            return [];
        }
        $options = [];
        $statement = $this->pdo->prepare(
            "SELECT id, full_name, member_code FROM members
             WHERE team_id = :team_id AND approval_status = 'approved'
             ORDER BY full_name"
        );
        $statement->execute(['team_id' => $teamId]);
        foreach ($statement->fetchAll() as $member) {
            $label = (string) ($member['full_name'] ?? '');
            if (($member['member_code'] ?? '') !== '') {
                $label .= ' (' . $member['member_code'] . ')';
            }
            $options[(string) $member['id']] = $label;
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
     * @return array<string, string>
     */
    private static function monthOptionsRequired(): array
    {
        $options = [];
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

    private function assertTeamCanMutate(string $resource, int $id, string $action): void
    {
        $teamId = Access::scopedTeamId();
        if ($teamId === null) {
            throw new InvalidArgumentException('حساب نهاد معتبر نیست.');
        }

        if ($resource === 'transactions') {
            $row = $this->find($resource, $id);
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                throw new InvalidArgumentException('دسترسی به این رکورد مجاز نیست.');
            }
            if (($row['payment_status'] ?? '') !== 'pending') {
                throw new InvalidArgumentException('فقط اعلام‌های در انتظار تأیید قابل تغییر هستند.');
            }

            return;
        }

        if ($resource === 'locker_requests') {
            $row = $this->find($resource, $id);
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                throw new InvalidArgumentException('دسترسی به این رکورد مجاز نیست.');
            }
            if (($row['status'] ?? '') !== 'pending') {
                throw new InvalidArgumentException('فقط درخواست‌های در انتظار قابل تغییر هستند.');
            }

            return;
        }

        if ($resource === 'member_requests') {
            $row = $this->find($resource, $id);
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                throw new InvalidArgumentException('دسترسی به این رکورد مجاز نیست.');
            }
            if (($row['status'] ?? '') !== 'pending') {
                throw new InvalidArgumentException('فقط درخواست‌های در انتظار قابل تغییر هستند.');
            }

            return;
        }

        if ($resource === 'members' && $action === 'delete') {
            $row = $this->find($resource, $id);
            if ((int) ($row['team_id'] ?? 0) !== $teamId) {
                throw new InvalidArgumentException('دسترسی به این رکورد مجاز نیست.');
            }
            if (($row['approval_status'] ?? '') !== 'pending') {
                throw new InvalidArgumentException('فقط اعضای در انتظار تأیید قابل حذف هستند.');
            }

            return;
        }

        throw new InvalidArgumentException('نهاد نمی‌تواند این رکورد را تغییر دهد.');
    }

    private function countPendingTeamPayment(int $teamId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM transactions
             WHERE team_id = :team_id AND category = 'واریز تیم' AND payment_status = 'pending'"
        );
        $statement->execute(['team_id' => $teamId]);

        return (int) $statement->fetchColumn();
    }

    private function beginImmediateTransaction(): void
    {
        // Keep PDO transaction state in sync so commit()/rollBack() work on all drivers.
        $this->pdo->beginTransaction();
    }

    private function lockTeamRow(int $teamId): void
    {
        if ($teamId <= 0) {
            return;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $statement = $this->pdo->prepare('SELECT id FROM teams WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $teamId]);
            $statement->fetchColumn();

            return;
        }

        // SQLite serializes writers; touch the team row inside the open transaction.
        $statement = $this->pdo->prepare('SELECT id FROM teams WHERE id = :id');
        $statement->execute(['id' => $teamId]);
        $statement->fetchColumn();
    }

    private function findChargeId(int $teamId, string $fiscalYear, int $monthIndex): ?int
    {
        if ($teamId <= 0 || $fiscalYear === '' || $monthIndex <= 0) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT id FROM charges WHERE team_id = :team_id AND fiscal_year = :year AND month_index = :month LIMIT 1'
        );
        $statement->execute([
            'team_id' => $teamId,
            'year' => JalaliDate::normalizeDigits($fiscalYear),
            'month' => $monthIndex,
        ]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return list<array{fiscal_year:string,month_index:int,amount:int}>
     */
    private function normalizeTeamPaymentPlan(int $teamId, mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw) || $raw === []) {
            throw new InvalidArgumentException('حداقل یک ماه بدهی را برای واریز انتخاب کنید.');
        }

        $payable = [];
        foreach ((new Repository($this->pdo))->teamPayableMonths($teamId) as $row) {
            $key = ($row['fiscal_year'] ?? '') . '-' . ($row['month_index'] ?? '');
            $payable[$key] = (int) ($row['amount_remaining'] ?? 0);
        }

        $plan = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fy = JalaliDate::normalizeDigits((string) ($item['fiscal_year'] ?? ''));
            $mi = (int) ($item['month_index'] ?? 0);
            $key = $fy . '-' . $mi;
            if ($fy === '' || $mi <= 0 || isset($seen[$key])) {
                continue;
            }
            $remaining = (int) ($payable[$key] ?? 0);
            if ($remaining <= 0) {
                throw new InvalidArgumentException('یکی از ماه‌های انتخاب‌شده بدهی ندارد یا قبلاً پرداخت شده است.');
            }
            $plan[] = ['fiscal_year' => $fy, 'month_index' => $mi, 'amount' => $remaining];
            $seen[$key] = true;
        }

        if ($plan === []) {
            throw new InvalidArgumentException('ماه‌های انتخاب‌شده معتبر نیستند.');
        }

        usort($plan, static fn (array $a, array $b): int => [$a['fiscal_year'], $a['month_index']] <=> [$b['fiscal_year'], $b['month_index']]);

        return $plan;
    }

    /**
     * @return array<string, string>
     */
    public static function incomeSubtypeOptions(): array
    {
        return [
            'دوره آموزشی' => 'دوره آموزشی / کارگاه',
            'جریمه نهاد' => 'جریمه نهاد',
            'اسپانسری' => 'اسپانسری / حمایت مالی',
            'اجاره فضا' => 'اجاره سالن / فضا',
            'خدمات' => 'فروش خدمات',
            'سایر' => 'سایر درآمد',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function expenseSubtypeOptions(): array
    {
        return [
            'لوازم مصرفی' => 'لوازم مصرفی',
            'خوراکی' => 'خوراکی و پذیرایی',
            'تعمیرات' => 'تعمیرات و نگهداری',
            'حقوق' => 'حقوق و دستمزد',
            'خدمات' => 'خدمات پیمانکاری',
            'آب و برق' => 'آب، برق، اینترنت',
            'سایر' => 'سایر هزینه',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function syncChargesForTeam(int $teamId, array $context = []): void
    {
        if ($teamId <= 0) {
            return;
        }

        $contracts = new TeamContracts($this->pdo);
        $years = [$contracts->currentFiscalYear()];
        foreach (['fiscal_year', 'assigned_from', 'assigned_until', 'assignment_from', 'assignment_until'] as $field) {
            $value = JalaliDate::tryNormalize((string) ($context[$field] ?? ''));
            if ($value !== '') {
                $years[] = substr($value, 0, 4);
            }
        }

        $seeder = new Seeder($this->pdo);
        foreach (array_unique(array_filter($years)) as $year) {
            $seeder->recalculateChargesForTeam($teamId, $year);
        }
    }
}
