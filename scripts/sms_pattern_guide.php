#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Print MeliPayamak pattern registration guide for panel setup.
 *
 * Usage: php scripts/sms_pattern_guide.php
 */

require __DIR__ . '/../src/bootstrap.php';

echo "=== راهنمای ثبت الگو در پنل ملی‌پیامک ===\n\n";
echo "نوع خط: خط خدماتی اشتراکی (shared)\n";
echo "پس از تأیید هر الگو، body_id واقعی را در تنظیمات پیامک جایگزین کنید.\n\n";

foreach (SmsPatterns::panelRegistrationGuide() as $index => $row) {
    $num = $index + 1;
    echo str_repeat('─', 72) . "\n";
    echo "{$num}. {$row['title']}\n";
    echo "   کلید سیستم: {$row['pattern_key']}\n";
    echo "   bodyId پیشنهادی: {$row['body_id']}\n";
    echo "   متغیرها (به ترتیب): " . implode(' → ', $row['variables']) . "\n";
    echo "   متن ثبت در پنل ملی‌پیامک:\n";
    echo '   ' . $row['panel_text'] . "\n";
    echo "   پیش‌نمایش نمونه:\n";
    echo '   ' . ($row['panel_preview'] ?? SmsPatterns::renderPanelPreview((string) $row['pattern_key'])) . "\n";
    echo "   الگوی ذخیره‌شده در سیستم:\n";
    echo '   ' . $row['system_template'] . "\n";
}

echo str_repeat('─', 72) . "\n";
echo "\nتمام.\n";
