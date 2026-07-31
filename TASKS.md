# برنامه توسعه فیچرهای جدید — Mechinno / ICAUT

> **هدف:** افزودن قابلیت‌های جدید **بدون شکستن** ماژول‌های فعلی (نهادها، شارژ، مالی، اعضا، میز، کمد، اتاق جلسه، SMS، گزارش، پورتال نهاد).
>
> **روش کار:** هر تسک را **یکی‌یکی** انجام دهید → تست همان تسک → `scripts/run_all_tests.sh` → سپس تسک بعدی.
>
> **وضعیت:** `⬜` انجام نشده · `🔄` در حال انجام · `✅` انجام و تست شده

---

## فهرست سریع

| فاز | عنوان | تسک‌ها | وابستگی |
|-----|--------|--------|---------|
| **۰** | زیرساخت مشترک | T-000 → T-003 | — |
| **۱** | لاگ ممیزی | T-100 → T-108 | فاز ۰ |
| **۲** | اعلان درون‌پنلی | T-200 → T-206 | فاز ۰ (+ بخشی از فاز ۱) |
| **۳** | گزارش وضعیت نهاد | T-300 → T-304 | فاز ۲ |
| **۴** | آپلود قرارداد نهاد | T-400 → T-406 | فاز ۰ |
| **۵** | گزارش استفاده اتاق | T-500 → T-505 | — |
| **۶** | تابلوی اعلانات | T-600 → T-606 | فاز ۲ |
| **۷** | نظرسنجی | T-700 → T-706 | فاز ۲ |
| **۸** | رویداد / کارگاه / منتورینگ | T-800 → T-809 | فاز ۲ |
| **۹** | درآمد رویداد → دفتر معین | T-900 → T-905 | فاز ۸ |

**ترتیب پیشنهادی اجرا:**  
`۰ → ۱ → ۲ → ۳ → ۴ → ۵ → ۶ → ۷ → ۸ → ۹`

---

## اصول کلی (قبل از شروع هر تسک)

### قوانین مهندسی

1. **مهاجرت دیتابیس:** هر فیچر جدید → `Schema::VERSION++` + جداول/ستون‌های جدید در `src/Schema.php` + `ensure*` برای نصب‌های قبلی.
2. **دسترسی:** هر endpoint جدید در `src/Access.php` (`TEAM_RESOURCES` / `ADMIN_RESOURCES`) ثبت شود.
3. **پورتال نهاد:** داده‌ها با `Access::scopedTeamId()` فیلتر شوند — نهاد نباید داده نهاد دیگر را ببیند.
4. **نقش مشاهده‌گر:** مدیر مشاهده‌گر فقط خواندن؛ بدون آپلود/حذف/تأیید.
5. **CSRF:** همه `POST`‌های JSON با `require_csrf_json()` محافظت شوند.
6. **فایل‌ها:** ذخیره خارج از `public` — مسیر پیشنهادی: `data/uploads/` (مسدود در `.htaccess`).
7. **بکاپ:** `src/DatabaseBackup.php` باید جداول/فایل‌های جدید را پوشش دهد.
8. **نصب مجدد:** `Schema::reset()` جداول جدید را در لیست پاک‌سازی داشته باشد.
9. **تست:** برای هر تسک حداقل یک مورد در `scripts/integration_test.php` اضافه شود.

### چک‌لیست رگرسیون (بعد از هر تسک)

- [ ] `scripts/run_all_tests.sh` بدون خطا
- [ ] ورود مدیر ویرایشگر / مشاهده‌گر / نهاد
- [ ] داشبورد admin و team بدون خطای JS
- [ ] تأیید عضو، واریز، کمد، رزرو اتاق (جریان‌های قدیمی)
- [ ] محاسبه شارژ و کلاژ ماهانه
- [ ] دفتر معین و موجودی
- [ ] رزرو عمومی `reserve.php`
- [ ] بکاپ JSON و بازیابی (اگر جدول جدید اضافه شد)
- [ ] نصب مجدد `install.php` روی پنل خالی

### فایل‌های کلیدی پروژه (مرجع)

| لایه | فایل‌ها |
|------|---------|
| Schema | `src/Schema.php` |
| API | `api.php`, `public-api.php` |
| دسترسی | `src/Access.php`, `src/Auth.php` |
| گردش کار | `src/Workflow.php`, `src/RoomReservations.php` |
| CRUD | `src/Crud.php`, `src/Repository.php` |
| مالی | `src/CenterLedger.php`, `src/Seeder.php` |
| UI مدیر | `index.php`, `assets/app.js` |
| UI نهاد | `team.php`, `assets/team-year-workspace.js` |
| گزارش | `src/ReportBuilder.php`, `report.php`, `export.php` |
| بکاپ | `src/DatabaseBackup.php`, `backup.php` |
| تست | `scripts/integration_test.php`, `scripts/run_all_tests.sh` |

---

## فاز ۰ — زیرساخت مشترک

> پایه‌ای که بقیه فیچرها روی آن سوار می‌شوند. **قبل از همه فازها.**

---

### T-000 — سرویس زمان و شناسه مشترک
**وضعیت:** ⬜

**شرح:** کلاس کمکی برای timestamp شمسی، `actor_user_id`, `actor_role`, `actor_username`, `ip_address` (اختیاری).

**خروجی:**
- `src/ActorContext.php` — خواندن اطلاعات عامل از session
- ثابت‌های `ACTION_*` برای انواع رویداد

**تست:** واحد ساده در integration_test — session mock → actor درست برگردد.

**رگرسیون:** بدون تغییر رفتار فعلی.

---

### T-001 — قرارداد ثبت رویداد (Event Logger Interface)
**وضعیت:** ⬜

**شرح:** اینترفیس/کلاس `AuditLogger` که همه ماژول‌ها از آن استفاده کنند (هنوز بدون UI).

**API پیشنهادی:**
```php
AuditLogger::log(
    action: 'payment.approved',
    entityType: 'transaction',
    entityId: 42,
    teamId: 3,          // nullable
    summary: 'تأیید واریز ۵,۰۰۰,۰۰۰ ریال',
    before: [...],      // nullable JSON
    after: [...],       // nullable JSON
    meta: [...]         // nullable
);
```

**فایل:** `src/AuditLogger.php`

**وابستگی:** T-000

---

### T-002 — جدول `audit_logs` + مهاجرت
**وضعیت:** ⬜

**شرح:** جدول مرکزی لاگ.

**ستون‌های پیشنهادی:**

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `created_at` | DATETIME | UTC یا local |
| `jalali_at` | VARCHAR(10) | تاریخ شمسی نمایشی |
| `actor_user_id` | INT NULL | `panel_users.id` |
| `actor_username` | VARCHAR | |
| `actor_role` | VARCHAR | admin_editor / admin_viewer / team |
| `team_id` | INT NULL | نهاد مرتبط |
| `action` | VARCHAR(64) | مثلاً `member.approved` |
| `entity_type` | VARCHAR(32) | transaction, member, ... |
| `entity_id` | INT NULL | |
| `summary` | TEXT | توضیح فارسی کوتاه |
| `before_json` | TEXT NULL | |
| `after_json` | TEXT NULL | |
| `meta_json` | TEXT NULL | IP، user-agent، ... |
| `is_sensitive` | TINYINT | ۱ = فقط admin |

**ایندکس:** `(team_id, created_at)`, `(action, created_at)`, `(entity_type, entity_id)`

**فایل‌ها:** `src/Schema.php`, `src/DatabaseBackup.php`, `Schema::reset()`

**وابستگی:** T-001

---

### T-003 — جدول `notifications` + مهاجرت
**وضعیت:** ⬜

**شرح:** اعلان درون‌پنلی (بدون SMS).

**ستون‌های پیشنهادی:**

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `created_at` | DATETIME | |
| `recipient_type` | ENUM | `admin` / `team` |
| `recipient_team_id` | INT NULL | برای نهاد |
| `recipient_user_id` | INT NULL | اختیاری — کاربر خاص |
| `category` | VARCHAR(32) | workflow, announcement, survey, event |
| `title` | VARCHAR(255) | |
| `body` | TEXT | |
| `link_section` | VARCHAR(64) NULL | مثلاً `payments` |
| `link_entity_type` | VARCHAR(32) NULL | |
| `link_entity_id` | INT NULL | |
| `is_read` | TINYINT | ۰/۱ |
| `read_at` | DATETIME NULL | |
| `expires_at` | DATETIME NULL | |

**ایندکس:** `(recipient_type, recipient_team_id, is_read, created_at)`

**سرویس:** `src/NotificationService.php`

**وابستگی:** T-000

---

## فاز ۱ — لاگ ممیزی (Audit Trail)

> **هدف:** ثبت کامل «چه کسی، چه زمانی، چه چیزی» — با نمایش در پروفایل نهاد و بخش مالی.

---

### T-100 — اتصال لاگ به گردش کار تأیید/رد
**وضعیت:** ⬜

**رویدادهای اجباری:**

| action | محل کد | before/after |
|--------|--------|--------------|
| `member.approved` | `Workflow::approveMember` | وضعیت، کد تردد |
| `member.rejected` | `Workflow::rejectMember` | وضعیت، دلیل |
| `payment.approved` | `Workflow::approvePayment` | payment_status, confirmed |
| `payment.rejected` | `Workflow::rejectPayment` | payment_status, notes |
| `locker.approved` | `Workflow::approveLockerRequest` | locker_number, status |
| `locker.rejected` | `Workflow::rejectLockerRequest` | status, reason |
| `member_request.approved` | `Workflow::approveMemberRequest` | نوع درخواست، داده |
| `member_request.rejected` | `Workflow::rejectMemberRequest` | دلیل |
| `room.approved` | `RoomReservations::approve` | status |
| `room.rejected` | `RoomReservations::reject` | status, reason |

**همزمان:** ایجاد `NotificationService` برای نهاد (مثلاً «واریز شما تأیید شد»).

**تست:** هر workflow → یک ردیف audit + یک notification برای نهاد.

**رگرسیون:** منطق تأیید/رد تغییر نکند؛ فقط لاگ اضافه شود.

---

### T-101 — لاگ تغییرات مالی و شارژ
**وضعیت:** ⬜

**رویدادها:**

| action | محل | جزئیات |
|--------|-----|--------|
| `charge.manual_edit` | `Crud::update` روی charges | مبلغ قبل/بعد، ماه، سال |
| `charge.recalculated` | `Seeder::recalculateCharges*` | سال، تعداد ردیف‌های تغییر |
| `rate.updated` | `Crud` روی rate_settings | نرخ شارژ، نرخ اجاره |
| `transaction.income` | ایجاد درآمد دستی | مبلغ، شرح |
| `transaction.expense` | ایجاد هزینه | مبلغ، شرح |
| `transaction.payment_announced` | نهاد اعلام واریز | مبلغ، team_id |
| `ledger.manual` | تراکنش‌های غیر واریز تیم | |

**نکته:** `recalculate-charges` ممکن است صدها ردیف تغییر دهد → یک لاگ خلاصه + `meta_json` با `{ "affected_rows": N }` کافی است (نه ۱۰۰ لاگ جدا).

---

### T-102 — لاگ تخصیص میز و قرارداد
**وضعیت:** ⬜

**رویدادها:**

| action | محل |
|--------|-----|
| `desk.assigned` | `DeskAssignments`, `Crud` |
| `desk.updated` | تغییر بازه / معافیت |
| `desk.unassigned` | پایان تخصیص |
| `contract.created` | `team_contracts` |
| `contract.updated` | تغییر مبلغ/تاریخ/نرخ اختصاصی |
| `contract.deleted` | حذف قرارداد |
| `team.created` / `team.updated` / `team.deleted` | `Crud` teams |

---

### T-103 — لاگ ورود / خروج / شکست ورود
**وضعیت:** ⬜

**رویدادها:**

| action | محل |
|--------|-----|
| `auth.login.success` | `Auth::attempt` |
| `auth.login.failed` | `LoginThrottle` / Auth |
| `auth.logout` | `logout.php` |

**حساسیت:** `is_sensitive = 1` — نهادها نبینند.

**meta:** IP، user-agent (خلاصه)

**نکته امنیتی:** رمز عبور هرگز در before/after نرود.

---

### T-104 — API خواندن لاگ (`audit-logs`)
**وضعیت:** ⬜

**Endpoint:** `GET api.php?resource=audit-logs`

**فیلترها:**
- `team_id` — اجباری برای پورتال نهاد (auto-scoped)
- `action` — پیشوند مثل `payment.*`
- `entity_type`, `entity_id`
- `actor_username`
- `date_from`, `date_to` (شمسی)
- `page`, `per_page`

**دسترسی:**
- Admin: همه (با فیلتر)
- Team: فقط `team_id` خودش و `is_sensitive = 0`
- Viewer: read-only

**فایل:** `src/AuditLogRepository.php`, `api.php`, `Access.php`

---

### T-105 — UI جدول «تاریخچه تغییرات» در پروفایل نهاد
**وضعیت:** ⬜

**محل:** `team.php` → بخش `profile` یا تب جدید «تاریخچه»

**UI:**
- جدول: تاریخ، عملیات (برچسب فارسی)، کاربر، خلاصه
- فیلتر: نوع عملیات (مالی / عضو / میز / اتاق)
- کلیک روی ردیف → جزئیات before/after (modal)

**فایل‌ها:** `team.php`, `assets/team-audit.js` (یا داخل workspace موجود)

**وابستگی:** T-104

---

### T-106 — UI جدول «تاریخچه» در بخش مالی (admin)
**وضعیت:** ⬜

**محل:** `index.php` → بخش `transactions` — تب یا پنل فرعی «تاریخچه مالی»

**فیلتر پیش‌فرض:** `action` ∈ payment.*, charge.*, transaction.*, rate.*

**UI مشابه T-105** + فیلتر نهاد

---

### T-107 — UI «تاریخچه» در پروفایل نهاد (admin)
**وضعیت:** ⬜

**محل:** وقتی مدیر پروفایل نهاد را باز می‌کند (`team-year-workspace` / admin team profile)

**هدف:** مدیر همان لاگ‌های T-105 را ببیند + لاگ‌های حساس (مثلاً تغییر نرخ اختصاصی)

---

### T-108 — برچسب‌های فارسی و گزارش audit
**وضعیت:** ⬜

**شرح:**
- نقشه `action` → عنوان فارسی («تأیید واریز»، «تغییر نرخ شارژ»، …)
- افزودن نوع گزارش `audit` به `ReportBuilder` (اختیاری ولی توصیه‌شده)
- خروجی Excel از لاگ‌ها

**تست کامل فاز ۱:**
- [ ] تأیید واریز → لاگ + اعلان نهاد
- [ ] تغییر دستی شارژ → لاگ با before/after
- [ ] تخصیص میز → لاگ
- [ ] ورود مدیر → لاگ حساس
- [ ] نهاد لاگ حساس نمی‌بیند
- [ ] رگرسیون کامل

---

## فاز ۲ — اعلان درون‌پنلی

> **هدف:** اطلاع‌رسانی بدون SMS — زنگوله در header، لیست اعلان‌ها، خوانده‌شدن.

---

### T-200 — `NotificationService` (ایجاد / خواندن / علامت‌خوانده)
**وضعیت:** ⬜

**متدها:**
- `notifyTeam(teamId, title, body, options)`
- `notifyAdmins(title, body, options)`
- `listForCurrentUser(filters, page)`
- `markRead(id)` / `markAllRead()`
- `unreadCount()`

**وابستگی:** T-003

---

### T-201 — API اعلان‌ها (`notifications`)
**وضعیت:** ⬜

| Method | Route | کار |
|--------|-------|-----|
| GET | `resource=notifications` | لیست |
| GET | `resource=notifications&action=unread-count` | تعداد |
| POST | `resource=notifications&action=mark-read` | خواندن یکی |
| POST | `resource=notifications&action=mark-all-read` | همه |

**دسترسی:** team فقط اعلان‌های خودش؛ admin اعلان‌های `recipient_type=admin`.

---

### T-202 — UI زنگوله اعلان (admin + team)
**وضعیت:** ⬜

**محل:** header در `index.php` و `team.php`

**رفتار:**
- badge تعداد خوانده‌نشده
- dropdown آخرین ۱۰ اعلان
- لینک به بخش «اعلان‌ها»
- polling هر ۶۰ ثانیه یا refresh هنگام تعویض section

**فایل:** `assets/notifications.js`, `assets/styles.css`

---

### T-203 — صفحه / بخش «اعلان‌ها»
**وضعیت:** ⬜

**محل:** section جدید `notifications` در هر دو پنل

**UI:** جدول با فیلتر دسته، تاریخ، خوانده/نخوانده

---

### T-204 — اتصال اعلان به workflowها
**وضعیت:** ⬜

**نقشه اعلان (همزمان با T-100):**

| رویداد | گیرنده | عنوان نمونه |
|--------|--------|-------------|
| عضو تأیید/رد شد | نهاد | «عضو X تأیید شد» |
| واریز تأیید/رد شد | نهاد | «واریز شما تأیید شد» |
| کمد تأیید/رد شد | نهاد | «کمد شماره N تخصیص یافت» |
| رزرو اتاق تأیید/رد | نهاد / مهمان؟ | فقط درون‌پنلی برای نهاد |
| عضو جدید در انتظار | admin | «درخواست عضو جدید» |
| واریز در انتظار | admin | «واریز جدید در انتظار» |

**نکته:** اعلان admin در داشبورد «اقدامات پیشنهادی» هم قابل نمایش است (بدون حذف queue فعلی).

---

### T-205 — اعلان خودکار تغییر نرخ / تعطیلی
**وضعیت:** ⬜

**تریگر:**
- تغییر `rate_settings` → اعلان به **همه نهادهای فعال**
- ثبت `room_closed_days` → اعلان عمومی (آماده‌سازی برای فاز ۶)

**وابستگی:** T-101, T-200

---

### T-206 — تست و رگرسیون فاز ۲
**وضعیت:** ⬜

- [ ] اعلان workflow به نهاد می‌رسد
- [ ] unread count درست
- [ ] mark read کار می‌کند
- [ ] نهاد اعلان admin نمی‌بیند
- [ ] SMS فعلی دست‌نخورده بماند

---

## فاز ۳ — گزارش ساده وضعیت نهاد (پورتال)

> **هدف:** نمای خلاصه بدهی، پرداخت‌ها، اعضا — بدون پیچیدگی گزارش admin.

---

### T-300 — API `team-status-report`
**وضعیت:** ⬜

**Endpoint:** `GET api.php?resource=team-status-report`

**خروجی پیشنهادی:**
```json
{
  "team": { "name", "entity_code", "entity_type", "leader" },
  "contract": { "fiscal_year", "start", "end", "days_remaining", "status" },
  "financial": {
    "total_charged_ytd",
    "total_paid_ytd",
    "outstanding_balance",
    "last_payment": { "date", "amount", "status" },
    "overdue_months_count"
  },
  "members": {
    "approved_count",
    "pending_count",
    "with_access_card_count"
  },
  "desks": { "active_count", "numbers": [] },
  "lockers": { "assigned_count" },
  "rooms": { "upcoming_reservations_count" }
}
```

**دسترسی:** team (scoped) + admin با `team_id`

**فایل:** `src/TeamStatusReport.php`

---

### T-301 — UI کارت‌های گزارش در داشبورد نهاد
**وضعیت:** ⬜

**محل:** `team.php` → section `overview`

**کارت‌ها:**
1. بدهی / مانده
2. پرداخت‌های سال
3. اعضای فعال / در انتظار
4. میزهای فعال
5. روزهای مانده قرارداد

**طراحی:** هماهنگ با KPI cards داشبورد admin

---

### T-302 — نمودار ساده (اختیاری ولی توصیه‌شده)
**وضعیت:** ⬜

**محل:** overview نهاد

**نوع:** میله‌ای شارژ vs پرداخت ماهانه (۶ ماه اخیر) — از داده‌های موجود `charges-matrix` و `payment-history`

---

### T-303 — دکمه «دانلود خلاصه» (چاپ)
**وضعیت:** ⬜

**محل:** overview نهاد

**خروجی:** صفحه چاپ HTML ساده (`team-report.php?team_id=X`) — مشابه `report.php`

**دسترسی:** team فقط خودش

---

### T-304 — تست فاز ۳
**وضعیت:** ⬜

- [ ] اعداد با `team-profile` و `charges-matrix`一致
- [ ] نهاد دیگر داده نمی‌بیند
- [ ] admin می‌تواند گزارش نهاد را ببیند

---

## فاز ۴ — آپلود قرارداد توسط نهاد

> **هدف:** نهاد بتواند نسخه امضاشده / PDF قرارداد را آپلود کند؛ مدیر بررسی و تأیید کند.

---

### T-400 — جدول `team_contract_documents`
**وضعیت:** ⬜

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `team_id` | INT FK | |
| `fiscal_year` | VARCHAR(4) | |
| `team_contract_id` | INT NULL FK | ارتباط با `team_contracts` |
| `original_name` | VARCHAR | نام فایل کاربر |
| `stored_name` | VARCHAR | نام یکتا روی دیسک |
| `mime_type` | VARCHAR | |
| `file_size` | INT | بایت |
| `sha256` | VARCHAR(64) | |
| `uploaded_by_user_id` | INT | |
| `uploaded_by_role` | VARCHAR | team / admin_editor |
| `status` | ENUM | `pending_review` / `approved` / `rejected` |
| `reviewed_by` | INT NULL | |
| `reviewed_at` | DATETIME NULL | |
| `rejection_reason` | TEXT NULL | |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |

**محدودیت:** حداکثر N فایل فعال per team per year (پیشنهاد: ۵)

---

### T-401 — ذخیره‌سازی امن فایل
**وضعیت:** ⬜

**مسیر:** `data/uploads/contracts/{team_id}/{year}/`

**قوانین:**
- پسوندهای مجاز: `pdf`, `jpg`, `jpeg`, `png`
- حداکثر حجم: ۱۰ مگابایت (قابل تنظیم در config)
- نام فایل روی دیسک: UUID — نه نام اصلی
- `.htaccess` در `data/` از دسترسی مستقیم HTTP جلوگیری کند

**دانلود:** فقط از طریق `contract-download.php?id=X` با auth

**فایل:** `src/ContractDocuments.php`, `contract-download.php`

---

### T-402 — API آپلود / لیست / تأیید / رد
**وضعیت:** ⬜

| Method | Route | دسترسی |
|--------|-------|--------|
| POST | `resource=contract-documents` (multipart) | team + admin |
| GET | `resource=contract-documents&team_id=&year=` | team scoped / admin |
| POST | `action=approve` | admin editor |
| POST | `action=reject` | admin editor |
| DELETE | — | admin editor (یا team فقط pending) |

**audit:** `contract_document.uploaded`, `.approved`, `.rejected`

**notification:** به admin هنگام آپلود؛ به team هنگام تأیید/رد

---

### T-403 — UI آپلود در پنل نهاد
**وضعیت:** ⬜

**محل:** `team.php` → section `profile` — زیر اطلاعات قرارداد سال

**UI:**
- انتخاب سال (تب سال موجود)
- drag & drop یا input file
- لیست فایل‌های آپلودشده با وضعیت (در انتظار / تأیید / رد)
- نمایش دلیل رد

---

### T-404 — UI بررسی در پنل admin
**وضعیت:** ⬜

**محل:** پروفایل نهاد admin + بخش `team-contracts`

**UI:**
- صف «قراردادهای در انتظار بررسی»
- پیش‌نمایش PDF/تصویر (embed یا لینک دانلود)
- دکمه تأیید / رد

---

### T-405 — بکاپ و نصب مجدد
**وضعیت:** ⬜

- جدول در JSON backup
- فایل‌ها: یا در backup (سنگین) یا یادآوری در README که فایل‌ها جدا backup شوند
- `install.php` reset → جدول + فایل‌های upload پاک شوند

---

### T-406 — تست فاز ۴
**وضعیت:** ⬜

- [ ] آپلود PDF توسط نهاد
- [ ] دانلود فقط با auth
- [ ] دسترسی مستقیم به `data/uploads` مسدود
- [ ] تأیید/رد توسط admin
- [ ] audit + notification
- [ ] رگرسیون پروفایل و قراردادهای فعلی

---

## فاز ۵ — گزارش استفاده اتاق جلسه

> **هدف:** برای هر اتاق، ساعت پر / خالی / نرخ اشغال.

---

### T-500 — سرویس `RoomUsageReport`
**وضعیت:** ⬜

**ورودی:** `room_id`, `date_from`, `date_to` (شمسی), `granularity` (day/week/month)

**منطق محاسبه:**
- ساعات کاری اتاق از `meeting_rooms.open_time` / `close_time`
- کم کردن `room_closed_days`
- رزروهای `status IN (confirmed, approved, …)` — مطابق `RoomReservations::ACTIVE_STATUSES`
- اسلات‌ها: بر اساس `slot_minutes` اتاق

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
    { "date": "1404/05/01", "available_minutes", "booked_minutes", "occupancy_percent" }
  ],
  "heatmap": { "by_weekday": [...], "by_hour": [...] }
}
```

**فایل:** `src/RoomUsageReport.php`

**نکته:** از داده موجود `room_reservations` استفاده شود — جدول جدید لازم نیست.

---

### T-501 — API `room-usage-report`
**وضعیت:** ⬜

`GET api.php?resource=room-usage-report&room_id=1&fiscal_year=1404&month=5`

**دسترسی:** فقط admin (viewer هم می‌تواند بخواند)

---

### T-502 — UI گزارش در بخش اتاق جلسه
**وضعیت:** ⬜

**محل:** `index.php` → `meeting-rooms` — تب «گزارش استفاده»

**UI:**
- انتخاب اتاق + بازه تاریخ
- KPI: نرخ اشغال، تعداد رزرو، میانگین مدت
- نمودار میله‌ای روزانه
- جدول تفکیکی

---

### T-503 — heatmap ساعتی (اختیاری)
**وضعیت:** ⬜

**UI:** جدول ۷×N (روز هفته × ساعت) با رنگ‌بندی تراکم رزرو

---

### T-504 — افزودن به ReportBuilder + export
**وضعیت:** ⬜

- نوع گزارش `room-usage` در `report-catalog`
- چاپ HTML + Excel

---

### T-505 — تست فاز ۵
**وضعیت:** ⬜

- [ ] اتاق بدون رزرو → ۰٪ اشغال
- [ ] رزرو ۲ ساعته → دقیق در booked_minutes
- [ ] روز تعطیل → available صفر
- [ ] رگرسیون رزرو و تقویم

---

## فاز ۶ — تابلوی اعلانات

> **هدف:** اعلان‌های عمومی مرکز (تعطیلات، رویدادها، تغییر نرخ) — قابل مشاهده در پنل نهاد.

---

### T-600 — جدول `announcements`
**وضعیت:** ⬜

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT PK | |
| `title` | VARCHAR(255) | |
| `body` | TEXT | HTML ساده یا markdown محدود |
| `category` | ENUM | `holiday`, `event`, `rate_change`, `general`, `maintenance` |
| `priority` | ENUM | `normal`, `important`, `urgent` |
| `audience` | ENUM | `all_teams`, `specific_teams` |
| `published_at` | DATETIME NULL | |
| `expires_at` | DATETIME NULL | |
| `is_pinned` | TINYINT | |
| `created_by` | INT | |
| `created_at` | DATETIME | |
| `status` | ENUM | `draft`, `published`, `archived` |

**جدول واسط:** `announcement_teams (announcement_id, team_id)` برای audience خاص

---

### T-601 — API CRUD اعلانات
**وضعیت:** ⬜

| نقش | دسترسی |
|-----|--------|
| admin editor | ایجاد / ویرایش / انتشار / آرشیو |
| admin viewer | فقط خواندن |
| team | فقط published مربوط به خودش |

**Endpoint:** `resource=announcements`

---

### T-602 — UI مدیریت اعلانات (admin)
**وضعیت:** ⬜

**محل:** section جدید `announcements` در `index.php`

**UI:**
- لیست با فیلتر دسته / وضعیت
- فرم: عنوان، متن، دسته، اولویت، مخاطب، پین، تاریخ انقضا
- پیش‌نمایش قبل از انتشار

---

### T-603 — UI تابلوی اعلانات (پنل نهاد)
**وضعیت:** ⬜

**محل:** section جدید `announcements` در `team.php` + ویجت در overview

**UI:**
- کارت اعلان‌های پین‌شده
- فیلتر دسته
- نشان «جدید» برای اعلان‌های منتشرشده بعد از آخرین بازدید

**جدول کمکی:** `announcement_reads (announcement_id, team_id, read_at)`

---

### T-604 — اتصال به اعلان درون‌پنلی
**وضعیت:** ⬜

هنگام `publish` → `NotificationService::notifyTeam` برای مخاطبان

**وابستگی:** T-200

---

### T-605 — اعلان خودکار تغییر نرخ
**وضعیت:** ⬜

هنگام تغییر `rate_settings`:
1. audit log (فاز ۱)
2. اعلان in-app (فاز ۲)
3. **اختیاری:** پیش‌نویس announcement با دسته `rate_change` برای تأیید مدیر قبل از انتشار

---

### T-606 — تست فاز ۶
**وضعیت:** ⬜

- [ ] انتشار برای همه نهادها
- [ ] انتشار برای نهاد خاص
- [ ] انقضا → دیگر نمایش داده نشود
- [ ] نهاد دیگر اعلان مخصوص نمی‌بیند

---

## فاز ۷ — نظرسنجی رضایت

> **هدف:** جمع‌آوری بازخورد نهادها از فضا و خدمات.

---

### T-700 — جداول نظرسنجی
**وضعیت:** ⬜

**`surveys`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `title` | VARCHAR |
| `description` | TEXT |
| `status` | draft / active / closed |
| `opens_at` | DATETIME |
| `closes_at` | DATETIME |
| `audience` | all_teams / specific |
| `is_anonymous` | TINYINT |
| `created_by` | INT |

**`survey_questions`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `survey_id` | FK |
| `sort_order` | INT |
| `question_text` | TEXT |
| `question_type` | rating_1_5 / yes_no / text |
| `category` | space / services / staff / finance / other |

**`survey_responses`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `survey_id` | FK |
| `team_id` | FK |
| `submitted_by_user_id` | INT NULL |
| `submitted_at` | DATETIME |

**`survey_answers`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `response_id` | FK |
| `question_id` | FK |
| `answer_value` | TEXT |

**محدودیت:** یک پاسخ per team per survey (`UNIQUE(survey_id, team_id)`)

---

### T-701 — API نظرسنجی
**وضعیت:** ⬜

| عمل | دسترسی |
|-----|--------|
| CRUD نظرسنجی | admin editor |
| مشاهده نتایج | admin (viewer هم) |
| پاسخ دادن | team (فقط active و در بازه) |
| مشاهده پاسخ خود | team (اگر ناشناس نیست) |

---

### T-702 — UI مدیریت نظرسنجی (admin)
**وضعیت:** ⬜

**محل:** section `surveys`

**قابلیت:**
- سازنده سوال (افزودن / حذف / مرتب‌سازی)
- فعال‌سازی / بستن
- داشبورد نتایج: میانگین امتیاز، توزیع، پاسخ‌های متنی
- نمودار per دسته (فضا / خدمات)

---

### T-703 — UI پاسخ‌دهی (پنل نهاد)
**وضعیت:** ⬜

**محل:** section `surveys` در team.php

**UX:**
- لیست نظرسنجی‌های فعال
- فرم سوالات
- پیام «قبلاً پاسخ داده‌اید»

---

### T-704 — اعلان نظرسنجی جدید
**وضعیت:** ⬜

فعال شدن نظرسنجی → notification به نهادهای مخاطب

---

### T-705 — گزارش و export نتایج
**وضعیت:** ⬜

- خلاصه در `ReportBuilder`
- Excel export

---

### T-706 — تست فاز ۷
**وضعیت:** ⬜

- [ ] یک تیم فقط یک بار پاسخ می‌دهد
- [ ] بسته شدن → فرم غیرفعال
- [ ] نتایج aggregate درست
- [ ] حالت ناشناس → admin team_id در پاسخ نبیند (قابل تنظیم)

---

## فاز ۸ — رویداد / کارگاه / منتورینگ

> **هدف:** ثبت برنامه‌های مرکز، ثبت‌نام نهادها، حضور.

---

### T-800 — جداول رویداد
**وضعیت:** ⬜

**`center_events`**

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | PK | |
| `title` | VARCHAR | |
| `event_type` | ENUM | `workshop`, `event`, `mentoring`, `other` |
| `description` | TEXT | |
| `location` | VARCHAR | اتاق / سالن / آنلاین |
| `starts_at` | DATETIME | |
| `ends_at` | DATETIME | |
| `capacity` | INT NULL | |
| `registration_opens_at` | DATETIME | |
| `registration_closes_at` | DATETIME | |
| `fee_amount` | INT DEFAULT 0 | ریال — ۰ = رایگان |
| `status` | ENUM | draft / published / cancelled / completed |
| `created_by` | INT | |

**`event_registrations`**

| ستون | نوع |
|------|-----|
| `id` | PK |
| `event_id` | FK |
| `team_id` | FK |
| `registered_by_user_id` | INT |
| `registered_at` | DATETIME |
| `status` | registered / cancelled / attended / no_show |
| `attendee_count` | INT DEFAULT 1 |
| `notes` | TEXT |

**محدودیت:** `UNIQUE(event_id, team_id)`

---

### T-801 — API رویدادها
**وضعیت:** ⬜

`resource=center-events`, `resource=event-registrations`

| عمل | دسترسی |
|-----|--------|
| CRUD رویداد | admin editor |
| مشاهده | admin + team (published) |
| ثبت‌نام / لغو | team |
| حضور و غیاب | admin editor |

---

### T-802 — UI مدیریت رویداد (admin)
**وضعیت:** ⬜

**محل:** section `center-events`

**قابلیت:**
- تقویم / لیست رویدادها
- فرم ایجاد
- لیست ثبت‌نام‌کنندگان
- علامت‌گذاری حضور
- لغو رویداد → اعلان به ثبت‌نام‌شده‌ها

---

### T-803 — UI ثبت‌نام (پنل نهاد)
**وضعیت:** ⬜

**محل:** section `events` در team.php

**UI:**
- کارت رویدادهای قابل ثبت‌نام
- نمایش ظرفیت باقی‌مانده
- رویدادهای من (ثبت‌شده / گذشته)

---

### T-804 — اتصال به تابلوی اعلانات
**وضعیت:** ⬜

انتشار رویداد → announcement با دسته `event` (فاز ۶)

---

### T-805 — نمایش در داشبورد admin
**وضعیت:** ⬜

KPI: رویدادهای پیش‌رو، ثبت‌نام‌های امروز

---

### T-806 — audit و notification
**وضعیت:** ⬜

- `event.published`, `event.registration`, `event.cancelled`
- اعلان به نهاد هنگام ثبت‌نام موفق / لغو / تغییر زمان

---

### T-809 — تست فاز ۸ (بدون مالی)
**وضعیت:** ⬜

- [ ] ظرفیت پر → ثبت‌نام مردود
- [ ] لغو ثبت‌نام
- [ ] حضور و غیاب
- [ ] رگرسیون سایر بخش‌ها

---

## فاز ۹ — درآمد رویداد → دفتر معین

> **هدف:** هزینه ثبت‌نام رویداد به‌صورت درآمد در `transactions` / دفتر معین ثبت شود.

---

### T-900 — مدل پرداخت رویداد
**وضعیت:** ⬜

**سناریوها:**

| حالت | رفتار |
|------|--------|
| رایگان | فقط registration |
| با هزینه | ثبت‌نام → ایجاد `transaction` با category `درآمد رویداد` و status `pending` |
| تأیید دستی | admin تأیید → `confirmed=1` → در ledger |
| پرداخت از بدهی نهاد | **فاز بعدی** — فعلاً فقط اعلام واریز یا ثبت دستی admin |

**ستون‌های اضافی `event_registrations`:**
- `transaction_id` INT NULL FK
- `payment_status` ENUM: `not_required`, `pending`, `approved`, `rejected`

---

### T-901 — ثبت خودکار تراکنش هنگام ثبت‌نام
**وضعیت:** ⬜

**محل:** هنگام `event_registrations` با `fee_amount > 0`

1. ایجاد `transactions` با:
   - `category = 'درآمد رویداد'`
   - `team_id`, `amount`, `tx_date`
   - `payment_status = pending` (اگر نهاد باید واریز کند)
   - `notes = 'ثبت‌نام رویداد: {title}'`
2. audit: `event.registration.payment_created`
3. notification به نهاد: «لطفاً هزینه رویداد را واریز کنید»

**نکته:** با `CenterLedger` هماهنگ شود — فقط `confirmed=1` در موجودی نقدی بیاید.

---

### T-902 — تأیید پرداخت رویداد
**وضعیت:** ⬜

**گزینه A:** از workflow موجود `approvePayment` استفاده شود  
**گزینه B:** دکمه «تأیید پرداخت رویداد» در صفحه ثبت‌نام‌ها

پس از تأیید:
- `event_registrations.payment_status = approved`
- تراکنش در ledger
- audit + notification

---

### T-903 — گزارش درآمد رویدادها
**وضعیت:** ⬜

- KPI در بخش رویداد: درآمد کل / وصول‌شده / معوق
- افزودن به `ReportBuilder` نوع `events-finance`
- تفکیک per رویداد

---

### T-904 — UI مالی رویداد (admin)
**وضعیت:** ⬜

در صفحه رویداد:
- تب «مالی»: لیست ثبت‌نام‌ها، مبلغ، وضعیت پرداخت، لینک به تراکنش

---

### T-905 — تست کامل فاز ۹
**وضعیت:** ⬜

- [ ] رویداد رایگان → بدون transaction
- [ ] رویداد پولی → transaction pending
- [ ] تأیید → ledger balance درست
- [ ] رد → registration لغو یا pending بماند (تصمیم محصول)
- [ ] رگرسیون: واریز شارژ تیم همچنان جدا کار کند
- [ ] category جدید در گزارش مالی دیده شود

---

## وابستگی بین فازها (نمودار)

```
فاز ۰ (زیرساخت)
  ├── فاز ۱ (Audit)
  │     └── فاز ۲ (اعلان) ──┬── فاز ۳ (گزارش نهاد)
  │                         ├── فاز ۶ (تابلو) ── فاز ۸ (رویداد) ── فاز ۹ (درآمد)
  │                         └── فاز ۷ (نظرسنجی)
  └── فاز ۴ (آپلود قرارداد)

فاز ۵ (گزارش اتاق) — مستقل، هر زمان بعد از فاز ۰ قابل اجرا
```

---

## نکات محصولی مهم (برای جلوگیری از تداخل)

### ۱. تفکیک «اعلان» vs «تابلو اعلانات»
| | اعلان درون‌پنلی (فاز ۲) | تابلوی اعلانات (فاز ۶) |
|--|-------------------------|------------------------|
| **ماهیت** | رویداد شخصی/کاری | محتوای عمومی |
| **مثال** | «واریز شما تأیید شد» | «تعطیلی ۱۵ مرداد» |
| **عمر** | کوتاه | با تاریخ انتشار/انقضا |

### ۲. تفکیک «گزارش نهاد» vs «گزارش admin»
نهاد فقط **خلاصه خودش** را می‌بیند؛ admin گزارش‌های aggregate دارد.

### ۳. فایل قرارداد vs قرارداد سیستمی
رکورد `team_contracts` (تاریخ، مبلغ) **دست‌نخورده** بماند؛ فایل آپلودی **ضمیمه** است نه جایگزین.

### ۴. درآمد رویداد vs شارژ میز
- شارژ: `charges` + `category = واریز تیم`
- رویداد: `category = درآمد رویداد`
گزارش‌ها باید تفکیک‌پذیر باشند.

### ۵. حجم لاگ
سیاست نگهداری پیشنهادی: ۲ سال — job اختیاری `audit_logs` پاکسازی (فاز بعدی).

---

## لاگ پیشرفت

| تاریخ | تسک | وضعیت | یادداشت |
|-------|-----|--------|---------|
| | | | |

---

## پیوست — نگاشت actionهای audit (کامل)

| action | بخش | حساس |
|--------|-----|------|
| `auth.login.success` | امنیت | ✓ |
| `auth.login.failed` | امنیت | ✓ |
| `auth.logout` | امنیت | ✓ |
| `member.approved` | اعضا | |
| `member.rejected` | اعضا | |
| `member.created` | اعضا | |
| `member.updated` | اعضا | |
| `member.deleted` | اعضا | |
| `member_request.approved` | اعضا | |
| `member_request.rejected` | اعضا | |
| `payment.approved` | مالی | |
| `payment.rejected` | مالی | |
| `payment.announced` | مالی | |
| `transaction.income` | مالی | |
| `transaction.expense` | مالی | |
| `charge.manual_edit` | شارژ | |
| `charge.recalculated` | شارژ | ✓ |
| `rate.updated` | شارژ | ✓ |
| `desk.assigned` | میز | |
| `desk.updated` | میز | |
| `desk.unassigned` | میز | |
| `locker.approved` | کمد | |
| `locker.rejected` | کمد | |
| `room.approved` | اتاق | |
| `room.rejected` | اتاق | |
| `room.created` | اتاق | |
| `contract.created` | قرارداد | |
| `contract.updated` | قرارداد | |
| `contract.deleted` | قرارداد | |
| `contract_document.uploaded` | قرارداد | |
| `contract_document.approved` | قرارداد | |
| `contract_document.rejected` | قرارداد | |
| `announcement.published` | اعلانات | |
| `survey.published` | نظرسنجی | |
| `survey.submitted` | نظرسنجی | |
| `event.published` | رویداد | |
| `event.registration` | رویداد | |
| `event.registration.payment_created` | رویداد | |
| `event.cancelled` | رویداد | |
| `settings.updated` | تنظیمات | ✓ |

---

*آخرین به‌روزرسانی: ۱۴۰۴/۰۵/۱۰ — نسخه ۱.۰*
