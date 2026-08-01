<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

require_auth();
$pdo = require_database();

$teamId = (int) ($_GET['id'] ?? 0);
if (Access::isTeam()) {
    $teamId = Access::scopedTeamId() ?? 0;
}
if ($teamId <= 0) {
    http_response_code(422);
    echo 'نهاد معتبر نیست.';
    exit;
}
$scopedTeamId = Access::scopedTeamId();
if ($scopedTeamId !== null && $scopedTeamId !== $teamId) {
    http_response_code(403);
    echo 'دسترسی به این نهاد مجاز نیست.';
    exit;
}

try {
    $profile = (new Repository($pdo))->teamProfile($teamId);
} catch (Throwable $error) {
    http_response_code(400);
    echo htmlspecialchars($error->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

$team = $profile['team'] ?? [];
$summary = $profile['summary'] ?? [];
$docs = new ContractDocuments($pdo);
$docOverview = $docs->teamOverview($teamId);
$docsByYear = [];
foreach ($docOverview['years'] as $item) {
    $docsByYear[(string) ($item['fiscal_year'] ?? '')] = $item;
}
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/profile-print.css') ?: time(),
    (int) Brand::version()
);
$entityLabels = [
    'team' => 'تیم',
    'company' => 'شرکت',
    'student' => 'دانشجو',
];
$entityLabel = $entityLabels[(string) ($team['entity_type'] ?? '')] ?? 'نهاد';
$today = JalaliDate::todayParts()['formatted'];

$fmtMoney = static function (mixed $value): string {
    $n = (int) $value;
    return number_format($n) . ' ریال';
};
$usageLabels = [
    'formal' => 'رسمی',
    'informal' => 'موقت',
    'mixed' => 'ترکیبی',
];
$approvalLabels = [
    'approved' => 'تأیید‌شده',
    'pending' => 'در انتظار',
    'rejected' => 'رد‌شده',
];
$paymentStatusLabels = [
    'approved' => 'تأیید‌شده',
    'pending' => 'در انتظار',
    'rejected' => 'رد‌شده',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>پرینت پروفایل — <?= e((string) ($team['name'] ?? 'نهاد')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/profile-print.css?v=<?= e($assetVer) ?>" />
</head>
<body>
  <div class="print-toolbar no-print">
    <div>
      <h1>پرینت پروفایل نهاد</h1>
      <p><?= e((string) ($team['name'] ?? '')) ?> — ابعاد A4</p>
    </div>
    <div class="print-toolbar-actions">
      <button type="button" onclick="window.print()">چاپ</button>
      <button type="button" class="ghost" onclick="if (window.history.length > 1) history.back(); else window.close();">بازگشت</button>
    </div>
  </div>

  <main class="sheet">
    <header class="sheet-header">
      <div>
        <p class="eyebrow">مرکز نوآوری مکانیک · مکینو</p>
        <h1><?= e((string) ($team['name'] ?? 'نهاد')) ?></h1>
        <p class="meta"><?= e($entityLabel) ?> · کد <?= e((string) ($team['entity_code'] ?? '—')) ?></p>
      </div>
      <div class="sheet-stamp">
        <strong>پروفایل نهاد</strong>
        <span>تاریخ چاپ: <?= e($today) ?></span>
      </div>
    </header>

    <section class="block">
      <h2>اطلاعات کلی</h2>
      <div class="info-grid">
        <div><span>مسئول</span><strong><?= e((string) ($team['leader'] ?? '—')) ?></strong></div>
        <div><span>تماس</span><strong><?= e((string) ($team['phone'] ?? '—')) ?></strong></div>
        <div><span>شروع قرارداد (کلی)</span><strong><?= e((string) ($team['contract_start'] ?? '—')) ?></strong></div>
        <div><span>پایان قرارداد (کلی)</span><strong><?= e((string) ($team['contract_end'] ?? '—')) ?></strong></div>
        <div><span>جمع شارژ</span><strong><?= e($fmtMoney($summary['charge_total'] ?? 0)) ?></strong></div>
        <div><span>پرداخت‌شده</span><strong><?= e($fmtMoney($summary['paid_total'] ?? 0)) ?></strong></div>
        <div><span>مانده بدهی</span><strong><?= e($fmtMoney($summary['debt_total'] ?? 0)) ?></strong></div>
        <div><span>تاریخ عضویت</span><strong><?= e((string) ($team['joined_at'] ?? '—')) ?></strong></div>
      </div>
      <?php if (!empty($team['notes'])): ?>
        <p class="notes"><?= e((string) $team['notes']) ?></p>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>قراردادهای سالانه</h2>
      <?php if (empty($profile['contracts'])): ?>
        <p class="empty">قراردادی ثبت نشده است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>سال</th>
              <th>شروع</th>
              <th>پایان</th>
              <th>مبلغ رسمی</th>
              <th>پیوست‌ها</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($profile['contracts'] as $contract): ?>
              <?php
                $year = (string) ($contract['fiscal_year'] ?? '');
                $bundle = $docsByYear[$year] ?? [];
                $membership = $bundle['files']['membership']['original_name'] ?? '—';
                $settlement = $bundle['files']['settlement']['original_name'] ?? '—';
              ?>
              <tr>
                <td><?= e($year) ?></td>
                <td><?= e((string) ($contract['contract_start'] ?? '—')) ?></td>
                <td><?= e((string) ($contract['contract_end'] ?? '—')) ?></td>
                <td><?= e($fmtMoney($contract['formal_contract_amount'] ?? 0)) ?></td>
                <td class="files-cell">عضویت: <?= e((string) $membership) ?><br />استقرار: <?= e((string) $settlement) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>میزها و تخصیص</h2>
      <?php if (empty($profile['desk_assignments'])): ?>
        <p class="empty">تخصیص میزی ثبت نشده است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>سال</th><th>میز</th><th>نوع</th><th>بازه</th><th>یادداشت</th></tr>
          </thead>
          <tbody>
            <?php foreach ($profile['desk_assignments'] as $row): ?>
              <tr>
                <td><?= e((string) ($row['fiscal_year'] ?? '—')) ?></td>
                <td><?= e((string) ($row['desk_number'] ?? '—')) ?></td>
                <td><?= e($usageLabels[(string) ($row['usage_type'] ?? '')] ?? (string) ($row['usage_type'] ?? '—')) ?></td>
                <td><?= e((string) ($row['assignment_period'] ?? '—')) ?></td>
                <td><?= e((string) ($row['notes'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>اعضا</h2>
      <?php if (empty($profile['members'])): ?>
        <p class="empty">عضوی ثبت نشده است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>کد</th><th>نام</th><th>تماس</th><th>کدملی</th><th>وضعیت</th></tr>
          </thead>
          <tbody>
            <?php foreach ($profile['members'] as $row): ?>
              <tr>
                <td><?= e((string) ($row['member_code'] ?? '—')) ?></td>
                <td><?= e((string) ($row['full_name'] ?? '—')) ?></td>
                <td><?= e((string) ($row['phone'] ?? '—')) ?></td>
                <td><?= e((string) ($row['national_id'] ?? '—')) ?></td>
                <td><?= e($approvalLabels[(string) ($row['approval_status'] ?? '')] ?? (string) ($row['approval_status'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>کمدها</h2>
      <?php if (empty($profile['lockers'])): ?>
        <p class="empty">کمدی تخصیص نیافته است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>شماره کمد</th><th>وضعیت</th><th>تاریخ تحویل</th><th>کلید</th></tr>
          </thead>
          <tbody>
            <?php foreach ($profile['lockers'] as $row): ?>
              <tr>
                <td><?= e((string) ($row['locker_number'] ?? '—')) ?></td>
                <td><?= e((string) ($row['status'] ?? '—')) ?></td>
                <td><?= e((string) ($row['delivered_at'] ?? '—')) ?></td>
                <td><?= e((string) ($row['key_number'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>خلاصه مالی ماهانه</h2>
      <?php if (empty($profile['charges'])): ?>
        <p class="empty">شارژی ثبت نشده است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>سال</th><th>ماه</th><th>شارژ</th><th>اجاره</th><th>جمع</th></tr>
          </thead>
          <tbody>
            <?php foreach ($profile['charges'] as $row): ?>
              <tr>
                <td><?= e((string) ($row['fiscal_year'] ?? '—')) ?></td>
                <td><?= e((string) ($row['month_name'] ?? $row['month_index'] ?? '—')) ?></td>
                <td><?= e($fmtMoney($row['charge_amount'] ?? 0)) ?></td>
                <td><?= e($fmtMoney($row['rent_amount'] ?? 0)) ?></td>
                <td><?= e($fmtMoney($row['amount'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="block">
      <h2>دریافت‌های تأیید‌شده</h2>
      <?php
        $approvedPayments = array_values(array_filter(
            $profile['payments'] ?? [],
            static fn (array $row): bool => (string) ($row['payment_status'] ?? '') === 'approved'
                && (int) ($row['confirmed'] ?? 0) === 1
        ));
      ?>
      <?php if ($approvedPayments === []): ?>
        <p class="empty">پرداخت تأیید‌شده‌ای ثبت نشده است.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>تاریخ</th><th>سال</th><th>ماه</th><th>مبلغ</th><th>وضعیت</th></tr>
          </thead>
          <tbody>
            <?php foreach ($approvedPayments as $row): ?>
              <tr>
                <td><?= e((string) ($row['tx_date'] ?? '—')) ?></td>
                <td><?= e((string) ($row['fiscal_year'] ?? '—')) ?></td>
                <td><?= e((string) ($row['month_name'] ?? $row['month_index'] ?? '—')) ?></td>
                <td><?= e($fmtMoney($row['amount'] ?? 0)) ?></td>
                <td><?= e($paymentStatusLabels[(string) ($row['payment_status'] ?? '')] ?? 'تأیید‌شده') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <footer class="sheet-footer">
      <span>مرکز نوآوری مکانیک</span>
      <span>سند پروفایل نهاد — فقط برای استفاده داخلی</span>
    </footer>
  </main>
</body>
</html>
