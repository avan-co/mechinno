<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isConfigured = is_file(__DIR__ . '/config.php');
if ($isConfigured) {
    require_auth();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل مدیریتی مرکز نوآوری</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <?php if (!$isConfigured): ?>
      <main class="setup-screen">
        <section class="setup-card">
          <span class="brand-mark">M</span>
          <h1>راه‌اندازی لازم است</h1>
          <p>برای اجرای پنل روی cPanel، فایل <code>config.sample.php</code> را به <code>config.php</code> کپی کنید و اطلاعات دیتابیس MySQL را وارد کنید.</p>
          <a class="button" href="install.php">رفتن به نصب</a>
        </section>
      </main>
    <?php else: ?>
      <div class="shell">
        <aside class="sidebar">
          <div class="brand">
            <span class="brand-mark">M</span>
            <div>
              <strong>Mechinno</strong>
              <small>مرکز نوآوری مکانیک</small>
            </div>
          </div>
          <nav class="nav">
            <button class="nav-item active" data-section="overview">داشبورد</button>
            <button class="nav-item" data-section="teams">تیم‌ها</button>
            <button class="nav-item" data-section="members">اعضا</button>
            <button class="nav-item" data-section="lockers">کمدها</button>
            <button class="nav-item" data-section="charges">شارژ</button>
            <button class="nav-item" data-section="team_payments">پرداخت‌ها</button>
            <button class="nav-item" data-section="transactions">مالی</button>
            <button class="nav-item" data-section="plans">برنامه‌ها</button>
            <button class="nav-item" data-section="rate_settings">نرخ‌ها</button>
            <button class="nav-item" data-section="backups">پشتیبان‌ها</button>
            <button class="nav-item" data-section="warnings">کیفیت داده</button>
          </nav>
          <a class="export-all" href="export.php?report=all">دریافت گزارش کامل Excel</a>
          <a class="export-all secondary" href="report.php">گزارش رسمی PDF</a>
          <a class="logout-link" href="logout.php">خروج از پنل</a>
        </aside>

        <main class="content">
          <header class="hero">
            <div>
              <p class="eyebrow">Management Intelligence</p>
              <h1>پنل مدیریتی یکپارچه مرکز نوآوری</h1>
              <p>نسخه PHP مناسب cPanel؛ داده‌های اعضا، تیم‌ها، کمدها، شارژ، مالی و برنامه‌های اجرایی از فایل‌های Excel وارد MySQL می‌شوند.</p>
            </div>
            <div class="hero-actions">
              <button id="reimportButton" class="button ghost">ورود مجدد از Excel</button>
              <a class="button" href="export.php?report=all">خروجی کامل</a>
            </div>
          </header>

          <section id="overview" class="section active">
            <div id="cards" class="cards"></div>
            <div class="grid two">
              <article class="panel">
                <div class="panel-head">
                  <h2>شارژ ماهانه</h2>
                  <a href="export.php?report=charges">Excel</a>
                </div>
                <div id="chargeChart" class="bar-chart"></div>
              </article>
              <article class="panel">
                <div class="panel-head">
                  <h2>وضعیت کمدها</h2>
                  <a href="export.php?report=lockers">Excel</a>
                </div>
                <div id="lockerStatus" class="status-list"></div>
              </article>
            </div>
            <div class="grid two">
              <article class="panel">
                <div class="panel-head">
                  <h2>مالی بر اساس دسته</h2>
                  <a href="export.php?report=transactions">Excel</a>
                </div>
                <div id="financeStatus" class="status-list"></div>
              </article>
              <article class="panel">
                <div class="panel-head">
                  <h2>برنامه‌های اجرایی</h2>
                  <a href="export.php?report=plans">Excel</a>
                </div>
                <div id="planStatus" class="status-list"></div>
              </article>
            </div>
            <div class="grid two">
              <article class="panel">
                <div class="panel-head"><h2>بدهی تیم‌ها</h2></div>
                <div id="debtChart" class="bar-chart"></div>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>درآمد و هزینه ماهانه</h2></div>
                <div id="financeChart" class="bar-chart"></div>
              </article>
            </div>
            <article class="panel">
              <div class="panel-head"><h2>اشغال منابع</h2></div>
              <div id="occupancyChart" class="metric-grid"></div>
            </article>
          </section>

          <section id="teams" class="section">
            <data-table title="تیم‌ها" endpoint="api.php?resource=teams" export-url="export.php?report=teams"></data-table>
          </section>
          <section id="members" class="section">
            <data-table title="اعضا" endpoint="api.php?resource=members" export-url="export.php?report=members"></data-table>
          </section>
          <section id="lockers" class="section">
            <data-table title="کمدها" endpoint="api.php?resource=lockers" export-url="export.php?report=lockers"></data-table>
          </section>
          <section id="charges" class="section">
            <data-table title="شارژ و اجاره" endpoint="api.php?resource=charges" export-url="export.php?report=charges"></data-table>
          </section>
          <section id="team_payments" class="section">
            <data-table title="بدهی و پرداخت تیم‌ها" endpoint="api.php?resource=team_payments" export-url="export.php?report=team_payments"></data-table>
          </section>
          <section id="transactions" class="section">
            <data-table title="مالی" endpoint="api.php?resource=transactions" export-url="export.php?report=transactions"></data-table>
          </section>
          <section id="plans" class="section">
            <data-table title="برنامه‌ها" endpoint="api.php?resource=plans" export-url="export.php?report=plans"></data-table>
          </section>
          <section id="rate_settings" class="section">
            <data-table title="تنظیمات نرخ شارژ و اجاره" endpoint="api.php?resource=rate_settings" export-url="export.php?report=rate_settings"></data-table>
          </section>
          <section id="backups" class="section">
            <data-table title="پشتیبان‌های import" endpoint="api.php?resource=backups" export-url="export.php?report=backups"></data-table>
          </section>
          <section id="warnings" class="section">
            <data-table title="هشدارهای کیفیت داده" endpoint="api.php?resource=warnings" export-url="export.php?report=warnings"></data-table>
          </section>
        </main>
      </div>
      <script>
        window.MECHINNO = { csrfToken: "<?= e(csrf_token()) ?>" };
      </script>
      <script src="assets/app.js"></script>
    <?php endif; ?>
  </body>
</html>
