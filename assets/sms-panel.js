/* global MECHINNO, fetchJson, fetchResource, postJson, showToast, escapeHtml, formatMoney, formatPlain, canWrite, csrfToken */

window.initSmsPanel = () => {
  loadSmsSettings().catch((error) => showToast(error.message, "error"));
  loadSmsStats().catch(() => {});
  loadSmsRecipients().catch((error) => showToast(error.message, "error"));
  loadSmsHistory().catch((error) => showToast(error.message, "error"));
  loadChargeReminderPanel().catch((error) => showToast(error.message, "error"));
};

const smsState = {
  recipients: [],
  selected: new Set(),
  chargeItems: [],
  selectedChargeTeams: new Set(),
  page: 1,
  perPage: 25,
  filters: { q: "", teamId: "", entityType: "", isLeader: "", wantsAccess: "" },
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

const bindSmsFilterEvents = () => {
  const root = document.getElementById("smsRecipientsPanel");
  if (!root || root.dataset.ready) return;
  root.dataset.ready = "1";
  root.querySelector("#smsFilterSearch")?.addEventListener("input", (event) => {
    clearTimeout(root.searchTimer);
    root.searchTimer = setTimeout(() => {
      smsState.filters.q = event.target.value.trim();
      smsState.page = 1;
      loadSmsRecipients().catch((error) => showToast(error.message, "error"));
    }, 300);
  });
  ["Team", "EntityType", "Leader", "Access"].forEach((suffix) => {
    root.querySelector(`#smsFilter${suffix}`)?.addEventListener("change", (event) => {
      const map = { Team: "teamId", EntityType: "entityType", Leader: "isLeader", Access: "wantsAccess" };
      smsState.filters[map[suffix]] = event.target.value;
      smsState.page = 1;
      loadSmsRecipients().catch((error) => showToast(error.message, "error"));
    });
  });
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

const loadSmsSettings = async () => {
  const host = document.getElementById("smsSettingsForm");
  if (!host) return;
  const data = await fetchJson("api.php?resource=sms-settings");
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>نام کاربری ملی‌پیامک</span><input name="sms_username" value="${escapeHtml(data.sms_username || "")}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>رمز عبور API</span><input name="sms_password" type="password" placeholder="${data.sms_password_set ? "برای تغییر وارد کنید" : "رمز API"}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>شماره خط ارسال</span><input name="sms_from_number" value="${escapeHtml(data.sms_from_number || "")}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>سقف ارسال روزانه</span><input name="sms_daily_limit" type="number" value="${escapeHtml(data.sms_daily_limit || 500)}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>هزینه هر پیامک (ریال)</span><input name="sms_unit_cost" type="number" value="${escapeHtml(data.sms_unit_cost || 0)}" ${canWrite ? "" : "readonly"} /></label>
    </div>
    ${canWrite ? `<div class="modal-actions"><button class="button" type="submit">ذخیره تنظیمات پیامک</button></div>` : `<p class="hint">فقط مشاهده — ارسال برای مدیر ویرایشگر است.</p>`}`;

  if (!canWrite) return;
  host.onsubmit = async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(host).entries());
    await postJson("api.php?resource=sms-settings", payload);
    showToast("تنظیمات پیامک ذخیره شد.", "success");
    loadSmsStats().catch(() => {});
  };
};

const loadSmsStats = async () => {
  const host = document.getElementById("smsStats");
  if (!host) return;
  const stats = await fetchJson("api.php?resource=sms-stats");
  host.innerHTML = `
    <div class="month-stats">
      <div class="month-stat"><span>ارسال امروز</span><strong>${Number(stats.sent_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>باقی‌مانده امروز</span><strong>${Number(stats.remaining_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>ناموفق امروز</span><strong>${Number(stats.failed_today || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>هزینه امروز</span><strong>${formatMoney(stats.cost_today || 0)}</strong></div>
      <div class="month-stat"><span>کل ارسال‌ها</span><strong>${Number(stats.total_sent || 0).toLocaleString("fa-IR")}</strong></div>
      <div class="month-stat"><span>کل هزینه</span><strong>${formatMoney(stats.total_cost || 0)}</strong></div>
    </div>`;
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
  }).join("") || `<tr><td colspan="5">گیرنده‌ای یافت نشد.</td></tr>`;
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

const populateSmsTeamFilter = async () => {
  const select = document.getElementById("smsFilterTeam");
  if (!select || select.dataset.ready) return;
  const meta = await fetchJson("api.php?resource=crud-meta");
  const teamOptions = meta.resources?.members?.fields?.team_id?.options || {};
  select.innerHTML = `<option value="">همه نهادها</option>${Object.entries(teamOptions).map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join("")}`;
  select.dataset.ready = "1";
};

const loadSmsRecipients = async () => {
  await populateSmsTeamFilter();
  bindSmsFilterEvents();
  const result = await fetchResource("api.php?resource=sms-recipients", smsRecipientFilters());
  smsState.recipients = result.rows || [];
  smsState.page = result.page;
  smsState.perPage = result.per_page;
  renderSmsRecipients();
  const pager = document.getElementById("smsRecipientsPager");
  if (pager) {
    pager.textContent = `صفحه ${result.page.toLocaleString("fa-IR")} از ${result.pages.toLocaleString("fa-IR")}`;
    pager.querySelector("[data-sms-prev]")?.toggleAttribute("disabled", result.page <= 1);
    pager.querySelector("[data-sms-next]")?.toggleAttribute("disabled", result.page >= result.pages);
  }
};

const sendSmsAnnouncement = async () => {
  if (!canWrite) throw new Error("دسترسی ارسال ندارید.");
  const message = document.getElementById("smsAnnouncementText")?.value?.trim();
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
};

const loadSmsHistory = async () => {
  const tbody = document.querySelector("#smsHistoryTable tbody");
  if (!tbody) return;
  const result = await fetchResource("api.php?resource=sms-history", { page: 1, perPage: 50 });
  tbody.innerHTML = (result.rows || []).map((row) => `<tr>
    <td>${escapeHtml(formatPlain(row.created_at))}</td>
    <td>${row.message_type === "charge_reminder" ? "یادآور شارژ" : "اطلاعیه"}</td>
    <td>${escapeHtml(row.recipient_name || "—")}</td>
    <td>${escapeHtml(row.phone || "—")}</td>
    <td>${escapeHtml(row.team_name || "—")}</td>
    <td><span class="badge">${escapeHtml(row.status || "—")}</span></td>
    <td>${formatMoney(row.cost_rial || 0)}</td>
    <td title="${escapeHtml(row.message_text || "")}">${escapeHtml((row.message_text || "").slice(0, 48))}${(row.message_text || "").length > 48 ? "…" : ""}</td>
    <td>${escapeHtml(row.error_message || "—")}</td>
  </tr>`).join("") || `<tr><td colspan="9">تاریخچه‌ای ثبت نشده است.</td></tr>`;
};

const renderChargeReminderPanel = () => {
  const host = document.getElementById("smsChargeReminderList");
  if (!host) return;
  host.innerHTML = smsState.chargeItems.map((item) => {
    const checked = smsState.selectedChargeTeams.has(item.team_id) ? "checked" : "";
    return `<article class="charge-reminder-card">
      <label class="charge-reminder-head">
        <input type="checkbox" data-charge-team="${item.team_id}" ${checked} ${canWrite ? "" : "disabled"} />
        <strong>${escapeHtml(item.team_name)} — ${escapeHtml(item.leader_name || "—")}</strong>
        <span>${formatMoney(item.debt_total)}</span>
      </label>
      <textarea data-charge-message="${item.team_id}" rows="5" ${canWrite ? "" : "readonly"}>${escapeHtml(item.message || "")}</textarea>
    </article>`;
  }).join("") || `<div class="empty">نهاد بدهکاری برای یادآور یافت نشد.</div>`;

  host.querySelectorAll("[data-charge-team]").forEach((input) => {
    input.addEventListener("change", () => {
      const teamId = Number(input.dataset.chargeTeam);
      if (input.checked) smsState.selectedChargeTeams.add(teamId);
      else smsState.selectedChargeTeams.delete(teamId);
    });
  });
};

const loadChargeReminderPanel = async () => {
  const data = await fetchJson("api.php?resource=sms-charge-preview");
  smsState.chargeItems = data.items || [];
  smsState.selectedChargeTeams = new Set(smsState.chargeItems.map((item) => Number(item.team_id)));
  renderChargeReminderPanel();
  const sendBtn = document.getElementById("smsSendChargeReminders");
  if (!sendBtn || sendBtn.dataset.ready) return;
  sendBtn.dataset.ready = "1";
  sendBtn.addEventListener("click", () => {
    sendChargeReminders().catch((error) => showToast(error.message, "error"));
  });
};

const sendChargeReminders = async () => {
  if (!canWrite) throw new Error("دسترسی ارسال ندارید.");
  const items = smsState.chargeItems
    .filter((item) => smsState.selectedChargeTeams.has(Number(item.team_id)))
    .map((item) => ({
      member_id: item.member_id,
      message: document.querySelector(`[data-charge-message="${item.team_id}"]`)?.value?.trim() || "",
    }));
  if (!items.length) throw new Error("حداقل یک نهاد بدهکار انتخاب کنید.");
  if (!window.confirm(`یادآور شارژ برای ${items.length} مسئول ارسال شود؟`)) return;
  const result = await postJson("api.php?resource=sms-send-charge-reminders", { items });
  showToast(`یادآور ارسال شد — موفق: ${result.result?.sent || 0}، ناموفق: ${result.result?.failed || 0}`, "success");
  await loadSmsStats();
  await loadSmsHistory();
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
