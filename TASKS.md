# برنامه توسعه — Mechinno / ICAUT

> **هدف این سند:** پیاده‌سازی **۴ فیچر درخواستی** به‌صورت مرحله‌ای، با تست بعد از هر تسک، **بدون شکستن** بخش‌های فعلی.
>
> **روش کار:** هر تسک را **یکی‌یکی** انجام دهید → تست همان تسک → `scripts/run_all_tests.sh` → ثبت در «لاگ پیشرفت» → تسک بعدی.
>
> **وضعیت:** `⬜` انجام نشده · `🔄` در حال انجام · `✅` انجام و تست شده

---

## فیچرهای این برنامه

| # | فیچر | پنل نهاد | پنل مدیر |
|---|------|----------|----------|
| ۱ | **آپلود قرارداد** توسط نهاد | آپلود + مشاهده وضعیت | بررسی / تأیید / رد |
| ۲ | **گزارش استفاده اتاق** (ساعت پر/خالی) | — | گزارش + نمودار + export |
| ۳ | **تابلوی اعلانات** (تعطیلات، رویدادها، تغییر نرخ) | مشاهده + نشان «جدید» | ایجاد / انتشار / آرشیو |
| ۴ | **نظرسنجی رضایت** (فضا، خدمات) | پاسخ‌دهی | طراحی / نتایج / export |

**خارج از محدوده این سند (فاز بعدی):** لاگ ممیزی کامل، گزارش وضعیت نهاد، رویداد/کارگاه، درآمد رویداد.

---

## فهرست سریع تسک‌ها

| فاز | عنوان | تسک‌ها | وابستگی |
|-----|--------|--------|---------|
| **۰** | زیرساخت مشترک | T-000 → T-002 | — |
| **۱** | آپلود قرارداد نهاد | T-100 → T-106 | فاز ۰ |
| **۲** | گزارش استفاده اتاق | T-200 → T-205 | فاز ۰ (سبک) |
| **۳** | تابلوی اعلانات | T-300 → T-306 | فاز ۰ |
| **۴** | نظرسنجی | T-400 → T-406 | فاز ۰ |

**ترتیب پیشنهادی اجرا:**
```
۰ → ۱ (قرارداد) → ۲ (اتاق) → ۳ (تابلو) → ۴ (نظرسنجی)
```

> فاز ۲ (اتاق) مستقل است و می‌تواند همزمان با فاز ۱ جلو برود؛ ولی ترتیب بالا برای کاهش ریسک پیشنهاد می‌شود.

---

## اصول کلی (قبل از شروع هر تسک)

### قوانین مهندسی

1. **مهاجرت دیتابیس:** هر فیچر → `Schema::VERSION++` در `src/Schema.php` + جداول جدید + `ensure*` برای نصب‌های قبلی.
2. **دسترسی:** هر endpoint در `src/Access.php` (`TEAM_RESOURCES` / `ADMIN_RESOURCES`) ثبت شود.
3. **پورتال نهاد:** همه queryها با `Access::scopedTeamId()` فیلتر شوند.
4. **مدیر مشاهده‌گر:** فقط خواندن — بدون آپلود، تأیید، حذف.
5. **CSRF:** همه `POST`های JSON با `require_csrf_json()`.
6. **فایل‌ها:** ذخیره در `data/uploads/` — مسیر `data/` از قبل در `.htaccess` مسدود است.
7. **بکاپ:** `src/DatabaseBackup.php` جداول جدید را پوشش دهد.
8. **نصب مجدد:** `Schema::reset()` / `install.php` جداول و فایل‌های مرتبط را پاک کند.
9. **تست:** برای هر فاز حداقل یک مورد در `scripts/integration_test.php`.

### چک‌لیست رگرسیون (بعد از **هر** تسک)

- [ ] `scripts/run_all_tests.sh` بدون خطا
- [ ] ورود: مدیر ویرایشگر / مشاهده‌گر / نهاد
- [ ] داشبورد `index.php` و `team.php` بدون خطای JS
- [ ] جریان‌های قدیمی: تأیید عضو، واریز، کمد، رزرو اتاق
- [ ] محاسبه شارژ + کلاژ ماهانه
- [ ] دفتر معین و موجودی
- [ ] رزرو عمومی `reserve.php`
- [ ] SMS (ارسال دستی) دست‌نخورده
- [ ] بکاپ JSON (اگر جدول جدید اضافه شد)
- [ ] `install.php` روی پنل خالی

### فایل‌های کلیدی

| لایه | فایل‌ها |
|------|---------|
| Schema | `src/Schema.php` |
| API | `api.php` |
| دسترسی | `src/Access.php` |
| اتاق | `src/RoomReservations.php` |
| UI مدیر | `index.php`, `assets/app.js` |
| UI نهاد | `team.php`, `assets/team-year-workspace.js` |
| گزارش | `src/ReportBuilder.php`, `report.php`, `export.php` |
| بکاپ | `src/DatabaseBackup.php` |
| تست | `scripts/integration_test.php` |

---

## فاز ۰ — زیرساخت مشترک

> حداقل زیرساخت برای ۴ فیچر. **قبل از همه فازها.**

---

### T-000 — کلاس `ActorContext`
**وضعیت:** ⬜

**شرح:** خواندن `user_id`, `username`, `role`, `team_id` از session برای audit ساده و ثبت `created_by`.

**خروجی:** `src/ActorContext.php`

**تست:** mock session → مقادیر درست.

**رگرسیون:** بدون تغییر رفتار فعلی.

---

### T-001 — سرویس آپلود فایل (`FileStorage`)
**وضعیت:** ⬜

**شرح:** کلاس مشترک برای ذخیره/حذف/اعتبارسنجی فایل.

**قوانین:**
- مسیر: `data/uploads/{category}/{scope_id}/`
- پسوند مجاز per category (قرارداد: `pdf`, `jpg`, `jpeg`, `png`)
- حداکثر حجم: ۱۰ مگابایت (قابل تنظیم در `config.php`)
- نام روی دیسک: UUID — نه نام اصلی کاربر
- برگرداندن: `stored_name`, `sha256`, `mime_type`, `file_size`

**خروجی:** `src/FileStorage.php`

**تست:** آپلود mock → فایل روی دیسک + متادیتا.

---

### T-002 — endpoint دانلود امن فایل
**وضعیت:** ⬜

**شرح:** `file-download.php?id=X&type=contract` — فقط با auth و بررسی دسترسی.

**قوانین:**
- نهاد فقط فایل‌های `team_id` خودش
- admin همه را می‌بیند
- هدر `Content-Disposition: attachment`
- MIME از دیتابیس، نه از پسوند کاربر

**وابستگی:** T-001

**تست:** بدون login → 403؛ نهاد A فایل نهاد B → 403.

---

## فاز ۱ — آپلود قرارداد توسط نهاد

> نهاد نسخه امضاشده/PDF قرارداد را آپلود می‌کند؛ مدیر بررسی و تأیید/رد می‌کند.
>
> **مهم:** رکورد `team_contracts` (تاریخ، مبلغ، نرخ) **دست‌نخورده** می‌ماند — فایل فقط **ضمیمه** است.

---

### T-100 — جدول `team_contract_documents`
**وضعیت:** ⬜

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `team_id` | INT FK | |
| `fiscal_year` | VARCHAR(4) | |
| `team_contract_id` | INT NULL | ارتباط با `team_contracts` |
| `original_name` | VARCHAR | نام فایل کاربر |
| `stored_name` | VARCHAR | UUID روی دیسک |
| `mime_type` | VARCHAR | |
| `file_size` | INT | بایت |
| `sha256` | VARCHAR(64) | |
| `uploaded_by_user_id` | INT | |
| `uploaded_by_role` | VARCHAR | `team` / `admin_editor` |
| `status` | ENUM | `pending_review` / `approved` / `rejected` |
| `reviewed_by` | INT NULL | |
| `reviewed_at` | DATETIME NULL | |
| `rejection_reason` | TEXT NULL | |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |

**محدودیت:** حداکثر ۵ فایل فعال per `team_id` + `fiscal_year`.

**فایل‌ها:** `src/Schema.php`, `src/DatabaseBackup.php`, `Schema::reset()`

---

### T-101 — سرویس `ContractDocuments`
**وضعیت:** ⬜

**متدها:**
- `upload(teamId, fiscalYear, file)` → رکورد + فایل
- `list(teamId, fiscalYear?)` → لیست
- `approve(id, adminUserId)` / `reject(id, reason)`
- `delete(id)` — team فقط `pending_review` خودش؛ admin همه

**فایل:** `src/ContractDocuments.php`

**وابستگی:** T-001, T-100

---

### T-102 — API `contract-documents`
**وضعیت:** ⬜

| Method | Route | دسترسی |
|--------|-------|--------|
| POST | `resource=contract-documents` (multipart) | team + admin editor |
| GET | `resource=contract-documents` | team scoped / admin |
| POST | `action=approve` | admin editor |
| POST | `action=reject` | admin editor |
| DELETE | `resource=contract-documents&id=` | طبق T-101 |

**ثبت در:** `api.php`, `src/Access.php`

**وابستگی:** T-101, T-002

---

### T-103 — UI آپلود در پنل نهاد
**وضعیت:** ⬜

**محل:** `team.php` → section `profile` — زیر اطلاعات قرارداد (کنار تب سال در `team-year-workspace.js`)

**UI:**
- انتخاب فایل (PDF / تصویر)
- لیست فایل‌ها: نام، تاریخ، وضعیت (در انتظار / تأیید / رد)
- نمایش دلیل رد
- دکمه دانلود فایل خودش

**فایل‌ها:** `team.php`, `assets/team-contract-upload.js` (یا ادغام در workspace موجود)

**وابستگی:** T-102

---

### T-104 — UI بررسی در پنل مدیر
**وضعیت:** ⬜

**محل:**
1. پروفایل نهاد (admin) — لیست ضمیمه‌های قرارداد
2. صف «در انتظار بررسی» در داشبورد یا بخش نهادها

**UI:**
- دانلود / پیش‌نمایش
- تأیید / رد با دلیل

**فایل‌ها:** `index.php`, `assets/app.js`

---

### T-105 — بکاپ، نصب مجدد، صف داشبورد
**وضعیت:** ⬜

- جدول در JSON backup
- `install.php` → پاک‌سازی `data/uploads/contracts/`
- کارت «قرارداد در انتظار بررسی» در action items داشبورد admin

---

### T-106 — تست فاز ۱
**وضعیت:** ⬜

- [ ] آپلود PDF توسط نهاد
- [ ] نهاد B فایل نهاد A را نمی‌بیند
- [ ] دانلود فقط با auth — URL مستقیم `data/uploads` → 403
- [ ] تأیید/رد توسط admin editor
- [ ] viewer نمی‌تواند تأیید کند
- [ ] `team_contracts` و پروفایل سالانه بدون تغییر کار می‌کنند
- [ ] integration_test برای upload + approve

---

## فاز ۲ — گزارش استفاده اتاق (ساعت پر / خالی)

> برای هر اتاق: دقیقه/ساعت در دسترس، رزروشده، درصد اشغال.
>
> **بدون جدول جدید** — محاسبه از `room_reservations` + `meeting_rooms` + `room_closed_days`.

---

### T-200 — سرویس `RoomUsageReport`
**وضعیت:** ⬜

**ورودی:** `room_id`, `date_from`, `date_to` (شمسی), `granularity` (`day` / `week` / `month`)

**منطق:**
- ساعات کاری: `open_time` / `close_time` اتاق
- کم کردن `room_closed_days`
- رزروها: `RoomReservations::ACTIVE_STATUSES`
- اسلات: `slot_minutes` اتاق

**خروجی:**
```json
{
  "room": { "id", "name", "capacity" },
  "period": { "from", "to" },
  "totals": {
    "available_minutes",
    "booked_minutes",
    "occupancy_percent",
    "reservation_count"
  },
  "breakdown": [
    { "label": "1404/05/01", "available_minutes", "booked_minutes", "occupancy_percent" }
  ]
}
```

**فایل:** `src/RoomUsageReport.php`

---

### T-201 — API `room-usage-report`
**وضعیت:** ⬜

`GET api.php?resource=room-usage-report&room_id=1&date_from=1404/01/01&date_to=1404/06/30`

**پارامتر جایگزین:** `fiscal_year` + `month` برای میانبر

**دسترسی:** admin + viewer (read-only)

**ثبت در:** `api.php`, `src/Access.php`

---

### T-202 — UI گزارش در بخش اتاق جلسه (admin)
**وضعیت:** ⬜

**محل:** `index.php` → `meeting-rooms` — تب «گزارش استفاده»

**UI:**
- انتخاب اتاق + بازه (ماه / فصل / سفارشی)
- KPI: نرخ اشغال، تعداد رزرو، ساعت پر، ساعت خالی
- نمودار میله‌ای روزانه / هفتگی
- جدول تفکیکی

**فایل:** `assets/room-usage-report.js`

---

### T-203 — heatmap ساعتی (اختیاری ولی توصیه‌شده)
**وضعیت:** ⬜

**UI:** جدول ۷×N (روز هفته × بازه ساعتی) با رنگ‌بندی تراکم رزرو

**خروجی API:** `heatmap.by_weekday`, `heatmap.by_hour`

---

### T-204 — ReportBuilder + export
**وضعیت:** ⬜

- نوع `room-usage` در `report-catalog`
- چاپ HTML (`report.php`)
- Excel (`export.php`)

**نکته:** گزارش اتاق **فقط admin** — در پورتال نهاد نمایش داده نشود.

---

### T-205 — تست فاز ۲
**وضعیت:** ⬜

- [ ] اتاق بدون رزرو → ۰٪ اشغال
- [ ] رزرو ۲ ساعته → `booked_minutes` دقیق
- [ ] روز تعطیل → `available_minutes = 0`
- [ ] چند اتاق — گزارش جدا
- [ ] رگرسیون: تقویم، رزرو، `reserve.php`, تأیید رزرو

---

## فاز ۳ — تابلوی اعلانات

> اعلان‌های عمومی مرکز: تعطیلات، رویدادها، تغییر نرخ، اطلاعیه عمومی.
>
> **تفاوت با اعلان workflow:** تابلوی اعلانات = محتوای عمومی با تاریخ انتشار/انقضا؛ نه «واریز شما تأیید شد».

---

### T-300 — جداول `announcements` + `announcement_reads`
**وضعیت:** ⬜

**`announcements`**

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `title` | VARCHAR(255) | |
| `body` | TEXT | متن ساده (بدون HTML خطرناک) |
| `category` | ENUM | `holiday`, `event`, `rate_change`, `general`, `maintenance` |
| `priority` | ENUM | `normal`, `important`, `urgent` |
| `audience` | ENUM | `all_teams`, `specific_teams` |
| `published_at` | DATETIME NULL | |
| `expires_at` | DATETIME NULL | |
| `is_pinned` | TINYINT | |
| `status` | ENUM | `draft`, `published`, `archived` |
| `created_by` | INT | |
| `created_at` | DATETIME | |

**`announcement_teams`** — `(announcement_id, team_id)` برای مخاطب خاص

**`announcement_reads`** — `(announcement_id, team_id, read_at)` برای نشان «جدید»

---

### T-301 — سرویس و API `announcements`
**وضعیت:** ⬜

| نقش | دسترسی |
|-----|--------|
| admin editor | ایجاد / ویرایش / انتشار / آرشیو / حذف draft |
| admin viewer | فقط خواندن |
| team | فقط `published` مربوط به خودش (یا `all_teams`) |

**Endpoint:** `resource=announcements`

**عملیات:** CRUD + `action=publish` + `action=archive` + `action=mark-read`

---

### T-302 — UI مدیریت اعلانات (admin)
**وضعیت:** ⬜

**محل:** section جدید `announcements` در `index.php` + منوی sidebar

**UI:**
- لیست با فیلتر: دسته، وضعیت، اولویت
- فرم: عنوان، متن، دسته، اولویت، مخاطب (همه / نهادهای خاص)، پین، تاریخ انقضا
- پیش‌نمایش قبل از انتشار
- دکمه انتشار / آرشیو

---

### T-303 — UI تابلوی اعلانات (پنل نهاد)
**وضعیت:** ⬜

**محل:**
- section جدید `announcements` در `team.php` + منو
- ویجت در `overview`: ۲–۳ اعلان پین‌شده / فوری

**UI:**
- کارت اعلان با برچسب دسته (تعطیلات / رویداد / …)
- فیلتر دسته
- نشان «جدید» تا اولین بازدید (`announcement_reads`)
- اعلان منقضی‌شده نمایش داده نشود

---

### T-304 — اعلان خودکار تغییر نرخ (اختیاری)
**وضعیت:** ⬜

هنگام ایجاد/ویرایش `rate_settings`:
- **پیش‌نویس** announcement با دسته `rate_change` ساخته شود
- مدیر قبل از انتشار، متن را تأیید/ویرایش کند

**هدف:** تغییر نرخ بدون فراموشی اطلاع‌رسانی — بدون انتشار خودکار ناخواسته.

**رگرسیون:** محاسبه شارژ و `recalculate-charges` دست‌نخورده.

---

### T-305 — بکاپ و تست یکپارچگی
**وضعیت:** ⬜

- جداول در backup
- integration_test: انتشار برای همه / نهاد خاص / انقضا / read tracking

---

### T-306 — تست فاز ۳
**وضعیت:** ⬜

- [ ] انتشار `all_teams` → همه نهادها می‌بینند
- [ ] `specific_teams` → فقط همان نهادها
- [ ] نهاد A اعلان مخصوص B را نمی‌بیند
- [ ] `expires_at` گذشته → مخفی
- [ ] پین در overview نمایش داده می‌شود
- [ ] draft برای team قابل مشاهده نیست
- [ ] رگرسیون: شارژ، SMS، سایر بخش‌ها

---

## فاز ۴ — نظرسنجی رضایت

> جمع‌آوری بازخورد نهادها از **فضا** و **خدمات** (و سایر دسته‌ها).

---

### T-400 — جداول نظرسنجی
**وضعیت:** ⬜

**`surveys`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `title` | VARCHAR |
| `description` | TEXT |
| `status` | `draft` / `active` / `closed` |
| `opens_at` | DATETIME |
| `closes_at` | DATETIME |
| `audience` | `all_teams` / `specific` |
| `is_anonymous` | TINYINT — اگر ۱، admin نتایج را بدون نام نهاد aggregate می‌بیند |
| `created_by` | INT |

**`survey_questions`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `survey_id` | FK |
| `sort_order` | INT |
| `question_text` | TEXT |
| `question_type` | `rating_1_5` / `yes_no` / `text` |
| `category` | `space` / `services` / `staff` / `finance` / `other` |

**`survey_teams`** — مخاطب خاص (مثل announcement)

**`survey_responses`** — `UNIQUE(survey_id, team_id)`

**`survey_answers`** — `(response_id, question_id, answer_value)`

---

### T-401 — سرویس و API `surveys`
**وضعیت:** ⬜

| عمل | دسترسی |
|-----|--------|
| CRUD نظرسنجی + سوالات | admin editor |
| فعال‌سازی / بستن | admin editor |
| مشاهده نتایج aggregate | admin (+ viewer) |
| پاسخ دادن | team (فقط `active` و در بازه) |
| مشاهده پاسخ خود | team |

**Endpoint:** `resource=surveys`, `resource=survey-responses`

---

### T-402 — UI مدیریت نظرسنجی (admin)
**وضعیت:** ⬜

**محل:** section `surveys` در `index.php`

**قابلیت:**
- سازنده سوال: افزودن / حذف / مرتب‌سازی
- پیش‌فرض پیشنهادی: ۵ سوال امتیازی (فضا، تمیزی، اینترنت، پشتیبانی، ارزش شارژ)
- فعال‌سازی / بستن
- داشبورد نتایج: میانگین per سوال، per دسته، توزیع ۱–۵
- نمودار میله‌ای / دایره‌ای

---

### T-403 — UI پاسخ‌دهی (پنل نهاد)
**وضعیت:** ⬜

**محل:** section `surveys` در `team.php` + لینک از overview اگر نظرسنجی فعال unanswered وجود دارد

**UX:**
- لیست نظرسنجی‌های فعال
- فرم سوالات (ستاره ۱–۵، بله/خیر، متن)
- پیام «قبلاً پاسخ داده‌اید» + امکان مشاهده پاسخ خود (اگر ناشناس نیست)
- بسته شدن → فرم غیرفعال

---

### T-404 — گزارش و export نتایج
**وضعیت:** ⬜

- نوع `survey-results` در `ReportBuilder`
- Excel export
- فیلتر per نظرسنجی / per دسته سوال

---

### T-405 — بکاپ و integration_test
**وضعیت:** ⬜

- همه جداول در backup
- تست: یک پاسخ per team، بستن survey، aggregate

---

### T-406 — تست فاز ۴
**وضعیت:** ⬜

- [ ] team فقط یک بار پاسخ می‌دهد
- [ ] خارج از بازه `opens_at`/`closes_at` → غیرفعال
- [ ] `is_anonymous=1` → export بدون team_id
- [ ] نتایج aggregate درست (میانگین، تعداد)
- [ ] نهاد خارج از audience نمی‌بیند
- [ ] رگرسیون کامل

---

## وابستگی بین فازها

```
فاز ۰ (FileStorage + دانلود امن)
  ├── فاز ۱ (آپلود قرارداد)
  ├── فاز ۲ (گزارش اتاق) — تقریباً مستقل
  ├── فاز ۳ (تابلو اعلانات)
  └── فاز ۴ (نظرسنجی)

فاز ۳ و ۴ مستقل از هم‌اند؛ هر دو بعد از فاز ۰ قابل شروع‌اند.
```

---

## نکات محصولی (جلوگیری از تداخل)

| موضوع | قانون |
|-------|--------|
| قرارداد سیستمی vs فایل | `team_contracts` منبع حقیقت مالی/تاریخ است؛ فایل ضمیمه است |
| تابلو vs SMS | تابلو جایگزین SMS نیست؛ SMS دستی فعلی حفظ شود |
| گزارش اتاق | فقط admin — نهادها نرخ اشغال اتاق‌های دیگر را نبینند |
| نظرسنجی vs اعلان | فعال شدن نظرسنجی می‌تواند یک announcement با دسته `general` هم بسازد (اختیاری در T-403) |
| تغییر نرخ | هم announcement (`rate_change`) هم محاسبه شارژ — ترتیب: اول نرخ در DB، بعد اطلاع‌رسانی، بعد recalculate دستی توسط مدیر |

---

## لاگ پیشرفت

| تاریخ | تسک | وضعیت | یادداشت |
|-------|-----|--------|---------|
| ۱۴۰۴/۰۵/۱۰ | ایجاد سند | ✅ | محدود به ۴ فیچر درخواستی |
| | T-000 | ⬜ | |
| | T-001 | ⬜ | |
| | … | | |

---

## پیوست — چک‌لیست نهایی (بعد از اتمام همه فازها)

- [ ] هر ۴ فیچر در پنل مدیر و نهاد (در صورت مربوط) قابل دسترسی است
- [ ] README به‌روز: بخش‌های قرارداد، گزارش اتاق، تابلو، نظرسنجی
- [ ] `Schema::VERSION` افزایش یافته و migrate روی MySQL + SQLite تست شده
- [ ] بکاپ/restore با داده نمونه هر ۴ ماژول
- [ ] `install.php` reset تمیز
- [ ] تست دستی موبایل (RTL) برای پنل نهاد
- [ ] مدیر مشاهده‌گر محدودیت‌ها را دارد

---

*آخرین به‌روزرسانی: ۱۴۰۴/۰۵/۱۰ — نسخه ۲.۰ (محدود به ۴ فیچر)*
