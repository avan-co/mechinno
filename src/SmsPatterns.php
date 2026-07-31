<?php

declare(strict_types=1);

/**
 * ثبت مرکزی الگوهای ملی‌پیامک (خط خدماتی اشتراکی).
 *
 * پس از تأیید الگو در پنل ملی‌پیامک، body_id هر الگو را در جدول sms_patterns
 * یا از بخش تنظیمات پیامک به‌روز کنید.
 *
 * قوانین ثبت در پنل ملی‌پیامک:
 * - متغیرها به‌صورت {0}، {1}، ... و به ترتیب
 * - متغیر نباید در انتهای متن باشد
 */
final class SmsPatterns
{
    /**
     * @return array<string, array{
     *   body_id:int,
     *   title:string,
     *   panel_text:string,
     *   variables:list<string>,
     *   workflow_key:?string
     * }>
     */
    public static function definitions(): array
    {
        return [
            'charge_reminder' => [
                'body_id' => 287101,
                'title' => 'یادآوری شارژ بدهکاران',
                'panel_text' => 'نهاد {0}؛ بدهی شارژ شما به مبلغ {1} ریال برای ماه‌های {2} ثبت شده است. لطفاً نسبت به پرداخت اقدام فرمایید.',
                'variables' => ['team_name', 'debt_total', 'debt_summary'],
                'workflow_key' => null,
            ],
            'room_pending' => [
                'body_id' => 287102,
                'title' => 'رزرو اتاق — ثبت درخواست',
                'panel_text' => '{0} گرامی؛ درخواست رزرو اتاق {1} برای تاریخ {2} از ساعت {3} تا {4} ثبت شد. کد پیگیری شما {5} است.',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'start_time', 'end_time', 'public_token'],
                'workflow_key' => 'room_pending',
            ],
            'room_approved' => [
                'body_id' => 287103,
                'title' => 'رزرو اتاق — تأیید',
                'panel_text' => '{0} گرامی؛ رزرو اتاق {1} برای تاریخ {2} از ساعت {3} تا {4} تأیید شد. کد پیگیری شما {5} است.',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'start_time', 'end_time', 'public_token'],
                'workflow_key' => 'room_approved',
            ],
            'room_rejected' => [
                'body_id' => 287104,
                'title' => 'رزرو اتاق — رد',
                'panel_text' => '{0} گرامی؛ رزرو اتاق {1} در تاریخ {2} رد شد. علت: {3}.',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'rejection_reason'],
                'workflow_key' => 'room_rejected',
            ],
            'room_cancelled' => [
                'body_id' => 287105,
                'title' => 'رزرو اتاق — لغو',
                'panel_text' => '{0} گرامی؛ رزرو اتاق {1} در تاریخ {2} لغو شد. علت: {3}.',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'cancel_reason'],
                'workflow_key' => 'room_cancelled',
            ],
            'member_approved' => [
                'body_id' => 287106,
                'title' => 'عضو — تأیید عضویت',
                'panel_text' => '{0} گرامی؛ عضویت شما در {1} تأیید شد. کد تردد شما {2} است.',
                'variables' => ['full_name', 'team_name', 'access_code'],
                'workflow_key' => 'member_approved',
            ],
            'member_rejected' => [
                'body_id' => 287107,
                'title' => 'عضو — رد عضویت',
                'panel_text' => '{0} گرامی؛ درخواست عضویت شما در {1} رد شد. علت: {2}.',
                'variables' => ['full_name', 'team_name', 'rejection_reason'],
                'workflow_key' => 'member_rejected',
            ],
            'member_request_approved' => [
                'body_id' => 287108,
                'title' => 'درخواست عضو — تأیید',
                'panel_text' => 'مسئول گرامی؛ درخواست {0} مربوط به عضو {1} در {2} تأیید شد.',
                'variables' => ['request_type_label', 'full_name', 'team_name'],
                'workflow_key' => 'member_request_approved',
            ],
            'member_request_rejected' => [
                'body_id' => 287109,
                'title' => 'درخواست عضو — رد',
                'panel_text' => 'مسئول گرامی؛ درخواست {0} مربوط به عضو {1} در {2} رد شد. علت: {3}.',
                'variables' => ['request_type_label', 'full_name', 'team_name', 'rejection_reason'],
                'workflow_key' => 'member_request_rejected',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sampleVariableValues(): array
    {
        return [
            'team_name' => 'شرکت نمونه',
            'debt_total' => '1,250,000',
            'debt_summary' => 'مرداد و شهریور 1405',
            'booker_name' => 'علی رضایی',
            'room_name' => 'اتاق جلسه الف',
            'reserved_date' => '1405/05/15',
            'start_time' => '10:00',
            'end_time' => '11:30',
            'public_token' => 'MN-876546',
            'rejection_reason' => 'تداخل با رزرو دیگر',
            'cancel_reason' => 'درخواست نهاد',
            'full_name' => 'سارا محمدی',
            'access_code' => '4821',
            'request_type_label' => 'ویرایش',
        ];
    }

    /**
     * @param list<string> $variables
     * @param array<string, scalar|null> $values
     */
    public static function renderPanelText(string $panelText, array $variables, array $values = []): string
    {
        $samples = self::sampleVariableValues();
        $text = $panelText;
        foreach ($variables as $index => $variable) {
            $value = (string) ($values[$variable] ?? $samples[$variable] ?? '—');
            $text = str_replace('{' . $index . '}', $value, $text);
        }

        return $text;
    }

    /**
     * @param array<string, scalar|null> $values
     */
    public static function renderPanelPreview(string $patternKey, array $values = []): string
    {
        $definition = self::definitions()[$patternKey] ?? null;
        if ($definition === null) {
            return '';
        }

        return self::renderPanelText((string) $definition['panel_text'], $definition['variables'], $values);
    }

    public static function panelTextEndsWithVariable(string $panelText): bool
    {
        return preg_match('/\{\d+\}\s*$/u', trim($panelText)) === 1;
    }

    public static function systemTemplate(string $patternKey, ?int $bodyId = null): string
    {
        $definition = self::definitions()[$patternKey] ?? null;
        if ($definition === null) {
            return '';
        }

        $id = $bodyId ?? (int) $definition['body_id'];
        $parts = array_map(
            static fn (string $variable): string => '{' . $variable . '}',
            $definition['variables']
        );

        return $id . '@' . implode('##', $parts) . '##shared';
    }

    public static function chargeTemplate(?int $bodyId = null): string
    {
        return self::systemTemplate('charge_reminder', $bodyId);
    }

    /**
     * @return array<string, string>
     */
    public static function workflowTemplates(?array $bodyIds = null): array
    {
        $templates = [];
        foreach (self::definitions() as $key => $definition) {
            $workflowKey = $definition['workflow_key'] ?? null;
            if ($workflowKey === null) {
                continue;
            }
            $id = $bodyIds[$key] ?? $bodyIds[$workflowKey] ?? null;
            $templates[$workflowKey] = self::systemTemplate($key, $id !== null ? (int) $id : null);
        }

        return $templates;
    }

    /**
     * @return array<string, string>
     */
    public static function workflowTemplateDefaults(): array
    {
        return self::workflowTemplates();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function panelRegistrationGuide(): array
    {
        $rows = [];
        foreach (self::definitions() as $key => $definition) {
            $rows[] = [
                'pattern_key' => $key,
                'workflow_key' => $definition['workflow_key'],
                'body_id' => (int) $definition['body_id'],
                'title' => $definition['title'],
                'panel_text' => $definition['panel_text'],
                'panel_preview' => self::renderPanelPreview($key),
                'panel_valid' => !self::panelTextEndsWithVariable((string) $definition['panel_text']),
                'variables' => $definition['variables'],
                'system_template' => self::systemTemplate($key),
            ];
        }

        return $rows;
    }
}
