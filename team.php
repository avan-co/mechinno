<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isConfigured = is_file(__DIR__ . '/config.php');
if ($isConfigured) {
    require_auth();
    Access::requireTeamHtml();
}
$pdo = $isConfigured ? require_database() : null;
$teamId = Access::scopedTeamId() ?? 0;
$team = null;
if ($teamId > 0 && $pdo) {
    $statement = $pdo->prepare('SELECT id, entity_code, entity_type, name, leader FROM teams WHERE id = :id');
    $statement->execute(['id' => $teamId]);
    $team = $statement->fetch() ?: null;
}
$today = JalaliDate::todayParts();
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
    filemtime(__DIR__ . '/assets/app.js')
);
$authContext = Access::clientContext();
$entityLabels = ['team' => 'تیم', 'company' => 'شرکت', 'student' => 'دانشجو'];
$entityLabel = $entityLabels[$team['entity_type'] ?? 'team'] ?? 'نهاد';
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل <?= e($team['name'] ?? 'نهاد') ?> — Mechinno</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($assetVer) ?>" />
    <script>
      (function () {
        try {
          var t = localStorage.getItem("mechinno-theme");
          if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t);
        } catch (e) {}
      })();
    </script>
  </head>
  <body class="app-body">
    <?php if (!$isConfigured || !$team): ?>
      <main class="setup-screen">
        <section class="setup-card">
          <h1>خطا</h1>
          <p>حساب نهاد به تیمی متصل نیست یا پنل پیکربندی نشده است.</p>
          <a class="button" href="logout.php">خروج</a>
        </section>
      </main>
    <?php else: ?>
      <div class="bg-blobs" aria-hidden="true"><span class="blob blob-a"></span><span class="blob blob-b"></span></div>
      <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

      <nav class="bottom-nav" aria-label="ناوبری موبایل">
        <button class="bottom-nav-item active" data-section="overview" type="button"><span>خانه</span></button>
        <button class="bottom-nav-item" data-section="members" type="button"><span>اعضا</span></button>
        <button class="bottom-nav-item" data-section="desks" type="button"><span>میزها</span></button>
        <button class="bottom-nav-item" data-section="charges" type="button"><span>شارژ</span></button>
      </nav>

      <div class="shell">
        <aside class="sidebar" id="sidebar">
          <div class="brand">
            <span class="brand-mark">M</span>
            <div>
              <strong><?= e($team['name']) ?></strong>
              <small>پنل <?= e($entityLabel) ?></small>
            </div>
          </div>
          <nav class="nav">
            <button class="nav-item active" data-section="overview" type="button">داشبورد نهاد</button>
            <button class="nav-item" data-section="members" type="button">اعضا</button>
            <button class="nav-item" data-section="desks" type="button">میزها</button>
            <button class="nav-item" data-section="lockers" type="button">کمدها</button>
            <button class="nav-item" data-section="charges" type="button">شارژ و پرداخت</button>
            <button class="nav-item" data-section="payments" type="button">اعلام واریز</button>
          </nav>
          <div class="sidebar-foot">
            <a class="logout-link" href="logout.php">خروج</a>
          </div>
        </aside>

        <div class="main-wrap">
          <header class="topbar">
            <button class="menu-toggle" id="menuToggle" type="button" aria-label="منو">☰</button>
            <div class="topbar-title">
              <p class="topbar-eyebrow"><?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?></p>
              <h1 id="pageTitle"><?= e($team['name']) ?></h1>
            </div>
            <div class="topbar-actions">
              <span class="role-chip">پنل نهاد</span>
              <span class="date-chip"><?= e($today['formatted']) ?></span>
              <button class="icon-btn" id="themeToggle" type="button" title="تغییر تم">◐</button>
            </div>
          </header>

          <main class="content">
            <p class="page-subtitle" id="pageSubtitle">مشاهده وضعیت نهاد، ثبت عضو و اعلام واریز</p>

            <section id="overview" class="section active">
              <div id="cards" class="stat-cards"></div>
              <div class="grid two">
                <article class="panel">
                  <div class="panel-head"><h2>خلاصه ماه جاری</h2><span id="currentMonthLabel" class="hint"></span></div>
                  <div id="currentMonthSummary" class="month-grid"></div>
                </article>
                <article class="panel">
                  <div class="panel-head"><h2>اطلاعات نهاد</h2></div>
                  <div class="month-grid">
                    <div class="month-stat"><span>مسئول</span><strong><?= e($team['leader'] ?? '—') ?></strong></div>
                    <div class="month-stat"><span>کد نهاد</span><strong><?= e($team['entity_code'] ?? '—') ?></strong></div>
                  </div>
                </article>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>شارژ ماهانه نهاد</h2></div>
                <div id="chargeChart" class="bar-chart"></div>
              </article>
            </section>

            <section id="members" class="section">
              <p class="hint">می‌توانید عضو جدید ثبت کنید. تا زمان تأیید مدیر، وضعیت «در انتظار» نمایش داده می‌شود.</p>
              <data-table title="اعضای نهاد" endpoint="api.php?resource=members"></data-table>
            </section>

            <section id="desks" class="section">
              <article class="panel">
                <div class="panel-head"><h2>میزهای اختصاص‌یافته</h2></div>
                <p class="hint">شماره میزهای تخصیص‌یافته به نهاد شما:</p>
                <div id="teamDeskNumbers" class="desk-number-list">در حال بارگذاری…</div>
              </article>
            </section>

            <section id="lockers" class="section">
              <data-table title="کمدهای نهاد" endpoint="api.php?resource=lockers"></data-table>
            </section>

            <section id="charges" class="section">
              <article class="panel">
                <div class="panel-head">
                  <h2>کلاژ شارژ و پرداخت</h2>
                  <select id="chargesYear" class="year-select"></select>
                </div>
                <div id="chargesCollage" class="charges-collage"></div>
              </article>
              <data-table title="جزئیات شارژ" endpoint="api.php?resource=charges"></data-table>
            </section>

            <section id="payments" class="section">
              <p class="hint">پس از واریز شارژ، اعلام کنید. واریزهای در انتظار تأیید در جدول اول نمایش داده می‌شوند؛ پس از تأیید مدیر در سوابق ثبت می‌شوند.</p>
              <data-table title="اعلام‌های در انتظار تأیید" endpoint="api.php?resource=transactions" data-payment-filter="pending"></data-table>
              <data-table title="سوابق پرداخت تأییدشده" endpoint="api.php?resource=payment-history"></data-table>
            </section>
          </main>
        </div>
      </div>

      <div id="toastHost" class="toast-host"></div>
      <script>
        window.MECHINNO = {
          csrfToken: "<?= e(csrf_token()) ?>",
          today: "<?= e($today['formatted']) ?>",
          fiscalYear: "<?= e((string) $today['year']) ?>",
          monthIndex: <?= (int) $today['month'] ?>,
          assetVer: "<?= e($assetVer) ?>",
          panel: "team",
          role: "<?= e($authContext['role']) ?>",
          canWrite: false,
          canTeamSubmit: true,
          teamId: <?= (int) $teamId ?>,
          teamName: "<?= e($team['name'] ?? '') ?>",
          username: "<?= e($authContext['username']) ?>",
        };
      </script>
      <script src="assets/app.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
