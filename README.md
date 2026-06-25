# Mechinno Management Panel

پنل مدیریتی فارسی و راست‌به‌چپ برای مرکز نوآوری دانشکده مکانیک. PHP ساده بدون Composer، مناسب cPanel.

## ویژگی‌ها

- رابط کاربری روشن و مینیمال (آبی روشن + طلایی) با فونت Vazirmatn
- داشبورد با نمودار شارژ، بدهی، مالی و اشغال میز/کمد
- مدیریت نهادها (تیم / شرکت / دانشجو)، اعضا، ۲۴ میز، کمدها، شارژ، مالی و برنامه‌ها
- داده اولیه از `data/install-bundle.json` — **بدون نیاز به آپلود Excel روی سرور**
- شناسه‌های خودکار (`T-001`, `M-001` و …)
- پروفایل کامل هر نهاد (میز، اعضا، کمد، شارژ، واریز)
- صفحه‌بندی جداول برای داده زیاد
- محاسبه خودکار شارژ از نرخ (دکمه در بخش شارژ)
- ویرایش دستی مبالغ شارژ و تراکنش‌ها
- خروجی Excel و گزارش چاپی PDF

## نیازمندی‌ها

- PHP 8.1+
- PDO (MySQL یا SQLite)
- ZipArchive و SimpleXML (فقط برای ساخت bundle در محیط توسعه)

## نصب روی cPanel

1. دیتابیس MySQL و کاربر بسازید.
2. فایل‌های پروژه را آپلود کنید.
3. `config.sample.php` را به `config.php` کپی و تنظیم کنید.
4. رمز ورود پنل را در `config.php` عوض کنید.
5. `https://your-domain.com/install.php` را باز کنید، وارد شوید و نصب را تأیید کنید.
6. پنل: `https://your-domain.com/index.php`

## داده نصب

داده واقعی مرکز در `data/install-bundle.json` قرار دارد (ساخته‌شده از فایل‌های Excel منبع).

برای بازسازی bundle در محیط توسعه (نیازی به اجرا روی سرور نیست):

```bash
php tools/build-install-bundle.php
```

فایل‌های Excel منبع (`Innovation Center.xlsx`, `CHARGE.xlsx`, `finance.xlsx`) فقط برای ساخت bundle استفاده می‌شوند و روی سرور production لازم نیستند.

## مسیرهای اصلی

| مسیر | کاربرد |
|------|--------|
| `index.php` | پنل اصلی |
| `install.php` | نصب اولیه |
| `api.php?resource=summary` | API داشبورد |
| `api.php?resource=team-profile&id=1` | پروفایل نهاد |
| `POST api.php?resource=recalculate-charges` | محاسبه خودکار شارژ |
| `export.php?report=all` | خروجی Excel |
| `report.php` | گزارش مالی قابل چاپ |

## API صفحه‌بندی

```text
GET api.php?resource=members&page=2&per_page=25
```

پاسخ:

```json
{
  "rows": [...],
  "total": 88,
  "page": 2,
  "per_page": 25,
  "pages": 4
}
```

## شارژ و نرخ

- نرخ‌ها در بخش **نرخ‌ها** تعریف می‌شوند.
- مبالغ شارژ را می‌توان دستی ویرایش کرد (`source_file = manual`).
- دکمه **محاسبه خودکار شارژ از نرخ** فقط رکوردهای `system` را بازمی‌نویسد؛ داده دستی و bundle حفظ می‌شود.

## امنیت

`.htaccess` دسترسی به `config.php`، `src/` و `data/` را محدود می‌کند. رمز قوی برای پنل و دیتابیس انتخاب کنید.
