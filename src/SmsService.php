<?php

declare(strict_types=1);

final class SmsService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(bool $withLive = false): array
    {
        $settings = (new CenterSettings($this->pdo))->smsSettings();
        $configured = $this->isApiConfigured();

        $result = array_merge($settings, [
            'sms_configured' => $configured,
            'sms_credit' => isset($settings['sms_panel_credit']) ? (int) $settings['sms_panel_credit'] : null,
            'sms_base_price' => (int) ($settings['sms_unit_cost'] ?? 0),
        ]);

        if ($withLive && $this->hasApiCredentials()) {
            try {
                $live = $this->refreshPricing();
                $result['sms_credit'] = $live['credit'];
                if ($live['base_price'] !== null) {
                    $result['sms_base_price'] = (int) $live['base_price'];
                    $result['sms_unit_cost'] = (int) $live['base_price'];
                }
                $result['sms_live_synced_at'] = JalaliDate::todayParts()['formatted'];
            } catch (InvalidArgumentException $exception) {
                $result['live_error'] = $exception->getMessage();
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function isApiConfigured(?array $settings = null): bool
    {
        $send = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $fromNumber = $settings !== null
            ? trim((string) ($settings['sms_from_number'] ?? $send['sms_from_number'] ?? ''))
            : trim((string) ($send['sms_from_number'] ?? ''));

        return $this->hasApiCredentials($send) && $fromNumber !== '';
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function hasApiCredentials(?array $settings = null): bool
    {
        if ($settings === null) {
            $settings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        }

        if (trim((string) ($settings['sms_username'] ?? '')) === '') {
            return false;
        }
        if (trim((string) ($settings['sms_password'] ?? '')) !== '') {
            return true;
        }

        return (bool) ($settings['sms_password_set'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        Access::requireWriteJson();
        $send = (new CenterSettings($this->pdo))->smsSettingsForSend();
        if (!$this->hasApiCredentials($send)) {
            throw new InvalidArgumentException('نام کاربری و رمز API را ابتدا ذخیره کنید.');
        }

        $client = new MelliPayamak();
        $username = (string) $send['sms_username'];
        $password = (string) $send['sms_password'];
        $checks = [];

        $creditResult = $client->getCredit($username, $password);
        $checks['credit'] = [
            'ok' => $creditResult['ok'],
            'value' => $creditResult['credit'],
            'error' => $creditResult['error'],
        ];

        $priceResult = $client->getBasePrice($username, $password);
        $checks['base_price'] = [
            'ok' => $priceResult['ok'],
            'value' => $priceResult['price'],
            'error' => $priceResult['error'],
        ];

        $ok = ($checks['credit']['ok'] ?? false) || ($checks['base_price']['ok'] ?? false);

        return [
            'ok' => $ok,
            'checks' => $checks,
            'message' => $ok
                ? 'اتصال به API ملی‌پیامک برقرار است.'
                : 'اتصال به API ناموفق بود. نام کاربری، رمز API و دسترسی REST را بررسی کنید.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSettings(array $payload): array
    {
        $section = trim((string) ($payload['section'] ?? ''));
        $center = new CenterSettings($this->pdo);
        $current = $center->smsSettings();

        $updatePayload = match ($section) {
            'credentials' => [
                'sms_username' => $payload['sms_username'] ?? '',
                'sms_password' => $payload['sms_password'] ?? '',
            ],
            'line' => [
                'sms_from_number' => $payload['sms_from_number'] ?? '',
                'sms_daily_limit' => $payload['sms_daily_limit'] ?? $current['sms_daily_limit'] ?? 500,
            ],
            'charge_template' => [
                'sms_charge_template' => $payload['sms_charge_template'] ?? '',
            ],
            'workflow_templates' => [
                'sms_workflow_templates' => $payload['sms_workflow_templates'] ?? [],
            ],
            default => $payload,
        };

        if ($section === 'line' && trim((string) ($updatePayload['sms_from_number'] ?? '')) === '') {
            throw new InvalidArgumentException('شماره خط ارسال الزامی است.');
        }

        $settings = $center->updateSms($updatePayload);

        $send = $center->smsSettingsForSend();

        if ($this->hasApiCredentials($send)) {
            try {
                $this->refreshPricing($send);
            } catch (Throwable) {
                // تعرفه و موجودی بعداً قابل بروزرسانی است.
            }
        }

        return $this->settings(withLive: $this->hasApiCredentials($send));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function stats(bool $withLive = false): array
    {
        $today = JalaliDate::todayParts()['formatted'];
        $settings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $dailyLimit = (int) ($settings['sms_daily_limit'] ?? 500);
        $sentToday = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM sms_logs WHERE status = 'sent' AND sent_at LIKE " . $this->pdo->quote($today . '%')
        )->fetchColumn();
        $failedToday = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM sms_logs WHERE status = 'failed' AND created_at LIKE " . $this->pdo->quote($today . '%')
        )->fetchColumn();
        $costToday = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(cost_rial), 0) FROM sms_logs WHERE status = 'sent' AND sent_at LIKE " . $this->pdo->quote($today . '%')
        )->fetchColumn();
        $totalSent = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'sent'")->fetchColumn();
        $totalCost = (int) $this->pdo->query("SELECT COALESCE(SUM(cost_rial), 0) FROM sms_logs WHERE status = 'sent'")->fetchColumn();

        $credit = null;
        $basePrice = (int) ($settings['sms_unit_cost'] ?? 0);
        if ($withLive && $this->hasApiCredentials()) {
            try {
                $live = $this->refreshPricing();
                $credit = $live['credit'];
                if ($live['base_price'] !== null) {
                    $basePrice = (int) $live['base_price'];
                }
            } catch (Throwable) {
                // آمار محلی همچنان قابل نمایش است.
            }
        }

        return [
            'daily_limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'remaining_today' => max(0, $dailyLimit - $sentToday),
            'cost_today' => $costToday,
            'total_sent' => $totalSent,
            'total_cost' => $totalCost,
            'panel_credit' => $credit,
            'unit_cost' => $basePrice,
            'sms_configured' => $this->isApiConfigured($settings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshPricing(?array $send = null): array
    {
        $send ??= (new CenterSettings($this->pdo))->smsSettingsForSend();
        if ($send['sms_username'] === '' || $send['sms_password'] === '') {
            throw new InvalidArgumentException('نام کاربری و رمز API را ابتدا ذخیره کنید.');
        }

        $client = new MelliPayamak();
        $credit = null;
        $price = null;
        $errors = [];
        $creditResult = $client->getCredit((string) $send['sms_username'], (string) $send['sms_password']);
        if ($creditResult['ok']) {
            $credit = $creditResult['credit'];
        } elseif (trim((string) ($creditResult['error'] ?? '')) !== '') {
            $errors[] = (string) $creditResult['error'];
        }
        $priceResult = $client->getBasePrice((string) $send['sms_username'], (string) $send['sms_password']);
        if ($priceResult['ok'] && $priceResult['price'] !== null) {
            $price = (int) $priceResult['price'];
            (new CenterSettings($this->pdo))->updateSmsUnitCost($price);
        } elseif (trim((string) ($priceResult['error'] ?? '')) !== '') {
            $errors[] = (string) $priceResult['error'];
        }
        if ($credit !== null || $price !== null) {
            (new CenterSettings($this->pdo))->storeSmsLiveStats($credit, $price);
        }
        if ($credit === null && $price === null && $errors !== []) {
            throw new InvalidArgumentException($errors[0]);
        }

        return ['credit' => $credit, 'base_price' => $price];
    }

    /**
     * @param array<string, string> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function recipients(int $page, int $perPage, array $filters): array
    {
        return (new Repository($this->pdo))->paginatedResource('sms-recipients', $page, $perPage, $filters);
    }

    /**
     * @param list<int> $memberIds
     * @return array<string, mixed>
     */
    public function sendAnnouncement(string $message, array $memberIds): array
    {
        Access::requireWriteJson();
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('متن پیامک الزامی است.');
        }
        if ($memberIds === []) {
            throw new InvalidArgumentException('حداقل یک گیرنده انتخاب کنید.');
        }

        $batchUid = $this->newBatchUid();
        $recipients = $this->loadMembersByIds($memberIds);
        $this->assertDailyCapacity(count($recipients));
        $center = (new CenterSettings($this->pdo))->get();
        $bankInfo = self::formatBankInfo($center);

        return $this->dispatchBatch(
            $batchUid,
            'announcement',
            $recipients,
            fn (array $member): string => self::renderTemplate($message, [
                'team_name' => (string) ($member['team_label'] ?? ''),
                'leader_name' => (string) ($member['full_name'] ?? ''),
                'full_name' => (string) ($member['full_name'] ?? ''),
                'bank_info' => $bankInfo,
                'card_number' => (string) ($center['card_number'] ?? ''),
                'account_number' => (string) ($center['account_number'] ?? ''),
                'sheba' => (string) ($center['sheba'] ?? ''),
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function patternRegistry(): array
    {
        $rows = [];
        if (Schema::tableExists($this->pdo, 'sms_patterns')) {
            $statement = $this->pdo->query(
                'SELECT pattern_key, body_id, title, panel_text, variables_json, system_template, workflow_key
                 FROM sms_patterns ORDER BY body_id'
            );
            $dbRows = $statement->fetchAll() ?: [];
            foreach ($dbRows as $row) {
                $variables = json_decode((string) ($row['variables_json'] ?? ''), true);
                $rows[] = [
                    'pattern_key' => (string) ($row['pattern_key'] ?? ''),
                    'workflow_key' => $row['workflow_key'] !== null ? (string) $row['workflow_key'] : null,
                    'body_id' => (int) ($row['body_id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'panel_text' => (string) ($row['panel_text'] ?? ''),
                    'variables' => is_array($variables) ? $variables : [],
                    'system_template' => (string) ($row['system_template'] ?? ''),
                ];
            }
        }
        if ($rows === []) {
            $rows = SmsPatterns::panelRegistrationGuide();
        } else {
            foreach ($rows as &$row) {
                $variables = is_array($row['variables'] ?? null) ? $row['variables'] : [];
                $row['panel_preview'] = SmsPatterns::renderPanelText(
                    (string) ($row['panel_text'] ?? ''),
                    $variables
                );
                $row['panel_valid'] = !SmsPatterns::panelTextEndsWithVariable((string) ($row['panel_text'] ?? ''));
            }
            unset($row);
        }

        $settings = (new CenterSettings($this->pdo))->smsSettings();

        return [
            'patterns' => $rows,
            'charge_template' => (string) ($settings['sms_charge_template'] ?? ''),
            'workflow_templates' => (array) ($settings['sms_workflow_templates'] ?? []),
            'registration_notes' => [
                'نوع خط: خط خدماتی اشتراکی (shared)',
                'متغیرها در پنل به‌صورت {0}، {1}، ... و به ترتیب قرار گیرند.',
                'متغیر نباید در انتهای متن باشد؛ پس از آخرین متغیر عبارت ثابت قرار دهید.',
                'پس از تأیید الگو در پنل، body_id واقعی را در تنظیمات پیامک جایگزین کنید.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chargeDebtors(): array
    {
        $center = new CenterSettings($this->pdo);
        $template = trim((string) ($center->smsSettings()['sms_charge_template'] ?? ''));
        $bank = $center->get();
        $debtors = [];

        foreach ((new Repository($this->pdo))->debtorTeamsForSms() as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId <= 0) {
                continue;
            }
            $leader = $this->leaderContactForTeam($teamId);
            $phone = TeamLeaders::normalizePhone((string) ($leader['phone'] ?? ''));
            $entry = [
                'team_id' => $teamId,
                'team_name' => (string) ($row['team_name'] ?? ''),
                'debt_total' => (int) ($row['debt_total'] ?? 0),
                'debt_summary' => (string) ($row['debt_summary'] ?? ''),
                'leader_name' => (string) ($leader['name'] ?? ''),
                'phone' => $phone,
                'phone_valid' => $phone !== '' && preg_match('/^09\d{9}$/', $phone) === 1,
            ];
            $debtTotal = (int) ($entry['debt_total'] ?? 0);
            $entry['preview_message'] = $template !== ''
                ? self::renderChargeTemplate($template, $entry, $bank)
                : '';
            $entry['preview_human'] = SmsPatterns::renderPanelPreview('charge_reminder', [
                'team_name' => (string) ($entry['team_name'] ?? ''),
                'debt_total' => number_format($debtTotal),
                'debt_summary' => (string) ($entry['debt_summary'] ?? ''),
            ]);
            $debtors[] = $entry;
        }

        return [
            'debtors' => $debtors,
            'template' => $template,
            'template_configured' => $template !== '',
            'total_debt' => array_sum(array_map(static fn (array $row): int => (int) ($row['debt_total'] ?? 0), $debtors)),
        ];
    }

    /**
     * @param list<int> $teamIds
     * @return array<string, mixed>
     */
    public function sendChargeReminders(array $teamIds): array
    {
        Access::requireWriteJson();
        $center = new CenterSettings($this->pdo);
        $template = trim((string) ($center->smsSettings()['sms_charge_template'] ?? ''));
        if ($template === '') {
            throw new InvalidArgumentException('الگوی یادآوری شارژ را در تنظیمات پیامک ذخیره کنید.');
        }
        if ($teamIds === []) {
            throw new InvalidArgumentException('حداقل یک نهاد بدهکار انتخاب کنید.');
        }

        $bank = $center->get();
        $selected = array_fill_keys(array_values(array_unique(array_filter(array_map('intval', $teamIds), static fn (int $id): bool => $id > 0))), true);
        $recipients = [];

        foreach ((new Repository($this->pdo))->debtorTeamsForSms() as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId <= 0 || !isset($selected[$teamId])) {
                continue;
            }
            $leader = $this->leaderContactForTeam($teamId);
            $phone = TeamLeaders::normalizePhone((string) ($leader['phone'] ?? ''));
            $debtor = [
                'team_id' => $teamId,
                'team_name' => (string) ($row['team_name'] ?? ''),
                'debt_total' => (int) ($row['debt_total'] ?? 0),
                'debt_summary' => (string) ($row['debt_summary'] ?? ''),
                'leader_name' => (string) ($leader['name'] ?? ''),
                'phone' => $phone,
            ];
            $recipients[] = [
                'id' => 0,
                'team_id' => $teamId,
                'team_label' => $debtor['team_name'],
                'full_name' => $debtor['leader_name'],
                'phone' => $phone,
                'is_leader' => 1,
                'debtor' => $debtor,
            ];
        }

        if ($recipients === []) {
            throw new InvalidArgumentException('نهاد بدهکار معتبری برای ارسال یافت نشد.');
        }

        $this->assertDailyCapacity(count($recipients));

        return $this->dispatchBatch(
            $this->newBatchUid(),
            'charge_reminder',
            $recipients,
            fn (array $recipient): string => self::renderChargeTemplate(
                $template,
                $recipient['debtor'],
                $bank
            )
        );
    }

    /**
     * @param array<string, mixed> $debtor
     * @param array<string, string> $center
     */
    public static function renderChargeTemplate(string $template, array $debtor, array $center): string
    {
        $debtTotal = (int) ($debtor['debt_total'] ?? 0);

        return self::renderTemplate($template, [
            'team_name' => (string) ($debtor['team_name'] ?? ''),
            'leader_name' => (string) ($debtor['leader_name'] ?? ''),
            'debt_total' => (string) $debtTotal,
            'debt_total_formatted' => number_format($debtTotal),
            'debt_summary' => (string) ($debtor['debt_summary'] ?? ''),
            'bank_info' => self::formatBankInfo($center),
            'card_number' => (string) ($center['card_number'] ?? ''),
            'account_number' => (string) ($center['account_number'] ?? ''),
            'sheba' => (string) ($center['sheba'] ?? ''),
        ]);
    }

    /**
     * @param array<string, scalar|null> $vars
     */
    public static function renderTemplate(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public static function optionalSmsValue(string $value, string $fallback = 'ندارد'): string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    public function notifyRoomReservation(array $reservation, string $event): void
    {
        $templateKey = match ($event) {
            'pending' => 'room_pending',
            'approved' => 'room_approved',
            'rejected' => 'room_rejected',
            'cancelled' => 'room_cancelled',
            default => '',
        };
        if ($templateKey === '') {
            return;
        }

        $reason = self::optionalSmsValue((string) ($reservation['rejection_reason'] ?? $reservation['cancel_reason'] ?? ''));
        $this->notifyWorkflow(
            $templateKey,
            TeamLeaders::normalizePhone((string) ($reservation['booker_phone'] ?? '')),
            (string) ($reservation['booker_name'] ?? ''),
            [
                'booker_name' => (string) ($reservation['booker_name'] ?? ''),
                'room_name' => (string) ($reservation['room_name'] ?? ''),
                'reserved_date' => (string) ($reservation['reserved_date'] ?? ''),
                'start_time' => (string) ($reservation['start_time'] ?? ''),
                'end_time' => (string) ($reservation['end_time'] ?? ''),
                'public_token' => (string) ($reservation['public_token'] ?? ''),
                'team_name' => (string) ($reservation['team_name'] ?? $reservation['booker_org'] ?? ''),
                'purpose' => (string) ($reservation['purpose'] ?? ''),
                'rejection_reason' => $reason,
                'cancel_reason' => $reason,
            ],
            'room_' . $event,
            [
                'team_id' => (int) ($reservation['team_id'] ?? 0),
                'team_name' => (string) ($reservation['team_name'] ?? $reservation['booker_org'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string, mixed> $member
     */
    public function notifyMemberStatus(array $member, string $event, string $accessCode = ''): void
    {
        $templateKey = match ($event) {
            'approved' => 'member_approved',
            'rejected' => 'member_rejected',
            default => '',
        };
        if ($templateKey === '') {
            return;
        }

        $code = self::optionalSmsValue(trim($accessCode) !== '' ? $accessCode : (string) ($member['access_code'] ?? ''));
        $reason = self::optionalSmsValue((string) ($member['rejection_reason'] ?? ''));
        $teamName = $this->teamName((int) ($member['team_id'] ?? 0), (string) ($member['team_label'] ?? ''));

        $this->notifyWorkflow(
            $templateKey,
            TeamLeaders::normalizePhone((string) ($member['phone'] ?? '')),
            (string) ($member['full_name'] ?? ''),
            [
                'full_name' => (string) ($member['full_name'] ?? ''),
                'team_name' => $teamName,
                'access_code' => $code,
                'rejection_reason' => $reason,
            ],
            'member_' . $event,
            [
                'member_id' => (int) ($member['id'] ?? 0),
                'team_id' => (int) ($member['team_id'] ?? 0),
                'team_name' => $teamName,
            ]
        );
    }

    /**
     * @param array<string, mixed> $request
     */
    public function notifyMemberRequestStatus(array $request, string $event): void
    {
        $templateKey = match ($event) {
            'approved' => 'member_request_approved',
            'rejected' => 'member_request_rejected',
            default => '',
        };
        if ($templateKey === '') {
            return;
        }

        $teamId = (int) ($request['team_id'] ?? 0);
        $leader = $this->leaderContactForTeam($teamId);
        $reason = self::optionalSmsValue((string) ($request['rejection_reason'] ?? ''));
        $type = (string) ($request['request_type'] ?? '');
        $typeLabel = match ($type) {
            'update' => 'ویرایش',
            'delete' => 'حذف',
            default => 'درخواست',
        };

        $this->notifyWorkflow(
            $templateKey,
            (string) ($leader['phone'] ?? ''),
            (string) ($leader['name'] ?? ''),
            [
                'full_name' => (string) ($request['full_name'] ?? $request['current_full_name'] ?? ''),
                'team_name' => (string) ($request['team_label'] ?? ''),
                'request_type' => $type,
                'request_type_label' => $typeLabel,
                'rejection_reason' => $reason,
            ],
            'member_request_' . $event,
            [
                'member_id' => (int) ($request['member_id'] ?? 0),
                'team_id' => $teamId,
                'team_name' => (string) ($request['team_label'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string, scalar|null> $vars
     * @param array<string, mixed> $logMeta
     */
    private function notifyWorkflow(
        string $templateKey,
        string $phone,
        string $recipientName,
        array $vars,
        string $messageType,
        array $logMeta = []
    ): void {
        if (!$this->isApiConfigured()) {
            return;
        }

        $template = trim((string) ((new CenterSettings($this->pdo))->workflowTemplates()[$templateKey] ?? ''));
        if ($template === '') {
            return;
        }

        $text = trim(self::renderTemplate($template, $vars));
        if ($text === '') {
            return;
        }

        try {
            $this->assertDailyCapacity(1);
        } catch (Throwable) {
            return;
        }

        $phone = TeamLeaders::normalizePhone($phone);
        if ($phone === '' || !preg_match('/^09\d{9}$/', $phone)) {
            return;
        }

        $settings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $client = new MelliPayamak();
        $response = $client->send(
            (string) ($settings['sms_username'] ?? ''),
            (string) ($settings['sms_password'] ?? ''),
            (string) ($settings['sms_from_number'] ?? ''),
            $phone,
            $text
        );
        $unitCost = (int) ($settings['sms_unit_cost'] ?? 0);

        $this->insertLog([
            'batch_uid' => $this->newBatchUid(),
            'message_type' => $messageType,
            'member_id' => (int) ($logMeta['member_id'] ?? 0),
            'team_id' => (int) ($logMeta['team_id'] ?? 0),
            'team_name' => (string) ($logMeta['team_name'] ?? ''),
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'is_leader' => (int) ($logMeta['is_leader'] ?? 0),
            'message_text' => $text,
            'status' => $response['ok'] ? 'sent' : 'failed',
            'error_message' => $response['error'],
            'provider_rec_id' => $response['rec_id'],
            'provider_response' => is_string($response['raw']) ? $response['raw'] : json_encode($response['raw'], JSON_UNESCAPED_UNICODE),
            'cost_rial' => $response['ok'] ? $unitCost : 0,
            'delivery_status' => $response['ok'] ? 'در حال ارسال' : null,
            'api_confirmed' => $response['ok'] && trim((string) ($response['rec_id'] ?? '')) !== '' ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, string> $center
     */
    private static function formatBankInfo(array $center): string
    {
        $parts = [];
        if (trim((string) ($center['bank_name'] ?? '')) !== '') {
            $parts[] = trim((string) $center['bank_name']);
        }
        if (trim((string) ($center['account_holder'] ?? '')) !== '') {
            $parts[] = trim((string) $center['account_holder']);
        }
        if (trim((string) ($center['card_number'] ?? '')) !== '') {
            $parts[] = 'کارت: ' . trim((string) $center['card_number']);
        }
        if (trim((string) ($center['account_number'] ?? '')) !== '') {
            $parts[] = 'حساب: ' . trim((string) $center['account_number']);
        }
        if (trim((string) ($center['sheba'] ?? '')) !== '') {
            $parts[] = 'شبا: ' . trim((string) $center['sheba']);
        }

        return implode(' — ', $parts);
    }

    private function teamName(int $teamId, string $fallback = ''): string
    {
        if ($fallback !== '') {
            return $fallback;
        }
        if ($teamId <= 0) {
            return '';
        }
        $statement = $this->pdo->prepare('SELECT name FROM teams WHERE id = :id');
        $statement->execute(['id' => $teamId]);

        return trim((string) ($statement->fetchColumn() ?: ''));
    }

    /**
     * @return array{name:string, phone:string}
     */
    private function leaderContactForTeam(int $teamId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.leader, t.phone AS team_phone, m.full_name, m.phone AS member_phone
             FROM teams t
             LEFT JOIN members m ON m.team_id = t.id AND m.is_leader = 1
                AND (m.approval_status = 'approved' OR m.approval_status IS NULL)
             WHERE t.id = :team_id
             ORDER BY m.id
             LIMIT 1"
        );
        $statement->execute(['team_id' => $teamId]);
        $row = $statement->fetch();
        if ($row === false) {
            return ['name' => '', 'phone' => ''];
        }

        $name = trim((string) ($row['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['leader'] ?? ''));
        }

        $phone = TeamLeaders::normalizePhone((string) ($row['member_phone'] ?? ''));
        if ($phone === '') {
            $phone = TeamLeaders::normalizePhone((string) ($row['team_phone'] ?? ''));
        }

        return ['name' => $name, 'phone' => $phone];
    }

    /**
     * @param array<string, string> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function history(int $page, int $perPage, array $filters, bool $sync = false): array
    {
        if ($sync) {
            $this->syncHistoryFromApi();
        }

        return (new Repository($this->pdo))->paginatedResource('sms-history', $page, $perPage, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncHistoryFromApi(): array
    {
        $send = (new CenterSettings($this->pdo))->smsSettingsForSend();
        if ($send['sms_username'] === '' || $send['sms_password'] === '') {
            throw new InvalidArgumentException('نام کاربری و رمز API را ابتدا ذخیره کنید.');
        }
        if (trim((string) ($send['sms_from_number'] ?? '')) === '') {
            throw new InvalidArgumentException('خط ارسال را انتخاب و ذخیره کنید.');
        }

        $client = new MelliPayamak();
        $from = (string) ($send['sms_from_number'] ?? '');
        $synced = 0;
        $confirmed = 0;
        $index = 0;
        $batchSize = 200;

        do {
            $result = $client->getMessages(
                (string) $send['sms_username'],
                (string) $send['sms_password'],
                2,
                $index,
                $batchSize,
                $from
            );
            if (!$result['ok']) {
                break;
            }
            $messages = $result['messages'];
            if ($messages === []) {
                break;
            }

            foreach ($messages as $message) {
                $providerId = trim((string) ($message['id'] ?? ''));
                $phone = TeamLeaders::normalizePhone((string) ($message['to'] ?? ''));
                $body = trim((string) ($message['body'] ?? ''));
                if ($phone === '' && $body === '') {
                    continue;
                }

                if ($providerId !== '') {
                    $statement = $this->pdo->prepare(
                        'SELECT id FROM sms_logs WHERE provider_rec_id = :provider_rec_id LIMIT 1'
                    );
                    $statement->execute(['provider_rec_id' => $providerId]);
                    $existingId = $statement->fetchColumn();
                    if ($existingId !== false) {
                        $this->pdo->prepare(
                            'UPDATE sms_logs SET api_confirmed = 1 WHERE id = :id'
                        )->execute(['id' => (int) $existingId]);
                        $confirmed++;
                        continue;
                    }
                }

                $match = $this->pdo->prepare(
                    "SELECT id FROM sms_logs
                     WHERE phone = :phone AND message_text = :message_text
                     ORDER BY id DESC LIMIT 1"
                );
                $match->execute(['phone' => $phone, 'message_text' => $body]);
                $localId = $match->fetchColumn();
                if ($localId !== false) {
                    $this->pdo->prepare(
                        'UPDATE sms_logs SET api_confirmed = 1, provider_rec_id = COALESCE(provider_rec_id, :provider_rec_id)
                         WHERE id = :id'
                    )->execute([
                        'id' => (int) $localId,
                        'provider_rec_id' => $providerId !== '' ? $providerId : null,
                    ]);
                    $confirmed++;
                    continue;
                }

                $this->insertLog([
                    'batch_uid' => 'api-sync',
                    'message_type' => 'api_sent',
                    'member_id' => 0,
                    'team_id' => 0,
                    'team_name' => '',
                    'recipient_name' => '',
                    'phone' => $phone,
                    'is_leader' => 0,
                    'message_text' => $body,
                    'status' => 'sent',
                    'error_message' => null,
                    'provider_rec_id' => $providerId !== '' ? $providerId : null,
                    'provider_response' => json_encode($message, JSON_UNESCAPED_UNICODE),
                    'cost_rial' => (int) ($send['sms_unit_cost'] ?? 0),
                    'delivery_status' => null,
                    'api_confirmed' => 1,
                ]);
                $synced++;
            }

            $index += count($messages);
        } while (count($messages) === $batchSize);

        (new CenterSettings($this->pdo))->markSmsHistorySynced();

        return ['synced' => $synced, 'confirmed' => $confirmed, 'index' => $index];
    }

    /**
     * @param list<int>|null $logIds
     * @return array<string, mixed>
     */
    public function checkDeliveries(?array $logIds = null, ?string $batchUid = null): array
    {
        $send = (new CenterSettings($this->pdo))->smsSettingsForSend();
        if ($send['sms_username'] === '' || $send['sms_password'] === '') {
            return ['checked' => 0, 'updated' => 0];
        }

        $clauses = ["status = 'sent'", "provider_rec_id IS NOT NULL", "provider_rec_id <> ''"];
        $params = [];
        if ($batchUid !== null && $batchUid !== '') {
            $clauses[] = 'batch_uid = :batch_uid';
            $params['batch_uid'] = $batchUid;
        }
        if ($logIds !== null && $logIds !== []) {
            $ids = implode(',', array_map('intval', $logIds));
            $clauses[] = "id IN ({$ids})";
        } else {
            $clauses[] = "(delivery_status IS NULL OR delivery_status = '' OR delivery_status = 'در حال ارسال' OR delivery_status = 'نامشخص')";
        }

        $sql = 'SELECT id, provider_rec_id FROM sms_logs WHERE ' . implode(' AND ', $clauses) . ' ORDER BY id DESC LIMIT 200';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();

        $client = new MelliPayamak();
        $checked = 0;
        $updated = 0;
        $now = JalaliDate::todayParts()['formatted'];
        foreach ($rows as $row) {
            $recId = trim((string) ($row['provider_rec_id'] ?? ''));
            if ($recId === '') {
                continue;
            }
            $checked++;
            $delivery = $client->getDelivery(
                (string) $send['sms_username'],
                (string) $send['sms_password'],
                $recId
            );
            if (!$delivery['ok'] || $delivery['status'] === null) {
                continue;
            }
            $this->pdo->prepare(
                'UPDATE sms_logs SET delivery_status = :delivery_status, delivery_checked_at = :checked_at WHERE id = :id'
            )->execute([
                'delivery_status' => $delivery['status'],
                'checked_at' => $now,
                'id' => (int) ($row['id'] ?? 0),
            ]);
            $updated++;
        }

        return ['checked' => $checked, 'updated' => $updated];
    }

    /**
     * @param list<array<string,mixed>> $recipients
     * @param callable(array<string,mixed>):string $messageBuilder
     * @return array<string, mixed>
     */
    private function dispatchBatch(string $batchUid, string $messageType, array $recipients, callable $messageBuilder): array
    {
        $settings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $client = new MelliPayamak();
        $unitCost = (int) ($settings['sms_unit_cost'] ?? 0);
        if ($unitCost <= 0 && $this->isApiConfigured($settings)) {
            try {
                $pricing = $this->refreshPricing($settings);
                $unitCost = (int) ($pricing['base_price'] ?? 0);
            } catch (Throwable) {
                $unitCost = 0;
            }
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];
        $pendingLogIds = [];

        foreach ($recipients as $member) {
            $phone = TeamLeaders::normalizePhone((string) ($member['phone'] ?? ''));
            if ($phone === '' || !preg_match('/^09\d{9}$/', $phone)) {
                $this->insertLog([
                    'batch_uid' => $batchUid,
                    'message_type' => $messageType,
                    'member_id' => (int) ($member['id'] ?? 0),
                    'team_id' => (int) ($member['team_id'] ?? 0),
                    'team_name' => (string) ($member['team_label'] ?? ''),
                    'recipient_name' => (string) ($member['full_name'] ?? ''),
                    'phone' => (string) ($member['phone'] ?? ''),
                    'is_leader' => (int) ($member['is_leader'] ?? 0),
                    'message_text' => '',
                    'status' => 'skipped',
                    'error_message' => 'شماره موبایل معتبر نیست',
                    'provider_rec_id' => null,
                    'provider_response' => null,
                    'cost_rial' => 0,
                    'delivery_status' => null,
                    'api_confirmed' => 0,
                ]);
                $skipped++;
                continue;
            }

            try {
                $text = trim($messageBuilder($member));
            } catch (InvalidArgumentException $exception) {
                $this->insertLog([
                    'batch_uid' => $batchUid,
                    'message_type' => $messageType,
                    'member_id' => (int) ($member['id'] ?? 0),
                    'team_id' => (int) ($member['team_id'] ?? 0),
                    'team_name' => (string) ($member['team_label'] ?? ''),
                    'recipient_name' => (string) ($member['full_name'] ?? ''),
                    'phone' => $phone,
                    'is_leader' => (int) ($member['is_leader'] ?? 0),
                    'message_text' => '',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'provider_rec_id' => null,
                    'provider_response' => null,
                    'cost_rial' => 0,
                    'delivery_status' => null,
                    'api_confirmed' => 0,
                ]);
                $failed++;
                continue;
            }

            $response = $client->send(
                (string) ($settings['sms_username'] ?? ''),
                (string) ($settings['sms_password'] ?? ''),
                (string) ($settings['sms_from_number'] ?? ''),
                $phone,
                $text
            );

            $logId = $this->insertLog([
                'batch_uid' => $batchUid,
                'message_type' => $messageType,
                'member_id' => (int) ($member['id'] ?? 0),
                'team_id' => (int) ($member['team_id'] ?? 0),
                'team_name' => (string) ($member['team_label'] ?? ''),
                'recipient_name' => (string) ($member['full_name'] ?? ''),
                'phone' => $phone,
                'is_leader' => (int) ($member['is_leader'] ?? 0),
                'message_text' => $text,
                'status' => $response['ok'] ? 'sent' : 'failed',
                'error_message' => $response['error'],
                'provider_rec_id' => $response['rec_id'],
                'provider_response' => is_string($response['raw']) ? $response['raw'] : json_encode($response['raw'], JSON_UNESCAPED_UNICODE),
                'cost_rial' => $response['ok'] ? $unitCost : 0,
                'delivery_status' => $response['ok'] ? 'در حال ارسال' : null,
                'api_confirmed' => $response['ok'] && trim((string) ($response['rec_id'] ?? '')) !== '' ? 1 : 0,
            ]);

            if ($response['ok']) {
                $sent++;
                if ($logId > 0) {
                    $pendingLogIds[] = $logId;
                }
            } else {
                $failed++;
            }
            $results[] = [
                'member_id' => (int) ($member['id'] ?? 0),
                'phone' => $phone,
                'status' => $response['ok'] ? 'sent' : 'failed',
                'error' => $response['error'],
                'log_id' => $logId,
            ];
        }

        return [
            'batch_uid' => $batchUid,
            'message_type' => $messageType,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'total_cost' => $sent * $unitCost,
            'results' => $results,
            'pending_delivery_log_ids' => $pendingLogIds,
        ];
    }

    private function assertDailyCapacity(int $planned): void
    {
        $stats = $this->stats();
        if ($planned > (int) ($stats['remaining_today'] ?? 0)) {
            throw new InvalidArgumentException(
                'سقف ارسال روزانه تکمیل شده یا تعداد انتخاب‌شده بیش از باقی‌مانده است. باقی‌مانده امروز: '
                . (int) ($stats['remaining_today'] ?? 0)
            );
        }
    }

    /**
     * @param list<int> $memberIds
     * @return list<array<string,mixed>>
     */
    private function loadMembersByIds(array $memberIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $idList = implode(',', $ids);

        return $this->pdo->query(
            "SELECT m.id, m.team_id, m.full_name, m.phone, m.is_leader, t.name AS team_label
             FROM members m
             LEFT JOIN teams t ON t.id = m.team_id
             WHERE m.id IN ({$idList}) AND m.approval_status = 'approved'
             ORDER BY m.is_leader DESC, t.name, m.full_name"
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertLog(array $data): int
    {
        $now = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            'INSERT INTO sms_logs (
                batch_uid, message_type, member_id, team_id, team_name, recipient_name, phone, is_leader,
                message_text, status, error_message, provider_rec_id, provider_response, cost_rial,
                sent_by, created_at, sent_at, delivery_status, delivery_checked_at, api_confirmed
             ) VALUES (
                :batch_uid, :message_type, :member_id, :team_id, :team_name, :recipient_name, :phone, :is_leader,
                :message_text, :status, :error_message, :provider_rec_id, :provider_response, :cost_rial,
                :sent_by, :created_at, :sent_at, :delivery_status, :delivery_checked_at, :api_confirmed
             )'
        )->execute([
            'batch_uid' => $data['batch_uid'],
            'message_type' => $data['message_type'],
            'member_id' => (int) ($data['member_id'] ?? 0) ?: null,
            'team_id' => (int) ($data['team_id'] ?? 0) ?: null,
            'team_name' => $data['team_name'] ?: null,
            'recipient_name' => $data['recipient_name'] ?: null,
            'phone' => $data['phone'] ?: null,
            'is_leader' => (int) ($data['is_leader'] ?? 0),
            'message_text' => $data['message_text'],
            'status' => $data['status'],
            'error_message' => $data['error_message'],
            'provider_rec_id' => $data['provider_rec_id'],
            'provider_response' => $data['provider_response'],
            'cost_rial' => (int) ($data['cost_rial'] ?? 0),
            'sent_by' => Access::username(),
            'created_at' => $now,
            'sent_at' => $data['status'] === 'sent' ? $now : null,
            'delivery_status' => $data['delivery_status'] ?? null,
            'delivery_checked_at' => null,
            'api_confirmed' => (int) ($data['api_confirmed'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function newBatchUid(): string
    {
        return 'sms-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }
}
