<?php

declare(strict_types=1);

final class CenterSettings
{
    private const DEFAULTS = [
        'bank_name' => '',
        'account_holder' => '',
        'account_number' => '',
        'card_number' => '',
        'sheba' => '',
        'payment_guide' => "پس از واریز شارژ، مبلغ، تاریخ، سال مالی و ماه را در بخش «اعلام واریز» ثبت کنید تا مدیر مرکز تأیید کند.",
    ];

    /** @var array<string, string> */
    public const WORKFLOW_TEMPLATE_DEFAULTS = [
        'room_pending' => 'درخواست رزرو {room_name} در {reserved_date} ساعت {start_time} تا {end_time} ثبت شد. کد پیگیری: {public_token}',
        'room_approved' => 'رزرو {room_name} در {reserved_date} ساعت {start_time} تا {end_time} تأیید شد. کد پیگیری: {public_token}',
        'room_rejected' => 'رزرو {room_name} در {reserved_date} رد شد.{rejection_reason_line}',
        'room_cancelled' => 'رزرو {room_name} در {reserved_date} لغو شد.{cancel_reason_line}',
        'member_approved' => 'عضویت {full_name} در {team_name} تأیید شد.{access_code_line}',
        'member_rejected' => 'درخواست عضویت {full_name} در {team_name} رد شد.{rejection_reason_line}',
        'member_request_approved' => 'درخواست {request_type_label} عضو {full_name} در {team_name} تأیید شد.',
        'member_request_rejected' => 'درخواست {request_type_label} عضو {full_name} در {team_name} رد شد.{rejection_reason_line}',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, string>
     */
    public function get(): array
    {
        $statement = $this->pdo->query('SELECT bank_name, account_holder, account_number, card_number, sheba, payment_guide, updated_at FROM center_settings WHERE id = 1');
        $row = $statement->fetch();
        if ($row === false) {
            $this->ensureRow();

            return self::DEFAULTS;
        }

        return [
            'bank_name' => (string) ($row['bank_name'] ?? ''),
            'account_holder' => (string) ($row['account_holder'] ?? ''),
            'account_number' => (string) ($row['account_number'] ?? ''),
            'card_number' => (string) ($row['card_number'] ?? ''),
            'sheba' => (string) ($row['sheba'] ?? ''),
            'payment_guide' => (string) ($row['payment_guide'] ?? self::DEFAULTS['payment_guide']),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function update(array $payload): array
    {
        $this->ensureRow();
        $data = [
            'bank_name' => trim((string) ($payload['bank_name'] ?? '')),
            'account_holder' => trim((string) ($payload['account_holder'] ?? '')),
            'account_number' => trim((string) ($payload['account_number'] ?? '')),
            'card_number' => trim((string) ($payload['card_number'] ?? '')),
            'sheba' => trim((string) ($payload['sheba'] ?? '')),
            'payment_guide' => trim((string) ($payload['payment_guide'] ?? '')),
            'updated_at' => JalaliDate::todayParts()['formatted'],
        ];

        $statement = $this->pdo->prepare(
            'UPDATE center_settings SET
                bank_name = :bank_name,
                account_holder = :account_holder,
                account_number = :account_number,
                card_number = :card_number,
                sheba = :sheba,
                payment_guide = :payment_guide,
                updated_at = :updated_at
             WHERE id = 1'
        );
        $statement->execute($data);

        return $this->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function smsSettings(): array
    {
        $this->ensureRow();
        try {
            $statement = $this->pdo->query(
                'SELECT sms_username, sms_password, sms_from_number, sms_daily_limit, sms_unit_cost, sms_updated_at,
                        sms_line_numbers, sms_lines_queried_at, sms_history_synced_at,
                        sms_panel_credit, sms_live_synced_at, sms_charge_template, sms_workflow_templates
                 FROM center_settings WHERE id = 1'
            );
            $row = $statement->fetch() ?: [];
        } catch (PDOException) {
            $statement = $this->pdo->query(
                'SELECT sms_username, sms_password, sms_from_number, sms_daily_limit, sms_unit_cost, sms_updated_at
                 FROM center_settings WHERE id = 1'
            );
            $row = $statement->fetch() ?: [];
            $row['sms_line_numbers'] = '';
            $row['sms_lines_queried_at'] = '';
            $row['sms_history_synced_at'] = '';
            $row['sms_panel_credit'] = null;
            $row['sms_live_synced_at'] = '';
            $row['sms_charge_template'] = '';
            $row['sms_workflow_templates'] = '';
        }

        return [
            'sms_username' => (string) ($row['sms_username'] ?? ''),
            'sms_password_set' => $this->smsPasswordPlain($row) !== '',
            'sms_from_number' => (string) ($row['sms_from_number'] ?? ''),
            'sms_daily_limit' => (int) ($row['sms_daily_limit'] ?? 500),
            'sms_unit_cost' => (int) ($row['sms_unit_cost'] ?? 0),
            'sms_updated_at' => (string) ($row['sms_updated_at'] ?? ''),
            'sms_line_numbers' => $this->decodeLineNumbers((string) ($row['sms_line_numbers'] ?? '')),
            'sms_lines_queried_at' => (string) ($row['sms_lines_queried_at'] ?? ''),
            'sms_history_synced_at' => (string) ($row['sms_history_synced_at'] ?? ''),
            'sms_panel_credit' => isset($row['sms_panel_credit']) && $row['sms_panel_credit'] !== null
                ? (int) $row['sms_panel_credit']
                : null,
            'sms_live_synced_at' => (string) ($row['sms_live_synced_at'] ?? ''),
            'sms_charge_template' => (string) ($row['sms_charge_template'] ?? ''),
            'sms_workflow_templates' => $this->decodeWorkflowTemplates((string) ($row['sms_workflow_templates'] ?? '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function workflowTemplates(): array
    {
        $this->ensureRow();
        try {
            $json = (string) $this->pdo->query('SELECT sms_workflow_templates FROM center_settings WHERE id = 1')->fetchColumn();
        } catch (PDOException) {
            $json = '';
        }

        return $this->decodeWorkflowTemplates($json);
    }

    /**
     * @return array<string, mixed>
     */
    public function smsSettingsForSend(): array
    {
        $this->ensureRow();
        $statement = $this->pdo->query(
            'SELECT sms_username, sms_password, sms_from_number, sms_daily_limit, sms_unit_cost
             FROM center_settings WHERE id = 1'
        );
        $row = $statement->fetch() ?: [];

        return [
            'sms_username' => (string) ($row['sms_username'] ?? ''),
            'sms_password' => $this->smsPasswordPlain($row),
            'sms_from_number' => (string) ($row['sms_from_number'] ?? ''),
            'sms_daily_limit' => (int) ($row['sms_daily_limit'] ?? 500),
            'sms_unit_cost' => (int) ($row['sms_unit_cost'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSms(array $payload): array
    {
        $this->ensureRow();
        $current = $this->pdo->query(
            'SELECT sms_username, sms_password, sms_from_number, sms_daily_limit, sms_unit_cost,
                    sms_line_numbers, sms_lines_queried_at, sms_charge_template, sms_workflow_templates
             FROM center_settings WHERE id = 1'
        )->fetch() ?: [];

        $username = array_key_exists('sms_username', $payload)
            ? trim((string) $payload['sms_username'])
            : trim((string) ($current['sms_username'] ?? ''));

        $password = array_key_exists('sms_password', $payload)
            ? trim((string) $payload['sms_password'])
            : '';
        if ($password === '') {
            $password = (string) ($current['sms_password'] ?? '');
        } elseif (app_configured()) {
            $password = SecretVault::encrypt($password, app_config());
        }

        $fromNumber = array_key_exists('sms_from_number', $payload)
            ? trim((string) $payload['sms_from_number'])
            : trim((string) ($current['sms_from_number'] ?? ''));

        $dailyLimit = array_key_exists('sms_daily_limit', $payload)
            ? max(1, (int) $payload['sms_daily_limit'])
            : max(1, (int) ($current['sms_daily_limit'] ?? 500));

        $unitCost = array_key_exists('sms_unit_cost', $payload)
            ? max(0, (int) $payload['sms_unit_cost'])
            : max(0, (int) ($current['sms_unit_cost'] ?? 0));

        $lineNumbers = $payload['sms_line_numbers'] ?? null;
        $lineNumbersJson = is_array($lineNumbers)
            ? json_encode(array_values(array_filter(array_map('strval', $lineNumbers))), JSON_UNESCAPED_UNICODE)
            : (string) ($current['sms_line_numbers'] ?? '');

        $linesQueriedAt = array_key_exists('sms_lines_queried_at', $payload)
            ? trim((string) $payload['sms_lines_queried_at'])
            : trim((string) ($current['sms_lines_queried_at'] ?? ''));

        $chargeTemplate = array_key_exists('sms_charge_template', $payload)
            ? trim((string) $payload['sms_charge_template'])
            : trim((string) ($current['sms_charge_template'] ?? ''));

        $workflowTemplates = array_key_exists('sms_workflow_templates', $payload)
            ? $this->encodeWorkflowTemplates($payload['sms_workflow_templates'])
            : (string) ($current['sms_workflow_templates'] ?? '');

        $statement = $this->pdo->prepare(
            'UPDATE center_settings SET
                sms_username = :sms_username,
                sms_password = :sms_password,
                sms_from_number = :sms_from_number,
                sms_daily_limit = :sms_daily_limit,
                sms_unit_cost = :sms_unit_cost,
                sms_line_numbers = :sms_line_numbers,
                sms_lines_queried_at = :sms_lines_queried_at,
                sms_charge_template = :sms_charge_template,
                sms_workflow_templates = :sms_workflow_templates,
                sms_updated_at = :sms_updated_at
             WHERE id = 1'
        );
        $statement->execute([
            'sms_username' => $username,
            'sms_password' => $password,
            'sms_from_number' => $fromNumber,
            'sms_daily_limit' => $dailyLimit,
            'sms_unit_cost' => $unitCost,
            'sms_line_numbers' => $lineNumbersJson,
            'sms_lines_queried_at' => $linesQueriedAt,
            'sms_charge_template' => $chargeTemplate,
            'sms_workflow_templates' => $workflowTemplates,
            'sms_updated_at' => JalaliDate::todayParts()['formatted'],
        ]);

        return $this->smsSettings();
    }

    /**
     * @param list<string> $numbers
     */
    public function storeSmsLineNumbers(array $numbers, ?string $fromNumber = null): void
    {
        $this->ensureRow();
        $numbers = array_values(array_unique(array_filter(array_map('trim', $numbers))));
        $currentFrom = (string) $this->pdo->query('SELECT sms_from_number FROM center_settings WHERE id = 1')->fetchColumn();
        $from = trim((string) ($fromNumber ?? $currentFrom));
        if ($from === '' && $numbers !== []) {
            $from = $numbers[0];
        }

        $statement = $this->pdo->prepare(
            'UPDATE center_settings SET
                sms_line_numbers = :sms_line_numbers,
                sms_lines_queried_at = :sms_lines_queried_at,
                sms_from_number = CASE WHEN sms_from_number IS NULL OR sms_from_number = \'\' THEN :sms_from_number ELSE sms_from_number END
             WHERE id = 1'
        );
        $statement->execute([
            'sms_line_numbers' => json_encode($numbers, JSON_UNESCAPED_UNICODE),
            'sms_lines_queried_at' => JalaliDate::todayParts()['formatted'],
            'sms_from_number' => $from,
        ]);
    }

    public function updateSmsUnitCost(int $price): void
    {
        $this->ensureRow();
        $this->pdo->prepare('UPDATE center_settings SET sms_unit_cost = :price WHERE id = 1')
            ->execute(['price' => max(0, $price)]);
    }

    public function storeSmsLiveStats(?int $credit, ?int $price): void
    {
        $this->ensureRow();
        $sets = ['sms_live_synced_at = :synced_at'];
        $params = ['synced_at' => JalaliDate::todayParts()['formatted']];
        if ($credit !== null) {
            $sets[] = 'sms_panel_credit = :credit';
            $params['credit'] = max(0, $credit);
        }
        if ($price !== null) {
            $sets[] = 'sms_unit_cost = :price';
            $params['price'] = max(0, $price);
        }
        $this->pdo->prepare('UPDATE center_settings SET ' . implode(', ', $sets) . ' WHERE id = 1')
            ->execute($params);
    }

    public function markSmsHistorySynced(): void
    {
        $this->ensureRow();
        $this->pdo->prepare('UPDATE center_settings SET sms_history_synced_at = :synced_at WHERE id = 1')
            ->execute(['synced_at' => JalaliDate::todayParts()['formatted']]);
    }

    /**
     * @return array<string, string>
     */
    private function decodeWorkflowTemplates(string $json): array
    {
        $templates = self::WORKFLOW_TEMPLATE_DEFAULTS;
        if ($json === '') {
            return $templates;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $templates;
        }
        foreach (self::WORKFLOW_TEMPLATE_DEFAULTS as $key => $default) {
            if (array_key_exists($key, $decoded)) {
                $templates[$key] = trim((string) $decoded[$key]);
            }
        }

        return $templates;
    }

    /**
     * @param array<string, mixed>|string $templates
     */
    private function encodeWorkflowTemplates(array|string $templates): string
    {
        if (is_string($templates)) {
            return trim($templates);
        }
        $normalized = [];
        foreach (self::WORKFLOW_TEMPLATE_DEFAULTS as $key => $default) {
            $normalized[$key] = trim((string) ($templates[$key] ?? $default));
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    private function decodeLineNumbers(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded)))
            : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function smsPasswordPlain(array $row): string
    {
        $stored = (string) ($row['sms_password'] ?? '');
        if ($stored === '') {
            return '';
        }
        if (!app_configured()) {
            return $stored;
        }

        return SecretVault::decrypt($stored, app_config());
    }

    private function ensureRow(): void
    {
        $exists = (int) $this->pdo->query('SELECT COUNT(*) FROM center_settings WHERE id = 1')->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $defaults = self::DEFAULTS;
        $defaults['updated_at'] = JalaliDate::todayParts()['formatted'];
        $statement = $this->pdo->prepare(
            'INSERT INTO center_settings (id, bank_name, account_holder, account_number, card_number, sheba, payment_guide, updated_at)
             VALUES (1, :bank_name, :account_holder, :account_number, :card_number, :sheba, :payment_guide, :updated_at)'
        );
        $statement->execute($defaults);
    }
}
