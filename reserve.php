<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = app_configured();
$error = null;
if ($configured) {
    try {
        $pdo = require_database();
        $settings = (new RoomReservations($pdo))->settings();
        if (!$settings['room_public_enabled']) {
            $error = 'رزرو عمومی اتاق جلسه در حال حاضر غیرفعال است.';
        }
    } catch (Throwable $exception) {
        $error = safe_error_message($exception);
    }
} else {
    $error = 'سیستم هنوز راه‌اندازی نشده است.';
}

$today = JalaliDate::todayParts();
$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
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
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
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
  <body class="login-body room-public-body">
    <div class="login-bg" aria-hidden="true">
      <span class="login-orb login-orb-a"></span>
      <span class="login-orb login-orb-b"></span>
    </div>

    <main class="room-public-screen">
      <header class="room-public-header">
        <div>
          <p class="room-public-eyebrow">مرکز نوآوری مکانیک</p>
          <h1>رزرو اتاق جلسه</h1>
          <p class="room-public-subtitle">بدون نیاز به ورود — حداکثر ۲ ساعت در روز برای هر شماره موبایل</p>
        </div>
        <a class="button ghost" href="login.php">ورود به پنل</a>
      </header>

      <?php if ($error): ?>
        <section class="room-public-card">
          <p class="hint warn"><?= e($error) ?></p>
        </section>
      <?php else: ?>
        <div id="roomPublicMessage" class="room-public-message" hidden></div>

        <section class="room-public-card" id="bookingSuccess" hidden></section>

        <section class="room-public-card">
          <h2>ثبت رزرو جدید</h2>
          <form id="bookingForm" class="room-public-form">
            <div class="crud-grid">
              <label>
                <span>اتاق</span>
                <select id="roomSelect" name="room_id" required></select>
              </label>
              <label>
                <span>تاریخ (شمسی)</span>
                <input id="reserveDate" name="reserved_date" type="text" required placeholder="1404/01/01" value="<?= e($today['formatted']) ?>" />
              </label>
              <label>
                <span>تعداد بازه</span>
                <select id="durationSlots" name="duration_slots">
                  <option value="1">۱ ساعت</option>
                  <option value="2">۲ ساعت</option>
                </select>
              </label>
              <label class="wide">
                <span>بازه‌های آزاد</span>
                <div id="slotGrid" class="room-slot-grid"></div>
                <p class="hint" id="timePreview"></p>
              </label>
              <label><span>نام *</span><input name="booker_name" type="text" required /></label>
              <label><span>موبایل *</span><input name="booker_phone" type="tel" required placeholder="09123456789" dir="ltr" class="ltr-input" /></label>
              <label><span>سازمان / نهاد</span><input name="booker_org" type="text" /></label>
              <label class="wide"><span>موضوع جلسه</span><textarea name="purpose" rows="2"></textarea></label>
            </div>
            <div class="form-actions">
              <button class="button" type="submit">ثبت رزرو</button>
            </div>
          </form>
        </section>

        <section class="room-public-card">
          <h2>پیگیری یا لغو</h2>
          <form id="lookupForm" class="room-public-form room-public-form--inline">
            <label class="wide">
              <span>کد پیگیری</span>
              <input name="token" type="text" required dir="ltr" class="ltr-input" placeholder="کد ۳۲ کاراکتری" />
            </label>
            <button class="button ghost" type="submit">جست‌وجو</button>
          </form>
          <div id="lookupResult" class="room-lookup-result" hidden></div>
        </section>
      <?php endif; ?>
    </main>

    <?php if (!$error): ?>
      <script src="assets/room-public.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
