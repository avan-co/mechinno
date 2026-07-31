<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isConfigured = is_file(__DIR__ . '/config.php');
$pageError = null;
$pdo = null;
$teamId = 0;
$team = null;
$authContext = ['role' => '', 'canWrite' => false, 'panel' => 'team', 'teamId' => null, 'username' => ''];

try {
    if ($isConfigured) {
        require_auth();
        Access::requireTeamHtml();
        $pdo = require_database();
        $teamId = Access::scopedTeamId() ?? 0;
        if ($teamId > 0) {
            $statement = $pdo->prepare(
                'SELECT id, entity_code, entity_type, name, leader, phone, contract_start, contract_end, joined_at, warning, notes
                 FROM teams WHERE id = :id'
            );
            $statement->execute(['id' => $teamId]);
            $team = $statement->fetch() ?: null;
        }
        $authContext = Access::clientContext();
    }
} catch (Throwable $exception) {
    $pageError = safe_error_message($exception);
}

$today = JalaliDate::todayParts();
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
    filemtime(__DIR__ . '/assets/app.js'),
    filemtime(__DIR__ . '/assets/room-booking.js'),
    filemtime(__DIR__ . '/assets/room-calendar.js'),
    filemtime(__DIR__ . '/assets/room.css'),
    filemtime(__DIR__ . '/assets/team-year-workspace.js'),
    (int) Brand::version()
);
$entityLabels = ['team' => 'تیم', 'company' => 'شرکت', 'student' => 'دانشجو'];
$entityLabel = $entityLabels[$team['entity_type'] ?? 'team'] ?? 'نهاد';
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل <?= e($team['name'] ?? 'نهاد') ?> — Mechinno</title>
    <?= Brand::headTags() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($assetVer) ?>" />
    <link rel="stylesheet" href="assets/room.css?v=<?= e($assetVer) ?>" />
    <script>
      (function () {
        try {
          var t = localStorage.getItem("mechinno-theme");
          if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t);
        } catch (e) {}
      })();
    </script>
  </head>
  <body class="<?= ($pageError || !$isConfigured || !$team) ? 'standalone-page' : 'app-body' ?>">
    <?php if ($pageError): ?>
      <div class="bg-blobs" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
      </div>
      <main class="setup-screen">
        <section class="setup-card">
          <div class="setup-brand">
            <?= Brand::mark('hero') ?>
            <div class="setup-brand-copy">
              <strong>Mechinno</strong>
              <small>پنل نهاد</small>
            </div>
          </div>
          <h1>خطا</h1>
          <div class="notice danger"><?= e($pageError) ?></div>
          <a class="button" href="logout.php">خروج</a>
        </section>
      </main>
    <?php elseif (!$isConfigured || !$team): ?>
      <div class="bg-blobs" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
      </div>
      <main class="setup-screen">
        <section class="setup-card">
          <div class="setup-brand">
            <?= Brand::mark('hero') ?>
            <div class="setup-brand-copy">
              <strong>Mechinno</strong>
              <small>پنل نهاد</small>
            </div>
          </div>
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
        <button class="bottom-nav-item" data-section="charges" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg>
          <span>شارژ</span>
        </button>
        <button class="bottom-nav-item" data-section="profile" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg>
          <span>پروفایل</span>
        </button>
        <button class="bottom-nav-item" data-section="payments" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg>
          <span>واریز</span>
        </button>
        <button class="bottom-nav-item" data-section="room-reservations" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg>
          <span>اتاق</span>
        </button>
        <button class="bottom-nav-item" type="button" id="bottomNavMenu" aria-label="باز کردن منو">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v2H4V7Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z" fill="currentColor"/></svg>
          <span>منو</span>
        </button>
      </nav>

      <div class="shell">
        <aside class="sidebar" id="sidebar">
          <div class="sidebar-inner">
          <div class="sidebar-brand-strip">
          <div class="brand">
            <?= Brand::mark('panel') ?>
            <div class="brand-copy">
              <strong><?= e($team['name']) ?></strong>
              <small><?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?></small>
            </div>
          </div>
          </div>
          <nav class="nav" aria-label="منوی نهاد">
            <div class="nav-section">
              <button class="nav-item active" data-section="overview" type="button">
                <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg></span>
                <span class="nav-label">داشبورد</span>
              </button>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">نهاد</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="profile" type="button">
                  <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">پروفایل نهاد</span>
                </button>
                <button class="nav-item" data-section="members" type="button">
                  <span class="nav-icon nav-icon--teal"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">اعضا</span>
                </button>
                <button class="nav-item" data-section="desks" type="button">
                  <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">میزها</span>
                </button>
                <button class="nav-item" data-section="lockers" type="button">
                  <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">کمدها</span>
                </button>
              </div>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">اتاق جلسه</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="room-reservations" type="button">
                  <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">رزرو اتاق</span>
                </button>
              </div>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">مالی</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="charges" type="button">
                  <span class="nav-icon nav-icon--amber"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">شارژ</span>
                </button>
                <button class="nav-item" data-section="payments" type="button">
                  <span class="nav-icon nav-icon--pink"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">اعلام واریز</span>
                </button>
              </div>
            </div>
          </nav>
          <div class="sidebar-user">
            <div class="user-pill">
              <span class="user-avatar" aria-hidden="true"><?= e(avatar_initial((string) ($team['name'] ?? ''), 'ن')) ?></span>
              <div class="user-pill-copy">
                <strong><?= e($team['name']) ?></strong>
                <small><?= e($entityLabel) ?> — پورتال نهاد</small>
              </div>
            </div>
          </div>
          </div>
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
            <div class="global-search" role="search">
              <span class="global-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M10.5 3a7.5 7.5 0 1 1 4.73 13.35l4.35 4.35-1.41 1.41-4.35-4.35A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 5.5 5.5A5.5 5.5 0 0 0 10.5 5Z" fill="currentColor"/></svg></span>
              <input type="search" id="globalSearch" placeholder="جست‌وجو در بخش فعلی…" autocomplete="off" aria-label="جست‌وجوی سریع" />
              <kbd class="kbd-hint" title="کلید / برای جست‌وجو">/</kbd>
            </div>
            <div class="topbar-actions">
              <?php
                $accountName = (string) ($team['name'] ?? 'نهاد');
                $accountRole = $entityLabel . ' — پورتال';
                $accountInitial = avatar_initial($accountName, 'ن');
              ?>
              <div class="account-menu" id="accountMenu">
                <button class="account-menu-trigger" id="accountMenuTrigger" type="button" aria-expanded="false" aria-haspopup="menu" aria-controls="accountMenuDropdown">
                  <span class="account-menu-avatar" aria-hidden="true"><?= e($accountInitial) ?></span>
                  <span class="account-menu-meta">
                    <strong><?= e($accountName) ?></strong>
                    <small><?= e($entityLabel) ?></small>
                  </span>
                  <svg class="account-menu-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5Z" fill="currentColor"/></svg>
                </button>
                <div class="account-menu-dropdown" id="accountMenuDropdown" role="menu" hidden>
                  <div class="account-menu-profile">
                    <span class="account-menu-avatar account-menu-avatar--lg" aria-hidden="true"><?= e($accountInitial) ?></span>
                    <div>
                      <strong><?= e($accountName) ?></strong>
                      <small><?= e($accountRole) ?></small>
                    </div>
                  </div>
                  <div class="account-menu-meta-row">
                    <span>تاریخ امروز</span>
                    <strong><?= e(fa_digits($today['formatted'])) ?></strong>
                  </div>
                  <div class="account-menu-divider" role="separator"></div>
                  <button class="account-menu-item" id="themeToggle" type="button" role="menuitem" title="تغییر تم">
                    <span class="account-menu-item-label">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 6-6 6 6 0 0 1-6 6Zm0-16h2v3h-2V2Zm0 19h2v3h-2v-3ZM2 11h3v2H2v-2Zm19 0h3v2h-3v-2Z" fill="currentColor" class="icon-sun"/><path d="M21 14.5A7.5 7.5 0 0 1 9.5 3a6 6 0 1 0 11.5 11.5Z" fill="currentColor" class="icon-moon"/></svg>
                      حالت نمایش
                    </span>
                    <span class="theme-toggle-label">تم</span>
                  </button>
                  <a class="account-menu-item account-menu-item--danger" href="logout.php" role="menuitem">
                    <span class="account-menu-item-label">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-8v-2h8V6h-8V4Zm-1.3 5.3L6.4 12l2.3 2.7-1.5 1.3L3 12l3.2-4 1.5 1.3Z" fill="currentColor"/></svg>
                      خروج از پنل
                    </span>
                  </a>
                </div>
              </div>
            </div>
          </header>

          <main class="content">
            <p class="page-subtitle" id="pageSubtitle">مشاهده وضعیت نهاد، ثبت عضو و اعلام واریز</p>

            <section id="overview" class="section active">
              <div class="dashboard-hero" id="dashboardHero">
                <div class="dashboard-hero-copy">
                  <p class="dashboard-hero-eyebrow"><?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?></p>
                  <h2 id="dashboardHeroTitle"><?= e($team['name']) ?></h2>
                  <p id="dashboardHeroSubtitle">وضعیت نهاد، اعضا، میزها و شارژ در یک نگاه</p>
                </div>
                <div class="dashboard-hero-meta" id="dashboardHeroMeta">
                  <div class="dashboard-hero-stat"><span>مسئول</span><strong><?= e($team['leader'] ?? '—') ?></strong></div>
                  <div class="dashboard-hero-stat"><span>تاریخ امروز</span><strong><?= e($today['formatted']) ?></strong></div>
                </div>
              </div>
              <nav class="quick-nav" aria-label="دسترسی سریع">
                <button type="button" class="quick-nav-item" data-section="profile"><span class="quick-nav-icon quick-nav-icon--purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>پروفایل</button>
                <button type="button" class="quick-nav-item" data-section="members"><span class="quick-nav-icon quick-nav-icon--teal"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>اعضا</button>
                <button type="button" class="quick-nav-item" data-section="desks"><span class="quick-nav-icon quick-nav-icon--orange"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>میزها</button>
                <button type="button" class="quick-nav-item" data-section="charges"><span class="quick-nav-icon quick-nav-icon--amber"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>شارژ</button>
                <button type="button" class="quick-nav-item" data-section="payments"><span class="quick-nav-icon quick-nav-icon--pink"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>واریز</button>
              </nav>
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
              <div class="section-intro section-intro--purple">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>قرارداد، میزها و بدهی هر سال — با تب سال می‌توانید سوابق گذشته را هم ببینید.</p></div>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>پروفایل و سوابق سالانه</h2></div>
                <div id="teamProfileContent" class="team-profile-content">در حال بارگذاری…</div>
              </article>
            </section>

            <section id="members" class="section">
              <div class="section-intro section-intro--teal">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>برای افزودن عضو جدید از «+ افزودن» استفاده کنید. برای اعضای تأیید‌شده فقط می‌توانید <strong>درخواست ویرایش</strong> یا <strong>درخواست حذف</strong> ثبت کنید — تغییر پس از تأیید مرکز اعمال می‌شود.</p></div>
              </div>
              <data-table title="اعضای نهاد" endpoint="api.php?resource=members"></data-table>
              <data-table title="درخواست‌های تغییر عضو" endpoint="api.php?resource=member-requests" data-readonly></data-table>
            </section>

            <section id="desks" class="section">
              <div class="section-intro section-intro--orange">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>میزهای فعال (بازه تخصیص هنوز تمام نشده) و سوابق پایان‌یافته — بازه هر میز روی کارت نمایش داده می‌شود.</p></div>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>میزهای اختصاص‌یافته</h2></div>
                <div id="teamDeskAssignments" class="desk-assignment-list">در حال بارگذاری…</div>
              </article>
            </section>

            <section id="lockers" class="section">
              <div class="section-intro section-intro--green">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>برای درخواست کمد جدید، درخواست ثبت کنید. پس از تأیید مدیر، کمد به نهاد تخصیص می‌یابد.</p></div>
              </div>
              <data-table title="درخواست‌های کمد" endpoint="api.php?resource=locker-requests"></data-table>
              <data-table title="کمدهای تخصیص‌یافته" endpoint="api.php?resource=lockers" data-readonly></data-table>
            </section>

            <section id="room-reservations" class="section">
              <div class="section-intro section-intro--blue">
                <div class="section-intro-copy"><p>رزرو اتاق جلسه برای نهاد — تقویم هفتگی و انتخاب اتاق با کارت.</p></div>
              </div>

              <article class="room-card room-calendar-shell" id="roomCalendarPanel">
                <div class="room-calendar-toolbar">
                  <div>
                    <h2>تقویم رزرو</h2>
                    <p class="room-card-lead" id="roomCalendarRangeLabel">در حال بارگذاری…</p>
                  </div>
                  <div class="room-calendar-controls">
                    <label class="room-calendar-filter">
                      <span>اتاق</span>
                      <select id="roomCalendarRoomFilter" aria-label="فیلتر اتاق">
                        <option value="0">همه اتاق‌ها</option>
                      </select>
                    </label>
                    <button type="button" class="button ghost" id="roomCalendarPrev">هفته قبل</button>
                    <button type="button" class="button ghost" id="roomCalendarToday">امروز</button>
                    <button type="button" class="button ghost" id="roomCalendarNext">هفته بعد</button>
                  </div>
                </div>
                <div id="roomCalendarGrid" class="room-calendar-grid" aria-live="polite"></div>
              </article>

              <article class="room-card room-booking-panel">
                <h2>رزرو جدید</h2>
                <p class="room-card-lead">اتاق و بازه زمانی را انتخاب کنید.</p>
                <form id="panelRoomBookingForm">
                  <label class="wide"><span>انتخاب اتاق</span></label>
                  <div id="panelRoomCardGrid" class="room-room-grid room-room-grid--panel" role="listbox" aria-label="انتخاب اتاق"></div>
                  <input type="hidden" name="room_id" required />
                  <div class="room-field-row">
                    <label><span>تاریخ</span><input name="reserved_date" type="text" required placeholder="1404/01/01" value="<?= e($today['formatted']) ?>" /></label>
                    <label><span>نام *</span><input name="booker_name" type="text" required /></label>
                    <label><span>موبایل *</span><input name="booker_phone" type="tel" required dir="ltr" class="ltr-input" /></label>
                    <label class="wide"><span>موضوع</span><textarea name="purpose" rows="2"></textarea></label>
                  </div>
                  <div class="room-slot-legend"><span class="free">آزاد</span><span class="pending">انتظار</span><span class="busy">پر</span></div>
                  <div id="panelRoomSlotGrid" class="room-slot-grid"></div>
                  <p class="hint" id="panelRoomTimePreview"></p>
                  <div class="form-actions"><button class="button" type="submit">ثبت رزرو</button></div>
                </form>
              </article>
              <data-table title="رزروهای نهاد" endpoint="api.php?resource=room-reservations" data-no-add data-readonly></data-table>
            </section>

            <section id="charges" class="section">
              <div class="section-intro section-intro--amber">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>نرخ شارژ سال جاری و وضعیت پرداخت هر ماه — کلاژ شارژ بر اساس تخصیص میز و نرخ‌های مرکز محاسبه می‌شود.</p></div>
              </div>
              <article class="panel" id="teamChargeRatesPanel">
                <div class="panel-head"><h2>نرخ شارژ سال جاری</h2></div>
                <div id="teamChargeRates" class="team-charge-rates">در حال بارگذاری…</div>
              </article>
              <article class="panel">
                <div class="panel-head">
                  <h2>کلاژ شارژ و پرداخت</h2>
                  <select id="chargesYear" class="year-select"></select>
                </div>
                <div id="chargesCollage" class="charges-collage"></div>
              </article>
              <data-table title="جزئیات شارژ" endpoint="api.php?resource=charges" data-readonly></data-table>
            </section>

            <section id="payments" class="section">
              <div class="section-intro section-intro--pink">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>اطلاعات حساب بانکی مرکز، اعلام واریز و پیگیری تأیید پرداخت‌ها.</p></div>
              </div>
              <article class="panel" id="paymentGuidePanel">
                <div class="panel-head"><h2>راهنمای پرداخت شارژ</h2></div>
                <div id="paymentGuideContent" class="payment-guide">در حال بارگذاری…</div>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>اعلام واریز</h2></div>
                <div id="teamPaymentWizard">در حال بارگذاری…</div>
              </article>
              <data-table title="اعلام‌های در انتظار تأیید" endpoint="api.php?resource=transactions" data-payment-filter="pending"></data-table>
              <data-table title="سوابق پرداخت" endpoint="api.php?resource=payment-history" data-readonly></data-table>
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
          teamEyebrow: "<?= e($entityLabel) ?> — <?= e($team['entity_code'] ?? '') ?>",
          teamSubtitle: "مشاهده وضعیت نهاد، ثبت عضو و اعلام واریز",
          username: "<?= e($authContext['username']) ?>",
        };
      </script>
      <script src="assets/app.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/team-year-workspace.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/room-booking.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/room-calendar.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
