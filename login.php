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
                $throttleMessage = Auth::throttleMessage((string) ($_POST['username'] ?? ''));
                $error = $throttleMessage ?? 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    } catch (Throwable $exception) {
        $error = safe_error_message($exception);
    }
}

$assetVer = (string) max(
    filemtime(__DIR__ . '/assets/styles.css'),
    (int) Brand::version()
);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ورود — Mechinno</title>
    <?= Brand::headTags() ?>
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
        <div class="login-top-bar">
          <button class="theme-toggle login-theme-toggle" id="themeToggle" type="button" title="تغییر تم" aria-label="تغییر تم">
            <span class="theme-toggle-track" aria-hidden="true">
              <svg class="icon-sun" viewBox="0 0 24 24" width="18" height="18"><path d="M12 18a6 6 0 1 1 6-6 6 6 0 0 1-6 6Zm0-16h2v3h-2V2Zm0 19h2v3h-2v-3ZM2 11h3v2H2v-2Zm19 0h3v2h-3v-2Z" fill="currentColor"/></svg>
              <svg class="icon-moon" viewBox="0 0 24 24" width="18" height="18"><path d="M21 14.5A7.5 7.5 0 0 1 9.5 3a6 6 0 1 0 11.5 11.5Z" fill="currentColor"/></svg>
            </span>
            <span class="theme-toggle-label">تم</span>
          </button>
        </div>
        <div class="login-brand">
          <?= Brand::mark('hero') ?>
          <div class="login-brand-copy">
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
    <script>
      document.getElementById("themeToggle")?.addEventListener("click", () => {
        const html = document.documentElement;
        const next = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
        html.setAttribute("data-theme", next);
        try { localStorage.setItem("mechinno-theme", next); } catch (e) {}
      });
    </script>
  </body>
</html>
