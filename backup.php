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

        if (($_POST['action'] ?? '') === 'download' || ($_POST['action'] ?? '') === 'download_zip') {
            $csrfError = require_csrf_html();
            if ($csrfError !== null) {
                throw new RuntimeException($csrfError);
            }
            $wantZip = ($_POST['action'] ?? '') === 'download_zip';
            if ($wantZip) {
                $archive = $backup->exportArchive();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $archive['filename'] . '"');
                header('Content-Length: ' . (string) $archive['bytes']);
                header('Cache-Control: no-store');
                readfile($archive['path']);
                @unlink($archive['path']);
                exit;
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
                $original = (string) ($upload['name'] ?? '');
                $counts = $backup->importFromUpload($tmpPath, $original);
                $result = 'restore';
            }
        } else {
            $export = $backup->export();
            $counts = $export['counts'] ?? [];
            $uploadRoot = FileStorage::rootDir();
            $uploadFiles = 0;
            if (is_dir($uploadRoot)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                        $uploadFiles++;
                    }
                }
            }
            $counts['_upload_files'] = $uploadFiles;
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
<html lang="fa" dir="rtl" data-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پشتیبان‌گیری و بازیابی — Mechinno</title>
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
            <small>پشتیبان‌گیری کامل دیتابیس و فایل‌ها</small>
          </div>
        </div>

        <h1>پشتیبان‌گیری و بازیابی</h1>
        <p>پشتیبان کامل شامل داده‌های پنل (نهادها، اعضا، تصاویر پروفایل، قراردادها، اتاق جلسه، پیامک و …) به‌صورت ZIP یا فقط JSON داده است.</p>

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
            <p class="hint">پیشنهاد: ZIP کامل (داده + تصاویر اعضا/نهادها + فایل قرارداد و گزارش عملکرد).</p>
            <p class="hint warn">این فایل شامل هش رمزها و تنظیمات رمزنگاری‌شده پیامک است؛ فقط در محل امن نگهداری کنید.</p>
            <form method="post" class="install-actions">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="download_zip" />
              <button class="button" type="submit">دانلود پشتیبان کامل (ZIP)</button>
            </form>
            <form method="post" class="install-actions">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="download" />
              <button class="button ghost" type="submit">دانلود فقط داده (JSON)</button>
            </form>
          </div>

          <form method="post" enctype="multipart/form-data" class="install-actions">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="restore" />
            <h2>بازیابی از فایل</h2>
            <p class="hint warn">ZIP یا JSON پذیرفته می‌شود. فقط جدول‌های موجود در فایل جایگزین می‌شوند؛ قبل از بازیابی یک نسخه تازه بگیرید. برای پیامک پس از بازیابی، همان <code>app_secret</code> باید در config باشد.</p>
            <label class="wide">
              <span>فایل پشتیبان ZIP یا JSON</span>
              <input type="file" name="backup_file" accept=".zip,.json,application/zip,application/json" required />
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
