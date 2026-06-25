<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

require_auth();
$pdo = require_database();
$repo = new Repository($pdo);
$summary = $repo->summary();
$debts = $repo->chargeDebtRows();
$transactions = $repo->resource('transactions');
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <title>گزارش مالی</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body class="report-body">
    <main class="report-page">
      <header class="report-header">
        <div>
          <h1>گزارش مالی مرکز نوآوری</h1>
        </div>
        <button class="button no-print" onclick="window.print()">چاپ / PDF</button>
      </header>
      <section class="report-cards">
        <div><span>دریافتی</span><strong><?= number_format((int) $summary['cards']['income_total']) ?></strong></div>
        <div><span>هزینه</span><strong><?= number_format(abs((int) $summary['cards']['expense_total'])) ?></strong></div>
        <div><span>بدهی</span><strong><?= number_format((int) $summary['cards']['debt_total']) ?></strong></div>
        <div><span>واریز تیم</span><strong><?= number_format((int) $summary['cards']['paid_total']) ?></strong></div>
      </section>
      <h2>بدهی و پرداخت</h2>
      <table class="report-table">
        <thead><tr><th>نهاد</th><th>سال</th><th>ماه</th><th>بدهی</th><th>پرداخت</th><th>وضعیت</th></tr></thead>
        <tbody>
          <?php foreach ($debts as $row): ?>
            <tr>
              <td><?= e($row['team_name'] ?? '-') ?></td>
              <td><?= e($row['fiscal_year'] ?? '-') ?></td>
              <td><?= e($row['month_name'] ?? '-') ?></td>
              <td><?= number_format((int) ($row['amount_due'] ?? 0)) ?></td>
              <td><?= number_format((int) ($row['amount_paid'] ?? 0)) ?></td>
              <td><?= e($row['status'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <h2>تراکنش‌ها</h2>
      <table class="report-table">
        <thead><tr><th>تاریخ</th><th>شرح</th><th>مبلغ</th><th>دسته</th></tr></thead>
        <tbody>
          <?php foreach ($transactions as $row): ?>
            <tr>
              <td><?= e($row['tx_date'] ?? '-') ?></td>
              <td><?= e($row['description'] ?? '-') ?></td>
              <td><?= number_format((int) ($row['amount'] ?? 0)) ?></td>
              <td><?= e($row['category'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </body>
</html>
