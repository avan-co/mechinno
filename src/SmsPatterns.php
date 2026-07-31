<?php

declare(strict_types=1);

/**
 * ثبت مرکزی الگوهای ملی‌پیامک (خط خدماتی اشتراکی).
 *
 * پس از تأیید الگو در پنل ملی‌پیامک، body_id هر الگو را در جدول sms_patterns
 * یا از بخش تنظیمات پیامک به‌روز کنید.
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
                'panel_text' => 'نهاد {0} عزیز؛ مبلغ بدهی شارژ {1} ریال. ماه‌های بدهکار: {2}',
                'variables' => ['team_name', 'debt_total', 'debt_summary'],
                'workflow_key' => null,
            ],
            'room_pending' => [
                'body_id' => 287102,
                'title' => 'رزرو اتاق — ثبت درخواست',
                'panel_text' => '{0} عزیز؛ درخواست رزرو اتاق {1} در تاریخ {2} از ساعت {3} تا {4} ثبت شد. کد پیگیری: {5}',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'start_time', 'end_time', 'public_token'],
                'workflow_key' => 'room_pending',
            ],
            'room_approved' => [
                'body_id' => 287103,
                'title' => 'رزرو اتاق — تأیید',
                'panel_text' => '{0} عزیز؛ رزرو اتاق {1} در {2} ساعت {3} تا {4} تأیید شد. کد پیگیری: {5}',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'start_time', 'end_time', 'public_token'],
                'workflow_key' => 'room_approved',
            ],
            'room_rejected' => [
                'body_id' => 287104,
                'title' => 'رزرو اتاق — رد',
                'panel_text' => '{0} عزیز؛ رزرو اتاق {1} در {2} رد شد.{3}',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'rejection_reason_line'],
                'workflow_key' => 'room_rejected',
            ],
            'room_cancelled' => [
                'body_id' => 287105,
                'title' => 'رزرو اتاق — لغو',
                'panel_text' => '{0} عزیز؛ رزرو اتاق {1} در {2} لغو شد.{3}',
                'variables' => ['booker_name', 'room_name', 'reserved_date', 'cancel_reason_line'],
                'workflow_key' => 'room_cancelled',
            ],
            'member_approved' => [
                'body_id' => 287106,
                'title' => 'عضو — تأیید عضویت',
                'panel_text' => '{0} عزیز؛ عضویت شما در {1} تأیید شد.{2}',
                'variables' => ['full_name', 'team_name', 'access_code_line'],
                'workflow_key' => 'member_approved',
            ],
            'member_rejected' => [
                'body_id' => 287107,
                'title' => 'عضو — رد عضویت',
                'panel_text' => '{0} عزیز؛ درخواست عضویت شما در {1} رد شد.{2}',
                'variables' => ['full_name', 'team_name', 'rejection_reason_line'],
                'workflow_key' => 'member_rejected',
            ],
            'member_request_approved' => [
                'body_id' => 287108,
                'title' => 'درخواست عضو — تأیید',
                'panel_text' => 'مسئول محترم؛ درخواست {0} عضو {1} در {2} تأیید شد.',
                'variables' => ['request_type_label', 'full_name', 'team_name'],
                'workflow_key' => 'member_request_approved',
            ],
            'member_request_rejected' => [
                'body_id' => 287109,
                'title' => 'درخواست عضو — رد',
                'panel_text' => 'مسئول محترم؛ درخواست {0} عضو {1} در {2} رد شد.{3}',
                'variables' => ['request_type_label', 'full_name', 'team_name', 'rejection_reason_line'],
                'workflow_key' => 'member_request_rejected',
            ],
        ];
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
                'variables' => $definition['variables'],
                'system_template' => self::systemTemplate($key),
            ];
        }

        return $rows;
    }
}
