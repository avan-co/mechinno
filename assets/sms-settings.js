/* global MECHINNO, fetchJson, postJson, showToast, escapeHtml, formatMoney, canWrite, csrfToken, createSmsEditor, SMS_EDITOR_VARS */

let smsSettingsState = null;
let templateEditor = null;

const loadSmsSettingsPage = async () => {
  const data = await fetchJson("api.php?resource=sms-settings");
  smsSettingsState = data;
  renderSmsSettingsForm(data);
  renderSmsSettingsStats(data);
  renderTemplateEditor(data.sms_charge_template || "");
};

const renderSmsSettingsForm = (data) => {
  const host = document.getElementById("smsSettingsForm");
  if (!host) return;
  const lines = Array.isArray(data.sms_line_numbers) ? data.sms_line_numbers : [];
  const lineOptions = lines.map((line) =>
    `<option value="${escapeHtml(line)}" ${line === data.sms_from_number ? "selected" : ""}>${escapeHtml(line)}</option>`
  ).join("");
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>نام کاربری ملی‌پیامک</span><input name="sms_username" value="${escapeHtml(data.sms_username || "")}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>رمز عبور API</span><input name="sms_password" type="password" placeholder="${data.sms_password_set ? "برای تغییر وارد کنید" : "رمز API"}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>خط ارسال</span>
        <select name="sms_from_number" ${canWrite ? "" : "disabled"}>
          <option value="">انتخاب خط…</option>
          ${lineOptions}
          ${data.sms_from_number && !lines.includes(data.sms_from_number) ? `<option value="${escapeHtml(data.sms_from_number)}" selected>${escapeHtml(data.sms_from_number)} (فعلی)</option>` : ""}
        </select>
      </label>
      <label><span>سقف ارسال روزانه (پنل)</span><input name="sms_daily_limit" type="number" value="${escapeHtml(data.sms_daily_limit || 500)}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>هزینه هر پیامک (ریال — از API)</span><input name="sms_unit_cost" type="number" value="${escapeHtml(data.sms_base_price || data.sms_unit_cost || 0)}" readonly /></label>
    </div>
    <p class="hint">${data.sms_lines_queried_at ? `آخرین استعلام خطوط: ${escapeHtml(data.sms_lines_queried_at)}` : "پس از اولین ذخیره، خطوط ارسال به‌صورت خودکار استعلام می‌شوند."}</p>
    ${canWrite ? `<div class="modal-actions"><button class="button" type="submit">ذخیره تنظیمات</button></div>` : `<p class="hint">فقط مشاهده</p>`}`;

  if (!canWrite) return;
  host.onsubmit = async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(host).entries());
    const result = await postJson("api.php?resource=sms-settings", payload);
    smsSettingsState = result.settings || result;
    showToast("تنظیمات پیامک ذخیره شد.", "success");
    renderSmsSettingsForm(smsSettingsState);
    renderSmsSettingsStats(smsSettingsState);
  };
};

const renderSmsSettingsStats = (data) => {
  const host = document.getElementById("smsSettingsStats");
  if (!host) return;
  host.innerHTML = `
    <div class="month-stats">
      <div class="month-stat"><span>موجودی پنل</span><strong>${data.sms_credit != null ? Number(data.sms_credit).toLocaleString("fa-IR") : "—"}</strong></div>
      <div class="month-stat"><span>تعرفه پایه</span><strong>${formatMoney(data.sms_base_price || data.sms_unit_cost || 0)}</strong></div>
      <div class="month-stat"><span>آخرین همگام‌سازی تاریخچه</span><strong>${escapeHtml(data.sms_history_synced_at || "—")}</strong></div>
    </div>`;
};

const renderTemplateEditor = (value) => {
  const host = document.getElementById("smsChargeTemplateEditor");
  if (!host) return;
  templateEditor = createSmsEditor(host, {
    label: "الگوی یادآور شارژ",
    value,
    readonly: !canWrite,
    variables: SMS_EDITOR_VARS,
    rows: 8,
  });
};

document.getElementById("smsSaveTemplate")?.addEventListener("click", async () => {
  if (!canWrite || !templateEditor) return;
  const payload = {
    sms_username: smsSettingsState?.sms_username || "",
    sms_from_number: smsSettingsState?.sms_from_number || "",
    sms_daily_limit: smsSettingsState?.sms_daily_limit || 500,
    sms_charge_template: templateEditor.getValue(),
  };
  await postJson("api.php?resource=sms-settings", payload);
  showToast("الگوی یادآور ذخیره شد.", "success");
});

document.getElementById("smsManualQueryLines")?.addEventListener("click", async () => {
  if (!canWrite) return;
  const result = await postJson("api.php?resource=sms-query-lines", {});
  showToast(`خطوط به‌روز شد — ${(result.result?.numbers || []).length} خط`, "success");
  await loadSmsSettingsPage();
});

document.getElementById("smsSyncHistory")?.addEventListener("click", async () => {
  const result = await postJson("api.php?resource=sms-sync-history", {});
  const synced = result.result?.synced ?? 0;
  const confirmed = result.result?.confirmed ?? 0;
  showToast(`همگام‌سازی انجام شد — تایید: ${confirmed}، جدید: ${synced}`, "success");
  await loadSmsSettingsPage();
});

loadSmsSettingsPage().catch((error) => showToast(error.message, "error"));
