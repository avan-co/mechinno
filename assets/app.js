const labels = {
  id: "شناسه",
  entity_code: "کد نهاد",
  entity_type: "نوع",
  member_code: "کد عضو",
  access_code: "کد تردد",
  wants_access: "دسترسی تردد",
  is_leader: "نقش",
  contract_start: "شروع قرارداد",
  contract_end: "پایان قرارداد",
  formal_contract_amount: "مبلغ قرارداد رسمی",
  fiscal_year: "سال مالی",
  full_name: "نام",
  father_name: "نام پدر",
  id_certificate_number: "شماره شناسنامه",
  birth_date: "تاریخ تولد",
  birth_place: "محل تولد",
  education: "تحصیلات",
  email: "ایمیل",
  address: "آدرس سکونت",
  avatar: "تصویر",
  avatar_url: "تصویر",
  logo: "لوگو",
  logo_url: "لوگو",
  team_id: "نهاد",
  team_name: "نهاد",
  team_is_active: "وضعیت نهاد",
  team_label: "نهاد",
  current_full_name: "نام فعلی",
  request_type: "نوع درخواست",
  name: "نام",
  leader: "مسئول",
  phone: "تماس",
  desk_count: "تعداد میز",
  informal_seats: "صندلی موقت",
  assigned_from: "از ماه",
  assigned_until: "تا ماه",
  assignment_period: "بازه تخصیص",
  assignment_from: "از ماه",
  assignment_until: "تا ماه",
  desk_number: "شماره میز",
  desk_numbers: "میزهای نهاد",
  number: "شماره میز",
  usage_type: "نوع استفاده",
  formal_seats: "صندلی رسمی",
  locker_number: "شماره کمد",
  member_name: "عضو",
  status: "وضعیت",
  delivered_at: "تاریخ تحویل",
  key_number: "شماره کلید",
  spare_key: "کلید یدک",
  title: "عنوان",
  month_index: "ماه",
  month_name: "ماه",
  charge_amount: "شارژ",
  rent_amount: "اجاره موقت",
  amount: "مبلغ",
  note: "یادداشت",
  notes: "توضیحات",
  national_id: "کدملی",
  tx_date: "تاریخ",
  description: "شرح",
  category: "دسته",
  confirmed: "تأیید",
  charge_rate: "نرخ شارژ",
  informal_rent_rate: "نرخ اجاره موقت",
  charge_rate_override: "نرخ شارژ اختصاصی",
  informal_rent_rate_override: "نرخ اجاره اختصاصی",
  charge_exempt: "معاف شارژ",
  rent_exempt: "معاف اجاره",
  billing_exemptions: "معافیت",
  effective_from: "تاریخ اثر",
  joined_at: "عضویت",
  year_status: "وضعیت سال جاری",
  contract_status: "وضعیت قرارداد",
  assignment_status: "وضعیت تخصیص",
  warning: "اخطار",
  portal_username: "نام کاربری نهاد",
  portal_has_password: "رمز ورود نهاد",
  role: "نقش",
  finance_subtype: "نوع",
  is_active: "وضعیت",
  approval_status: "وضعیت تأیید",
  payment_status: "وضعیت واریز",
  payment_reference: "شماره پیگیری",
  announced_at: "تاریخ اعلام",
  reviewed_at: "تاریخ بررسی",
  submitted_at: "تاریخ درخواست",
  rejection_reason: "دلیل رد",
  priority: "اولویت",
  due_date: "موعد",
  sort_order: "ترتیب",
  created_at: "ایجاد",
  updated_at: "به‌روزرسانی",
  depends_on_id: "وابسته به",
  depends_on_title: "پیش‌نیاز",
  estimated_cost: "برآورد هزینه",
  estimated_revenue: "برآورد درآمد",
  related_section: "بخش مرتبط",
  bank_name: "بانک",
  account_holder: "صاحب حساب",
  account_number: "شماره حساب",
  card_number: "شماره کارت",
  sheba: "شماره شبا",
  payment_guide: "راهنمای پرداخت",
  username: "نام کاربری",
  room_name: "اتاق",
  room_code: "کد اتاق",
  reserved_date: "تاریخ",
  start_time: "شروع",
  end_time: "پایان",
  duration_minutes: "مدت (دقیقه)",
  booker_name: "رزروکننده",
  booker_phone: "موبایل",
  booker_org: "سازمان",
  purpose: "موضوع",
  source: "منبع",
  public_token: "کد پیگیری",
  cancel_reason: "دلیل لغو",
  capacity: "ظرفیت",
  floor: "طبقه",
  equipment: "تجهیزات",
  open_time: "شروع کاری",
  close_time: "پایان کاری",
  slot_minutes: "بازه (دقیقه)",
};

const entityTypeLabels = { team: "تیم", company: "شرکت", student: "دانشجو" };

const teamActiveBadge = (isActive) => Number(isActive) === 1
  ? '<span class="badge badge-paid">فعال</span>'
  : '<span class="badge badge-debt">غیرفعال</span>';

const requestTypeLabel = (type) => ({
  update: "ویرایش",
  delete: "حذف",
}[type] || type || "—");
const usageLabels = { formal: "رسمی", informal: "موقت", mixed: "ترکیبی" };

const billingExemptionBadges = (row = {}) => {
  const bits = [];
  if (Number(row.charge_exempt) === 1) bits.push('<span class="badge badge-partial">معاف شارژ</span>');
  if (Number(row.rent_exempt) === 1) bits.push('<span class="badge badge-partial">معاف اجاره</span>');
  return bits.length ? bits.join(" ") : "—";
};

const teamBillingBadges = (billing = {}, options = {}) => {
  if (!billing?.has_billing_adjustments) return "";
  const compact = options.compact === true;
  if (!compact) {
    return (billing.labels || []).map((label) =>
      `<span class="badge badge-team billing-badge" title="${escapeHtml(label)}">${escapeHtml(label)}</span>`
    ).join("");
  }

  const chips = [];
  const summaryTitle = escapeHtml(billing.summary_text || (billing.labels || []).join(" · "));
  if (billing.has_custom_rates) {
    const rateBits = [];
    if (billing.charge_rate_override) rateBits.push(`شارژ ${formatMoney(billing.charge_rate_override)}`);
    if (billing.informal_rent_rate_override) rateBits.push(`اجاره ${formatMoney(billing.informal_rent_rate_override)}`);
    chips.push(`<span class="billing-chip billing-chip--rate" title="${escapeHtml(rateBits.join(" · "))}">نرخ ویژه</span>`);
  }

  const exemptDesks = billing.exempt_desks || [];
  if (exemptDesks.length > 0) {
    const full = exemptDesks.filter((d) => d.charge_exempt && d.rent_exempt);
    const chargeOnly = exemptDesks.filter((d) => d.charge_exempt && !d.rent_exempt);
    const rentOnly = exemptDesks.filter((d) => !d.charge_exempt && d.rent_exempt);
    const deskLabel = (list, suffix) => {
      const nums = list.map((d) => d.desk_number).sort((a, b) => a - b);
      const text = nums.length === 1 ? `میز ${nums[0]}` : `${nums.length} میز`;
      const title = nums.map((n) => `میز ${n}`).join("، ");
      return `<span class="billing-chip billing-chip--exempt" title="${escapeHtml(`${title} — ${suffix}`)}">${escapeHtml(`${text} ${suffix}`)}</span>`;
    };
    if (full.length) chips.push(deskLabel(full, "معاف"));
    if (chargeOnly.length) chips.push(deskLabel(chargeOnly, "معاف شارژ"));
    if (rentOnly.length) chips.push(deskLabel(rentOnly, "معاف اجاره"));
  }

  if (chips.length === 0 && billing.labels?.length) {
    return `<span class="billing-chip billing-chip--info" title="${summaryTitle}">تنظیم مالی ویژه</span>`;
  }

  return chips.join("");
};

const sectionMeta = {
  overview: { eyebrow: "داشبورد", title: "مدیریت مرکز نوآوری", subtitle: "خلاصه وضعیت مرکز و اقدامات پیشنهادی" },
  teams: { eyebrow: "نهادها", title: "تیم‌ها، شرکت‌ها و دانشجویان", subtitle: "ثبت و مدیریت نهادها — قرارداد و میز هر سال از پروفایل نهاد" },
  "team-contracts": { eyebrow: "نهادها", title: "قراردادهای نهادها", subtitle: "تأیید پیشنهادها و فهرست قراردادهای ثبت‌شده" },
  "performance-reports": { eyebrow: "گزارش‌ها", title: "گزارش عملکرد نهادها", subtitle: "بررسی و تأیید گزارش‌های ۶ماهه نهادها" },
  "performance-settings": { eyebrow: "گزارش‌ها", title: "تنظیمات گزارش عملکرد", subtitle: "فعال‌سازی بخش و بازه مجاز ارسال هر نیمه" },
  members: { eyebrow: "اعضا", title: "اعضای نهادها", subtitle: "هر عضو به یک نهاد تعلق دارد — میزها در سطح نهاد تخصیص می‌یابند" },
  desks: { eyebrow: "میزها", title: "نقشه و تخصیص ۲۴ میز", subtitle: "تخصیص سال جاری از نقشه و جدول زیر نقشه" },
  "desk-history": { eyebrow: "میزها", title: "تاریخچه تخصیص میزها", subtitle: "سوابق تخصیص همه نهادها — جاری و منقضی" },
  lockers: { eyebrow: "کمدها", title: "مدیریت کمدها", subtitle: "شماره کمدها را خودتان تعریف و تخصیص دهید" },
  charges: { eyebrow: "شارژ", title: "نرخ و شارژ ماهانه", subtitle: "تعریف نرخ سالانه، محاسبه خودکار و پیگیری پرداخت" },
  transactions: { eyebrow: "مالی", title: "دفتر معین و موجودی نقدی", subtitle: "گردش واقعی حساب مرکز — بدون تکرار شارژ سیستمی" },
  reports: { eyebrow: "گزارش‌گیری", title: "گزارش‌ساز", subtitle: "انتخاب نوع گزارش، بازه ماهانه/سه‌ماهه/سالانه و خروجی چاپ یا Excel" },
  development: { eyebrow: "برنامه‌ریزی", title: "برنامه توسعه", subtitle: "کارهای جاری مرکز — اولویت‌بندی و پیگیری ساده" },
  users: { eyebrow: "دسترسی", title: "کاربران پنل", subtitle: "مدیریت نقش‌ها و پنل اختصاصی نهادها" },
  "file-manager": { eyebrow: "فایل‌ها", title: "مدیریت فایل‌های آپلود", subtitle: "مرور پوشه‌ای، دانلود، پیش‌نمایش و حذف امن فایل‌ها" },
  "meeting-rooms": { eyebrow: "اتاق جلسه", title: "مدیریت اتاق‌های جلسه", subtitle: "تعریف اتاق‌ها، رزروها و تنظیمات" },
  "room-settings": { eyebrow: "اتاق جلسه", title: "تنظیمات رزرو", subtitle: "قوانین رزرو عمومی و تأیید خودکار" },
  sms: { eyebrow: "اطلاع‌رسانی", title: "ارسال پیامک", subtitle: "ارسال اطلاعیه به اعضا و مسئولین نهادها" },
  "sms-settings": { eyebrow: "پیامک", title: "تنظیمات ملی‌پیامک", subtitle: "اتصال API، خط ارسال و همگام‌سازی" },
};

const teamSectionMeta = {
  overview: { eyebrow: "داشبورد نهاد", title: "وضعیت نهاد", subtitle: "خلاصه اعضا، میزها، کمدها و شارژ" },
  members: { eyebrow: "اعضا", title: "اعضای نهاد", subtitle: "لیست اعضای ثبت‌شده در نهاد شما" },
  desks: { eyebrow: "میزها", title: "نقشه و میزهای نهاد", subtitle: "موقعیت میز خودتان روی نقشه — بدون نمایش وضعیت دیگران" },
  lockers: { eyebrow: "کمدها", title: "کمدهای نهاد", subtitle: "درخواست کمد و کمدهای تخصیص‌یافته" },
  profile: { eyebrow: "پروفایل", title: "پروفایل نهاد", subtitle: "خلاصه سالانه، میز و بدهی" },
  contracts: { eyebrow: "قراردادها", title: "قراردادهای نهاد", subtitle: "مشاهده، ارسال و پیگیری قرارداد عضویت و استقرار هر سال" },
  "performance-reports": { eyebrow: "گزارش‌ها", title: "گزارش عملکرد", subtitle: "ارسال فایل گزارش ۶ماهه طبق زمان‌بندی مرکز" },
  charges: { eyebrow: "شارژ", title: "شارژ و پرداخت", subtitle: "لیست شارژ سالانه و وضعیت پرداخت" },
  payments: { eyebrow: "واریز", title: "اعلام واریز", subtitle: "ثبت واریز شارژ و پیگیری تأیید مدیر" },
  "room-reservations": { eyebrow: "اتاق جلسه", title: "رزرو اتاق جلسه", subtitle: "رزرو بازه‌های زمانی برای نهاد" },
};

const cardNavMap = {
  income_year: "transactions",
  income_month: "transactions",
  expense_year: "transactions",
  expense_month: "transactions",
  debt_total: "charges",
  charge_total: "charges",
  paid_total: "transactions",
  pending_members: "members",
  pending_payments: "transactions",
  pending_contracts: "team-contracts",
  pending_performance: "performance-reports",
  pending_locker_requests: "lockers",
  members: "members",
  teams: "teams",
  desks: "desks",
  lockers: "lockers",
  ledger_balance: "transactions",
  formal_contract_year: "teams",
  paid_total_year: "transactions",
  available_lockers: "lockers",
  desks_occupied: "desks",
};

const statIconSvg = {
  members: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0Z" fill="currentColor"/></svg>',
  desks: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18ZM8 17h2v-3H8v3Zm6 0h2v-3h-2v3Z" fill="currentColor"/></svg>',
  lockers: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h5v18H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm7 0h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5V3Zm1.5 7h2v3h-2v-3Z" fill="currentColor"/></svg>',
  charges: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 6.5A2.5 2.5 0 1 1 9.5 6 2.5 2.5 0 0 1 12 8.5Z" fill="currentColor"/></svg>',
  debt: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 5v4h4v2h-6V7Z" fill="currentColor"/></svg>',
  paid: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4Z" fill="currentColor"/></svg>',
  payments: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v2h12V7Zm0 4v2h8v-2Z" fill="currentColor"/></svg>',
  income: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12.2l3.6-3.6L17 13l-5 5-5-5 1.4-1.4L11 15.2V3h1Zm-7 16h14v2H5v-2Z" fill="currentColor"/></svg>',
  expense: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V8.8l-3.6 3.6L7 11l5-5 5 5-1.4 1.4L13 8.8V21h-1Zm-7-2h14v2H5v-2Z" fill="currentColor"/></svg>',
  balance: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v3H3V6Zm0 5h18v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8Zm3 3h4v2H6v-2Z" fill="currentColor"/></svg>',
};

/** Compact admin KPI strip — only keys that summary() always provides. */
const adminCardConfig = [
  ["ledger_balance", "موجودی نقد", statIconSvg.balance, "income"],
  ["debt_total", "مطالبات نهادها", statIconSvg.debt, "debt"],
  ["income_month", "درآمد این ماه", statIconSvg.income, "income"],
  ["expense_month", "هزینه این ماه", statIconSvg.expense, "expense"],
  ["pending_payments", "واریز معلق", statIconSvg.payments, "payments"],
  ["pending_contracts", "قرارداد معلق", statIconSvg.charges, "contracts"],
  ["pending_performance", "گزارش معلق", statIconSvg.members, "performance"],
  ["desks_occupied", "میز اشغال", statIconSvg.desks, "desks"],
];

const teamCardConfig = [
  ["members", "اعضای فعال", statIconSvg.members, "members"],
  ["desks", "میز", statIconSvg.desks, "desks"],
  ["charge_total", "جمع شارژ", statIconSvg.charges, "charge"],
  ["debt_total", "مانده بدهی", statIconSvg.debt, "debt"],
  ["paid_total", "پرداخت‌شده", statIconSvg.paid, "paid"],
  ["pending_payments", "واریزهای در انتظار تأیید", statIconSvg.payments, "payments"],
];

const cardConfig = adminCardConfig;

const moneyCards = new Set(["income_year", "income_month", "expense_year", "expense_month", "debt_total", "paid_total", "charge_total", "ledger_balance", "formal_contract_year"]);

const monthNames = ["", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

const formatMonthRange = (from, until) => {
  const fromMonth = Number(String(from || "").slice(5, 7)) || Number(from);
  const untilMonth = Number(String(until || "").slice(5, 7)) || Number(until);
  if (!fromMonth && !untilMonth) return "—";
  if (fromMonth && (!untilMonth || untilMonth === fromMonth)) return monthNames[fromMonth] || String(fromMonth);
  if (fromMonth && untilMonth) {
    return fromMonth === untilMonth
      ? (monthNames[fromMonth] || String(fromMonth))
      : `از ${monthNames[fromMonth] || fromMonth} تا ${monthNames[untilMonth] || untilMonth}`;
  }
  return monthNames[untilMonth] || String(untilMonth);
};

const normalizeDigits = (value) => String(value ?? "").trim()
  .replace(/[۰-۹]/g, (ch) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(ch)))
  .replace(/[٠-٩]/g, (ch) => String("٠١٢٣٤٥٦٧٨٩".indexOf(ch)));

const monthIndexFromDate = (value) => {
  const text = normalizeDigits(value);
  if (!text) return "";
  if (/^\d{1,2}$/.test(text)) return text;
  const parts = text.split("/");
  return parts.length >= 2 ? String(Number(parts[1]) || "") : "";
};

const validAssignmentMonth = (value, fallback = "") => {
  const month = Number(monthIndexFromDate(value));
  return month >= 1 && month <= 12 ? String(month) : fallback;
};

const fiscalYearFromDate = (value) => {
  const text = normalizeDigits(value);
  return text.length >= 4 ? text.slice(0, 4) : "";
};

const resourceColumns = {
  teams: ["logo_url", "entity_code", "entity_type", "name", "is_active", "year_status", "leader", "phone", "joined_at", "portal_username", "portal_has_password", "desk_count", "warning", "notes"],
  team_contracts: ["team_name", "fiscal_year", "contract_status", "contract_start", "contract_end", "formal_contract_amount", "charge_rate_override", "informal_rent_rate_override", "notes"],
  members: ["avatar_url", "member_code", "full_name", "is_leader", "team_label", "entity_type", "desk_numbers", "wants_access", "access_code", "phone", "email", "national_id", "joined_at", "approval_status", "rejection_reason"],
  desks: ["number", "team_name", "usage_type", "assignment_period", "notes"],
  "desk-assignments": ["assignment_status", "fiscal_year", "desk_number", "team_name", "usage_type", "billing_exemptions", "assignment_period", "notes"],
  lockers: ["locker_number", "status", "team_label", "delivered_at", "key_number", "spare_key"],
  "locker-requests": ["submitted_at", "status", "locker_number", "notes", "reviewed_at", "rejection_reason"],
  "member-requests": ["submitted_at", "request_type", "avatar_url", "current_full_name", "full_name", "phone", "email", "national_id", "status", "reviewed_at", "rejection_reason"],
  "pending-member-requests": ["team_label", "submitted_at", "request_type", "avatar_url", "current_full_name", "full_name", "phone", "email", "national_id", "father_name", "wants_access", "notes"],
  "pending-locker-requests": ["team_label", "submitted_at", "notes"],
  rate_settings: ["fiscal_year", "title", "charge_rate", "informal_rent_rate", "effective_from", "notes"],
  panel_users: ["username", "role", "full_name", "is_active"],
  charges: ["fiscal_year", "team_name", "month_name", "charge_amount", "rent_amount", "amount", "note"],
  transactions: ["tx_date", "description", "amount", "category", "team_name", "fiscal_year", "month_name", "payment_status", "payment_reference", "confirmed"],
  "pending-members": ["avatar_url", "member_code", "full_name", "team_label", "phone", "email", "national_id", "father_name", "wants_access", "joined_at", "submitted_at"],
  "pending-payments": ["tx_date", "team_name", "fiscal_year", "month_name", "amount", "payment_reference", "announced_at", "notes", "description"],
  "payment-history": ["tx_date", "team_name", "fiscal_year", "month_name", "amount", "payment_status", "payment_reference", "announced_at", "reviewed_at", "notes"],
  development_plans: ["title", "status", "priority", "due_date", "notes"],
  "meeting-rooms": ["name", "code", "capacity", "floor", "open_time", "close_time", "slot_minutes", "is_active", "equipment", "notes"],
  "room-reservations": ["reserved_date", "start_time", "end_time", "room_name", "booker_name", "booker_phone", "team_label", "status", "source", "purpose"],
  "pending-room-reservations": ["reserved_date", "start_time", "end_time", "room_name", "booker_name", "booker_phone", "team_label", "purpose", "source", "submitted_at"],
};

const teamPanelHiddenColumns = {
  members: ["team_label", "entity_type", "access_code"],
  desks: ["team_name"],
  lockers: ["team_label"],
  "locker-requests": ["team_label"],
  "member-requests": ["team_label"],
  "room-reservations": ["team_label"],
  charges: ["team_name"],
  transactions: ["category", "team_name", "confirmed"],
  "payment-history": ["team_name"],
};

const createDefaults = {
  teams: () => ({ is_active: "1" }),
  team_contracts: () => ({
    fiscal_year: window.MECHINNO?.fiscalYear || "",
    contract_start: window.MECHINNO?.fiscalYear ? `${window.MECHINNO.fiscalYear}/01/01` : "",
    contract_end: window.MECHINNO?.fiscalYear ? `${window.MECHINNO.fiscalYear}/12/29` : "",
  }),
  desk_assignments: () => {
    const year = window.MECHINNO?.fiscalYear || "1405";
    return {
      fiscal_year: year,
      assigned_from_month: "1",
      assigned_until_month: "12",
      usage_type: "formal",
    };
  },
  charges: () => ({ fiscal_year: window.MECHINNO?.fiscalYear || "" }),
  rate_settings: () => ({ fiscal_year: window.MECHINNO?.fiscalYear || "" }),
  transactions: () => ({
    fiscal_year: window.MECHINNO?.fiscalYear || "",
    month_index: String(window.MECHINNO?.monthIndex || 1),
    tx_date: window.MECHINNO?.today || "",
    confirmed: "1",
  }),
  development_plans: () => ({ priority: "medium", status: "open" }),
  meeting_rooms: () => ({ is_active: "1", capacity: "10", slot_minutes: "60", open_time: "08:00", close_time: "20:00" }),
  members: () => ({
    wants_access: "0",
  }),
  "locker-requests": () => ({}),
};
const csrfToken = window.MECHINNO?.csrfToken || "";
const canWrite = window.MECHINNO?.canWrite === true;
const canTeamSubmit = window.MECHINNO?.canTeamSubmit === true;
const canMutate = canWrite || canTeamSubmit;
const panelMode = window.MECHINNO?.panel || "admin";

const crudResourceKey = (resource) => (resource || "").replace(/-/g, "_");

const editableResourceKeys = new Set(
  canWrite
    ? ["members", "teams", "team_contracts", "desks", "desk_assignments", "lockers", "charges", "transactions", "rate_settings", "panel_users", "development_plans", "meeting_rooms"]
    : canTeamSubmit
    ? ["members", "transactions", "locker_requests", "member_requests"]
    : []
);

const isEditableResource = (resource) => editableResourceKeys.has(crudResourceKey(resource));
const workflowQueueResources = new Set([
  "pending-members",
  "pending-member-requests",
  "pending-payments",
  "pending-locker-requests",
  "pending-room-reservations",
  "pending-contract-proposals",
  "pending-performance-reports",
]);

const teamReadOnlyResources = new Set(["lockers", "charges", "payment-history"]);

const tableSuppressesAdd = (table) => {
  const resource = table.resource || "";
  if (table.hasAttribute("data-readonly") || table.hasAttribute("data-no-add")) return true;
  if (table.getAttribute("data-workflow") || workflowQueueResources.has(resource)) return true;
  if (panelMode === "team" && teamReadOnlyResources.has(resource)) return true;
  return false;
};

const tableAllowsAdd = (table, definition = null) => {
  const resource = table.resource || "";
  if (tableSuppressesAdd(table)) return false;
  if (!(canWrite || (canTeamSubmit && ["members", "transactions", "locker-requests"].includes(resource)))) {
    return false;
  }
  if (!definition || !isEditableResource(resource)) return false;
  return true;
};

const tableAllowsEdit = (table, definition = null) => {
  const resource = table.resource || "";
  if (table.getAttribute("data-workflow") || workflowQueueResources.has(resource)) return false;
  if (table.hasAttribute("data-readonly")) return false;
  if (panelMode === "team") {
    if (teamReadOnlyResources.has(resource)) return false;
    if (canTeamSubmit && ["transactions", "locker-requests"].includes(resource)) {
      return !!definition && isEditableResource(resource);
    }
    return false;
  }
  if (!canWrite || !definition || !isEditableResource(resource)) return false;
  return true;
};

const rowAllowsTeamDelete = (resource, row) => {
  if (!canTeamSubmit || panelMode !== "team") return false;
  if (resource === "transactions") return row.payment_status === "pending";
  if (resource === "locker-requests") return row.status === "pending";
  if (resource === "member-requests") return row.status === "pending";
  if (resource === "members") return row.approval_status === "pending";
  return false;
};

const rowAllowsTeamEdit = (resource, row) => {
  if (!canTeamSubmit || panelMode !== "team") return false;
  if (resource === "transactions") return row.payment_status === "pending";
  if (resource === "locker-requests") return row.status === "pending";
  return false;
};

const chargeStatusLabel = (status) => {
  if (panelMode !== "team") return status || "—";
  const map = {
    "بدهکار به مرکز": "مانده پرداخت",
    "پرداخت‌شده": "پرداخت‌شده",
    "ناقص": "پرداخت ناقص",
    "خارج از قرارداد": "خارج از قرارداد",
  };
  return map[status] || status || "—";
};
const hiddenColumns = new Set([
  "id", "source_sheet", "source_file", "team_id", "locker_id", "member_id",
  "row_index", "col_index", "created_at", "entity_type",
  "row_number", "lockers", "power_strips", "rent_rate",
]);
const plainColumns = new Set([
  "phone", "booker_phone", "national_id", "access_code", "member_code", "entity_code",
  "fiscal_year", "tx_date", "effective_from", "joined_at", "delivered_at",
  "key_number", "number", "locker_number", "desk_numbers", "desk_number", "month_index", "month_name",
  "assigned_from", "assigned_until",
  "portal_username", "portal_password",
]);

const linkColumns = {
  team_label: "team_id",
  team_name: "team_id",
  name: "id",
};

let crudMetaPromise = null;
let highlightDesk = null;
let highlightLocker = null;

const invalidateCrudMeta = () => {
  crudMetaPromise = null;
};

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const showToast = (message, type = "info") => {
  const logFn = type === "error" ? console.error : console.log;
  logFn(`[mechinno:toast:${type}]`, message);
  const host = document.getElementById("toastHost");
  if (!host) {
    console.warn("[mechinno:toast] toastHost element missing — message was:", message);
    return;
  }
  const icons = {
    success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z" fill="currentColor"/></svg>',
    error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 14h-2v-2h2v2Zm0-4h-2V7h2v5Z" fill="currentColor"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z" fill="currentColor"/></svg>',
  };
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span class="toast-text">${escapeHtml(message)}</span>`;
  host.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("show"));
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 220);
  }, 3200);
};

const EMPTY_ICONS = {
  default: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4V4Zm2 2v12h12V6H6Zm2 2h8v2H8V8Zm0 4h5v2H8v-2Z" fill="currentColor"/></svg>',
  search: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 1 1 4.73 13.35l4.35 4.35-1.41 1.41-4.35-4.35A7.5 7.5 0 0 1 10.5 3Zm0 2a5.5 5.5 0 1 0 5.5 5.5A5.5 5.5 0 0 0 10.5 5Z" fill="currentColor"/></svg>',
  inbox: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v4.5L12 13l8-6.5V6H4Z" fill="currentColor"/></svg>',
  chart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16v2H4v-2Zm3-8h2v6H7v-6Zm5-4h2v10h-2V7Zm5 6h2v4h-2v-4Z" fill="currentColor"/></svg>',
  desk: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a1 1 0 0 1 1 1v3H3V6a1 1 0 0 1 1-1Zm17 6v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8h18Z" fill="currentColor"/></svg>',
  error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 14h-2v-2h2v2Zm0-4h-2V7h2v5Z" fill="currentColor"/></svg>',
};

const renderEmptyState = (message, { icon = "default", cta = "", className = "" } = {}) => `
  <div class="empty-state ${className}">
    <span class="empty-state-icon" aria-hidden="true">${EMPTY_ICONS[icon] || EMPTY_ICONS.default}</span>
    <p class="empty-state-text">${escapeHtml(String(message))}</p>
    ${cta}
  </div>`;

const renderSkeletonTable = (rows = 5, cols = 5) => `
  <div class="skeleton-table" aria-busy="true" aria-label="در حال بارگذاری">
    ${Array.from({ length: rows }, () => `
      <div class="skeleton-row">${Array.from({ length: cols }, () => `<span class="skeleton-cell"></span>`).join("")}</div>
    `).join("")}
  </div>`;

window.renderEmptyState = renderEmptyState;
window.renderSkeletonTable = renderSkeletonTable;

const renderSectionLoadError = (hostId, message, retryFn) => {
  const host = document.getElementById(hostId);
  if (!host) return;
  host.classList.add("is-ready");
  const retryId = `retry-${hostId}-${Date.now()}`;
  host.innerHTML = renderEmptyState(message, {
    icon: "error",
    cta: `<button type="button" class="button ghost" id="${retryId}">تلاش دوباره</button>`,
  });
  host.querySelector(`#${retryId}`)?.addEventListener("click", () => {
    host.innerHTML = "در حال بارگذاری…";
    host.classList.remove("is-ready");
    Promise.resolve(retryFn()).catch((error) => {
      showToast(error.message, "error");
      renderSectionLoadError(hostId, message, retryFn);
    });
  });
};

const loadCrudMeta = () => {
  if (!crudMetaPromise) {
    crudMetaPromise = fetchJson("api.php?resource=crud-meta");
  }
  return crudMetaPromise;
};

const formatPlain = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  return String(value);
};

/** Display ISO (2026-08-02T…) or Jalali dates consistently. */
const formatDateTime = (value) => {
  const text = String(value || "").trim();
  if (!text) return "—";
  if (/^\d{4}\/\d{2}\/\d{2}/.test(normalizeDigits(text))) {
    return formatPlain(normalizeDigits(text).slice(0, 16).replace("T", " "));
  }
  const parsed = Date.parse(text);
  if (!Number.isNaN(parsed)) {
    try {
      return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(parsed));
    } catch {
      return text.slice(0, 16).replace("T", " ");
    }
  }
  return formatPlain(text);
};

const formatNumber = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  const maybe = Number(value);
  if (!Number.isNaN(maybe) && String(value).trim() !== "") return maybe.toLocaleString("fa-IR");
  return value;
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  const maybe = Number(value);
  if (Number.isNaN(maybe)) return "—";
  return `${maybe.toLocaleString("fa-IR")} ریال`;
};

const moneyColumns = new Set([
  "amount", "charge_amount", "rent_amount", "charge_rate", "informal_rent_rate",
  "formal_contract_amount", "charge_rate_override", "informal_rent_rate_override",
  "estimated_cost", "estimated_revenue",
]);

const formatReportCell = (cell, kind = "text") => {
  if (cell === null || cell === undefined || cell === "") return "—";
  if (kind === "money") return formatMoney(cell);
  if (kind === "count" || kind === "number") return formatNumber(cell);
  return String(cell);
};

const formatKpiValue = (kpi) => {
  const format = kpi?.format || (typeof kpi?.value === "number" ? "money" : "text");
  if (format === "money") return formatMoney(kpi.value);
  if (format === "count" || format === "number") return formatNumber(kpi.value);
  return kpi?.value || "—";
};

const debugLog = (scope, ...args) => {
  console.log(`[mechinno:${scope}]`, ...args);
};

const fetchJson = async (url, options = {}) => {
  const method = options.method || "GET";
  let reqBody;
  if (options.body) {
    try {
      reqBody = JSON.parse(options.body);
    } catch {
      reqBody = options.body;
    }
  }
  debugLog("api:request", method, url, reqBody);
  const response = await fetch(url, options);
  const raw = await response.text();
  let data = {};
  if (raw.trim() !== "") {
    try {
      data = JSON.parse(raw);
    } catch {
      console.error("[mechinno:api:parse-error]", url, raw.slice(0, 500));
      throw new Error(raw.trim() || "پاسخ نامعتبر از سرور");
    }
  }
  if (!response.ok) {
    console.error("[mechinno:api:error]", method, url, response.status, data);
    throw new Error(data.error || raw.trim() || `Request failed: ${url}`);
  }
  debugLog("api:ok", method, url, data?.record ? { ok: data.ok, recordId: data.record?.id } : data);
  return data;
};

const fetchResource = async (endpoint, {
  page = 1,
  perPage = 25,
  category = "",
  paymentStatus = "",
  approvalStatus = "",
  fiscalYear = "",
  teamId = "",
  entityType = "",
  isLeader = "",
  wantsAccess = "",
  messageType = "",
  status = "",
  assignmentStatus = "",
  q = "",
} = {}) => {
  const url = new URL(endpoint, window.location.href);
  url.searchParams.set("page", String(page));
  url.searchParams.set("per_page", String(perPage));
  if (category) url.searchParams.set("category", category);
  if (paymentStatus) url.searchParams.set("payment_status", paymentStatus);
  if (approvalStatus) url.searchParams.set("approval_status", approvalStatus);
  if (fiscalYear) url.searchParams.set("fiscal_year", fiscalYear);
  if (teamId) url.searchParams.set("team_id", teamId);
  if (entityType) url.searchParams.set("entity_type", entityType);
  if (isLeader !== "") url.searchParams.set("is_leader", isLeader);
  if (wantsAccess !== "") url.searchParams.set("wants_access", wantsAccess);
  if (messageType) url.searchParams.set("message_type", messageType);
  if (status) url.searchParams.set("status", status);
  if (assignmentStatus) url.searchParams.set("assignment_status", assignmentStatus);
  if (q) url.searchParams.set("q", q);
  const data = await fetchJson(url.toString());
  if (Array.isArray(data)) {
    return { rows: data, total: data.length, page: 1, per_page: data.length, pages: 1 };
  }
  return {
    rows: data.rows || [],
    total: Number(data.total || 0),
    page: Number(data.page || page),
    per_page: Number(data.per_page || perPage),
    pages: Number(data.pages || 1),
  };
};

const postJson = async (url, payload = {}) => {
  const data = await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
    body: JSON.stringify(payload),
  });
  // Guard against list endpoints accidentally handling mutation POSTs as success.
  if (String(url).includes("action=") && data && data.ok !== true && Array.isArray(data.rows)) {
    throw new Error(data.error || "پاسخ نامعتبر از سرور برای این عملیات.");
  }
  return data;
};

const postForm = async (url, formData) => {
  const data = await fetchJson(url, {
    method: "POST",
    headers: { "X-CSRF-Token": csrfToken },
    body: formData,
  });
  if (String(url).includes("action=") && data && data.ok !== true && Array.isArray(data.rows)) {
    throw new Error(data.error || "پاسخ نامعتبر از سرور برای این عملیات.");
  }
  return data;
};

const PROFILE_IMAGE_MAX_BYTES = 2_097_152;

const profileThumb = (url, label = "", fallback = "assets/brand/default-member.svg") => {
  if (!url) {
    return `<img class="profile-thumb" src="${escapeHtml(fallback)}" alt="" loading="lazy" />`;
  }
  return `<img class="profile-thumb" src="${escapeHtml(url)}" alt="" loading="lazy" onerror="this.onerror=null;this.src='${escapeHtml(fallback)}';" />`;
};

const assertProfileImageFile = (file, label = "تصویر پروفایل") => {
  if (!file || !file.size) {
    throw new Error(`${label} الزامی است.`);
  }
  if (file.size > PROFILE_IMAGE_MAX_BYTES) {
    throw new Error(`${label} نباید بیشتر از ۲ مگابایت باشد.`);
  }
};

const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

const syncMobileClass = () => {
  document.body.classList.toggle("is-mobile", isMobile());
};

const updatePageHeader = (sectionId) => {
  const metaSource = panelMode === "team" ? teamSectionMeta : sectionMeta;
  let meta = metaSource[sectionId] || metaSource.overview;
  if (panelMode === "team" && sectionId === "overview" && window.MECHINNO?.teamName) {
    meta = {
      eyebrow: window.MECHINNO.teamEyebrow || meta.eyebrow,
      title: window.MECHINNO.teamName,
      subtitle: window.MECHINNO.teamSubtitle || meta.subtitle,
    };
  }
  const eyebrow = document.getElementById("pageEyebrow");
  const title = document.getElementById("pageTitle");
  const subtitle = document.getElementById("pageSubtitle");
  if (eyebrow) eyebrow.textContent = meta.eyebrow;
  if (title) title.textContent = meta.title;
  if (subtitle) subtitle.textContent = meta.subtitle;
};

const reloadSectionTables = (sectionId, resetPage = false) => {
  document.querySelectorAll(`#${sectionId} data-table`).forEach((table) => {
    if (resetPage) table.page = 1;
    table.load?.();
  });
};

const reloadDeskTables = async () => {
  const desksTable = document.getElementById("currentDesksTable");
  const historyTable = document.getElementById("deskAssignmentsTable");
  if (desksTable) {
    desksTable.page = 1;
    await desksTable.load?.();
  }
  if (historyTable) {
    historyTable.page = 1;
    await historyTable.load?.();
  }
  await loadDeskGrid().catch((error) => showToast(error.message, "error"));
};

const refreshAfterMutation = async (sectionId = null) => {
  invalidateCrudMeta();
  if (sectionId) reloadSectionTables(sectionId, true);
  if (sectionId === "desks") {
    loadDeskGrid().catch((error) => showToast(error.message, "error"));
  }
  if (!sectionId || sectionId === "meeting-rooms" || sectionId === "room-reservations") {
    window.refreshRoomCalendar?.();
    window.refreshPanelMonthPicker?.();
  }
  try {
    await loadDashboard();
  } catch (error) {
    showToast(error.message, "error");
  }
  if (!sectionId || sectionId === "transactions") {
    if (panelMode === "admin" && document.getElementById("ledgerPanel")) {
      loadLedger().catch((error) => showToast(error.message, "error"));
    }
  }
  if (!sectionId) {
    document.querySelectorAll("data-table").forEach((table) => table.load?.());
  }
};

const closeDrawer = () => {
  document.getElementById("sidebar")?.classList.remove("open");
  document.getElementById("sidebarBackdrop")?.setAttribute("hidden", "");
};

const openDrawer = () => {
  document.getElementById("sidebar")?.classList.add("open");
  document.getElementById("sidebarBackdrop")?.removeAttribute("hidden");
};

let reportCatalog = null;
let reportSelectedType = "finance";
let reportLastFilters = null;

const reportTypeSupportsPeriod = (typeId) => {
  const item = (reportCatalog?.types || []).find((row) => row.id === typeId);
  return item ? !!item.supports_period : true;
};

const fillSelectOptions = (select, options, selected) => {
  if (!select) return;
  select.innerHTML = options.map((opt) => {
    const value = String(opt.id ?? opt.value ?? "");
    const label = String(opt.label ?? opt.name ?? value);
    const isSelected = String(selected) === value ? " selected" : "";
    return `<option value="${escapeHtml(value)}"${isSelected}>${escapeHtml(label)}</option>`;
  }).join("");
};

const syncReportPeriodFields = () => {
  const period = document.getElementById("reportPeriod")?.value || "monthly";
  const supports = reportTypeSupportsPeriod(reportSelectedType);
  const periodSelect = document.getElementById("reportPeriod");
  if (periodSelect) periodSelect.disabled = !supports;
  const showMonth = supports && period === "monthly";
  const showQuarter = supports && period === "quarterly";
  const showCustom = supports && period === "custom";
  document.getElementById("reportMonthWrap")?.toggleAttribute("hidden", !showMonth);
  document.getElementById("reportQuarterWrap")?.toggleAttribute("hidden", !showQuarter);
  document.getElementById("reportMonthFromWrap")?.toggleAttribute("hidden", !showCustom);
  document.getElementById("reportMonthToWrap")?.toggleAttribute("hidden", !showCustom);
};

const collectReportFilters = () => {
  const period = document.getElementById("reportPeriod")?.value || "monthly";
  const filters = {
    type: reportSelectedType || "finance",
    period: reportTypeSupportsPeriod(reportSelectedType) ? period : "annual",
    fiscal_year: document.getElementById("reportFiscalYear")?.value || String(window.MECHINNO?.fiscalYear || ""),
    team_id: Number(document.getElementById("reportTeam")?.value || 0),
  };
  if (filters.period === "monthly") {
    filters.month = Number(document.getElementById("reportMonth")?.value || window.MECHINNO?.monthIndex || 1);
  } else if (filters.period === "quarterly") {
    filters.quarter = Number(document.getElementById("reportQuarter")?.value || 1);
  } else if (filters.period === "custom") {
    filters.month_from = Number(document.getElementById("reportMonthFrom")?.value || 1);
    filters.month_to = Number(document.getElementById("reportMonthTo")?.value || 12);
  }
  return filters;
};

const reportQueryString = (filters) => {
  const params = new URLSearchParams();
  Object.entries(filters || {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "" || (value === 0 && key === "team_id")) return;
    params.set(key, String(value));
  });
  return params.toString();
};

const renderReportTypeCards = () => {
  const host = document.getElementById("reportTypeGrid");
  if (!host || !reportCatalog) return;
  host.innerHTML = (reportCatalog.types || []).map((type) => `
    <button type="button" class="report-type-card${type.id === reportSelectedType ? " is-active" : ""}" data-report-type="${escapeHtml(type.id)}">
      <strong>${escapeHtml(type.label)}</strong>
      <span>${escapeHtml(type.description || "")}</span>
    </button>
  `).join("");
  host.querySelectorAll("[data-report-type]").forEach((btn) => {
    btn.addEventListener("click", () => {
      reportSelectedType = btn.getAttribute("data-report-type") || "finance";
      renderReportTypeCards();
      syncReportPeriodFields();
    });
  });
};

const renderReportPreviewTable = (headers, rows, emptyText, columnKinds = []) => {
  if (!rows?.length) {
    return renderEmptyState(emptyText || "داده‌ای نیست.", { icon: "chart" });
  }
  return `<div class="table-wrap"><table class="data-table">
    <thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("")}</tr></thead>
    <tbody>
      ${rows.map((cells) => `<tr>${cells.map((cell, index) => {
        const kind = columnKinds[index] || (typeof cell === "number" ? "count" : "text");
        const className = kind === "money" ? "num" : "";
        return `<td class="${className}">${escapeHtml(formatReportCell(cell, kind))}</td>`;
      }).join("")}</tr>`).join("")}
    </tbody>
  </table></div>`;
};

const previewReport = async () => {
  const host = document.getElementById("reportPreview");
  const meta = document.getElementById("reportPreviewMeta");
  if (!host) return;
  const filters = collectReportFilters();
  reportLastFilters = filters;
  host.innerHTML = renderSkeletonTable(4, 5);
  try {
    const data = await fetchJson(`api.php?resource=reports&${reportQueryString(filters)}`);
    const info = data.meta || {};
    if (meta) {
      meta.textContent = `${info.type_label || ""} — ${info.period_title || ""} — ${info.team_name || "همه نهادها"}`;
    }
    const blocks = [];
    if (data.kpis?.length) {
      blocks.push(`<div class="report-kpi-grid">${data.kpis.map((kpi) => `
        <article class="report-kpi-card ${kpi.tone === "danger" ? "is-danger" : kpi.tone === "success" ? "is-success" : ""}">
          <span>${escapeHtml(kpi.label || "")}</span>
          <strong>${escapeHtml(formatKpiValue(kpi))}</strong>
        </article>`).join("")}</div>`);
    }
    if (data.finance_summary) {
      const finance = data.finance_summary;
      blocks.push(`<h3 class="report-preview-title">خلاصه مالی بازه</h3>`);
      blocks.push(renderReportPreviewTable(
        ["شاخص", "مبلغ"],
        [
          ["واریز نهادها", finance.deposits || 0],
          ["درآمد دستی", finance.manual_income || 0],
          ["جمع درآمد", finance.income_total || 0],
          ["هزینه‌ها", finance.expense_total || 0],
          ["خالص نقدی", finance.net || 0],
          ["جمع شارژ", finance.charge_total || 0],
          ["مانده طلب", finance.debt_total || 0],
          ...(finance.formal_contract_total !== undefined
            ? [["جمع مبلغ قراردادهای رسمی سال", finance.formal_contract_total || 0]]
            : []),
        ],
        null,
        ["text", "money"]
      ));
    }
    if (data.monthly_breakdown?.length) {
      blocks.push(`<h3 class="report-preview-title">تفکیک ماهانه</h3>`);
      blocks.push(renderReportPreviewTable(
        ["ماه", "واریز", "درآمد دستی", "هزینه", "خالص", "شارژ", "مانده طلب"],
        data.monthly_breakdown.map((row) => [
          row.month_name, row.deposits, row.manual_income, row.expense_total, row.net, row.charge_total, row.debt_total,
        ]),
        null,
        ["text", "money", "money", "money", "money", "money", "money"]
      ));
    }
    if (data.debts) {
      blocks.push(`<h3 class="report-preview-title">مطالبات</h3>`);
      blocks.push(renderReportPreviewTable(
        ["نهاد", "سال", "ماه", "مستحق", "دریافت", "مانده", "وضعیت"],
        data.debts.map((row) => [
          row.team_name, row.fiscal_year, row.month_name, row.amount_due, row.amount_paid, row.amount_remaining, row.status,
        ]),
        "مطالبه‌ای در این بازه نیست.",
        ["text", "text", "text", "money", "money", "money", "text"]
      ));
    }
    if (data.charges) {
      blocks.push(`<h3 class="report-preview-title">شارژ</h3>`);
      blocks.push(renderReportPreviewTable(
        ["نهاد", "سال", "ماه", "شارژ", "اجاره", "جمع", "یادداشت"],
        data.charges.map((row) => [
          row.team_name, row.fiscal_year, row.month_name, row.charge_amount, row.rent_amount, row.amount, row.note || "—",
        ]),
        "شارژی در این بازه نیست.",
        ["text", "text", "text", "money", "money", "money", "text"]
      ));
    }
    if (data.transactions) {
      blocks.push(`<h3 class="report-preview-title">تراکنش‌ها</h3>`);
      blocks.push(renderReportPreviewTable(
        ["تاریخ", "شرح", "مبلغ", "دسته", "نهاد"],
        data.transactions.slice(0, 50).map((row) => [
          row.tx_date, row.description, row.amount, row.category_label || row.category, row.team_name,
        ]),
        "تراکنشی در این بازه نیست.",
        ["text", "text", "money", "text", "text"]
      ));
      if (data.transactions.length > 50) {
        blocks.push(`<p class="hint">نمایش ۵۰ تراکنش اول از ${formatNumber(data.transactions.length)} مورد — برای لیست کامل چاپ/PDF بگیرید.</p>`);
      }
    }
    if (data.teams) {
      blocks.push(`<h3 class="report-preview-title">نهادها</h3>`);
      blocks.push(renderReportPreviewTable(
        ["کد", "نام", "مسئول", "میز"],
        data.teams.map((row) => [row.entity_code, row.name, row.leader, row.desk_count || 0]),
        null,
        ["text", "text", "text", "count"]
      ));
    }
    if (data.members) {
      blocks.push(`<h3 class="report-preview-title">اعضا</h3>`);
      blocks.push(renderReportPreviewTable(
        ["کد", "نام", "نهاد", "تماس"],
        data.members.slice(0, 50).map((row) => [row.member_code, row.full_name, row.team_label, row.phone]),
        "عضوی نیست.",
        ["text", "text", "text", "text"]
      ));
    }
    if (data.desks) {
      blocks.push(`<h3 class="report-preview-title">میزها</h3>`);
      blocks.push(renderReportPreviewTable(
        ["شماره", "نهاد", "نوع"],
        data.desks.map((row) => [row.number, row.team_name || "آزاد", usageLabels[row.usage_type] || row.usage_type || "—"]),
        null,
        ["count", "text", "text"]
      ));
    }
    if (data.lockers) {
      blocks.push(`<h3 class="report-preview-title">کمدها</h3>`);
      blocks.push(renderReportPreviewTable(
        ["شماره", "وضعیت", "نهاد"],
        data.lockers.map((row) => [row.locker_number, row.status, row.team_label || "—"]),
        null,
        ["count", "text", "text"]
      ));
    }
    host.innerHTML = blocks.join("") || renderEmptyState("برای این انتخاب داده‌ای یافت نشد.", { icon: "search" });
  } catch (error) {
    host.innerHTML = renderEmptyState("ساخت گزارش ناموفق بود.", { icon: "error" });
    showToast(error.message || "خطا در ساخت گزارش", "error");
  }
};

const initReportBuilder = async () => {
  const form = document.getElementById("reportBuilderForm");
  if (!form || panelMode !== "admin") return;
  if (!reportCatalog) {
    reportCatalog = await fetchJson("api.php?resource=report-catalog");
  }
  const defaults = reportCatalog.defaults || {};
  if (!form.dataset.initialized) {
    reportSelectedType = reportSelectedType || defaults.type || "finance";
    fillSelectOptions(
      document.getElementById("reportFiscalYear"),
      (reportCatalog.fiscal_years || []).map((year) => ({ id: year, label: year })),
      defaults.fiscal_year
    );
    fillSelectOptions(document.getElementById("reportMonth"), reportCatalog.months || [], defaults.month);
    fillSelectOptions(document.getElementById("reportMonthFrom"), reportCatalog.months || [], 1);
    fillSelectOptions(document.getElementById("reportMonthTo"), reportCatalog.months || [], 12);
    fillSelectOptions(document.getElementById("reportQuarter"), reportCatalog.quarters || [], defaults.quarter);
    const teamOptions = [{ id: 0, label: "همه نهادها" }].concat(
      (reportCatalog.teams || []).map((team) => ({ id: team.id, label: team.name }))
    );
    fillSelectOptions(document.getElementById("reportTeam"), teamOptions, defaults.team_id || 0);
    const periodSelect = document.getElementById("reportPeriod");
    if (periodSelect) {
      periodSelect.value = defaults.period || "monthly";
      periodSelect.addEventListener("change", syncReportPeriodFields);
    }
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await previewReport();
    });
    document.getElementById("reportOpenPrint")?.addEventListener("click", () => {
      const filters = reportLastFilters || collectReportFilters();
      window.open(`report.php?${reportQueryString(filters)}`, "_blank", "noopener");
    });
    document.getElementById("reportOpenExcel")?.addEventListener("click", () => {
      const filters = reportLastFilters || collectReportFilters();
      const excelReport = ({
        overview: "summary",
        finance: "finance",
        transactions: "transactions",
        charges: "charges",
        debts: "debts",
        teams: "teams",
        members: "members",
        desks: "desks",
        lockers: "lockers",
        rooms: "rooms",
        full: "all",
      })[filters.type] || "all";
      const params = new URLSearchParams({ report: excelReport });
      // Always pass period/team when available so Excel matches the preview filters.
      params.set("fiscal_year", filters.fiscal_year || "");
      let monthFrom = Number(filters.month_from || filters.month || 1);
      let monthTo = Number(filters.month_to || filters.month || 12);
      if (filters.period === "quarterly") {
        const q = Number(filters.quarter || 1);
        monthFrom = ((q - 1) * 3) + 1;
        monthTo = q * 3;
      } else if (filters.period === "annual" || !reportTypeSupportsPeriod(filters.type)) {
        monthFrom = 1;
        monthTo = 12;
      } else if (filters.period === "monthly") {
        monthFrom = Number(filters.month || 1);
        monthTo = monthFrom;
      }
      params.set("month_from", String(monthFrom));
      params.set("month_to", String(monthTo));
      if (filters.team_id) params.set("team_id", String(filters.team_id));
      window.open(`export.php?${params.toString()}`, "_blank", "noopener");
    });
    form.dataset.initialized = "1";
  }
  renderReportTypeCards();
  syncReportPeriodFields();
};

const activateSection = (id, options = {}) => {
  if (!id) return;
  if (options.highlightDesk !== undefined) highlightDesk = options.highlightDesk;
  if (options.highlightLocker !== undefined) highlightLocker = options.highlightLocker;

  const target = document.getElementById(id);
  if (!target || !target.classList.contains("section")) {
    // Missing section (e.g. desk-history on team panel) must not blank the whole UI.
    const fallback = document.querySelector(".section.active")?.id
      || document.querySelector(".nav-item, .bottom-nav-item[data-section]")?.dataset?.section
      || "";
    if (fallback && fallback !== id) {
      activateSection(fallback, { ...options, updateHash: options.updateHash });
    }
    return;
  }

  document.querySelectorAll(".section").forEach((s) => s.classList.toggle("active", s.id === id));
  document.querySelectorAll(".nav-item, .bottom-nav-item[data-section]").forEach((i) => {
    i.classList.toggle("active", i.dataset.section === id);
  });

  updatePageHeader(id);
  closeDrawer();
  if (options.updateHash !== false && id) {
    const nextHash = `#${id}`;
    if (location.hash !== nextHash) {
      history.replaceState(null, "", `${location.pathname}${location.search}${nextHash}`);
    }
  }
  if (id === "members" && panelMode === "admin") {
    initMemberFilters().catch((error) => showToast(error.message, "error"));
    reloadSectionTables(id);
  } else {
    reloadSectionTables(id);
  }

  if (id === "desks") {
    loadDeskGrid().catch((error) => showToast(error.message, "error"));
  }
  if (id === "desk-history" && panelMode === "admin") {
    initDeskHistoryFilters().catch((error) => showToast(error.message, "error"));
  }
  if (id === "desks" && panelMode === "team") {
    loadTeamDeskAssignments().catch((error) => showToast(error.message, "error"));
  }
  if (id === "performance-reports") {
    loadPerformanceReportsSection().catch((error) => {
      showToast(error.message, "error");
      renderSectionLoadError("performanceReportsContent", "بارگذاری گزارش‌های عملکرد ناموفق بود.", () => loadPerformanceReportsSection());
    });
  }
  if (id === "team-contracts" && panelMode === "admin") {
    loadPendingContractsQueue().catch((error) => {
      showToast(error.message, "error");
      renderSectionLoadError("pendingContractsQueue", "بارگذاری صف قراردادها ناموفق بود.", () => loadPendingContractsQueue());
    });
  }
  if (id === "contracts" && panelMode === "team") {
    loadTeamContractsSection().catch((error) => {
      showToast(error.message, "error");
      renderSectionLoadError("teamContractsContent", "بارگذاری قراردادها ناموفق بود.", () => loadTeamContractsSection());
    });
  }
  if (id === "performance-settings") loadPerformanceSettingsForm().catch((error) => showToast(error.message, "error"));
  if (id === "file-manager" && panelMode === "admin") {
    loadFileManager().catch((error) => {
      showToast(error.message, "error");
      renderSectionLoadError("fileManagerContent", "بارگذاری مدیریت فایل ناموفق بود.", () => loadFileManager());
    });
  }
  if (id === "profile" && panelMode === "team") loadTeamProfile().catch((error) => showToast(error.message, "error"));
  if (id === "charges") {
    loadChargesCollage().catch((error) => showToast(error.message, "error"));
    if (panelMode === "team") loadTeamChargeRates().catch((error) => showToast(error.message, "error"));
  }
  if (id === "transactions" && canWrite) loadPaymentSettings().catch((error) => showToast(error.message, "error"));
  if (id === "transactions" && panelMode === "admin") loadLedger().catch((error) => showToast(error.message, "error"));
  if (id === "payments" && panelMode === "team") {
    loadPaymentGuide().catch((error) => showToast(error.message, "error"));
    loadTeamPaymentWizard().catch((error) => showToast(error.message, "error"));
  }
  if (id === "sms" && panelMode === "admin") {
    window.initSmsPanel?.();
  }
  if (id === "sms-settings" && panelMode === "admin") {
    window.initSmsSettingsPanel?.();
  }
  if (id === "reports" && panelMode === "admin") {
    initReportBuilder().catch((error) => showToast(error.message, "error"));
  }
  if ((id === "meeting-rooms" || id === "room-reservations") && window.initRoomCalendar) {
    window.initRoomCalendar();
  }
  if (options.scrollTarget) {
    setTimeout(() => {
      document.querySelector(`data-table[data-table-key="${options.scrollTarget}"]`)
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
    }, 180);
  }
  if (options.teamId) {
    setTimeout(() => openTeamProfile(options.teamId).catch((error) => showToast(error.message, "error")), 120);
  }
};

document.querySelectorAll(".nav-item, .bottom-nav-item[data-section]").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.section));
});

document.getElementById("menuToggle")?.addEventListener("click", openDrawer);
document.getElementById("bottomNavMenu")?.addEventListener("click", openDrawer);
document.getElementById("sidebarBackdrop")?.addEventListener("click", closeDrawer);

document.querySelectorAll(".start-step[data-go], .text-link[data-go], .button[data-go]").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.go));
});

document.querySelectorAll(".quick-nav-item[data-section]").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.section));
});

const focusActiveSectionSearch = (query = "") => {
  const section = document.querySelector(".section.active");
  if (!section) return;
  const search = section.querySelector("data-table .search, #smsFilterBar [data-filter='q']");
  if (!search) return;
  search.focus();
  if (query) {
    search.value = query;
    search.dispatchEvent(new Event("input", { bubbles: true }));
  }
};

document.getElementById("globalSearch")?.addEventListener("keydown", (event) => {
  if (event.key !== "Enter") return;
  event.preventDefault();
  focusActiveSectionSearch(event.target.value.trim());
});

document.getElementById("globalSearch")?.addEventListener("focus", () => {
  focusActiveSectionSearch();
});

document.getElementById("themeToggle")?.addEventListener("click", () => {
  const html = document.documentElement;
  const next = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
  html.setAttribute("data-theme", next);
  try {
    localStorage.setItem("mechinno-theme", next);
  } catch (e) {}
});

const accountMenu = document.getElementById("accountMenu");
const accountMenuTrigger = document.getElementById("accountMenuTrigger");
const accountMenuDropdown = document.getElementById("accountMenuDropdown");
const setAccountMenuOpen = (open) => {
  if (!accountMenu || !accountMenuTrigger || !accountMenuDropdown) return;
  accountMenu.classList.toggle("is-open", open);
  accountMenuTrigger.setAttribute("aria-expanded", open ? "true" : "false");
  accountMenuDropdown.hidden = !open;
};
accountMenuTrigger?.addEventListener("click", (event) => {
  event.stopPropagation();
  setAccountMenuOpen(accountMenuDropdown?.hidden !== false);
});
document.addEventListener("click", (event) => {
  if (!accountMenu || accountMenu.contains(event.target)) return;
  setAccountMenuOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") setAccountMenuOpen(false);
});
accountMenuDropdown?.addEventListener("click", (event) => {
  // Keep menu open when toggling theme; close for other actions / navigation.
  if (event.target.closest("#themeToggle")) return;
  if (event.target.closest("a, button")) setAccountMenuOpen(false);
});

const teamLink = (teamId, label) => {
  if (!teamId || !label) return escapeHtml(label || "—");
  return `<button type="button" class="text-link" data-team-id="${escapeHtml(teamId)}">${escapeHtml(label)}</button>`;
};

const deskLink = (number) =>
  `<button type="button" class="text-link" data-nav-section="desks" data-highlight-desk="${escapeHtml(number)}">میز ${escapeHtml(number)}</button>`;

const lockerLink = (number) =>
  `<button type="button" class="text-link" data-nav-section="lockers" data-highlight-locker="${escapeHtml(number)}">کمد ${escapeHtml(number)}</button>`;

const entityBadge = (type) => {
  const label = entityTypeLabels[type] || type || "—";
  const cls = type === "company" ? "badge-company" : type === "student" ? "badge-student" : "badge-team";
  return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
};

const lockerStatusBadge = (status) => {
  const map = {
    "خالی": "badge-locker-empty",
    "تخصیص یافته": "badge-locker-assigned",
    "رزرو": "badge-locker-reserved",
    "خراب": "badge-locker-broken",
  };
  return `<span class="badge ${map[status] || "badge-locker-empty"}">${escapeHtml(status || "—")}</span>`;
};

document.addEventListener("click", (event) => {
  const deskTile = event.target.closest(".desk-tile[data-desk-number]");
  if (deskTile && panelMode === "admin" && canWrite) {
    if (event.target.closest("[data-team-id]")) return;
    event.preventDefault();
    event.stopPropagation();
    window.TeamYearWorkspace?.openDeskAssignModal(Number(deskTile.dataset.deskNumber))
      .catch((error) => showToast(error.message, "error"));
    return;
  }

  const link = event.target.closest(".text-link[data-nav-section], .text-link[data-team-id], .action-item, .card-clickable, .debt-link, .desk-tile");
  if (!link) return;

  if (link.dataset.teamId) {
    event.preventDefault();
    event.stopPropagation();
    openTeamProfile(Number(link.dataset.teamId)).catch((error) => showToast(error.message, "error"));
    return;
  }

  if (!link.dataset.navSection) return;
  event.preventDefault();
  const openTeamId = link.dataset.openTeam ? Number(link.dataset.openTeam) : undefined;
  activateSection(link.dataset.navSection, {
    highlightDesk: link.dataset.highlightDesk ? Number(link.dataset.highlightDesk) : undefined,
    highlightLocker: link.dataset.highlightLocker ? Number(link.dataset.highlightLocker) : undefined,
    teamId: openTeamId,
    scrollTarget: link.dataset.scrollTarget || undefined,
  });
});

const resolveCardSection = (key) => {
  if (panelMode === "team") {
    if (key === "pending_payments" || key === "paid_total") return "payments";
    if (key === "charge_total" || key === "debt_total") return "charges";
    if (key === "desks") return "desks";
    if (key === "members") return "members";
  }
  return cardNavMap[key] || "members";
};

const renderCards = (cards, config = cardConfig, containerId = "cards") => {
  const container = document.getElementById(containerId);
  if (!container) return;
  const source = cards && typeof cards === "object" ? cards : {};
  container.innerHTML = config
    .map(([key, title, icon, tone]) => {
      const raw = source[key];
      const missing = raw === undefined || raw === null || raw === "";
      let value = "—";
      if (!missing) {
        if (key === "desks" && panelMode === "team" && source.desk_numbers) value = source.desk_numbers || "—";
        else if (key === "desks" && panelMode === "team") value = formatNumber(source.desks ?? raw);
        else if (key === "desks_occupied") {
          value = `${formatNumber(source.desks_occupied ?? 0)} / ${formatNumber(source.desks_total ?? 0)}`;
        } else if (moneyCards.has(key)) value = formatMoney(raw);
        else value = formatNumber(raw);
      }
      const section = resolveCardSection(key);
      const alert = !missing && ["pending_payments", "pending_members", "pending_contracts", "pending_performance", "debt_total"].includes(key) && Number(raw) > 0;
      return `<article class="stat-card stat-card--${tone}${alert ? " is-alert" : ""}${missing ? " is-empty" : ""} card-clickable" data-nav-section="${section}" tabindex="0" role="button">
        <span class="stat-icon" aria-hidden="true">${icon}</span>
        <div><span class="stat-label">${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong></div>
      </article>`;
    })
    .join("");
};

const renderCurrentMonth = (month) => {
  const label = document.getElementById("currentMonthLabel");
  const container = document.getElementById("currentMonthSummary");
  if (!month || !container) return;
  if (label) label.textContent = `${month.month_name} ${month.fiscal_year}`;
  const paidLabel = panelMode === "team" ? "تخصیص به این ماه" : "تخصیص واریز به ماه";
  const debtLabel = panelMode === "team" ? "مانده این ماه" : "مانده مطالبه ماه";
  const chargeLabel = "شارژ این ماه";
  container.innerHTML = `
    <div class="month-stat"><span>${escapeHtml(chargeLabel)}</span><strong>${escapeHtml(formatMoney(month.charge_total))}</strong></div>
    <div class="month-stat"><span>${escapeHtml(paidLabel)}</span><strong>${escapeHtml(formatMoney(month.paid_total))}</strong></div>
    <div class="month-stat"><span>${escapeHtml(debtLabel)}</span><strong class="debt-value">${escapeHtml(formatMoney(month.debt_total))}</strong></div>
    ${panelMode === "admin" ? `<div class="month-stat"><span>نهاد دارای مانده</span><strong>${escapeHtml(formatNumber(month.debtor_count))}</strong></div>` : ""}`;
};

const renderActionItems = (items) => {
  const container = document.getElementById("actionItems");
  if (!container) return;
  if (!items?.length) {
    container.innerHTML = renderEmptyState("همه‌چیز مرتب است — مورد فوری نیست.", { icon: "inbox" });
    return;
  }
  container.innerHTML = items.map((item) => `
    <button type="button" class="action-item action-${escapeHtml(item.type || "default")}"
      data-nav-section="${escapeHtml(item.section)}"
      ${item.target ? `data-scroll-target="${escapeHtml(item.target)}"` : ""}
      ${item.team_id ? `data-open-team="${escapeHtml(item.team_id)}"` : ""}>
      <strong>${escapeHtml(item.label)}</strong>
      <span>${escapeHtml(item.detail || "")}</span>
    </button>`).join("");
};

const renderChargeChart = (rows) => {
  const container = document.getElementById("chargeChart");
  if (!container) return;
  const compact = panelMode === "team";
  const source = compact ? rows.slice(-6) : rows.slice(-10);
  const max = Math.max(...source.map((r) => Number(r.amount || 0)), 1);
  container.classList.toggle("bar-chart--compact", compact);
  container.innerHTML = source.length
    ? source.map((row) => `
    <div class="bar-row ${compact ? "bar-row--compact" : ""}">
      <span>${escapeHtml(row.fiscal_year)} ${escapeHtml(row.month_name)}</span>
      <div class="bar-track"><div class="bar-fill" style="width:${(Number(row.amount || 0) / max) * 100}%"></div></div>
      <strong>${escapeHtml(formatMoney(row.amount))}</strong>
    </div>`).join("")
    : renderEmptyState("داده‌ای موجود نیست.", { icon: "chart" });
};

const renderDebtChart = (rows) => {
  const container = document.getElementById("debtChart");
  if (!container) return;
  const max = Math.max(...rows.map((r) => Number(r.debt || 0)), 1);
  container.innerHTML = rows.length
    ? rows.map((row) => `
      <div class="bar-row">
        <button type="button" class="text-link debt-link" data-team-id="${escapeHtml(row.team_id)}">${escapeHtml(row.team_name || "—")}</button>
        <div class="bar-track"><div class="bar-fill danger-fill" style="width:${(Number(row.debt || 0) / max) * 100}%"></div></div>
        <strong>${escapeHtml(formatMoney(row.debt))}</strong>
      </div>`).join("")
    : renderEmptyState("مطالبه ثبت‌شده‌ای از نهادها نیست.", { icon: "chart" });
};

const renderDashboardHero = (cards = {}, team = null) => {
  const titleEl = document.getElementById("dashboardHeroTitle");
  const subtitleEl = document.getElementById("dashboardHeroSubtitle");
  const heroMeta = document.getElementById("dashboardHeroMeta");
  if (!titleEl) return;

  const username = window.MECHINNO?.username || "";
  const greeting = username ? `سلام، ${username}` : "خوش آمدید";

  if (panelMode === "team" && team?.name) {
    titleEl.textContent = team.name;
    if (subtitleEl) subtitleEl.textContent = "اعضا، میز و وضعیت شارژ";
  } else {
    titleEl.textContent = greeting;
    const pending = Number(cards?.pending_payments || 0)
      + Number(cards?.pending_members || 0)
      + Number(cards?.pending_contracts || 0)
      + Number(cards?.pending_performance || 0)
      + Number(cards?.pending_rooms || 0)
      + Number(cards?.pending_locker_requests || 0);
    if (subtitleEl) {
      subtitleEl.textContent = pending > 0
        ? `${formatNumber(pending)} مورد در انتظار رسیدگی`
        : "وضعیت مالی و ظرفیت مرکز";
    }
  }

  // Keep hero meta to date + fiscal year only (KPIs live in the card strip).
  if (heroMeta && panelMode !== "team") {
    const todayEl = document.getElementById("heroToday");
    const yearEl = document.getElementById("heroFiscalYear");
    if (todayEl) todayEl.textContent = window.MECHINNO?.today || todayEl.textContent || "—";
    if (yearEl) yearEl.textContent = window.MECHINNO?.fiscalYear || yearEl.textContent || "—";
  }
};

const loadDashboard = async () => {
  const data = await fetchJson("api.php?resource=summary");
  if (panelMode === "team") {
    renderTeamDashboard(data);
    return;
  }
  renderDashboardHero(data.cards || {});
  renderCurrentMonth(data.current_month || {});
  renderActionItems(data.action_items || []);
  renderCards(data.cards || {}, adminCardConfig, "cards");
  renderChargeChart(data.monthly_charges || []);
  renderDebtChart((data.debt_by_team || []).slice(0, 6));
  const welcome = document.getElementById("welcomePanel");
  if (welcome) welcome.hidden = Number(data.cards?.teams || 0) > 0;
};

const renderRecentApprovals = (items, actionItems = []) => {
  const container = document.getElementById("recentApprovals");
  if (!container) return;
  const actionHtml = (actionItems || []).map((item) => `
    <button type="button" class="action-item action-${escapeHtml(item.type || "default")}"
      data-nav-section="${escapeHtml(item.section || "overview")}">
      <div class="action-item-head">
        <strong>${escapeHtml(item.label || "—")}</strong>
        <span class="badge badge-debt">اقدام</span>
      </div>
      <span>${escapeHtml(item.detail || "")}</span>
    </button>`).join("");
  if (!items?.length && !actionHtml) {
    container.innerHTML = renderEmptyState("هنوز تأیید یا ردی از مرکز ثبت نشده است.", { icon: "inbox" });
    return;
  }
  const approvalHtml = (items || []).map((item) => {
    const statusClass = item.status === "approved" ? "action-payment" : "action-debt";
    const badge = item.status === "approved" ? "badge-paid" : "badge-debt";
    const statusLabel = item.status === "approved" ? "تأیید‌شده" : "رد‌شده";
    return `<button type="button" class="action-item ${statusClass}"
      data-nav-section="${escapeHtml(item.section || "overview")}">
      <div class="action-item-head">
        <strong>${escapeHtml(item.label || "—")}</strong>
        <span class="badge ${badge}">${escapeHtml(statusLabel)}</span>
      </div>
      <span>${escapeHtml(item.detail || "")}</span>
      ${item.reason ? `<small class="hint">${escapeHtml(item.reason)}</small>` : ""}
      ${item.date ? `<small class="hint">${escapeHtml(item.date)}</small>` : ""}
    </button>`;
  }).join("");
  container.innerHTML = actionHtml + approvalHtml;
};

const renderTeamDashboard = (data) => {
  const cards = document.getElementById("cards");
  const team = data.team || {};
  renderDashboardHero(data.cards || {}, team);
  if (cards) {
    renderCards({ ...data.cards, desk_numbers: data.cards?.desk_numbers || "—" }, teamCardConfig);
  }
  renderCurrentMonth(data.current_month || {});
  renderRecentApprovals(data.recent_approvals || [], data.action_items || []);
  renderChargeChart((data.monthly_charges || []).map((row) => ({
    fiscal_year: row.fiscal_year,
    month_name: row.month_name,
    amount: row.amount,
  })));
  const title = document.getElementById("pageTitle");
  if (title && team.name && document.querySelector(".section.active")?.id === "overview") {
    title.textContent = team.name;
  }
};

const entryTypeLabel = (type) => ({
  deposit: "دریافت نقدی",
  income: "درآمد",
  expense: "هزینه",
}[type] || type || "—");

let ledgerPage = 1;

const loadLedger = async (page = ledgerPage) => {
  const summaryBody = document.getElementById("ledgerSummaryBody");
  const tableBody = document.getElementById("ledgerTableBody");
  const billingWrap = document.getElementById("ledgerBillingWrap");
  const billingBody = document.getElementById("ledgerBillingBody");
  const pager = document.getElementById("ledgerPager");
  if (!summaryBody || !tableBody) return;

  ledgerPage = Math.max(1, Number(page) || 1);
  const data = await fetchJson(`api.php?resource=ledger&page=${ledgerPage}&per_page=100`);
  const totals = data.totals || {};
  const billing = data.billing || {};
  const balance = Number(totals.balance ?? data.balance ?? 0);
  const pages = Math.max(1, Number(data.pages || 1));
  ledgerPage = Math.min(ledgerPage, pages);

  summaryBody.innerHTML = `
    <tr class="ledger-row-balance ${balance < 0 ? "ledger-negative-row" : ""}">
      <th scope="row">موجودی نقدی فعلی</th>
      <td class="num ledger-balance-cell">${escapeHtml(formatMoney(balance))}</td>
    </tr>
    <tr><th scope="row">دریافت از نهادها</th><td class="num">${escapeHtml(formatMoney(totals.deposits || 0))}</td></tr>
    <tr><th scope="row">درآمد دستی</th><td class="num">${escapeHtml(formatMoney(totals.manual_income || 0))}</td></tr>
    <tr><th scope="row">هزینه‌ها</th><td class="num ledger-expense">${escapeHtml(formatMoney(totals.manual_expense || 0))}</td></tr>
    <tr><th scope="row">جمع دریافت‌ها</th><td class="num">${escapeHtml(formatMoney(totals.income_total || 0))}</td></tr>`;

  if (billingWrap && billingBody) {
    billingWrap.hidden = false;
    billingBody.innerHTML = `
      <tr>
        <td class="num">${escapeHtml(formatMoney(billing.charge_total || 0))}</td>
        <td class="num">${escapeHtml(formatMoney(billing.received_total || 0))}</td>
        <td class="num">${escapeHtml(formatMoney(billing.receivable || 0))}</td>
      </tr>`;
  }

  const rows = data.rows || [];
  if (!rows.length) {
    tableBody.innerHTML = `<tr><td colspan="7" class="empty-cell">${renderEmptyState("هنوز گردش نقدی ثبت نشده است.", { icon: "chart" })}</td></tr>`;
    if (pager) pager.innerHTML = "";
    return;
  }

  tableBody.innerHTML = [...rows].reverse().map((row) => {
    const signed = Number(row.signed_amount ?? row.amount ?? 0);
    const debit = signed < 0 ? formatMoney(Math.abs(signed)) : "—";
    const credit = signed > 0 ? formatMoney(signed) : "—";
    return `<tr>
      <td>${escapeHtml(String(row.line_no ?? "—"))}</td>
      <td>${escapeHtml(formatPlain(row.tx_date))}</td>
      <td>${escapeHtml(row.entry_type_label || entryTypeLabel(row.entry_type))}</td>
      <td class="ledger-desc" title="${escapeHtml(row.description || "")}">${escapeHtml(row.description || "—")}</td>
      <td class="num ledger-income">${escapeHtml(credit)}</td>
      <td class="num ledger-expense">${escapeHtml(debit)}</td>
      <td class="num ledger-balance-cell">${escapeHtml(formatMoney(row.running_balance ?? 0))}</td>
    </tr>`;
  }).join("");

  if (pager) {
    const total = Number(data.total || rows.length);
    pager.innerHTML = pages > 1
      ? `<button type="button" class="button ghost" ${ledgerPage <= 1 ? "disabled" : ""} data-ledger-page="${ledgerPage - 1}">قبلی</button>
         <span class="hint">صفحه ${ledgerPage} از ${pages} — ${total} ردیف</span>
         <button type="button" class="button ghost" ${ledgerPage >= pages ? "disabled" : ""} data-ledger-page="${ledgerPage + 1}">بعدی</button>`
      : `<span class="hint">${total} ردیف گردش نقدی</span>`;
    pager.querySelectorAll("[data-ledger-page]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (btn.hasAttribute("disabled")) return;
        loadLedger(Number(btn.getAttribute("data-ledger-page") || 1));
      });
    });
  }
};

const loadTeamDeskAssignments = async () => {
  const host = document.getElementById("teamDeskAssignments");
  if (!host) return;
  const { rows } = await fetchResource("api.php?resource=desk-assignments", { page: 1, perPage: 200 });
  if (!rows.length) {
    host.classList.add("is-ready");
    host.innerHTML = renderEmptyState("هنوز سابقه تخصیص میزی برای نهاد شما ثبت نشده است.", { icon: "desk" });
    return;
  }

  const isActiveAssignment = (row) => String(row.assignment_status || "") === "active";
  const currentYear = String(window.MECHINNO?.fiscalYear || "");
  const activeRows = rows.filter(isActiveAssignment);
  const historyRows = rows.filter((row) => !isActiveAssignment(row));
  const renderCard = (row, isActive = false) => `
    <article class="desk-assignment-card${isActive ? " is-active" : ""}">
      <div class="desk-assignment-card-head">
        <strong>میز ${escapeHtml(row.desk_number)}</strong>
        <span class="badge">${escapeHtml(row.fiscal_year || "—")}</span>
      </div>
      <span class="badge">${escapeHtml(usageLabels[row.usage_type] || row.usage_type || "—")}</span>
      <div class="desk-assignment-dates">
        <span>${escapeHtml(row.assignment_period || formatMonthRange(row.assigned_from, row.assigned_until))}</span>
        ${isActive ? `<span class="hint">فعال${row.assigned_until ? "" : " — بدون تاریخ تحویل"}</span>` : ""}
      </div>
      ${row.notes ? `<p class="hint">${escapeHtml(row.notes)}</p>` : ""}
    </article>`;

  const activeHtml = activeRows.length
    ? `<div class="desk-assignment-section">
        <h3>میزهای فعال${currentYear ? ` سال ${escapeHtml(currentYear)}` : ""}</h3>
        <div class="desk-assignment-grid">${activeRows.map((row) => renderCard(row, true)).join("")}</div>
      </div>`
    : `<div class="desk-assignment-section"><h3>میزهای فعال</h3>${renderEmptyState("در حال حاضر میز فعالی ثبت نشده است.", { icon: "desk" })}</div>`;

  const historyHtml = historyRows.length
    ? `<div class="desk-assignment-section">
        <h3>سوابق و تخصیص‌های پایان‌یافته</h3>
        <div class="desk-assignment-grid">${historyRows.map((row) => renderCard(row)).join("")}</div>
      </div>`
    : "";

  host.classList.add("is-ready");
  host.innerHTML = `${activeHtml}${historyHtml}`;
};

const docStatusBadge = (status) => {
  if (status === "approved") return `<span class="badge badge-ok">تأیید‌شده</span>`;
  if (status === "rejected") return `<span class="badge badge-danger">رد‌شده</span>`;
  if (status === "pending") return `<span class="badge badge-partial">در انتظار</span>`;
  return `<span class="badge">${escapeHtml(status || "—")}</span>`;
};

const renderContractReviewTable = (rows, { showActions = false } = {}) => {
  if (!rows.length) {
    return renderEmptyState("موردی در انتظار تأیید نیست.", { icon: "inbox" });
  }
  const showActionColumn = showActions && canWrite;
  return `<div class="table-wrap"><table class="data-table review-list-table">
    <thead><tr>
      <th>نهاد</th><th>سال</th><th>بازه</th><th>مبلغ</th><th>پیوست عضویت</th><th>پیوست استقرار</th>
      ${showActionColumn ? "<th>عملیات</th>" : "<th>وضعیت</th>"}
    </tr></thead>
    <tbody>${rows.map((row) => {
      const membership = row.files?.membership;
      const settlement = row.files?.settlement;
      const canApprove = Boolean(row.can_approve);
      return `<tr>
        <td>${escapeHtml(row.team_name || "—")}<div class="hint">${escapeHtml(row.entity_code || "")}</div></td>
        <td>${escapeHtml(row.fiscal_year || "—")}</td>
        <td>${escapeHtml(formatPlain(row.contract_start))} تا ${escapeHtml(formatPlain(row.contract_end))}</td>
        <td>${escapeHtml(formatMoney(row.formal_contract_amount || 0))}</td>
        <td>${membership ? `<a class="text-link" href="${escapeHtml(membership.download_url)}" target="_blank" rel="noopener">${escapeHtml(membership.original_name)}</a>` : "<span class=\"hint\">ندارد</span>"}</td>
        <td>${settlement ? `<a class="text-link" href="${escapeHtml(settlement.download_url)}" target="_blank" rel="noopener">${escapeHtml(settlement.original_name)}</a>` : "<span class=\"hint\">ندارد</span>"}</td>
        <td>${showActionColumn ? `<div class="row-actions review-list-actions">
            <button class="mini-button primary" type="button" data-approve-proposal="${row.id}" ${canApprove ? "" : "disabled"}>تأیید</button>
            <button class="mini-button danger" type="button" data-reject-proposal="${row.id}">رد</button>
            ${!canApprove ? `<div class="hint reject-hint">${row.has_official
              ? "برای این سال قرارداد رسمی ثبت شده — ابتدا همان را بررسی/حذف کنید یا پیشنهاد را رد کنید."
              : "هر دو پیوست لازم است — برای باز کردن مسیر نهاد، پیشنهاد را رد کنید."}</div>` : ""}
          </div>` : docStatusBadge(row.status)}
        </td>
      </tr>`;
    }).join("")}</tbody>
  </table></div>${showActions && !canWrite ? `<p class="hint">فقط مدیر ویرایشگر می‌تواند تأیید یا رد کند.</p>` : ""}`;
};

const bindContractReviewActions = (root) => {
  const lockRowActions = (button, locked) => {
    button.closest(".review-list-actions")?.querySelectorAll("button").forEach((btn) => {
      btn.disabled = locked;
    });
  };
  root.querySelectorAll("[data-approve-proposal]").forEach((button) => {
    button.addEventListener("click", async () => {
      lockRowActions(button, true);
      try {
        await postJson("api.php?resource=pending-contract-proposals&action=approve", { id: Number(button.dataset.approveProposal) });
        showToast("قرارداد با پیوست‌ها تأیید و ثبت شد.", "success");
        await loadPendingContractsQueue();
        await refreshAfterMutation("team-contracts");
      } catch (error) {
        showToast(error.message, "error");
        lockRowActions(button, false);
      }
    });
  });
  root.querySelectorAll("[data-reject-proposal]").forEach((button) => {
    button.addEventListener("click", async () => {
      lockRowActions(button, true);
      try {
        const reason = await askRejectReason({ required: true, title: "رد قرارداد" });
        await postJson("api.php?resource=pending-contract-proposals&action=reject", {
          id: Number(button.dataset.rejectProposal),
          reason: String(reason).trim(),
        });
        showToast("قرارداد رد شد؛ نهاد می‌تواند اصلاحیه بفرستد.", "success");
        await loadPendingContractsQueue();
        await refreshAfterMutation("team-contracts");
      } catch (error) {
        if (error.message !== "cancelled") showToast(error.message, "error");
        lockRowActions(button, false);
      }
    });
  });
};

const loadPendingContractsQueue = async () => {
  const pendingHost = document.getElementById("pendingContractsQueue");
  if (!pendingHost) return;
  const data = await fetchJson("api.php?resource=pending-contract-proposals");
  pendingHost.classList.add("is-ready");
  pendingHost.innerHTML = renderContractReviewTable(data.rows || [], { showActions: true });
  bindContractReviewActions(pendingHost);
};

const loadTeamContractsSection = async () => {
  const host = document.getElementById("teamContractsContent");
  if (!host) return;
  const data = await fetchJson("api.php?resource=contract-documents&overview=1");
  const years = data.years || [];
  const currentYear = String(data.current_year || window.MECHINNO?.fiscalYear || "");
  const labels = data.doc_labels || { membership: "قرارداد عضویت", settlement: "قرارداد استقرار" };

  const renderYearCard = (bundle) => {
    const year = String(bundle.fiscal_year || "");
    const official = bundle.official_contract;
    const proposal = bundle.proposal;
    const membership = bundle.files?.membership;
    const settlement = bundle.files?.settlement;
    const canSubmit = Boolean(bundle.can_submit);
    const registered = Boolean(bundle.is_registered);
    const status = registered
      ? docStatusBadge("approved")
      : proposal
        ? docStatusBadge(proposal.status)
        : `<span class="badge">ثبت‌نشده</span>`;

    const filesHtml = `<div class="contract-files-grid">
      <div class="contract-file-slot"><div class="contract-file-slot-head"><h4>${escapeHtml(labels.membership || "قرارداد عضویت")}</h4></div>
        ${membership ? `<div class="contract-file-meta"><strong>${escapeHtml(membership.original_name)}</strong>${docStatusBadge(membership.status)}<a class="text-link" href="${escapeHtml(membership.download_url)}" target="_blank" rel="noopener">دانلود</a></div>` : `<p class="hint">پیوستی بارگذاری نشده</p>`}
      </div>
      <div class="contract-file-slot"><div class="contract-file-slot-head"><h4>${escapeHtml(labels.settlement || "قرارداد استقرار")}</h4></div>
        ${settlement ? `<div class="contract-file-meta"><strong>${escapeHtml(settlement.original_name)}</strong>${docStatusBadge(settlement.status)}<a class="text-link" href="${escapeHtml(settlement.download_url)}" target="_blank" rel="noopener">دانلود</a></div>` : `<p class="hint">پیوستی بارگذاری نشده</p>`}
      </div>
    </div>`;

    const keepFiles = Boolean(membership && settlement);
    const formHtml = canSubmit ? `<form class="year-contract-form team-contract-package" data-team-contract-package data-year="${escapeHtml(year)}" data-keep-files="${keepFiles ? "1" : "0"}">
      <h4>${proposal?.status === "rejected" ? "ارسال اصلاحیه قرارداد" : "ارسال قرارداد سال " + escapeHtml(year)}</h4>
      ${proposal?.rejection_reason ? `<p class="hint reject-hint">دلیل رد قبلی: ${escapeHtml(proposal.rejection_reason)}</p>` : ""}
      <div class="crud-grid year-form-grid">
        <label><span>شروع قرارداد</span><input name="contract_start" type="text" required dir="ltr" class="ltr-input" placeholder="${escapeHtml(year)}/01/01" value="${escapeHtml(proposal?.contract_start || official?.contract_start || `${year}/01/01`)}" /></label>
        <label><span>پایان قرارداد</span><input name="contract_end" type="text" required dir="ltr" class="ltr-input" placeholder="${escapeHtml(year)}/12/29" value="${escapeHtml(proposal?.contract_end || official?.contract_end || `${year}/12/29`)}" /></label>
        <label><span>مبلغ کل قرارداد رسمی (ریال)</span><input name="formal_contract_amount" type="number" min="1" step="1" required value="${escapeHtml(proposal?.formal_contract_amount ?? official?.formal_contract_amount ?? "")}" /></label>
        <label class="wide"><span>توضیحات</span><textarea name="notes" rows="2">${escapeHtml(proposal?.notes || official?.notes || "")}</textarea></label>
        <label><span>${escapeHtml(labels.membership || "قرارداد عضویت")}${keepFiles ? " (در صورت نیاز جایگزین کنید)" : " (الزامی)"}</span><input name="membership_file" type="file" ${keepFiles ? "" : "required"} accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx" /></label>
        <label><span>${escapeHtml(labels.settlement || "قرارداد استقرار")}${keepFiles ? " (در صورت نیاز جایگزین کنید)" : " (الزامی)"}</span><input name="settlement_file" type="file" ${keepFiles ? "" : "required"} accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx" /></label>
      </div>
      <div class="year-panel-actions"><button class="button" type="submit">ارسال برای تأیید مرکز</button></div>
    </form>` : (registered
      ? `<p class="hint">این سال در سامانه ثبت شده و ارسال تکراری مجاز نیست.</p>`
      : proposal?.status === "pending"
        ? `<p class="hint">پیشنهاد در انتظار تأیید مرکز است.</p>`
        : "");

    return `<article class="year-panel">
      <div class="year-panel-head">
        <h3>سال ${escapeHtml(year)}${year === currentYear ? " (جاری)" : ""}</h3>
        ${status}
      </div>
      ${official ? `<div class="year-contract-readonly">
        <div><span>شروع</span><strong>${escapeHtml(formatPlain(official.contract_start))}</strong></div>
        <div><span>پایان</span><strong>${escapeHtml(formatPlain(official.contract_end))}</strong></div>
        <div><span>مبلغ</span><strong>${escapeHtml(formatMoney(official.formal_contract_amount || 0))}</strong></div>
      </div>` : (proposal ? `<div class="year-contract-readonly">
        <div><span>شروع پیشنهادی</span><strong>${escapeHtml(formatPlain(proposal.contract_start))}</strong></div>
        <div><span>پایان پیشنهادی</span><strong>${escapeHtml(formatPlain(proposal.contract_end))}</strong></div>
        <div><span>مبلغ پیشنهادی</span><strong>${escapeHtml(formatMoney(proposal.formal_contract_amount || 0))}</strong></div>
      </div>` : "")}
      ${filesHtml}
      ${formHtml}
    </article>`;
  };

  // Ensure current year always has a submit card even if empty.
  const hasCurrent = years.some((row) => String(row.fiscal_year) === currentYear);
  const cards = [...years];
  if (!hasCurrent && currentYear) {
    cards.unshift({
      fiscal_year: currentYear,
      files: { membership: null, settlement: null },
      proposal: null,
      official_contract: null,
      can_submit: true,
      is_registered: false,
      has_both_files: false,
    });
  }

  host.classList.add("is-ready");
  host.innerHTML = cards.length
    ? `<div class="year-workspace-panels">${cards.map(renderYearCard).join("")}</div>`
    : renderEmptyState("هنوز قراردادی ثبت نشده است.", { icon: "inbox" });

  host.querySelectorAll("[data-team-contract-package]").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const membership = form.membership_file?.files?.[0];
      const settlement = form.settlement_file?.files?.[0];
      const keepFiles = form.dataset.keepFiles === "1";
      if (!keepFiles && (!membership || !settlement)) {
        showToast("هر دو فایل عضویت و استقرار الزامی است.", "error");
        return;
      }
      if (!isValidJalaliDate(form.contract_start?.value) || !isValidJalaliDate(form.contract_end?.value)) {
        showToast("تاریخ شروع و پایان باید به صورت 1404/01/01 باشد.", "error");
        return;
      }
      if (Number(form.formal_contract_amount?.value || 0) <= 0) {
        showToast("مبلغ قرارداد باید بیشتر از صفر باشد.", "error");
        return;
      }
      const body = new FormData(form);
      body.set("fiscal_year", form.dataset.year || "");
      if (!membership) body.delete("membership_file");
      if (!settlement) body.delete("settlement_file");
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        await fetchJson("api.php?resource=contract-documents&action=submit-package", {
          method: "POST",
          headers: { "X-CSRF-Token": csrfToken },
          body,
        });
        showToast("قرارداد با هر دو پیوست برای تأیید ارسال شد.", "success");
        await loadTeamContractsSection();
      } catch (error) {
        showToast(error.message, "error");
      } finally {
        submit.disabled = false;
      }
    });
  });
};

const formatFileBytes = (bytes) => {
  const n = Number(bytes) || 0;
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
};

const fileManagerState = { folder: "" };

const loadFileManager = async (folder = fileManagerState.folder) => {
  const host = document.getElementById("fileManagerContent");
  if (!host) return;
  fileManagerState.folder = folder || "";
  if (!fileManagerState.folder) {
    const data = await fetchJson("api.php?resource=file-manager");
    const folders = data.folders || [];
    host.innerHTML = `
      <div class="file-manager-toolbar">
        <p class="hint">ریشه آپلود: <code dir="ltr">${escapeHtml(data.root || "data/uploads")}</code></p>
      </div>
      <div class="file-folder-grid">
        ${folders.length ? folders.map((folderItem) => `
          <button type="button" class="file-folder-card" data-open-folder="${escapeHtml(folderItem.name)}">
            <strong>${escapeHtml(folderItem.label || folderItem.name)}</strong>
            <span dir="ltr">${escapeHtml(folderItem.name)}</span>
            <em>${escapeHtml(String(folderItem.file_count || 0))} فایل · ${escapeHtml(formatFileBytes(folderItem.total_bytes))}</em>
          </button>
        `).join("") : `<div class="empty-state"><p class="empty-state-text">هنوز فایلی آپلود نشده است.</p></div>`}
      </div>`;
    host.querySelectorAll("[data-open-folder]").forEach((button) => {
      button.addEventListener("click", () => {
        loadFileManager(button.dataset.openFolder).catch((error) => showToast(error.message, "error"));
      });
    });
    bindFileManagerClearBroken();
    return;
  }

  const data = await fetchJson(`api.php?resource=file-manager&folder=${encodeURIComponent(fileManagerState.folder)}`);
  const files = data.files || [];
  host.innerHTML = `
    <div class="file-manager-toolbar">
      <button type="button" class="button ghost" data-file-back>بازگشت به پوشه‌ها</button>
      <div>
        <strong>${escapeHtml(data.label || data.folder)}</strong>
        <p class="hint" dir="ltr">${escapeHtml(data.folder)}</p>
      </div>
    </div>
    ${files.length ? `<div class="table-wrap"><table class="data-table file-manager-table">
      <thead><tr><th>پیش‌نمایش</th><th>نام فایل</th><th>حجم</th><th>ارجاع</th><th>عملیات</th></tr></thead>
      <tbody>
        ${files.map((file) => `
          <tr>
            <td>${file.is_image && file.preview_url
              ? `<img class="file-preview-thumb" src="${escapeHtml(file.preview_url)}" alt="" loading="lazy" />`
              : `<span class="file-preview-thumb file-preview-thumb--doc">فایل</span>`}</td>
            <td><code dir="ltr">${escapeHtml(file.name)}</code></td>
            <td>${escapeHtml(formatFileBytes(file.size_bytes))}</td>
            <td>${file.reference_count
              ? escapeHtml((file.references || []).map((ref) => ref.label).join("، "))
              : "<span class=\"hint\">بدون ارجاع</span>"}</td>
            <td class="row-actions">
              <a class="mini-button" href="${escapeHtml(file.download_url)}" target="_blank" rel="noopener">دانلود</a>
              ${file.preview_url ? `<a class="mini-button" href="${escapeHtml(file.preview_url)}" target="_blank" rel="noopener">نمایش</a>` : ""}
              ${canWrite ? `<button type="button" class="mini-button danger" data-delete-file="${escapeHtml(file.relative_path)}">حذف</button>` : ""}
            </td>
          </tr>
        `).join("")}
      </tbody>
    </table></div>` : `<div class="empty-state"><p class="empty-state-text">این پوشه خالی است.</p></div>`}`;

  host.querySelector("[data-file-back]")?.addEventListener("click", () => {
    loadFileManager("").catch((error) => showToast(error.message, "error"));
  });
  host.querySelectorAll("[data-delete-file]").forEach((button) => {
    button.addEventListener("click", async () => {
      if (!confirm("این فایل حذف شود؟ ارجاع‌های دیتابیس هم پاک می‌شوند و در صورت نیاز تصویر پیش‌فرض نمایش داده می‌شود.")) {
        return;
      }
      button.disabled = true;
      try {
        const result = await postJson("api.php?resource=file-manager&action=delete", { path: button.dataset.deleteFile });
        const cleared = Number(result?.result?.cleared_references || 0);
        showToast(cleared > 0 ? `فایل حذف شد و ${cleared} ارجاع پاک شد.` : "فایل حذف شد.", "success");
        await loadFileManager(fileManagerState.folder);
      } catch (error) {
        showToast(error.message, "error");
        button.disabled = false;
      }
    });
  });
  bindFileManagerClearBroken();
};

const bindFileManagerClearBroken = () => {
  const button = document.getElementById("fileManagerClearBroken");
  if (!button || button.dataset.bound === "1" || !canWrite) return;
  button.dataset.bound = "1";
  button.addEventListener("click", async () => {
    if (!confirm("ارجاع‌های دیتابیس که فایلشان روی دیسک نیست پاک شوند؟")) return;
    button.disabled = true;
    try {
      const result = await postJson("api.php?resource=file-manager&action=clear-broken", {});
      const cleared = Number(result?.result?.cleared || 0);
      showToast(cleared > 0 ? `${cleared} ارجاع شکسته پاک شد.` : "ارجاع شکسته‌ای پیدا نشد.", "success");
      await loadFileManager(fileManagerState.folder);
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      button.disabled = false;
    }
  });
};

const loadPerformanceSettingsForm = async () => {
  const form = document.getElementById("performanceSettingsForm");
  if (!form) return;
  const settings = await fetchJson("api.php?resource=performance-settings");
  form.performance_reports_enabled.value = settings.performance_reports_enabled ? "1" : "0";
  form.performance_h1_open_from.value = settings.performance_h1_open_from || "";
  form.performance_h1_open_until.value = settings.performance_h1_open_until || "";
  form.performance_h2_open_from.value = settings.performance_h2_open_from || "";
  form.performance_h2_open_until.value = settings.performance_h2_open_until || "";
  form.performance_report_guide.value = settings.performance_report_guide || "";
  if (!form.dataset.bound) {
    form.dataset.bound = "1";
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;
      try {
        await postJson("api.php?resource=performance-settings", payload);
        showToast("تنظیمات گزارش عملکرد ذخیره شد.", "success");
        await loadPerformanceReportsSection();
      } catch (error) {
        showToast(error.message, "error");
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  }
};

let performanceAdminFilter = "pending";

const renderPerformanceAdminTable = (rows) => {
  if (!rows.length) {
    return renderEmptyState("گزارشی در این فهرست نیست.", { icon: "inbox" });
  }
  return `<div class="table-wrap"><table class="data-table review-list-table">
    <thead><tr>
      <th>نهاد</th><th>سال</th><th>نیمه</th><th>فایل</th><th>وضعیت</th><th>تاریخ ارسال</th><th>دلیل رد</th>${canWrite ? "<th>عملیات</th>" : ""}
    </tr></thead>
    <tbody>${rows.map((row) => `<tr>
      <td>${escapeHtml(row.team_name || "—")}<div class="hint">${escapeHtml(row.entity_code || "")}</div></td>
      <td>${escapeHtml(row.fiscal_year || "—")}</td>
      <td>${escapeHtml(row.period_label || row.period || "—")}</td>
      <td><a class="text-link" href="${escapeHtml(row.download_url)}" target="_blank" rel="noopener">${escapeHtml(row.original_name || "دانلود")}</a></td>
      <td>${docStatusBadge(row.status)}</td>
      <td class="reject-hint">${escapeHtml(row.rejection_reason || "—")}</td>
      <td>${escapeHtml(formatDateTime(row.submitted_at))}</td>
      ${canWrite ? `<td>${row.status === "pending" ? `<div class="row-actions review-list-actions">
          <button class="mini-button primary" type="button" data-approve-report="${row.id}">تأیید</button>
          <button class="mini-button danger" type="button" data-reject-report="${row.id}">رد</button>
        </div>` : "—"}</td>` : ""}
    </tr>`).join("")}</tbody>
  </table></div>${canWrite ? "" : `<p class="hint">فقط مدیر ویرایشگر می‌تواند تأیید یا رد کند.</p>`}`;
};

const loadPerformanceReportsSection = async () => {
  const host = document.getElementById("performanceReportsContent");
  if (!host) return;

  if (panelMode === "admin") {
    const [listData, settings] = await Promise.all([
      fetchJson("api.php?resource=performance-reports&list=1"),
      fetchJson("api.php?resource=performance-settings"),
    ]);
    const allRows = listData.rows || [];
    const enabled = Boolean(settings.performance_reports_enabled);
    const filter = performanceAdminFilter || "pending";
    const filtered = filter === "all" ? allRows : allRows.filter((row) => String(row.status) === filter);
    host.classList.add("is-ready");
    host.innerHTML = `
      <p class="hint">وضعیت بخش برای نهادها: <strong>${enabled ? "فعال" : "غیرفعال"}</strong> — تنظیم از «تنظیمات گزارش عملکرد».</p>
      ${renderPerformanceAdminTable(filtered)}`;

    document.querySelectorAll("#performanceStatusTabs [data-perf-filter]").forEach((tab) => {
      const active = tab.dataset.perfFilter === filter;
      tab.classList.toggle("active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
      if (!tab.dataset.bound) {
        tab.dataset.bound = "1";
        tab.addEventListener("click", () => {
          performanceAdminFilter = tab.dataset.perfFilter || "pending";
          loadPerformanceReportsSection().catch((error) => showToast(error.message, "error"));
        });
      }
    });

    const lockPerfActions = (button, locked) => {
      button.closest("tr, .review-list-actions")?.querySelectorAll("button").forEach((btn) => {
        btn.disabled = locked;
      });
    };
    host.querySelectorAll("[data-approve-report]").forEach((button) => {
      button.addEventListener("click", async () => {
        lockPerfActions(button, true);
        try {
          await postJson("api.php?resource=performance-reports&action=approve", { id: Number(button.dataset.approveReport) });
          showToast("گزارش تأیید شد.", "success");
          await loadPerformanceReportsSection();
          await refreshAfterMutation("performance-reports");
        } catch (error) {
          showToast(error.message, "error");
          lockPerfActions(button, false);
        }
      });
    });
    host.querySelectorAll("[data-reject-report]").forEach((button) => {
      button.addEventListener("click", async () => {
        lockPerfActions(button, true);
        try {
          const reason = await askRejectReason({ required: true, title: "رد گزارش عملکرد" });
          await postJson("api.php?resource=performance-reports&action=reject", {
            id: Number(button.dataset.rejectReport),
            reason: String(reason).trim(),
          });
          showToast("گزارش رد شد.", "success");
          await loadPerformanceReportsSection();
          await refreshAfterMutation("performance-reports");
        } catch (error) {
          if (error.message !== "cancelled") showToast(error.message, "error");
          lockPerfActions(button, false);
        }
      });
    });
    return;
  }

  const data = await fetchJson("api.php?resource=performance-reports");
  if (!data.enabled) {
    host.classList.add("is-ready");
    host.innerHTML = renderEmptyState("بخش گزارش عملکرد فعلاً توسط مرکز غیرفعال است.", { icon: "inbox" });
    return;
  }
  const guide = String(data.settings?.performance_report_guide || "").trim();
  host.classList.add("is-ready");
  host.innerHTML = `
    ${guide ? `<p class="hint">${escapeHtml(guide)}</p>` : ""}
    <div class="table-wrap"><table class="data-table review-list-table">
      <thead><tr><th>سال</th><th>نیمه</th><th>بازه ارسال</th><th>وضعیت</th><th>تاریخ ارسال</th><th>فایل</th><th>اقدام</th></tr></thead>
      <tbody>${(data.periods || []).map((period) => {
        const report = period.report;
        const periodYear = String(period.fiscal_year || data.fiscal_year || "");
        const canSubmit = Boolean(period.can_submit) && (!report || report.status === "rejected");
        const windowConfigured = Boolean(period.window_configured)
          || (period.window?.open_from && period.window?.open_until);
        const windowText = windowConfigured
          ? `${period.window.open_from} تا ${period.window.open_until}`
          : "اعلام‌نشده";
        let actionCell = windowConfigured
          ? `<span class="hint">خارج از بازه ارسال</span>`
          : `<span class="hint">بازه ارسال هنوز اعلام نشده</span>`;
        if (canSubmit) {
          actionCell = `<label class="contract-file-upload">
              <span>${report ? "انتخاب فایل اصلاحی" : "انتخاب فایل"}</span>
              <input type="file" data-performance-upload data-period="${escapeHtml(period.period)}" data-year="${escapeHtml(periodYear)}" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx" />
            </label>`;
        } else if (report?.status === "pending") {
          actionCell = `<span class="hint">در انتظار تأیید مرکز</span>`;
        } else if (report?.status === "approved") {
          actionCell = `<span class="hint">تأیید‌شده</span>`;
        } else if (report?.status === "rejected" && !canSubmit) {
          actionCell = `<span class="hint reject-hint">رد‌شده — برای ارسال اصلاحیه با مرکز هماهنگ کنید</span>`;
        }
        return `<tr>
          <td>${escapeHtml(periodYear)}</td>
          <td>${escapeHtml(period.period_label)}</td>
          <td>${escapeHtml(windowText)}</td>
          <td>${report ? docStatusBadge(report.status) : `<span class="badge">ارسال‌نشده</span>`}</td>
          <td>${escapeHtml(formatDateTime(report?.submitted_at))}</td>
          <td>${report ? `<a class="text-link" href="${escapeHtml(report.download_url)}" target="_blank" rel="noopener">${escapeHtml(report.original_name)}</a>
            ${report.rejection_reason ? `<div class="hint reject-hint">${escapeHtml(report.rejection_reason)}</div>` : ""}` : "—"}</td>
          <td>${actionCell}</td>
        </tr>`;
      }).join("")}</tbody>
    </table></div>
    ${(data.history || []).length ? `<div class="queue-block" style="margin-top:1rem">
      <h3>سوابق گزارش‌های سال‌های قبل</h3>
      <div class="table-wrap"><table class="data-table review-list-table">
        <thead><tr><th>سال</th><th>نیمه</th><th>وضعیت</th><th>تاریخ ارسال</th><th>فایل</th></tr></thead>
        <tbody>${data.history.map((row) => `<tr>
          <td>${escapeHtml(row.fiscal_year || "—")}</td>
          <td>${escapeHtml(row.period_label || row.period || "—")}</td>
          <td>${docStatusBadge(row.status)}</td>
          <td>${escapeHtml(formatDateTime(row.submitted_at))}</td>
          <td><a class="text-link" href="${escapeHtml(row.download_url)}" target="_blank" rel="noopener">${escapeHtml(row.original_name || "دانلود")}</a>
            ${row.rejection_reason ? `<div class="hint reject-hint">${escapeHtml(row.rejection_reason)}</div>` : ""}</td>
        </tr>`).join("")}</tbody>
      </table></div>
    </div>` : ""}`;

  host.querySelectorAll("[data-performance-upload]").forEach((input) => {
    input.addEventListener("change", async () => {
      const file = input.files?.[0];
      if (!file) return;
      if (!window.confirm(`فایل «${file.name}» برای این نیمه ارسال شود؟`)) {
        input.value = "";
        return;
      }
      const body = new FormData();
      body.append("file", file);
      body.append("fiscal_year", String(input.dataset.year || ""));
      body.append("period", String(input.dataset.period || ""));
      input.disabled = true;
      try {
        await fetchJson("api.php?resource=performance-reports&action=submit", {
          method: "POST",
          headers: { "X-CSRF-Token": csrfToken },
          body,
        });
        showToast("گزارش ارسال شد.", "success");
        await loadPerformanceReportsSection();
      } catch (error) {
        showToast(error.message, "error");
      } finally {
        input.disabled = false;
        input.value = "";
      }
    });
  });
};

const loadTeamProfile = async () => {
  const host = document.getElementById("teamProfileContent");
  if (!host || !window.MECHINNO?.teamId) return;
  if (window.TeamYearWorkspace) {
    await window.TeamYearWorkspace.mountInline(host, window.MECHINNO.teamId);
    return;
  }
  const data = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(window.MECHINNO.teamId)}`);
  const team = data.team || {};
  host.innerHTML = `
    <div class="profile-summary team-profile-grid">
      <div class="profile-brand-cell"><span>تصویر نهاد</span><strong>${profileThumb(team.logo_url || "", team.name || "", "assets/brand/default-team.svg")}</strong></div>
      <div><span>نام نهاد</span><strong>${escapeHtml(team.name || "—")}</strong></div>
      <div><span>نوع</span><strong>${entityBadge(team.entity_type)}</strong></div>
      <div><span>کد نهاد</span><strong>${escapeHtml(team.entity_code || "—")}</strong></div>
      <div><span>مسئول</span><strong>${escapeHtml(team.leader || "—")}</strong></div>
      <div><span>تماس</span><strong>${escapeHtml(team.phone || "—")}</strong></div>
      <div><span>شروع قرارداد</span><strong>${escapeHtml(team.contract_start || "—")}</strong></div>
      <div><span>پایان قرارداد</span><strong>${escapeHtml(team.contract_end || "—")}</strong></div>
      <div><span>تاریخ عضویت</span><strong>${escapeHtml(team.joined_at || "—")}</strong></div>
      <div><span>مانده بدهی قرارداد</span><strong class="debt-value">${escapeHtml(formatMoney(data.summary?.debt_total || 0))}</strong></div>
      <div><span>پرداخت‌شده</span><strong>${escapeHtml(formatMoney(data.summary?.paid_total || 0))}</strong></div>
      ${Number(data.summary?.overpayment_total || 0) > 0
        ? `<div><span>پیش‌پرداخت (مازاد واریز)</span><strong>${escapeHtml(formatMoney(data.summary.overpayment_total))}</strong></div>` : ""}
    </div>
    ${team.warning ? `<p class="hint warning-text">اخطار: ${escapeHtml(team.warning)}</p>` : ""}
    ${team.notes ? `<p class="hint">${escapeHtml(team.notes)}</p>` : ""}
    ${profileSection("میزها و تاریخ تخصیص", data.desk_assignments || [], ["fiscal_year", "desk_number", "usage_type", "assignment_period", "notes"])}
    ${profileSection("اعضا", data.members || [], ["avatar_url", "full_name", "email", "phone", "national_id", "joined_at", "approval_status"])}
    ${profileSection("کمدهای تخصیص‌یافته", data.lockers || [], ["locker_number", "status", "delivered_at"])}
    ${profileSection("درخواست‌های کمد", data.locker_requests || [], ["submitted_at", "status", "locker_number", "notes"])}`;
  host.classList.add("is-ready");
};

const paymentStatusBadge = (status) => {
  const map = {
    approved: "badge-paid",
    pending: "badge-partial",
    rejected: "badge-debt",
  };
  const label = { approved: "تأیید‌شده", pending: "در انتظار تأیید", rejected: "رد‌شده" }[status] || status || "—";
  return `<span class="badge ${map[status] || ""}">${escapeHtml(label)}</span>`;
};

const approvalStatusBadge = (status) => {
  const map = { approved: "badge-paid", pending: "badge-partial", rejected: "badge-debt" };
  const label = { approved: "تأیید‌شده", pending: "در انتظار", rejected: "رد‌شده" }[status] || status || "—";
  return `<span class="badge ${map[status] || ""}">${escapeHtml(label)}</span>`;
};

const loadDevKanban = async () => {
  const host = document.getElementById("devKanban");
  if (!host) return;
  const data = await fetchResource("api.php?resource=development_plans", { page: 1, perPage: 100 });
  const rows = data.rows || [];
  const columns = [
    { key: "open", label: "باز" },
    { key: "in_progress", label: "در حال اجرا" },
    { key: "done", label: "انجام‌شده" },
    { key: "cancelled", label: "لغو‌شده" },
  ];
  host.innerHTML = columns.map((column) => {
    const cards = rows.filter((row) => row.status === column.key);
    return `<div class="kanban-column">
      <div class="kanban-column-head"><strong>${escapeHtml(column.label)}</strong><span>${cards.length}</span></div>
      <div class="kanban-column-body">
        ${cards.length ? cards.map((row) => `
          <article class="kanban-card">
            <strong>${escapeHtml(row.title || "—")}</strong>
            <div class="kanban-meta"><span class="badge">${escapeHtml(devCategoryLabels[row.category] || row.category || "—")}</span>
              <span class="badge">${escapeHtml(devPriorityLabels[row.priority] || row.priority || "")}</span></div>
            ${row.depends_on_title ? `<div class="kanban-meta">پیش‌نیاز: ${escapeHtml(row.depends_on_title)}</div>` : ""}
            ${row.due_date ? `<div class="kanban-meta">موعد: ${escapeHtml(formatPlain(row.due_date))}</div>` : ""}
            ${row.estimated_cost ? `<div class="kanban-meta">هزینه: ${escapeHtml(formatMoney(row.estimated_cost))}</div>` : ""}
            ${row.estimated_revenue ? `<div class="kanban-meta">درآمد: ${escapeHtml(formatMoney(row.estimated_revenue))}</div>` : ""}
            ${row.related_section ? `<button type="button" class="text-link" data-nav-section="${escapeHtml(row.related_section)}">${escapeHtml(relatedSectionLabels[row.related_section] || row.related_section)}</button>` : ""}
          </article>`).join("") : column.key === "open"
          ? `<div class="empty">خالی</div><button class="button ghost kanban-add" type="button">+ افزودن برنامه</button>`
          : `<div class="empty">خالی</div>`}
      </div>
    </div>`;
  }).join("");
  host.querySelectorAll(".kanban-add").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelector('#development data-table[endpoint*="development_plans"] .add-button')?.click();
    });
  });
};

const loadPaymentSettings = async () => {
  const form = document.getElementById("paymentSettingsForm");
  if (!form || !canWrite) return;
  const data = await fetchJson("api.php?resource=center-settings");
  ["bank_name", "account_holder", "account_number", "card_number", "sheba", "payment_guide"].forEach((field) => {
    const input = form.elements.namedItem(field);
    if (input && "value" in input) input.value = data[field] ?? "";
  });
  if (!form.dataset.ready) {
    form.dataset.ready = "1";
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submitButton = form.querySelector('button[type="submit"]');
      submitButton.disabled = true;
      try {
        const payload = Object.fromEntries(new FormData(form).entries());
        await postJson("api.php?resource=center-settings", payload);
        showToast("اطلاعات واریز ذخیره شد.", "success");
      } catch (error) {
        showToast(error.message, "error");
      } finally {
        submitButton.disabled = false;
      }
    });
  }
};

const loadPaymentGuide = async () => {
  const host = document.getElementById("paymentGuideContent");
  if (!host) return;
  const data = await fetchJson("api.php?resource=center-settings");
  const rows = [
    ["بانک", data.bank_name],
    ["صاحب حساب", data.account_holder],
    ["شماره حساب", data.account_number],
    ["شماره کارت", data.card_number],
    ["شماره شبا", data.sheba],
  ].filter(([, value]) => value);
  const accounts = rows.length
    ? `<div class="payment-account-grid">${rows.map(([label, value]) => `
        <div class="payment-account-item">
          <span>${escapeHtml(label)}</span>
          <strong class="ltr-value" dir="ltr">${escapeHtml(formatBankValue(label, value))}</strong>
        </div>`).join("")}</div>`
    : `<div class="notice warn">اطلاعات حساب هنوز توسط مرکز ثبت نشده است.</div>`;
  host.innerHTML = `
    ${accounts}
    <div class="payment-guide-steps" dir="rtl">
      <h3>مراحل پرداخت شارژ</h3>
      <ol>
        <li>در بخش «اعلام واریز»، ماه‌های بدهی خود را انتخاب کنید.</li>
        <li>مبلغ دقیق نمایش‌داده‌شده را به حساب بالا واریز کنید — <strong>نه بیشتر</strong>.</li>
        <li>پس از واریز، تاریخ و شماره پیگیری را ثبت و «اعلام واریز انجام‌شده» را بزنید.</li>
        <li>پس از تأیید مرکز، مبلغ به ماه‌های انتخاب‌شده تخصیص می‌یابد.</li>
      </ol>
      ${data.payment_guide ? `<p class="payment-guide-note" dir="rtl">${escapeHtml(data.payment_guide)}</p>` : ""}
    </div>`;
};

const openDeskHistoryAssignModal = async (prefill = {}) => {
  const meta = await loadCrudMeta();
  const teamOptions = meta.resources?.desk_assignments?.fields?.team_id?.options || {};
  const deskOptions = meta.resources?.desk_assignments?.fields?.desk_id?.options || {};
  const usageOptions = meta.resources?.desk_assignments?.fields?.usage_type?.options || {};
  const monthOptionsHtml = monthNames.slice(1).map((name, index) => {
    const value = index + 1;
    return `<option value="${value}">${escapeHtml(name)}</option>`;
  }).join("");

  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = prefill.id
    ? `ویرایش تخصیص میز${prefill.lockDesk ? "" : ""}`
    : (prefill.lockDesk ? "تخصیص میز — سال جاری" : "ثبت تخصیص میز");
  const state = {
    teamId: prefill.team_id ? String(prefill.team_id) : "",
    contractId: "",
    fiscalYear: prefill.fiscal_year ? String(prefill.fiscal_year) : "",
    contractStartMonth: 1,
    contractEndMonth: 12,
    lockDesk: Boolean(prefill.lockDesk),
    monthsTouched: Boolean(prefill.id),
    desks: [{
      desk_id: prefill.desk_id ? String(prefill.desk_id) : "",
      usage_type: prefill.usage_type || "formal",
      assigned_from_month: validAssignmentMonth(prefill.assigned_from_month, "1"),
      assigned_until_month: validAssignmentMonth(prefill.assigned_until_month, "12"),
      charge_exempt: Number(prefill.charge_exempt) === 1 ? "1" : "0",
      rent_exempt: Number(prefill.rent_exempt) === 1 ? "1" : "0",
      notes: prefill.notes || "",
    }],
  };
  debugLog("desk-assign:modal-open", { prefill, state });

  const teamOptionsHtml = Object.entries(teamOptions).map(([value, label]) =>
    `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
  ).join("");
  const deskOptionsHtml = Object.entries(deskOptions).map(([value, label]) =>
    `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
  ).join("");
  const usageOptionsHtml = Object.entries(usageOptions).map(([value, label]) =>
    `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
  ).join("");

  const renderDeskRows = () => state.desks.map((desk, index) => `
    <div class="desk-assign-row" data-desk-row="${index}">
      <label><span>میز</span>
        <select data-field="desk_id" data-index="${index}" required ${state.lockDesk || prefill.id ? "disabled" : ""}>
          <option value="">انتخاب میز</option>
          ${deskOptionsHtml}
        </select>
      </label>
      <label><span>نوع</span>
        <select data-field="usage_type" data-index="${index}">
          ${usageOptionsHtml}
        </select>
      </label>
      <label><span>از ماه</span>
        <select data-field="assigned_from_month" data-index="${index}">${monthOptionsHtml}</select>
      </label>
      <label><span>تا ماه</span>
        <select data-field="assigned_until_month" data-index="${index}">${monthOptionsHtml}</select>
      </label>
      <label class="wide"><span>یادداشت</span>
        <input data-field="notes" data-index="${index}" type="text" value="${escapeHtml(desk.notes || "")}" />
      </label>
      <label class="desk-exempt-check"><span>معافیت</span>
        <span class="check-row-inline">
          <label><input type="checkbox" data-field="charge_exempt" data-index="${index}" value="1" ${Number(desk.charge_exempt) === 1 ? "checked" : ""} /> معاف شارژ</label>
          <label><input type="checkbox" data-field="rent_exempt" data-index="${index}" value="1" ${Number(desk.rent_exempt) === 1 ? "checked" : ""} /> معاف اجاره</label>
        </span>
      </label>
      ${state.desks.length > 1 ? `<button type="button" class="mini-button danger" data-remove-desk="${index}">حذف ردیف</button>` : ""}
    </div>`).join("");

  const render = () => {
    form.innerHTML = `
      <p class="hint">ابتدا نهاد و قرارداد سال را انتخاب کنید. بازه ماه‌ها پیش‌فرض از قرارداد پر می‌شود و قابل تغییر است.</p>
      <div class="crud-grid desk-assign-form">
        <label><span>نهاد *</span>
          <select id="deskAssignTeam" required ${prefill.id ? "disabled" : ""}>
            <option value="">انتخاب نهاد</option>
            ${teamOptionsHtml}
          </select>
        </label>
        <label><span>قرارداد (سال) *</span>
          <select id="deskAssignContract" required ${prefill.id ? "disabled" : ""}>
            <option value="">ابتدا نهاد را انتخاب کنید</option>
          </select>
        </label>
        <div class="wide desk-assign-contract-hint" id="deskAssignContractHint"></div>
      </div>
      <div class="desk-assign-rows">${renderDeskRows()}</div>
      ${!prefill.id ? `<button type="button" class="button ghost" id="deskAssignAddRow">+ میز دیگر</button>` : ""}
      <div class="modal-actions">
        <button class="button" type="submit">${prefill.id ? "ذخیره" : "ثبت تخصیص"}</button>
        <button class="button ghost" type="button" data-close-modal>انصراف</button>
      </div>`;

    const teamSelect = form.querySelector("#deskAssignTeam");
    const contractSelect = form.querySelector("#deskAssignContract");
    teamSelect.value = state.teamId;
    state.desks.forEach((desk, index) => {
      form.querySelectorAll(`[data-index="${index}"]`).forEach((input) => {
        const field = input.dataset.field;
        if (field && desk[field] !== undefined) input.value = desk[field];
      });
    });

    const loadContracts = async () => {
      const teamId = teamSelect.value;
      state.teamId = teamId;
      contractSelect.innerHTML = `<option value="">انتخاب قرارداد</option>`;
      form.querySelector("#deskAssignContractHint").textContent = "";
      if (!teamId) return;
      const { rows } = await fetchResource("api.php?resource=team_contracts", { page: 1, perPage: 100, teamId });
      if (!rows.length) {
        contractSelect.innerHTML = `<option value="">قراردادی ثبت نشده</option>`;
        return;
      }
      contractSelect.innerHTML = `<option value="">انتخاب قرارداد</option>${rows.map((row) =>
        `<option value="${escapeHtml(row.id)}" data-year="${escapeHtml(row.fiscal_year)}"
          data-start="${escapeHtml(row.contract_start || "")}" data-end="${escapeHtml(row.contract_end || "")}">
          سال ${escapeHtml(row.fiscal_year)} — ${escapeHtml(formatMonthRange(row.contract_start, row.contract_end))}
        </option>`
      ).join("")}`;
      if (state.contractId) contractSelect.value = state.contractId;
      else if (state.fiscalYear) {
        const match = [...contractSelect.options].find((opt) => opt.dataset.year === state.fiscalYear);
        if (match) contractSelect.value = match.value;
      }
      applyContractDefaults();
    };

    const syncDeskInputsFromState = () => {
      state.desks.forEach((desk, index) => {
        form.querySelectorAll(`[data-index="${index}"]`).forEach((input) => {
          const field = input.dataset.field;
          if (!field || desk[field] === undefined) return;
          if (input.type === "checkbox") {
            input.checked = String(desk[field]) === "1";
            return;
          }
          input.value = desk[field];
        });
      });
    };

    const syncDeskStateFromForm = () => {
      form.querySelectorAll("[data-field][data-index]").forEach((input) => {
        const index = Number(input.dataset.index);
        const field = input.dataset.field;
        if (!state.desks[index] || !field) return;
        if (input.type === "checkbox") {
          state.desks[index][field] = input.checked ? "1" : "0";
          return;
        }
        state.desks[index][field] = input.value;
      });
    };

    const readDeskMonthsFromForm = (index) => {
      const fromEl = form.querySelector(`[data-field="assigned_from_month"][data-index="${index}"]`);
      const untilEl = form.querySelector(`[data-field="assigned_until_month"][data-index="${index}"]`);
      const assigned_from_month = validAssignmentMonth(fromEl?.value, "");
      const assigned_until_month = validAssignmentMonth(untilEl?.value, "");
      if (!assigned_from_month || !assigned_until_month) {
        throw new Error("ماه شروع و پایان تخصیص را انتخاب کنید.");
      }
      if (Number(assigned_until_month) < Number(assigned_from_month)) {
        throw new Error("ماه پایان نمی‌تواند قبل از ماه شروع باشد.");
      }
      return { assigned_from_month, assigned_until_month };
    };

    const syncContractSelection = () => {
      const option = contractSelect.selectedOptions[0];
      if (!option || !option.dataset.year) return;
      state.contractId = contractSelect.value;
      state.fiscalYear = option.dataset.year;
      state.contractStartMonth = Number(monthIndexFromDate(option.dataset.start)) || 1;
      state.contractEndMonth = Number(monthIndexFromDate(option.dataset.end)) || 12;
      form.querySelector("#deskAssignContractHint").textContent =
        `قرارداد: ${formatMonthRange(option.dataset.start, option.dataset.end)} — می‌توانید بازه تخصیص را تغییر دهید.`;
    };

    const applyContractDefaults = () => {
      syncContractSelection();
      if (prefill.id || state.monthsTouched) return;
      state.desks = state.desks.map((desk) => ({
        ...desk,
        assigned_from_month: String(state.contractStartMonth),
        assigned_until_month: String(state.contractEndMonth),
      }));
      form.querySelector(".desk-assign-rows").innerHTML = renderDeskRows();
      bindDeskRowInputs();
      syncDeskInputsFromState();
    };

    const bindDeskRowInputs = () => {
      form.querySelectorAll("[data-field]").forEach((input) => {
        input.addEventListener("change", () => {
          const index = Number(input.dataset.index);
          const field = input.dataset.field;
          if (!state.desks[index] || !field) return;
          if (input.type === "checkbox") {
            state.desks[index][field] = input.checked ? "1" : "0";
          } else {
            state.desks[index][field] = input.value;
          }
          if (field === "assigned_from_month" || field === "assigned_until_month") {
            state.monthsTouched = true;
          }
        });
      });
      form.querySelectorAll("[data-remove-desk]").forEach((button) => {
        button.addEventListener("click", () => {
          state.desks.splice(Number(button.dataset.removeDesk), 1);
          form.querySelector(".desk-assign-rows").innerHTML = renderDeskRows();
          bindDeskRowInputs();
          syncDeskInputsFromState();
        });
      });
    };

    teamSelect.addEventListener("change", () => loadContracts().catch((error) => showToast(error.message, "error")));
    contractSelect.addEventListener("change", applyContractDefaults);
    form.querySelector("#deskAssignAddRow")?.addEventListener("click", () => {
      state.desks.push({
        desk_id: "",
        usage_type: "formal",
        assigned_from_month: String(state.contractStartMonth || 1),
        assigned_until_month: String(state.contractEndMonth || 12),
        charge_exempt: "0",
        rent_exempt: "0",
        notes: "",
      });
      form.querySelector(".desk-assign-rows").innerHTML = renderDeskRows();
      bindDeskRowInputs();
      syncDeskInputsFromState();
    });
    form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
    bindDeskRowInputs();
    if (state.teamId) loadContracts().catch((error) => showToast(error.message, "error"));

    form.onsubmit = async (event) => {
      event.preventDefault();
      if (!state.teamId || !state.fiscalYear) {
        showToast("نهاد و قرارداد سال را انتخاب کنید.", "error");
        return;
      }
      const submitButton = form.querySelector('button[type="submit"]');
      submitButton.disabled = true;
      try {
        syncDeskStateFromForm();
        const desk = state.desks[0];
        const months = readDeskMonthsFromForm(0);
        const payload = prefill.id
          ? {
            id: prefill.id,
            team_id: state.teamId,
            desk_id: desk.desk_id,
            usage_type: desk.usage_type,
            fiscal_year: state.fiscalYear,
            assigned_from_month: months.assigned_from_month,
            assigned_until_month: months.assigned_until_month,
            charge_exempt: desk.charge_exempt || "0",
            rent_exempt: desk.rent_exempt || "0",
            notes: desk.notes || "",
          }
          : null;
        if (prefill.id) {
          debugLog("desk-assign:update", payload);
          const result = await postJson("api.php?resource=desk-assignments&action=update", payload);
          debugLog("desk-assign:update:ok", result?.record);
        } else {
          for (let index = 0; index < state.desks.length; index += 1) {
            const deskRow = state.desks[index];
            if (!deskRow.desk_id) continue;
            const rowMonths = readDeskMonthsFromForm(index);
            const createPayload = {
              team_id: state.teamId,
              desk_id: deskRow.desk_id,
              usage_type: deskRow.usage_type,
              fiscal_year: state.fiscalYear,
              assigned_from_month: rowMonths.assigned_from_month,
              assigned_until_month: rowMonths.assigned_until_month,
              charge_exempt: deskRow.charge_exempt || "0",
              rent_exempt: deskRow.rent_exempt || "0",
              notes: deskRow.notes || "",
            };
            debugLog("desk-assign:create", createPayload);
            const result = await postJson("api.php?resource=desk-assignments&action=create", createPayload);
            debugLog("desk-assign:create:ok", result?.record);
          }
        }
        closeModal();
        await reloadDeskTables();
        await refreshAfterMutation("desk-history");
        await refreshAfterMutation("desks");
        showToast("تخصیص میز ذخیره شد.", "success");
      } catch (error) {
        console.error("[mechinno:desk-assign:save-error]", error);
        showToast(error.message, "error");
      } finally {
        submitButton.disabled = false;
      }
    };
  };

  render();
  modal.hidden = false;
  trapFocus(modal);
};

const initDeskHistoryFilters = async () => {
  const bar = document.getElementById("deskHistoryFilters");
  const table = document.getElementById("deskAssignmentsTable");
  if (!bar || !table) return;
  const meta = await loadCrudMeta();
  const teamOptions = meta.resources?.desk_assignments?.fields?.team_id?.options
    || meta.resources?.members?.fields?.team_id?.options
    || {};
  const teamEntries = Object.entries(teamOptions);
  let years = [];
  try {
    years = (await fetchJson("api.php?resource=charge-fiscal-years")).years || [];
  } catch (error) {
    years = [window.MECHINNO?.fiscalYear || "1405"];
  }
  years = [...new Set(years.filter(Boolean))].sort((a, b) => Number(b) - Number(a));

  const applyFilters = () => {
    const teamId = bar.querySelector('[data-filter="teamId"]')?.value || "";
    const fiscalYear = bar.querySelector('[data-filter="fiscalYear"]')?.value || "";
    const status = bar.querySelector('[data-filter="status"]')?.value || "";
    table.memberTeamFilter = teamId;
    table.fiscalYearFilter = fiscalYear;
    table.assignmentStatusFilter = status;
    table.setAttribute("data-member-team", teamId);
    table.setAttribute("data-fiscal-year", fiscalYear);
    table.setAttribute("data-assignment-status", status);
    table.page = 1;
    table.load?.();
  };

  if (!bar.dataset.ready) {
    bar.dataset.ready = "1";
    bar.className = "filter-bar desk-history-filter-bar";
    bar.innerHTML = `
      <label>نهاد
        <select data-filter="teamId"><option value="">همه نهادها</option></select>
      </label>
      <label>سال مالی
        <select data-filter="fiscalYear"><option value="">همه سال‌ها</option></select>
      </label>
      <label>وضعیت
        <select data-filter="status">
          <option value="">همه</option>
          <option value="active">جاری</option>
          <option value="expired">منقضی</option>
        </select>
      </label>
      <button type="button" class="button ghost" data-filter-reset>پاک کردن فیلترها</button>`;
    const teamSelect = bar.querySelector('[data-filter="teamId"]');
    teamSelect.innerHTML = `<option value="">همه نهادها</option>${teamEntries.map(([value, label]) =>
      `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
    ).join("")}`;
    const yearSelect = bar.querySelector('[data-filter="fiscalYear"]');
    yearSelect.innerHTML = `<option value="">همه سال‌ها</option>${years.map((year) =>
      `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`
    ).join("")}`;
    bar.querySelectorAll("select").forEach((select) => select.addEventListener("change", applyFilters));
    bar.querySelector("[data-filter-reset]")?.addEventListener("click", () => {
      bar.querySelector('[data-filter="teamId"]').value = "";
      bar.querySelector('[data-filter="fiscalYear"]').value = "";
      bar.querySelector('[data-filter="status"]').value = "";
      applyFilters();
    });
  }
  table.load?.();
};

const loadDevProgramSummary = async () => {
  const host = document.getElementById("devProgramSummary");
  if (!host) return;
  const data = await fetchResource("api.php?resource=development_plans", { page: 1, perPage: 100 });
  const rows = data.rows || [];
  const counts = { idea: 0, action: 0, planned: 0, open: 0, in_progress: 0, done: 0 };
  rows.forEach((row) => {
    if (counts[row.category] !== undefined) counts[row.category] += 1;
    if (counts[row.status] !== undefined) counts[row.status] += 1;
  });
  host.innerHTML = `
    <div class="dev-summary-grid">
      <div class="month-stat"><span>ایده</span><strong>${counts.idea}</strong></div>
      <div class="month-stat"><span>اقدام</span><strong>${counts.action}</strong></div>
      <div class="month-stat"><span>برنامه‌ریزی‌شده</span><strong>${counts.planned}</strong></div>
      <div class="month-stat"><span>در حال اجرا</span><strong>${counts.in_progress}</strong></div>
      <div class="month-stat"><span>انجام‌شده</span><strong>${counts.done}</strong></div>
    </div>`;
};

const wantsAccessLabel = (value) =>
  value === 1 || value === "1" || value === true || value === "true" ? "بله — نیاز به تردد" : "خیر";

const accessStatusLabel = (row = {}) => {
  const code = String(row.access_code ?? "").trim();
  if (code) return "دارد";
  if (wantsAccessLabel(row.wants_access) === "بله — نیاز به تردد") return "در انتظار ثبت کد";
  return "ندارد";
};

const formatBankValue = (label, value) => {
  if (!value) return "";
  if (label === "شماره کارت") {
    const digits = normalizeDigits(value).replace(/\D/g, "");
    const grouped = digits.replace(/(.{4})/g, "$1 ").trim();
    return grouped || String(value);
  }
  return String(value);
};

const loadDeskGrid = async () => {
  const isTeamMap = panelMode === "team";
  let desks = [];
  try {
    desks = (await fetchJson("api.php?resource=desks-map")).rows || [];
  } catch (error) {
    if (isTeamMap) {
      const container = document.getElementById("deskGrid");
      if (container) container.innerHTML = renderEmptyState("نقشه میزها در دسترس نیست.", { icon: "error" });
      return;
    }
    desks = (await fetchResource("api.php?resource=desks", { page: 1, perPage: 100 })).rows;
  }
  const container = document.getElementById("deskGrid");
  if (!container) return;
  if (!desks.length) {
    container.innerHTML = renderEmptyState("نقشه میزها بارگذاری نشد.", { icon: "error" });
    return;
  }
  const rows = { 1: [], 2: [], 3: [] };
  desks.forEach((desk) => {
    const rowIndex = Number(desk.row_index) || 1;
    if (!rows[rowIndex]) rows[rowIndex] = [];
    rows[rowIndex].push(desk);
  });
  container.innerHTML = [1, 2, 3].map((rowIndex) => `
    <div class="desk-row-block">
      <div class="desk-row-label">ردیف ${rowIndex}</div>
      <div class="desk-row">
        ${(rows[rowIndex] || []).sort((a, b) => a.col_index - b.col_index).map((desk) => {
          const foreign = Boolean(desk.foreign_occupied);
          const occupied = Boolean(desk.team_id) || foreign;
          const isOwn = Boolean(desk.is_own);
          const neutral = Boolean(desk.privacy_neutral) || (isTeamMap && !isOwn);
          const highlighted = highlightDesk === Number(desk.number) || (isTeamMap && isOwn);
          const tileClass = isTeamMap
            ? (isOwn ? "occupied own-desk" : "desk-neutral")
            : (occupied ? "occupied" : "free");
          let meta = `<span class="desk-meta">بدون نهاد</span>`;
          let status = occupied ? "اشغال" : "آزاد";
          let badge = escapeHtml(usageLabels[desk.usage_type] || desk.usage_type || "—");
          if (isTeamMap) {
            if (isOwn) {
              meta = `<span class="desk-meta">میز شما</span>`;
              status = "موقعیت شما";
              badge = escapeHtml(usageLabels[desk.usage_type] || "میز نهاد");
            } else {
              meta = `<span class="desk-meta"> </span>`;
              status = "";
              badge = "";
            }
          } else if (occupied && desk.team_id) {
            const statusBadgeHtml = desk.team_is_active !== undefined && desk.team_is_active !== null
              ? ` ${teamActiveBadge(desk.team_is_active)}` : "";
            meta = `<span class="desk-meta"><span role="button" tabindex="0" class="text-link-inline" data-team-id="${escapeHtml(desk.team_id)}">${escapeHtml(desk.team_name || "نهاد")}</span>${statusBadgeHtml}</span>`;
          }
          return `<button type="button" class="desk-tile ${tileClass} ${highlighted ? "highlighted" : ""} ${neutral ? "is-neutral" : ""}"
            ${isTeamMap
              ? (isOwn ? `data-highlight-desk="${desk.number}"` : "disabled")
              : (canWrite ? `data-desk-number="${desk.number}"` : `data-nav-section="desks" data-highlight-desk="${desk.number}"`)}>
            <span class="desk-num">${desk.number}</span>
            ${status ? `<span class="desk-status">${status}</span>` : `<span class="desk-status desk-status--blank">&nbsp;</span>`}
            ${meta}
            ${badge ? `<span class="desk-badge">${badge}</span>` : `<span class="desk-badge desk-badge--blank">&nbsp;</span>`}
          </button>`;
        }).join("")}
      </div>
    </div>`).join("");

  if (!isTeamMap) {
    container.querySelectorAll("[data-team-id]").forEach((el) => {
      const open = (event) => {
        event.stopPropagation();
        openTeamProfile(Number(el.dataset.teamId)).catch((error) => showToast(error.message, "error"));
      };
      el.addEventListener("click", open);
      el.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          open(event);
        }
      });
    });
  }
};

const collageCellClass = (status) => {
  if (status === "پرداخت‌شده") return "cell-paid";
  if (status === "ناقص") return "cell-partial";
  if (status === "بدهکار به مرکز") return "cell-debt";
  if (status === "خارج از قرارداد") return "cell-outside";
  return "cell-empty";
};

const collageCellMeta = (cell, row, year, months) => {
  const cls = collageCellClass(cell.status);
  const depositClickable = canWrite && (cell.status === "بدهکار به مرکز" || cell.status === "ناقص");
  const chargeEditable = canWrite && cell.status !== "خارج از قرارداد" && cell.status !== "—";
  const monthName = months.find((m) => m.index === cell.month_index)?.name || "";
  const showRent = row.team?.has_informal_desk && Number(cell.rent_amount || 0) > 0;
  return {
    cls,
    depositClickable,
    chargeEditable,
    showRent,
    monthName,
    dataAttrs: (depositClickable || chargeEditable)
      ? `data-team-id="${row.team.id}" data-team-name="${escapeHtml(row.team.name)}"
         data-fiscal-year="${escapeHtml(year)}" data-month-index="${cell.month_index}"
         data-month-name="${escapeHtml(monthName)}"
         data-amount-due="${cell.amount_due}" data-amount-paid="${cell.amount_paid}"
         data-charge-amount="${cell.charge_amount}" data-rent-amount="${cell.rent_amount}"
         data-note="${escapeHtml(cell.note || "")}"
         data-deposit="${depositClickable ? "1" : "0"}" data-charge-edit="${chargeEditable ? "1" : "0"}"
         data-informal-desk="${row.team?.has_informal_desk ? "1" : "0"}"`
      : "",
  };
};

const collageCellMarkup = (tag, meta, innerHtml, extraClass = "") => {
  const interactive = meta.depositClickable || meta.chargeEditable;
  return `<${tag} class="${extraClass}${meta.cls}${interactive ? " cell-clickable" : ""}"${interactive ? ' role="button" tabindex="0"' : ""} ${meta.dataAttrs}>${innerHtml}</${tag}>`;
};

const bindCollageCells = (container) => {
  container.querySelectorAll(".cell-clickable").forEach((cell) => {
    const handler = () => {
      if (cell.dataset.deposit === "1") {
        openDepositModal({
          teamId: Number(cell.dataset.teamId),
          teamName: cell.dataset.teamName,
          fiscalYear: cell.dataset.fiscalYear,
          monthIndex: Number(cell.dataset.monthIndex),
          monthName: cell.dataset.monthName,
          amountDue: Number(cell.dataset.amountDue),
          amountPaid: Number(cell.dataset.amountPaid),
        });
        return;
      }
      if (cell.dataset.chargeEdit === "1") {
        openChargeModal({
          teamId: Number(cell.dataset.teamId),
          teamName: cell.dataset.teamName,
          fiscalYear: cell.dataset.fiscalYear,
          monthIndex: Number(cell.dataset.monthIndex),
          monthName: cell.dataset.monthName,
          chargeAmount: Number(cell.dataset.chargeAmount),
          rentAmount: Number(cell.dataset.rentAmount),
          amount: Number(cell.dataset.amountDue),
          note: cell.dataset.note || "",
          hasInformalDesk: cell.dataset.informalDesk === "1",
        });
      }
    };
    cell.addEventListener("click", handler);
    cell.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handler();
      }
    });
  });
};

const loadTeamChargeRates = async () => {
  const host = document.getElementById("teamChargeRates");
  if (!host || panelMode !== "team" || !window.MECHINNO?.teamId) return;
  try {
    const profile = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(window.MECHINNO.teamId)}`);
    const rates = profile.current_year_rates || {};
    const year = rates.fiscal_year || window.MECHINNO?.fiscalYear || "—";
    const contract = (profile.contracts || []).find((row) => String(row.fiscal_year) === String(year));
    const chargeRate = contract?.charge_rate_override != null && contract?.charge_rate_override !== ""
      ? Number(contract.charge_rate_override)
      : Number(rates.charge_rate || 0);
    const rentRate = contract?.informal_rent_rate_override != null && contract?.informal_rent_rate_override !== ""
      ? Number(contract.informal_rent_rate_override)
      : Number(rates.informal_rent_rate || 0);
    const billing = profile.billing_summaries?.[year];
    host.innerHTML = `
      <div class="team-charge-rates-grid">
        <div class="month-stat"><span>سال</span><strong>${escapeHtml(year)}</strong></div>
        <div class="month-stat"><span>شارژ هر میز (ماهانه)</span><strong>${escapeHtml(formatMoney(chargeRate))}${contract?.charge_rate_override ? " <small class='hint'>(اختصاصی)</small>" : ""}</strong></div>
        ${profile.has_informal_desk
          ? `<div class="month-stat"><span>اجاره موقت هر میز</span><strong>${escapeHtml(formatMoney(rentRate))}${contract?.informal_rent_rate_override ? " <small class='hint'>(اختصاصی)</small>" : ""}</strong></div>`
          : ""}
      </div>
      ${billing?.has_billing_adjustments ? `<div class="team-billing-badges team-billing-badges--compact">${teamBillingBadges(billing, { compact: true })}</div>` : ""}
      <p class="hint">نرخ‌های سال‌های گذشته در کلاژ همان سال نمایش داده می‌شود. بدهی هر ماه فقط برای ماه‌هایی که میز فعال دارید محاسبه می‌شود.</p>`;
  } catch (error) {
    host.innerHTML = renderEmptyState("نرخ سال جاری در دسترس نیست.", { icon: "chart" });
  }
};

const loadChargesCollage = async () => {
  const yearSelect = document.getElementById("chargesYear");
  if (!yearSelect) return;
  if (!yearSelect.dataset.ready) {
    yearSelect.dataset.ready = "1";
    yearSelect.addEventListener("change", () => loadChargesCollage().catch((error) => showToast(error.message, "error")));
  }
  let years = [];
  try {
    const yearData = await fetchJson("api.php?resource=charge-fiscal-years");
    years = yearData.years || [];
  } catch (error) {
    years = [window.MECHINNO?.fiscalYear || "1404"];
  }
  if (panelMode === "team" && window.MECHINNO?.teamId) {
    try {
      const profile = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(window.MECHINNO.teamId)}`);
      const team = profile.team || {};
      (profile.contracts || []).forEach((c) => years.push(String(c.fiscal_year || "")));
    } catch (error) {
      // ignore profile year enrichment errors
    }
  }
  years = [...new Set(years.filter(Boolean))].sort((a, b) => Number(b) - Number(a));
  const current = yearSelect.value || window.MECHINNO?.fiscalYear || years[0] || "1404";
  yearSelect.innerHTML = years.map((y) => `<option value="${escapeHtml(y)}">${escapeHtml(y)}</option>`).join("");
  yearSelect.value = years.includes(current) ? current : years[0];
  const year = yearSelect.value || window.MECHINNO?.fiscalYear || "1404";
  const data = await fetchJson(`api.php?resource=charges-matrix&fiscal_year=${encodeURIComponent(year)}`);
  const container = document.getElementById("chargesCollage");
  if (!data.rows?.length) {
    const emptyMessage = panelMode === "team"
      ? "برای این سال قرارداد، میز یا شارژ ثبت‌شده‌ای ندارید."
      : "برای این سال نهادی با قرارداد و میز فعال نیست — قرارداد سالانه و میز را بررسی کنید.";
    container.innerHTML = renderEmptyState(emptyMessage, { icon: "search" });
    return;
  }

  if (isMobile() && panelMode === "team") {
    const row = data.rows[0];
    container.innerHTML = `<div class="collage-mobile-list">${row.cells.map((cell) => {
      const month = data.months.find((m) => m.index === cell.month_index);
      const meta = collageCellMeta(cell, row, year, data.months);
      const amountHtml = cell.amount_due > 0
        ? `<div class="collage-mobile-meta">${escapeHtml(formatMoney(cell.amount_paid))} از ${escapeHtml(formatMoney(cell.amount_due))}</div>`
        : `<div class="collage-mobile-meta">—</div>`;
      const inner = `
        <div class="collage-mobile-head">
          <strong>${escapeHtml(month?.name || "—")}</strong>
          <span class="badge">${escapeHtml(chargeStatusLabel(cell.status))}</span>
        </div>
        ${amountHtml}
        ${meta.showRent ? `<small class="hint">شارژ: ${escapeHtml(formatMoney(cell.charge_amount))} · اجاره: ${escapeHtml(formatMoney(cell.rent_amount))}</small>` : `<small class="hint">شارژ: ${escapeHtml(formatMoney(cell.charge_amount))}</small>`}`;
      return collageCellMarkup("article", meta, inner, "collage-mobile-card ");
    }).join("")}</div>`;
    bindCollageCells(container);
    return;
  }

  const head = panelMode === "team"
    ? `<tr>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`
    : `<tr><th class="team-col">نهاد</th>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`;
  const body = data.rows.map((row) => `
    <tr${row.team?.has_informal_desk ? ' data-informal="1"' : ""}>
      ${panelMode === "team" ? "" : `<td class="team-col">
        <button type="button" class="text-link" data-team-id="${escapeHtml(row.team.id)}">${escapeHtml(row.team.name)}</button>
        <br>${entityBadge(row.team.entity_type)} ${teamActiveBadge(row.team.is_active)}
        ${row.team.billing?.has_billing_adjustments ? `<div class="team-billing-badges team-billing-badges--compact">${teamBillingBadges(row.team.billing, { compact: true })}</div>` : ""}
      </td>`}
      ${row.cells.map((cell) => {
        const meta = collageCellMeta(cell, row, year, data.months);
        const inner = cell.amount_due > 0
          ? `<div>${escapeHtml(formatMoney(cell.amount_paid))}</div><small>از ${escapeHtml(formatMoney(cell.amount_due))}</small>`
          : "—";
        const title = meta.showRent
          ? `title="شارژ: ${formatMoney(cell.charge_amount)} | اجاره: ${formatMoney(cell.rent_amount)}"`
          : `title="شارژ: ${formatMoney(cell.charge_amount)}"`;
        return collageCellMarkup("td", meta, inner, "").replace("<td ", `<td ${title} `);
      }).join("")}
    </tr>`).join("");
  const scrollHint = isMobile() && panelMode === "admin"
    ? `<p class="collage-scroll-hint">برای دیدن همه ماه‌ها، جدول را به چپ و راست بکشید.</p>`
    : "";
  container.innerHTML = `${scrollHint}<table class="collage-table"><thead>${head}</thead><tbody>${body}</tbody></table>`;
  bindCollageCells(container);
};

const profileSection = (title, rows, cols, cellRenderer = null) => `
  <div class="profile-table">
    <h3>${escapeHtml(title)}</h3>
    <table>
      <thead><tr>${cols.map((c) => `<th>${escapeHtml(labels[c] || c)}</th>`).join("")}</tr></thead>
      <tbody>${rows.length
        ? rows.map((row) => `<tr>${cols.map((c) => {
          if (cellRenderer) {
            const custom = cellRenderer(c, row);
            if (custom !== null) return `<td>${custom}</td>`;
          }
          const value = row[c];
          if (["amount", "charge_amount", "rent_amount", "formal_contract_amount", "charge_rate", "informal_rent_rate", "charge_rate_override", "informal_rent_rate_override"].includes(c)) {
            return `<td class="num">${escapeHtml(formatMoney(value))}</td>`;
          }
          if (c === "avatar_url") return `<td>${profileThumb(value || "", row.full_name || "")}</td>`;
          if (c === "logo_url") return `<td>${profileThumb(value || "", row.name || "", "assets/brand/default-team.svg")}</td>`;
          if (c === "usage_type") return `<td>${usageLabels[value] || value || "—"}</td>`;
          if (c === "wants_access") return `<td>${accessStatusLabel(row)}</td>`;
          if (c === "approval_status") return `<td>${approvalStatusBadge(value)}</td>`;
          if (c === "payment_status") return `<td>${paymentStatusBadge(value)}</td>`;
          if (c === "number") return `<td>${deskLink(value)}</td>`;
          if (plainColumns.has(c)) return `<td>${escapeHtml(formatPlain(value))}</td>`;
          return `<td>${escapeHtml(formatNumber(value ?? "—"))}</td>`;
        }).join("")}</tr>`).join("")
        : `<tr><td colspan="${cols.length}">داده‌ای موجود نیست.</td></tr>`}
      </tbody>
    </table>
  </div>`;

const openTeamProfile = async (teamId, options = {}) => {
  if (window.TeamYearWorkspace) {
    await window.TeamYearWorkspace.openModal(teamId, options);
    return;
  }
  const data = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = `پروفایل نهاد: ${data.team.name || "—"}`;
  const deskList = (data.desks || []).map((d) => d.number).join("، ") || "—";
  form.innerHTML = `
    <div class="profile-summary">
      <div class="profile-brand-cell"><span>تصویر نهاد</span><strong>${profileThumb(data.team.logo_url || "", data.team.name || "", "assets/brand/default-team.svg")}</strong></div>
      <div><span>نوع</span><strong>${entityBadge(data.team.entity_type)}</strong></div>
      <div><span>مسئول</span><strong>${escapeHtml(data.team.leader || "—")}</strong></div>
      <div><span>میزها</span><strong>${escapeHtml(deskList)}</strong></div>
      <div><span>جمع شارژ</span><strong>${escapeHtml(formatMoney(data.summary.charge_total || 0))}</strong></div>
      <div><span>دریافت از نهاد</span><strong>${escapeHtml(formatMoney(data.summary.paid_total || 0))}</strong></div>
      <div><span>مانده بدهی قرارداد</span><strong class="debt-value">${escapeHtml(formatMoney(data.summary.debt_total || 0))}</strong></div>
    </div>
    <div class="profile-actions">
      <a class="button ghost" href="profile-print.php?id=${encodeURIComponent(teamId)}" target="_blank" rel="noopener">چاپ پروفایل A4</a>
      ${canWrite ? `<button type="button" class="button" data-profile-action="add-member">افزودن عضو</button>
      <button type="button" class="button ghost" data-profile-action="deposit">ثبت دریافت شارژ</button>
      <button type="button" class="button ghost" data-profile-action="charges">مشاهده شارژ</button>
      <button type="button" class="button ghost" data-profile-action="desks">مدیریت میزها</button>` : `<button type="button" class="button ghost" data-profile-action="charges">مشاهده شارژ</button>
      <button type="button" class="button ghost" data-profile-action="desks">مشاهده میزها</button>`}
    </div>
    ${profileSection("قراردادهای سالانه", data.contracts || [], ["fiscal_year", "contract_start", "contract_end", "formal_contract_amount", "notes"])}
    ${profileSection("میزهای نهاد", data.desks, ["number", "usage_type", "notes"])}
    ${profileSection("تاریخچه تخصیص میز", data.desk_assignments || [], ["fiscal_year", "desk_number", "usage_type", "assigned_from", "assigned_until", "notes"])}
    ${profileSection("اعضا", data.members, ["avatar_url", "member_code", "full_name", "email", "phone", "national_id", "joined_at"])}
    ${profileSection("کمدها", data.lockers, ["locker_number", "status", "delivered_at", "key_number"], (column, row) => {
      if (column === "locker_number") return lockerLink(row.locker_number);
      if (column === "status") return lockerStatusBadge(row.status);
      return null;
    })}
    ${profileSection("شارژها", data.charges, ["fiscal_year", "month_name", "charge_amount", "rent_amount", "amount"])}
    ${profileSection("دریافت شارژ از نهاد", data.payments, ["tx_date", "fiscal_year", "month_name", "amount", "payment_status"])}
    <div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>`;

  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  form.querySelectorAll("[data-profile-action]").forEach((button) => {
    button.addEventListener("click", async () => {
      const action = button.dataset.profileAction;
      if (action === "add-member" && !canWrite) return;
      if (action === "deposit" && !canWrite) return;
      if (action === "add-member") {
        closeModal();
        activateSection("members");
        const meta = await loadCrudMeta();
        openRecordModal({
          resource: "members",
          definition: meta.resources.members,
          title: `افزودن عضو — ${data.team.name}`,
          record: { team_id: String(teamId) },
          onSaved: async () => {
            await refreshAfterMutation("members");
            showToast("عضو ثبت شد.", "success");
          },
        });
      } else if (action === "deposit") {
        const month = data.current_month || {};
        openDepositModal({
          teamId,
          teamName: data.team.name,
          fiscalYear: month.fiscal_year || window.MECHINNO?.fiscalYear || "1404",
          monthIndex: month.month_index || window.MECHINNO?.monthIndex || 1,
          monthName: month.month_name || "",
          amountDue: Number(month.charge_total || 0),
          amountPaid: Number(month.paid_total || 0),
        });
      } else if (action === "charges") {
        closeModal();
        activateSection("charges");
      } else if (action === "desks") {
        closeModal();
        activateSection("desks");
      }
    });
  });
  modal.hidden = false;
  trapFocus(modal);
};

const openChargeModal = async ({ teamId, teamName, fiscalYear, monthIndex, monthName, chargeAmount, rentAmount, amount, note = "", hasInformalDesk }) => {
  const meta = await loadCrudMeta();
  const definition = meta.resources.charges;
  const resolvedMonthName = monthName || monthNames[monthIndex] || "";
  const fields = { ...definition.fields };
  if (!hasInformalDesk) {
    delete fields.rent_amount;
  }
  openRecordModal({
    resource: "charges",
    definition: { ...definition, fields },
    title: `ثبت/ویرایش شارژ — ${teamName} — ${resolvedMonthName} ${fiscalYear}`,
    record: {
      team_id: String(teamId),
      fiscal_year: fiscalYear,
      month_index: String(monthIndex),
      charge_amount: chargeAmount || "",
      rent_amount: hasInformalDesk ? (rentAmount || "") : "0",
      amount: amount || "",
      note: note || "",
    },
    onSaved: async () => {
      await refreshAfterMutation("charges");
      await loadChargesCollage();
      showToast("شارژ ماه به‌روز شد.", "success");
    },
  });
};

const openPortalCredentialsModal = ({ username, password, password_set: passwordSet, message }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = "اطلاعات ورود نهاد";
  const passwordBlock = password
    ? `<label><span>رمز عبور</span><strong class="ltr-value" dir="ltr">${escapeHtml(password)}</strong></label>`
    : `<p class="hint warning-text">${escapeHtml(message || "رمز عبور در سیستم ذخیره نمی‌شود. برای دریافت رمز جدید از «بازنشانی رمز» استفاده کنید.")}</p>`;
  form.innerHTML = `
    <div class="portal-creds">
      <label><span>نام کاربری</span><strong class="ltr-value" dir="ltr">${escapeHtml(username || "—")}</strong></label>
      ${passwordBlock}
      ${passwordSet === false ? `<p class="hint">حساب ورود هنوز ساخته نشده یا رمز تنظیم نشده است.</p>` : ""}
    </div>
    <p class="hint warning-text">این اطلاعات محرمانه است — فقط در اختیار مسئول نهاد قرار دهید.</p>
    <div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>`;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  modal.hidden = false;
  trapFocus(modal);
};

const openPortalPasswordResultModal = ({ username, password }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = "رمز جدید پنل نهاد";
  form.innerHTML = `
    <div class="portal-creds">
      <label><span>نام کاربری</span><strong class="ltr-value" dir="ltr">${escapeHtml(username || "—")}</strong></label>
      <label><span>رمز عبور جدید</span><strong class="ltr-value" dir="ltr">${escapeHtml(password || "—")}</strong></label>
    </div>
    <p class="hint warning-text">این رمز فقط یک‌بار نمایش داده می‌شود. آن را یادداشت کنید و به مسئول نهاد بدهید.</p>
    <div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>`;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  modal.hidden = false;
  trapFocus(modal);
};

const loadTeamPaymentWizard = async () => {
  const host = document.getElementById("teamPaymentWizard");
  if (!host) return;
  const monthsData = await fetchJson("api.php?resource=team-payable-months");
  const months = monthsData.months || [];
  if (!months.length) {
    host.innerHTML = renderEmptyState("ماه بدهی باز ندارید.", { icon: "inbox" });
    return;
  }
  host.innerHTML = `
    <div class="payment-wizard">
      <p class="hint warning-text">مبلغ را دقیقاً مطابق عدد زیر واریز کنید. واریز بیشتر قابل ثبت نیست و مازاد از دست می‌رود.</p>
      <div class="payment-month-picker" id="paymentMonthPicker">
        ${months.map((m) => `
          <label class="payment-month-option">
            <input type="checkbox" name="pay_month" value="${escapeHtml(m.fiscal_year)}-${m.month_index}"
              data-year="${escapeHtml(m.fiscal_year)}" data-month="${m.month_index}" data-amount="${m.amount_remaining}" />
            <span>${escapeHtml(m.month_name)} ${escapeHtml(m.fiscal_year)}</span>
            <strong>${escapeHtml(formatMoney(m.amount_remaining))}</strong>
          </label>`).join("")}
      </div>
      <div class="payment-wizard-total payment-wizard-hero">
        <span>مبلغ دقیق واریز</span>
        <strong id="paymentWizardTotal" class="payment-hero-amount">۰</strong>
      </div>
      <form id="teamPaymentForm" class="crud-grid">
        <label><span>تاریخ واریز</span><input name="tx_date" type="text" required value="${escapeHtml(window.MECHINNO?.today || "")}" /></label>
        <label><span>شماره پیگیری</span><input name="payment_reference" type="text" dir="ltr" /></label>
        <label class="wide"><span>توضیح</span><textarea name="description" rows="2" required placeholder="واریز انجام شد"></textarea></label>
        <div class="wide form-actions">
          <button class="button" type="submit" id="teamPaymentSubmit" disabled>اعلام واریز انجام‌شده</button>
        </div>
      </form>
    </div>`;
  const totalEl = host.querySelector("#paymentWizardTotal");
  const submitBtn = host.querySelector("#teamPaymentSubmit");
  const updateTotal = () => {
    let total = 0;
    host.querySelectorAll('input[name="pay_month"]:checked').forEach((input) => {
      total += Number(input.dataset.amount || 0);
    });
    totalEl.textContent = formatMoney(total);
    submitBtn.disabled = total <= 0;
    submitBtn.dataset.amount = String(total);
  };
  host.querySelectorAll('input[name="pay_month"]').forEach((input) => input.addEventListener("change", updateTotal));
  updateTotal();
  host.querySelector("#teamPaymentForm").addEventListener("submit", async (event) => {
    event.preventDefault();
    const plan = [];
    host.querySelectorAll('input[name="pay_month"]:checked').forEach((input) => {
      plan.push({
        fiscal_year: input.dataset.year,
        month_index: Number(input.dataset.month),
        amount: Number(input.dataset.amount),
      });
    });
    if (!plan.length) {
      showToast("حداقل یک ماه را انتخاب کنید.", "error");
      return;
    }
    submitBtn.disabled = true;
    try {
      const formData = Object.fromEntries(new FormData(event.target).entries());
      await postJson("api.php?resource=transactions&action=create", {
        ...formData,
        payment_plan: plan,
        amount: plan.reduce((sum, item) => sum + item.amount, 0),
      });
      showToast("اعلام واریز ثبت شد.", "success");
      await loadTeamPaymentWizard();
      document.querySelector('data-table[endpoint*="transactions"]')?.load?.();
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      submitBtn.disabled = false;
      updateTotal();
    }
  });
};

const openDepositModal = async ({ teamId, teamName, fiscalYear, monthIndex, monthName, amountDue, amountPaid }) => {
  const meta = await loadCrudMeta();
  const definition = meta.resources.transactions;
  const remaining = Math.max(0, Number(amountDue) - Number(amountPaid));
  const resolvedMonthName = monthName || monthNames[monthIndex] || "";
  openRecordModal({
    resource: "transactions",
    definition,
    title: `ثبت مستقیم دریافت — ${teamName}`,
    record: {
      category: "واریز تیم",
      team_id: String(teamId),
      fiscal_year: fiscalYear,
      month_index: String(monthIndex),
      amount: remaining || amountDue,
      description: `دریافت شارژ ${resolvedMonthName} ${fiscalYear}`,
      tx_date: window.MECHINNO?.today || "",
      confirmed: "1",
    },
    onSaved: async () => {
      await refreshAfterMutation("transactions");
      await loadChargesCollage();
      showToast("ثبت مستقیم مدیر انجام شد.", "success");
    },
  });
};

const ensureModal = () => {
  let modal = document.getElementById("crudModal");
  if (modal) return modal;
  document.body.insertAdjacentHTML("beforeend", `
    <div id="crudModal" class="modal-backdrop" hidden>
      <section class="modal-card" role="dialog" aria-modal="true">
        <div class="modal-head">
          <h2 id="crudModalTitle"></h2>
          <button class="modal-close" type="button" aria-label="بستن">×</button>
        </div>
        <form id="crudForm" class="crud-form"></form>
      </section>
    </div>`);
  modal = document.getElementById("crudModal");
  modal.querySelector(".modal-close").addEventListener("click", closeModal);
  modal.addEventListener("click", (e) => { if (e.target === modal) closeModal(); });
  return modal;
};

const closeModal = () => {
  const modal = document.getElementById("crudModal");
  if (modal) {
    releaseFocusTrap(modal);
    modal.hidden = true;
  }
};

const focusTrapState = new WeakMap();

const trapFocus = (modal) => {
  const card = modal.querySelector(".modal-card");
  if (!card) return;
  const focusable = card.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  const handler = (event) => {
    if (event.key !== "Tab") return;
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };
  card.addEventListener("keydown", handler);
  focusTrapState.set(modal, handler);
  first.focus();
};

const releaseFocusTrap = (modal) => {
  const handler = focusTrapState.get(modal);
  if (!handler) return;
  modal.querySelector(".modal-card")?.removeEventListener("keydown", handler);
  focusTrapState.delete(modal);
};

const ltrFields = new Set([
  "access_code", "phone", "national_id", "portal_username", "portal_password",
  "account_number", "card_number", "sheba", "payment_reference", "entity_code", "member_code",
  "email", "id_certificate_number",
]);

const jalaliDateFieldNames = new Set([
  "joined_at", "birth_date", "contract_start", "contract_end", "tx_date", "assigned_from", "assigned_until",
  "submitted_at", "reviewed_at", "effective_from", "announced_at", "created_at", "updated_at",
  "sent_at",
]);

const isJalaliDateField = (name, meta) => {
  if (meta?.type === "date") return true;
  if (jalaliDateFieldNames.has(name)) return true;
  return /(?:^|_)(?:date|at)$/.test(name) || name.endsWith("_from") || name.endsWith("_until");
};

const isValidJalaliDate = (value) => /^\d{4}\/\d{2}\/\d{2}$/.test(normalizeDigits(value));

const isValidIranPhone = (value) => /^09\d{9}$/.test(normalizeDigits(value));

const isValidNationalId = (value) => /^\d{10}$/.test(normalizeDigits(value));

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());

const validateCrudForm = (form, definition) => {
  const payload = Object.fromEntries(new FormData(form).entries());
  for (const [name, meta] of Object.entries(definition.fields || {})) {
    const value = String(payload[name] ?? "").trim();
    if (meta.required && value === "" && meta.type !== "hidden") {
      throw new Error(`${meta.label || name} الزامی است.`);
    }
    if (value === "") continue;
    if (isJalaliDateField(name, meta) && !isValidJalaliDate(value)) {
      throw new Error(`${meta.label || name} باید به فرمت 1404/01/01 باشد.`);
    }
    if (name === "phone" && !isValidIranPhone(value)) {
      throw new Error("شماره موبایل باید ۱۱ رقم و با 09 شروع شود.");
    }
    if (name === "national_id" && !isValidNationalId(value)) {
      throw new Error("کد ملی باید ۱۰ رقم باشد.");
    }
    if (name === "email" && !isValidEmail(value)) {
      throw new Error("ایمیل معتبر نیست.");
    }
  }
};

const fieldInput = (name, meta, value) => {
  const type = meta.type || "text";
  const isReadonly = Boolean(meta.readonly || meta.auto);
  const required = meta.required && !isReadonly ? "required" : "";
  const placeholder = meta.placeholder ? `placeholder="${escapeHtml(meta.placeholder)}"` : "";
  const safeValue = value ?? "";
  const ltr = ltrFields.has(name) ? 'dir="ltr" class="ltr-input"' : "";
  const readonlyAttr = isReadonly ? "readonly" : "";

  if (type === "hidden") {
    return `<input type="hidden" name="${escapeHtml(name)}" value="${escapeHtml(safeValue)}" />`;
  }
  if (type === "textarea") {
    return `<textarea name="${escapeHtml(name)}" ${required} ${readonlyAttr} ${placeholder}>${escapeHtml(safeValue)}</textarea>`;
  }
  if (type === "select") {
    const options = meta.options || {};
    const entries = Array.isArray(options) ? options.map((o) => [o, o]) : Object.entries(options);
    const placeholder = meta.required
      ? ""
      : `<option value="">انتخاب کنید</option>`;
    return `<select name="${escapeHtml(name)}" ${required} ${isReadonly ? "disabled" : ""}>
      ${placeholder}
      ${entries.map(([optionValue, optionLabel]) => {
        const selected = String(optionValue) === String(safeValue) ? "selected" : "";
        return `<option value="${escapeHtml(optionValue)}" ${selected}>${escapeHtml(optionLabel)}</option>`;
      }).join("")}
    </select>`;
  }
  if (type === "number") {
    return `<input name="${escapeHtml(name)}" type="number" value="${escapeHtml(safeValue)}" ${required} ${readonlyAttr} ${placeholder} ${ltr} />`;
  }
  if (type === "password") {
    return `<input name="${escapeHtml(name)}" type="password" value="" ${required} autocomplete="new-password" placeholder="برای تغییر وارد کنید" ${ltr} />`;
  }
  const patternAttr = name === "phone"
    ? 'pattern="09[0-9]{9}" inputmode="numeric" title="مثلاً 09121234567"'
    : name === "national_id"
      ? 'pattern="\\d{10}" inputmode="numeric" title="۱۰ رقم"'
      : isJalaliDateField(name, meta)
        ? 'pattern="\\d{4}/\\d{2}/\\d{2}" title="مثلاً 1404/01/01"'
        : "";
  return `<input name="${escapeHtml(name)}" type="text" value="${escapeHtml(safeValue)}" ${required} ${readonlyAttr} ${placeholder} ${ltr} ${patternAttr} />`;
};

const portalPasswordSectionHtml = (title = "رمز ورود پنل نهاد") => `
  <div class="portal-password-options wide">
    <span class="portal-password-title">${escapeHtml(title)}</span>
    <label class="portal-password-choice">
      <input type="radio" name="portal_password_mode" value="auto" checked />
      <span>ساخت خودکار (۸ کاراکتر امن)</span>
    </label>
    <label class="portal-password-choice">
      <input type="radio" name="portal_password_mode" value="custom" />
      <span>تعیین دستی توسط مدیر</span>
    </label>
    <label class="portal-password-custom" hidden>
      <span>رمز دلخواه</span>
      <input name="portal_password" type="password" dir="ltr" class="ltr-input" autocomplete="new-password" minlength="6" maxlength="64" placeholder="حداقل ۶ کاراکتر" />
    </label>
    <p class="hint">نام کاربری از کد نهاد ساخته می‌شود و پس از ثبت قابل مشاهده است.</p>
  </div>`;

const wirePortalPasswordFields = (form) => {
  const modeInputs = form.querySelectorAll('input[name="portal_password_mode"]');
  const customWrap = form.querySelector(".portal-password-custom");
  const customInput = form.querySelector('input[name="portal_password"]');
  if (!modeInputs.length || !customWrap) return;
  const sync = () => {
    const mode = form.querySelector('input[name="portal_password_mode"]:checked')?.value || "auto";
    const isCustom = mode === "custom";
    customWrap.hidden = !isCustom;
    if (customInput) {
      customInput.required = isCustom;
      if (!isCustom) customInput.value = "";
    }
  };
  modeInputs.forEach((input) => input.addEventListener("change", sync));
  sync();
};

const collectPortalPasswordPayload = (form) => {
  const mode = form.querySelector('input[name="portal_password_mode"]:checked')?.value || "auto";
  if (mode !== "custom") return {};
  const password = String(form.querySelector('input[name="portal_password"]')?.value || "").trim();
  if (!password) throw new Error("رمز دلخواه را وارد کنید.");
  if (password.length < 6) throw new Error("رمز ورود نهاد باید حداقل ۶ کاراکتر باشد.");
  return { portal_password: password };
};

const openResetPortalModal = (teamId, teamName = "") => new Promise((resolve, reject) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = `بازنشانی رمز — ${teamName || "نهاد"}`;
  form.innerHTML = `
    ${portalPasswordSectionHtml("رمز جدید پنل نهاد")}
    <div class="modal-actions">
      <button class="button" type="submit">بازنشانی رمز</button>
      <button class="button ghost" type="button" data-close-modal>انصراف</button>
    </div>`;
  wirePortalPasswordFields(form);
  form.querySelector("[data-close-modal]").addEventListener("click", () => {
    closeModal();
    reject(new Error("cancelled"));
  });
  form.onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      const body = { id: teamId };
      const custom = collectPortalPasswordPayload(form);
      if (custom.portal_password) body.password = custom.portal_password;
      const result = await postJson("api.php?resource=teams&action=reset-portal-password", body);
      closeModal();
      resolve(result);
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      submitButton.disabled = false;
    }
  };
  modal.hidden = false;
  trapFocus(modal);
});

const openRecordModal = ({ resource, definition, record = null, onSaved, title = null }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  const isEdit = Boolean(record?.id);
  // Defaults first, then caller prefill — so collage/deposit forms keep the selected year/month.
  const defaults = (!isEdit && createDefaults[crudResourceKey(resource)])
    ? createDefaults[crudResourceKey(resource)]()
    : {};
  const formRecord = { ...defaults, ...(record || {}) };
  modal.querySelector("#crudModalTitle").textContent = title || `${isEdit ? "ویرایش" : "افزودن"} ${definition.title}`;
  const paymentHint = resource === "transactions" && panelMode === "team"
    ? `<p class="hint payment-allocation-hint">ماه اعلام‌شده برای پیگیری شماست. پس از تأیید مدیر، مبلغ <strong>ابتدا به قدیمی‌ترین ماه‌های بدهکار</strong> شما تخصیص می‌یابد و ممکن است با ماهی که اعلام کردید متفاوت باشد.</p>`
    : "";
  const portalPasswordBlock = resource === "teams" && !isEdit && canWrite && panelMode !== "team"
    ? portalPasswordSectionHtml()
    : "";
  const usesProfileUpload = resource === "members" || resource === "teams";
  const memberNeedsAvatar = resource === "members" && (!isEdit || Number(formRecord.has_avatar) !== 1);
  const teamNeedsLogo = resource === "teams" && (!isEdit || Number(formRecord.has_logo) !== 1);
  const incompleteMemberHint = resource === "members" && isEdit && (
    Number(formRecord.has_avatar) !== 1
    || !String(formRecord.father_name || "").trim()
    || !String(formRecord.email || "").trim()
    || !String(formRecord.address || "").trim()
  )
    ? `<p class="hint warning-text">پروفایل این عضو ناقص است. قبل از ذخیره، تصویر و مشخصات هویتی را تکمیل کنید.</p>`
    : "";
  const memberAvatarBlock = resource === "members"
    ? `<label class="wide profile-upload-field">
         <span>تصویر پروفایل${memberNeedsAvatar ? " *" : " (در صورت نیاز جایگزین کنید)"}</span>
         <div class="profile-upload-row">
           ${profileThumb(formRecord.avatar_url || "", formRecord.full_name || "")}
           <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" ${memberNeedsAvatar ? "required" : ""} />
         </div>
         <p class="hint">فقط JPG، PNG یا WebP — حداکثر ۲ مگابایت</p>
       </label>`
    : "";
  const teamLogoBlock = resource === "teams"
    ? `<label class="wide profile-upload-field">
         <span>تصویر پروفایل نهاد${teamNeedsLogo ? " *" : " (در صورت نیاز جایگزین کنید)"}</span>
         <div class="profile-upload-row">
           ${profileThumb(formRecord.logo_url || "", formRecord.name || "", "assets/brand/default-team.svg")}
           <input name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" ${teamNeedsLogo ? "required" : ""} />
         </div>
         <p class="hint">فقط JPG، PNG یا WebP — حداکثر ۲ مگابایت</p>
       </label>`
    : "";
  form.innerHTML = `
    ${isEdit ? `<input type="hidden" name="id" value="${escapeHtml(String(record.id))}" />` : ""}
    ${incompleteMemberHint}
    <div class="crud-grid">
      ${memberAvatarBlock}
      ${teamLogoBlock}
      ${Object.entries(definition.fields).map(([name, meta]) => {
        if (meta.type === "hidden") {
          return fieldInput(name, meta, formRecord[name] ?? "");
        }
        // Auto fields (e.g. member joined_at) are stamped server-side on create.
        if ((meta.auto || meta.readonly) && !isEdit) {
          return "";
        }
        const hint = meta.auto || meta.readonly
          ? '<p class="hint">به‌صورت خودکار ثبت شده و قابل ویرایش نیست.</p>'
          : "";
        return `
        <label class="${meta.type === "textarea" ? "wide" : ""}">
          <span>${escapeHtml(meta.label)}${meta.required && !meta.readonly && !meta.auto ? " *" : ""}</span>
          ${fieldInput(name, meta, formRecord[name] ?? "")}
          ${hint}
        </label>`;
      }).join("")}
    </div>
    ${portalPasswordBlock}
    ${paymentHint}
    <div class="modal-actions">
      <button class="button" type="submit">${isEdit ? "ذخیره" : "ثبت"}</button>
      <button class="button ghost" type="button" data-close-modal>انصراف</button>
    </div>`;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  if (portalPasswordBlock) wirePortalPasswordFields(form);
  form.onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      validateCrudForm(form, definition);
      if (usesProfileUpload) {
        const body = new FormData(form);
        if (isEdit) body.set("id", String(payloadId(record, body)));
        if (portalPasswordBlock) {
          const portal = collectPortalPasswordPayload(form);
          Object.entries(portal).forEach(([key, value]) => body.set(key, value));
        }
        const avatarFile = body.get("avatar");
        const logoFile = body.get("logo");
        if (resource === "members") {
          if (memberNeedsAvatar) {
            assertProfileImageFile(avatarFile instanceof File ? avatarFile : null, "تصویر پروفایل عضو");
          } else if (avatarFile instanceof File && avatarFile.size) {
            assertProfileImageFile(avatarFile, "تصویر پروفایل عضو");
          } else {
            body.delete("avatar");
          }
        }
        if (resource === "teams") {
          if (teamNeedsLogo) {
            assertProfileImageFile(logoFile instanceof File ? logoFile : null, "تصویر پروفایل نهاد");
          } else if (logoFile instanceof File && logoFile.size) {
            assertProfileImageFile(logoFile, "تصویر پروفایل نهاد");
          } else {
            body.delete("logo");
          }
        }
        await postForm(`api.php?resource=${encodeURIComponent(resource)}&action=${isEdit ? "update" : "create"}`, body);
      } else {
        const payload = Object.fromEntries(new FormData(form).entries());
        if (isEdit) payload.id = payload.id || record.id;
        if (portalPasswordBlock) Object.assign(payload, collectPortalPasswordPayload(form));
        await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=${isEdit ? "update" : "create"}`, payload);
      }
      closeModal();
      await onSaved();
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      submitButton.disabled = false;
    }
  };
  modal.hidden = false;
  trapFocus(modal);
};

const payloadId = (record, body) => body.get("id") || record?.id || "";

const devCategoryLabels = { idea: "ایده", action: "اقدام", planned: "برنامه‌ریزی‌شده" };
const devStatusLabels = { open: "باز", in_progress: "در حال اجرا", done: "انجام‌شده", cancelled: "لغو‌شده" };
const devPriorityLabels = { high: "بالا", medium: "متوسط", low: "پایین" };
const relatedSectionLabels = {
  teams: "نهادها", members: "اعضا", desks: "میزها", lockers: "کمدها", charges: "شارژ", transactions: "مالی",
};

const askLockerNumber = (emptyLockers = []) => new Promise((resolve, reject) => {
  let modal = document.getElementById("lockerModal");
  if (!modal) {
    document.body.insertAdjacentHTML("beforeend", `
      <div id="lockerModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-labelledby="lockerModalTitle">
          <div class="modal-head">
            <h2 id="lockerModalTitle">تخصیص کمد</h2>
            <button class="modal-close" type="button" data-locker-cancel aria-label="بستن">×</button>
          </div>
          <label class="wide"><span>شماره کمد</span>
            <input id="lockerNumberInput" type="number" min="1" placeholder="مثلاً ۱۲" list="emptyLockerOptions" />
            <datalist id="emptyLockerOptions"></datalist>
          </label>
          <p class="hint" id="lockerModalHint"></p>
          <div class="form-actions">
            <button type="button" class="button ghost" data-locker-cancel>انصراف</button>
            <button type="button" class="button" data-locker-confirm>تأیید تخصیص</button>
          </div>
        </div>
      </div>`);
    modal = document.getElementById("lockerModal");
    modal.addEventListener("click", (event) => {
      if (event.target === modal) modal._pendingCancel?.();
    });
  }

  const input = modal.querySelector("#lockerNumberInput");
  const datalist = modal.querySelector("#emptyLockerOptions");
  const hint = modal.querySelector("#lockerModalHint");
  datalist.innerHTML = emptyLockers.map((n) => `<option value="${escapeHtml(String(n))}"></option>`).join("");
  hint.textContent = emptyLockers.length
    ? `کمدهای خالی: ${emptyLockers.slice(0, 8).join("، ")}${emptyLockers.length > 8 ? "…" : ""}`
    : "شماره کمد خالی را وارد کنید.";
  input.value = emptyLockers[0] ? String(emptyLockers[0]) : "";
  modal.hidden = false;
  input.focus();
  trapFocus(modal);

  const cleanup = () => {
    modal.hidden = true;
    releaseFocusTrap(modal);
    modal._pendingCancel = null;
    modal.querySelectorAll("[data-locker-cancel]").forEach((btn) => { btn.onclick = null; });
    modal.querySelector("[data-locker-confirm]").onclick = null;
  };

  modal._pendingCancel = () => {
    cleanup();
    reject(new Error("cancelled"));
  };
  modal.querySelectorAll("[data-locker-cancel]").forEach((btn) => {
    btn.onclick = () => modal._pendingCancel?.();
  });
  modal.querySelector("[data-locker-confirm]").onclick = () => {
    const parsed = Number(String(input.value).replace(/[^\d]/g, ""));
    if (!parsed) {
      showToast("شماره کمد معتبر نیست.", "error");
      return;
    }
    cleanup();
    resolve(parsed);
  };
});

const openMemberRequestModal = (requestType, member) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  const isDelete = requestType === "delete";
  modal.querySelector("#crudModalTitle").textContent = isDelete
    ? `درخواست حذف — ${member.full_name || "عضو"}`
    : `درخواست ویرایش — ${member.full_name || "عضو"}`;
  form.innerHTML = isDelete
    ? `<p class="hint">پس از تأیید مرکز، عضو از فهرست نهاد حذف می‌شود.</p>
       <label class="wide"><span>توضیح (اختیاری)</span><textarea name="notes" rows="3"></textarea></label>
       <div class="modal-actions">
         <button class="button danger" type="submit">ثبت درخواست حذف</button>
         <button class="button ghost" type="button" data-close-modal>انصراف</button>
       </div>`
    : `<div class="crud-grid">
         <label class="wide profile-upload-field">
           <span>تصویر پروفایل (در صورت نیاز جایگزین کنید)</span>
           <div class="profile-upload-row">
             ${profileThumb(member.avatar_url || "", member.full_name || "")}
             <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
           </div>
         </label>
         <label><span>نام و نام خانوادگی *</span><input name="full_name" type="text" required value="${escapeHtml(member.full_name || "")}" /></label>
         <label><span>نام پدر *</span><input name="father_name" type="text" required value="${escapeHtml(member.father_name || "")}" /></label>
         <label><span>کد ملی *</span><input name="national_id" type="text" required dir="ltr" class="ltr-input" value="${escapeHtml(member.national_id || "")}" /></label>
         <label><span>شماره شناسنامه *</span><input name="id_certificate_number" type="text" required dir="ltr" class="ltr-input" value="${escapeHtml(member.id_certificate_number || "")}" /></label>
         <label><span>تاریخ تولد *</span><input name="birth_date" type="text" required placeholder="1370/01/01" value="${escapeHtml(member.birth_date || "")}" /></label>
         <label><span>محل تولد *</span><input name="birth_place" type="text" required value="${escapeHtml(member.birth_place || "")}" /></label>
         <label><span>تحصیلات *</span><input name="education" type="text" required value="${escapeHtml(member.education || "")}" /></label>
         <label><span>موبایل *</span><input name="phone" type="text" required dir="ltr" class="ltr-input" value="${escapeHtml(member.phone || "")}" /></label>
         <label><span>ایمیل *</span><input name="email" type="text" required dir="ltr" class="ltr-input" value="${escapeHtml(member.email || "")}" /></label>
         <label><span>دسترسی تردد</span>
           <select name="wants_access">
             <option value="0" ${Number(member.wants_access) !== 1 ? "selected" : ""}>خیر</option>
             <option value="1" ${Number(member.wants_access) === 1 ? "selected" : ""}>بله — نیاز به کد تردد دارد</option>
           </select>
         </label>
         <label class="wide"><span>آدرس محل سکونت *</span><textarea name="address" rows="2" required>${escapeHtml(member.address || "")}</textarea></label>
         <label class="wide"><span>توضیح</span><textarea name="notes" rows="2">${escapeHtml(member.notes || "")}</textarea></label>
       </div>
       <div class="modal-actions">
         <button class="button" type="submit">ثبت درخواست ویرایش</button>
         <button class="button ghost" type="button" data-close-modal>انصراف</button>
       </div>`;
  form.querySelector("[data-close-modal]")?.addEventListener("click", closeModal);
  form.onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      if (isDelete) {
        await postJson("api.php?resource=member-requests&action=create", {
          member_id: String(member.id),
          request_type: requestType,
          ...Object.fromEntries(new FormData(form).entries()),
        });
      } else {
        const body = new FormData(form);
        body.set("member_id", String(member.id));
        body.set("request_type", requestType);
        const phone = normalizeDigits(String(body.get("phone") || "")).replace(/\D/g, "");
        const nationalId = normalizeDigits(String(body.get("national_id") || "")).replace(/\D/g, "");
        if (!/^09\d{9}$/.test(phone)) {
          throw new Error("شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.");
        }
        if (!/^\d{10}$/.test(nationalId)) {
          throw new Error("کد ملی باید ۱۰ رقم باشد.");
        }
        if (!isValidEmail(String(body.get("email") || ""))) {
          throw new Error("ایمیل معتبر نیست.");
        }
        if (!isValidJalaliDate(String(body.get("birth_date") || ""))) {
          throw new Error("تاریخ تولد باید به فرمت 1370/01/01 باشد.");
        }
        body.set("phone", phone);
        body.set("national_id", nationalId);
        const avatarFile = body.get("avatar");
        if (avatarFile instanceof File && avatarFile.size) {
          assertProfileImageFile(avatarFile, "تصویر پروفایل");
        } else {
          body.delete("avatar");
        }
        await postForm("api.php?resource=member-requests&action=create", body);
      }
      closeModal();
      showToast("درخواست ثبت شد.", "success");
      document.querySelector('data-table[endpoint*="member-requests"]')?.load?.();
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      submitButton.disabled = false;
    }
  };
  modal.hidden = false;
  trapFocus(modal);
};

const workflowApprove = async (resource, id, row = {}, workflowType = "") => {
  if (resource === "pending-members" || workflowType === "member-approve") {
    let accessCode = "";
    if (Number(row.wants_access) === 1) {
      accessCode = await askAccessCode(row.full_name || "عضو");
    }
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id, access_code: accessCode });
    return;
  }

  if (resource === "pending-member-requests" || workflowType === "member-request") {
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id });
    return;
  }

  if (resource === "pending-locker-requests" || workflowType === "locker-request") {
    let emptyLockers = [];
    try {
      const lockerData = await fetchResource("api.php?resource=lockers", { page: 1, perPage: 100 });
      emptyLockers = lockerData.rows
        .filter((locker) => locker.status === "خالی")
        .map((locker) => Number(locker.locker_number))
        .filter((n) => n > 0)
        .sort((a, b) => a - b);
    } catch (error) {
      emptyLockers = [];
    }
    const lockerNumber = await askLockerNumber(emptyLockers);
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, {
      id,
      locker_number: lockerNumber,
    });
    return;
  }

  if (resource === "pending-room-reservations" || workflowType === "room-reservation") {
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id });
    return;
  }

  await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id });
};

const workflowReject = async (resource, id, reason = "") => {
  await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=reject`, { id, reason });
};

const askAccessCode = (memberName = "عضو") => new Promise((resolve, reject) => {
  let modal = document.getElementById("accessCodeModal");
  if (!modal) {
    document.body.insertAdjacentHTML("beforeend", `
      <div id="accessCodeModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-labelledby="accessCodeModalTitle">
          <div class="modal-head">
            <h2 id="accessCodeModalTitle">ثبت کد تردد</h2>
            <button class="modal-close" type="button" data-access-cancel aria-label="بستن">×</button>
          </div>
          <p class="hint" id="accessCodeModalHint"></p>
          <label class="wide"><span>کد تردد</span>
            <input id="accessCodeInput" type="text" dir="ltr" class="ltr-input" required placeholder="کد دسترسی عضو" />
          </label>
          <div class="form-actions">
            <button type="button" class="button ghost" data-access-cancel>انصراف</button>
            <button type="button" class="button" data-access-confirm>تأیید عضو</button>
          </div>
        </div>
      </div>`);
    modal = document.getElementById("accessCodeModal");
    modal.addEventListener("click", (event) => {
      if (event.target === modal) modal._pendingCancel?.();
    });
  }

  modal.querySelector("#accessCodeModalHint").textContent = `برای «${memberName}» کد تردد را وارد کنید.`;
  const input = modal.querySelector("#accessCodeInput");
  input.value = "";
  modal.hidden = false;
  input.focus();
  trapFocus(modal);

  const cleanup = () => {
    modal.hidden = true;
    releaseFocusTrap(modal);
    modal._pendingCancel = null;
    modal.querySelector("[data-access-confirm]").onclick = null;
    modal.querySelectorAll("[data-access-cancel]").forEach((btn) => { btn.onclick = null; });
  };

  modal._pendingCancel = () => {
    cleanup();
    reject(new Error("cancelled"));
  };
  modal.querySelector("[data-access-confirm]").onclick = () => {
    const code = input.value.trim();
    if (!code) {
      showToast("کد تردد الزامی است.", "error");
      return;
    }
    cleanup();
    resolve(code);
  };
  modal.querySelectorAll("[data-access-cancel]").forEach((btn) => {
    btn.onclick = () => modal._pendingCancel?.();
  });
});

const askRejectReason = (options = {}) => new Promise((resolve, reject) => {
  const required = options.required === true;
  const title = options.title || "رد درخواست";
  let modal = document.getElementById("rejectModal");
  if (!modal) {
    document.body.insertAdjacentHTML("beforeend", `
      <div id="rejectModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
          <div class="modal-head">
            <h2 id="rejectModalTitle">رد درخواست</h2>
            <button class="modal-close" type="button" data-reject-cancel aria-label="بستن">×</button>
          </div>
          <label class="wide"><span id="rejectReasonLabel">دلیل رد (اختیاری)</span><textarea id="rejectReasonInput" rows="3" placeholder="دلیل رد را بنویسید…"></textarea></label>
          <p class="hint reject-hint" id="rejectReasonError" hidden>دلیل رد الزامی است.</p>
          <div class="form-actions">
            <button type="button" class="button ghost" data-reject-cancel>انصراف</button>
            <button type="button" class="button danger" data-reject-confirm>رد کردن</button>
          </div>
        </div>
      </div>`);
    modal = document.getElementById("rejectModal");
    modal.addEventListener("click", (event) => {
      if (event.target === modal) modal._pendingCancel?.();
    });
  }

  const input = modal.querySelector("#rejectReasonInput");
  const label = modal.querySelector("#rejectReasonLabel");
  const error = modal.querySelector("#rejectReasonError");
  const titleEl = modal.querySelector("#rejectModalTitle");
  if (titleEl) titleEl.textContent = title;
  if (label) label.textContent = required ? "دلیل رد (الزامی)" : "دلیل رد (اختیاری)";
  if (error) error.hidden = true;
  input.value = "";
  modal.hidden = false;
  trapFocus(modal);
  input.focus();

  const cleanup = () => {
    modal.hidden = true;
    releaseFocusTrap(modal);
    modal._pendingCancel = null;
    modal.querySelectorAll("[data-reject-cancel]").forEach((btn) => { btn.onclick = null; });
    modal.querySelector("[data-reject-confirm]").onclick = null;
  };

  modal._pendingCancel = () => {
    cleanup();
    reject(new Error("cancelled"));
  };
  modal.querySelectorAll("[data-reject-cancel]").forEach((btn) => {
    btn.onclick = () => modal._pendingCancel?.();
  });
  modal.querySelector("[data-reject-confirm]").onclick = () => {
    const reason = input.value.trim();
    if (required && !reason) {
      if (error) error.hidden = false;
      input.focus();
      return;
    }
    cleanup();
    resolve(reason);
  };
});

const incomeSubtypeOptions = {
  "دوره آموزشی": "دوره آموزشی / کارگاه",
  "جریمه نهاد": "جریمه نهاد",
  "اسپانسری": "اسپانسری / حمایت مالی",
  "اجاره فضا": "اجاره سالن / فضا",
  "خدمات": "فروش خدمات",
  "سایر": "سایر درآمد",
};

const expenseSubtypeOptions = {
  "لوازم مصرفی": "لوازم مصرفی",
  "خوراکی": "خوراکی و پذیرایی",
  "تعمیرات": "تعمیرات و نگهداری",
  "حقوق": "حقوق و دستمزد",
  "خدمات": "خدمات پیمانکاری",
  "آب و برق": "آب، برق، اینترنت",
  "سایر": "سایر هزینه",
};

const openFinanceModal = async (category, record = null) => {
  const isEdit = Boolean(record?.id);
  const subtypeOptions = category === "هزینه" ? expenseSubtypeOptions : incomeSubtypeOptions;
  const fields = {
    category: { type: "hidden" },
    confirmed: { type: "hidden" },
    tx_date: { label: "تاریخ", type: "date", required: true, placeholder: "1404/01/01" },
    finance_subtype: {
      label: category === "هزینه" ? "نوع هزینه" : "نوع درآمد",
      type: "select",
      options: subtypeOptions,
      required: true,
    },
    description: {
      label: "شرح تکمیلی",
      type: "textarea",
      required: false,
      placeholder: category === "هزینه" ? "مثلاً خرید لوازم آشپزخانه" : "مثلاً برگزاری دوره طراحی محصول",
    },
    amount: {
      label: category === "هزینه" ? "مبلغ هزینه (ریال)" : "مبلغ درآمد (ریال)",
      type: "number",
      required: true,
    },
    notes: { label: "یادداشت داخلی", type: "textarea" },
  };
  openRecordModal({
    resource: "transactions",
    definition: { title: isEdit
      ? (category === "هزینه" ? "ویرایش هزینه" : "ویرایش درآمد")
      : (category === "هزینه" ? "ثبت هزینه" : "ثبت درآمد دستی"), fields },
    record: {
      ...(record || {}),
      category,
      confirmed: "1",
      tx_date: record?.tx_date || window.MECHINNO?.today || "",
      finance_subtype: record?.finance_subtype || "",
      amount: record ? Math.abs(Number(record.amount || 0)) || "" : "",
      description: record?.description || "",
      notes: record?.notes || "",
    },
    onSaved: async () => {
      await refreshAfterMutation("transactions");
      showToast(isEdit ? "ذخیره شد." : (category === "هزینه" ? "هزینه ثبت شد." : "درآمد ثبت شد."), "success");
    },
  });
};

document.getElementById("addIncomeButton")?.addEventListener("click", () => {
  openFinanceModal("درآمد").catch((error) => showToast(error.message, "error"));
});

document.getElementById("addExpenseButton")?.addEventListener("click", () => {
  openFinanceModal("هزینه").catch((error) => showToast(error.message, "error"));
});

const formatCell = (column, value, row, resource) => {
  if (column === "avatar_url") {
    return profileThumb(value || row.avatar_url || "", row.full_name || row.current_full_name || "");
  }
  if (column === "logo_url") {
    return profileThumb(value || row.logo_url || "", row.name || "", "assets/brand/default-team.svg");
  }
  if (column === "entity_type") return entityBadge(value);
  if (column === "is_leader") {
    return Number(value) === 1
      ? '<span class="badge badge-paid">مسئول نهاد</span>'
      : '<span class="badge">عضو</span>';
  }
  if (column === "is_active" && resource === "teams") {
    return Number(value) === 1
      ? '<span class="badge badge-paid">فعال</span>'
      : '<span class="badge badge-debt">غیرفعال — بدون قرارداد سال جاری</span>';
  }
  if (column === "year_status" && resource === "teams" && window.TeamYearWorkspace) {
    return window.TeamYearWorkspace.renderTeamStatusChecklist(row);
  }
  if (column === "contract_status") {
    if (value === "active") return '<span class="badge badge-paid">فعال</span>';
    if (value === "expired") return '<span class="badge badge-debt">منقضی</span>';
    return '<span class="badge">غیرفعال</span>';
  }
  if (column === "assignment_status") {
    if (value === "active") return '<span class="badge badge-paid">جاری</span>';
    if (value === "scheduled") return '<span class="badge badge-partial">زمان‌بندی‌شده</span>';
    if (value === "expired") return '<span class="badge badge-debt">منقضی</span>';
    return '<span class="badge">—</span>';
  }
  if (column === "team_label" || column === "team_name") {
    const teamId = row[linkColumns[column]] || row.team_id;
    const name = (panelMode === "admin" && teamId && value)
      ? teamLink(teamId, value)
      : escapeHtml(value || "—");
    const active = row.team_is_active;
    if (active !== undefined && active !== null && value) {
      return `${name} ${teamActiveBadge(active)}`;
    }
    return name;
  }
  if (column === "request_type") return escapeHtml(requestTypeLabel(value));
  if (column === "usage_type") return escapeHtml(usageLabels[value] || value || "—");
  if (column === "billing_exemptions") return billingExemptionBadges(row);
  if (["charge_rate_override", "informal_rent_rate_override", "formal_contract_amount"].includes(column)) {
    return value === null || value === "" || value === undefined ? "—" : escapeHtml(formatMoney(value));
  }
  if (column === "category" && resource === "development_plans") {
    return escapeHtml(devCategoryLabels[value] || value || "—");
  }
  if (column === "category") {
    if (value === "واریز تیم") return "دریافت از نهاد";
    return escapeHtml(value || "—");
  }
  if (column === "finance_subtype") return escapeHtml(value || "—");
  if (column === "confirmed") return Number(value) === 1 ? "بله" : "خیر";
  if (column === "wants_access") return accessStatusLabel(row);
  if (column === "access_code") {
    const code = String(value ?? "").trim();
    if (!code) return "—";
    return panelMode === "team" ? "—" : escapeHtml(code);
  }
  if (column === "approval_status") return approvalStatusBadge(value);
  if (column === "payment_status") return paymentStatusBadge(value);
  if (column === "status" && resource === "development_plans") {
    return escapeHtml(devStatusLabels[value] || value || "—");
  }
  if (column === "priority") return escapeHtml(devPriorityLabels[value] || value || "—");
  if (column === "related_section") {
    const label = relatedSectionLabels[value] || value;
    return value
      ? `<button type="button" class="text-link" data-nav-section="${escapeHtml(value)}">${escapeHtml(label)}</button>`
      : "—";
  }
  if (column === "depends_on_title") return escapeHtml(value || "—");
  if (["estimated_cost", "estimated_revenue"].includes(column)) return formatMoney(value);
  if (column === "notes" && (resource === "pending-payments" || resource === "payment-history") && value) {
    return escapeHtml(String(value));
  }
  if (column === "description" && resource === "development_plans" && value) {
    const text = String(value);
    return escapeHtml(text.length > 80 ? `${text.slice(0, 80)}…` : text);
  }
  if (column === "month_name" && !value && row.month_index) {
    return formatPlain(monthNames[Number(row.month_index)] || row.month_index);
  }
  if (column === "month_index") {
    return formatPlain(monthNames[Number(value)] || value);
  }
  if (column === "assignment_period") {
    return escapeHtml(row.assignment_period || formatMonthRange(row.assigned_from || row.assignment_from, row.assigned_until || row.assignment_until));
  }
  if (["assigned_from", "assignment_from"].includes(column)) {
    const idx = monthIndexFromDate(value || row.assigned_from || row.assignment_from);
    return formatPlain(monthNames[Number(idx)] || value || "—");
  }
  if (["assigned_until", "assignment_until"].includes(column)) {
    const idx = monthIndexFromDate(value || row.assigned_until || row.assignment_until);
    return formatPlain(monthNames[Number(idx)] || value || "—");
  }
  if (column === "role") {
    const map = { admin_editor: "مدیر — ویرایش", admin_viewer: "مدیر — مشاهده", team: "نهاد" };
    return escapeHtml(map[value] || value || "—");
  }
  if (column === "is_active") return Number(value) === 1 ? "فعال" : "غیرفعال";
  if (column === "portal_has_password") return Number(value) === 1 ? "تنظیم‌شده" : "—";
  if (column === "password") return "—";
  if (column === "status" && resource === "lockers") return lockerStatusBadge(value);
  if (column === "status" && (resource === "locker-requests" || resource === "pending-locker-requests")) {
    const map = { pending: "در انتظار", approved: "تأیید‌شده", rejected: "رد‌شده" };
    const label = map[value] || value || "—";
    return `<span class="badge">${escapeHtml(label)}</span>`;
  }
  if (column === "status" && (resource === "room-reservations" || resource === "pending-room-reservations")) {
    const map = { pending: "در انتظار", approved: "تأیید‌شده", rejected: "رد‌شده", cancelled: "لغو‌شده" };
    const label = map[value] || value || "—";
    return `<span class="badge">${escapeHtml(label)}</span>`;
  }
  if (column === "source" && (resource === "room-reservations" || resource === "pending-room-reservations")) {
    const map = { public: "عمومی", team: "نهاد", admin: "مدیر" };
    return escapeHtml(map[value] || value || "—");
  }
  if (linkColumns[column] && row[linkColumns[column]] && value) {
    // `name` → id is only for teams; meeting-rooms also have `name`/`id`.
    if (column === "name") {
      if (resource !== "teams") return escapeHtml(value);
      return `<button type="button" class="text-link" data-team-id="${escapeHtml(row.id)}">${escapeHtml(value)}</button>`;
    }
    return teamLink(row[linkColumns[column]], value);
  }
  if (column === "desk_numbers" && value) {
    return String(value).split(",").filter(Boolean).map((n) => deskLink(n.trim())).join(" ");
  }
  if (column === "locker_number" && value) return lockerLink(value);
  if (column === "number" && resource === "desks") return deskLink(value);
  if (["amount", "charge_amount", "rent_amount", "charge_rate", "informal_rent_rate", "formal_contract_amount"].includes(column)) {
    return formatMoney(value);
  }
  if (plainColumns.has(column)) return formatPlain(value);
  return formatNumber(value);
};

const resolveColumns = (rows, resource) => {
  let preferred = resourceColumns[resource] ?? [];
  if (panelMode === "team" && teamPanelHiddenColumns[resource]) {
    const hidden = new Set(teamPanelHiddenColumns[resource]);
    preferred = preferred.filter((column) => !hidden.has(column));
  }
  if (!rows.length) return preferred;
  const available = new Set(Object.keys(rows[0]));
  return preferred.filter((c) => available.has(c));
};

const openChangeLeaderModal = async (teamId, teamName) => {
  const { rows } = await fetchResource("api.php?resource=members", {
    page: 1,
    perPage: 200,
    teamId: String(teamId),
    approvalStatus: "approved",
  });
  if (!rows.length) {
    throw new Error("برای این نهاد عضو تأیید‌شده‌ای وجود ندارد.");
  }
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = `تغییر مسئول: ${teamName || "نهاد"}`;
  form.innerHTML = `
    <p class="hint">مسئول جدید را از بین اعضای تأیید‌شده این نهاد انتخاب کنید. نام و تماس نهاد به‌روز می‌شود.</p>
    <label>
      <span>عضو مسئول *</span>
      <select name="member_id" required>
        <option value="">انتخاب کنید</option>
        ${rows.map((row) => `<option value="${escapeHtml(row.id)}">${escapeHtml(row.full_name || "—")}${Number(row.is_leader) === 1 ? " (مسئول فعلی)" : ""}</option>`).join("")}
      </select>
    </label>
    <div class="modal-actions">
      <button class="button" type="submit">ثبت مسئول جدید</button>
      <button class="button ghost" type="button" data-close-modal>انصراف</button>
    </div>`;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  form.onsubmit = async (event) => {
    event.preventDefault();
    const memberId = Number(new FormData(form).get("member_id"));
    if (!memberId) return;
    try {
      await postJson("api.php?resource=teams&action=change-leader", { id: teamId, member_id: memberId });
      closeModal();
      await refreshAfterMutation("teams");
      await refreshAfterMutation("members");
      showToast("مسئول نهاد به‌روز شد.", "success");
    } catch (error) {
      showToast(error.message, "error");
    }
  };
  modal.hidden = false;
  trapFocus(modal);
};

const initMemberFilters = async () => {
  const bar = document.getElementById("memberFilters");
  const table = document.getElementById("membersTable");
  if (!bar || !table) return;

  const initial = {
    q: table.filter || "",
    teamId: table.memberTeamFilter || "",
    entityType: table.memberEntityTypeFilter || "",
    isLeader: table.memberLeaderFilter || "",
    wantsAccess: table.memberAccessFilter || "",
  };

  const applyMemberFilters = (filters) => {
    table.memberTeamFilter = filters.teamId;
    table.memberEntityTypeFilter = filters.entityType;
    table.memberLeaderFilter = filters.isLeader;
    table.memberAccessFilter = filters.wantsAccess;
    table.filter = filters.q;
    table.setAttribute("data-member-team", filters.teamId);
    table.setAttribute("data-member-entity-type", filters.entityType);
    table.setAttribute("data-member-leader", filters.isLeader);
    table.setAttribute("data-member-access", filters.wantsAccess);
    table.page = 1;
    table.load?.();
  };

  await buildRecipientFilterBar(bar, initial, applyMemberFilters);
  applyMemberFilters(initial);

  const search = table.querySelector(".search");
  if (search) {
    search.closest(".table-actions")?.classList.add("hidden");
  }
};

const readRecipientFilters = (container) => ({
  q: container.querySelector('[data-filter="q"]')?.value.trim() || "",
  teamId: container.querySelector('[data-filter="teamId"]')?.value || "",
  entityType: container.querySelector('[data-filter="entityType"]')?.value || "",
  isLeader: container.querySelector('[data-filter="isLeader"]')?.value || "",
  wantsAccess: container.querySelector('[data-filter="wantsAccess"]')?.value || "",
});

window.buildRecipientFilterBar = async (container, state, onApply) => {
  if (!container) return;
  container._filterOnApply = onApply;
  const meta = await loadCrudMeta();
  const teamOptions = meta.resources?.members?.fields?.team_id?.options || {};
  const teamEntries = Object.entries(teamOptions);

  if (!container.dataset.ready) {
    container.dataset.ready = "1";
    container.className = "filter-bar recipient-filter-bar";
    container.innerHTML = `
      <label>جست‌وجو
        <input type="search" data-filter="q" placeholder="نام، موبایل، نهاد…" />
      </label>
      <label>نهاد
        <select data-filter="teamId"><option value="">همه</option></select>
      </label>
      <label>نوع نهاد
        <select data-filter="entityType">
          <option value="">همه</option>
          <option value="team">تیم</option>
          <option value="company">شرکت</option>
          <option value="student">دانشجو</option>
        </select>
      </label>
      <label>نقش
        <select data-filter="isLeader">
          <option value="">همه</option>
          <option value="1">مسئول</option>
          <option value="0">عضو عادی</option>
        </select>
      </label>
      <label>دسترسی تردد
        <select data-filter="wantsAccess">
          <option value="">همه</option>
          <option value="1">نیاز به تردد</option>
          <option value="0">بدون تردد</option>
        </select>
      </label>`;

    const teamSelect = container.querySelector('[data-filter="teamId"]');
    teamSelect.innerHTML = `<option value="">همه</option>${teamEntries.map(([value, label]) =>
      `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
    ).join("")}`;

    const apply = () => {
      container._filterOnApply?.(readRecipientFilters(container));
    };

    container.querySelector('[data-filter="q"]')?.addEventListener("input", () => {
      clearTimeout(container.searchTimer);
      container.searchTimer = setTimeout(apply, 300);
    });
    container.querySelectorAll("select").forEach((select) => select.addEventListener("change", apply));
  }

  container.querySelector('[data-filter="q"]').value = state.q || "";
  container.querySelector('[data-filter="teamId"]').value = state.teamId || "";
  container.querySelector('[data-filter="entityType"]').value = state.entityType || "";
  container.querySelector('[data-filter="isLeader"]').value = state.isLeader || "";
  container.querySelector('[data-filter="wantsAccess"]').value = state.wantsAccess || "";
};

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.resource = new URL(this.endpoint, window.location.href).searchParams.get("resource");
    this.workflow = this.getAttribute("data-workflow") || "";
    this.workflowType = this.getAttribute("data-workflow-type") || "";
    this.noAdd = this.hasAttribute("data-no-add") || this.hasAttribute("data-readonly");
    this.txCategoryFilter = this.getAttribute("data-tx-filter") || "";
    this.fiscalYearFilter = this.getAttribute("data-fiscal-year") || "";
    this.paymentStatusFilter = this.getAttribute("data-payment-filter") || "";
    this.approvalStatusFilter = this.getAttribute("data-approval-filter") || "";
    this.memberTeamFilter = this.getAttribute("data-member-team") || "";
    this.memberEntityTypeFilter = this.getAttribute("data-member-entity-type") || "";
    this.memberLeaderFilter = this.getAttribute("data-member-leader") || "";
    this.memberAccessFilter = this.getAttribute("data-member-access") || "";
    this.assignmentStatusFilter = this.getAttribute("data-assignment-status") || "";
    this.tableKey = this.getAttribute("data-table-key") || "";
    this.readOnly = tableSuppressesAdd(this);
    this.definition = null;
    this.rows = [];
    this.page = 1;
    this.perPage = Number(this.getAttribute("data-per-page") || 25) || 25;
    this.total = 0;
    this.pages = 1;
    this.filter = "";
    this.searchTimer = null;
    const addButtonHtml = this.readOnly
      ? ""
      : `<button class="button add-button" type="button">+ افزودن</button>`;
    const bulkImportHtml = this.resource === "teams" && canWrite && panelMode === "admin"
      ? `<button class="button ghost bulk-import-button" type="button" id="bulkYearImportButton">ورود گروهی CSV</button>`
      : "";
    const perPageOptions = [10, 25, 50, 100].map((n) =>
      `<option value="${n}"${n === this.perPage ? " selected" : ""}>${n}</option>`
    ).join("");
    this.innerHTML = `
      <article class="panel data-panel">
        <div class="table-toolbar${this.title?.trim() ? "" : " is-empty-title"}">
          <h2>${escapeHtml(this.title)}</h2>
          <div class="table-actions">
            ${bulkImportHtml}
            ${addButtonHtml}
            <input class="search" type="search" placeholder="جست‌وجو... ( / )" />
          </div>
        </div>
        <div class="table-wrap">${renderSkeletonTable()}</div>
        <div class="mobile-cards"></div>
        <div class="table-pagination" hidden>
          <span class="pager-info"></span>
          <div class="pager-buttons">
            <label>تعداد
              <select class="per-page-select">
                ${perPageOptions}
              </select>
            </label>
            <button class="mini-button pager-prev" type="button">قبلی</button>
            <button class="mini-button pager-next" type="button">بعدی</button>
          </div>
        </div>
      </article>`;
    this.querySelector(".search").addEventListener("input", (e) => {
      this.filter = e.target.value;
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.page = 1;
        this.load();
      }, 300);
    });
    this.querySelector(".bulk-import-button")?.addEventListener("click", () => {
      window.TeamYearWorkspace?.openBulkImportModal();
    });
    this.querySelector(".add-button")?.addEventListener("click", async () => {
      if (this.resource === "transactions" && this.txCategoryFilter) {
        openFinanceModal(this.txCategoryFilter).catch((error) => showToast(error.message, "error"));
        return;
      }
      try {
        if (!this.definition) {
          const meta = await loadCrudMeta();
          this.definition = meta.resources[this.resource]
            || meta.resources[this.resource.replace(/-/g, "_")]
            || null;
        }
        if (!this.definition) {
          showToast("تعریف فرم این بخش هنوز بارگذاری نشده است.", "error");
          return;
        }
        openRecordModal({
          resource: this.resource,
          definition: this.definition,
          onSaved: async () => {
            this.page = 1;
            await this.load();
            await refreshAfterMutation(this.closest(".section")?.id || null);
            showToast("ثبت شد.", "success");
          },
        });
      } catch (error) {
        showToast(error.message, "error");
      }
    });
    this.querySelector(".per-page-select")?.addEventListener("change", (e) => {
      this.perPage = Number(e.target.value) || 25;
      this.page = 1;
      this.load();
    });
    this.querySelector(".pager-prev")?.addEventListener("click", () => {
      if (this.page > 1) { this.page -= 1; this.load(); }
    });
    this.querySelector(".pager-next")?.addEventListener("click", () => {
      if (this.page < this.pages) { this.page += 1; this.load(); }
    });
    this.addEventListener("click", (e) => this.handleClick(e));
    this.addEventListener("change", (e) => this.handleChange(e));
    // Lazy-load: only fetch when the parent section is already active.
    if (this.closest(".section.active")) {
      this.load();
    }
  }

  async load() {
    try {
      const meta = await loadCrudMeta();
      this.definition = meta.resources[this.resource]
        || meta.resources[this.resource.replace(/-/g, "_")]
        || null;
      const canAdd = tableAllowsAdd(this, this.definition);
      const addBtn = this.querySelector(".add-button");
      if (addBtn) addBtn.hidden = !canAdd;
      const result = await fetchResource(this.endpoint, {
        page: this.page,
        perPage: this.perPage,
        category: this.txCategoryFilter,
        paymentStatus: this.paymentStatusFilter,
        approvalStatus: this.approvalStatusFilter,
        fiscalYear: this.fiscalYearFilter,
        teamId: this.memberTeamFilter,
        entityType: this.memberEntityTypeFilter,
        isLeader: this.memberLeaderFilter,
        wantsAccess: this.memberAccessFilter,
        assignmentStatus: this.assignmentStatusFilter,
        q: this.filter.trim(),
      });
      this.rows = result.rows;
      this.total = result.total;
      this.page = result.page;
      this.perPage = result.per_page;
      this.pages = result.pages;
      this.render();
      this.renderPager();
    } catch (error) {
      this.querySelector(".table-wrap").innerHTML = renderEmptyState(`خطا: ${error.message}`, { icon: "error" });
      this.querySelector(".table-pagination").hidden = true;
    }
  }

  renderPager() {
    const pager = this.querySelector(".table-pagination");
    if (!pager) return;
    // Keep pager visible whenever there are rows so page-size control stays available.
    pager.hidden = this.total <= 0;
    pager.querySelector(".pager-info").textContent =
      `صفحه ${this.page.toLocaleString("fa-IR")} از ${this.pages.toLocaleString("fa-IR")} — ${this.total.toLocaleString("fa-IR")} رکورد`;
    const perPageSelect = pager.querySelector(".per-page-select");
    if (perPageSelect) perPageSelect.value = String(this.perPage);
    pager.querySelector(".pager-prev").disabled = this.page <= 1;
    pager.querySelector(".pager-next").disabled = this.page >= this.pages;
  }

  filteredRows() {
    return this.rows;
  }

  resolveTableColumns(rows) {
    let preferred = resourceColumns[this.resource] ?? [];
    if (this.resource === "transactions" && this.txCategoryFilter) {
      preferred = ["tx_date", "finance_subtype", "description", "amount", "notes"];
    }
    if (panelMode === "team" && teamPanelHiddenColumns[this.resource]) {
      const hidden = new Set(teamPanelHiddenColumns[this.resource]);
      preferred = preferred.filter((column) => !hidden.has(column));
    }
    if (!rows.length) return preferred;
    const available = new Set(Object.keys(rows[0]));
    return preferred.filter((column) => available.has(column));
  }

  renderMobileCards(rows) {
    const container = this.querySelector(".mobile-cards");
    if (!isMobile()) return false;
    const columns = rows.length ? this.resolveTableColumns(rows).slice(0, 6) : [];
    const editable = tableAllowsEdit(this, this.definition);
    const workflow = this.workflow && canWrite;
    container.innerHTML = rows.length
      ? rows.map((row) => {
        const highlighted = this.resource === "lockers" && highlightLocker === Number(row.locker_number);
        const fields = columns.map((column) => `
          <div class="mobile-card-field">
            <span>${escapeHtml(labels[column] || column)}</span>
            <strong>${formatCell(column, row[column], row, this.resource)}</strong>
          </div>`).join("");
        const profileBtn = this.resource === "teams"
          ? `<button class="mini-button primary" type="button" data-action="profile" data-id="${escapeHtml(row.id)}">پروفایل</button>
             ${canWrite ? `<button class="mini-button" type="button" data-action="change-leader" data-id="${escapeHtml(row.id)}">تغییر مسئول</button>
             <button class="mini-button" type="button" data-action="show-portal" data-id="${escapeHtml(row.id)}">نمایش رمز</button>
           <button class="mini-button" type="button" data-action="reset-portal" data-id="${escapeHtml(row.id)}">بازنشانی رمز</button>` : ""}` : "";
        const workflowBtns = workflow
          ? `<button class="mini-button primary" type="button" data-action="approve" data-id="${escapeHtml(row.id)}">تأیید</button>
             <button class="mini-button danger" type="button" data-action="reject" data-id="${escapeHtml(row.id)}">رد</button>` : "";
        const memberTeamActions = panelMode === "team" && this.resource === "members" && row.approval_status === "approved"
          ? `<button class="mini-button" type="button" data-action="request-member-edit" data-id="${escapeHtml(row.id)}">درخواست ویرایش</button>
             <button class="mini-button danger" type="button" data-action="request-member-delete" data-id="${escapeHtml(row.id)}">درخواست حذف</button>`
          : "";
        const editBtns = ((editable && canWrite) || rowAllowsTeamEdit(this.resource, row))
          ? `<button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>` : "";
        const deleteBtns = ((editable && canWrite) || rowAllowsTeamDelete(this.resource, row))
          ? `<button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : "";
        const rowEditBtns = `${editBtns}${deleteBtns}`;
        return `<article class="mobile-card ${highlighted ? "highlighted" : ""}">${fields}
          <div class="row-actions">${profileBtn}${workflowBtns}${memberTeamActions}${rowEditBtns}</div></article>`;
      }).join("")
      : renderEmptyState("رکوردی یافت نشد.", { icon: "search" });
    return true;
  }

  render() {
    syncMobileClass();
    const rows = this.filteredRows();
    const wrap = this.querySelector(".table-wrap");
    const mobile = this.querySelector(".mobile-cards");

    if (this.renderMobileCards(rows)) {
      wrap.innerHTML = "";
      return;
    }

    if (!rows.length) {
      const cta = tableAllowsAdd(this, this.definition)
        ? `<div class="empty-state-cta"><button class="button add-inline" type="button">+ افزودن اولین رکورد</button></div>` : "";
      wrap.innerHTML = renderEmptyState("رکوردی یافت نشد.", { icon: "inbox", cta });
      wrap.querySelector(".add-inline")?.addEventListener("click", () => this.querySelector(".add-button")?.click());
      if (mobile) mobile.innerHTML = "";
      return;
    }

    const columns = this.resolveTableColumns(rows);
    const editable = tableAllowsEdit(this, this.definition);
    const workflow = this.workflow && canWrite;
    const statusField = this.definition?.status_field;
    const statusOptions = this.definition?.status_options || [];
    const head = columns.map((c) => `<th>${escapeHtml(labels[c] || c)}</th>`).join("");
    const body = rows.map((row) => {
      const rowHighlight = (this.resource === "lockers" && highlightLocker === Number(row.locker_number))
        || (this.resource === "desks" && highlightDesk === Number(row.number));
      const cells = columns.map((column) => {
        const value = row[column];
        if (editable && column === statusField && statusOptions.length) {
          const labelMap = this.resource === "development_plans" ? devStatusLabels : null;
          const options = statusOptions.map((o) => {
            const label = labelMap?.[o] || o;
            return `<option value="${escapeHtml(o)}" ${String(o) === String(value) ? "selected" : ""}>${escapeHtml(label)}</option>`;
          }).join("");
          return `<td><select class="inline-status" data-id="${escapeHtml(row.id)}">${options}</select></td>`;
        }
        let className = "";
        if (moneyColumns.has(column)) {
          className = "num";
          if (column === "amount") {
            className += Number(value) < 0 ? " money-negative" : Number(value) > 0 ? " money-positive" : "";
          }
        }
        return `<td class="${className}">${formatCell(column, value, row, this.resource)}</td>`;
      }).join("");
      const profileAction = this.resource === "teams"
        ? `<button class="mini-button primary" type="button" data-action="profile" data-id="${escapeHtml(row.id)}">پروفایل</button>
           ${canWrite ? `<button class="mini-button" type="button" data-action="change-leader" data-id="${escapeHtml(row.id)}">تغییر مسئول</button>
           <button class="mini-button" type="button" data-action="show-portal" data-id="${escapeHtml(row.id)}">نمایش رمز</button>
           <button class="mini-button" type="button" data-action="reset-portal" data-id="${escapeHtml(row.id)}">بازنشانی رمز</button>` : ""}` : "";
      const workflowAction = workflow
        ? `<button class="mini-button primary" type="button" data-action="approve" data-id="${escapeHtml(row.id)}">تأیید</button>
           <button class="mini-button danger" type="button" data-action="reject" data-id="${escapeHtml(row.id)}">رد</button>`
        : "";
      const canEditRow = (editable && canWrite) || rowAllowsTeamEdit(this.resource, row);
      const canDeleteRow = (editable && canWrite) || rowAllowsTeamDelete(this.resource, row);
      const memberTeamActions = panelMode === "team" && this.resource === "members" && row.approval_status === "approved"
        ? `<button class="mini-button" type="button" data-action="request-member-edit" data-id="${escapeHtml(row.id)}">درخواست ویرایش</button>
           <button class="mini-button danger" type="button" data-action="request-member-delete" data-id="${escapeHtml(row.id)}">درخواست حذف</button>`
        : "";
      const actions = canEditRow || canDeleteRow || profileAction || workflowAction || memberTeamActions
        ? `<td class="row-actions">${profileAction}${workflowAction}${memberTeamActions}
        ${canEditRow ? `<button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>` : ""}
        ${canDeleteRow ? `<button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : ""}</td>` : "";
      return `<tr class="${rowHighlight ? "highlighted" : ""}">${cells}${actions}</tr>`;
    }).join("");
    const hasActions = rows.some((row) =>
      (editable && canWrite) || rowAllowsTeamEdit(this.resource, row) || rowAllowsTeamDelete(this.resource, row)
      || (panelMode === "team" && this.resource === "members" && row.approval_status === "approved")
    ) || this.resource === "teams" || workflow;
    wrap.innerHTML = `<table><thead><tr>${head}${hasActions ? "<th>عملیات</th>" : ""}</tr></thead><tbody>${body}</tbody></table>`;
    if (mobile) mobile.innerHTML = "";
  }

  async handleClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button || !this.contains(button)) return;
    const id = Number(button.dataset.id);
    if (!id) return;

    if (button.dataset.action === "approve" && this.workflow) {
      if (button.disabled) return;
      button.disabled = true;
      const row = this.rows.find((item) => String(item.id) === String(id)) || {};
      try {
        await workflowApprove(this.resource, id, row, this.workflowType);
        await this.load();
        await refreshAfterMutation(this.closest(".section")?.id || null);
        showToast("تأیید شد.", "success");
      } catch (error) {
        if (error.message !== "cancelled") showToast(error.message, "error");
      } finally {
        button.disabled = false;
      }
      return;
    }
    if (button.dataset.action === "reject" && this.workflow) {
      if (button.disabled) return;
      button.disabled = true;
      try {
        const reason = await askRejectReason();
        await workflowReject(this.resource, id, reason);
        await this.load();
        await refreshAfterMutation(this.closest(".section")?.id || null);
        showToast("رد شد.", "success");
      } catch (error) {
        if (error.message !== "cancelled") showToast(error.message, "error");
      } finally {
        button.disabled = false;
      }
      return;
    }

    const record = this.rows.find((row) => Number(row.id) === id);
    if (!record) return;
    if (button.dataset.action === "profile") {
      openTeamProfile(id).catch((error) => showToast(error.message, "error"));
      return;
    }
    if (button.dataset.action === "change-leader" && canWrite) {
      const team = this.rows.find((row) => Number(row.id) === id);
      openChangeLeaderModal(id, team?.name || "").catch((error) => showToast(error.message, "error"));
      return;
    }
    if (button.dataset.action === "request-member-edit") {
      openMemberRequestModal("update", record);
      return;
    }
    if (button.dataset.action === "request-member-delete") {
      if (!window.confirm(`درخواست حذف «${record.full_name || "عضو"}» ثبت شود؟`)) return;
      openMemberRequestModal("delete", record);
      return;
    }
    if (button.dataset.action === "show-portal") {
      try {
        const creds = await fetchJson(`api.php?resource=teams&action=portal-credentials&id=${encodeURIComponent(id)}`);
        openPortalCredentialsModal(creds);
      } catch (error) {
        showToast(error.message, "error");
      }
      return;
    }

    if (button.dataset.action === "reset-portal") {
      if (!canWrite) return;
      try {
        const team = this.rows.find((row) => Number(row.id) === id);
        const result = await openResetPortalModal(id, team?.name || "");
        await this.load();
        if (result.credentials?.password) {
          openPortalPasswordResultModal(result.credentials);
        }
        showToast("رمز پنل نهاد بازنشانی شد.", "success");
      } catch (error) {
        if (error.message !== "cancelled") showToast(error.message, "error");
      }
      return;
    }
    if (!this.definition) return;
    if (button.dataset.action === "edit") {
      if (this.resource === "transactions" && this.txCategoryFilter) {
        openFinanceModal(this.txCategoryFilter, { ...record }).catch((error) => showToast(error.message, "error"));
        return;
      }
      if (this.resource === "desks" && canWrite && window.TeamYearWorkspace) {
        window.TeamYearWorkspace.openDeskAssignModal(Number(record.number))
          .catch((error) => showToast(error.message, "error"));
        return;
      }
      if (this.resource === "desk-assignments" && canWrite) {
        openDeskHistoryAssignModal({
          id: record.id,
          team_id: record.team_id,
          desk_id: record.desk_id,
          usage_type: record.usage_type,
          fiscal_year: record.fiscal_year,
          assigned_from_month: validAssignmentMonth(record.assigned_from_month || monthIndexFromDate(record.assigned_from), "1"),
          assigned_until_month: validAssignmentMonth(record.assigned_until_month || monthIndexFromDate(record.assigned_until), "12"),
          charge_exempt: record.charge_exempt,
          rent_exempt: record.rent_exempt,
          notes: record.notes,
        }).catch((error) => showToast(error.message, "error"));
        return;
      }
      openRecordModal({
        resource: this.resource,
        definition: this.definition,
        record: { ...record },
        onSaved: async () => {
          await this.load();
          await refreshAfterMutation(this.closest(".section")?.id || null);
          showToast("ذخیره شد.", "success");
        },
      });
      return;
    }
    if (button.dataset.action === "delete") {
      const deleteMessage = this.resource === "team_contracts"
        ? "قرارداد و فایل‌های پیوست آن حذف شود؟ این عمل قابل بازگشت نیست."
        : "حذف شود؟";
      if (!window.confirm(deleteMessage)) return;
      try {
        await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=delete`, { id });
        await this.load();
        await refreshAfterMutation(this.closest(".section")?.id || null);
        if (this.resource === "team_contracts") {
          await loadPendingContractsQueue().catch(() => {});
        }
        showToast(this.resource === "team_contracts" ? "قرارداد و پیوست‌ها حذف شد." : "حذف شد.", "success");
      } catch (error) {
        showToast(error.message, "error");
      }
    }
  }

  async handleChange(event) {
    const select = event.target.closest(".inline-status");
    if (!select || !this.contains(select) || !this.definition) return;
    select.disabled = true;
    try {
      await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=status`, { id: select.dataset.id, status: select.value });
      await this.load();
      await refreshAfterMutation(this.closest(".section")?.id || null);
      showToast("وضعیت به‌روز شد.", "success");
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      select.disabled = false;
    }
  }
}

customElements.define("data-table", DataTable);

const recalcChargesButton = document.getElementById("recalcChargesButton");
if (recalcChargesButton && canWrite) {
  recalcChargesButton.addEventListener("click", async () => {
    const year = document.getElementById("chargesYear")?.value || "1404";
    if (!window.confirm(`شارژهای محاسبه‌شده خودکار سال ${year} از نرخ‌ها بازمحاسبه شود؟ ماه‌هایی که دستی ویرایش کرده‌اید حفظ می‌شوند.`)) return;
    recalcChargesButton.disabled = true;
    recalcChargesButton.classList.add("is-loading");
    recalcChargesButton.textContent = "در حال محاسبه…";
    try {
      await postJson("api.php?resource=recalculate-charges", { fiscal_year: year });
      await loadChargesCollage();
      await refreshAfterMutation("charges");
      showToast("محاسبه خودکار انجام شد.", "success");
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      recalcChargesButton.disabled = false;
      recalcChargesButton.classList.remove("is-loading");
      recalcChargesButton.textContent = "محاسبه خودکار از نرخ";
    }
  });
}

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    const rejectModal = document.getElementById("rejectModal");
    if (rejectModal && !rejectModal.hidden && rejectModal._pendingCancel) {
      rejectModal._pendingCancel();
      return;
    }
    closeModal();
    closeDrawer();
    return;
  }
  const modalOpen = document.getElementById("crudModal") && !document.getElementById("crudModal").hidden;
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s" && modalOpen) {
    event.preventDefault();
    document.getElementById("crudForm")?.requestSubmit();
    return;
  }
  if (event.key === "/" && !["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement?.tagName)) {
    event.preventDefault();
    const activeSection = document.querySelector(".section.active");
    activeSection?.querySelector("data-table .search")?.focus();
  }
});

window.addEventListener("resize", () => {
  syncMobileClass();
  document.querySelectorAll("data-table").forEach((table) => table.render?.());
});

syncMobileClass();
document.body.classList.add(panelMode === "team" ? "panel-team" : "panel-admin");
const hashSection = (location.hash || "").replace(/^#/, "");
const initialSection = hashSection && document.getElementById(hashSection) ? hashSection : "overview";
activateSection(initialSection);

window.addEventListener("hashchange", () => {
  const id = (location.hash || "").replace(/^#/, "");
  if (!id || !document.getElementById(id)) return;
  activateSection(id, { updateHash: false });
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Enter" && event.key !== " ") return;
  const card = event.target.closest(".card-clickable[data-nav-section]");
  if (!card || event.target !== card) return;
  event.preventDefault();
  activateSection(card.dataset.navSection);
});

const memberApprovalTabs = document.getElementById("memberApprovalTabs");
const membersTable = document.getElementById("membersTable");
if (memberApprovalTabs && membersTable) {
  memberApprovalTabs.addEventListener("click", (event) => {
    const tab = event.target.closest(".filter-tab[data-approval-filter]");
    if (!tab) return;
    memberApprovalTabs.querySelectorAll(".filter-tab").forEach((button) => {
      const active = button === tab;
      button.classList.toggle("active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });
    const filter = tab.dataset.approvalFilter || "";
    membersTable.setAttribute("data-approval-filter", filter);
    membersTable.approvalStatusFilter = filter;
    membersTable.page = 1;
    membersTable.load?.();
  });
}

loadDashboard().catch((error) => {
  const cards = document.getElementById("cards");
  if (cards) cards.innerHTML = `<article class="stat-card"><span class="stat-label">خطا</span><strong>${escapeHtml(error.message)}</strong></article>`;
});

document.getElementById("deskHistoryAddButton")?.addEventListener("click", () => {
  openDeskHistoryAssignModal().catch((error) => showToast(error.message, "error"));
});

window.MechinnoShared = {
  fetchJson,
  fetchResource,
  postJson,
  postForm,
  profileThumb,
  escapeHtml,
  formatMoney,
  formatPlain,
  formatNumber,
  showToast,
  canWrite,
  canTeamSubmit,
  csrfToken,
  panelMode,
  loadCrudMeta,
  openRecordModal,
  refreshAfterMutation,
  closeModal,
  ensureModal,
  trapFocus,
  releaseFocusTrap,
  activateSection,
  entityBadge,
  teamActiveBadge,
  usageLabels,
  labels,
  monthNames,
  formatMonthRange,
  normalizeDigits,
  monthIndexFromDate,
  validAssignmentMonth,
  fiscalYearFromDate,
  openDeskHistoryAssignModal,
  debugLog,
  profileSection,
  entityTypeLabels,
  openDepositModal,
  openChargeModal,
  loadDeskGrid,
  deskLink,
  teamBillingBadges,
  initReportBuilder,
  MECHINNO: window.MECHINNO,
};
