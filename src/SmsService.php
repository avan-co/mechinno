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
        $configured = $this->isApiConfigured($settings);

        $result = array_merge($settings, [
            'sms_configured' => $configured,
            'sms_credit' => null,
            'sms_base_price' => (int) ($settings['sms_unit_cost'] ?? 0),
        ]);

        if ($withLive && $configured) {
            $live = $this->refreshPricing();
            $result['sms_credit'] = $live['credit'];
            if ($live['base_price'] !== null) {
                $result['sms_base_price'] = (int) $live['base_price'];
                $result['sms_unit_cost'] = (int) $live['base_price'];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function isApiConfigured(?array $settings = null): bool
    {
        $settings ??= (new CenterSettings($this->pdo))->smsSettingsForSend();

        return trim((string) ($settings['sms_username'] ?? '')) !== ''
            && trim((string) ($settings['sms_password'] ?? '')) !== ''
            && trim((string) ($settings['sms_from_number'] ?? '')) !== '';
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
            'template' => [
                'sms_charge_template' => $payload['sms_charge_template'] ?? '',
            ],
            default => $payload,
        };

        $settings = $center->updateSms($updatePayload);

        $username = trim((string) ($settings['sms_username'] ?? ''));
        $passwordPayload = trim((string) ($payload['sms_password'] ?? ''));
        $send = $center->smsSettingsForSend();
        $shouldQueryLines = in_array($section, ['credentials', ''], true)
            && $username !== '' && $send['sms_password'] !== ''
            && (
                ($current['sms_lines_queried_at'] ?? '') === ''
                || $passwordPayload !== ''
                || ($payload['query_lines'] ?? false)
            );

        if ($shouldQueryLines) {
            try {
                $this->refreshLineNumbers($send, true);
            } catch (Throwable) {
                // خطوط بعداً با دکمه استعلام دستی قابل دریافت است.
            }
            $settings = $center->smsSettings();
        }

        if ($this->isApiConfigured($send)) {
            try {
                $this->refreshPricing($send);
            } catch (Throwable) {
                // تعرفه و موجودی بعداً قابل بروزرسانی است.
            }
        }

        return $this->settings(withLive: $this->isApiConfigured($send));
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
        if ($withLive && $this->isApiConfigured($settings)) {
            try {
                $live = $this->refreshPricing($settings);
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
    public function queryLines(): array
    {
        Access::requireWriteJson();
        $send = (new CenterSettings($this->pdo))->smsSettingsForSend();

        return $this->refreshLineNumbers($send, false);
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
        if ($credit === null && $price === null && $errors !== []) {
            throw new RuntimeException($errors[0]);
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
     * @return list<array<string, mixed>>
     */
    public function chargeReminderPreview(?int $teamId = null): array
    {
        $repo = new Repository($this->pdo);
        $settings = (new CenterSettings($this->pdo))->get();
        $smsSettings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $template = (string) ($smsSettings['sms_charge_template'] ?? CenterSettings::DEFAULT_CHARGE_TEMPLATE);
        $items = [];

        foreach ($repo->debtorTeamsForSms() as $row) {
            if ($teamId !== null && (int) ($row['team_id'] ?? 0) !== $teamId) {
                continue;
            }
            $leader = $this->leaderRecipient((int) $row['team_id']);
            $displayLeader = $this->teamLeaderDisplay((int) $row['team_id']);
            $leaderMissing = $leader === null;
            $leaderData = $displayLeader ?? [];
            $phone = trim((string) ($leaderData['phone'] ?? ''));
            $items[] = [
                'team_id' => (int) $row['team_id'],
                'team_name' => (string) ($row['team_name'] ?? ''),
                'debt_total' => (int) ($row['debt_total'] ?? 0),
                'debt_summary' => (string) ($row['debt_summary'] ?? ''),
                'member_id' => (int) ($leader['id'] ?? 0),
                'leader_name' => (string) ($leaderData['full_name'] ?? ''),
                'phone' => $phone,
                'leader_missing' => $leaderMissing,
                'can_send' => !$leaderMissing && $phone !== '',
                'message' => $leaderMissing
                    ? ''
                    : $this->renderChargeTemplate($template, $row, $settings, $leader ?? $leaderData),
            ];
        }

        return $items;
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

        return $this->dispatchBatch($batchUid, 'announcement', $recipients, static fn (array $member): string => $message);
    }

    /**
     * @param list<array{member_id:int,message?:string,team_id?:int}> $items
     * @return array<string, mixed>
     */
    public function sendChargeReminders(array $items, ?string $template = null): array
    {
        Access::requireWriteJson();
        if ($items === []) {
            throw new InvalidArgumentException('حداقل یک نهاد بدهکار انتخاب کنید.');
        }

        $batchUid = $this->newBatchUid();
        $centerSettings = (new CenterSettings($this->pdo))->get();
        $smsSettings = (new CenterSettings($this->pdo))->smsSettingsForSend();
        $template = trim((string) ($template ?? $smsSettings['sms_charge_template'] ?? CenterSettings::DEFAULT_CHARGE_TEMPLATE));
        if ($template === '') {
            throw new InvalidArgumentException('الگوی یادآور شارژ خالی است.');
        }

        $repo = new Repository($this->pdo);
        $debtors = [];
        foreach ($repo->debtorTeamsForSms() as $row) {
            $debtors[(int) ($row['team_id'] ?? 0)] = $row;
        }

        $recipients = [];
        $messages = [];
        foreach ($items as $item) {
            $memberId = (int) ($item['member_id'] ?? 0);
            $teamId = (int) ($item['team_id'] ?? 0);
            if ($memberId <= 0 && $teamId > 0) {
                $leader = $this->leaderRecipient($teamId);
                $memberId = (int) ($leader['id'] ?? 0);
            }
            if ($memberId <= 0) {
                continue;
            }
            $customMessage = trim((string) ($item['message'] ?? ''));
            if ($customMessage !== '') {
                $messages[$memberId] = $customMessage;
            } elseif ($teamId > 0 && isset($debtors[$teamId])) {
                $leader = $this->leaderRecipient($teamId);
                $messages[$memberId] = $this->renderChargeTemplate($template, $debtors[$teamId], $centerSettings, $leader ?? []);
            } else {
                $messages[$memberId] = $template;
            }
            $recipients[] = $memberId;
        }

        $recipientRows = $this->loadMembersByIds($recipients, leadersOnly: true);
        $this->assertDailyCapacity(count($recipientRows));

        return $this->dispatchBatch(
            $batchUid,
            'charge_reminder',
            $recipientRows,
            static function (array $member) use ($messages): string {
                $text = trim($messages[(int) ($member['id'] ?? 0)] ?? '');
                if ($text === '') {
                    throw new InvalidArgumentException('متن یادآور برای «' . ($member['full_name'] ?? 'عضو') . '» خالی است.');
                }

                return $text;
            }
        );
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
                'api_confirmed' => 0,
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
    private function loadMembersByIds(array $memberIds, bool $leadersOnly = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $idList = implode(',', $ids);
        $leaderSql = $leadersOnly ? ' AND m.is_leader = 1' : '';

        return $this->pdo->query(
            "SELECT m.id, m.team_id, m.full_name, m.phone, m.is_leader, t.name AS team_label
             FROM members m
             LEFT JOIN teams t ON t.id = m.team_id
             WHERE m.id IN ({$idList}) AND m.approval_status = 'approved'{$leaderSql}
             ORDER BY m.is_leader DESC, t.name, m.full_name"
        )->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leaderRecipient(int $teamId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT m.id, m.full_name, m.phone, m.team_id, t.name AS team_label, m.is_leader
             FROM members m
             LEFT JOIN teams t ON t.id = m.team_id
             WHERE m.team_id = :team_id AND m.is_leader = 1 AND m.approval_status = 'approved'
             ORDER BY m.id LIMIT 1"
        );
        $statement->execute(['team_id' => $teamId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamLeaderDisplay(int $teamId): ?array
    {
        $leader = $this->leaderRecipient($teamId);
        if ($leader !== null) {
            return $leader;
        }

        $teamStatement = $this->pdo->prepare('SELECT leader, phone FROM teams WHERE id = :team_id');
        $teamStatement->execute(['team_id' => $teamId]);
        $team = $teamStatement->fetch();
        if ($team === false) {
            return null;
        }
        $name = trim((string) ($team['leader'] ?? ''));
        $phone = TeamLeaders::normalizePhone((string) ($team['phone'] ?? ''));
        if ($name === '' && $phone === '') {
            return null;
        }

        return [
            'id' => 0,
            'full_name' => $name !== '' ? $name : 'مسئول نهاد',
            'phone' => $phone,
            'team_id' => $teamId,
            'team_label' => '',
            'is_leader' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $settings
     * @param array<string, mixed> $leader
     */
    public function renderChargeTemplate(string $template, array $row, array $settings, array $leader = []): string
    {
        $debtSummary = trim((string) ($row['debt_summary'] ?? ''));
        $bank = trim((string) ($settings['bank_name'] ?? ''));
        $card = trim((string) ($settings['card_number'] ?? ''));
        $account = trim((string) ($settings['account_number'] ?? ''));
        $bankInfo = '';
        if ($card !== '') {
            $bankInfo = 'کارت: ' . $card;
        } elseif ($account !== '') {
            $bankInfo = 'حساب: ' . $account;
        } elseif ($bank !== '') {
            $bankInfo = 'بانک: ' . $bank;
        }

        $replacements = [
            '{team_name}' => (string) ($row['team_name'] ?? 'نهاد'),
            '{leader_name}' => (string) ($leader['full_name'] ?? ''),
            '{debt_total}' => number_format((int) ($row['debt_total'] ?? 0)),
            '{debt_summary}' => $debtSummary !== '' ? 'دوره: ' . $debtSummary : '',
            '{bank_info}' => $bankInfo,
            '{card_number}' => $card,
            '{account_number}' => $account,
            '{bank_name}' => $bank,
        ];

        $text = strtr($template, $replacements);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text) ?: []), static fn (string $line): bool => $line !== ''));

        return implode("\n", $lines);
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

    /**
     * @param array<string, mixed> $send
     * @return array<string, mixed>
     */
    private function refreshLineNumbers(array $send, bool $onlyIfEmpty): array
    {
        if ($send['sms_username'] === '' || $send['sms_password'] === '') {
            throw new InvalidArgumentException('نام کاربری و رمز API را وارد کنید.');
        }

        $current = (new CenterSettings($this->pdo))->smsSettings();
        if ($onlyIfEmpty && $current['sms_lines_queried_at'] !== '' && $current['sms_line_numbers'] !== []) {
            return ['numbers' => $current['sms_line_numbers'], 'cached' => true];
        }

        $result = (new MelliPayamak())->getUserNumbers(
            (string) $send['sms_username'],
            (string) $send['sms_password']
        );
        if (!$result['ok'] || $result['numbers'] === []) {
            throw new RuntimeException($result['error'] ?? 'استعلام خطوط ارسال ناموفق بود.');
        }

        (new CenterSettings($this->pdo))->storeSmsLineNumbers($result['numbers'], (string) ($send['sms_from_number'] ?? ''));

        return ['numbers' => $result['numbers'], 'cached' => false];
    }

    private function newBatchUid(): string
    {
        return 'sms-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }
}
