<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$hasExistingData = false;
$action = '';
$installDisabled = false;

if ($configured) {
    $installConfig = require __DIR__ . '/config.php';
    $installDisabled = ($installConfig['install_enabled'] ?? true) === false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    try {
        if ($installDisabled) {
            throw new RuntimeException('صفحه نصب در تنظیمات غیرفعال شده است. برای فعال‌سازی موقت، install_enabled را در config.php روی true بگذارید.');
        }
        require_auth();
        if (!Access::canWrite()) {
            throw new RuntimeException('فقط مدیر ویرایشگر می‌تواند پنل را بازنشانی کند.');
        }
        $csrfError = require_csrf_html();
        if ($csrfError !== null) {
            throw new RuntimeException($csrfError);
        }

        $action = (string) ($_POST['action'] ?? 'reset');
        $pdo = Database::connect();
        $installer = new Installer($pdo);

        if ($action === 'sync') {
            $result = $installer->syncDatabase();
        } else {
            if (($_POST['confirm_import'] ?? '') !== '1') {
                throw new RuntimeException('برای بازنشانی کامل، گزینه تأیید را فعال کنید.');
            }
            $result = $installer->installFresh();
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : safe_error_message($exception);
    }
} elseif ($configured) {
    require_auth();
    if (!Access::canWrite()) {
        redirect_to('index.php');
    }
    if ($installDisabled) {
        redirect_to('index.php');
    }
    try {
        $pdo = Database::connect();
        Schema::migrate($pdo);
        $hasExistingData = Schema::hasData($pdo);
    } catch (Throwable) {
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>نصب پنل Mechinno</title>
    <?= Brand::headTags() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e((string) filemtime(__DIR__ . '/assets/styles.css')) ?>" />
    <script>
      (function () {
        try {
          var t = localStorage.getItem("mechinno-theme");
          if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t);
        } catch (e) {}
      })();
    </script>
  </head>
  <body class="standalone-page">
    <div class="bg-blobs" aria-hidden="true">
      <span class="blob blob-a"></span>
      <span class="blob blob-b"></span>
      <span class="blob blob-c"></span>
    </div>
    <main class="setup-screen">
      <section class="setup-card wide">
        <div class="setup-brand">
          <?= Brand::mark('hero') ?>
          <div class="setup-brand-copy">
            <strong>Mechinno</strong>
            <small>مرکز نوآوری مکانیک</small>
          </div>
        </div>
        <h1>راه‌اندازی پنل</h1>
        <p>دو حالت دارید: <strong>همگام‌سازی دیتابیس</strong> (بدون حذف داده) یا <strong>بازنشانی کامل</strong> (پنل خالی با ۲۴ میز).</p>

        <?php if (!$configured): ?>
          <div class="notice danger">ابتدا <code>config.sample.php</code> را به <code>config.php</code> کپی کنید.</div>
        <?php endif; ?>

        <?php if ($configured && $hasExistingData && !$result): ?>
          <div class="notice warn">داده قبلی پیدا شد. برای همگام‌سازی، داده‌ها حفظ می‌شوند. برای بازنشانی کامل، همه رکوردها پاک می‌شود.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="notice danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
          <div class="notice success">
            <?= $action === 'sync'
              ? 'دیتابیس با نسخه فعلی کد همگام شد (جداول، تخصیص میزها، حذف ردیف‌های تکراری).'
              : 'پنل خالی آماده است:' ?>
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
          <p><a class="button" href="index.php">ورود به پنل</a></p>
        <?php endif; ?>

        <?php if ($configured && !$result): ?>
          <form method="post" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="sync" />
            <p class="hint">همگام‌سازی: جداول جدید ساخته می‌شود، تاریخ پایان تخصیص‌های قدیمی پر می‌شود و ردیف‌های تکراری میز حذف می‌شود.</p>
            <button class="button" type="submit">همگام‌سازی دیتابیس با کد</button>
          </form>

          <form method="post" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="reset" />
            <label class="check-row">
              <input type="checkbox" name="confirm_import" value="1" />
              <span>تأیید می‌کنم داده‌های فعلی پاک شود و پنل خالی ساخته شود.</span>
            </label>
            <button class="button danger" type="submit">بازنشانی کامل پنل</button>
          </form>
        <?php endif; ?>

        <p><a class="button ghost" href="index.php">بازگشت</a></p>
      </section>
    </main>
  </body>
</html>
