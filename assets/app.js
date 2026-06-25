const labels = {
  id: "شناسه",
  row_number: "ردیف فایل",
  code: "کد",
  full_name: "نام",
  team_name: "تیم/شرکت",
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

const editableResources = new Set(["members", "teams", "lockers", "plans", "charges", "transactions"]);

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
];

const moneyCards = new Set(["charge_total", "income_total", "expense_total"]);

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
  renderStatus("lockerStatus", data.locker_status);
  renderStatus("planStatus", data.plan_status);
  renderStatus("financeStatus", data.finance_by_category, "category", "amount", true);
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
      this.render("");
    } catch (error) {
      this.querySelector(".table-wrap").innerHTML = `<div class="empty">خطا در بارگذاری: ${escapeHtml(error.message)}</div>`;
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
    const columns = Object.keys(rows[0]).filter((column) => !["source_sheet", "source_file"].includes(column));
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
