# Mechinno Management Panel - PHP/cPanel

پنل مدیریتی فارسی و راست‌به‌چپ برای مرکز نوآوری دانشکده مکانیک، نوشته‌شده با PHP ساده و مناسب نصب روی cPanel.

این نسخه بدون Composer و بدون فریم‌ورک اجرا می‌شود و داده‌های سه فایل Excel موجود در مخزن را مستقیم وارد دیتابیس MySQL می‌کند:

- `Innovation Center.xlsx`: اعضا، تیم‌ها، کمدها و برنامه‌های اجرایی
- `CHARGE.xlsx`: شارژ و اجاره ماهانه سال‌های ۱۴۰۴ و ۱۴۰۵
- `finance.xlsx`: تنخواه و تراکنش‌های مالی

## امکانات

- داشبورد مدیریتی لوکس با ظاهر تیره/طلایی و RTL
- دیتابیس MySQL با جداول جداگانه برای اعضا، تیم‌ها، کمدها، شارژ، مالی، برنامه‌ها و هشدارهای داده
- خواندن مستقیم فایل‌های XLSX با PHP `ZipArchive` و `SimpleXML`
- نصب و import از طریق `install.php`
- API ساده با `api.php`
- خروجی Excel-compatible با قالب‌بندی زیبا از طریق `export.php`
- ثبت هشدارهای کیفیت داده برای موارد مشکوک مثل تاریخ نامعتبر یا مبلغ ثبت‌شده در ستون توضیحات

## نیازمندی‌ها روی cPanel

- PHP 8.1 یا بالاتر
- افزونه‌های PHP:
  - `PDO`
  - `pdo_mysql`
  - `zip`
  - `simplexml`
  - `json`
- یک دیتابیس MySQL و یک User متصل به آن

## نصب روی cPanel

1. در cPanel وارد **MySQL Databases** شوید.
2. یک دیتابیس و یک کاربر بسازید و User را به دیتابیس اضافه کنید.
3. فایل‌های پروژه را داخل `public_html` یا مسیر دلخواه دامنه آپلود کنید.
4. فایل `config.sample.php` را به `config.php` کپی کنید.
5. داخل `config.php` این موارد را با اطلاعات cPanel خودتان تنظیم کنید:

```php
'database' => 'cpaneluser_mechinno',
'username' => 'cpaneluser_mechinno',
'password' => 'YOUR_PASSWORD',
```

6. در مرورگر باز کنید:

```text
https://your-domain.com/install.php
```

7. روی **ساخت دیتابیس و ورود داده‌ها** بزنید.
8. سپس پنل را باز کنید:

```text
https://your-domain.com/index.php
```

## مسیرهای اصلی

- پنل: `index.php`
- نصب/import: `install.php`
- API: `api.php?resource=summary`
- ورود مجدد داده‌ها: دکمه داخل پنل یا `POST api.php?resource=reimport`
- گزارش کامل Excel: `export.php?report=all`

## خروجی‌های Excel

- گزارش کامل: `export.php?report=all`
- اعضا: `export.php?report=members`
- تیم‌ها: `export.php?report=teams`
- کمدها: `export.php?report=lockers`
- شارژ: `export.php?report=charges`
- مالی: `export.php?report=transactions`
- برنامه‌ها: `export.php?report=plans`
- هشدارهای داده: `export.php?report=warnings`

## نکته امنیتی

فایل `.htaccess` داخل پروژه دسترسی مستقیم به `config.php`، فایل‌های Excel، پوشه `src` و داده‌های داخلی را محدود می‌کند. اگر هاست شما Apache نیست یا `.htaccess` را نادیده می‌گیرد، بهتر است پروژه را بیرون از web root نگه دارید و فقط فایل‌های وب را در مسیر عمومی قرار دهید.
