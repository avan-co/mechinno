<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = app_configured();
$error = null;
$settings = null;
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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>رزرو اتاق جلسه — Mechinno</title>
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
  <body class="room-public-body">
    <div class="bg-blobs" aria-hidden="true">
      <span class="blob blob-a"></span>
      <span class="blob blob-b"></span>
    </div>

    <div class="room-app">
      <header class="room-app-header">
        <div class="room-app-brand">
          <?= Brand::mark('compact') ?>
          <div>
            <p class="room-app-eyebrow">مرکز نوآوری مکانیک</p>
            <h1>رزرو آنلاین اتاق جلسه</h1>
            <p class="room-app-subtitle">اتاق را انتخاب کنید، بازه زمانی را مشخص کنید و رزرو را در چند ثانیه ثبت کنید.</p>
          </div>
        </div>
        <a class="button ghost" href="login.php">ورود به پنل</a>
      </header>

      <?php if ($error): ?>
        <div class="room-alert room-alert--error"><?= e($error) ?></div>
      <?php else: ?>
        <div class="room-steps" aria-label="مراحل رزرو">
          <span class="room-step-pill is-active" data-step-pill="1"><span class="room-step-num">۱</span> انتخاب اتاق</span>
          <span class="room-step-pill" data-step-pill="2"><span class="room-step-num">۲</span> زمان</span>
          <span class="room-step-pill" data-step-pill="3"><span class="room-step-num">۳</span> اطلاعات</span>
        </div>

        <div id="roomPublicMessage" class="room-alert" hidden></div>
        <section class="room-card room-success-card" id="bookingSuccess" hidden></section>

        <div class="room-layout" id="bookingLayout">
          <div class="room-main">
            <section class="room-card" id="stepRooms">
              <h2>انتخاب اتاق</h2>
              <p class="room-card-lead">اتاق مناسب جلسه خود را انتخاب کنید.</p>
              <div id="roomCardGrid" class="room-room-grid" role="listbox" aria-label="لیست اتاق‌ها"></div>
            </section>

            <section class="room-card" id="stepSchedule" hidden>
              <h2>تاریخ و ساعت</h2>
              <p class="room-card-lead">روز و بازه‌های آزاد را انتخاب کنید. حداکثر <?= (int) ($settings['room_max_hours_per_day'] ?? 2) ?> ساعت در روز.</p>
              <div class="room-field-row">
                <label>
                  <span>تاریخ (شمسی)</span>
                  <input id="reserveDate" name="reserved_date" type="text" required placeholder="1404/01/01" value="<?= e($today['formatted']) ?>" />
                </label>
                <label>
                  <span>مدت رزرو</span>
                  <select id="durationSlots" name="duration_slots">
                    <option value="1">۳۰ دقیقه</option>
                    <option value="2" selected>۱ ساعت</option>
                    <option value="3">۱٫۵ ساعت</option>
                    <option value="4">۲ ساعت</option>
                  </select>
                </label>
              </div>
              <div class="room-slot-legend">
                <span class="free">آزاد</span>
                <span class="pending">در انتظار</span>
                <span class="busy">رزرو شده</span>
              </div>
              <div id="slotGrid" class="room-slot-grid"></div>
              <p class="hint" id="timePreview"></p>
            </section>

            <section class="room-card" id="stepDetails" hidden>
              <h2>اطلاعات رزروکننده</h2>
              <p class="room-card-lead">برای تأیید رزرو، اطلاعات تماس را وارد کنید.</p>
              <form id="bookingForm">
                <div class="room-field-row">
                  <label><span>نام و نام خانوادگی *</span><input name="booker_name" type="text" required autocomplete="name" /></label>
                  <label><span>موبایل *</span><input name="booker_phone" type="tel" required placeholder="09123456789" dir="ltr" class="ltr-input" autocomplete="tel" /></label>
                  <label><span>سازمان / نهاد</span><input name="booker_org" type="text" autocomplete="organization" /></label>
                  <label class="wide"><span>موضوع جلسه</span><textarea name="purpose" rows="3" placeholder="اختیاری"></textarea></label>
                </div>
                <div class="form-actions">
                  <button class="button ghost" type="button" id="backToSchedule">بازگشت</button>
                  <button class="button" type="submit">ثبت نهایی رزرو</button>
                </div>
              </form>
            </section>
          </div>

          <aside class="room-summary">
            <div class="room-card">
              <h2>خلاصه رزرو</h2>
              <div class="room-summary-item"><span>اتاق</span><strong id="summaryRoom">—</strong></div>
              <div class="room-summary-item"><span>تاریخ</span><strong id="summaryDate">—</strong></div>
              <div class="room-summary-item"><span>ساعت</span><strong id="summaryTime">—</strong></div>
              <div class="form-actions" style="margin-top:16px">
                <button class="button" type="button" id="nextStepButton" disabled>ادامه</button>
              </div>
            </div>

            <div class="room-card room-lookup-panel">
              <h2>پیگیری / لغو</h2>
              <p class="room-card-lead">کد پیگیری دریافتی پس از رزرو را وارد کنید.</p>
              <form id="lookupForm">
                <label class="wide">
                  <span>کد پیگیری</span>
                  <input name="token" type="text" required dir="ltr" class="ltr-input" placeholder="کد ۳۲ کاراکتری" />
                </label>
                <div class="form-actions">
                  <button class="button ghost" type="submit">جست‌وجو</button>
                </div>
              </form>
              <div id="lookupResult" hidden></div>
            </div>
          </aside>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$error): ?>
      <script src="assets/room-public.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
