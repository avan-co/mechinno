<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

require_auth();
Access::requireAdminHtml();
$pdo = require_database();

$filters = [
    'type' => (string) ($_GET['type'] ?? 'full'),
    'period' => (string) ($_GET['period'] ?? 'annual'),
    'fiscal_year' => (string) ($_GET['fiscal_year'] ?? ''),
    'month' => (int) ($_GET['month'] ?? 0),
    'quarter' => (int) ($_GET['quarter'] ?? 0),
    'month_from' => (int) ($_GET['month_from'] ?? 0),
    'month_to' => (int) ($_GET['month_to'] ?? 0),
    'team_id' => (int) ($_GET['team_id'] ?? 0),
];
foreach (['month', 'quarter', 'month_from', 'month_to'] as $key) {
    if (($filters[$key] ?? 0) <= 0) {
        unset($filters[$key]);
    }
}
if ($filters['fiscal_year'] === '') {
    unset($filters['fiscal_year']);
}

try {
    $data = (new ReportBuilder($pdo))->build($filters);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$meta = $data['meta'];
$sections = $meta['sections'] ?? [];
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/report.css'),
    (int) Brand::version()
);

$query = http_build_query(array_filter([
    'type' => $meta['type'] ?? '',
    'period' => $meta['period'] ?? '',
    'fiscal_year' => $meta['fiscal_year'] ?? '',
    'month' => ($meta['month_from'] ?? 0) === ($meta['month_to'] ?? 0) ? ($meta['month_from'] ?? null) : null,
    'quarter' => ($meta['period'] ?? '') === 'quarterly' ? (int) ceil(((int) ($meta['month_from'] ?? 1)) / 3) : null,
    'month_from' => ($meta['period'] ?? '') === 'custom' ? ($meta['month_from'] ?? null) : null,
    'month_to' => ($meta['period'] ?? '') === 'custom' ? ($meta['month_to'] ?? null) : null,
    'team_id' => ((int) ($meta['team_id'] ?? 0)) > 0 ? (int) $meta['team_id'] : null,
], static fn ($value): bool => $value !== null && $value !== ''));

$excelUrl = 'export.php?' . http_build_query(array_filter([
    'report' => match ((string) ($meta['type'] ?? 'full')) {
        'finance', 'transactions' => 'transactions',
        'charges' => 'charges',
        'debts' => 'debts',
        'teams' => 'teams',
        'members' => 'members',
        'desks' => 'desks',
        'lockers' => 'lockers',
        default => 'all',
    },
    'fiscal_year' => $meta['fiscal_year'] ?? null,
    'month_from' => $meta['month_from'] ?? null,
    'month_to' => $meta['month_to'] ?? null,
    'team_id' => ((int) ($meta['team_id'] ?? 0)) > 0 ? (int) $meta['team_id'] : null,
], static fn ($value): bool => $value !== null && $value !== ''));

$statusClass = static function (?string $status): string {
    return match ($status) {
        'پرداخت‌شده' => 'status-paid',
        'بدهکار به مرکز' => 'status-debt',
        'ناقص' => 'status-partial',
        default => '',
    };
};
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e((string) ($meta['title'] ?? 'گزارش')) ?></title>
    <?= Brand::headTags() ?>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/report.css?v=<?= e($assetVer) ?>" />
  </head>
  <body>
    <header class="report-toolbar no-print">
      <h1>پیش‌نمایش گزارش — <?= e((string) ($meta['type_label'] ?? '')) ?></h1>
      <div class="report-actions">
        <a class="btn btn--ghost" href="index.php#reports">بازگشت به گزارش‌گیری</a>
        <a class="btn btn--ghost" href="<?= e($excelUrl) ?>">دانلود Excel</a>
        <button class="btn" type="button" onclick="window.print()">چاپ / ذخیره PDF</button>
      </div>
    </header>

    <article class="report-doc">
      <header class="report-cover">
        <div class="report-brand">
          <div class="report-brand-text">
            <strong><?= e((string) ($meta['title'] ?? 'گزارش')) ?></strong>
            <small><?= e((string) ($meta['subtitle'] ?? '')) ?></small>
          </div>
          <?= Brand::mark('report') ?>
        </div>
        <div class="report-meta">
          <span>نوع: <?= e((string) ($meta['type_label'] ?? '')) ?></span>
          <span>بازه: <?= e((string) ($meta['period_title'] ?? '')) ?></span>
          <span>نهاد: <?= e((string) ($meta['team_name'] ?? 'همه نهادها')) ?></span>
          <span>تاریخ گزارش: <?= e((string) ($meta['generated_at'] ?? '')) ?></span>
          <span>ساعت: <?= e((string) ($meta['generated_time'] ?? '')) ?></span>
        </div>
      </header>

      <?php if (in_array('kpis', $sections, true) && !empty($data['kpis'])): ?>
      <section class="report-section">
        <h2 class="section-title">خلاصه شاخص‌ها</h2>
        <div class="kpi-grid">
          <?php foreach ($data['kpis'] as $kpi): ?>
            <?php
              $tone = (string) ($kpi['tone'] ?? '');
              $toneClass = $tone === 'danger' ? 'kpi--danger' : ($tone === 'success' ? 'kpi--success' : '');
              $value = $kpi['value'] ?? '';
              $display = is_numeric($value) ? ReportData::money($value) : ReportData::cell($value);
            ?>
            <div class="kpi <?= e($toneClass) ?>">
              <span class="kpi-label"><?= e((string) ($kpi['label'] ?? '')) ?></span>
              <span class="kpi-value"><?= e((string) $display) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('finance_summary', $sections, true) && !empty($data['finance_summary'])): ?>
      <?php $finance = $data['finance_summary']; ?>
      <section class="report-section">
        <h2 class="section-title">خلاصه مالی بازه</h2>
        <div class="table-scroll">
          <table class="data-table">
            <tbody>
              <tr><th>واریز نهادها</th><td class="num"><?= ReportData::money($finance['deposits'] ?? 0) ?></td></tr>
              <tr><th>درآمد دستی</th><td class="num"><?= ReportData::money($finance['manual_income'] ?? 0) ?></td></tr>
              <tr><th>جمع درآمد</th><td class="num"><?= ReportData::money($finance['income_total'] ?? 0) ?></td></tr>
              <tr><th>هزینه‌ها</th><td class="num"><?= ReportData::money($finance['expense_total'] ?? 0) ?></td></tr>
              <tr><th>خالص نقدی</th><td class="num"><?= ReportData::money($finance['net'] ?? 0) ?></td></tr>
              <tr><th>جمع شارژ</th><td class="num"><?= ReportData::money($finance['charge_total'] ?? 0) ?></td></tr>
              <tr><th>مانده طلب</th><td class="num"><?= ReportData::money($finance['debt_total'] ?? 0) ?></td></tr>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('monthly_breakdown', $sections, true) && !empty($data['monthly_breakdown'])): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">تفکیک ماهانه</h2>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr>
                <th>ماه</th><th>واریز</th><th>درآمد دستی</th><th>هزینه</th><th>خالص</th><th>شارژ</th><th>مانده طلب</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data['monthly_breakdown'] as $row): ?>
                <tr>
                  <td><?= e((string) ($row['month_name'] ?? '')) ?></td>
                  <td class="num"><?= ReportData::money($row['deposits'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['manual_income'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['expense_total'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['net'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['charge_total'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['debt_total'] ?? 0) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('debts', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">مطالبات و بدهی‌ها</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['debts'] ?? [])) ?> ردیف</p>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr><th>نهاد</th><th>سال</th><th>ماه</th><th>مبلغ مستحق</th><th>دریافت‌شده</th><th>مانده</th><th>وضعیت</th></tr>
            </thead>
            <tbody>
              <?php if (($data['debts'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="7">ردیفی در این بازه نیست.</td></tr>
              <?php else: ?>
                <?php foreach ($data['debts'] as $row): ?>
                  <tr>
                    <td><?= e(ReportData::cell($row['team_name'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['month_name'] ?? null)) ?></td>
                    <td class="num"><?= ReportData::money($row['amount_due'] ?? 0) ?></td>
                    <td class="num"><?= ReportData::money($row['amount_paid'] ?? 0) ?></td>
                    <td class="num"><?= ReportData::money($row['amount_remaining'] ?? 0) ?></td>
                    <td class="<?= e($statusClass($row['status'] ?? null)) ?>"><?= e(ReportData::cell($row['status'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('charges', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">شارژ ماهانه</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['charges'] ?? [])) ?> ردیف</p>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr><th>نهاد</th><th>سال</th><th>ماه</th><th>شارژ</th><th>اجاره</th><th>جمع</th><th>یادداشت</th></tr>
            </thead>
            <tbody>
              <?php if (($data['charges'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="7">شارژی در این بازه نیست.</td></tr>
              <?php else: ?>
                <?php foreach ($data['charges'] as $row): ?>
                  <tr>
                    <td><?= e(ReportData::cell($row['team_name'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['month_name'] ?? null)) ?></td>
                    <td class="num"><?= ReportData::money($row['charge_amount'] ?? 0) ?></td>
                    <td class="num"><?= ReportData::money($row['rent_amount'] ?? 0) ?></td>
                    <td class="num"><?= ReportData::money($row['amount'] ?? 0) ?></td>
                    <td><?= e(ReportData::cell($row['note'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('transactions', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">تراکنش‌های مالی</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['transactions'] ?? [])) ?> تراکنش</p>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr><th>تاریخ</th><th>شرح</th><th>مبلغ</th><th>دسته</th><th>نهاد</th><th>سال</th><th>ماه</th></tr>
            </thead>
            <tbody>
              <?php if (($data['transactions'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="7">تراکنشی در این بازه نیست.</td></tr>
              <?php else: ?>
                <?php foreach ($data['transactions'] as $row): ?>
                  <tr>
                    <td><?= e(ReportData::cell($row['tx_date'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['description'] ?? null)) ?></td>
                    <td class="num"><?= ReportData::money($row['amount'] ?? 0) ?></td>
                    <td><?= e(ReportData::cell($row['category_label'] ?? $row['category'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['team_name'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['month_name'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('teams', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">نهادها</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['teams'] ?? [])) ?> نهاد</p>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr><th>کد</th><th>نوع</th><th>نام</th><th>مسئول</th><th>تماس</th><th>میز</th><th>شروع قرارداد</th><th>پایان قرارداد</th></tr>
            </thead>
            <tbody>
              <?php if (($data['teams'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="8">نهادی ثبت نشده است.</td></tr>
              <?php else: ?>
                <?php foreach ($data['teams'] as $row): ?>
                  <tr>
                    <td><?= e(ReportData::cell($row['entity_code'] ?? null)) ?></td>
                    <td><?= e(ReportData::entityLabel($row['entity_type'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['name'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['leader'] ?? null)) ?></td>
                    <td><?= e(ReportData::plain($row['phone'] ?? null)) ?></td>
                    <td class="num"><?= ReportData::money($row['desk_count'] ?? 0) ?></td>
                    <td><?= e(ReportData::cell($row['contract_start'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['contract_end'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('members', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">اعضا</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['members'] ?? [])) ?> عضو</p>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead>
              <tr><th>کد عضو</th><th>نام</th><th>نهاد</th><th>تماس</th><th>کدملی</th><th>تردد</th></tr>
            </thead>
            <tbody>
              <?php if (($data['members'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="6">عضوی ثبت نشده است.</td></tr>
              <?php else: ?>
                <?php foreach ($data['members'] as $row): ?>
                  <tr>
                    <td><?= e(ReportData::cell($row['member_code'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['full_name'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['team_label'] ?? null)) ?></td>
                    <td><?= e(ReportData::plain($row['phone'] ?? null)) ?></td>
                    <td><?= e(ReportData::plain($row['national_id'] ?? null)) ?></td>
                    <td><?= e(ReportData::wantsAccessLabel($row['wants_access'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('desks', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">میزها</h2>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead><tr><th>شماره</th><th>نهاد</th><th>نوع</th><th>توضیحات</th></tr></thead>
            <tbody>
              <?php foreach (($data['desks'] ?? []) as $row): ?>
                <tr>
                  <td class="num"><?= ReportData::money($row['number'] ?? 0) ?></td>
                  <td><?= e(ReportData::cell($row['team_name'] ?? 'آزاد')) ?></td>
                  <td><?= e(ReportData::usageLabel($row['usage_type'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['notes'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (in_array('lockers', $sections, true)): ?>
      <section class="report-section report-section--break">
        <h2 class="section-title">کمدها</h2>
        <div class="table-scroll">
          <table class="data-table data-table--wide">
            <thead><tr><th>شماره</th><th>وضعیت</th><th>نهاد</th><th>تحویل</th></tr></thead>
            <tbody>
              <?php if (($data['lockers'] ?? []) === []): ?>
                <tr class="empty-row"><td colspan="4">کمدی ثبت نشده است.</td></tr>
              <?php else: ?>
                <?php foreach ($data['lockers'] as $row): ?>
                  <tr>
                    <td class="num"><?= e(ReportData::cell($row['locker_number'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['status'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['team_label'] ?? null)) ?></td>
                    <td><?= e(ReportData::cell($row['delivered_at'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <footer class="report-footer">
        گزارش تولیدشده توسط پنل Mechinno — <?= e((string) ($meta['period_title'] ?? '')) ?> — <?= e((string) ($meta['generated_at'] ?? '')) ?> <?= e((string) ($meta['generated_time'] ?? '')) ?>
      </footer>
    </article>
  </body>
</html>
