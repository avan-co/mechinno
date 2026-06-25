<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

require_auth();
$pdo = require_database();
$data = (new ReportData($pdo))->build();
$meta = $data['meta'];
$summary = $data['summary'];
$cards = $summary['cards'];
$month = $summary['current_month'];
$assetVer = (string) filemtime(__DIR__ . '/assets/report.css');

$statusClass = static function (?string $status): string {
    return match ($status) {
        'پرداخت‌شده' => 'status-paid',
        'بدهکار' => 'status-debt',
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
    <title><?= e($meta['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/report.css?v=<?= e($assetVer) ?>" />
  </head>
  <body>
    <header class="report-toolbar no-print">
      <h1>پیش‌نمایش گزارش چاپی A4</h1>
      <div class="report-actions">
        <a class="btn btn--ghost" href="index.php">بازگشت به پنل</a>
        <a class="btn btn--ghost" href="export.php?report=all">دانلود Excel</a>
        <button class="btn" type="button" onclick="window.print()">چاپ / ذخیره PDF</button>
      </div>
    </header>

    <article class="report-doc">
      <header class="report-cover">
        <div class="report-brand">
          <div class="report-brand-text">
            <strong><?= e($meta['title']) ?></strong>
            <small><?= e($meta['subtitle']) ?></small>
          </div>
          <span class="report-brand-mark">M</span>
        </div>
        <div class="report-meta">
          <span>تاریخ گزارش: <?= e($meta['generated_at']) ?></span>
          <span>ساعت: <?= e($meta['generated_time']) ?></span>
          <span>ماه جاری: <?= e($month['month_name'] ?? '') ?> <?= e($month['fiscal_year'] ?? '') ?></span>
        </div>
      </header>

      <section class="report-section">
        <h2 class="section-title">خلاصه مدیریتی</h2>
        <div class="kpi-grid">
          <div class="kpi"><span class="kpi-label">نهادها</span><span class="kpi-value"><?= ReportData::money($cards['teams']) ?></span></div>
          <div class="kpi"><span class="kpi-label">اعضا</span><span class="kpi-value"><?= ReportData::money($cards['members']) ?></span></div>
          <div class="kpi"><span class="kpi-label">میز اشغال</span><span class="kpi-value"><?= ReportData::money($cards['desks_occupied']) ?> / 24</span></div>
          <div class="kpi"><span class="kpi-label">کمدها</span><span class="kpi-value"><?= ReportData::money($cards['lockers']) ?></span></div>
          <div class="kpi kpi--success"><span class="kpi-label">دریافتی (ریال)</span><span class="kpi-value"><?= ReportData::money($cards['income_total']) ?></span></div>
          <div class="kpi"><span class="kpi-label">هزینه (ریال)</span><span class="kpi-value"><?= ReportData::money($cards['expense_total']) ?></span></div>
          <div class="kpi"><span class="kpi-label">جمع شارژ (ریال)</span><span class="kpi-value"><?= ReportData::money($cards['charge_total']) ?></span></div>
          <div class="kpi"><span class="kpi-label">واریز تیم (ریال)</span><span class="kpi-value"><?= ReportData::money($cards['paid_total']) ?></span></div>
          <div class="kpi kpi--danger"><span class="kpi-label">بدهی کل (ریال)</span><span class="kpi-value"><?= ReportData::money($cards['debt_total']) ?></span></div>
          <div class="kpi"><span class="kpi-label">شارژ ماه جاری</span><span class="kpi-value"><?= ReportData::money($month['charge_total'] ?? 0) ?></span></div>
          <div class="kpi"><span class="kpi-label">واریز ماه جاری</span><span class="kpi-value"><?= ReportData::money($month['paid_total'] ?? 0) ?></span></div>
          <div class="kpi kpi--danger"><span class="kpi-label">بدهی ماه جاری</span><span class="kpi-value"><?= ReportData::money($month['debt_total'] ?? 0) ?></span></div>
        </div>
      </section>

      <section class="report-section report-section--break">
        <h2 class="section-title">نهادها (تیم / شرکت / دانشجو)</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['teams'])) ?> نهاد</p>
        <table class="data-table">
          <thead>
            <tr>
              <th>کد</th><th>نوع</th><th>نام</th><th>مسئول</th><th>تماس</th><th>میز</th><th>صندلی غیررسمی</th><th>عضویت</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($data['teams'] === []): ?>
              <tr class="empty-row"><td colspan="8">نهادی ثبت نشده است.</td></tr>
            <?php else: ?>
              <?php foreach ($data['teams'] as $row): ?>
                <tr>
                  <td><?= e(ReportData::cell($row['entity_code'] ?? null)) ?></td>
                  <td><?= e(ReportData::entityLabel($row['entity_type'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['name'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['leader'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['phone'] ?? null)) ?></td>
                  <td class="num"><?= ReportData::money($row['desk_count'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['informal_seats'] ?? 0) ?></td>
                  <td><?= e(ReportData::cell($row['joined_at'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="report-section report-section--break">
        <h2 class="section-title">اعضا</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['members'])) ?> عضو — میزها در سطح نهاد تخصیص یافته‌اند.</p>
        <table class="data-table">
          <thead>
            <tr>
              <th>کد عضو</th><th>نام</th><th>نهاد</th><th>نوع نهاد</th><th>میزهای نهاد</th><th>کد تردد</th><th>کمد</th><th>تماس</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($data['members'] === []): ?>
              <tr class="empty-row"><td colspan="8">عضوی ثبت نشده است.</td></tr>
            <?php else: ?>
              <?php foreach ($data['members'] as $row): ?>
                <tr>
                  <td><?= e(ReportData::cell($row['member_code'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['full_name'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['team_label'] ?? null)) ?></td>
                  <td><?= e(ReportData::entityLabel($row['entity_type'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['desk_numbers'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['access_code'] ?? null)) ?></td>
                  <td class="num"><?= e(ReportData::cell($row['locker_number'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['phone'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="report-section report-section--break">
        <div class="two-col">
          <div>
            <h2 class="section-title">میزها (۲۴ میز)</h2>
            <table class="data-table">
              <thead>
                <tr><th>شماره</th><th>نهاد</th><th>نوع</th><th>ر</th><th>غ</th></tr>
              </thead>
              <tbody>
                <?php foreach ($data['desks'] as $row): ?>
                  <tr>
                    <td class="num"><?= ReportData::money($row['number'] ?? 0) ?></td>
                    <td><?= e(ReportData::cell($row['team_name'] ?? 'آزاد')) ?></td>
                    <td><?= e(ReportData::usageLabel($row['usage_type'] ?? null)) ?></td>
                    <td class="num"><?= ReportData::money($row['formal_seats'] ?? 0) ?></td>
                    <td class="num"><?= ReportData::money($row['informal_seats'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <h2 class="section-title">کمدها</h2>
            <table class="data-table">
              <thead>
                <tr><th>شماره</th><th>وضعیت</th><th>نهاد</th><th>عضو</th><th>تحویل</th></tr>
              </thead>
              <tbody>
                <?php if ($data['lockers'] === []): ?>
                  <tr class="empty-row"><td colspan="5">کمدی ثبت نشده است.</td></tr>
                <?php else: ?>
                  <?php foreach ($data['lockers'] as $row): ?>
                    <tr>
                      <td class="num"><?= e(ReportData::cell($row['locker_number'] ?? null)) ?></td>
                      <td><?= e(ReportData::cell($row['status'] ?? null)) ?></td>
                      <td><?= e(ReportData::cell($row['team_label'] ?? null)) ?></td>
                      <td><?= e(ReportData::cell($row['member_name'] ?? null)) ?></td>
                      <td><?= e(ReportData::cell($row['delivered_at'] ?? null)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="report-section report-section--break">
        <h2 class="section-title">نرخ‌های سالانه</h2>
        <table class="data-table">
          <thead>
            <tr><th>سال</th><th>عنوان</th><th>نرخ شارژ/میز</th><th>نرخ اجاره غیررسمی</th><th>تاریخ اثر</th><th>توضیحات</th></tr>
          </thead>
          <tbody>
            <?php if ($data['rate_settings'] === []): ?>
              <tr class="empty-row"><td colspan="6">نرخی تعریف نشده است.</td></tr>
            <?php else: ?>
              <?php foreach ($data['rate_settings'] as $row): ?>
                <tr>
                  <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['title'] ?? null)) ?></td>
                  <td class="num"><?= ReportData::money($row['charge_rate'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['informal_rent_rate'] ?? 0) ?></td>
                  <td><?= e(ReportData::cell($row['effective_from'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['notes'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="report-section">
        <h2 class="section-title">شارژ ماهانه و وضعیت پرداخت</h2>
        <table class="data-table">
          <thead>
            <tr><th>نهاد</th><th>سال</th><th>ماه</th><th>شارژ</th><th>اجاره</th><th>جمع بدهی</th><th>پرداخت</th><th>وضعیت</th></tr>
          </thead>
          <tbody>
            <?php if ($data['debts'] === []): ?>
              <tr class="empty-row"><td colspan="8">رکورد شارژی ثبت نشده است.</td></tr>
            <?php else: ?>
              <?php foreach ($data['debts'] as $row): ?>
                <tr>
                  <td><?= e(ReportData::cell($row['team_name'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['month_name'] ?? null)) ?></td>
                  <td class="num"><?= ReportData::money($row['charge_amount'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['rent_amount'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['amount_due'] ?? 0) ?></td>
                  <td class="num"><?= ReportData::money($row['amount_paid'] ?? 0) ?></td>
                  <td class="<?= e($statusClass($row['status'] ?? null)) ?>"><?= e(ReportData::cell($row['status'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="report-section report-section--break">
        <h2 class="section-title">تراکنش‌های مالی</h2>
        <p class="section-note">تعداد: <?= ReportData::money(count($data['transactions'])) ?> تراکنش</p>
        <table class="data-table">
          <thead>
            <tr><th>تاریخ</th><th>شرح</th><th>مبلغ (ریال)</th><th>دسته</th><th>نهاد</th><th>سال</th><th>ماه</th></tr>
          </thead>
          <tbody>
            <?php if ($data['transactions'] === []): ?>
              <tr class="empty-row"><td colspan="7">تراکنشی ثبت نشده است.</td></tr>
            <?php else: ?>
              <?php foreach ($data['transactions'] as $row): ?>
                <tr>
                  <td><?= e(ReportData::cell($row['tx_date'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['description'] ?? null)) ?></td>
                  <td class="num"><?= ReportData::money($row['amount'] ?? 0) ?></td>
                  <td><?= e(ReportData::cell($row['category'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['team_name'] ?? null)) ?></td>
                  <td><?= e(ReportData::cell($row['fiscal_year'] ?? null)) ?></td>
                  <td class="num"><?= e(ReportData::cell($row['month_index'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <footer class="report-footer">
        گزارش تولیدشده توسط پنل Mechinno — <?= e($meta['generated_at']) ?> <?= e($meta['generated_time']) ?>
      </footer>
    </article>
  </body>
</html>
