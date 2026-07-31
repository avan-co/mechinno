<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isConfigured = is_file(__DIR__ . '/config.php');
if ($isConfigured) {
    require_auth();
    Access::requireAdminHtml();
}
$authContext = $isConfigured ? Access::clientContext() : ['role' => '', 'canWrite' => false, 'panel' => 'admin', 'teamId' => null, 'username' => ''];
$today = JalaliDate::todayParts();
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
    filemtime(__DIR__ . '/assets/app.js'),
    filemtime(__DIR__ . '/assets/team-year-workspace.js'),
    filemtime(__DIR__ . '/assets/sms-panel.js'),
    filemtime(__DIR__ . '/assets/sms-editor.js'),
    filemtime(__DIR__ . '/assets/sms-settings.js'),
    filemtime(__DIR__ . '/assets/room-range.js'),
    filemtime(__DIR__ . '/assets/room-booking.js'),
    filemtime(__DIR__ . '/assets/room-calendar.js'),
    filemtime(__DIR__ . '/assets/room.css'),
    (int) Brand::version()
);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل مرکز نوآوری — Mechinno</title>
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
  <body class="<?= $isConfigured ? 'app-body' : 'standalone-page' ?>">
    <?php if (!$isConfigured): ?>
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
              <small>مرکز نوآوری مکانیک</small>
            </div>
          </div>
          <h1>راه‌اندازی پنل</h1>
          <p>فایل <code>config.sample.php</code> را به <code>config.php</code> کپی کنید.</p>
          <a class="button" href="install.php">شروع نصب</a>
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
        <button class="bottom-nav-item" data-section="teams" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg>
          <span>نهادها</span>
        </button>
        <button class="bottom-nav-item" data-section="charges" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg>
          <span>شارژ</span>
        </button>
        <button class="bottom-nav-item" data-section="members" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg>
          <span>اعضا</span>
        </button>
        <button class="bottom-nav-item" data-section="transactions" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg>
          <span>مالی</span>
        </button>
        <button class="bottom-nav-item" data-section="meeting-rooms" type="button">
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
              <strong>Mechinno</strong>
              <small>مرکز نوآوری مکانیک</small>
            </div>
          </div>
          </div>

          <nav class="nav" aria-label="منوی اصلی">
            <div class="nav-section">
              <button class="nav-item active" data-section="overview" type="button">
                <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg></span>
                <span class="nav-label">داشبورد</span>
              </button>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">عملیات مرکز</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="teams" type="button">
                  <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">نهادها</span>
                </button>
                <?php if (Access::isAdmin()): ?>
                <button class="nav-item nav-item--sub" data-section="team-contracts" type="button">
                  <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">قراردادها</span>
                </button>
                <?php endif; ?>
                <button class="nav-item" data-section="members" type="button">
                  <span class="nav-icon nav-icon--teal"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">اعضا</span>
                </button>
                <button class="nav-item" data-section="desks" type="button">
                  <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">میزها</span>
                </button>
                <?php if (Access::isAdmin()): ?>
                <button class="nav-item nav-item--sub" data-section="desk-history" type="button">
                  <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M7 3h10v4H7V3Zm0 6h10v12H7V9Zm2 2v2h6v-2H9Zm0 4v2h4v-2H9Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">تاریخچه تخصیص</span>
                </button>
                <?php endif; ?>
                <button class="nav-item" data-section="lockers" type="button">
                  <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">کمدها</span>
                </button>
              </div>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">اتاق جلسه</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="meeting-rooms" type="button">
                  <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">مدیریت رزرو</span>
                </button>
                <?php if (Access::canWrite()): ?>
                <button class="nav-item nav-item--sub" data-section="room-settings" type="button">
                  <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M12 8a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8-3H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">تنظیمات رزرو</span>
                </button>
                <?php endif; ?>
                <a class="nav-item nav-item--sub" href="reserve.php" target="_blank" rel="noopener">
                  <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">صفحه رزرو عمومی</span>
                </a>
              </div>
            </div>

            <div class="nav-section">
              <p class="nav-section-title">مالی و گزارش</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="charges" type="button">
                  <span class="nav-icon nav-icon--amber"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">شارژ</span>
                </button>
                <button class="nav-item" data-section="transactions" type="button">
                  <span class="nav-icon nav-icon--pink"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">مالی</span>
                </button>
                <button class="nav-item" data-section="reports" type="button">
                  <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Zm0-8h4v2H8V8Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">گزارش‌گیری</span>
                </button>
                <a class="nav-item nav-item--sub" href="export.php?report=all">
                  <span class="nav-icon nav-icon--teal"><svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm1 7V4.5L18.5 10H15ZM8 13h8v2H8v-2Zm0 4h5v2H8v-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">خروجی Excel</span>
                </a>
                <?php if (Access::canWrite()): ?>
                <a class="nav-item nav-item--sub" href="backup.php">
                  <span class="nav-icon nav-icon--amber"><svg viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 0-7 7v2.1A4.5 4.5 0 0 0 7.5 21h9A4.5 4.5 0 0 0 19 12.1V10a7 7 0 0 0-7-7Zm0 2a5 5 0 0 1 5 5v1H7v-1a5 5 0 0 1 5-5Zm-1 8h2v4h-2v-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">پشتیبان‌گیری</span>
                </a>
                <?php endif; ?>
              </div>
            </div>

            <?php if (Access::isAdmin()): ?>
            <div class="nav-section">
              <p class="nav-section-title">ارتباطات</p>
              <div class="nav-section-items">
                <button class="nav-item" data-section="sms" type="button">
                  <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 2v.5L12 13 4 6.5V6ZM4 18V8.2l7.4 6.5a1 1 0 0 0 1.2 0L20 8.2V18Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">ارسال پیامک</span>
                </button>
                <button class="nav-item nav-item--sub" data-section="sms-settings" type="button">
                  <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M12 8a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8-3H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">تنظیمات پیامک</span>
                </button>
              </div>
            </div>
            <?php endif; ?>

            <?php if (Access::canWrite() || Access::isAdmin()): ?>
            <div class="nav-section">
              <p class="nav-section-title">مدیریت</p>
              <div class="nav-section-items">
                <?php if (Access::canWrite()): ?>
                <button class="nav-item" data-section="development" type="button">
                  <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h10v4H4v-4Zm0 6h16v4H4v-4Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">برنامه توسعه</span>
                </button>
                <?php endif; ?>
                <?php if (Access::isAdmin()): ?>
                <button class="nav-item" data-section="users" type="button">
                  <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
                  <span class="nav-label">کاربران مدیر</span>
                </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </nav>

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
              <p class="topbar-eyebrow" id="pageEyebrow">داشبورد</p>
              <h1 id="pageTitle">مدیریت مرکز نوآوری</h1>
            </div>
            <div class="global-search" role="search">
              <span class="global-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M10.5 3a7.5 7.5 0 1 1 4.73 13.35l4.35 4.35-1.41 1.41-4.35-4.35A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 5.5 5.5A5.5 5.5 0 0 0 10.5 5Z" fill="currentColor"/></svg></span>
              <input type="search" id="globalSearch" placeholder="جست‌وجو در بخش فعلی…" autocomplete="off" aria-label="جست‌وجوی سریع" />
              <kbd class="kbd-hint" title="کلید / برای جست‌وجو">/</kbd>
            </div>
            <div class="topbar-actions">
              <?php
                $accountName = (string) ($authContext['username'] ?: 'مدیر');
                $accountRole = Access::roleLabel($authContext['role'] ?? '');
                $accountInitial = avatar_initial($accountName, 'م');
              ?>
              <div class="account-menu" id="accountMenu">
                <button class="account-menu-trigger" id="accountMenuTrigger" type="button" aria-expanded="false" aria-haspopup="menu" aria-controls="accountMenuDropdown">
                  <span class="account-menu-avatar" aria-hidden="true"><?= e($accountInitial) ?></span>
                  <span class="account-menu-meta">
                    <strong><?= e($accountName) ?></strong>
                    <small><?= e($accountRole) ?></small>
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
                    <strong id="todayChip"><?= e(fa_digits($today['formatted'])) ?></strong>
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
            <p class="page-subtitle" id="pageSubtitle">خلاصه وضعیت مرکز و اقدامات پیشنهادی</p>

            <section id="overview" class="section active">
            <div class="dashboard-hero" id="dashboardHero">
              <div class="dashboard-hero-copy">
                <p class="dashboard-hero-eyebrow">مرکز نوآوری مکانیک</p>
                <h2 id="dashboardHeroTitle">خلاصه وضعیت امروز</h2>
                <p id="dashboardHeroSubtitle">وضعیت مالی، ظرفیت و اقدام‌های فوری</p>
              </div>
              <div class="dashboard-hero-meta" id="dashboardHeroMeta">
                <div class="dashboard-hero-stat"><span>امروز</span><strong id="heroToday"><?= e(fa_digits($today['formatted'])) ?></strong></div>
                <div class="dashboard-hero-stat"><span>سال مالی</span><strong id="heroFiscalYear"><?= e(fa_digits((string) $today['year'])) ?></strong></div>
              </div>
            </div>

            <nav class="quick-nav" id="quickNav" aria-label="دسترسی سریع">
              <button type="button" class="quick-nav-item" data-section="teams"><span class="quick-nav-icon quick-nav-icon--purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg></span>نهادها</button>
              <button type="button" class="quick-nav-item" data-section="members"><span class="quick-nav-icon quick-nav-icon--teal"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>اعضا</button>
              <button type="button" class="quick-nav-item" data-section="desks"><span class="quick-nav-icon quick-nav-icon--orange"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>میزها</button>
              <button type="button" class="quick-nav-item" data-section="charges"><span class="quick-nav-icon quick-nav-icon--amber"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>شارژ</button>
              <button type="button" class="quick-nav-item" data-section="transactions"><span class="quick-nav-icon quick-nav-icon--pink"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>مالی</button>
              <?php if (Access::isAdmin()): ?>
              <button type="button" class="quick-nav-item" data-section="reports"><span class="quick-nav-icon quick-nav-icon--blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Zm0-8h4v2H8V8Z" fill="currentColor"/></svg></span>گزارش</button>
              <?php endif; ?>
            </nav>

            <?php if (Access::canWrite()): ?>
            <article class="panel panel--accent welcome-panel" id="welcomePanel" hidden>
              <h2>شروع سریع</h2>
              <div class="start-steps" id="startSteps">
                <button class="start-step" data-go="teams" type="button"><span>۱</span>ثبت نهادها</button>
                <button class="start-step" data-go="members" type="button"><span>۲</span>افزودن اعضا</button>
                <button class="start-step" data-go="desks" type="button"><span>۳</span>تخصیص میز</button>
                <button class="start-step" data-go="charges" type="button"><span>۴</span>نرخ و شارژ</button>
              </div>
            </article>
            <?php endif; ?>

              <div id="cards" class="stat-cards" aria-label="شاخص‌های کلیدی"></div>

              <div class="grid two dashboard-panels">
                <article class="panel">
                  <div class="panel-head"><h2>ماه جاری</h2><span id="currentMonthLabel" class="hint"></span></div>
                  <div id="currentMonthSummary" class="month-grid"></div>
                </article>
                <article class="panel">
                  <div class="panel-head"><h2>اقدام‌های فوری</h2><span class="hint">موارد نیازمند رسیدگی</span></div>
                  <div id="actionItems" class="action-list"></div>
                </article>
              </div>

              <div class="grid two dashboard-panels">
                <article class="panel"><div class="panel-head"><h2>روند شارژ</h2></div><div id="chargeChart" class="bar-chart"></div></article>
                <article class="panel"><div class="panel-head"><h2>بیشترین مطالبات</h2></div><div id="debtChart" class="bar-chart"></div></article>
              </div>
            </section>

            <section id="teams" class="section">
              <div class="section-intro section-intro--purple">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>هر نهاد را از دکمه <strong>پروفایل</strong> باز کنید — قرارداد، میز و بدهی هر سال در یکجا مدیریت می‌شود.</p></div>
              </div>
              <?php if (Access::canWrite()): ?>
              <article class="panel">
                <div class="panel-head">
                  <h2>ورود گروهی سابقه</h2>
                  <button type="button" class="button ghost" id="bulkYearImportButton">ورود CSV سال</button>
                </div>
                <p class="hint">برای ثبت یک‌باره چند نهاد در یک سال — فرمت: <code>نام نهاد,شروع,پایان,میزها</code></p>
              </article>
              <?php endif; ?>
              <data-table title="نهادها" endpoint="api.php?resource=teams"></data-table>
            </section>

            <section id="team-contracts" class="section">
              <div class="section-intro section-intro--purple">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>همه قراردادهای سال جاری و سال‌های قبل — قراردادهای <strong>فعال</strong> (در بازه امروز) در بالای لیست نمایش داده می‌شوند.</p></div>
              </div>
              <data-table title="قراردادهای نهادها" endpoint="api.php?resource=team_contracts"></data-table>
            </section>

            <section id="members" class="section">
              <div class="section-intro section-intro--teal">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>اعضای تأییدشده در لیست اصلی نمایش داده می‌شوند. درخواست‌های نهادها در جدول «در انتظار تأیید» بررسی می‌شود. کد تردد پس از تأیید، به‌صورت حضوری و با تأخیر ثبت می‌شود.</p></div>
              </div>
              <?php if (Access::isAdmin()): ?>
              <data-table title="اعضا — در انتظار تأیید نهاد" endpoint="api.php?resource=pending-members" data-workflow="members" data-workflow-type="member-approve" data-table-key="pending-members" data-readonly></data-table>
              <data-table title="درخواست ویرایش/حذف عضو" endpoint="api.php?resource=pending-member-requests" data-workflow="members" data-workflow-type="member-request" data-table-key="pending-member-requests" data-readonly></data-table>
              <?php endif; ?>
              <div class="filter-tabs" id="memberApprovalTabs" role="tablist" aria-label="فیلتر وضعیت عضو">
                <button type="button" class="filter-tab active" data-approval-filter="" role="tab" aria-selected="true">همه</button>
                <button type="button" class="filter-tab" data-approval-filter="approved" role="tab" aria-selected="false">تأیید‌شده</button>
                <button type="button" class="filter-tab" data-approval-filter="rejected" role="tab" aria-selected="false">رد‌شده</button>
              </div>
              <div class="member-filters" id="memberFilters"></div>
              <data-table id="membersTable" title="فهرست اعضا" endpoint="api.php?resource=members" data-approval-filter=""></data-table>
            </section>

            <section id="sms" class="section">
              <div id="smsSetupBanner" hidden></div>
              <div class="section-intro section-intro--green">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 2v.5L12 13 4 6.5V6ZM4 18V8.2l7.4 6.5a1 1 0 0 0 1.2 0L20 8.2V18Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy">
                  <p>ارسال پیامک از طریق <strong>ملی‌پیامک</strong>. تنظیمات API در بخش <button type="button" class="text-link" data-go="sms-settings">تنظیمات پیامک</button>. مدیر مشاهده‌گر فقط آمار و تاریخچه را می‌بیند.</p>
                </div>
                <?php if (Access::canWrite()): ?>
                <button type="button" class="button ghost section-intro-action" data-go="sms-settings">تنظیمات پیامک</button>
                <?php endif; ?>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>آمار ارسال</h2></div>
                <div id="smsStats">در حال بارگذاری…</div>
              </article>
              <article class="panel" id="smsRecipientsPanel">
                <div class="panel-head">
                  <h2>ارسال اطلاعیه</h2>
                  <div class="panel-head-actions sms-selection-actions">
                    <button type="button" class="button ghost" id="smsSelectLeaders">انتخاب مسئول‌ها</button>
                    <button type="button" class="button ghost" id="smsSelectAllPage">انتخاب صفحه</button>
                    <button type="button" class="button ghost" id="smsClearSelection">پاک کردن انتخاب</button>
                  </div>
                </div>
                <div id="smsFilterBar"></div>
                <p class="hint" id="smsSelectionInfo">۰ نفر انتخاب شده</p>
                <div class="table-wrap">
                  <table id="smsRecipientsTable" class="data-table">
                    <thead><tr><th></th><th>نام</th><th>نهاد</th><th>موبایل</th><th>تردد</th></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>
                <div class="table-pagination" id="smsRecipientsPager">
                  <span class="pager-info"></span>
                  <div class="pager-buttons">
                    <button class="mini-button" type="button" data-sms-prev>قبلی</button>
                    <button class="mini-button" type="button" data-sms-next>بعدی</button>
                  </div>
                </div>
                <div id="smsAnnouncementEditor"></div>
                <?php if (Access::canWrite()): ?>
                <button type="button" class="button" id="smsSendAnnouncement">ارسال به انتخاب‌شده‌ها</button>
                <?php endif; ?>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>تاریخچه پیامک‌ها</h2></div>
                <div class="table-wrap table-scroll">
                  <table id="smsHistoryTable" class="data-table data-table--wide">
                    <thead><tr><th>زمان</th><th>نوع</th><th>گیرنده</th><th>موبایل</th><th>نهاد</th><th>وضعیت</th><th>دلیوری</th><th>پذیرش API</th><th>هزینه</th><th>متن</th></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>
              </article>
            </section>

            <section id="sms-settings" class="section">
              <div class="section-intro section-intro--green">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 8a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8-3H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>اتصال REST ملی‌پیامک. حساب API و شماره خط ارسال را جداگانه وارد و ذخیره کنید.</p></div>
              </div>
              <article class="panel">
                <div class="panel-head"><h2>حساب API ملی‌پیامک</h2></div>
                <form id="smsCredentialsForm" class="payment-settings-form">در حال بارگذاری…</form>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>خط ارسال و محدودیت روزانه</h2></div>
                <form id="smsLineForm" class="payment-settings-form">در حال بارگذاری…</form>
              </article>
              <article class="panel">
                <div class="panel-head"><h2>آمار و همگام‌سازی</h2></div>
                <div id="smsSettingsStats">در حال بارگذاری…</div>
                <div class="panel-actions">
                  <?php if (Access::canWrite()): ?>
                  <button class="button ghost" type="button" id="smsTestConnection">تست اتصال API</button>
                  <button class="button ghost" type="button" id="smsRefreshLiveStats">بروزرسانی موجودی و تعرفه از API</button>
                  <button class="button ghost" type="button" id="smsSyncHistory">همگام‌سازی تاریخچه از API</button>
                  <?php endif; ?>
                </div>
              </article>
            </section>

            <section id="desks" class="section">
              <div class="section-intro section-intro--orange">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>نقشه ۲۴ میز سال جاری — <?php if (Access::canWrite()): ?>روی هر میز کلیک کنید تا تخصیص را ویرایش کنید.<?php else: ?>میزها به نهاد تخصیص می‌یابند.<?php endif; ?></p></div>
              </div>
              <article class="panel">
                <div class="panel-head">
                  <h2>نقشه ۲۴ میز — سال جاری</h2>
                  <div class="desk-legend">
                    <span class="legend-item legend-free">آزاد</span>
                    <span class="legend-item legend-occupied">اشغال</span>
                    <span class="legend-item legend-highlight">بازه</span>
                  </div>
                </div>
                <p class="hint">۳ ردیف × ۸ میز — <?php if (Access::canWrite()): ?>روی هر میز کلیک کنید تا تخصیص سال جاری را ویرایش کنید.<?php else: ?>میزها به نهاد تخصیص می‌یابند.<?php endif; ?></p>
                <div id="deskGrid" class="desk-map"></div>
              </article>
              <data-table id="currentDesksTable" title="تخصیص سال جاری — جزئیات میزها" endpoint="api.php?resource=desks" data-no-add></data-table>
            </section>

            <section id="desk-history" class="section">
              <div class="section-intro section-intro--orange">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h10v4H7V3Zm0 6h10v12H7V9Zm2 2v2h6v-2H9Zm0 4v2h4v-2H9Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>تخصیص میز فقط برای نهادهایی با <strong>قرارداد ثبت‌شده</strong> امکان‌پذیر است. بازه‌ها به‌صورت ماه نمایش داده می‌شوند.</p></div>
              </div>
              <article class="panel desk-history-panel">
                <div class="panel-head">
                  <h2>تاریخچه تخصیص میزها</h2>
                  <?php if (Access::canWrite()): ?>
                  <button id="deskHistoryAddButton" class="button" type="button">ثبت تخصیص میز</button>
                  <?php endif; ?>
                </div>
                <p class="hint">بازه‌ها به‌صورت ماه نمایش داده می‌شوند (مثلاً از فروردین تا مرداد).</p>
                <div id="deskHistoryFilters"></div>
                <data-table id="deskAssignmentsTable" title="" endpoint="api.php?resource=desk-assignments" data-no-add></data-table>
              </article>
            </section>

            <section id="lockers" class="section">
              <div class="section-intro section-intro--green">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>مدیریت کمدها و درخواست‌های نهادها — پس از تأیید، کمد به نهاد تخصیص می‌یابد.</p></div>
              </div>
              <?php if (Access::isAdmin()): ?>
              <data-table title="درخواست کمد — در انتظار تأیید" endpoint="api.php?resource=pending-locker-requests" data-workflow="lockers" data-workflow-type="locker-request" data-table-key="pending-locker-requests" data-readonly></data-table>
              <?php endif; ?>
              <data-table title="کمدها" endpoint="api.php?resource=lockers"></data-table>
            </section>

            <section id="meeting-rooms" class="section">
              <div class="section-intro section-intro--blue">
                <div class="section-intro-copy"><p>رزرو اتاق جلسه، تقویم هفتگی و مدیریت اتاق‌ها — <a href="reserve.php" target="_blank" rel="noopener">صفحه رزرو عمومی</a></p></div>
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

              <div id="panelRoomOverview" class="room-overview-grid" aria-label="اتاق‌های فعال"></div>

              <?php if (Access::isAdmin()): ?>
              <data-table title="رزرو — در انتظار تأیید" endpoint="api.php?resource=pending-room-reservations" data-workflow="meeting-rooms" data-workflow-type="room-reservation" data-table-key="pending-room-reservations" data-readonly></data-table>
              <?php endif; ?>
              <?php if (Access::canWrite()): ?>
              <div class="room-booking-shell">
                <article class="room-card room-booking-panel">
                  <h2>رزرو جدید</h2>
                  <p class="room-card-lead">روز را انتخاب کنید، سپس مثل رزرو هتل ابتدا ساعت شروع و بعد ساعت پایان را بزنید (مثلاً ۱۰:۰۰ تا ۱۲:۰۰ = ۲ ساعت).</p>
                  <form id="panelRoomBookingForm">
                    <label class="wide"><span>انتخاب اتاق</span></label>
                    <div id="panelRoomCardGrid" class="room-room-grid room-room-grid--panel" role="listbox" aria-label="انتخاب اتاق"></div>
                    <input type="hidden" name="room_id" required />
                    <input type="hidden" name="reserved_date" id="panelReservedDate" value="<?= e($today['formatted']) ?>" />

                    <div class="room-booking-layout">
                      <div class="room-month-picker" id="panelRoomMonthPicker">
                        <div class="room-month-toolbar">
                          <button type="button" class="button ghost" id="panelMonthPrev" aria-label="ماه قبل">‹</button>
                          <strong id="panelMonthLabel">—</strong>
                          <button type="button" class="button ghost" id="panelMonthNext" aria-label="ماه بعد">›</button>
                        </div>
                        <div class="room-month-weekdays" aria-hidden="true">
                          <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <div id="panelMonthGrid" class="room-month-grid"></div>
                        <p class="hint" id="panelMonthHint">روی یک روز قابل‌رزرو کلیک کنید.</p>
                      </div>

                      <div class="room-booking-fields">
                        <div class="room-field-row">
                          <label>
                            <span>نهاد (از فهرست)</span>
                            <select name="team_id" id="panelTeamSelect">
                              <option value="">— بدون نهاد / مهمان —</option>
                            </select>
                          </label>
                          <label><span>نام *</span><input name="booker_name" type="text" required /></label>
                          <label><span>موبایل *</span><input name="booker_phone" type="tel" required dir="ltr" class="ltr-input" placeholder="09xxxxxxxxx" /></label>
                          <label><span>سازمان / نهاد</span><input name="booker_org" type="text" /></label>
                          <label class="wide"><span>موضوع</span><textarea name="purpose" rows="2"></textarea></label>
                        </div>
                        <div class="room-slot-legend">
                          <span class="free">آزاد</span>
                          <span class="range">بازه</span>
                          <span class="pending">انتظار</span>
                          <span class="busy">پر</span>
                          <span class="closed">تعطیل</span>
                        </div>
                        <p class="hint" id="panelSelectedDayLabel">روز انتخاب‌شده: <?= e(fa_digits($today['formatted'])) ?></p>
                        <p class="hint" id="panelRangeHint">۱) شروع  ۲) پایان (ساعت اتمام) — مثلاً ۱۰:۰۰ تا ۱۲:۰۰ = ۲ ساعت.</p>
                        <div id="panelRoomSlotGrid" class="room-slot-grid"></div>
                        <p class="hint" id="panelRoomTimePreview"></p>
                        <div class="form-actions"><button class="button" type="submit">ثبت رزرو</button></div>
                      </div>
                    </div>
                  </form>
                </article>
                <aside class="room-card" id="roomClosedDaysPanel">
                  <h2>روزهای تعطیل</h2>
                  <p class="room-card-lead">روزهایی که رزرو (عمومی و پنل) در آن‌ها غیرفعال است.</p>
                  <form id="roomClosedDayForm" class="room-closed-form">
                    <label><span>تاریخ</span><input name="date" type="text" required placeholder="1404/01/01" /></label>
                    <label><span>توضیح</span><input name="note" type="text" placeholder="مثلاً تعطیل رسمی" /></label>
                    <button class="button" type="submit">تعطیل کردن</button>
                  </form>
                  <div id="roomClosedDaysList" class="room-closed-list"></div>
                </aside>
              </div>
              <?php endif; ?>
              <data-table title="رزروهای اتاق" endpoint="api.php?resource=room-reservations" data-no-add data-readonly></data-table>
              <data-table title="اتاق‌های جلسه" endpoint="api.php?resource=meeting-rooms"></data-table>
            </section>

            <section id="room-settings" class="section">
              <div class="section-intro section-intro--blue">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 8a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8-3H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>قوانین رزرو عمومی و تأیید خودکار — حداکثر ساعت روزانه، بازه‌های ۳۰/۶۰ دقیقه‌ای و روزهای تعطیل.</p></div>
              </div>
              <?php if (Access::canWrite()): ?>
              <article class="panel">
                <form id="roomSettingsForm" class="crud-grid">
                  <label><span>رزرو عمومی</span>
                    <select name="room_public_enabled"><option value="1">فعال</option><option value="0">غیرفعال</option></select>
                  </label>
                  <label><span>تأیید خودکار</span>
                    <select name="room_auto_approve"><option value="1">بله</option><option value="0">خیر — نیاز به تأیید مدیر</option></select>
                  </label>
                  <label><span>حداکثر روز جلو</span><input name="room_max_advance_days" type="number" min="1" max="90" /></label>
                  <label><span>حداکثر ساعت در روز (هر موبایل)</span><input name="room_max_hours_per_day" type="number" min="1" max="8" /></label>
                  <label><span>بازه پیش‌فرض (دقیقه)</span>
                    <select name="room_slot_minutes"><option value="30">۳۰</option><option value="60">۶۰</option></select>
                  </label>
                  <div class="form-actions wide"><button class="button" type="submit">ذخیره تنظیمات</button></div>
                </form>
              </article>
              <?php else: ?>
              <p class="hint">فقط مدیر ویرایشگر می‌تواند تنظیمات را تغییر دهد.</p>
              <?php endif; ?>
            </section>

            <section id="charges" class="section">
              <div class="section-intro section-intro--amber">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>نرخ شارژ و اجاره <strong>به ازای هر میز</strong> است. ماه‌های شارژ از <strong>بازه تخصیص میز</strong> محاسبه می‌شود. ویرایش دستی شارژ در محاسبه خودکار حفظ می‌شود.</p></div>
              </div>
              <data-table title="نرخ‌های سالانه" endpoint="api.php?resource=rate_settings"></data-table>
              <article class="panel">
                <div class="panel-head">
                  <h2>کلاژ شارژ و پرداخت</h2>
                  <div class="panel-head-actions">
                    <select id="chargesYear" class="year-select"></select>
                    <?php if (Access::canWrite()): ?>
                    <button id="recalcChargesButton" class="button ghost" type="button">محاسبه خودکار از نرخ</button>
                    <?php endif; ?>
                  </div>
                </div>
                <p class="hint"><?php if (Access::canWrite()): ?>روی سلول «بدهکار به مرکز» کلیک کنید تا <strong>ثبت مستقیم مدیر</strong> انجام شود (بدون صف تأیید). واریز نهادها ابتدا به <strong>قدیمی‌ترین ماه‌های بدهکار</strong> تخصیص می‌یابد.<?php else: ?>وضعیت پرداخت هر نهاد در هر ماه — فقط مشاهده. واریزها ابتدا به قدیمی‌ترین بدهی‌ها تخصیص می‌یابند.<?php endif; ?></p>
                <div id="chargesCollage" class="charges-collage"></div>
              </article>
              <data-table title="ثبت و ویرایش شارژ" endpoint="api.php?resource=charges"></data-table>
            </section>

            <section id="transactions" class="section">
              <div class="section-intro section-intro--pink">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>دفتر معین فقط <strong>گردش نقدی واقعی</strong> را نشان می‌دهد: واریز تأییدشده نهادها، درآمد و هزینه دستی. شارژ و مطالبات در بخش شارژ محاسبه می‌شود.</p></div>
              </div>
              <article class="panel" id="ledgerPanel">
                <div class="panel-head">
                  <h2>دفتر معین — موجودی حساب مرکز</h2>
                  <span class="hint">از صفر — فقط گردش نقدی واقعی</span>
                </div>
                <div class="table-wrap ledger-block">
                  <table class="data-table ledger-summary-table">
                    <caption class="ledger-caption">خلاصه موجودی</caption>
                    <thead>
                      <tr><th>شرح</th><th class="num">مبلغ (ریال)</th></tr>
                    </thead>
                    <tbody id="ledgerSummaryBody">
                      <tr><td colspan="2" class="empty">در حال بارگذاری…</td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="table-wrap ledger-block">
                  <table class="data-table ledger-table">
                    <caption class="ledger-caption">گردش حساب (دفتر معین)</caption>
                    <thead>
                      <tr>
                        <th class="num">ردیف</th>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>شرح</th>
                        <th class="num">بستانکار</th>
                        <th class="num">بدهکار</th>
                        <th class="num">مانده</th>
                      </tr>
                    </thead>
                    <tbody id="ledgerTableBody">
                      <tr><td colspan="7" class="empty">در حال بارگذاری…</td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="pager" id="ledgerPager" aria-label="صفحه‌بندی دفتر معین"></div>
                <div class="table-wrap ledger-block" id="ledgerBillingWrap" hidden>
                  <table class="data-table ledger-billing-table">
                    <caption class="ledger-caption">مطالبات شارژ — مرجع بخش شارژ (در موجودی نقدی لحاظ نمی‌شود)</caption>
                    <thead>
                      <tr>
                        <th class="num">مطالبات شارژ</th>
                        <th class="num">دریافت‌شده از نهادها</th>
                        <th class="num">مانده طلب</th>
                      </tr>
                    </thead>
                    <tbody id="ledgerBillingBody"></tbody>
                  </table>
                </div>
              </article>
              <?php if (Access::isAdmin()): ?>
              <data-table title="اعلام واریز — در انتظار تأیید" endpoint="api.php?resource=pending-payments" data-workflow="payments" data-table-key="pending-payments" data-readonly></data-table>
              <data-table title="واریزهای رد‌شده" endpoint="api.php?resource=payment-history" data-payment-filter="rejected" data-table-key="rejected-payments" data-readonly></data-table>
              <?php endif; ?>
              <?php if (Access::canWrite()): ?>
              <article class="panel" id="paymentSettingsPanel">
                <div class="panel-head">
                  <h2>اطلاعات واریز شارژ</h2>
                  <span class="hint">در پنل نهادها نمایش داده می‌شود</span>
                </div>
                <form id="paymentSettingsForm" class="crud-grid payment-settings-form">
                  <label><span>نام بانک</span><input name="bank_name" type="text" /></label>
                  <label><span>نام صاحب حساب</span><input name="account_holder" type="text" /></label>
                  <label><span>شماره حساب</span><input name="account_number" type="text" dir="ltr" /></label>
                  <label><span>شماره کارت</span><input name="card_number" type="text" dir="ltr" placeholder="xxxx-xxxx-xxxx-xxxx" /></label>
                  <label><span>شماره شبا</span><input name="sheba" type="text" dir="ltr" placeholder="IR..." /></label>
                  <label class="wide"><span>راهنمای پرداخت برای نهادها</span><textarea name="payment_guide" rows="4"></textarea></label>
                  <div class="wide form-actions">
                    <button class="button" type="submit">ذخیره اطلاعات واریز</button>
                  </div>
                </form>
              </article>
              <?php endif; ?>
              <div class="grid two finance-actions">
                <article class="panel">
                  <div class="panel-head">
                    <h2>درآمد دستی</h2>
                    <?php if (Access::canWrite()): ?>
                    <button class="button ghost" type="button" id="addIncomeButton">+ درآمد</button>
                    <?php endif; ?>
                  </div>
                  <p class="hint">درآمدهای غیر از شارژ و اجاره نهادها — دوره آموزشی، جریمه، اسپانسری و …</p>
                  <data-table title="" endpoint="api.php?resource=transactions" data-tx-filter="درآمد" data-no-add></data-table>
                </article>
                <article class="panel">
                  <div class="panel-head">
                    <h2>هزینه‌ها</h2>
                    <?php if (Access::canWrite()): ?>
                    <button class="button ghost" type="button" id="addExpenseButton">+ هزینه</button>
                    <?php endif; ?>
                  </div>
                  <p class="hint">هزینه‌های جاری مرکز — لوازم مصرفی، خوراکی، تعمیرات و …</p>
                  <data-table title="" endpoint="api.php?resource=transactions" data-tx-filter="هزینه" data-no-add></data-table>
                </article>
              </div>
            </section>

            <section id="reports" class="section">
              <div class="section-intro section-intro--blue">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Zm0-8h4v2H8V8Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>نوع گزارش، بازه زمانی (ماهانه / سه‌ماهه / سالانه / سفارشی) و در صورت نیاز نهاد را انتخاب کنید؛ سپس پیش‌نمایش، چاپ PDF یا Excel بگیرید.</p></div>
              </div>
              <article class="panel report-builder-panel">
                <div class="panel-head">
                  <h2>سازنده گزارش</h2>
                  <span class="hint">انتخاب دقیق محتوا و بازه</span>
                </div>
                <form id="reportBuilderForm" class="report-builder-form">
                  <div class="report-type-grid" id="reportTypeGrid" role="radiogroup" aria-label="نوع گزارش"></div>
                  <div class="crud-grid report-filters-grid">
                    <label>
                      <span>بازه زمانی</span>
                      <select name="period" id="reportPeriod">
                        <option value="monthly">ماهانه</option>
                        <option value="quarterly">سه‌ماهه</option>
                        <option value="annual">سالانه</option>
                        <option value="custom">بازه سفارشی</option>
                      </select>
                    </label>
                    <label>
                      <span>سال مالی</span>
                      <select name="fiscal_year" id="reportFiscalYear"></select>
                    </label>
                    <label id="reportMonthWrap">
                      <span>ماه</span>
                      <select name="month" id="reportMonth"></select>
                    </label>
                    <label id="reportQuarterWrap" hidden>
                      <span>فصل</span>
                      <select name="quarter" id="reportQuarter"></select>
                    </label>
                    <label id="reportMonthFromWrap" hidden>
                      <span>از ماه</span>
                      <select name="month_from" id="reportMonthFrom"></select>
                    </label>
                    <label id="reportMonthToWrap" hidden>
                      <span>تا ماه</span>
                      <select name="month_to" id="reportMonthTo"></select>
                    </label>
                    <label>
                      <span>نهاد (اختیاری)</span>
                      <select name="team_id" id="reportTeam">
                        <option value="0">همه نهادها</option>
                      </select>
                    </label>
                  </div>
                  <div class="form-actions report-builder-actions">
                    <button class="button" type="submit">پیش‌نمایش گزارش</button>
                    <button class="button ghost" type="button" id="reportOpenPrint">چاپ / PDF</button>
                    <button class="button ghost" type="button" id="reportOpenExcel">دانلود Excel</button>
                  </div>
                </form>
              </article>
              <article class="panel">
                <div class="panel-head">
                  <h2>پیش‌نمایش</h2>
                  <span class="hint" id="reportPreviewMeta">هنوز گزارشی ساخته نشده است</span>
                </div>
                <div id="reportPreview" class="report-preview">
                  <div class="empty-state">
                    <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Zm0-8h4v2H8V8Z" fill="currentColor"/></svg></span>
                    <p class="empty-state-text">نوع گزارش و بازه را انتخاب کنید، سپس «پیش‌نمایش گزارش» را بزنید.</p>
                  </div>
                </div>
              </article>
            </section>

            <?php if (Access::canWrite()): ?>
            <section id="development" class="section">
              <div class="section-intro section-intro--purple">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h10v4H4v-4Zm0 6h16v4H4v-4Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>برنامه‌های جاری مرکز — عنوان، وضعیت، اولویت و موعد.</p></div>
              </div>
              <data-table title="برنامه‌های توسعه" endpoint="api.php?resource=development_plans"></data-table>
            </section>
            <?php endif; ?>

            <?php if (Access::isAdmin()): ?>
            <section id="users" class="section">
              <div class="section-intro section-intro--purple">
                <span class="section-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
                <div class="section-intro-copy"><p>مدیران سیستم — هنگام ثبت نهاد می‌توانید رمز ورود را خودکار بسازید یا دستی تعیین کنید.</p></div>
              </div>
              <data-table title="کاربران مدیر" endpoint="api.php?resource=panel_users"></data-table>
            </section>
            <?php endif; ?>
          </main>

          <footer class="app-footer">
            <span>پنل مدیریت مرکز نوآوری</span>
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
          panel: "<?= e($authContext['panel']) ?>",
          role: "<?= e($authContext['role']) ?>",
          canWrite: <?= $authContext['canWrite'] ? 'true' : 'false' ?>,
          canTeamSubmit: <?= ($authContext['canTeamSubmit'] ?? false) ? 'true' : 'false' ?>,
          username: "<?= e($authContext['username']) ?>",
        };
      </script>
      <script src="assets/app.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/team-year-workspace.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/sms-editor.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/sms-panel.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/sms-settings.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/room-range.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/room-booking.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/room-calendar.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
