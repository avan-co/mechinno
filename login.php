<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = app_configured();
$error = null;
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? Access::homePath());
if ($next === '' || str_starts_with($next, 'http://') || str_starts_with($next, 'https://') || str_starts_with($next, '//')) {
    $next = Access::homePath();
}

if ($configured) {
    try {
        $config = app_config();
        if (!Auth::isEnabled($config)) {
            redirect_to(Access::sanitizeNext($next));
        }
        if (Auth::check()) {
            redirect_to(Access::sanitizeNext($next));
        }
        if (!Auth::configured($config)) {
            $error = 'لطفاً قبل از ورود، در config.php نام کاربری و رمز عبور امن تنظیم کنید.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                $error = $csrfError;
            } else {
                $pdo = require_database();
                if (Auth::attempt($pdo, $config, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                    redirect_to(Access::sanitizeNext($next));
                }
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    } catch (Throwable $exception) {
        $error = safe_error_message($exception);
    }
}

$assetVer = (string) filemtime(__DIR__ . '/assets/styles.css');
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ورود — Mechinno</title>
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
  <body class="login-body">
    <div class="login-bg" aria-hidden="true">
      <span class="login-orb login-orb-a"></span>
      <span class="login-orb login-orb-b"></span>
    </div>

    <main class="login-screen">
      <section class="login-card">
        <div class="login-brand">
          <span class="brand-mark">M</span>
          <div>
            <strong>Mechinno</strong>
            <small>مرکز نوآوری مکانیک</small>
          </div>
        </div>

        <h1>ورود به پنل</h1>

        <?php if (!$configured): ?>
          <div class="notice danger">فایل <code>config.php</code> هنوز ساخته نشده است.</div>
          <a class="button login-submit" href="install.php">شروع نصب</a>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="notice danger"><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post" class="auth-form login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="next" value="<?= e($next) ?>" />
            <label>
              <span>نام کاربری</span>
              <input name="username" autocomplete="username" required autofocus />
            </label>
            <label>
              <span>رمز عبور</span>
              <input name="password" type="password" autocomplete="current-password" required />
            </label>
            <button class="button login-submit" type="submit">ورود</button>
          </form>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
