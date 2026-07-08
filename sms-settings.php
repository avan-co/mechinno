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
    filemtime(__DIR__ . '/assets/sms-settings.js')
);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>تنظیمات پیامک — Mechinno</title>
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
  <body class="app-body panel-admin sms-settings-page">
    <?php if (!$isConfigured): ?>
      <main class="setup-screen"><section class="setup-card"><a class="button" href="install.php">نصب پنل</a></section></main>
    <?php else: ?>
      <div class="shell shell--compact">
        <div class="main-wrap" style="margin:0 auto; max-width:960px; padding:24px 16px 48px;">
          <header class="topbar" style="position:static; margin-bottom:18px;">
            <div class="topbar-start">
              <a class="button ghost" href="index.php#sms">بازگشت به پیامک</a>
            </div>
            <div class="topbar-title">
              <p class="topbar-eyebrow">پیامک</p>
              <h1>تنظیمات ملی‌پیامک</h1>
            </div>
            <div class="topbar-actions">
              <span class="date-chip"><?= e($today['formatted']) ?></span>
            </div>
          </header>

          <p class="hint">اتصال REST ملی‌پیامک. خطوط ارسال در اولین ذخیره استعلام می‌شوند؛ بعداً می‌توانید دستی استعلام بگیرید. هزینه هر پیامک از API خوانده می‌شود.</p>

          <article class="panel">
            <div class="panel-head"><h2>حساب و خط ارسال</h2></div>
            <form id="smsSettingsForm" class="payment-settings-form">در حال بارگذاری…</form>
          </article>

          <article class="panel">
            <div class="panel-head">
              <h2>الگوی یادآور شارژ</h2>
            </div>
            <p class="hint">برای ارسال دسته‌ای یادآور، همین الگو برای همه نهادهای انتخاب‌شده اعمال می‌شود.</p>
            <div id="smsChargeTemplateEditor"></div>
            <?php if (Access::canWrite()): ?>
            <div class="modal-actions">
              <button class="button" type="button" id="smsSaveTemplate">ذخیره الگو</button>
            </div>
            <?php endif; ?>
          </article>

          <article class="panel">
            <div class="panel-head"><h2>آمار و همگام‌سازی</h2></div>
            <div id="smsSettingsStats">در حال بارگذاری…</div>
            <div class="modal-actions">
              <?php if (Access::canWrite()): ?>
              <button class="button ghost" type="button" id="smsManualQueryLines">استعلام مجدد خطوط</button>
              <button class="button ghost" type="button" id="smsSyncHistory">همگام‌سازی تاریخچه از API</button>
              <?php endif; ?>
            </div>
          </article>
        </div>
      </div>

      <script>
        window.MECHINNO = <?= json_encode($authContext, JSON_UNESCAPED_UNICODE) ?>;
        window.MECHINNO.csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
      </script>
      <script src="assets/app.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/sms-editor.js?v=<?= e($assetVer) ?>"></script>
      <script src="assets/sms-settings.js?v=<?= e($assetVer) ?>"></script>
    <?php endif; ?>
  </body>
</html>
