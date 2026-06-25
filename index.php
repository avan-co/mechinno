<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isConfigured = is_file(__DIR__ . '/config.php');
if ($isConfigured) {
    require_auth();
}
$today = JalaliDate::todayParts();
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل مرکز نوآوری</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <?php if (!$isConfigured): ?>
      <main class="setup-screen">
        <section class="setup-card">
          <span class="brand-mark">✦</span>
          <h1>راه‌اندازی پنل</h1>
          <p>فایل <code>config.sample.php</code> را به <code>config.php</code> کپی کنید.</p>
          <a class="button" href="install.php">شروع نصب</a>
        </section>
      </main>
    <?php else: ?>
      <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
      <nav class="bottom-nav" aria-label="ناوبری">
        <button class="bottom-nav-item active" data-section="overview" type="button"><span class="bottom-nav-icon">⌂</span><span>خانه</span></button>
        <button class="bottom-nav-item" data-section="teams" type="button"><span class="bottom-nav-icon">◉</span><span>نهادها</span></button>
        <button class="bottom-nav-item" data-section="charges" type="button"><span class="bottom-nav-icon">₪</span><span>شارژ</span></button>
        <button class="bottom-nav-item" data-section="transactions" type="button"><span class="bottom-nav-icon">◈</span><span>مالی</span></button>
      </nav>

      <div class="shell">
        <aside class="sidebar" id="sidebar">
          <div class="brand">
            <span class="brand-mark">✦</span>
            <div>
              <strong>Mechinno</strong>
              <small>مرکز نوآوری مکانیک</small>
            </div>
          </div>
          <nav class="nav">
            <button class="nav-item active" data-section="overview" type="button">🏠 داشبورد</button>
            <button class="nav-item" data-section="teams" type="button">◉ نهادها</button>
            <button class="nav-item" data-section="members" type="button">👤 اعضا</button>
            <button class="nav-item" data-section="desks" type="button">▦ میزها</button>
            <button class="nav-item" data-section="lockers" type="button">▣ کمدها</button>
            <button class="nav-item" data-section="charges" type="button">₪ شارژ</button>
            <button class="nav-item" data-section="transactions" type="button">◈ مالی</button>
          </nav>
          <div class="sidebar-foot">
            <a class="export-all" href="export.php?report=all">خروجی Excel</a>
            <a class="export-all secondary" href="report.php">گزارش PDF</a>
            <a class="export-all ghost" href="install.php">بازنشانی پنل</a>
            <a class="logout-link" href="logout.php">خروج</a>
          </div>
        </aside>

        <main class="content">
          <header class="mobile-topbar">
            <button class="menu-toggle" id="menuToggle" type="button" aria-label="منو">☰</button>
            <strong>Mechinno</strong>
            <span class="kbd-hint" title="/ جست‌وجو">/</span>
          </header>

          <header class="hero">
            <div class="hero-glow"></div>
            <div class="hero-text">
              <p class="eyebrow">Innovation Center Panel</p>
              <h1>مدیریت مرکز نوآوری</h1>
              <p>پنل خالی و آماده — نهادها، میزها، شارژ و مالی را خودتان تکمیل کنید.</p>
            </div>
          </header>

          <section id="overview" class="section active">
            <article class="panel welcome-panel" id="welcomePanel">
              <h2>شروع سریع</h2>
              <ol class="start-steps" id="startSteps">
                <li data-go="teams">ثبت نهادها (تیم / شرکت / دانشجو)</li>
                <li data-go="members">افزودن اعضا و انتساب میز</li>
                <li data-go="lockers">تعریف شماره کمدها</li>
                <li data-go="charges">تنظیم نرخ و ثبت شارژ</li>
              </ol>
            </article>
            <article class="panel">
              <div class="panel-head"><h2>خلاصه ماه جاری</h2><span id="currentMonthLabel" class="hint"></span></div>
              <div id="currentMonthSummary" class="current-month-grid"></div>
            </article>
            <article class="panel">
              <div class="panel-head"><h2>نیاز به اقدام</h2></div>
              <div id="actionItems" class="action-list"></div>
            </article>
            <div id="cards" class="cards"></div>
            <div class="grid two">
              <article class="panel"><div class="panel-head"><h2>شارژ ماهانه</h2></div><div id="chargeChart" class="bar-chart"></div></article>
              <article class="panel"><div class="panel-head"><h2>بدهی نهادها</h2></div><div id="debtChart" class="bar-chart"></div></article>
            </div>
          </section>

          <section id="teams" class="section">
            <data-table title="نهادها" endpoint="api.php?resource=teams"></data-table>
          </section>
          <section id="members" class="section">
            <data-table title="اعضا" endpoint="api.php?resource=members"></data-table>
          </section>
          <section id="desks" class="section">
            <article class="panel">
              <div class="panel-head"><h2>نقشه ۲۴ میز</h2><span class="hint">۳ ردیف × ۸ — هر میز ۲ صندلی</span></div>
              <div id="deskGrid" class="desk-grid"></div>
            </article>
            <data-table title="جزئیات میزها" endpoint="api.php?resource=desks"></data-table>
          </section>
          <section id="lockers" class="section">
            <data-table title="کمدها — شماره را خودتان اضافه کنید" endpoint="api.php?resource=lockers"></data-table>
          </section>
          <section id="charges" class="section">
            <data-table title="نرخ‌های سالانه" endpoint="api.php?resource=rate_settings"></data-table>
            <article class="panel">
              <div class="panel-head">
                <h2>کلاژ شارژ و پرداخت</h2>
                <div class="panel-head-actions">
                  <select id="chargesYear" class="year-select"></select>
                  <button id="recalcChargesButton" class="button ghost" type="button">محاسبه خودکار از نرخ</button>
                </div>
              </div>
              <p class="hint collage-hint">روی سلول بدهکار کلیک کنید تا واریز ثبت شود.</p>
              <div id="chargesCollage" class="charges-collage"></div>
            </article>
            <data-table title="ثبت و ویرایش شارژ" endpoint="api.php?resource=charges"></data-table>
          </section>
          <section id="transactions" class="section">
            <data-table title="مالی و واریز تیم‌ها" endpoint="api.php?resource=transactions"></data-table>
          </section>
        </main>
      </div>
      <div id="toastHost" class="toast-host" aria-live="polite"></div>
      <script>
        window.MECHINNO = {
          csrfToken: "<?= e(csrf_token()) ?>",
          today: "<?= e($today['formatted']) ?>",
          fiscalYear: "<?= e((string) $today['year']) ?>",
          monthIndex: <?= (int) $today['month'] ?>,
        };
      </script>
      <script src="assets/app.js"></script>
    <?php endif; ?>
  </body>
</html>
