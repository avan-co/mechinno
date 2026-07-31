<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$dbError = null;
$hasExistingData = false;
$action = '';
$installDisabled = false;
$dbReady = false;
$alreadyInstalled = false;
$requiresAuth = false;

if ($configured) {
    /** @var array<string, mixed> $installConfig */
    $installConfig = require __DIR__ . '/config.php';
    $installDisabled = ($installConfig['install_enabled'] ?? true) === false;

    try {
        $pdo = Database::connect();
        Schema::migrate($pdo);
        $dbReady = true;
        $hasExistingData = Schema::hasData($pdo);
        $userCount = Schema::tableExists($pdo, 'panel_users')
            ? (int) $pdo->query('SELECT COUNT(*) FROM panel_users')->fetchColumn()
            : 0;
        // Desks may be seeded by migrate alone — that does NOT mean install finished.
        // Require auth only after real data or bootstrap users exist.
        $alreadyInstalled = $hasExistingData || $userCount > 0;
    } catch (Throwable $exception) {
        $dbError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : safe_error_message($exception);
    }
}

// Auth is only required after a successful prior install. First-time setup
// (or broken DB credentials) must remain reachable so admins can recover.
$requiresAuth = $configured && $dbReady && $alreadyInstalled;

if ($requiresAuth && $installDisabled) {
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    try {
        if ($installDisabled && $alreadyInstalled) {
            throw new RuntimeException('صفحه نصب در تنظیمات غیرفعال شده است. برای فعال‌سازی موقت، install_enabled را در config.php روی true بگذارید.');
        }

        if ($requiresAuth) {
            require_auth();
            if (!Access::canWrite()) {
                throw new RuntimeException('فقط مدیر ویرایشگر می‌تواند پنل را بازنشانی کند.');
            }
        } else {
            // First-time / recovery install: verify config.php admin password.
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                throw new RuntimeException($csrfError);
            }
            $setupUser = trim((string) ($_POST['setup_username'] ?? ''));
            $setupPass = (string) ($_POST['setup_password'] ?? '');
            $auth = is_array($installConfig['auth'] ?? null) ? $installConfig['auth'] : [];
            $expectedUser = (string) ($auth['username'] ?? 'admin');
            $passwordHash = (string) ($auth['password_hash'] ?? '');
            $plainPassword = (string) ($auth['password'] ?? '');
            $userOk = $expectedUser !== '' && hash_equals($expectedUser, $setupUser);
            $passOk = $passwordHash !== ''
                ? password_verify($setupPass, $passwordHash)
                : ($plainPassword !== '' && hash_equals($plainPassword, $setupPass));
            if (!$userOk || !$passOk) {
                throw new RuntimeException('نام کاربری یا رمز مدیر در config.php نادرست است.');
            }
        }

        if ($requiresAuth) {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                throw new RuntimeException($csrfError);
            }
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
            // Create a session so the admin can enter the panel immediately.
            if (!Auth::check()) {
                $auth = is_array($installConfig['auth'] ?? null) ? $installConfig['auth'] : [];
                Auth::login((string) ($auth['username'] ?? 'admin'));
            }
            UserAccounts::ensureBootstrapUsers($pdo, $installConfig);
        }

        $dbReady = true;
        $dbError = null;
        $hasExistingData = Schema::hasData($pdo);
        $alreadyInstalled = true;
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : safe_error_message($exception);
    }
} elseif ($requiresAuth) {
    require_auth();
    if (!Access::canWrite()) {
        redirect_to('index.php');
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
          <div class="notice danger">
            ابتدا <code>config.sample.php</code> را به <code>config.php</code> کپی کنید، سپس بخش <code>db</code> و رمزهای ورود را تنظیم کنید.
            <ol class="hint" style="margin:10px 0 0;padding-inline-start:1.2em">
              <li>دیتابیس و کاربر MySQL را در cPanel بسازید</li>
              <li><code>database</code> / <code>username</code> / <code>password</code> را در config.php وارد کنید</li>
              <li><code>auth.password</code> را از مقدار نمونه عوض کنید</li>
              <li>این صفحه را دوباره باز کنید</li>
            </ol>
          </div>
        <?php endif; ?>

        <?php if ($configured && $dbError): ?>
          <div class="notice danger">
            <strong>اتصال به دیتابیس برقرار نشد.</strong>
            <div style="margin-top:8px"><?= e($dbError) ?></div>
            <p class="hint" style="margin-top:10px">
              مقادیر <code>db.host</code>، <code>db.database</code>، <code>db.username</code> و <code>db.password</code>
              را در <code>config.php</code> بررسی کنید. اگر هنوز مقدارهایی مثل
              <code>YOUR_DB_NAME</code> دارید، باید با اطلاعات واقعی cPanel جایگزین شوند.
            </p>
          </div>
        <?php endif; ?>

        <?php if ($configured && $installDisabled && !$alreadyInstalled): ?>
          <div class="notice warn">
            <code>install_enabled</code> روی <code>false</code> است، اما به‌خاطر نصب‌نشدن پنل، نصب اولیه همچنان مجاز است.
            بعد از نصب موفق بهتر است این مقدار را <code>false</code> نگه دارید.
          </div>
        <?php endif; ?>

        <?php if ($configured && $hasExistingData && !$result): ?>
          <div class="notice warn">داده قبلی پیدا شد. برای همگام‌سازی، داده‌ها حفظ می‌شوند. برای بازنشانی کامل، همه رکوردها پاک می‌شود.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="notice danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
          <div class="notice success">
            <?= $action === 'sync'
              ? 'دیتابیس با نسخه فعلی کد همگام شد (جداول، تخصیص میزها، حذف ردیف‌های تکراری).'
              : 'پنل خالی آماده است:' ?>
            <pre><?= e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
          </div>
          <p><a class="button" href="index.php">ورود به پنل</a></p>
        <?php endif; ?>

        <?php if ($configured && !$result && $dbReady && !($installDisabled && $alreadyInstalled)): ?>
          <?php if (!$requiresAuth): ?>
            <div class="notice">
              برای نصب اولیه، نام کاربری و رمز مدیر را از <code>config.php</code>
              (بخش <code>auth</code>) وارد کنید.
            </div>
          <?php endif; ?>

          <form method="post" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="sync" />
            <?php if (!$requiresAuth): ?>
              <label>
                <span>نام کاربری مدیر (config.php)</span>
                <input name="setup_username" autocomplete="username" required value="admin" />
              </label>
              <label>
                <span>رمز مدیر (config.php)</span>
                <input name="setup_password" type="password" autocomplete="current-password" required />
              </label>
            <?php endif; ?>
            <p class="hint">همگام‌سازی: جداول جدید ساخته می‌شود، تاریخ پایان تخصیص‌های قدیمی پر می‌شود و ردیف‌های تکراری میز حذف می‌شود.</p>
            <button class="button" type="submit">همگام‌سازی دیتابیس با کد</button>
          </form>

          <form method="post" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="reset" />
            <?php if (!$requiresAuth): ?>
              <label>
                <span>نام کاربری مدیر (config.php)</span>
                <input name="setup_username" autocomplete="username" required value="admin" />
              </label>
              <label>
                <span>رمز مدیر (config.php)</span>
                <input name="setup_password" type="password" autocomplete="current-password" required />
              </label>
            <?php endif; ?>
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
