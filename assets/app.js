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
  desk_numbers: "میزها",
  number: "شماره میز",
  row_index: "ردیف",
  col_index: "ستون",
  usage_type: "نوع استفاده",
  formal_seats: "صندلی رسمی",
  informal_seats: "صندلی غیررسمی",
  locker_number: "شماره کمد",
  locker_id: "کمد",
  member_id: "عضو",
  member_name: "عضو",
  status: "وضعیت",
  delivered_at: "تاریخ تحویل",
  key_number: "شماره کلید",
  spare_key: "کلید یدک",
  plan_code: "کد برنامه",
  title: "عنوان",
  priority: "اولویت",
  owner_team: "تیم مجری",
  owner_team_id: "تیم مجری",
  proposed_budget: "بودجه",
  start_date: "شروع",
  end_date: "پایان",
  progress: "پیشرفت",
  fiscal_year: "سال",
  month_index: "ماه",
  month_name: "ماه",
  charge_amount: "شارژ",
  rent_amount: "اجاره غیررسمی",
  amount: "مبلغ",
  amount_due: "بدهی",
  amount_paid: "پرداخت",
  note: "یادداشت",
  notes: "توضیحات",
  national_id: "کدملی",
  tx_date: "تاریخ",
  description: "شرح",
  category: "دسته",
  confirmed: "تأیید",
  charge_rate: "نرخ شارژ",
  informal_rent_rate: "نرخ اجاره غیررسمی",
  joined_at: "عضویت",
  warning: "اخطار",
  created_at: "تاریخ",
  reason: "دلیل",
  summary: "خلاصه",
  source_file: "منبع",
};

const entityTypeLabels = { team: "تیم", company: "شرکت", student: "دانشجو" };
const usageLabels = { formal: "رسمی", informal: "غیررسمی", mixed: "ترکیبی" };

const csrfToken = window.MECHINNO?.csrfToken || "";
let crudMetaPromise = null;

const editableResources = new Set(["members", "teams", "desks", "lockers", "plans", "transactions", "rate_settings", "team_rates"]);

const hiddenColumns = new Set([
  "source_sheet", "source_file", "team_id", "owner_team_id", "locker_id", "member_id", "row_index", "col_index",
]);

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const loadCrudMeta = () => {
  if (!crudMetaPromise) crudMetaPromise = fetchJson("api.php?resource=crud-meta");
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

const postJson = (url, payload = {}) =>
  fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
    body: JSON.stringify(payload),
  });

const activateSection = (id) => {
  document.querySelectorAll(".section").forEach((s) => s.classList.toggle("active", s.id === id));
  document.querySelectorAll(".nav-item").forEach((i) => i.classList.toggle("active", i.dataset.section === id));
  if (id === "desks") loadDeskGrid().catch(console.error);
  if (id === "charges") loadChargesCollage().catch(console.error);
};

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.section));
});

const cardDefinitions = [
  ["members", "اعضا"],
  ["teams", "نهادها"],
  ["desks_occupied", "میز اشغال"],
  ["charge_total", "جمع شارژ"],
  ["income_total", "دریافتی"],
  ["expense_total", "هزینه"],
  ["debt_total", "بدهی"],
  ["paid_total", "واریز تیم"],
  ["available_lockers", "کمد آزاد"],
];

const moneyCards = new Set(["charge_total", "income_total", "expense_total", "debt_total", "paid_total"]);

const renderCards = (cards) => {
  document.getElementById("cards").innerHTML = cardDefinitions
    .map(([key, title]) => {
      let value = cards[key];
      if (key === "desks_occupied") value = `${cards.desks_occupied || 0} / ${cards.desks_total || 24}`;
      else if (moneyCards.has(key)) value = formatMoney(value);
      else value = formatNumber(value);
      return `<article class="card"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong></article>`;
    })
    .join("");
};

const renderStatus = (id, rows, labelKey = "status", valueKey = "count", isMoney = false) => {
  const container = document.getElementById(id);
  container.innerHTML = rows.length
    ? rows.map((row) => `<div class="status-row"><span>${escapeHtml(row[labelKey] || "—")}</span><strong>${escapeHtml(isMoney ? formatMoney(row[valueKey]) : formatNumber(row[valueKey]))}</strong></div>`).join("")
    : `<div class="empty">داده‌ای موجود نیست.</div>`;
};

const renderChargeChart = (rows) => {
  const container = document.getElementById("chargeChart");
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
  const max = Math.max(...rows.map((r) => Number(r.debt || 0)), 1);
  container.innerHTML = rows.length
    ? rows.map((row) => `
      <div class="bar-row">
        <span>${escapeHtml(row.team_name || "—")}</span>
        <div class="bar-track danger-track"><div class="bar-fill danger-fill" style="width:${(Number(row.debt || 0) / max) * 100}%"></div></div>
        <strong>${escapeHtml(formatMoney(row.debt))}</strong>
      </div>`).join("")
    : `<div class="empty">بدهی ثبت‌شده‌ای نیست.</div>`;
};

const renderFinanceChart = (rows) => {
  const container = document.getElementById("financeChart");
  const max = Math.max(...rows.flatMap((r) => [Number(r.income || 0), Number(r.expense || 0)]), 1);
  container.innerHTML = rows.length
    ? rows.slice(-8).map((row) => `
      <div class="double-bar">
        <span>${escapeHtml(row.period || "—")}</span>
        <div class="bar-track"><div class="bar-fill success-fill" style="width:${(Number(row.income || 0) / max) * 100}%"></div></div>
        <div class="bar-track"><div class="bar-fill danger-fill" style="width:${(Number(row.expense || 0) / max) * 100}%"></div></div>
      </div>`).join("")
    : `<div class="empty">داده مالی نیست.</div>`;
};

const renderOccupancy = (stats) => {
  document.getElementById("occupancyChart").innerHTML = `
    <div class="metric"><span>میز اشغال</span><strong>${formatNumber(stats.desks_occupied || 0)} / 24</strong></div>
    <div class="metric"><span>میز آزاد</span><strong>${formatNumber(stats.desks_free || 0)}</strong></div>
    <div class="metric"><span>کمد تخصیص‌یافته</span><strong>${formatNumber(stats.lockers_assigned || 0)}</strong></div>
    <div class="metric"><span>ظرفیت صندلی غیررسمی</span><strong>${formatNumber(stats.desks_occupied || 0)} میز</strong></div>`;
};

const loadDashboard = async () => {
  const data = await fetchJson("api.php?resource=summary");
  renderCards(data.cards);
  renderChargeChart(data.monthly_charges || []);
  renderDebtChart(data.debt_by_team || []);
  renderFinanceChart(data.finance_monthly || []);
  renderOccupancy(data.occupancy || {});
  renderStatus("lockerStatus", data.locker_status || []);
};

const loadDeskGrid = async () => {
  const desks = await fetchJson("api.php?resource=desks");
  const container = document.getElementById("deskGrid");
  const rows = { 1: [], 2: [], 3: [] };
  desks.forEach((desk) => rows[desk.row_index]?.push(desk));
  container.innerHTML = [1, 2, 3].map((rowIndex) => `
    <div>
      <div class="desk-row-label">ردیف ${rowIndex}</div>
      <div class="desk-row">
        ${(rows[rowIndex] || []).sort((a, b) => a.col_index - b.col_index).map((desk) => {
          const occupied = Boolean(desk.team_id);
          return `<div class="desk-tile ${occupied ? "occupied" : "free"}">
            <strong>${desk.number}</strong>
            <span>${occupied ? escapeHtml(desk.team_name || "اشغال") : "آزاد"}</span>
            <span class="badge">${escapeHtml(usageLabels[desk.usage_type] || desk.usage_type)}</span>
            <span>ر:${desk.formal_seats || 0} غ:${desk.informal_seats || 0}</span>
          </div>`;
        }).join("")}
      </div>
    </div>`).join("");
};

const loadChargesCollage = async () => {
  const yearSelect = document.getElementById("chargesYear");
  if (!yearSelect.options.length) {
    ["1404", "1405"].forEach((y) => yearSelect.insertAdjacentHTML("beforeend", `<option value="${y}">${y}</option>`));
    yearSelect.addEventListener("change", () => loadChargesCollage().catch(console.error));
  }
  const year = yearSelect.value || "1404";
  const data = await fetchJson(`api.php?resource=charges-matrix&fiscal_year=${encodeURIComponent(year)}`);
  const container = document.getElementById("chargesCollage");
  if (!data.rows?.length) {
    container.innerHTML = `<div class="empty">داده شارژی برای این سال نیست.</div>`;
    return;
  }
  const head = `<tr><th class="team-col">نهاد</th>${data.months.map((m) => `<th>${escapeHtml(m.name)}</th>`).join("")}</tr>`;
  const body = data.rows.map((row) => `
    <tr>
      <td class="team-col">${escapeHtml(row.team.name)}<br><small>${escapeHtml(entityTypeLabels[row.team.entity_type] || "")}</small></td>
      ${row.cells.map((cell) => {
        const cls = cell.status === "پرداخت‌شده" ? "cell-paid" : cell.status === "ناقص" ? "cell-partial" : cell.status === "بدهکار" ? "cell-debt" : "cell-empty";
        return `<td class="${cls}" title="شارژ: ${formatMoney(cell.charge_amount)} | اجاره: ${formatMoney(cell.rent_amount)}">
          ${cell.amount_due > 0 ? `<div>${escapeHtml(formatMoney(cell.amount_paid))}</div><small>از ${escapeHtml(formatMoney(cell.amount_due))}</small>` : "—"}
        </td>`;
      }).join("")}
    </tr>`).join("");
  container.innerHTML = `<table class="collage-table"><thead>${head}</thead><tbody>${body}</tbody></table>`;
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
  if (type === "multi_select") {
    const options = meta.options || {};
    const selected = Array.isArray(safeValue) ? safeValue.map(String) : String(safeValue || "").split(",").filter(Boolean);
    return `<select name="${escapeHtml(name)}" multiple ${required}>
      ${Object.entries(options).map(([optionValue, optionLabel]) => {
        const isSelected = selected.includes(String(optionValue)) ? "selected" : "";
        return `<option value="${escapeHtml(optionValue)}" ${isSelected}>${escapeHtml(optionLabel)}</option>`;
      }).join("")}
    </select>`;
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

const openRecordModal = ({ resource, definition, record = null, onSaved }) => {
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  const isEdit = Boolean(record?.id);
  modal.querySelector("#crudModalTitle").textContent = `${isEdit ? "ویرایش" : "افزودن"} ${definition.title}`;
  form.innerHTML = `
    <div class="crud-grid">
      ${Object.entries(definition.fields).map(([name, meta]) => `
        <label class="${meta.type === "textarea" || meta.type === "multi_select" ? "wide" : ""}">
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
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      const multi = form.querySelector('select[multiple][name="desk_ids"]');
      if (multi) payload.desk_ids = Array.from(multi.selectedOptions).map((o) => o.value);
      if (isEdit) payload.id = record.id;
      await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=${isEdit ? "update" : "create"}`, payload);
      closeModal();
      await onSaved();
      await loadDashboard();
    } catch (error) {
      alert(error.message);
    } finally {
      submitButton.disabled = false;
    }
  };
  modal.hidden = false;
};

const formatCell = (column, value, row) => {
  if (column === "entity_type") return entityTypeLabels[value] || value;
  if (column === "usage_type") return usageLabels[value] || value;
  if (column === "confirmed") return Number(value) === 1 ? "بله" : "خیر";
  if (column === "progress") {
    const pct = Math.min(100, Math.max(0, Number(value || 0)));
    return `${pct}%<div class="progress-bar"><span style="width:${pct}%"></span></div>`;
  }
  if (["amount", "proposed_budget", "charge_amount", "rent_amount", "charge_rate", "informal_rent_rate"].includes(column)) {
    return formatMoney(value);
  }
  return formatNumber(value);
};

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.resource = new URL(this.endpoint, window.location.href).searchParams.get("resource");
    this.definition = null;
    this.rows = [];
    this.innerHTML = `
      <article class="panel" style="margin-top:14px">
        <div class="table-toolbar">
          <h2>${this.title}</h2>
          <div class="table-actions">
            <button class="button add-button" type="button" hidden>افزودن</button>
            <input class="search" placeholder="جست‌وجو..." />
          </div>
        </div>
        <div class="table-wrap"><div class="empty">در حال بارگذاری...</div></div>
      </article>`;
    this.querySelector(".search").addEventListener("input", (e) => this.render(e.target.value));
    this.querySelector(".add-button").addEventListener("click", () => {
      openRecordModal({ resource: this.resource, definition: this.definition, onSaved: () => this.load() });
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
      this.rows = await fetchJson(this.endpoint);
      this.render("");
    } catch (error) {
      this.querySelector(".table-wrap").innerHTML = `<div class="empty">خطا: ${escapeHtml(error.message)}</div>`;
    }
  }

  render(filter) {
    const wrap = this.querySelector(".table-wrap");
    const normalized = filter.trim().toLowerCase();
    const rows = normalized
      ? this.rows.filter((row) => JSON.stringify(row).toLowerCase().includes(normalized))
      : this.rows;
    if (!rows.length) {
      wrap.innerHTML = `<div class="empty">رکوردی یافت نشد.</div>`;
      return;
    }
    const columns = Object.keys(rows[0]).filter((c) => !hiddenColumns.has(c));
    const editable = this.definition && editableResources.has(this.resource);
    const statusField = this.definition?.status_field;
    const statusOptions = this.definition?.status_options || [];
    const head = columns.map((c) => `<th>${escapeHtml(labels[c] || c)}</th>`).join("");
    const body = rows.map((row) => {
      const cells = columns.map((column) => {
        const value = row[column];
        if (editable && column === statusField && statusOptions.length) {
          const options = statusOptions.map((o) => `<option value="${escapeHtml(o)}" ${String(o) === String(value) ? "selected" : ""}>${escapeHtml(o)}</option>`).join("");
          return `<td><select class="inline-status" data-id="${escapeHtml(row.id)}">${options}</select></td>`;
        }
        const className = column === "amount" && Number(value) < 0 ? "money-negative" : column === "amount" && Number(value) > 0 ? "money-positive" : "";
        return `<td class="${className}">${formatCell(column, value, row)}</td>`;
      }).join("");
      const actions = editable ? `<td class="row-actions">
        <button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
        <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>
      </td>` : "";
      return `<tr>${cells}${actions}</tr>`;
    }).join("");
    wrap.innerHTML = `<table><thead><tr>${head}${editable ? "<th>عملیات</th>" : ""}</tr></thead><tbody>${body}</tbody></table>`;
  }

  async handleClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button || !this.contains(button) || !this.definition) return;
    const id = Number(button.dataset.id);
    const record = this.rows.find((row) => Number(row.id) === id);
    if (!record) return;
    if (button.dataset.action === "edit") {
      const full = this.rows.find((r) => Number(r.id) === id);
      if (full?.desk_ids && typeof full.desk_ids === "string") {
        full.desk_ids = full.desk_ids.split(",").filter(Boolean);
      }
      openRecordModal({ resource: this.resource, definition: this.definition, record: full || record, onSaved: () => this.load() });
      return;
    }
    if (button.dataset.action === "delete") {
      if (!window.confirm("حذف شود؟")) return;
      await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=delete`, { id });
      await this.load();
      await loadDashboard();
    }
  }

  async handleChange(event) {
    const select = event.target.closest(".inline-status");
    if (!select || !this.contains(select) || !this.definition) return;
    select.disabled = true;
    try {
      await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=status`, { id: select.dataset.id, status: select.value });
      await this.load();
      await loadDashboard();
    } catch (error) {
      alert(error.message);
    } finally {
      select.disabled = false;
    }
  }
}

customElements.define("data-table", DataTable);

const reimportButton = document.getElementById("reimportButton");
if (reimportButton) {
  reimportButton.addEventListener("click", async () => {
    if (!window.confirm("داده‌های نمونه جایگزین داده فعلی شود؟ رکوردهای دستی ممکن است از بین برود.")) return;
    reimportButton.disabled = true;
    try {
      await fetchJson("api.php?resource=reimport", { method: "POST", headers: { "X-CSRF-Token": csrfToken } });
      window.location.reload();
    } catch (error) {
      alert(error.message);
      reimportButton.disabled = false;
    }
  });
}

loadDashboard().catch((error) => {
  document.getElementById("cards").innerHTML = `<article class="card"><span>خطا</span><strong>${escapeHtml(error.message)}</strong></article>`;
});
