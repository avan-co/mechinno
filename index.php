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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <?php if (!$isConfigured): ?>
      <main class="setup-screen">
        <section class="setup-card">
          <span class="brand-mark">M</span>
          <h1>راه‌اندازی لازم است</h1>
          <p>فایل <code>config.sample.php</code> را به <code>config.php</code> کپی کنید و اطلاعات دیتابیس را وارد کنید.</p>
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
            <button class="nav-item" data-section="teams">نهادها</button>
            <button class="nav-item" data-section="members">اعضا</button>
            <button class="nav-item" data-section="desks">میزها</button>
            <button class="nav-item" data-section="lockers">کمدها</button>
            <button class="nav-item" data-section="charges">شارژ</button>
            <button class="nav-item" data-section="transactions">مالی</button>
            <button class="nav-item" data-section="plans">برنامه‌ها</button>
            <button class="nav-item" data-section="rate_settings">نرخ‌ها</button>
            <button class="nav-item" data-section="backups">پشتیبان‌ها</button>
          </nav>
          <a class="export-all" href="export.php?report=all">خروجی Excel</a>
          <a class="export-all secondary" href="report.php">گزارش PDF</a>
          <a class="logout-link" href="logout.php">خروج</a>
        </aside>

        <main class="content">
          <header class="hero compact">
            <div>
              <p class="eyebrow">Management Panel</p>
              <h1>پنل مدیریت مرکز نوآوری</h1>
              <p>مدیریت مستقل نهادها، میزها، شارژ و مالی — پس از راه‌اندازی اولیه نیازی به Excel نیست.</p>
            </div>
            <div class="hero-actions">
              <button id="reimportButton" class="button ghost">بازنشانی داده نمونه</button>
            </div>
          </header>

          <section id="overview" class="section active">
            <div id="cards" class="cards"></div>
            <div class="grid two">
              <article class="panel">
                <div class="panel-head"><h2>شارژ ماهانه</h2></div>
                <div id="chargeChart" class="bar-chart"></div>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>وضعیت کمدها</h2></div>
                <div id="lockerStatus" class="status-list"></div>
              </article>
            </div>
            <div class="grid two">
              <article class="panel">
                <div class="panel-head"><h2>بدهی نهادها</h2></div>
                <div id="debtChart" class="bar-chart"></div>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>درآمد و هزینه</h2></div>
                <div id="financeChart" class="bar-chart"></div>
              </article>
            </div>
            <article class="panel">
              <div class="panel-head"><h2>اشغال میز و کمد</h2></div>
              <div id="occupancyChart" class="metric-grid"></div>
            </article>
          </section>

          <section id="teams" class="section">
            <data-table title="تیم‌ها، شرکت‌ها و دانشجویان" endpoint="api.php?resource=teams"></data-table>
          </section>
          <section id="members" class="section">
            <data-table title="اعضا" endpoint="api.php?resource=members"></data-table>
          </section>
          <section id="desks" class="section">
            <article class="panel">
              <div class="panel-head">
                <h2>نقشه ۲۴ میز (۳ ردیف × ۸)</h2>
                <span class="hint">هر میز ۲ صندلی — رسمی / غیررسمی / ترکیبی</span>
              </div>
              <div id="deskGrid" class="desk-grid"></div>
            </article>
            <data-table title="جزئیات میزها" endpoint="api.php?resource=desks"></data-table>
          </section>
          <section id="lockers" class="section">
            <data-table title="کمدها" endpoint="api.php?resource=lockers"></data-table>
          </section>
          <section id="charges" class="section">
            <article class="panel">
              <div class="panel-head">
                <h2>کلاژ شارژ و پرداخت</h2>
                <select id="chargesYear" class="year-select"></select>
              </div>
              <div id="chargesCollage" class="charges-collage"></div>
            </article>
            <data-table title="ویرایش دستی مبالغ شارژ" endpoint="api.php?resource=charges"></data-table>
          </section>
          <section id="transactions" class="section">
            <data-table title="مالی (تنخواه + واریز تیم‌ها)" endpoint="api.php?resource=transactions"></data-table>
          </section>
          <section id="plans" class="section">
            <data-table title="برنامه‌های اجرایی" endpoint="api.php?resource=plans"></data-table>
          </section>
          <section id="rate_settings" class="section">
            <data-table title="نرخ پیش‌فرض" endpoint="api.php?resource=rate_settings"></data-table>
            <data-table title="نرخ اختصاصی نهادها" endpoint="api.php?resource=team_rates"></data-table>
          </section>
          <section id="backups" class="section">
            <data-table title="پشتیبان‌ها" endpoint="api.php?resource=backups"></data-table>
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
