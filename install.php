<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$hasExistingData = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    try {
        require_auth();
        if (!Access::canWrite()) {
            throw new RuntimeException('فقط مدیر ویرایشگر می‌تواند پنل را بازنشانی کند.');
        }
        $csrfError = require_csrf_html();
        if ($csrfError !== null) {
            throw new RuntimeException($csrfError);
        }
        if (($_POST['confirm_import'] ?? '') !== '1') {
            throw new RuntimeException('برای ادامه، گزینه تأیید را فعال کنید.');
        }
        $pdo = Database::connect();
        $result = (new Installer($pdo))->installFresh();
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : safe_error_message($exception);
    }
} elseif ($configured) {
    require_auth();
    if (!Access::canWrite()) {
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
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>نصب پنل Mechinno</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e((string) filemtime(__DIR__ . '/assets/styles.css')) ?>" />
  </head>
  <body>
    <main class="setup-screen">
      <section class="setup-card wide">
        <span class="brand-mark">M</span>
        <h1>راه‌اندازی پنل</h1>
        <p>پنل با دیتابیس <strong>خالی</strong> نصب می‌شود — فقط ۲۴ میز آماده است. نهادها، اعضا، کمدها و مبالغ را خودتان وارد کنید.</p>

        <?php if (!$configured): ?>
          <div class="notice danger">ابتدا <code>config.sample.php</code> را به <code>config.php</code> کپی کنید.</div>
        <?php endif; ?>

        <?php if ($configured && $hasExistingData && !$result): ?>
          <div class="notice warn">داده قبلی پیدا شد. با نصب مجدد، همه رکوردها پاک و پنل خالی می‌شود.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="notice danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
          <div class="notice success">
            پنل خالی آماده است:
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
          <p><a class="button" href="index.php">ورود به پنل</a></p>
        <?php endif; ?>

        <form method="post">
          <?php if ($configured && !$result): ?>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <label class="check-row">
              <input type="checkbox" name="confirm_import" value="1" />
              <span>تأیید می‌کنم داده‌های فعلی پاک شود و پنل خالی ساخته شود.</span>
            </label>
            <button class="button" type="submit">نصب / بازنشانی پنل خالی</button>
          <?php endif; ?>
          <a class="button ghost" href="index.php">بازگشت</a>
        </form>
      </section>
    </main>
  </body>
</html>
