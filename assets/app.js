const labels = {
  id: "شناسه",
  row_number: "ردیف فایل",
  code: "کد",
  full_name: "نام",
  team_name: "تیم/شرکت",
  team_id: "تیم مرتبط",
  related_team: "تیم مرتبط",
  name: "نام",
  leader: "سرگروه",
  phone: "تماس",
  desk_count: "تعداد میز",
  desks: "میز",
  lockers: "کمد",
  power_strips: "سه‌راهی",
  joined_at: "تاریخ عضویت",
  warning: "اخطار",
  notes: "توضیحات",
  national_id: "کدملی",
  locker_number: "شماره کمد",
  status: "وضعیت",
  assigned_to: "اختصاص به",
  delivered_at: "تاریخ تحویل",
  key_number: "شماره کلید",
  spare_key: "کلید یدک",
  plan_number: "شماره",
  title: "عنوان",
  proposed_budget: "بودجه پیشنهادی",
  cost_type: "نوع هزینه",
  schedule: "زمان‌بندی",
  fiscal_year: "سال",
  month_index: "شماره ماه",
  month_name: "ماه",
  amount: "مبلغ",
  note: "یادداشت",
  charge_rate: "نرخ شارژ",
  rent_rate: "نرخ اجاره",
  amount_due: "بدهی",
  amount_paid: "پرداخت‌شده",
  paid_at: "تاریخ پرداخت",
  batch_id: "دوره",
  invoice_count: "تعداد فاکتور",
  tx_date: "تاریخ",
  description: "شرح",
  category: "دسته",
  suspected_amount_note: "مبلغ مشکوک",
  sheet_name: "شیت",
  petty_cash_holder: "دارنده تنخواه",
  file_name: "فایل",
  source_file: "فایل منبع",
  source_sheet: "شیت منبع",
  message: "پیام",
  payload: "جزئیات",
  backups: "پشتیبان‌ها",
  reason: "دلیل",
  summary: "خلاصه",
  created_at: "تاریخ ایجاد",
  fiscal_year: "سال مالی",
  effective_from: "تاریخ اثرگذاری",
};

const csrfToken = window.MECHINNO?.csrfToken || "";
let crudMetaPromise = null;

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const editableResources = new Set(["members", "teams", "lockers", "plans", "charges", "transactions", "rate_settings"]);

const loadCrudMeta = () => {
  if (!crudMetaPromise) {
    crudMetaPromise = fetchJson("api.php?resource=crud-meta");
  }
  return crudMetaPromise;
};

const formatNumber = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  const maybe = Number(value);
  if (!Number.isNaN(maybe) && String(value).trim() !== "") {
    return maybe.toLocaleString("fa-IR");
  }
  return value;
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  return `${Number(value).toLocaleString("fa-IR")} ریال`;
};

const fetchJson = async (url, options = {}) => {
  const response = await fetch(url, options);
  const contentType = response.headers.get("content-type") || "";
  const data = contentType.includes("application/json")
    ? await response.json()
    : { error: await response.text() };
  if (!response.ok) throw new Error(data.error || `Request failed: ${url}`);
  return data;
};

const postJson = (url, payload = {}) =>
  fetchJson(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken,
    },
    body: JSON.stringify(payload),
  });

const activateSection = (id) => {
  document.querySelectorAll(".section").forEach((section) => {
    section.classList.toggle("active", section.id === id);
  });
  document.querySelectorAll(".nav-item").forEach((item) => {
    item.classList.toggle("active", item.dataset.section === id);
  });
};

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", () => activateSection(item.dataset.section));
});

const cardDefinitions = [
  ["members", "اعضا"],
  ["teams", "تیم‌ها"],
  ["lockers", "کمدها"],
  ["charge_total", "جمع شارژ"],
  ["income_total", "دریافتی‌ها"],
  ["expense_total", "هزینه‌ها"],
  ["reserved_lockers", "کمد رزرو"],
  ["warnings", "هشدار داده"],
  ["backups", "پشتیبان‌ها"],
  ["debt_total", "بدهی کل"],
  ["paid_total", "پرداخت ثبت‌شده"],
];

const moneyCards = new Set(["charge_total", "income_total", "expense_total", "debt_total", "paid_total"]);

const renderCards = (cards) => {
  const container = document.getElementById("cards");
  container.innerHTML = cardDefinitions
    .map(([key, title]) => {
      const value = moneyCards.has(key) ? formatMoney(cards[key]) : formatNumber(cards[key]);
      return `<article class="card"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong></article>`;
    })
    .join("");
};

const renderStatus = (id, rows, labelKey = "status", valueKey = "count", isMoney = false) => {
  const container = document.getElementById(id);
  container.innerHTML = rows.length
    ? rows
        .map(
          (row) =>
            `<div class="status-row"><span>${escapeHtml(row[labelKey] || "نامشخص")}</span><strong>${escapeHtml(
              isMoney ? formatMoney(row[valueKey]) : formatNumber(row[valueKey]),
            )}</strong></div>`,
        )
        .join("")
    : `<div class="empty">داده‌ای موجود نیست.</div>`;
};

const renderChargeChart = (rows) => {
  const container = document.getElementById("chargeChart");
  const max = Math.max(...rows.map((row) => Number(row.amount || 0)), 1);
  container.innerHTML = rows
    .slice(-16)
    .map((row) => {
      const width = (Number(row.amount || 0) / max) * 100;
      return `
        <div class="bar-row">
          <span>${escapeHtml(row.fiscal_year)} - ${escapeHtml(row.month_name)}</span>
          <div class="bar-track"><div class="bar-fill" style="width:${width}%"></div></div>
          <strong>${escapeHtml(formatMoney(row.amount))}</strong>
        </div>
      `;
    })
    .join("");
};

const loadDashboard = async () => {
  const data = await fetchJson("api.php?resource=summary");
  renderCards(data.cards);
  renderChargeChart(data.monthly_charges);
  renderDebtChart(data.debt_by_team || []);
  renderFinanceChart(data.finance_monthly || []);
  renderOccupancy(data.occupancy || {});
  renderStatus("lockerStatus", data.locker_status);
  renderStatus("planStatus", data.plan_status);
  renderStatus("financeStatus", data.finance_by_category, "category", "amount", true);
};

const renderDebtChart = (rows) => {
  const container = document.getElementById("debtChart");
  const max = Math.max(...rows.map((row) => Number(row.debt || 0)), 1);
  container.innerHTML = rows.length
    ? rows.map((row) => `
        <div class="bar-row">
          <span>${escapeHtml(row.team_name || "نامشخص")}</span>
          <div class="bar-track danger-track"><div class="bar-fill danger-fill" style="width:${(Number(row.debt || 0) / max) * 100}%"></div></div>
          <strong>${escapeHtml(formatMoney(row.debt))}</strong>
        </div>
      `).join("")
    : `<div class="empty">بدهی ثبت‌شده‌ای وجود ندارد.</div>`;
};

const renderFinanceChart = (rows) => {
  const container = document.getElementById("financeChart");
  const max = Math.max(...rows.flatMap((row) => [Number(row.income || 0), Number(row.expense || 0)]), 1);
  container.innerHTML = rows.length
    ? rows.slice(-12).map((row) => `
        <div class="double-bar">
          <span>${escapeHtml(row.period || "-")}</span>
          <div class="bar-track"><div class="bar-fill success-fill" style="width:${(Number(row.income || 0) / max) * 100}%"></div></div>
          <div class="bar-track"><div class="bar-fill danger-fill" style="width:${(Number(row.expense || 0) / max) * 100}%"></div></div>
        </div>
      `).join("")
    : `<div class="empty">داده مالی ماهانه موجود نیست.</div>`;
};

const renderOccupancy = (stats) => {
  const container = document.getElementById("occupancyChart");
  const total = Number(stats.lockers_total || 0);
  const assigned = Number(stats.lockers_assigned || 0);
  const reserved = Number(stats.lockers_reserved || 0);
  const available = Math.max(total - assigned - reserved, 0);
  container.innerHTML = `
    <div class="metric"><span>کمد تخصیص‌یافته</span><strong>${assigned.toLocaleString("fa-IR")} از ${total.toLocaleString("fa-IR")}</strong></div>
    <div class="metric"><span>کمد رزرو</span><strong>${reserved.toLocaleString("fa-IR")}</strong></div>
    <div class="metric"><span>کمد آزاد تقریبی</span><strong>${available.toLocaleString("fa-IR")}</strong></div>
    <div class="metric"><span>میزهای استفاده‌شده</span><strong>${formatNumber(stats.desks_used || 0)}</strong></div>
  `;
};

const ensureModal = () => {
  let modal = document.getElementById("crudModal");
  if (modal) return modal;

  document.body.insertAdjacentHTML(
    "beforeend",
    `
      <div id="crudModal" class="modal-backdrop" hidden>
        <section class="modal-card" role="dialog" aria-modal="true">
          <div class="modal-head">
            <h2 id="crudModalTitle"></h2>
            <button class="modal-close" type="button" aria-label="بستن">×</button>
          </div>
          <form id="crudForm" class="crud-form"></form>
        </section>
      </div>
    `,
  );
  modal = document.getElementById("crudModal");
  modal.querySelector(".modal-close").addEventListener("click", closeModal);
  modal.addEventListener("click", (event) => {
    if (event.target === modal) closeModal();
  });
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
    const options = meta.options || [];
    const entries = Array.isArray(options) ? options.map((option) => [option, option]) : Object.entries(options);
    return `
      <select name="${escapeHtml(name)}" ${required}>
        <option value="">انتخاب کنید</option>
        ${entries
          .map(([optionValue, optionLabel]) => {
            const selected = String(optionValue) === String(safeValue) || String(optionLabel) === String(safeValue) ? "selected" : "";
            return `<option value="${escapeHtml(optionValue)}" ${selected}>${escapeHtml(optionLabel)}</option>`;
          })
          .join("")}
      </select>
    `;
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
      ${Object.entries(definition.fields)
        .map(([name, meta]) => {
          const value = record ? record[name] : "";
          return `
            <label class="${meta.type === "textarea" ? "wide" : ""}">
              <span>${escapeHtml(meta.label)}${meta.required ? " *" : ""}</span>
              ${fieldInput(name, meta, value)}
            </label>
          `;
        })
        .join("")}
    </div>
    <div class="modal-actions">
      <button class="button" type="submit">${isEdit ? "ذخیره تغییرات" : "ثبت"}</button>
      <button class="button ghost" type="button" data-close-modal>انصراف</button>
    </div>
  `;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  form.onsubmit = async (event) => {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.textContent = "در حال ذخیره...";
    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      if (isEdit) payload.id = record.id;
      await postJson(`api.php?resource=${encodeURIComponent(resource)}&action=${isEdit ? "update" : "create"}`, payload);
      closeModal();
      await onSaved();
      await loadDashboard();
    } catch (error) {
      alert(error.message);
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = isEdit ? "ذخیره تغییرات" : "ثبت";
    }
  };
  modal.hidden = false;
};

const openTeamProfile = async (teamId) => {
  const data = await fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
  const modal = ensureModal();
  const form = modal.querySelector("#crudForm");
  modal.querySelector("#crudModalTitle").textContent = `پروفایل تیم: ${data.team.name || "-"}`;
  const section = (title, rows, cols) => `
    <h3>${escapeHtml(title)}</h3>
    <div class="profile-table">
      <table><thead><tr>${cols.map((c) => `<th>${escapeHtml(labels[c] || c)}</th>`).join("")}</tr></thead>
      <tbody>${rows.length ? rows.map((row) => `<tr>${cols.map((c) => `<td>${escapeHtml(formatNumber(row[c] ?? "-"))}</td>`).join("")}</tr>`).join("") : `<tr><td colspan="${cols.length}">داده‌ای موجود نیست.</td></tr>`}</tbody></table>
    </div>
  `;
  form.innerHTML = `
    <div class="profile-summary">
      <div><span>سرگروه</span><strong>${escapeHtml(data.team.leader || "-")}</strong></div>
      <div><span>تعداد میز</span><strong>${escapeHtml(formatNumber(data.team.desk_count || 0))}</strong></div>
      <div><span>جمع شارژ</span><strong>${escapeHtml(formatMoney(data.summary.charge_total || 0))}</strong></div>
      <div><span>بدهی</span><strong>${escapeHtml(formatMoney(data.summary.debt_total || 0))}</strong></div>
      <div><span>پرداخت</span><strong>${escapeHtml(formatMoney(data.summary.paid_total || 0))}</strong></div>
    </div>
    ${section("اعضا", data.members, ["full_name", "phone", "desks", "lockers"])}
    ${section("کمدها", data.lockers, ["locker_number", "status", "delivered_at", "spare_key"])}
    ${section("شارژها", data.charges, ["fiscal_year", "month_name", "amount", "note"])}
    ${section("پرداخت‌ها", data.payments, ["fiscal_year", "month_name", "amount_due", "amount_paid", "status"])}
    ${section("تراکنش‌های مرتبط تقریبی", data.transactions, ["tx_date", "description", "amount", "category"])}
    <div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>
  `;
  form.querySelector("[data-close-modal]").addEventListener("click", closeModal);
  modal.hidden = false;
};

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.exportUrl = this.getAttribute("export-url");
    this.resource = new URL(this.endpoint, window.location.href).searchParams.get("resource");
    this.definition = null;
    this.rows = [];
    this.innerHTML = `
      <article class="panel">
        <div class="table-toolbar">
          <h2>${this.title}</h2>
          <div class="table-actions">
            <button class="button add-button" type="button" hidden>افزودن</button>
            <button class="button ghost profile-button" type="button" hidden>پروفایل تیم</button>
            <input class="search" placeholder="جست‌وجو..." />
            <a class="button" href="${this.exportUrl}">Excel</a>
          </div>
        </div>
        <div class="table-wrap"><div class="empty">در حال بارگذاری...</div></div>
      </article>
    `;
    this.querySelector(".search").addEventListener("input", (event) => {
      this.render(event.target.value);
    });
    this.querySelector(".add-button").addEventListener("click", () => {
      openRecordModal({
        resource: this.resource,
        definition: this.definition,
        onSaved: () => this.load(),
      });
    });
    this.querySelector(".profile-button").addEventListener("click", () => {
      const selected = this.querySelector(".team-profile-select")?.value;
      if (selected) openTeamProfile(selected).catch((error) => alert(error.message));
    });
    this.addEventListener("click", (event) => this.handleClick(event));
    this.addEventListener("change", (event) => this.handleChange(event));
    this.load();
  }

  async load() {
    try {
      const meta = await loadCrudMeta();
      this.definition = meta.resources[this.resource] || null;
      this.querySelector(".add-button").hidden = !this.definition || !editableResources.has(this.resource);
      this.rows = await fetchJson(this.endpoint);
      this.renderFilters();
      this.render("");
    } catch (error) {
      this.querySelector(".table-wrap").innerHTML = `<div class="empty">خطا در بارگذاری: ${escapeHtml(error.message)}</div>`;
    }
  }

  render(filter) {
    const wrap = this.querySelector(".table-wrap");
    const normalized = filter.trim().toLowerCase();
    const rows = this.applyAdvancedFilters(normalized
      ? this.rows.filter((row) => JSON.stringify(row).toLowerCase().includes(normalized))
      : this.rows);
    if (!rows.length) {
      wrap.innerHTML = `<div class="empty">رکوردی یافت نشد.</div>`;
      return;
    }
    const hiddenColumns = new Set(["source_sheet", "source_file", "team_id"]);
    const columns = Object.keys(rows[0]).filter((column) => !hiddenColumns.has(column));
    const head = columns.map((column) => `<th>${escapeHtml(labels[column] || column)}</th>`).join("");
    const editable = this.definition && editableResources.has(this.resource);
    const statusField = this.definition?.status_field;
    const statusOptions = this.definition?.status_options || [];
    const body = rows
      .map((row) => {
        const cells = columns
          .map((column) => {
            const value = row[column];
            const className =
              column === "amount" && Number(value) < 0
                ? "money-negative"
                : column === "amount" && Number(value) > 0
                  ? "money-positive"
                  : "";
            const display = ["amount", "proposed_budget", "charge_rate", "rent_rate", "suspected_amount_note"].includes(column)
              ? formatMoney(value)
              : formatNumber(value);
            if (editable && column === statusField && statusOptions.length) {
              const options = statusOptions
                .map((option) => `<option value="${escapeHtml(option)}" ${String(option) === String(value) ? "selected" : ""}>${escapeHtml(option)}</option>`)
                .join("");
              return `<td><select class="inline-status" data-id="${escapeHtml(row.id)}">${options}</select></td>`;
            }
            return `<td class="${className}">${escapeHtml(display)}</td>`;
          })
          .join("");
        const actions = editable
          ? `<td class="row-actions">
              <button class="mini-button" type="button" data-action="edit" data-id="${escapeHtml(row.id)}">ویرایش</button>
              <button class="mini-button danger" type="button" data-action="delete" data-id="${escapeHtml(row.id)}">حذف</button>
            </td>`
          : "";
        return `<tr>${cells}${actions}</tr>`;
      })
      .join("");
    wrap.innerHTML = `<table><thead><tr>${head}${editable ? "<th>عملیات</th>" : ""}</tr></thead><tbody>${body}</tbody></table>`;
  }

  renderFilters() {
    const toolbar = this.querySelector(".table-toolbar");
    let filterBox = this.querySelector(".advanced-filters");
    if (!filterBox) {
      toolbar.insertAdjacentHTML("afterend", `<div class="advanced-filters"></div>`);
      filterBox = this.querySelector(".advanced-filters");
      filterBox.addEventListener("input", () => this.render(this.querySelector(".search").value));
      filterBox.addEventListener("change", () => this.render(this.querySelector(".search").value));
    }
    const years = [...new Set(this.rows.map((r) => r.fiscal_year).filter(Boolean))];
    const statuses = [...new Set(this.rows.map((r) => r.status || r.category).filter(Boolean))];
    const teams = [...new Map(this.rows.filter((r) => r.team_id || r.related_team || r.name).map((r) => [r.team_id || r.id, r.related_team || r.name || r.team_name])).entries()].filter(([id]) => id);
    this.querySelector(".profile-button").hidden = this.resource !== "teams";
    filterBox.innerHTML = `
      ${years.length ? `<select data-filter="year"><option value="">همه سال‌ها</option>${years.map((y) => `<option>${escapeHtml(y)}</option>`).join("")}</select>` : ""}
      ${statuses.length ? `<select data-filter="status"><option value="">همه وضعیت‌ها</option>${statuses.map((s) => `<option>${escapeHtml(s)}</option>`).join("")}</select>` : ""}
      ${teams.length && this.resource !== "teams" ? `<select data-filter="team"><option value="">همه تیم‌ها</option>${teams.map(([id, name]) => `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`).join("")}</select>` : ""}
      ${this.resource === "teams" ? `<select class="team-profile-select"><option value="">انتخاب تیم برای پروفایل</option>${this.rows.map((r) => `<option value="${escapeHtml(r.id)}">${escapeHtml(r.name || "-")}</option>`).join("")}</select>` : ""}
      <input data-filter="from" placeholder="از تاریخ 1404/01/01" />
      <input data-filter="to" placeholder="تا تاریخ 1404/12/29" />
    `;
  }

  applyAdvancedFilters(rows) {
    const box = this.querySelector(".advanced-filters");
    if (!box) return rows;
    const year = box.querySelector('[data-filter="year"]')?.value || "";
    const status = box.querySelector('[data-filter="status"]')?.value || "";
    const team = box.querySelector('[data-filter="team"]')?.value || "";
    const from = box.querySelector('[data-filter="from"]')?.value || "";
    const to = box.querySelector('[data-filter="to"]')?.value || "";
    return rows.filter((row) => {
      const rowStatus = row.status || row.category || "";
      const rowDate = row.tx_date || row.paid_at || row.delivered_at || row.effective_from || "";
      return (!year || row.fiscal_year === year)
        && (!status || rowStatus === status)
        && (!team || String(row.team_id || "") === String(team))
        && (!from || String(rowDate) >= from)
        && (!to || String(rowDate) <= to);
    });
  }

  async handleClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button || !this.contains(button) || !this.definition) return;
    const id = Number(button.dataset.id);
    const record = this.rows.find((row) => Number(row.id) === id);
    if (!record) return;

    if (button.dataset.action === "edit") {
      openRecordModal({
        resource: this.resource,
        definition: this.definition,
        record,
        onSaved: () => this.load(),
      });
      return;
    }

    if (button.dataset.action === "delete") {
      if (!window.confirm("این رکورد حذف شود؟ این عملیات قابل برگشت نیست.")) return;
      button.disabled = true;
      try {
        await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=delete`, { id });
        await this.load();
        await loadDashboard();
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
      }
    }
  }

  async handleChange(event) {
    const select = event.target.closest(".inline-status");
    if (!select || !this.contains(select) || !this.definition) return;
    select.disabled = true;
    try {
      await postJson(`api.php?resource=${encodeURIComponent(this.resource)}&action=status`, {
        id: select.dataset.id,
        status: select.value,
      });
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

document.getElementById("reimportButton").addEventListener("click", async () => {
  if (!window.confirm("ورود مجدد داده‌ها جداول را از روی فایل‌های Excel بازسازی می‌کند. ادامه می‌دهید؟")) {
    return;
  }
  const button = document.getElementById("reimportButton");
  button.disabled = true;
  button.textContent = "در حال ورود اطلاعات...";
  try {
    await fetchJson("api.php?resource=reimport", {
      method: "POST",
      headers: { "X-CSRF-Token": csrfToken },
    });
    window.location.reload();
  } catch (error) {
    alert(error.message);
    button.disabled = false;
    button.textContent = "ورود مجدد از Excel";
  }
});

loadDashboard().catch((error) => {
  console.error(error);
  document.getElementById("cards").innerHTML = `<article class="card"><span>خطا</span><strong>${escapeHtml(error.message)}</strong></article>`;
});
