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
    $statement = $pdo->prepare(
        'SELECT id, entity_code, entity_type, name, leader, phone, contract_start, contract_end, joined_at, warning, notes
         FROM teams WHERE id = :id'
    );
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
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
      <div class="bg-blobs" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
        <span class="blob blob-c"></span>
      </div>
      <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

      <nav class="bottom-nav" aria-label="ناوبری موبایل">
        <button class="bottom-nav-item active" data-section="overview" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg>
          <span>خانه</span>
        </button>
        <button class="bottom-nav-item" data-section="members" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg>
          <span>اعضا</span>
        </button>
        <button class="bottom-nav-item" data-section="desks" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg>
          <span>میزها</span>
        </button>
        <button class="bottom-nav-item" data-section="charges" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg>
          <span>شارژ</span>
        </button>
        <button class="bottom-nav-item" data-section="lockers" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg>
          <span>کمدها</span>
        </button>
        <button class="bottom-nav-item" data-section="payments" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg>
          <span>واریز</span>
        </button>
      </nav>

      <div class="shell">
        <aside class="sidebar" id="sidebar">
          <div class="brand">
            <span class="brand-mark">M</span>
            <div>
              <strong><?= e($team['name']) ?></strong>
              <small><?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?></small>
            </div>
          </div>
          <nav class="nav" aria-label="منوی نهاد">
            <button class="nav-item active" data-section="overview" type="button">
              <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg></span>
              داشبورد
            </button>
            <button class="nav-item" data-section="profile" type="button">
              <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
              پروفایل نهاد
            </button>
            <button class="nav-item" data-section="members" type="button">
              <span class="nav-icon nav-icon--teal"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
              اعضا
            </button>
            <button class="nav-item" data-section="desks" type="button">
              <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
              میزها
            </button>
            <button class="nav-item" data-section="lockers" type="button">
              <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
              کمدها
            </button>
            <button class="nav-item" data-section="charges" type="button">
              <span class="nav-icon nav-icon--amber"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
              شارژ
            </button>
            <button class="nav-item" data-section="payments" type="button">
              <span class="nav-icon nav-icon--pink"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
              اعلام واریز
            </button>
          </nav>
        </aside>

        <div class="main-wrap">
          <header class="topbar">
            <div class="topbar-start">
              <button class="menu-toggle" id="menuToggle" type="button" aria-label="منو">
                <svg viewBox="0 0 24 24"><path d="M4 7h16v2H4V7Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z" fill="currentColor"/></svg>
              </button>
            </div>
            <div class="topbar-title">
              <p class="topbar-eyebrow" id="pageEyebrow"><?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?></p>
              <h1 id="pageTitle"><?= e($team['name']) ?></h1>
            </div>
            <div class="topbar-actions">
              <a class="logout-top" href="logout.php" title="خروج از پنل">خروج</a>
              <span class="role-chip entity-name-chip"><?= e($team['name']) ?></span>
              <span class="date-chip"><?= e($today['formatted']) ?></span>
              <button class="icon-btn" id="themeToggle" type="button" title="تغییر تم" aria-label="تغییر تم">
                <svg class="icon-sun" viewBox="0 0 24 24"><path d="M12 18a6 6 0 1 1 6-6 6 6 0 0 1-6 6Zm0-16h2v3h-2V2Zm0 19h2v3h-2v-3ZM2 11h3v2H2v-2Zm19 0h3v2h-3v-2Z" fill="currentColor"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 14.5A7.5 7.5 0 0 1 9.5 3a6 6 0 1 0 11.5 11.5Z" fill="currentColor"/></svg>
              </button>
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
                  <div class="panel-head"><h2>قرارداد نهاد</h2></div>
                  <div class="month-grid">
                    <div class="month-stat"><span>شروع</span><strong><?= e($team['contract_start'] ?? '—') ?></strong></div>
                    <div class="month-stat"><span>پایان</span><strong><?= e($team['contract_end'] ?? '—') ?></strong></div>
                    <div class="month-stat"><span>مسئول</span><strong><?= e($team['leader'] ?? '—') ?></strong></div>
                    <div class="month-stat"><span>تماس</span><strong><?= e($team['phone'] ?? '—') ?></strong></div>
                  </div>
                </article>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>شارژ ماهانه نهاد</h2></div>
                <div id="chargeChart" class="bar-chart"></div>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>تأییدهای اخیر</h2><span class="hint">اعلام‌های بررسی‌شده توسط مرکز</span></div>
                <div id="recentApprovals" class="action-list"></div>
              </article>
            </section>

            <section id="profile" class="section">
              <article class="panel">
                <div class="panel-head"><h2>اطلاعات تکمیلی نهاد</h2></div>
                <div id="teamProfileContent" class="team-profile-content">در حال بارگذاری…</div>
              </article>
            </section>

            <section id="members" class="section">
              <p class="hint">نام، موبایل و کد ملی اجباری است. در صورت نیاز به دسترسی تردد اعلام کنید — کد دستگاه پس از تأیید مدیر ثبت می‌شود.</p>
              <data-table title="اعضای نهاد" endpoint="api.php?resource=members"></data-table>
            </section>

            <section id="desks" class="section">
              <article class="panel">
                <div class="panel-head"><h2>میزهای اختصاص‌یافته</h2></div>
                <p class="hint">میزهای فعال نهاد — برای سابقه کامل به پروفایل نهاد مراجعه کنید.</p>
                <div id="teamDeskAssignments" class="desk-assignment-list">در حال بارگذاری…</div>
              </article>
            </section>

            <section id="lockers" class="section">
              <p class="hint">برای درخواست کمد جدید، درخواست ثبت کنید. پس از تأیید مدیر، کمد به نهاد تخصیص می‌یابد.</p>
              <data-table title="درخواست‌های کمد" endpoint="api.php?resource=locker-requests"></data-table>
              <data-table title="کمدهای تخصیص‌یافته" endpoint="api.php?resource=lockers" data-no-add></data-table>
            </section>

            <section id="charges" class="section">
              <article class="panel">
                <div class="panel-head">
                  <h2>کلاژ شارژ و پرداخت</h2>
                  <select id="chargesYear" class="year-select"></select>
                </div>
                <div id="chargesCollage" class="charges-collage"></div>
              </article>
              <data-table title="جزئیات شارژ" endpoint="api.php?resource=charges" data-no-add></data-table>
            </section>

            <section id="payments" class="section">
              <article class="panel" id="paymentGuidePanel">
                <div class="panel-head"><h2>راهنمای پرداخت شارژ</h2></div>
                <div id="paymentGuideContent" class="payment-guide">در حال بارگذاری…</div>
              </article>
              <p class="hint">پس از واریز، اعلام کنید. واریزهای در انتظار در جدول اول نمایش داده می‌شوند.</p>
              <data-table title="اعلام‌های در انتظار تأیید" endpoint="api.php?resource=transactions" data-payment-filter="pending"></data-table>
              <data-table title="اعلام‌های رد‌شده" endpoint="api.php?resource=transactions" data-payment-filter="rejected" data-no-add></data-table>
              <data-table title="سوابق پرداخت تأییدشده" endpoint="api.php?resource=payment-history" data-no-add></data-table>
            </section>
          </main>

          <footer class="app-footer">
            <span>پنل <?= e($entityLabel) ?> — <?= e($team['name']) ?></span>
            <span>Mechinno · مرکز نوآوری مکانیک</span>
          </footer>
        </div>
      </div>

      <div id="toastHost" class="toast-host" aria-live="polite"></div>
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
