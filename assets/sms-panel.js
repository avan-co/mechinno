/* global fetchJson, fetchResource, postJson, showToast, escapeHtml, formatMoney, formatPlain, canWrite, createSmsEditor, buildRecipientFilterBar */

const smsState = {
  recipients: [],
  selected: new Set(),
  configured: false,
  page: 1,
  perPage: 25,
  filters: { q: "", teamId: "", entityType: "", isLeader: "", wantsAccess: "" },
};

let announcementEditor = null;

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
      <p class="hint">برای ارسال پیامک، ابتدا نام کاربری و رمز API ملی‌پیامک را در بخش تنظیمات وارد کنید. برای متن آزاد، شماره خط هم لازم است؛ الگوی اشتراکی با ##shared بدون خط کار می‌کند.</p>
      <button type="button" class="button" data-go="sms-settings">رفتن به تنظیمات پیامک</button>
    </article>`;
};

window.initSmsPanel = () => {
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
};

const initSmsEditors = () => {
  const announcementHost = document.getElementById("smsAnnouncementEditor");
  if (announcementHost && !announcementHost.dataset.ready) {
    announcementHost.dataset.ready = "1";
    announcementEditor = createSmsEditor(announcementHost, {
      label: "متن اطلاعیه",
      placeholder: "متن آزاد، یا الگوی اشتراکی: 12345@مقدار۱;مقدار۲##shared",
      readonly: !canWrite,
      rows: 6,
    });
    if (!announcementHost.querySelector(".sms-pattern-hint")) {
      const hint = document.createElement("p");
      hint.className = "hint sms-pattern-hint";
      hint.textContent = "الگوی ملی‌پیامک: کدالگو@متغیر۱;متغیر۲##shared — در غیر این صورت متن به‌صورت آزاد از خط اختصاصی ارسال می‌شود.";
      announcementHost.appendChild(hint);
    }
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
  host.innerHTML = `
    <div class="month-stats">
      <div class="month-stat"><span>ارسال امروز</span><strong>${Number(stats.sent_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>باقی‌مانده امروز</span><strong>${Number(stats.remaining_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>ناموفق امروز</span><strong>${Number(stats.failed_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>هزینه امروز</span><strong>${formatMoney(stats.cost_today || 0)}</strong></div>
      <div class="month-stat"><span>موجودی پنل</span><strong>${stats.panel_credit != null ? Number(stats.panel_credit).toLocaleString("fa-IR") : "—"}</strong></div>
      <div class="month-stat"><span>تعرفه هر پیامک</span><strong>${formatMoney(stats.unit_cost || 0)}</strong></div>
    </div>
    ${stats.sms_configured ? "" : `<p class="hint">اتصال API هنوز کامل نیست — موجودی و تعرفه زنده بعد از تنظیمات نمایش داده می‌شود.</p>`}`;
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
};

const loadSmsHistory = async () => {
  const tbody = document.querySelector("#smsHistoryTable tbody");
  if (!tbody) return;
  const result = await fetchResource("api.php?resource=sms-history", { page: 1, perPage: 100 });
  tbody.innerHTML = (result.rows || []).map((row) => `<tr>
    <td>${escapeHtml(formatPlain(row.created_at))}</td>
    <td>${row.message_type === "announcement" ? "اطلاعیه" : "ارسالی"}</td>
    <td>${escapeHtml(row.recipient_name || "—")}</td>
    <td>${escapeHtml(row.phone || "—")}</td>
    <td>${escapeHtml(row.team_name || "—")}</td>
    <td><span class="badge">${escapeHtml(row.status || "—")}</span></td>
    <td>${escapeHtml(row.delivery_status || "—")}</td>
    <td>${Number(row.api_confirmed) === 1 ? "بله" : "خیر"}</td>
    <td>${formatMoney(row.cost_rial || 0)}</td>
    <td title="${escapeHtml(row.message_text || "")}">${escapeHtml((row.message_text || "").slice(0, 40))}${(row.message_text || "").length > 40 ? "…" : ""}</td>
  </tr>`).join("") || `<tr class="empty-row"><td colspan="10">تاریخچه‌ای ثبت نشده است.</td></tr>`;
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
