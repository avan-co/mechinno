<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

require_auth();
$pdo = require_database();
if ((int) $pdo->query('SELECT COUNT(*) FROM import_runs')->fetchColumn() === 0) {
    (new Importer($pdo, app_base_path()))->importAll();
}
$repo = new Repository($pdo);
$summary = $repo->summary();
$payments = $repo->resource('team_payments');
$transactions = $repo->resource('transactions');
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <title>گزارش رسمی مالی و تنخواه</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body class="report-body">
    <main class="report-page">
      <header class="report-header">
        <div>
          <h1>گزارش رسمی مالی و تنخواه</h1>
          <p>مرکز نوآوری دانشکده مکانیک</p>
        </div>
        <button class="button no-print" onclick="window.print()">چاپ / ذخیره PDF</button>
      </header>

      <section class="report-cards">
        <div><span>جمع دریافت‌ها</span><strong><?= number_format((int) $summary['cards']['income_total']) ?> ریال</strong></div>
        <div><span>جمع هزینه‌ها</span><strong><?= number_format(abs((int) $summary['cards']['expense_total'])) ?> ریال</strong></div>
        <div><span>بدهی تیم‌ها</span><strong><?= number_format((int) $summary['cards']['debt_total']) ?> ریال</strong></div>
        <div><span>پرداخت ثبت‌شده</span><strong><?= number_format((int) $summary['cards']['paid_total']) ?> ریال</strong></div>
      </section>

      <h2>وضعیت بدهی و پرداخت تیم‌ها</h2>
      <table class="report-table">
        <thead><tr><th>تیم</th><th>سال</th><th>ماه</th><th>بدهی</th><th>پرداخت</th><th>وضعیت</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $row): ?>
            <tr>
              <td><?= e($row['related_team'] ?? '-') ?></td>
              <td><?= e($row['fiscal_year'] ?? '-') ?></td>
              <td><?= e($row['month_name'] ?? '-') ?></td>
              <td><?= number_format((int) ($row['amount_due'] ?? 0)) ?></td>
              <td><?= number_format((int) ($row['amount_paid'] ?? 0)) ?></td>
              <td><?= e($row['status'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2>تراکنش‌های مالی</h2>
      <table class="report-table">
        <thead><tr><th>تاریخ</th><th>شرح</th><th>مبلغ</th><th>دسته</th><th>توضیحات</th></tr></thead>
        <tbody>
          <?php foreach ($transactions as $row): ?>
            <tr>
              <td><?= e($row['tx_date'] ?? '-') ?></td>
              <td><?= e($row['description'] ?? '-') ?></td>
              <td><?= number_format((int) ($row['amount'] ?? 0)) ?></td>
              <td><?= e($row['category'] ?? '-') ?></td>
              <td><?= e($row['notes'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </body>
</html>
