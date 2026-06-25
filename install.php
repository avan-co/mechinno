<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$hasExistingData = false;
$existingCounts = [];
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
            throw new RuntimeException('برای ادامه، گزینه تأیید را فعال کنید.');
        }
        $pdo = Database::connect();
        Schema::migrate($pdo);
        $result = (new Importer($pdo, app_base_path()))->importAll();
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : safe_error_message($exception);
    }
} elseif ($configured) {
    require_auth();
    try {
        $pdo = Database::connect();
        Schema::migrate($pdo);
        $hasExistingData = Schema::hasData($pdo);
        if ($hasExistingData) {
            foreach (['teams', 'members', 'charges', 'transactions', 'lockers'] as $table) {
                $existingCounts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            }
        }
    } catch (Throwable) {
        // اتصال یا migrate در مرحله نمایش فرم شکست خورد — پیام در submit نشان داده می‌شود.
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>نصب پنل Mechinno</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <main class="setup-screen">
      <section class="setup-card wide">
        <span class="brand-mark">M</span>
        <h1>نصب پنل مدیریتی</h1>
        <p>ابتدا <code>config.sample.php</code> را به <code>config.php</code> کپی کنید. داده اولیه از <code>data/install-bundle.json</code> بارگذاری می‌شود — نیازی به آپلود Excel روی سرور نیست.</p>

        <div class="requirements">
          <?php foreach ($requirements as $name => $ok): ?>
            <div class="requirement <?= $ok ? 'ok' : 'bad' ?>">
              <span><?= $ok ? '✓' : '×' ?></span>
              <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!$configured): ?>
          <div class="notice danger">فایل <code>config.php</code> پیدا نشد.</div>
        <?php endif; ?>

        <?php if ($configured && $hasExistingData && !$result): ?>
          <div class="notice warn">
            <strong>دیتابیس قبلی پیدا شد.</strong>
            نیازی به پاک کردن دستی جداول نیست — با زدن دکمه نصب، همه داده‌های قبلی به‌طور خودکار پاک و دوباره از bundle بارگذاری می‌شود.
            <?php if ($existingCounts !== []): ?>
              <ul class="install-counts">
                <?php foreach ($existingCounts as $table => $count): ?>
                  <li><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?>: <?= number_format($count) ?> رکورد</li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="notice danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
          <div class="notice success">
            نصب انجام شد:
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
          <div class="notice success post-install-guide">
            <strong>بعد از نصب این فایل‌ها را حذف کنید (امنیت):</strong>
            <ul>
              <li><code>install.php</code> — جلوگیری از نصب مجدد تصادفی</li>
              <li><code>Innovation Center.xlsx</code>، <code>CHARGE.xlsx</code>، <code>finance.xlsx</code> — اگر آپلود کرده‌اید (روی سرور لازم نیست)</li>
              <li>پوشه <code>tools/</code> — فقط برای ساخت bundle در محیط توسعه</li>
            </ul>
            <strong>حذف نکنید:</strong>
            <ul>
              <li><code>config.php</code> — تنظیمات دیتابیس و ورود</li>
              <li><code>data/install-bundle.json</code> — برای بازنشانی داده از داخل پنل</li>
              <li>بقیه فایل‌های پنل (<code>index.php</code>، <code>api.php</code>، <code>src/</code>، …)</li>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post">
          <?php if ($configured): ?>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <label class="check-row">
              <input type="checkbox" name="confirm_import" value="1" />
              <span>تأیید می‌کنم داده‌های فعلی دیتابیس پاک شده و دوباره نصب شوند.</span>
            </label>
          <?php endif; ?>
          <button class="button" type="submit" <?= $configured ? '' : 'disabled' ?>>نصب / بازنصب دیتابیس</button>
          <a class="button ghost" href="index.php">بازگشت به پنل</a>
        </form>
      </section>
    </main>
  </body>
</html>
