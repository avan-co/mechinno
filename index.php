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
    filemtime(__DIR__ . '/assets/sms-settings.js')
);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل مرکز نوآوری — Mechinno</title>
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
    <?php if (!$isConfigured): ?>
      <main class="setup-screen">
        <section class="setup-card">
          <div class="brand">
            <span class="brand-mark">M</span>
            <div>
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
        <button class="bottom-nav-item" type="button" id="bottomNavMenu" aria-label="باز کردن منو">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v2H4V7Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z" fill="currentColor"/></svg>
          <span>منو</span>
        </button>
      </nav>

      <div class="shell">
        <aside class="sidebar" id="sidebar">
          <div class="brand">
            <span class="brand-mark">M</span>
            <div>
              <strong>Mechinno</strong>
              <small>مرکز نوآوری مکانیک</small>
            </div>
          </div>

          <nav class="nav" aria-label="منوی اصلی">
            <button class="nav-item active" data-section="overview" type="button">
              <span class="nav-icon nav-icon--blue"><svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg></span>
              داشبورد
            </button>
            <button class="nav-item" data-section="teams" type="button">
              <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg></span>
              نهادها
            </button>
            <?php if (Access::isAdmin()): ?>
            <button class="nav-item nav-item--sub" data-section="team-contracts" type="button">
              <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2Z" fill="currentColor"/></svg></span>
              قراردادها
            </button>
            <?php endif; ?>
            <button class="nav-item" data-section="members" type="button">
              <span class="nav-icon nav-icon--teal"><svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3S17.7 5 16 5s-3 1.3-3 3 1.3 3 3 3ZM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3Zm0 2c-2.7 0-8 1.3-8 4v3h10v-3c0-1.1.4-2.1 1.1-2.9C9.8 13.1 8.9 13 8 13Zm8 0c-.9 0-1.8.1-2.6.3.7.8 1.1 1.8 1.1 2.9v3h7v-3c0-2.7-5.3-4-8-4Z" fill="currentColor"/></svg></span>
              اعضا
            </button>
            <button class="nav-item" data-section="desks" type="button">
              <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg></span>
              میزها
            </button>
            <?php if (Access::isAdmin()): ?>
            <button class="nav-item nav-item--sub" data-section="desk-history" type="button">
              <span class="nav-icon nav-icon--orange"><svg viewBox="0 0 24 24"><path d="M7 3h10v4H7V3Zm0 6h10v12H7V9Zm2 2v2h6v-2H9Zm0 4v2h4v-2H9Z" fill="currentColor"/></svg></span>
              تاریخچه تخصیص
            </button>
            <?php endif; ?>
            <button class="nav-item" data-section="lockers" type="button">
              <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 4v12h12V7H6Zm3 2h2v2H9V9Zm4 0h2v2h-2V9Z" fill="currentColor"/></svg></span>
              کمدها
            </button>
            <button class="nav-item" data-section="charges" type="button">
              <span class="nav-icon nav-icon--amber"><svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg></span>
              شارژ
            </button>
            <button class="nav-item" data-section="transactions" type="button">
              <span class="nav-icon nav-icon--pink"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg></span>
              مالی
            </button>
            <?php if (Access::isAdmin()): ?>
            <button class="nav-item" data-section="sms" type="button">
              <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 2v.5L12 13 4 6.5V6ZM4 18V8.2l7.4 6.5a1 1 0 0 0 1.2 0L20 8.2V18Z" fill="currentColor"/></svg></span>
              ارسال پیامک
            </button>
            <button class="nav-item nav-item--sub" data-section="sms-settings" type="button">
              <span class="nav-icon nav-icon--green"><svg viewBox="0 0 24 24"><path d="M12 8a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8-3H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z" fill="currentColor"/></svg></span>
              تنظیمات پیامک
            </button>
            <?php endif; ?>
            <?php if (Access::canWrite()): ?>
            <button class="nav-item" data-section="development" type="button">
              <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h10v4H4v-4Zm0 6h16v4H4v-4Z" fill="currentColor"/></svg></span>
              برنامه توسعه
            </button>
            <?php endif; ?>
            <?php if (Access::isAdmin()): ?>
            <button class="nav-item" data-section="users" type="button">
              <span class="nav-icon nav-icon--purple"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 9a8 8 0 0 1 16 0Z" fill="currentColor"/></svg></span>
              کاربران مدیر
            </button>
            <?php endif; ?>
          </nav>

          <div class="sidebar-foot">
            <a class="foot-btn" href="export.php?report=all">خروجی Excel</a>
            <a class="foot-btn foot-btn--soft" href="report.php">گزارش PDF</a>
            <?php if (Access::canWrite()): ?>
            <a class="foot-btn foot-btn--danger" href="install.php">بازنشانی پنل (خطرناک)</a>
            <?php endif; ?>
            <a class="logout-link" href="logout.php">خروج</a>
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
            <div class="topbar-actions">
              <a class="logout-top" href="logout.php" title="خروج از پنل">خروج</a>
              <span class="role-chip"><?= e(match ($authContext['role']) {
                  'admin_editor' => 'مدیر — ویرایش',
                  'admin_viewer' => 'مدیر — مشاهده',
                  default => 'مدیر',
              }) ?></span>
              <span class="date-chip" id="todayChip"><?= e($today['formatted']) ?></span>
              <button class="icon-btn" id="themeToggle" type="button" title="تغییر تم" aria-label="تغییر تم">
                <svg class="icon-sun" viewBox="0 0 24 24"><path d="M12 18a6 6 0 1 1 6-6 6 6 0 0 1-6 6Zm0-16h2v3h-2V2Zm0 19h2v3h-2v-3ZM2 11h3v2H2v-2Zm19 0h3v2h-3v-2ZM4.2 4.2l2.1 2.1-1.4 1.4-2.1-2.1 1.4-1.4Zm13.1 13.1 2.1 2.1-1.4 1.4-2.1-2.1 1.4-1.4ZM4.2 19.8l1.4-1.4 2.1 2.1-1.4 1.4-2.1-2.1Zm13.1-13.1 1.4-1.4 2.1 2.1-1.4 1.4-2.1-2.1Z" fill="currentColor"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 14.5A7.5 7.5 0 0 1 9.5 3a6 6 0 1 0 11.5 11.5Z" fill="currentColor"/></svg>
              </button>
              <span class="kbd-hint" title="کلید / برای جست‌وجو">/</span>
            </div>
          </header>

          <main class="content">
            <p class="page-subtitle" id="pageSubtitle">خلاصه وضعیت مرکز و اقدامات پیشنهادی</p>

            <section id="overview" class="section active">
            <?php if (Access::canWrite()): ?>
            <article class="panel panel--accent welcome-panel" id="welcomePanel">
              <h2>شروع سریع</h2>
              <div class="start-steps" id="startSteps">
                <button class="start-step" data-go="teams" type="button"><span>۱</span>ثبت نهادها</button>
                <button class="start-step" data-go="members" type="button"><span>۲</span>افزودن اعضا به نهاد</button>
                <button class="start-step" data-go="desks" type="button"><span>۳</span>تخصیص میز به نهاد</button>
                <button class="start-step" data-go="lockers" type="button"><span>۴</span>تعریف کمدها</button>
                <button class="start-step" data-go="charges" type="button"><span>۵</span>نرخ و شارژ</button>
              </div>
            </article>
            <?php endif; ?>

              <div id="cards" class="stat-cards"></div>
              <div id="opsCards" class="stat-cards stat-cards--ops"></div>

              <div class="grid two">
                <article class="panel">
                  <div class="panel-head"><h2>خلاصه ماه جاری</h2><span id="currentMonthLabel" class="hint"></span></div>
                  <div id="currentMonthSummary" class="month-grid"></div>
                </article>
                <article class="panel">
                  <div class="panel-head"><h2>کارهای امروز</h2><span class="hint">اولویت‌بندی اقدامات فوری</span></div>
                  <div id="actionItems" class="action-list"></div>
                </article>
              </div>

              <div class="grid two">
                <article class="panel"><div class="panel-head"><h2>شارژ ماهانه</h2></div><div id="chargeChart" class="bar-chart"></div></article>
                <article class="panel"><div class="panel-head"><h2>طلب از نهادها</h2><span class="hint">مطالبات مرکز — نهاد بدهکار، مرکز طلبکار</span></div><div id="debtChart" class="bar-chart"></div></article>
              </div>
            </section>

            <section id="teams" class="section">
              <p class="hint">هر نهاد را از دکمه <strong>پروفایل</strong> باز کنید — قرارداد، میز و بدهی هر سال در یکجا مدیریت می‌شود. ستون «وضعیت سال جاری» خلاصه قرارداد، میز و بدهی را نشان می‌دهد.</p>
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
              <p class="hint">همه قراردادهای سال جاری و سال‌های قبل — قراردادهای <strong>فعال</strong> (در بازه امروز) در بالای لیست نمایش داده می‌شوند.</p>
              <data-table title="قراردادهای نهادها" endpoint="api.php?resource=team_contracts"></data-table>
            </section>

            <section id="members" class="section">
              <p class="hint">اعضای تأییدشده در لیست اصلی نمایش داده می‌شوند. درخواست‌های نهادها در جدول «در انتظار تأیید» بررسی می‌شود. کد تردد پس از تأیید، به‌صورت حضوری و با تأخیر ثبت می‌شود.</p>
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
              <div class="sms-page-head">
                <p class="hint">ارسال پیامک از طریق <strong>ملی‌پیامک</strong>. تنظیمات API در بخش <button type="button" class="text-link" data-go="sms-settings">تنظیمات پیامک</button>. مدیر مشاهده‌گر فقط آمار و تاریخچه را می‌بیند.</p>
                <?php if (Access::canWrite()): ?>
                <button type="button" class="button ghost" data-go="sms-settings">تنظیمات پیامک</button>
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
              <p class="hint">اتصال REST ملی‌پیامک. حساب API و شماره خط ارسال را جداگانه وارد و ذخیره کنید.</p>
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
                <div class="modal-actions">
                  <?php if (Access::canWrite()): ?>
                  <button class="button ghost" type="button" id="smsTestConnection">تست اتصال API</button>
                  <button class="button ghost" type="button" id="smsRefreshLiveStats">بروزرسانی موجودی و تعرفه از API</button>
                  <button class="button ghost" type="button" id="smsSyncHistory">همگام‌سازی تاریخچه از API</button>
                  <?php endif; ?>
                </div>
              </article>
            </section>

            <section id="desks" class="section">
              <article class="panel">
                <div class="panel-head">
                  <h2>نقشه ۲۴ میز — سال جاری</h2>
                  <div class="desk-legend">
                    <span class="legend-item legend-free">آزاد</span>
                    <span class="legend-item legend-occupied">اشغال</span>
                    <span class="legend-item legend-highlight">انتخاب‌شده</span>
                  </div>
                </div>
                <p class="hint">۳ ردیف × ۸ میز — <?php if (Access::canWrite()): ?>روی هر میز کلیک کنید تا تخصیص سال جاری را ویرایش کنید.<?php else: ?>میزها به نهاد تخصیص می‌یابند.<?php endif; ?></p>
                <div id="deskGrid" class="desk-map"></div>
              </article>
              <data-table id="currentDesksTable" title="تخصیص سال جاری — جزئیات میزها" endpoint="api.php?resource=desks" data-no-add></data-table>
            </section>

            <section id="desk-history" class="section">
              <p class="hint">تاریخچه کامل تخصیص میزها — تخصیص‌های <strong>جاری</strong> در بالای لیست هستند. می‌توانید بر اساس نهاد فیلتر کنید.</p>
              <div class="member-filters" id="deskHistoryFilters"></div>
              <data-table id="deskAssignmentsTable" title="تاریخچه تخصیص میزها" endpoint="api.php?resource=desk-assignments" data-no-add></data-table>
            </section>

            <section id="lockers" class="section">
              <?php if (Access::isAdmin()): ?>
              <data-table title="درخواست کمد — در انتظار تأیید" endpoint="api.php?resource=pending-locker-requests" data-workflow="lockers" data-workflow-type="locker-request" data-table-key="pending-locker-requests" data-readonly></data-table>
              <?php endif; ?>
              <data-table title="کمدها" endpoint="api.php?resource=lockers"></data-table>
            </section>

            <section id="charges" class="section">
              <p class="hint">نرخ شارژ و اجاره <strong>به ازای هر میز</strong> است. فقط نهادهایی که در آن سال <strong>قرارداد</strong> و <strong>میز فعال</strong> دارند در کلاژ می‌آیند. اجاره غیررسمی فقط برای میزهای غیررسمی/ترکیبی محاسبه می‌شود.</p>
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
              <p class="hint">دفتر معین فقط <strong>گردش نقدی واقعی</strong> را نشان می‌دهد: واریز تأییدشده نهادها، درآمد و هزینه دستی. شارژ و مطالبات در بخش شارژ محاسبه می‌شود و اینجا تکرار نمی‌شود.</p>
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

            <?php if (Access::canWrite()): ?>
            <section id="development" class="section">
              <p class="hint">برنامه‌های جاری مرکز — عنوان، وضعیت، اولویت و موعد.</p>
              <data-table title="برنامه‌های توسعه" endpoint="api.php?resource=development_plans"></data-table>
            </section>
            <?php endif; ?>

            <?php if (Access::isAdmin()): ?>
            <section id="users" class="section">
              <p class="hint">مدیران سیستم — کاربران نهاد هنگام ثبت نهاد خودکار ساخته می‌شوند و نام کاربری/رمز در جدول نهادها نمایش داده می‌شود.</p>
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
    <?php endif; ?>
  </body>
</html>
