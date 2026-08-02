/* global fetchJson, fetchResource, postJson, showToast, escapeHtml, formatMoney, formatPlain, canWrite, createSmsEditor, buildRecipientFilterBar */

const smsState = {
  recipients: [],
  selected: new Set(),
  configured: false,
  page: 1,
  perPage: 25,
  filters: { q: "", teamId: "", entityType: "", isLeader: "", wantsAccess: "" },
  debtors: [],
  selectedDebtors: new Set(),
  chargeTemplateConfigured: false,
  historyPage: 1,
  historyPages: 1,
  historyPerPage: 50,
};

let announcementEditor = null;

const bindSmsPanelTabs = () => {
  const root = document.getElementById("sms");
  if (!root || root.dataset.tabsReady) return;
  root.dataset.tabsReady = "1";
  const tabs = root.querySelectorAll("[data-sms-tab]");
  const panels = root.querySelectorAll("[data-sms-panel]");
  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const id = tab.dataset.smsTab;
      tabs.forEach((item) => {
        const active = item === tab;
        item.classList.toggle("is-active", active);
        item.setAttribute("aria-selected", active ? "true" : "false");
      });
      panels.forEach((panel) => {
        const active = panel.dataset.smsPanel === id;
        panel.classList.toggle("is-active", active);
        panel.hidden = !active;
      });
    });
  });
};

const smsStatusBadge = (status) => {
  const value = String(status || "");
  if (value === "sent") return `<span class="badge badge-sent">ارسال شد</span>`;
  if (value === "failed") return `<span class="badge badge-failed">ناموفق</span>`;
  return `<span class="badge">${escapeHtml(value || "—")}</span>`;
};

const renderSmsSetupBanner = (settings = null) => {
  const host = document.getElementById("smsSetupBanner");
  if (!host) return;
  const configured = settings?.sms_configured ?? smsState.configured;
  if (configured) {
    host.hidden = true;
    host.innerHTML = "";
    return;
  }
  host.hidden = false;
  host.innerHTML = `
    <article class="panel panel--accent">
      <h2>تنظیمات پیامک ناقص است</h2>
      <p class="hint">برای ارسال پیامک، ابتدا نام کاربری، رمز API و خط ارسال ملی‌پیامک را در بخش تنظیمات وارد کنید.</p>
      <button type="button" class="button" data-go="sms-settings">رفتن به تنظیمات پیامک</button>
    </article>`;
};

window.initSmsPanel = () => {
  bindSmsPanelTabs();
  initSmsFilters().catch((error) => showToast(error.message, "error"));
  initSmsEditors();
  loadSmsStats().catch((error) => {
    const host = document.getElementById("smsStats");
    if (host) host.innerHTML = `<div class="empty">خطا در بارگذاری آمار: ${escapeHtml(error.message)}</div>`;
  });
  loadSmsRecipients().catch((error) => showToast(error.message, "error"));
  loadSmsHistory().catch((error) => {
    const tbody = document.querySelector("#smsHistoryTable tbody");
    if (tbody) tbody.innerHTML = `<tr><td colspan="10">خطا: ${escapeHtml(error.message)}</td></tr>`;
  });
  initChargeReminderPanel().catch((error) => showToast(error.message, "error"));
};

const initSmsEditors = () => {
  const announcementHost = document.getElementById("smsAnnouncementEditor");
  if (announcementHost && !announcementHost.dataset.ready) {
    announcementHost.dataset.ready = "1";
    announcementEditor = createSmsEditor(announcementHost, {
      label: "متن اطلاعیه",
      placeholder: "متن پیامک اطلاعیه را بنویسید…",
      readonly: !canWrite,
      rows: 6,
      variables: window.SMS_EDITOR_VARS,
    });
  }
};

const smsRecipientFilters = () => ({
  page: smsState.page,
  perPage: smsState.perPage,
  q: smsState.filters.q,
  teamId: smsState.filters.teamId,
  entityType: smsState.filters.entityType,
  isLeader: smsState.filters.isLeader,
  wantsAccess: smsState.filters.wantsAccess,
});

const initSmsFilters = async () => {
  const root = document.getElementById("smsRecipientsPanel");
  const mount = document.getElementById("smsFilterBar");
  if (!root || !mount) return;
  await buildRecipientFilterBar(mount, smsState.filters, (filters) => {
    Object.assign(smsState.filters, filters);
    smsState.page = 1;
    loadSmsRecipients().catch((error) => showToast(error.message, "error"));
  });

  if (root.dataset.actionsReady) return;
  root.dataset.actionsReady = "1";
  root.querySelector("#smsSelectLeaders")?.addEventListener("click", () => {
    smsState.recipients.filter((row) => Number(row.is_leader) === 1).forEach((row) => smsState.selected.add(Number(row.id)));
    renderSmsRecipients();
  });
  root.querySelector("#smsSelectAllPage")?.addEventListener("click", () => {
    smsState.recipients.forEach((row) => smsState.selected.add(Number(row.id)));
    renderSmsRecipients();
  });
  root.querySelector("#smsClearSelection")?.addEventListener("click", () => {
    smsState.selected.clear();
    renderSmsRecipients();
  });
  root.querySelector("#smsSendAnnouncement")?.addEventListener("click", () => {
    sendSmsAnnouncement().catch((error) => showToast(error.message, "error"));
  });
};

const loadSmsStats = async () => {
  const host = document.getElementById("smsStats");
  if (!host) return;
  const stats = await fetchJson("api.php?resource=sms-stats");
  smsState.configured = Boolean(stats.sms_configured);
  renderSmsSetupBanner({ sms_configured: stats.sms_configured });
  const statusClass = stats.sms_configured ? "sms-status-pill--ok" : "sms-status-pill--warn";
  const statusLabel = stats.sms_configured ? "آماده ارسال" : "نیاز به تنظیمات";
  host.innerHTML = `
    <div class="sms-page-head">
      <div>
        <span class="panel-subtitle">وضعیت سرویس</span>
        <span class="sms-status-pill ${statusClass}">${statusLabel}</span>
      </div>
    </div>
    <div class="month-stats sms-stats-grid">
      <div class="month-stat"><span>ارسال امروز</span><strong>${Number(stats.sent_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>باقی‌مانده امروز</span><strong>${Number(stats.remaining_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>ناموفق امروز</span><strong>${Number(stats.failed_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>هزینه امروز</span><strong>${formatMoney(stats.cost_today || 0)}</strong></div>
      <div class="month-stat"><span>موجودی پنل</span><strong>${stats.panel_credit != null ? Number(stats.panel_credit).toLocaleString("fa-IR") : "—"}</strong></div>
      <div class="month-stat"><span>تعرفه هر پیامک</span><strong>${formatMoney(stats.unit_cost || 0)}</strong></div>
    </div>
    ${stats.sms_configured ? "" : `<p class="hint">برای ارسال، ابتدا حساب API و خط ارسال را در تنظیمات پیامک تکمیل کنید.</p>`}`;
};

const renderSmsRecipients = () => {
  const tbody = document.querySelector("#smsRecipientsTable tbody");
  const info = document.getElementById("smsSelectionInfo");
  if (!tbody) return;
  tbody.innerHTML = smsState.recipients.map((row) => {
    const id = Number(row.id);
    const checked = smsState.selected.has(id) ? "checked" : "";
    return `<tr>
      <td><input type="checkbox" data-sms-member="${id}" ${checked} ${canWrite ? "" : "disabled"} /></td>
      <td>${escapeHtml(row.full_name || "—")}${Number(row.is_leader) === 1 ? ' <span class="badge badge-paid">مسئول</span>' : ""}</td>
      <td>${escapeHtml(row.team_label || "—")}</td>
      <td>${escapeHtml(row.phone || "—")}</td>
      <td>${Number(row.wants_access) === 1 ? "بله" : "خیر"}</td>
    </tr>`;
  }).join("") || `<tr class="empty-row"><td colspan="5">گیرنده‌ای با این فیلتر یافت نشد.</td></tr>`;
  tbody.querySelectorAll("[data-sms-member]").forEach((input) => {
    input.addEventListener("change", () => {
      const id = Number(input.dataset.smsMember);
      if (input.checked) smsState.selected.add(id);
      else smsState.selected.delete(id);
      if (info) info.textContent = `${smsState.selected.size.toLocaleString("fa-IR")} نفر انتخاب شده`;
    });
  });
  if (info) info.textContent = `${smsState.selected.size.toLocaleString("fa-IR")} نفر انتخاب شده`;
};

const loadSmsRecipients = async () => {
  await initSmsFilters();
  const result = await fetchResource("api.php?resource=sms-recipients", smsRecipientFilters());
  smsState.recipients = result.rows || [];
  smsState.page = result.page;
  smsState.perPage = result.per_page;
  renderSmsRecipients();
  const pager = document.getElementById("smsRecipientsPager");
  if (pager) {
    pager.querySelector(".pager-info").textContent = `صفحه ${result.page.toLocaleString("fa-IR")} از ${result.pages.toLocaleString("fa-IR")}`;
    pager.querySelector("[data-sms-prev]")?.toggleAttribute("disabled", result.page <= 1);
    pager.querySelector("[data-sms-next]")?.toggleAttribute("disabled", result.page >= result.pages);
  }
};

const scheduleDeliveryCheck = (batchUid, logIds = []) => {
  if (!batchUid && logIds.length === 0) return;
  [8000, 30000, 90000].forEach((delay) => {
    setTimeout(() => {
      postJson("api.php?resource=sms-check-deliveries", {
        batch_uid: batchUid || "",
        log_ids: logIds,
      }).then(() => loadSmsHistory()).catch((error) => showToast(error.message, "error"));
    }, delay);
  });
};

const sendSmsAnnouncement = async () => {
  if (!canWrite) throw new Error("دسترسی ارسال ندارید.");
  if (!smsState.configured) throw new Error("ابتدا تنظیمات ملی‌پیامک را کامل کنید.");
  const message = announcementEditor?.getValue() || "";
  if (!message) throw new Error("متن پیامک را وارد کنید.");
  if (!smsState.selected.size) throw new Error("حداقل یک گیرنده انتخاب کنید.");
  if (!window.confirm(`ارسال پیامک به ${smsState.selected.size} نفر انجام شود؟`)) return;
  const button = document.getElementById("smsSendAnnouncement");
  if (button) button.disabled = true;
  try {
    const result = await postJson("api.php?resource=sms-send", {
      message,
      member_ids: [...smsState.selected],
    });
    showToast(`ارسال انجام شد — موفق: ${result.result?.sent || 0}، ناموفق: ${result.result?.failed || 0}`, "success");
    smsState.selected.clear();
    await loadSmsStats();
    await loadSmsHistory();
    renderSmsRecipients();
    scheduleDeliveryCheck(result.result?.batch_uid, result.result?.pending_delivery_log_ids || []);
  } finally {
    if (button) button.disabled = false;
  }
};

const initChargeReminderPanel = async () => {
  const panel = document.getElementById("smsChargeReminderPanel");
  if (!panel) return;
  if (!panel.dataset.actionsReady) {
    panel.dataset.actionsReady = "1";
    panel.querySelector("#smsSelectAllDebtors")?.addEventListener("click", () => {
      smsState.debtors
        .filter((row) => row.phone_valid)
        .forEach((row) => smsState.selectedDebtors.add(Number(row.team_id)));
      renderChargeDebtors();
    });
    panel.querySelector("#smsClearDebtorSelection")?.addEventListener("click", () => {
      smsState.selectedDebtors.clear();
      renderChargeDebtors();
    });
    panel.querySelector("#smsSendChargeReminders")?.addEventListener("click", () => {
      sendChargeReminders().catch((error) => showToast(error.message, "error"));
    });
  }
  await loadChargeDebtors();
};

const loadChargeDebtors = async () => {
  const list = document.getElementById("smsChargeDebtorList");
  if (!list) return;
  const data = await fetchJson("api.php?resource=sms-charge-debtors");
  smsState.debtors = data.debtors || [];
  smsState.chargeTemplateConfigured = Boolean(data.template_configured);
  renderChargeDebtors();
};

const renderChargeDebtors = () => {
  const list = document.getElementById("smsChargeDebtorList");
  const info = document.getElementById("smsDebtorSelectionInfo");
  const preview = document.getElementById("smsChargePreview");
  if (!list) return;

  if (!smsState.chargeTemplateConfigured) {
    list.innerHTML = `<div class="empty">الگوی یادآوری شارژ هنوز در تنظیمات پیامک ذخیره نشده است.</div>`;
    if (preview) {
      preview.innerHTML = `<p class="hint">برای الگوی shared ملی‌پیامک از فرمت <code dir="ltr">bodyId@{team_name}##{debt_total}##shared</code> استفاده کنید.</p>`;
    }
    if (info) info.textContent = "۰ نهاد انتخاب شده";
    return;
  }

  if (!smsState.debtors.length) {
    list.innerHTML = `<div class="empty">نهاد بدهکاری یافت نشد.</div>`;
    if (preview) preview.innerHTML = "";
    if (info) info.textContent = "۰ نهاد انتخاب شده";
    return;
  }

  list.innerHTML = smsState.debtors.map((row) => {
    const teamId = Number(row.team_id);
    const disabled = !row.phone_valid;
    const checked = smsState.selectedDebtors.has(teamId) ? "checked" : "";
    return `<label class="charge-debtor-row${disabled ? " charge-debtor-row--disabled" : ""}">
      <input type="checkbox" data-sms-debtor="${teamId}" ${checked} ${disabled || !canWrite ? "disabled" : ""} />
      <div class="charge-debtor-name">
        <strong>${escapeHtml(row.team_name || "—")}</strong>
        <span>${escapeHtml(row.leader_name || "—")} — ${escapeHtml(row.phone || "بدون موبایل")}</span>
        ${row.debt_summary ? `<span class="charge-debtor-months">${escapeHtml(row.debt_summary)}</span>` : ""}
      </div>
      <span class="charge-debtor-amount">${formatMoney(row.debt_total || 0)}</span>
    </label>`;
  }).join("");

  list.querySelectorAll("[data-sms-debtor]").forEach((input) => {
    input.addEventListener("change", () => {
      const teamId = Number(input.dataset.smsDebtor);
      if (input.checked) smsState.selectedDebtors.add(teamId);
      else smsState.selectedDebtors.delete(teamId);
      updateChargePreview();
      if (info) info.textContent = `${smsState.selectedDebtors.size.toLocaleString("fa-IR")} نهاد انتخاب شده`;
    });
  });

  updateChargePreview();
  if (info) info.textContent = `${smsState.selectedDebtors.size.toLocaleString("fa-IR")} نهاد انتخاب شده`;
};

const updateChargePreview = () => {
  const preview = document.getElementById("smsChargePreview");
  if (!preview) return;
  const selected = smsState.debtors.filter((row) => smsState.selectedDebtors.has(Number(row.team_id)));
  const sample = selected[0] || smsState.debtors.find((row) => row.phone_valid) || smsState.debtors[0];
  const humanPreview = sample?.preview_human || "";
  if (!humanPreview && !sample?.preview_message) {
    preview.innerHTML = `<p class="hint">پس از انتخاب نهاد، پیش‌نمایش پیامک نمایش داده می‌شود.</p>`;
    return;
  }
  preview.innerHTML = `
    <div class="charge-reminder-card sms-preview-card">
      <div class="charge-reminder-head">
        <strong>پیش‌نمایش پیامک</strong>
        <span class="hint">${escapeHtml(sample.team_name || "")}</span>
      </div>
      ${humanPreview ? `<textarea readonly dir="rtl" class="sms-pattern-preview">${escapeHtml(humanPreview)}</textarea>` : ""}
      ${sample.preview_message ? `<p class="hint sms-api-preview" dir="ltr">API: ${escapeHtml(sample.preview_message)}</p>` : ""}
    </div>`;
};

const sendChargeReminders = async () => {
  if (!canWrite) throw new Error("دسترسی ارسال ندارید.");
  if (!smsState.configured) throw new Error("ابتدا تنظیمات ملی‌پیامک را کامل کنید.");
  if (!smsState.chargeTemplateConfigured) throw new Error("الگوی یادآوری شارژ را در تنظیمات ذخیره کنید.");
  if (!smsState.selectedDebtors.size) throw new Error("حداقل یک نهاد بدهکار انتخاب کنید.");
  if (!window.confirm(`ارسال یادآوری شارژ به ${smsState.selectedDebtors.size} نهاد انجام شود؟`)) return;
  const button = document.getElementById("smsSendChargeReminders");
  if (button) button.disabled = true;
  try {
    const result = await postJson("api.php?resource=sms-send-charge-reminders", {
      team_ids: [...smsState.selectedDebtors],
    });
    showToast(`ارسال انجام شد — موفق: ${result.result?.sent || 0}، ناموفق: ${result.result?.failed || 0}، رد شده: ${result.result?.skipped || 0}`, "success");
    smsState.selectedDebtors.clear();
    await loadSmsStats();
    await loadSmsHistory();
    await loadChargeDebtors();
    scheduleDeliveryCheck(result.result?.batch_uid, result.result?.pending_delivery_log_ids || []);
  } finally {
    if (button) button.disabled = false;
  }
};

const messageTypeLabel = (type) => ({
  announcement: "اطلاعیه",
  charge_reminder: "یادآوری شارژ",
  room_pending: "رزرو — ثبت",
  room_approved: "رزرو — تأیید",
  room_rejected: "رزرو — رد",
  room_cancelled: "رزرو — لغو",
  member_approved: "عضو — تأیید",
  member_rejected: "عضو — رد",
  member_request_approved: "درخواست عضو — تأیید",
  member_request_rejected: "درخواست عضو — رد",
}[type] || "ارسالی");

const ensureSmsHistoryPager = () => {
  const table = document.getElementById("smsHistoryTable");
  if (!table || document.getElementById("smsHistoryPager")) return;
  const pager = document.createElement("div");
  pager.id = "smsHistoryPager";
  pager.className = "table-pagination";
  pager.innerHTML = `
    <span class="pager-info"></span>
    <div class="pager-actions">
      <button type="button" class="button ghost" data-sms-history-prev>قبلی</button>
      <button type="button" class="button ghost" data-sms-history-next>بعدی</button>
    </div>`;
  table.parentElement?.appendChild(pager);
  pager.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-sms-history-prev], button[data-sms-history-next]");
    if (!button) return;
    if (button.hasAttribute("data-sms-history-prev") && smsState.historyPage > 1) {
      smsState.historyPage -= 1;
    }
    if (button.hasAttribute("data-sms-history-next") && smsState.historyPage < smsState.historyPages) {
      smsState.historyPage += 1;
    }
    loadSmsHistory().catch((error) => showToast(error.message, "error"));
  });
};

const loadSmsHistory = async () => {
  const tbody = document.querySelector("#smsHistoryTable tbody");
  if (!tbody) return;
  ensureSmsHistoryPager();
  const result = await fetchResource("api.php?resource=sms-history", {
    page: smsState.historyPage,
    perPage: smsState.historyPerPage,
  });
  smsState.historyPage = Number(result.page || 1);
  smsState.historyPages = Math.max(1, Number(result.pages || 1));
  tbody.innerHTML = (result.rows || []).map((row) => `<tr>
    <td>${escapeHtml(formatPlain(row.created_at))}</td>
    <td>${escapeHtml(messageTypeLabel(row.message_type))}</td>
    <td>${escapeHtml(row.recipient_name || "—")}</td>
    <td dir="ltr">${escapeHtml(row.phone || "—")}</td>
    <td>${escapeHtml(row.team_name || "—")}</td>
    <td>${smsStatusBadge(row.status)}</td>
    <td>${escapeHtml(row.delivery_status || "—")}</td>
    <td class="sms-history-message" title="${escapeHtml(row.message_text || "")}">${escapeHtml(row.message_text || "—")}</td>
  </tr>`).join("") || `<tr class="empty-row"><td colspan="8">تاریخچه‌ای ثبت نشده است.</td></tr>`;
  const pager = document.getElementById("smsHistoryPager");
  if (pager) {
    pager.hidden = Number(result.total || 0) <= 0;
    const info = pager.querySelector(".pager-info");
    if (info) {
      info.textContent = `صفحه ${smsState.historyPage.toLocaleString("fa-IR")} از ${smsState.historyPages.toLocaleString("fa-IR")} — ${Number(result.total || 0).toLocaleString("fa-IR")} پیامک`;
    }
    pager.querySelector("[data-sms-history-prev]")?.toggleAttribute("disabled", smsState.historyPage <= 1);
    pager.querySelector("[data-sms-history-next]")?.toggleAttribute("disabled", smsState.historyPage >= smsState.historyPages);
  }
};

document.getElementById("smsRecipientsPager")?.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-sms-prev], button[data-sms-next]");
  if (!button) return;
  if (button.hasAttribute("data-sms-prev") && smsState.page > 1) smsState.page -= 1;
  if (button.hasAttribute("data-sms-next")) smsState.page += 1;
  loadSmsRecipients().catch((error) => showToast(error.message, "error"));
});

if (document.getElementById("sms")?.classList.contains("active")) {
  window.initSmsPanel?.();
}
