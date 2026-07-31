<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = app_configured();
$error = null;
$settings = [
    'room_max_hours_per_day' => 2,
    'room_slot_minutes' => 30,
    'room_max_advance_days' => 14,
];
if ($configured) {
    try {
        $pdo = public_database();
        $settings = (new RoomReservations($pdo))->settings();
        if (!$settings['room_public_enabled']) {
            $error = 'رزرو عمومی اتاق جلسه در حال حاضر غیرفعال است.';
        }
    } catch (Throwable $exception) {
        $error = public_page_error($exception);
    }
} else {
    $error = 'سیستم هنوز راه‌اندازی نشده است. لطفاً با مدیر مرکز تماس بگیرید.';
}

$today = JalaliDate::todayParts();
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
    filemtime(__DIR__ . '/assets/room.css'),
    filemtime(__DIR__ . '/assets/room-public.js'),
    (int) Brand::version()
);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#16324f" />
    <title>رزرو اتاق جلسه — Mechinno</title>
    <?= Brand::headTags() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($assetVer) ?>" />
    <link rel="stylesheet" href="assets/room.css?v=<?= e($assetVer) ?>" />
  </head>
  <body class="room-public-body">
    <div class="pub-shell">
      <header class="pub-top">
        <div class="pub-brand">
          <?= Brand::mark('compact') ?>
          <div>
            <p class="pub-eyebrow">مرکز نوآوری مکانیک</p>
            <h1>رزرو اتاق جلسه</h1>
          </div>
        </div>
        <button type="button" class="pub-icon-btn" id="themeToggle" aria-label="تغییر تم" title="تغییر تم">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 18a6 6 0 1 1 6-6 6 6 0 0 1-6 6Z" fill="currentColor"/></svg>
        </button>
      </header>

      <?php if ($error): ?>
        <div class="room-alert room-alert--error"><?= e($error) ?></div>
      <?php else: ?>
        <div id="roomPublicMessage" class="room-alert" hidden></div>
        <section class="room-card room-success-card" id="bookingSuccess" hidden></section>

        <nav class="pub-steps" aria-label="مراحل رزرو">
          <button type="button" class="pub-step is-active" data-step-pill="1"><span>۱</span>اتاق</button>
          <button type="button" class="pub-step" data-step-pill="2"><span>۲</span>زمان</button>
          <button type="button" class="pub-step" data-step-pill="3"><span>۳</span>ثبت</button>
        </nav>

        <section class="pub-week-card" id="weekStatusCard" aria-label="وضعیت هفته جاری">
          <div class="pub-week-head">
            <div>
              <h2>هفته جاری</h2>
              <p class="hint" id="weekRangeLabel">در حال بارگذاری…</p>
            </div>
            <select id="weekRoomFilter" aria-label="فیلتر اتاق هفته">
              <option value="0">همه اتاق‌ها</option>
            </select>
          </div>
          <div id="weekStrip" class="pub-week-strip"></div>
        </section>

        <section class="room-card" id="stepRooms">
          <h2>انتخاب اتاق</h2>
          <p class="room-card-lead">اتاق جلسه را انتخاب کنید.</p>
          <div id="roomCardGrid" class="room-room-grid" role="listbox" aria-label="لیست اتاق‌ها"></div>
        </section>

        <section class="room-card" id="stepSchedule" hidden>
          <h2>تاریخ و ساعت</h2>
          <p class="room-card-lead">روز را بزنید، بعد مثل هتل ابتدا شروع و سپس پایان را انتخاب کنید. حداکثر <?= (int) ($settings['room_max_hours_per_day'] ?? 2) ?> ساعت.</p>

          <div class="room-month-picker pub-month-picker" id="publicMonthPicker">
            <div class="room-month-toolbar">
              <button type="button" class="button ghost" id="publicMonthPrev" aria-label="ماه قبل">‹</button>
              <strong id="publicMonthLabel">—</strong>
              <button type="button" class="button ghost" id="publicMonthNext" aria-label="ماه بعد">›</button>
            </div>
            <div class="room-month-weekdays" aria-hidden="true">
              <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
            </div>
            <div id="publicMonthGrid" class="room-month-grid"></div>
          </div>

          <p class="hint" id="publicSelectedDayLabel">روز: <?= e(fa_digits($today['formatted'])) ?></p>
          <div class="room-slot-legend">
            <span class="free">آزاد</span>
            <span class="range">انتخاب‌شده</span>
            <span class="pending">انتظار</span>
            <span class="busy">پر</span>
          </div>
          <div id="slotGrid" class="room-slot-grid"></div>
          <p class="hint" id="timePreview"></p>
          <input type="hidden" id="reserveDate" value="<?= e($today['formatted']) ?>" />
        </section>

        <section class="room-card" id="stepDetails" hidden>
          <h2>اطلاعات شما</h2>
          <p class="room-card-lead">برای ثبت رزرو، تماس خود را وارد کنید.</p>
          <div class="pub-summary-inline">
            <div><span>اتاق</span><strong id="summaryRoom">—</strong></div>
            <div><span>تاریخ</span><strong id="summaryDate">—</strong></div>
            <div><span>ساعت</span><strong id="summaryTime">—</strong></div>
          </div>
          <form id="bookingForm">
            <div class="room-field-row">
              <label><span>نام و نام خانوادگی *</span><input name="booker_name" type="text" required autocomplete="name" /></label>
              <label><span>موبایل *</span><input name="booker_phone" type="tel" required inputmode="tel" placeholder="09123456789" dir="ltr" class="ltr-input" autocomplete="tel" /></label>
              <label><span>سازمان / نهاد</span><input name="booker_org" type="text" autocomplete="organization" /></label>
              <label class="wide"><span>موضوع جلسه</span><textarea name="purpose" rows="3" placeholder="اختیاری"></textarea></label>
            </div>
            <div class="form-actions pub-sticky-actions">
              <button class="button ghost" type="button" id="backToSchedule">بازگشت</button>
              <button class="button" type="submit">ثبت رزرو</button>
            </div>
          </form>
        </section>

        <div class="pub-bottom-bar" id="pubBottomBar">
          <div class="pub-bottom-copy">
            <strong id="bottomSummary">اتاق را انتخاب کنید</strong>
            <small id="bottomHint">مرحله ۱ از ۳</small>
          </div>
          <button class="button" type="button" id="nextStepButton" disabled>ادامه</button>
        </div>

        <details class="pub-lookup">
          <summary>پیگیری یا لغو رزرو قبلی</summary>
          <form id="lookupForm" class="room-field-row">
            <label class="wide">
              <span>کد پیگیری</span>
              <input name="token" type="text" required dir="ltr" class="ltr-input" placeholder="کد دریافتی پس از رزرو" />
            </label>
            <div class="form-actions">
              <button class="button ghost" type="submit">جست‌وجو</button>
            </div>
          </form>
          <div id="lookupResult" hidden></div>
        </details>
      <?php endif; ?>

      <footer class="pub-foot">
        <a href="login.php">ورود به پنل</a>
        <span>Mechinno</span>
      </footer>
    </div>

    <?php if (!$error): ?>
      <script>
        window.MECHINNO_PUBLIC = {
          today: <?= json_encode($today['formatted'], JSON_UNESCAPED_UNICODE) ?>,
          year: <?= (int) $today['year'] ?>,
          month: <?= (int) $today['month'] ?>,
          maxHours: <?= (int) ($settings['room_max_hours_per_day'] ?? 2) ?>,
          slotMinutes: <?= (int) ($settings['room_slot_minutes'] ?? 30) ?>,
        };
      </script>
      <script src="assets/room-public.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
