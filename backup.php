<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/DatabaseBackup.php';

$configured = is_file(__DIR__ . '/config.php');
$result = null;
$error = null;
$counts = [];

if (!$configured) {
    $error = 'ابتدا config.php را بسازید.';
} else {
    require_auth();
    if (!Access::canWrite()) {
        redirect_to('index.php');
    }

    try {
        $pdo = require_database();
        $backup = new DatabaseBackup($pdo);

        if (($_POST['action'] ?? '') === 'download') {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                throw new RuntimeException($csrfError);
            }
            $filename = $backup->suggestedFilename();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            echo $backup->exportJson();
            exit;
        }

        if (($_GET['action'] ?? '') === 'download') {
            throw new RuntimeException('برای دانلود پشتیبان از دکمه دانلود در صفحه استفاده کنید.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                throw new RuntimeException($csrfError);
            }

            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'restore') {
                if (($_POST['confirm_restore'] ?? '') !== '1') {
                    throw new RuntimeException('برای بازیابی، گزینه تأیید را فعال کنید.');
                }
                if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file'])) {
                    throw new RuntimeException('فایل پشتیبان را انتخاب کنید.');
                }
                $upload = $_FILES['backup_file'];
                if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('بارگذاری فایل ناموفق بود.');
                }
                $tmpPath = (string) ($upload['tmp_name'] ?? '');
                if ($tmpPath === '' || !is_readable($tmpPath)) {
                    throw new RuntimeException('فایل پشتیبان قابل خواندن نیست.');
                }
                $size = (int) ($upload['size'] ?? 0);
                if ($size <= 0) {
                    throw new RuntimeException('فایل پشتیبان خالی است.');
                }
                if ($size > 64 * 1024 * 1024) {
                    throw new RuntimeException('حداکثر اندازه فایل پشتیبان ۶۴ مگابایت است.');
                }

                $json = file_get_contents($tmpPath);
                if ($json === false || trim($json) === '') {
                    throw new RuntimeException('خواندن فایل پشتیبان ناموفق بود.');
                }

                $counts = $backup->import($json);
                $result = 'restore';
            }
        } else {
            $counts = $backup->export()['counts'] ?? [];
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : safe_error_message($exception);
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پشتیبان‌گیری و بازیابی — Mechinno</title>
    <?= Brand::headTags() ?>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css?v=<?= e((string) filemtime(__DIR__ . '/assets/styles.css')) ?>" />
  </head>
  <body class="standalone-page">
    <div class="bg-blobs" aria-hidden="true">
      <span class="blob blob-a"></span>
      <span class="blob blob-b"></span>
    </div>
    <main class="setup-screen">
      <section class="setup-card wide">
        <div class="setup-brand">
          <?= Brand::mark('hero') ?>
          <div class="setup-brand-copy">
            <strong>Mechinno</strong>
            <small>پشتیبان‌گیری کامل دیتابیس</small>
          </div>
        </div>

        <h1>پشتیبان‌گیری و بازیابی</h1>
        <p>یک فایل JSON شامل تمام داده‌های پنل (نهادها، اعضا، میزها، شارژ، مالی، کاربران، پیامک و …) دانلود یا بازیابی کنید.</p>

        <?php if ($error): ?>
          <div class="notice danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($result === 'restore'): ?>
          <div class="notice success">
            بازیابی با موفقیت انجام شد.
            <pre><?= htmlspecialchars(json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
          <p><a class="button" href="index.php">ورود به پنل</a></p>
        <?php elseif ($configured && !$error): ?>
          <div class="notice">
            <strong>وضعیت فعلی</strong>
            <pre><?= htmlspecialchars(json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
          </div>

          <div class="install-actions">
            <h2>دانلود پشتیبان</h2>
            <p class="hint">فایل JSON خوانا است و می‌توانید آن را ویرایش یا در جای امن نگه دارید.</p>
            <p class="hint warn">این فایل شامل هش رمزها و تنظیمات رمزنگاری‌شده پیامک است؛ فقط در محل امن نگهداری کنید و از کانال‌های عمومی ارسال نکنید.</p>
            <form method="post" class="install-actions">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="download" />
              <button class="button" type="submit">دانلود پشتیبان کامل</button>
            </form>
          </div>

          <form method="post" enctype="multipart/form-data" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="restore" />
            <h2>بازیابی از فایل</h2>
            <p class="hint warn">بازیابی، داده‌های فعلی را با محتوای فایل جایگزین می‌کند. قبل از بازیابی یک نسخه پشتیبان بگیرید.</p>
            <label class="wide">
              <span>فایل پشتیبان JSON</span>
              <input type="file" name="backup_file" accept=".json,application/json" required />
            </label>
            <label class="check-row">
              <input type="checkbox" name="confirm_restore" value="1" />
              <span>تأیید می‌کنم داده‌های فعلی با فایل پشتیبان جایگزین شوند.</span>
            </label>
            <button class="button danger" type="submit">بازیابی داده‌ها</button>
          </form>
        <?php endif; ?>

        <p><a class="button ghost" href="index.php">بازگشت به پنل</a></p>
      </section>
    </main>
  </body>
</html>
