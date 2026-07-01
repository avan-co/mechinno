const labels = {
  id: "شناسه",
  entity_code: "کد نهاد",
  entity_type: "نوع",
  member_code: "کد عضو",
  access_code: "کد تردد",
  wants_access: "دسترسی تردد",
  contract_start: "شروع قرارداد",
  contract_end: "پایان قرارداد",
  full_name: "نام",
  team_id: "نهاد",
  team_label: "نهاد",
  team_name: "نهاد",
  name: "نام",
  leader: "مسئول",
  phone: "تماس",
  desk_count: "تعداد میز",
  informal_seats: "صندلی غیررسمی",
  assigned_from: "تاریخ شروع تخصیص",
  assigned_until: "تاریخ پایان تخصیص",
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
  fiscal_year: "سال مالی",
  month_index: "ماه",
  month_name: "ماه",
  charge_amount: "شارژ",
  rent_amount: "اجاره غیررسمی",
  amount: "مبلغ",
  note: "یادداشت",
  notes: "توضیحات",
  national_id: "کدملی",
  tx_date: "تاریخ",
  description: "شرح",
  category: "دسته",
  confirmed: "تأیید",
  charge_rate: "نرخ شارژ",
  informal_rent_rate: "نرخ اجاره غیررسمی",
  effective_from: "تاریخ اثر",
  joined_at: "عضویت",
  warning: "اخطار",
  portal_username: "نام کاربری نهاد",
  portal_password: "رمز ورود نهاد",
  role: "نقش",
  is_active: "فعال",
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
};

const entityTypeLabels = { team: "تیم", company: "شرکت", student: "دانشجو" };
const usageLabels = { formal: "رسمی", informal: "غیررسمی", mixed: "ترکیبی" };

const sectionMeta = {
  overview: { eyebrow: "داشبورد", title: "مدیریت مرکز نوآوری", subtitle: "خلاصه وضعیت مرکز و اقدامات پیشنهادی" },
  teams: { eyebrow: "نهادها", title: "تیم‌ها، شرکت‌ها و دانشجویان", subtitle: "ثبت و مدیریت نهادهای مستقر در مرکز" },
  members: { eyebrow: "اعضا", title: "اعضای نهادها", subtitle: "هر عضو به یک نهاد تعلق دارد — میزها در سطح نهاد تخصیص می‌یابند" },
  desks: { eyebrow: "میزها", title: "نقشه و تخصیص ۲۴ میز", subtitle: "میزها را به نهادها اختصاص دهید" },
  lockers: { eyebrow: "کمدها", title: "مدیریت کمدها", subtitle: "شماره کمدها را خودتان تعریف و تخصیص دهید" },
  charges: { eyebrow: "شارژ", title: "نرخ و شارژ ماهانه", subtitle: "تعریف نرخ سالانه، محاسبه خودکار و پیگیری پرداخت" },
  transactions: { eyebrow: "مالی", title: "دفتر معین و موجودی نقدی", subtitle: "گردش واقعی حساب مرکز — بدون تکرار شارژ سیستمی" },
  development: { eyebrow: "برنامه‌ریزی", title: "برنامه توسعه", subtitle: "ایده‌ها، اقدامات و برنامه اجرایی مرکز" },
  users: { eyebrow: "دسترسی", title: "کاربران پنل", subtitle: "مدیریت نقش‌ها و پنل اختصاصی نهادها" },
};

const teamSectionMeta = {
  overview: { eyebrow: "داشبورد نهاد", title: "وضعیت نهاد", subtitle: "خلاصه اعضا، میزها، کمدها و شارژ" },
  members: { eyebrow: "اعضا", title: "اعضای نهاد", subtitle: "لیست اعضای ثبت‌شده در نهاد شما" },
  desks: { eyebrow: "میزها", title: "میزهای نهاد", subtitle: "میزهای تخصیص‌یافته به نهاد" },
  lockers: { eyebrow: "کمدها", title: "کمدهای نهاد", subtitle: "درخواست کمد و کمدهای تخصیص‌یافته" },
  profile: { eyebrow: "پروفایل", title: "پروفایل نهاد", subtitle: "اطلاعات تکمیلی قرارداد و وضعیت نهاد" },
  charges: { eyebrow: "شارژ", title: "شارژ و پرداخت", subtitle: "لیست شارژ سالانه و وضعیت پرداخت" },
  payments: { eyebrow: "واریز", title: "اعلام واریز", subtitle: "ثبت واریز شارژ و پیگیری تأیید مدیر" },
};

const cardNavMap = {
  income_year: "transactions",
  income_month: "transactions",
  expense_year: "transactions",
  expense_month: "transactions",
  debt_total: "charges",
  pending_members: "members",
  pending_payments: "transactions",
  members: "members",
  desks: "desks",
  debt_total_team: "charges",
  charge_total: "charges",
  paid_total: "payments",
};

const adminCardConfig = [
  ["income_year", "درآمد سال جاری", "↓", "income"],
  ["income_month", "درآمد ماه جاری", "↓", "income"],
  ["expense_year", "هزینه سال جاری", "↑", "expense"],
  ["expense_month", "هزینه ماه جاری", "↑", "expense"],
  ["debt_total", "طلب از نهادها", "!", "debt"],
];

const teamCardConfig = [
  ["members", "اعضای فعال", "👤", "members"],
  ["desks", "میز", "▦", "desks"],
  ["charge_total", "مبلغ کل قرارداد", "📋", "charges"],
  ["debt_total", "مانده بدهی قرارداد", "!", "debt"],
  ["paid_total", "پرداخت‌شده", "✓", "paid"],
  ["pending_payments", "واریزهای در انتظار تأیید", "⏳", "payments"],
];

const cardConfig = adminCardConfig;

const moneyCards = new Set(["income_year", "income_month", "expense_year", "expense_month", "debt_total", "paid_total", "charge_total"]);

const monthNames = ["", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

const resourceColumns = {
  teams: ["entity_code", "entity_type", "name", "leader", "phone", "contract_start", "contract_end", "portal_username", "portal_password", "desk_count", "joined_at", "warning", "notes"],
  members: ["member_code", "full_name", "team_label", "entity_type", "desk_numbers", "wants_access", "access_code", "phone", "national_id", "approval_status", "rejection_reason"],
  desks: ["number", "team_name", "usage_type", "assignment_from", "assignment_until", "notes"],
  "desk-assignments": ["desk_number", "team_name", "usage_type", "assigned_from", "assigned_until", "notes"],
  lockers: ["locker_number", "status", "team_label", "delivered_at", "key_number", "spare_key"],
  "locker-requests": ["submitted_at", "status", "locker_number", "notes", "reviewed_at", "rejection_reason"],
  "pending-locker-requests": ["team_label", "submitted_at", "notes"],
  rate_settings: ["fiscal_year", "title", "charge_rate", "informal_rent_rate", "effective_from", "notes"],
  panel_users: ["username", "role", "full_name", "is_active"],
  charges: ["fiscal_year", "team_name", "month_name", "charge_amount", "rent_amount", "amount", "note"],
  transactions: ["tx_date", "description", "amount", "category", "team_name", "fiscal_year", "month_name", "payment_status", "payment_reference", "confirmed"],
  "pending-members": ["member_code", "full_name", "team_label", "phone", "national_id", "wants_access", "submitted_at"],
  "pending-payments": ["tx_date", "team_name", "fiscal_year", "month_name", "amount", "payment_reference", "announced_at", "notes", "description"],
  "payment-history": ["tx_date", "team_name", "fiscal_year", "month_name", "amount", "payment_status", "payment_reference", "announced_at", "reviewed_at", "notes"],
  development_plans: ["title", "category", "priority", "status", "due_date", "depends_on_title", "estimated_cost", "estimated_revenue", "related_section", "description", "notes", "sort_order"],
};

const teamPanelHiddenColumns = {
  members: ["team_label", "entity_type", "access_code"],
  desks: ["team_name"],
  lockers: ["team_label"],
  "locker-requests": ["team_label"],
  charges: ["team_name"],
  transactions: ["category", "team_name", "confirmed"],
  "payment-history": ["team_name"],
};

const createDefaults = {
  charges: () => ({ fiscal_year: window.MECHINNO?.fiscalYear || "" }),
  rate_settings: () => ({ fiscal_year: window.MECHINNO?.fiscalYear || "" }),
  transactions: () => ({
    fiscal_year: window.MECHINNO?.fiscalYear || "",
    month_index: String(window.MECHINNO?.monthIndex || 1),
    tx_date: window.MECHINNO?.today || "",
    confirmed: "1",
  }),
  development_plans: () => ({ category: "idea", priority: "medium", status: "open", sort_order: "0" }),
  members: () => ({ wants_access: "0" }),
  "locker-requests": () => ({}),
};
const csrfToken = window.MECHINNO?.csrfToken || "";
const canWrite = window.MECHINNO?.canWrite === true;
const canTeamSubmit = window.MECHINNO?.canTeamSubmit === true;
const canMutate = canWrite || canTeamSubmit;
const panelMode = window.MECHINNO?.panel || "admin";

const editableResources = new Set(
  canWrite
    ? ["members", "teams", "desks", "lockers", "charges", "transactions", "rate_settings", "panel_users", "development_plans"]
    : canTeamSubmit
      ? ["members", "transactions", "locker-requests"]
      : []
);
const workflowQueueResources = new Set([
  "pending-members",
  "pending-payments",
  "pending-locker-requests",
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
  if (!definition || !editableResources.has(resource)) return false;
  return true;
};

const tableAllowsEdit = (table, definition = null) => {
  const resource = table.resource || "";
  if (table.getAttribute("data-workflow") || workflowQueueResources.has(resource)) return false;
  if (table.hasAttribute("data-readonly")) return false;
  if (panelMode === "team" && teamReadOnlyResources.has(resource)) return false;
  if (!canWrite || !definition || !editableResources.has(resource)) return false;
  return true;
};
const hiddenColumns = new Set([
  "id", "source_sheet", "source_file", "team_id", "locker_id", "member_id",
  "row_index", "col_index", "created_at", "entity_type",
  "row_number", "lockers", "power_strips", "rent_rate",
]);
const plainColumns = new Set([
  "phone", "national_id", "access_code", "member_code", "entity_code",
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
  const host = document.getElementById("toastHost");
  if (!host) return;
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  host.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("show"));
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 220);
  }, 3200);
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

const formatNumber = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  const maybe = Number(value);
  if (!Number.isNaN(maybe) && String(value).trim() !== "") return maybe.toLocaleString("fa-IR");
  return value;
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  return `${Number(value).toLocaleString("fa-IR")} ریال`;
};

const fetchJson = async (url, options = {}) => {
  const response = await fetch(url, options);
  const contentType = response.headers.get("content-type") || "";
  const data = contentType.includes("application/json") ? await response.json() : { error: await response.text() };
  if (!response.ok) throw new Error(data.error || `Request failed: ${url}`);
  return data;
};

const fetchResource = async (endpoint, { page = 1, perPage = 25, category = "", paymentStatus = "" } = {}) => {
  const url = new URL(endpoint, window.location.href);
  url.searchParams.set("page", String(page));
  url.searchParams.set("per_page", String(perPage));
  if (category) url.searchParams.set("category", category);
  if (paymentStatus) url.searchParams.set("payment_status", paymentStatus);
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

const postJson = (url, payload = {}) =>
  fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
    body: JSON.stringify(payload),
  });

const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

const syncMobileClass = () => {
  document.body.classList.toggle("is-mobile", isMobile());
};

const updatePageHeader = (sectionId) => {
  const metaSource = panelMode === "team" ? teamSectionMeta : sectionMeta;
  const meta = metaSource[sectionId] || metaSource.overview;
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

const refreshAfterMutation = async (sectionId = null) => {
  invalidateCrudMeta();
  if (sectionId) reloadSectionTables(sectionId, true);
  try {
    await loadDashboard();
  } catch (error) {
    showToast(error.message, "error");
  }
  if (!sectionId || sectionId === "transactions") {
    if (panelMode === "admin" && document.getElementById("ledgerPanel")) {
      loadLedger().catch(() => {});
    }
  }
  document.querySelectorAll("data-table").forEach((table) => {
    if (!sectionId || table.closest(`#${sectionId}`)) return;
    table.load?.();
  });
};

const closeDrawer = () => {
  document.getElementById("sidebar")?.classList.remove("open");
  document.getElementById("sidebarBackdrop")?.setAttribute("hidden", "");
};

const openDrawer = () => {
  document.getElementById("sidebar")?.classList.add("open");
  document.getElementById("sidebarBackdrop")?.removeAttribute("hidden");
};

const activateSection = (id, options = {}) => {
  if (options.highlightDesk !== undefined) highlightDesk = options.highlightDesk;
  if (options.highlightLocker !== undefined) highlightLocker = options.highlightLocker;

  document.querySelectorAll(".section").forEach((s) => s.classList.toggle("active", s.id === id));
  document.querySelectorAll(".nav-item, .bottom-nav-item").forEach((i) => {
    i.classList.toggle("active", i.dataset.section === id);
  });

  updatePageHeader(id);
  closeDrawer();
  reloadSectionTables(id);

  if (id === "desks" && panelMode === "admin") loadDeskGrid().catch((error) => showToast(error.message, "error"));
  if (id === "desks" && panelMode === "team") loadTeamDeskAssignments().catch((error) => showToast(error.message, "error"));
  if (id === "profile" && panelMode === "team") loadTeamProfile().catch((error) => showToast(error.message, "error"));
  if (id === "charges") loadChargesCollage().catch((error) => showToast(error.message, "error"));
  if (id === "development") {
    loadDevProgramSummary().catch(() => {});
    loadDevKanban().catch(() => {});
  }
  if (id === "transactions" && canWrite) loadPaymentSettings().catch(() => {});
  if (id === "transactions" && panelMode === "admin") loadLedger().catch((error) => showToast(error.message, "error"));
  if (id === "payments" && panelMode === "team") loadPaymentGuide().catch(() => {});
  if (options.scrollTarget) {
    setTimeout(() => {
      document.querySelector(`data-table[data-table-key="${options.scrollTarget}"]`)
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
    }, 180);
  }
  if (id === "teams" && options.teamId) {
    setTimeout(() => openTeamProfile(options.teamId).catch((error) => showToast(error.message, "error")), 120);
  }
};

document.querySelectorAll(".nav-item, .bottom-nav-item").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.section));
});

document.getElementById("menuToggle")?.addEventListener("click", openDrawer);
document.getElementById("sidebarBackdrop")?.addEventListener("click", closeDrawer);

document.querySelectorAll(".start-step[data-go]").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.go));
});

document.getElementById("themeToggle")?.addEventListener("click", () => {
  const html = document.documentElement;
  const next = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
  html.setAttribute("data-theme", next);
  try {
    localStorage.setItem("mechinno-theme", next);
  } catch (e) {}
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
  activateSection(link.dataset.navSection, {
    highlightDesk: link.dataset.highlightDesk ? Number(link.dataset.highlightDesk) : undefined,
    highlightLocker: link.dataset.highlightLocker ? Number(link.dataset.highlightLocker) : undefined,
    teamId: link.dataset.openTeam ? Number(link.dataset.openTeam) : undefined,
    scrollTarget: link.dataset.scrollTarget || undefined,
  });
});

const resolveCardSection = (key) => {
  if (panelMode === "team" && key === "pending_payments") return "payments";
  return cardNavMap[key] || "members";
};

const renderCards = (cards, config = cardConfig) => {
  const container = document.getElementById("cards");
  if (!container) return;
  container.innerHTML = config
    .map(([key, title, icon, tone]) => {
      let value = cards?.[key];
      if (key === "desks" && panelMode === "team" && cards?.desk_numbers) value = cards.desk_numbers || "—";
      else if (key === "desks" && panelMode === "team") value = formatNumber(cards?.desks ?? value);
      else if (moneyCards.has(key)) value = formatMoney(value);
      else value = formatNumber(value);
      const section = resolveCardSection(key);
      return `<article class="stat-card stat-card--${tone} card-clickable" data-nav-section="${section}" tabindex="0" role="button">
        <span class="stat-icon" aria-hidden="true">${icon}</span>
        <div><span class="stat-label">${escapeHtml(title)}</span><strong>${escapeHtml(value ?? "—")}</strong></div>
      </article>`;
    })
    .join("");
};

const renderCurrentMonth = (month) => {
  const label = document.getElementById("currentMonthLabel");
  const container = document.getElementById("currentMonthSummary");
  if (!month || !container) return;
  if (label) label.textContent = `${month.month_name} ${month.fiscal_year}`;
  const debtLabel = panelMode === "team" ? "مانده ماه" : "مانده طلب ماه";
  container.innerHTML = `
    <div class="month-stat"><span>شارژ ماه</span><strong>${escapeHtml(formatMoney(month.charge_total))}</strong></div>
    <div class="month-stat"><span>واریز ماه</span><strong>${escapeHtml(formatMoney(month.paid_total))}</strong></div>
    <div class="month-stat"><span>${escapeHtml(debtLabel)}</span><strong class="debt-value">${escapeHtml(formatMoney(month.debt_total))}</strong></div>
    ${panelMode === "admin" ? `<div class="month-stat"><span>نهاد بدهکار به مرکز</span><strong>${escapeHtml(formatNumber(month.debtor_count))}</strong></div>` : ""}`;
};

const renderActionItems = (items) => {
  const container = document.getElementById("actionItems");
  if (!container) return;
  if (!items?.length) {
    container.innerHTML = `<div class="empty">همه‌چیز مرتب است — مورد فوری نیست.</div>`;
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
  container.innerHTML = source.map((row) => `
    <div class="bar-row ${compact ? "bar-row--compact" : ""}">
      <span>${escapeHtml(row.fiscal_year)} ${escapeHtml(row.month_name)}</span>
      <div class="bar-track"><div class="bar-fill" style="width:${(Number(row.amount || 0) / max) * 100}%"></div></div>
      <strong>${escapeHtml(formatMoney(row.amount))}</strong>
    </div>`).join("") || `<div class="empty">داده‌ای موجود نیست.</div>`;
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
    : `<div class="empty">مطالبه ثبت‌شده‌ای از نهادها نیست.</div>`;
};

const loadDashboard = async () => {
  const data = await fetchJson("api.php?resource=summary");
  if (panelMode === "team") {
    renderTeamDashboard(data);
    return;
  }
  renderCurrentMonth(data.current_month || {});
  renderActionItems(data.action_items || []);
  renderCards(data.cards || {});
  renderChargeChart(data.monthly_charges || []);
  renderDebtChart(data.debt_by_team || []);
  const welcome = document.getElementById("welcomePanel");
  if (welcome) welcome.hidden = Number(data.cards?.teams || 0) > 0;
};

const renderRecentApprovals = (items) => {
  const container = document.getElementById("recentApprovals");
  if (!container) return;
  if (!items?.length) {
    container.innerHTML = `<div class="empty">هنوز تأیید یا ردی از مرکز ثبت نشده است.</div>`;
    return;
  }
  container.innerHTML = items.map((item) => {
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
};

const renderTeamDashboard = (data) => {
  const cards = document.getElementById("cards");
  const team = data.team || {};
  if (cards) {
    renderCards({ ...data.cards, desk_numbers: data.cards?.desk_numbers || "—" }, teamCardConfig);
  }
  renderCurrentMonth(data.current_month || {});
  renderRecentApprovals(data.recent_approvals || []);
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

const loadLedger = async () => {
  const summaryBody = document.getElementById("ledgerSummaryBody");
  const tableBody = document.getElementById("ledgerTableBody");
  const billingWrap = document.getElementById("ledgerBillingWrap");
  const billingBody = document.getElementById("ledgerBillingBody");
  if (!summaryBody || !tableBody) return;

  const data = await fetchJson("api.php?resource=ledger");
  const totals = data.totals || {};
  const billing = data.billing || {};
  const balance = Number(totals.balance ?? data.balance ?? 0);

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
    tableBody.innerHTML = `<tr><td colspan="7" class="empty">هنوز گردش نقدی ثبت نشده است.</td></tr>`;
    return;
  }

  tableBody.innerHTML = [...rows].reverse().map((row) => {
    const signed = Number(row.signed_amount ?? row.amount ?? 0);
    const debit = signed < 0 ? formatMoney(Math.abs(signed)) : "—";
    const credit = signed > 0 ? formatMoney(signed) : "—";
    return `<tr>
      <td class="num">${escapeHtml(String(row.line_no ?? "—"))}</td>
      <td>${escapeHtml(formatPlain(row.tx_date))}</td>
      <td>${escapeHtml(row.entry_type_label || entryTypeLabel(row.entry_type))}</td>
      <td class="ledger-desc">${escapeHtml(row.description || "—")}</td>
      <td class="num ledger-income">${escapeHtml(credit)}</td>
      <td class="num ledger-expense">${escapeHtml(debit)}</td>
      <td class="num ledger-balance-cell">${escapeHtml(formatMoney(row.running_balance ?? 0))}</td>
    </tr>`;
  }).join("");
};

const loadTeamDeskAssignments = async () => {
  const host = document.getElementById("teamDeskAssignments");
  if (!host) return;
  const { rows } = await fetchResource("api.php?resource=desk-assignments", { page: 1, perPage: 100 });
  if (!rows.length) {
    host.innerHTML = `<div class="empty">هنوز میزی به نهاد شما تخصیص داده نشده است.</div>`;
    return;
  }
  host.innerHTML = `<div class="desk-assignment-grid">${rows.map((row) => `
    <article class="desk-assignment-card">
      <strong>میز ${escapeHtml(row.desk_number)}</strong>
      <span class="badge">${escapeHtml(usageLabels[row.usage_type] || row.usage_type || "—")}</span>
      <div class="desk-assignment-dates">
        <span>از ${escapeHtml(formatPlain(row.assigned_from))}</span>
        <span>${row.assigned_until ? `تا ${escapeHtml(formatPlain(row.assigned_until))}` : "فعال"}</span>
      </div>
      ${row.notes ? `<p class="hint">${escapeHtml(row.notes)}</p>` : ""}
    </article>`).join("")}</div>`;
};

const loadTeamProfile = async () => {
  const host = document.getElementById("teamProfileContent");
  if (!host || !window.MECHINNO?.teamId) return;
  const data = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(window.MECHINNO.teamId)}`);
  const team = data.team || {};
  host.innerHTML = `
    <div class="profile-summary team-profile-grid">
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
    </div>
    ${team.warning ? `<p class="hint warning-text">اخطار: ${escapeHtml(team.warning)}</p>` : ""}
    ${team.notes ? `<p class="hint">${escapeHtml(team.notes)}</p>` : ""}
    ${profileSection("میزها و تاریخ تخصیص", data.desk_assignments || [], ["desk_number", "usage_type", "assigned_from", "assigned_until", "notes"])}
    ${profileSection("اعضا", data.members || [], ["full_name", "wants_access", "phone", "national_id", "approval_status"])}
    ${profileSection("کمدهای تخصیص‌یافته", data.lockers || [], ["locker_number", "status", "delivered_at"])}
    ${profileSection("درخواست‌های کمد", data.locker_requests || [], ["submitted_at", "status", "locker_number", "notes"])}`;
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
          </article>`).join("") : `<div class="empty">خالی</div>`}
      </div>
    </div>`;
  }).join("");
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
        <li>مبلغ شارژ ماه را از بخش «شارژ و پرداخت» ببینید.</li>
        <li>مبلغ را به حساب بالا واریز کنید.</li>
        <li>در جدول «اعلام‌های در انتظار تأیید»، واریز را با مبلغ، تاریخ، سال و ماه ثبت کنید.</li>
        <li>پس از تأیید مرکز، در سوابق پرداخت تأییدشده نمایش داده می‌شود.</li>
      </ol>
      ${data.payment_guide ? `<p class="payment-guide-note" dir="rtl">${escapeHtml(data.payment_guide)}</p>` : ""}
    </div>`;
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
    const digits = String(value).replace(/\D/g, "");
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
    desks = (await fetchResource("api.php?resource=desks", { page: 1, perPage: 100 })).rows;
  }
  const container = document.getElementById("deskGrid");
  if (!container) return;
  if (!desks.length) {
    container.innerHTML = `<div class="empty">نقشه میزها بارگذاری نشد.</div>`;
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
          const highlighted = highlightDesk === Number(desk.number) || (isTeamMap && isOwn);
          const tileClass = isTeamMap
            ? (isOwn ? "occupied own-desk" : foreign ? "occupied foreign-desk" : "free")
            : (occupied ? "occupied" : "free");
          let meta = `<span class="desk-meta">بدون نهاد</span>`;
          if (occupied) {
            if (isTeamMap && foreign) {
              meta = `<span class="desk-meta">نهاد دیگر</span>`;
            } else if (isTeamMap && isOwn) {
              meta = `<span class="desk-meta">${escapeHtml(desk.team_name || "نهاد شما")}</span>`;
            } else if (!isTeamMap && desk.team_id) {
              meta = `<span class="desk-meta"><span role="button" tabindex="0" class="text-link-inline" data-team-id="${escapeHtml(desk.team_id)}">${escapeHtml(desk.team_name || "نهاد")}</span></span>`;
            }
          }
          return `<button type="button" class="desk-tile ${tileClass} ${highlighted ? "highlighted" : ""}"
            data-nav-section="desks" data-highlight-desk="${desk.number}">
            <span class="desk-num">${desk.number}</span>
            <span class="desk-status">${occupied ? "اشغال" : "آزاد"}</span>
            ${meta}
            <span class="desk-badge">${escapeHtml(usageLabels[desk.usage_type] || desk.usage_type || "—")}</span>
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

const loadChargesCollage = async () => {
  const yearSelect = document.getElementById("chargesYear");
  if (!yearSelect) return;
  if (!yearSelect.dataset.ready) {
    yearSelect.dataset.ready = "1";
    yearSelect.addEventListener("change", () => loadChargesCollage().catch((error) => showToast(error.message, "error")));
  }
  let rateRows = [];
  if (panelMode !== "team") {
    try {
      const rateData = await fetchResource("api.php?resource=rate_settings", { page: 1, perPage: 100 });
      rateRows = rateData.rows;
    } catch (error) {
      rateRows = [];
    }
  }
  const { rows: chargeRows } = await fetchResource("api.php?resource=charges", { page: 1, perPage: 200 });
  const yearSet = new Set([
    window.MECHINNO?.fiscalYear || "1404",
    ...rateRows.map((r) => String(r.fiscal_year || "")),
    ...chargeRows.map((r) => String(r.fiscal_year || "")),
  ]);
  if (panelMode === "team" && window.MECHINNO?.teamId) {
    try {
      const profile = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(window.MECHINNO.teamId)}`);
      const team = profile.team || {};
      if (team.contract_start) yearSet.add(String(team.contract_start).slice(0, 4));
      if (team.contract_end) yearSet.add(String(team.contract_end).slice(0, 4));
      (profile.charges || []).forEach((row) => yearSet.add(String(row.fiscal_year || "")));
    } catch (error) {
      // ignore profile year enrichment errors
    }
  }
  const years = [...yearSet].filter(Boolean).sort((a, b) => Number(b) - Number(a));
  const current = yearSelect.value || window.MECHINNO?.fiscalYear || years[0] || "1404";
  yearSelect.innerHTML = years.map((y) => `<option value="${escapeHtml(y)}">${escapeHtml(y)}</option>`).join("");
  yearSelect.value = years.includes(current) ? current : years[0];
  const year = yearSelect.value || window.MECHINNO?.fiscalYear || "1404";
  const data = await fetchJson(`api.php?resource=charges-matrix&fiscal_year=${encodeURIComponent(year)}`);
  const container = document.getElementById("chargesCollage");
  if (!data.rows?.length) {
    const emptyMessage = panelMode === "team"
      ? "برای این سال شارژی ثبت نشده است."
      : "داده شارژی برای این سال نیست — ابتدا نهاد و نرخ تعریف کنید.";
    container.innerHTML = `<div class="empty">${escapeHtml(emptyMessage)}</div>`;
    return;
  }
  const head = panelMode === "team"
    ? `<tr>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`
    : `<tr><th class="team-col">نهاد</th>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`;
  const body = data.rows.map((row) => `
    <tr>
      ${panelMode === "team" ? "" : `<td class="team-col">
        <button type="button" class="text-link" data-team-id="${escapeHtml(row.team.id)}">${escapeHtml(row.team.name)}</button>
        <br>${entityBadge(row.team.entity_type)}
      </td>`}
      ${row.cells.map((cell) => {
        const cls = cell.status === "پرداخت‌شده" ? "cell-paid"
          : cell.status === "ناقص" ? "cell-partial"
            : cell.status === "بدهکار به مرکز" ? "cell-debt"
              : cell.status === "خارج از قرارداد" ? "cell-outside"
                : "cell-empty";
        const clickable = canWrite && (cell.status === "بدهکار به مرکز" || cell.status === "ناقص");
        const attrs = clickable
          ? `class="${cls} cell-clickable" role="button" tabindex="0"
             data-team-id="${row.team.id}" data-team-name="${escapeHtml(row.team.name)}"
             data-fiscal-year="${escapeHtml(year)}" data-month-index="${cell.month_index}"
             data-month-name="${escapeHtml(data.months.find((m) => m.index === cell.month_index)?.name || "")}"
             data-amount-due="${cell.amount_due}" data-amount-paid="${cell.amount_paid}"`
          : `class="${cls}"`;
        return `<td ${attrs} title="شارژ: ${formatMoney(cell.charge_amount)} | اجاره: ${formatMoney(cell.rent_amount)}">
          ${cell.amount_due > 0 ? `<div>${escapeHtml(formatMoney(cell.amount_paid))}</div><small>از ${escapeHtml(formatMoney(cell.amount_due))}</small>` : "—"}
        </td>`;
      }).join("")}
    </tr>`).join("");
  container.innerHTML = `<table class="collage-table"><thead>${head}</thead><tbody>${body}</tbody></table>`;

  container.querySelectorAll(".cell-clickable").forEach((cell) => {
    const handler = () => openDepositModal({
      teamId: Number(cell.dataset.teamId),
      teamName: cell.dataset.teamName,
      fiscalYear: cell.dataset.fiscalYear,
      monthIndex: Number(cell.dataset.monthIndex),
      monthName: cell.dataset.monthName,
      amountDue: Number(cell.dataset.amountDue),
      amountPaid: Number(cell.dataset.amountPaid),
    });
    cell.addEventListener("click", handler);
    cell.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handler();
      }
    });
  });
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
          if (["amount", "charge_amount", "rent_amount"].includes(c)) return `<td>${escapeHtml(formatMoney(value))}</td>`;
          if (c === "usage_type") return `<td>${usageLabels[value] || value || "—"}</td>`;
          if (c === "wants_access") return `<td>${accessStatusLabel(row)}</td>`;
          if (c === "approval_status") return `<td>${approvalStatusBadge(value)}</td>`;
          if (c === "number") return `<td>${deskLink(value)}</td>`;
          if (plainColumns.has(c)) return `<td>${escapeHtml(formatPlain(value))}</td>`;
          return `<td>${escapeHtml(formatNumber(value ?? "—"))}</td>`;
        }).join("")}</tr>`).join("")
        : `<tr><td colspan="${cols.length}">داده‌ای موجود نیست.</td></tr>`}
      </tbody>
    </table>
  </div>`;

const openTeamProfile = async (teamId) => {
  const data = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = `پروفایل نهاد: ${data.team.name || "—"}`;
  const deskList = (data.desks || []).map((d) => d.number).join("، ") || "—";
  form.innerHTML = `
    <div class="profile-summary">
      <div><span>نوع</span><strong>${entityBadge(data.team.entity_type)}</strong></div>
      <div><span>مسئول</span><strong>${escapeHtml(data.team.leader || "—")}</strong></div>
      <div><span>میزها</span><strong>${escapeHtml(deskList)}</strong></div>
      <div><span>جمع شارژ</span><strong>${escapeHtml(formatMoney(data.summary.charge_total || 0))}</strong></div>
      <div><span>دریافت از نهاد</span><strong>${escapeHtml(formatMoney(data.summary.paid_total || 0))}</strong></div>
      <div><span>مانده بدهی قرارداد</span><strong class="debt-value">${escapeHtml(formatMoney(data.summary.debt_total || 0))}</strong></div>
    </div>
    ${canWrite ? `<div class="profile-actions">
      <button type="button" class="button" data-profile-action="add-member">افزودن عضو</button>
      <button type="button" class="button ghost" data-profile-action="deposit">ثبت دریافت شارژ</button>
      <button type="button" class="button ghost" data-profile-action="charges">مشاهده شارژ</button>
      <button type="button" class="button ghost" data-profile-action="desks">مدیریت میزها</button>
    </div>` : `<div class="profile-actions">
      <button type="button" class="button ghost" data-profile-action="charges">مشاهده شارژ</button>
      <button type="button" class="button ghost" data-profile-action="desks">مشاهده میزها</button>
    </div>`}
    ${profileSection("میزهای نهاد", data.desks, ["number", "usage_type", "notes"])}
    ${profileSection("تاریخچه تخصیص میز", data.desk_assignments || [], ["desk_number", "usage_type", "assigned_from", "assigned_until"])}
    ${profileSection("اعضا", data.members, ["member_code", "full_name", "access_code", "phone", "national_id"])}
    ${profileSection("کمدها", data.lockers, ["locker_number", "status", "delivered_at", "key_number"], (column, row) => {
      if (column === "locker_number") return lockerLink(row.locker_number);
      if (column === "status") return lockerStatusBadge(row.status);
      return null;
    })}
    ${profileSection("شارژها", data.charges, ["fiscal_year", "month_name", "charge_amount", "rent_amount", "amount"])}
    ${profileSection("دریافت شارژ از نهاد", data.payments, ["tx_date", "fiscal_year", "month_name", "amount"])}
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
        openDepositModal({
          teamId,
          teamName: data.team.name,
          fiscalYear: window.MECHINNO?.fiscalYear || "1404",
          monthIndex: window.MECHINNO?.monthIndex || 1,
          monthName: "",
          amountDue: Number(data.summary.debt_total || 0),
          amountPaid: 0,
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
  if (modal) modal.hidden = true;
};

const ltrFields = new Set([
  "access_code", "phone", "national_id", "portal_username", "portal_password",
  "account_number", "card_number", "sheba", "payment_reference", "entity_code", "member_code",
]);

const fieldInput = (name, meta, value) => {
  const type = meta.type || "text";
  const required = meta.required ? "required" : "";
  const placeholder = meta.placeholder ? `placeholder="${escapeHtml(meta.placeholder)}"` : "";
  const safeValue = value ?? "";
  const ltr = ltrFields.has(name) ? 'dir="ltr" class="ltr-input"' : "";

  if (type === "textarea") {
    return `<textarea name="${escapeHtml(name)}" ${required} ${placeholder}>${escapeHtml(safeValue)}</textarea>`;
  }
  if (type === "select") {
    const options = meta.options || {};
    const entries = Array.isArray(options) ? options.map((o) => [o, o]) : Object.entries(options);
    return `<select name="${escapeHtml(name)}" ${required}>
      <option value="">انتخاب کنید</option>
      ${entries.map(([optionValue, optionLabel]) => {
        const selected = String(optionValue) === String(safeValue) ? "selected" : "";
        return `<option value="${escapeHtml(optionValue)}" ${selected}>${escapeHtml(optionLabel)}</option>`;
      }).join("")}
    </select>`;
  }
  if (type === "number") {
    return `<input name="${escapeHtml(name)}" type="number" value="${escapeHtml(safeValue)}" ${required} ${placeholder} ${ltr} />`;
  }
  if (type === "password") {
    return `<input name="${escapeHtml(name)}" type="password" value="" ${required} autocomplete="new-password" placeholder="برای تغییر وارد کنید" ${ltr} />`;
  }
  return `<input name="${escapeHtml(name)}" type="text" value="${escapeHtml(safeValue)}" ${required} ${placeholder} ${ltr} />`;
};

const openRecordModal = ({ resource, definition, record = null, onSaved, title = null }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  const isEdit = Boolean(record?.id);
  const formRecord = { ...(record || {}) };
  if (!isEdit && createDefaults[resource]) {
    Object.assign(formRecord, createDefaults[resource]());
  }
  modal.querySelector("#crudModalTitle").textContent = title || `${isEdit ? "ویرایش" : "افزودن"} ${definition.title}`;
  form.innerHTML = `
    <div class="crud-grid">
      ${Object.entries(definition.fields).map(([name, meta]) => `
        <label class="${meta.type === "textarea" ? "wide" : ""}">
          <span>${escapeHtml(meta.label)}${meta.required ? " *" : ""}</span>
          ${fieldInput(name, meta, formRecord[name] ?? "")}
        </label>`).join("")}
    </div>
    <div class="modal-actions">
      <button class="button" type="submit">${isEdit ? "ذخیره" : "ثبت"}</button>
      <button class="button ghost" type="button" data-close-modal>انصراف</button>
    </div>`;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  form.onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      if (isEdit) payload.id = record.id;
      await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=${isEdit ? "update" : "create"}`, payload);
      closeModal();
      await onSaved();
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      submitButton.disabled = false;
    }
  };
  modal.hidden = false;
};

const devCategoryLabels = { idea: "ایده", action: "اقدام", planned: "برنامه‌ریزی‌شده" };
const devStatusLabels = { open: "باز", in_progress: "در حال اجرا", done: "انجام‌شده", cancelled: "لغو‌شده" };
const devPriorityLabels = { high: "بالا", medium: "متوسط", low: "پایین" };
const relatedSectionLabels = {
  teams: "نهادها", members: "اعضا", desks: "میزها", lockers: "کمدها", charges: "شارژ", transactions: "مالی",
};

const workflowApprove = async (resource, id, row = {}, workflowType = "") => {
  if (resource === "pending-members" || workflowType === "member-approve") {
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id });
    return;
  }

  if (resource === "pending-locker-requests" || workflowType === "locker-request") {
    const lockerNumber = window.prompt("شماره کمد برای تخصیص:", "");
    if (lockerNumber === null) return;
    const parsed = Number(String(lockerNumber).replace(/[^\d]/g, ""));
    if (!parsed) {
      throw new Error("شماره کمد معتبر نیست.");
    }
    await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, {
      id,
      locker_number: parsed,
    });
    return;
  }

  await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=approve`, { id });
};

const workflowReject = async (resource, id, reason = "") => {
  await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=reject`, { id, reason });
};

const askRejectReason = () => new Promise((resolve, reject) => {
  let modal = document.getElementById("rejectModal");
  if (!modal) {
    document.body.insertAdjacentHTML("beforeend", `
      <div id="rejectModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-labelledby="rejectModalTitle">
          <div class="modal-head">
            <h2 id="rejectModalTitle">رد درخواست</h2>
            <button class="modal-close" type="button" data-reject-cancel aria-label="بستن">×</button>
          </div>
          <label class="wide"><span>دلیل رد (اختیاری)</span><textarea id="rejectReasonInput" rows="3" placeholder="دلیل رد را بنویسید…"></textarea></label>
          <div class="form-actions">
            <button type="button" class="button ghost" data-reject-cancel>انصراف</button>
            <button type="button" class="button danger" data-reject-confirm>رد کردن</button>
          </div>
        </div>
      </div>`);
    modal = document.getElementById("rejectModal");
    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        modal.hidden = true;
        reject(new Error("cancelled"));
      }
    });
  }

  const input = modal.querySelector("#rejectReasonInput");
  input.value = "";
  modal.hidden = false;
  input.focus();

  const cleanup = () => {
    modal.hidden = true;
    modal.querySelectorAll("[data-reject-cancel]").forEach((btn) => { btn.onclick = null; });
    modal.querySelector("[data-reject-confirm]").onclick = null;
  };

  modal.querySelectorAll("[data-reject-cancel]").forEach((btn) => {
    btn.onclick = () => {
      cleanup();
      reject(new Error("cancelled"));
    };
  });
  modal.querySelector("[data-reject-confirm]").onclick = () => {
    const reason = input.value.trim();
    cleanup();
    resolve(reason);
  };
});

const openFinanceModal = async (category) => {
  const meta = await loadCrudMeta();
  const base = meta.resources.transactions;
  const fieldKeys = category === "هزینه"
    ? ["tx_date", "description", "amount", "notes"]
    : ["tx_date", "description", "amount", "notes"];
  const fields = Object.fromEntries(fieldKeys.map((key) => [key, base.fields[key]]));
  if (category === "هزینه") {
    fields.amount = { label: "مبلغ هزینه (ریال)", type: "number", required: true };
  } else {
    fields.amount = { label: "مبلغ درآمد (ریال)", type: "number", required: true };
  }
  openRecordModal({
    resource: "transactions",
    definition: { title: category === "هزینه" ? "ثبت هزینه" : "ثبت درآمد دستی", fields },
    record: {
      category,
      confirmed: "1",
      tx_date: window.MECHINNO?.today || "",
      amount: "",
      description: "",
    },
    onSaved: async () => {
      await refreshAfterMutation("transactions");
      showToast(category === "هزینه" ? "هزینه ثبت شد." : "درآمد ثبت شد.", "success");
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
  if (column === "entity_type") return entityBadge(value);
  if (column === "usage_type") return escapeHtml(usageLabels[value] || value || "—");
  if (column === "category" && resource === "development_plans") {
    return escapeHtml(devCategoryLabels[value] || value || "—");
  }
  if (column === "category") {
    if (value === "واریز تیم") return "دریافت از نهاد";
    return escapeHtml(value || "—");
  }
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
  if (column === "role") {
    const map = { admin_editor: "مدیر — ویرایش", admin_viewer: "مدیر — مشاهده", team: "نهاد" };
    return escapeHtml(map[value] || value || "—");
  }
  if (column === "is_active") return Number(value) === 1 ? "فعال" : "غیرفعال";
  if (column === "password") return "—";
  if (column === "status" && resource === "lockers") return lockerStatusBadge(value);
  if (column === "status" && (resource === "locker-requests" || resource === "pending-locker-requests")) {
    const map = { pending: "در انتظار", approved: "تأیید‌شده", rejected: "رد‌شده" };
    const label = map[value] || value || "—";
    return `<span class="badge">${escapeHtml(label)}</span>`;
  }
  if (linkColumns[column] && row[linkColumns[column]] && value) {
    if (column === "name" && resource === "teams") {
      return `<button type="button" class="text-link" data-team-id="${escapeHtml(row.id)}">${escapeHtml(value)}</button>`;
    }
    return teamLink(row[linkColumns[column]], value);
  }
  if (column === "full_name" && resource === "members" && row.team_id && panelMode === "admin") {
    return `${escapeHtml(value || "—")} <small>${teamLink(row.team_id, "پروفایل نهاد")}</small>`;
  }
  if (column === "desk_numbers" && value) {
    return String(value).split(",").filter(Boolean).map((n) => deskLink(n.trim())).join(" ");
  }
  if (column === "locker_number" && value) return lockerLink(value);
  if (column === "number" && resource === "desks") return deskLink(value);
  if (["amount", "charge_amount", "rent_amount", "charge_rate", "informal_rent_rate"].includes(column)) {
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

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.resource = new URL(this.endpoint, window.location.href).searchParams.get("resource");
    this.workflow = this.getAttribute("data-workflow") || "";
    this.workflowType = this.getAttribute("data-workflow-type") || "";
    this.noAdd = this.hasAttribute("data-no-add") || this.hasAttribute("data-readonly");
    this.txCategoryFilter = this.getAttribute("data-tx-filter") || "";
    this.paymentStatusFilter = this.getAttribute("data-payment-filter") || "";
    this.tableKey = this.getAttribute("data-table-key") || "";
    this.readOnly = tableSuppressesAdd(this);
    this.definition = null;
    this.rows = [];
    this.page = 1;
    this.perPage = 25;
    this.total = 0;
    this.pages = 1;
    this.filter = "";
    const addButtonHtml = this.readOnly
      ? ""
      : `<button class="button add-button" type="button">+ افزودن</button>`;
    this.innerHTML = `
      <article class="panel data-panel">
        <div class="table-toolbar">
          <h2>${escapeHtml(this.title)}</h2>
          <div class="table-actions">
            ${addButtonHtml}
            <input class="search" type="search" placeholder="جست‌وجو... ( / )" />
          </div>
        </div>
        <div class="table-wrap"><div class="empty">در حال بارگذاری...</div></div>
        <div class="mobile-cards"></div>
        <div class="table-pagination" hidden>
          <span class="pager-info"></span>
          <div class="pager-buttons">
            <label>تعداد
              <select class="per-page-select">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </label>
            <button class="mini-button pager-prev" type="button">قبلی</button>
            <button class="mini-button pager-next" type="button">بعدی</button>
          </div>
        </div>
      </article>`;
    this.querySelector(".search").addEventListener("input", (e) => {
      const next = e.target.value;
      const hadFilter = Boolean(this.filter.trim());
      const hasFilter = Boolean(next.trim());
      this.filter = next;
      if (!hadFilter && hasFilter) {
        this.searchPerPage = this.perPage;
        this.perPage = 100;
        this.page = 1;
        this.load();
        return;
      }
      if (hadFilter && !hasFilter && this.searchPerPage) {
        this.perPage = this.searchPerPage;
        this.searchPerPage = null;
        this.page = 1;
        this.load();
        return;
      }
      this.render();
    });
    this.querySelector(".add-button")?.addEventListener("click", () => {
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
    this.load();
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
      });
      this.rows = result.rows;
      this.total = result.total;
      this.page = result.page;
      this.perPage = result.per_page;
      this.pages = result.pages;
      this.render();
      this.renderPager();
    } catch (error) {
      this.querySelector(".table-wrap").innerHTML = `<div class="empty">خطا: ${escapeHtml(error.message)}</div>`;
      this.querySelector(".table-pagination").hidden = true;
    }
  }

  renderPager() {
    const pager = this.querySelector(".table-pagination");
    if (!pager) return;
    pager.hidden = this.total <= this.perPage && this.pages <= 1;
    pager.querySelector(".pager-info").textContent =
      `صفحه ${this.page.toLocaleString("fa-IR")} از ${this.pages.toLocaleString("fa-IR")} — ${this.total.toLocaleString("fa-IR")} رکورد`;
    const perPageSelect = pager.querySelector(".per-page-select");
    if (perPageSelect) perPageSelect.value = String(this.perPage);
    pager.querySelector(".pager-prev").disabled = this.page <= 1;
    pager.querySelector(".pager-next").disabled = this.page >= this.pages;
  }

  filteredRows() {
    const normalized = this.filter.trim().toLowerCase();
    if (!normalized) return this.rows;
    return this.rows.filter((row) => JSON.stringify(row).toLowerCase().includes(normalized));
  }

  renderMobileCards(rows) {
    const container = this.querySelector(".mobile-cards");
    if (!isMobile()) return false;
    const columns = rows.length ? resolveColumns(rows, this.resource).slice(0, 6) : [];
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
             ${canWrite ? `<button class="mini-button" type="button" data-action="reset-portal" data-id="${escapeHtml(row.id)}">بازنشانی رمز</button>` : ""}` : "";
        const workflowBtns = workflow
          ? `<button class="mini-button primary" type="button" data-action="approve" data-id="${escapeHtml(row.id)}">تأیید</button>
             <button class="mini-button danger" type="button" data-action="reject" data-id="${escapeHtml(row.id)}">رد</button>` : "";
        const editBtns = editable
          ? `<button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
             <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : "";
        return `<article class="mobile-card ${highlighted ? "highlighted" : ""}">${fields}
          <div class="row-actions">${profileBtn}${workflowBtns}${editBtns}</div></article>`;
      }).join("")
      : `<div class="empty">رکوردی یافت نشد.</div>`;
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
      wrap.innerHTML = `<div class="empty">رکوردی یافت نشد.${cta}</div>`;
      wrap.querySelector(".add-inline")?.addEventListener("click", () => this.querySelector(".add-button")?.click());
      if (mobile) mobile.innerHTML = "";
      return;
    }

    const columns = resolveColumns(rows, this.resource);
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
        if (column === "amount") {
          className = Number(value) < 0 ? "money-negative" : Number(value) > 0 ? "money-positive" : "";
        }
        return `<td class="${className}">${formatCell(column, value, row, this.resource)}</td>`;
      }).join("");
      const profileAction = this.resource === "teams"
        ? `<button class="mini-button primary" type="button" data-action="profile" data-id="${escapeHtml(row.id)}">پروفایل</button>
           ${canWrite ? `<button class="mini-button" type="button" data-action="reset-portal" data-id="${escapeHtml(row.id)}">بازنشانی رمز</button>` : ""}` : "";
      const workflowAction = workflow
        ? `<button class="mini-button primary" type="button" data-action="approve" data-id="${escapeHtml(row.id)}">تأیید</button>
           <button class="mini-button danger" type="button" data-action="reject" data-id="${escapeHtml(row.id)}">رد</button>`
        : "";
      const actions = editable || profileAction || workflowAction
        ? `<td class="row-actions">${profileAction}${workflowAction}${editable ? `
        <button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
        <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : ""}</td>` : "";
      return `<tr class="${rowHighlight ? "highlighted" : ""}">${cells}${actions}</tr>`;
    }).join("");
    const hasActions = editable || this.resource === "teams" || workflow;
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
        showToast(error.message, "error");
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
    if (button.dataset.action === "reset-portal") {
      if (!canWrite) return;
      if (!window.confirm("رمز ورود این نهاد بازنشانی شود؟")) return;
      try {
        const result = await postJson("api.php?resource=teams&action=reset-portal-password", { id });
        await this.load();
        showToast(`رمز جدید: ${result.credentials?.password || "—"}`, "success");
      } catch (error) {
        showToast(error.message, "error");
      }
      return;
    }
    if (!this.definition) return;
    if (button.dataset.action === "edit") {
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
      if (!window.confirm("حذف شود؟")) return;
      await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=delete`, { id });
      await this.load();
      await refreshAfterMutation(this.closest(".section")?.id || null);
      showToast("حذف شد.", "success");
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
    if (!window.confirm(`شارژهای محاسبه‌شده خودکار سال ${year} از نرخ‌ها بازمحاسبه شود؟`)) return;
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
updatePageHeader("overview");
loadDashboard().catch((error) => {
  const cards = document.getElementById("cards");
  if (cards) cards.innerHTML = `<article class="stat-card"><span class="stat-label">خطا</span><strong>${escapeHtml(error.message)}</strong></article>`;
});
