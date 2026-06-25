const labels = {
  id: "شناسه",
  entity_code: "کد نهاد",
  entity_type: "نوع",
  member_code: "کد عضو",
  access_code: "کد تردد",
  full_name: "نام",
  team_id: "نهاد",
  team_label: "نهاد",
  team_name: "نهاد",
  name: "نام",
  leader: "مسئول",
  phone: "تماس",
  desk_count: "تعداد میز",
  informal_seats: "صندلی غیررسمی",
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
  transactions: { eyebrow: "مالی", title: "درآمد، هزینه و واریز", subtitle: "ثبت تراکنش‌ها و واریز شارژ تیم‌ها" },
};

const cardNavMap = {
  members: "members",
  teams: "teams",
  desks_occupied: "desks",
  charge_total: "charges",
  income_total: "transactions",
  expense_total: "transactions",
  debt_total: "charges",
  paid_total: "transactions",
  available_lockers: "lockers",
};

const cardConfig = [
  ["members", "اعضا", "👤", "members"],
  ["teams", "نهادها", "◉", "teams"],
  ["desks_occupied", "میز اشغال", "▦", "desks"],
  ["charge_total", "جمع شارژ", "₪", "charge"],
  ["income_total", "دریافتی", "↓", "income"],
  ["expense_total", "هزینه", "↑", "expense"],
  ["debt_total", "بدهی", "!", "debt"],
  ["paid_total", "واریز تیم", "✓", "paid"],
  ["available_lockers", "کمد آزاد", "▣", "lockers"],
];

const moneyCards = new Set(["charge_total", "income_total", "expense_total", "debt_total", "paid_total"]);

const resourceColumns = {
  teams: ["entity_code", "entity_type", "name", "leader", "phone", "desk_count", "informal_seats", "joined_at", "warning"],
  members: ["member_code", "full_name", "team_label", "entity_type", "desk_numbers", "access_code", "locker_number", "phone"],
  desks: ["number", "team_name", "usage_type", "formal_seats", "informal_seats"],
  lockers: ["locker_number", "status", "team_label", "member_name", "delivered_at", "key_number", "spare_key"],
  rate_settings: ["fiscal_year", "title", "charge_rate", "informal_rent_rate", "effective_from", "notes"],
  charges: ["fiscal_year", "team_name", "month_name", "charge_amount", "rent_amount", "amount", "note"],
  transactions: ["tx_date", "description", "amount", "category", "team_name", "fiscal_year", "month_index", "confirmed"],
};

const editableResources = new Set(["members", "teams", "desks", "lockers", "charges", "transactions", "rate_settings"]);
const hiddenColumns = new Set([
  "source_sheet", "source_file", "team_id", "locker_id", "member_id",
  "row_index", "col_index", "created_at", "entity_type",
]);

const linkColumns = {
  team_label: "team_id",
  team_name: "team_id",
  name: "id",
};

const csrfToken = window.MECHINNO?.csrfToken || "";
let crudMetaPromise = null;
let highlightDesk = null;
let highlightLocker = null;
let txCategoryFilter = "";

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

const fetchResource = async (endpoint, { page = 1, perPage = 25 } = {}) => {
  const url = new URL(endpoint, window.location.href);
  url.searchParams.set("page", String(page));
  url.searchParams.set("per_page", String(perPage));
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
  const meta = sectionMeta[sectionId] || sectionMeta.overview;
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
  await loadDashboard();
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

  if (id === "desks") loadDeskGrid().catch((error) => showToast(error.message, "error"));
  if (id === "charges") loadChargesCollage().catch((error) => showToast(error.message, "error"));
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

document.getElementById("txCategoryFilter")?.addEventListener("change", (event) => {
  txCategoryFilter = event.target.value;
  document.querySelector("#transactions data-table")?.render?.();
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
  });
});

const renderCards = (cards) => {
  const container = document.getElementById("cards");
  if (!container) return;
  container.innerHTML = cardConfig
    .map(([key, title, icon, tone]) => {
      let value = cards?.[key];
      if (key === "desks_occupied") value = `${cards?.desks_occupied || 0} / ${cards?.desks_total || 24}`;
      else if (moneyCards.has(key)) value = formatMoney(value);
      else value = formatNumber(value);
      const section = cardNavMap[key];
      return `<article class="stat-card stat-card--${tone} card-clickable" data-nav-section="${section}" tabindex="0" role="button">
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
  container.innerHTML = `
    <div class="month-stat"><span>شارژ ماه</span><strong>${escapeHtml(formatMoney(month.charge_total))}</strong></div>
    <div class="month-stat"><span>واریز ماه</span><strong>${escapeHtml(formatMoney(month.paid_total))}</strong></div>
    <div class="month-stat"><span>بدهی باقی‌مانده</span><strong class="debt-value">${escapeHtml(formatMoney(month.debt_total))}</strong></div>
    <div class="month-stat"><span>نهاد بدهکار</span><strong>${escapeHtml(formatNumber(month.debtor_count))}</strong></div>`;
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
      ${item.team_id ? `data-open-team="${escapeHtml(item.team_id)}"` : ""}>
      <strong>${escapeHtml(item.label)}</strong>
      <span>${escapeHtml(item.detail || "")}</span>
    </button>`).join("");
};

const renderChargeChart = (rows) => {
  const container = document.getElementById("chargeChart");
  if (!container) return;
  const max = Math.max(...rows.map((r) => Number(r.amount || 0)), 1);
  container.innerHTML = rows.slice(-10).map((row) => `
    <div class="bar-row">
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
    : `<div class="empty">بدهی ثبت‌شده‌ای نیست.</div>`;
};

const loadDashboard = async () => {
  const data = await fetchJson("api.php?resource=summary");
  renderCurrentMonth(data.current_month || {});
  renderActionItems(data.action_items || []);
  renderCards(data.cards || {});
  renderChargeChart(data.monthly_charges || []);
  renderDebtChart(data.debt_by_team || []);
  const welcome = document.getElementById("welcomePanel");
  if (welcome) welcome.hidden = Number(data.cards?.teams || 0) > 0;
};

const loadDeskGrid = async () => {
  const { rows: desks } = await fetchResource("api.php?resource=desks", { page: 1, perPage: 100 });
  const container = document.getElementById("deskGrid");
  if (!container) return;
  const rows = { 1: [], 2: [], 3: [] };
  desks.forEach((desk) => rows[desk.row_index]?.push(desk));
  container.innerHTML = [1, 2, 3].map((rowIndex) => `
    <div class="desk-row-block">
      <div class="desk-row-label">ردیف ${rowIndex}</div>
      <div class="desk-row">
        ${(rows[rowIndex] || []).sort((a, b) => a.col_index - b.col_index).map((desk) => {
          const occupied = Boolean(desk.team_id);
          const highlighted = highlightDesk === Number(desk.number);
          return `<button type="button" class="desk-tile ${occupied ? "occupied" : "free"} ${highlighted ? "highlighted" : ""}"
            data-nav-section="desks" data-highlight-desk="${desk.number}">
            <span class="desk-num">${desk.number}</span>
            <span class="desk-status">${occupied ? "اشغال" : "آزاد"}</span>
            ${occupied
              ? `<span class="desk-meta"><span role="button" tabindex="0" class="text-link-inline" data-team-id="${escapeHtml(desk.team_id)}">${escapeHtml(desk.team_name || "نهاد")}</span></span>`
              : `<span class="desk-meta">بدون نهاد</span>`}
            <span class="desk-badge">${escapeHtml(usageLabels[desk.usage_type] || desk.usage_type || "—")}</span>
            <span class="desk-meta">ر:${desk.formal_seats || 0} · غ:${desk.informal_seats || 0}</span>
          </button>`;
        }).join("")}
      </div>
    </div>`).join("");

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
};

const loadChargesCollage = async () => {
  const yearSelect = document.getElementById("chargesYear");
  if (!yearSelect) return;
  if (!yearSelect.options.length) {
    [window.MECHINNO?.fiscalYear || "1404", "1405"].filter((v, i, a) => a.indexOf(v) === i).forEach((y) => {
      yearSelect.insertAdjacentHTML("beforeend", `<option value="${y}">${y}</option>`);
    });
    yearSelect.addEventListener("change", () => loadChargesCollage().catch((error) => showToast(error.message, "error")));
  }
  const year = yearSelect.value || window.MECHINNO?.fiscalYear || "1404";
  const data = await fetchJson(`api.php?resource=charges-matrix&fiscal_year=${encodeURIComponent(year)}`);
  const container = document.getElementById("chargesCollage");
  if (!data.rows?.length) {
    container.innerHTML = `<div class="empty">داده شارژی برای این سال نیست — ابتدا نهاد و نرخ تعریف کنید.</div>`;
    return;
  }
  const head = `<tr><th class="team-col">نهاد</th>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`;
  const body = data.rows.map((row) => `
    <tr>
      <td class="team-col">
        <button type="button" class="text-link" data-team-id="${escapeHtml(row.team.id)}">${escapeHtml(row.team.name)}</button>
        <br>${entityBadge(row.team.entity_type)}
      </td>
      ${row.cells.map((cell) => {
        const cls = cell.status === "پرداخت‌شده" ? "cell-paid" : cell.status === "ناقص" ? "cell-partial" : cell.status === "بدهکار" ? "cell-debt" : "cell-empty";
        const clickable = cell.status === "بدهکار" || cell.status === "ناقص";
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
          if (c === "number") return `<td>${deskLink(value)}</td>`;
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
      <div><span>واریز</span><strong>${escapeHtml(formatMoney(data.summary.paid_total || 0))}</strong></div>
      <div><span>بدهی</span><strong class="debt-value">${escapeHtml(formatMoney(data.summary.debt_total || 0))}</strong></div>
    </div>
    <div class="profile-actions">
      <button type="button" class="button" data-profile-action="add-member">افزودن عضو</button>
      <button type="button" class="button ghost" data-profile-action="deposit">ثبت واریز</button>
      <button type="button" class="button ghost" data-profile-action="charges">مشاهده شارژ</button>
      <button type="button" class="button ghost" data-profile-action="desks">مدیریت میزها</button>
    </div>
    ${profileSection("میزهای نهاد", data.desks, ["number", "usage_type", "formal_seats", "informal_seats"])}
    ${profileSection("اعضا", data.members, ["full_name", "access_code", "locker_number", "phone"])}
    ${profileSection("کمدها", data.lockers, ["locker_number", "status", "delivered_at", "key_number"], (column, row) => {
      if (column === "locker_number") return lockerLink(row.locker_number);
      if (column === "status") return lockerStatusBadge(row.status);
      return null;
    })}
    ${profileSection("شارژها", data.charges, ["fiscal_year", "month_name", "charge_amount", "rent_amount", "amount"])}
    ${profileSection("واریز تیم", data.payments, ["tx_date", "fiscal_year", "month_index", "amount"])}
    <div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>`;

  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  form.querySelectorAll("[data-profile-action]").forEach((button) => {
    button.addEventListener("click", async () => {
      const action = button.dataset.profileAction;
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
  const monthNames = ["", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
  const resolvedMonthName = monthName || monthNames[monthIndex] || "";
  openRecordModal({
    resource: "transactions",
    definition,
    title: `ثبت واریز — ${teamName}`,
    record: {
      category: "واریز تیم",
      team_id: String(teamId),
      fiscal_year: fiscalYear,
      month_index: String(monthIndex),
      amount: remaining || amountDue,
      description: `واریز شارژ ${resolvedMonthName} ${fiscalYear}`,
      tx_date: window.MECHINNO?.today || "",
      confirmed: "1",
    },
    onSaved: async () => {
      await refreshAfterMutation("transactions");
      await loadChargesCollage();
      showToast("واریز ثبت شد.", "success");
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

const fieldInput = (name, meta, value) => {
  const type = meta.type || "text";
  const required = meta.required ? "required" : "";
  const placeholder = meta.placeholder ? `placeholder="${escapeHtml(meta.placeholder)}"` : "";
  const safeValue = value ?? "";

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
  return `<input name="${escapeHtml(name)}" type="${type === "number" ? "number" : "text"}" value="${escapeHtml(safeValue)}" ${required} ${placeholder} />`;
};

const openRecordModal = ({ resource, definition, record = null, onSaved, title = null }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  const isEdit = Boolean(record?.id);
  modal.querySelector("#crudModalTitle").textContent = title || `${isEdit ? "ویرایش" : "افزودن"} ${definition.title}`;
  form.innerHTML = `
    <div class="crud-grid">
      ${Object.entries(definition.fields).map(([name, meta]) => `
        <label class="${meta.type === "textarea" ? "wide" : ""}">
          <span>${escapeHtml(meta.label)}${meta.required ? " *" : ""}</span>
          ${fieldInput(name, meta, record ? record[name] : "")}
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

const formatCell = (column, value, row, resource) => {
  if (column === "entity_type") return entityBadge(value);
  if (column === "usage_type") return escapeHtml(usageLabels[value] || value || "—");
  if (column === "confirmed") return Number(value) === 1 ? "بله" : "خیر";
  if (column === "status" && resource === "lockers") return lockerStatusBadge(value);
  if (linkColumns[column] && row[linkColumns[column]] && value) {
    if (column === "name" && resource === "teams") {
      return `<button type="button" class="text-link" data-team-id="${escapeHtml(row.id)}">${escapeHtml(value)}</button>`;
    }
    return teamLink(row[linkColumns[column]], value);
  }
  if (column === "full_name" && resource === "members" && row.team_id) {
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
  return formatNumber(value);
};

const resolveColumns = (rows, resource) => {
  if (!rows.length) return [];
  const preferred = resourceColumns[resource] || [];
  const available = Object.keys(rows[0]).filter((c) => !hiddenColumns.has(c));
  const ordered = preferred.filter((c) => available.includes(c));
  const rest = available.filter((c) => !ordered.includes(c));
  return [...ordered, ...rest];
};

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.resource = new URL(this.endpoint, window.location.href).searchParams.get("resource");
    this.definition = null;
    this.rows = [];
    this.page = 1;
    this.perPage = 25;
    this.total = 0;
    this.pages = 1;
    this.filter = "";
    this.innerHTML = `
      <article class="panel data-panel">
        <div class="table-toolbar">
          <h2>${escapeHtml(this.title)}</h2>
          <div class="table-actions">
            <button class="button add-button" type="button" hidden>+ افزودن</button>
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
      this.filter = e.target.value;
      this.render();
    });
    this.querySelector(".add-button").addEventListener("click", () => {
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
      this.definition = meta.resources[this.resource] || null;
      this.querySelector(".add-button").hidden = !this.definition || !editableResources.has(this.resource);
      const result = await fetchResource(this.endpoint, { page: this.page, perPage: this.perPage });
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
    let rows = this.rows;
    if (this.resource === "transactions" && txCategoryFilter) {
      rows = rows.filter((row) => row.category === txCategoryFilter);
    }
    const normalized = this.filter.trim().toLowerCase();
    if (normalized) {
      rows = rows.filter((row) => JSON.stringify(row).toLowerCase().includes(normalized));
    }
    return rows;
  }

  renderMobileCards(rows) {
    const container = this.querySelector(".mobile-cards");
    if (!isMobile()) return false;
    const columns = rows.length ? resolveColumns(rows, this.resource).slice(0, 6) : [];
    const editable = this.definition && editableResources.has(this.resource);
    container.innerHTML = rows.length
      ? rows.map((row) => {
        const highlighted = this.resource === "lockers" && highlightLocker === Number(row.locker_number);
        const fields = columns.map((column) => `
          <div class="mobile-card-field">
            <span>${escapeHtml(labels[column] || column)}</span>
            <strong>${formatCell(column, row[column], row, this.resource)}</strong>
          </div>`).join("");
        const profileBtn = this.resource === "teams"
          ? `<button class="mini-button primary" type="button" data-action="profile" data-id="${escapeHtml(row.id)}">پروفایل</button>` : "";
        const editBtns = editable
          ? `<button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
             <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : "";
        return `<article class="mobile-card ${highlighted ? "highlighted" : ""}">${fields}
          <div class="row-actions">${profileBtn}${editBtns}</div></article>`;
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
      const cta = this.definition && editableResources.has(this.resource)
        ? `<div class="empty-state-cta"><button class="button add-inline" type="button">+ افزودن اولین رکورد</button></div>` : "";
      wrap.innerHTML = `<div class="empty">رکوردی یافت نشد.${cta}</div>`;
      wrap.querySelector(".add-inline")?.addEventListener("click", () => this.querySelector(".add-button")?.click());
      if (mobile) mobile.innerHTML = "";
      return;
    }

    const columns = resolveColumns(rows, this.resource);
    const editable = this.definition && editableResources.has(this.resource);
    const statusField = this.definition?.status_field;
    const statusOptions = this.definition?.status_options || [];
    const head = columns.map((c) => `<th>${escapeHtml(labels[c] || c)}</th>`).join("");
    const body = rows.map((row) => {
      const rowHighlight = (this.resource === "lockers" && highlightLocker === Number(row.locker_number))
        || (this.resource === "desks" && highlightDesk === Number(row.number));
      const cells = columns.map((column) => {
        const value = row[column];
        if (editable && column === statusField && statusOptions.length) {
          const options = statusOptions.map((o) => `<option value="${escapeHtml(o)}" ${String(o) === String(value) ? "selected" : ""}>${escapeHtml(o)}</option>`).join("");
          return `<td><select class="inline-status" data-id="${escapeHtml(row.id)}">${options}</select></td>`;
        }
        let className = "";
        if (column === "amount") {
          className = Number(value) < 0 ? "money-negative" : Number(value) > 0 ? "money-positive" : "";
        }
        return `<td class="${className}">${formatCell(column, value, row, this.resource)}</td>`;
      }).join("");
      const profileAction = this.resource === "teams"
        ? `<button class="mini-button primary" type="button" data-action="profile" data-id="${escapeHtml(row.id)}">پروفایل</button>` : "";
      const actions = editable || profileAction
        ? `<td class="row-actions">${profileAction}${editable ? `
        <button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
        <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>` : ""}</td>` : "";
      return `<tr class="${rowHighlight ? "highlighted" : ""}">${cells}${actions}</tr>`;
    }).join("");
    wrap.innerHTML = `<table><thead><tr>${head}${editable || this.resource === "teams" ? "<th>عملیات</th>" : ""}</tr></thead><tbody>${body}</tbody></table>`;
    if (mobile) mobile.innerHTML = "";
  }

  async handleClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button || !this.contains(button)) return;
    const id = Number(button.dataset.id);
    const record = this.rows.find((row) => Number(row.id) === id);
    if (!record) return;
    if (button.dataset.action === "profile") {
      openTeamProfile(id).catch((error) => showToast(error.message, "error"));
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
if (recalcChargesButton) {
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
