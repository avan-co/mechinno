<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$configured = app_configured();
$error = null;
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
if ($next === '' || str_starts_with($next, 'http://') || str_starts_with($next, 'https://') || str_starts_with($next, '//')) {
    $next = 'index.php';
}

if ($configured) {
    try {
        $config = app_config();
        if (!Auth::isEnabled($config)) {
            redirect_to($next);
        }
        if (Auth::check()) {
            redirect_to($next);
        }
        if (!Auth::configured($config)) {
            $error = 'لطفاً قبل از ورود، در config.php نام کاربری و رمز عبور امن تنظیم کنید. مقدار CHANGE_ME قابل قبول نیست.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                $error = $csrfError;
            } elseif (Auth::attempt($config, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                redirect_to($next);
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    } catch (Throwable $exception) {
        $error = safe_error_message($exception);
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ورود به پنل Mechinno</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <main class="setup-screen">
      <section class="setup-card">
        <span class="brand-mark">M</span>
        <h1>ورود به پنل</h1>
        <p>برای مشاهده داده‌های مدیریتی، خروجی‌ها و عملیات import ابتدا وارد شوید.</p>

        <?php if (!$configured): ?>
          <div class="notice danger">فایل <code>config.php</code> هنوز ساخته نشده است. ابتدا تنظیمات نصب را انجام دهید.</div>
          <a class="button" href="install.php">رفتن به نصب</a>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="notice danger"><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="next" value="<?= e($next) ?>" />
            <label>
              <span>نام کاربری</span>
              <input name="username" autocomplete="username" required />
            </label>
            <label>
              <span>رمز عبور</span>
              <input name="password" type="password" autocomplete="current-password" required />
            </label>
            <button class="button" type="submit">ورود</button>
          </form>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
