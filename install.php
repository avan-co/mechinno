<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL یا PDO SQLite' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
    'ZipArchive' => class_exists('ZipArchive'),
    'SimpleXML' => extension_loaded('simplexml'),
    'JSON' => extension_loaded('json'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    try {
        require_auth();
        $csrfError = require_csrf_html();
        if ($csrfError !== null) {
            throw new RuntimeException($csrfError);
        }
        if (($_POST['confirm_import'] ?? '') !== '1') {
            throw new RuntimeException('برای جلوگیری از reset ناخواسته دیتابیس، گزینه تأیید ورود مجدد داده‌ها را فعال کنید.');
        }
        $pdo = Database::connect();
        Schema::migrate($pdo);
        $result = (new Importer($pdo, app_base_path()))->importAll();
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : safe_error_message($exception);
    }
} elseif ($configured) {
    require_auth();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>نصب پنل Mechinno</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <main class="setup-screen">
      <section class="setup-card wide">
        <span class="brand-mark">M</span>
        <h1>نصب پنل مدیریتی</h1>
        <p>ابتدا در cPanel یک MySQL Database و User بسازید، سپس <code>config.sample.php</code> را به <code>config.php</code> کپی کرده و اطلاعات دیتابیس و رمز عبور پنل را وارد کنید.</p>

        <div class="requirements">
          <?php foreach ($requirements as $name => $ok): ?>
            <div class="requirement <?= $ok ? 'ok' : 'bad' ?>">
              <span><?= $ok ? '✓' : '×' ?></span>
              <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!$configured): ?>
          <div class="notice danger">
            فایل <code>config.php</code> پیدا نشد. لطفاً <code>config.sample.php</code> را کپی و تنظیم کنید.
          </div>
        <?php else: ?>
          <div class="notice">
            این عملیات جداول مدیریتی را از فایل‌های Excel بازسازی می‌کند. قبل از import مجدد مطمئن شوید فایل‌های Excel نسخه صحیح هستند.
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="notice danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
          <div class="notice success">
            نصب و ورود اطلاعات انجام شد:
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
        <?php endif; ?>

        <form method="post">
          <?php if ($configured): ?>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <label class="check-row">
              <input type="checkbox" name="confirm_import" value="1" />
              <span>تأیید می‌کنم داده‌های فعلی از روی فایل‌های Excel بازسازی شوند.</span>
            </label>
          <?php endif; ?>
          <button class="button" type="submit" <?= $configured ? '' : 'disabled' ?>>ساخت دیتابیس و ورود داده‌ها</button>
          <a class="button ghost" href="index.php">بازگشت به پنل</a>
        </form>
      </section>
    </main>
  </body>
</html>
