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
        $statement = $this->pdo->query(
            'SELECT sms_username, sms_password, sms_from_number, sms_daily_limit, sms_unit_cost, sms_updated_at
             FROM center_settings WHERE id = 1'
        );
        $row = $statement->fetch() ?: [];

        return [
            'sms_username' => (string) ($row['sms_username'] ?? ''),
            'sms_password_set' => trim((string) ($row['sms_password'] ?? '')) !== '',
            'sms_from_number' => (string) ($row['sms_from_number'] ?? ''),
            'sms_daily_limit' => (int) ($row['sms_daily_limit'] ?? 500),
            'sms_unit_cost' => (int) ($row['sms_unit_cost'] ?? 0),
            'sms_updated_at' => (string) ($row['sms_updated_at'] ?? ''),
        ];
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
            'sms_password' => (string) ($row['sms_password'] ?? ''),
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
        $current = $this->pdo->query('SELECT sms_password FROM center_settings WHERE id = 1')->fetch() ?: [];
        $password = trim((string) ($payload['sms_password'] ?? ''));
        if ($password === '') {
            $password = (string) ($current['sms_password'] ?? '');
        }

        $statement = $this->pdo->prepare(
            'UPDATE center_settings SET
                sms_username = :sms_username,
                sms_password = :sms_password,
                sms_from_number = :sms_from_number,
                sms_daily_limit = :sms_daily_limit,
                sms_unit_cost = :sms_unit_cost,
                sms_updated_at = :sms_updated_at
             WHERE id = 1'
        );
        $statement->execute([
            'sms_username' => trim((string) ($payload['sms_username'] ?? '')),
            'sms_password' => $password,
            'sms_from_number' => trim((string) ($payload['sms_from_number'] ?? '')),
            'sms_daily_limit' => max(1, (int) ($payload['sms_daily_limit'] ?? 500)),
            'sms_unit_cost' => max(0, (int) ($payload['sms_unit_cost'] ?? 0)),
            'sms_updated_at' => JalaliDate::todayParts()['formatted'],
        ]);

        return $this->smsSettings();
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
