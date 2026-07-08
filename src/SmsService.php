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
    public function settings(): array
    {
        return (new CenterSettings($this->pdo))->smsSettings();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSettings(array $payload): array
    {
        return (new CenterSettings($this->pdo))->updateSms($payload);
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $today = JalaliDate::todayParts()['formatted'];
        $settings = $this->sendSettings();
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

        return [
            'daily_limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'remaining_today' => max(0, $dailyLimit - $sentToday),
            'cost_today' => $costToday,
            'total_sent' => $totalSent,
            'total_cost' => $totalCost,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function recipients(int $page, int $perPage, array $filters): array
    {
        $repo = new Repository($this->pdo);

        return $repo->paginatedResource('sms-recipients', $page, $perPage, $filters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chargeReminderPreview(?int $teamId = null): array
    {
        $repo = new Repository($this->pdo);
        $settings = (new CenterSettings($this->pdo))->get();
        $items = [];

        foreach ($repo->debtorTeamsForSms() as $row) {
            if ($teamId !== null && (int) ($row['team_id'] ?? 0) !== $teamId) {
                continue;
            }
            $leader = $this->leaderRecipient((int) $row['team_id']);
            if ($leader === null) {
                continue;
            }
            $items[] = [
                'team_id' => (int) $row['team_id'],
                'team_name' => (string) ($row['team_name'] ?? ''),
                'debt_total' => (int) ($row['debt_total'] ?? 0),
                'debt_summary' => (string) ($row['debt_summary'] ?? ''),
                'member_id' => (int) $leader['id'],
                'leader_name' => (string) $leader['full_name'],
                'phone' => (string) ($leader['phone'] ?? ''),
                'message' => $this->buildChargeReminderMessage($row, $settings),
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
     * @return array<string, mixed>
     */
    private function sendSettings(): array
    {
        return (new CenterSettings($this->pdo))->smsSettingsForSend();
    }

    /**
     * @param list<array{member_id:int,message:string}> $items
     * @return array<string, mixed>
     */
    public function sendChargeReminders(array $items): array
    {
        Access::requireWriteJson();
        if ($items === []) {
            throw new InvalidArgumentException('حداقل یک نهاد بدهکار انتخاب کنید.');
        }

        $batchUid = $this->newBatchUid();
        $memberIds = array_map(static fn (array $item): int => (int) ($item['member_id'] ?? 0), $items);
        $messages = [];
        foreach ($items as $item) {
            $messages[(int) ($item['member_id'] ?? 0)] = trim((string) ($item['message'] ?? ''));
        }
        $recipients = $this->loadMembersByIds($memberIds, leadersOnly: true);
        $this->assertDailyCapacity(count($recipients));

        return $this->dispatchBatch(
            $batchUid,
            'charge_reminder',
            $recipients,
            static function (array $member) use ($messages): string {
                $text = $messages[(int) ($member['id'] ?? 0)] ?? '';
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
    public function history(int $page, int $perPage, array $filters): array
    {
        $repo = new Repository($this->pdo);

        return $repo->paginatedResource('sms-history', $page, $perPage, $filters);
    }

    /**
     * @param list<array<string,mixed>> $recipients
     * @param callable(array<string,mixed>):string $messageBuilder
     * @return array<string, mixed>
     */
    private function dispatchBatch(string $batchUid, string $messageType, array $recipients, callable $messageBuilder): array
    {
        $settings = $this->sendSettings();
        $client = new MelliPayamak();
        $unitCost = (int) ($settings['sms_unit_cost'] ?? 0);
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];

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

            $this->insertLog([
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
                'provider_response' => $response['raw'],
                'cost_rial' => $response['ok'] ? $unitCost : 0,
            ]);

            if ($response['ok']) {
                $sent++;
            } else {
                $failed++;
            }
            $results[] = [
                'member_id' => (int) ($member['id'] ?? 0),
                'phone' => $phone,
                'status' => $response['ok'] ? 'sent' : 'failed',
                'error' => $response['error'],
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
     * @param array<string, mixed> $row
     * @param array<string, string> $settings
     */
    private function buildChargeReminderMessage(array $row, array $settings): string
    {
        $teamName = (string) ($row['team_name'] ?? 'نهاد');
        $debt = number_format((int) ($row['debt_total'] ?? 0));
        $summary = trim((string) ($row['debt_summary'] ?? ''));
        $bank = trim((string) ($settings['bank_name'] ?? ''));
        $card = trim((string) ($settings['card_number'] ?? ''));
        $account = trim((string) ($settings['account_number'] ?? ''));

        $lines = [
            $teamName . ' گرامی؛',
            'مانده شارژ: ' . $debt . ' ریال',
        ];
        if ($summary !== '') {
            $lines[] = 'دوره: ' . $summary;
        }
        $lines[] = 'لطفاً در اسرع وقت نسبت به تسویه اقدام فرمایید.';
        if ($card !== '') {
            $lines[] = 'کارت: ' . $card;
        } elseif ($account !== '') {
            $lines[] = 'حساب: ' . $account;
        } elseif ($bank !== '') {
            $lines[] = 'بانک: ' . $bank;
        }
        $lines[] = 'مرکز نوآوری مکانیک';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertLog(array $data): void
    {
        $now = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            'INSERT INTO sms_logs (
                batch_uid, message_type, member_id, team_id, team_name, recipient_name, phone, is_leader,
                message_text, status, error_message, provider_rec_id, provider_response, cost_rial,
                sent_by, created_at, sent_at
             ) VALUES (
                :batch_uid, :message_type, :member_id, :team_id, :team_name, :recipient_name, :phone, :is_leader,
                :message_text, :status, :error_message, :provider_rec_id, :provider_response, :cost_rial,
                :sent_by, :created_at, :sent_at
             )'
        )->execute([
            'batch_uid' => $data['batch_uid'],
            'message_type' => $data['message_type'],
            'member_id' => $data['member_id'] ?: null,
            'team_id' => $data['team_id'] ?: null,
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
        ]);
    }

    private function newBatchUid(): string
    {
        return 'sms-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }
}
