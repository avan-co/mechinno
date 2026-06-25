# Mechinno Management Panel

پنل مدیریتی فارسی و راست‌به‌چپ برای داده‌های مرکز نوآوری دانشکده مکانیک.

این نسخه اولیه داده‌های سه فایل Excel موجود در مخزن را وارد دیتابیس SQLite می‌کند و یک داشبورد وب برای مدیریت و گزارش‌گیری می‌دهد:

- `Innovation Center.xlsx`: اعضا، تیم‌ها، کمدها و برنامه‌های اجرایی
- `CHARGE.xlsx`: شارژ و اجاره ماهانه سال‌های ۱۴۰۴ و ۱۴۰۵
- `finance.xlsx`: تنخواه و تراکنش‌های مالی

## امکانات

- داشبورد مدیریتی با ظاهر تیره/طلایی و RTL
- دیتابیس SQLite با جداول جداگانه برای اعضا، تیم‌ها، کمدها، شارژ، مالی، برنامه‌ها و هشدارهای داده
- ورود خودکار داده از فایل‌های Excel هنگام اجرای برنامه
- API برای مشاهده داده‌ها
- خروجی Excel قالب‌بندی‌شده برای هر بخش و گزارش کامل
- ثبت هشدارهای کیفیت داده برای موارد مشکوک مثل تاریخ نامعتبر یا مبلغ ثبت‌شده در ستون توضیحات

## نصب و اجرا

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python scripts/import_data.py
uvicorn app.main:app --reload
```

سپس پنل را باز کنید:

```text
http://127.0.0.1:8000
```

## خروجی‌های Excel

- گزارش کامل: `/exports/all.xlsx`
- اعضا: `/exports/members.xlsx`
- تیم‌ها: `/exports/teams.xlsx`
- کمدها: `/exports/lockers.xlsx`
- شارژ: `/exports/charges.xlsx`
- مالی: `/exports/transactions.xlsx`
- برنامه‌ها: `/exports/plans.xlsx`
- هشدارهای داده: `/exports/warnings.xlsx`

## تست

```bash
pytest
```
