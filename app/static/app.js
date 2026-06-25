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

const formatNumber = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  if (typeof value === "number") return value.toLocaleString("fa-IR");
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

const fetchJson = async (url) => {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Request failed: ${url}`);
  return response.json();
};

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
      return `<article class="card"><span>${title}</span><strong>${value}</strong></article>`;
    })
    .join("");
};

const renderStatus = (id, rows, labelKey = "status", valueKey = "count", isMoney = false) => {
  const container = document.getElementById(id);
  container.innerHTML = rows.length
    ? rows
        .map(
          (row) =>
            `<div class="status-row"><span>${row[labelKey] || "نامشخص"}</span><strong>${
              isMoney ? formatMoney(row[valueKey]) : formatNumber(row[valueKey])
            }</strong></div>`,
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
          <span>${row.fiscal_year} - ${row.month_name}</span>
          <div class="bar-track"><div class="bar-fill" style="width:${width}%"></div></div>
          <strong>${formatMoney(row.amount)}</strong>
        </div>
      `;
    })
    .join("");
};

const loadDashboard = async () => {
  const data = await fetchJson("/api/summary");
  renderCards(data.cards);
  renderChargeChart(data.monthly_charges);
  renderStatus("lockerStatus", data.locker_status);
  renderStatus("planStatus", data.plan_status);
  renderStatus("financeStatus", data.finance_by_category, "category", "amount", true);
};

class DataTable extends HTMLElement {
  connectedCallback() {
    this.title = this.getAttribute("title");
    this.endpoint = this.getAttribute("endpoint");
    this.exportUrl = this.getAttribute("export-url");
    this.rows = [];
    this.innerHTML = `
      <article class="panel">
        <div class="table-toolbar">
          <h2>${this.title}</h2>
          <div class="table-actions">
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
    this.load();
  }

  async load() {
    this.rows = await fetchJson(this.endpoint);
    this.render("");
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
    const head = columns.map((column) => `<th>${labels[column] || column}</th>`).join("");
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
            return `<td class="${className}">${display}</td>`;
          })
          .join("");
        return `<tr>${cells}</tr>`;
      })
      .join("");
    wrap.innerHTML = `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
  }
}

customElements.define("data-table", DataTable);

document.getElementById("reimportButton").addEventListener("click", async () => {
  const button = document.getElementById("reimportButton");
  button.disabled = true;
  button.textContent = "در حال ورود اطلاعات...";
  await fetch("/api/reimport", { method: "POST" });
  window.location.reload();
});

loadDashboard().catch((error) => {
  console.error(error);
  document.getElementById("cards").innerHTML = `<article class="card"><span>خطا</span><strong>بارگذاری ناموفق</strong></article>`;
});
