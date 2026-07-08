/* global fetchJson, postJson, showToast, escapeHtml, formatMoney, canWrite, createSmsEditor, SMS_EDITOR_VARS */

let smsSettingsState = null;
let templateEditor = null;
let smsSettingsReady = false;

const loadSmsSettingsPage = async (withLive = false) => {
  const data = await fetchJson(`api.php?resource=sms-settings${withLive ? "&live=1" : ""}`);
  smsSettingsState = data;
  renderSmsCredentialsForm(data);
  renderSmsLineForm(data);
  renderSmsSettingsStats(data);
  renderTemplateEditor(data.sms_charge_template || "");
};

const renderSmsCredentialsForm = (data) => {
  const host = document.getElementById("smsCredentialsForm");
  if (!host) return;
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>نام کاربری ملی‌پیامک</span><input name="sms_username" value="${escapeHtml(data.sms_username || "")}" ${canWrite ? "" : "readonly"} required /></label>
      <label><span>رمز عبور API</span><input name="sms_password" type="password" placeholder="${data.sms_password_set ? "برای تغییر وارد کنید" : "رمز API"}" ${canWrite ? "" : "readonly"} /></label>
    </div>
    <p class="hint">پس از ذخیره، خطوط ارسال به‌صورت خودکار از API استعلام می‌شوند.</p>
    ${canWrite ? `<div class="modal-actions"><button class="button" type="submit">ذخیره حساب API</button></div>` : `<p class="hint">فقط مشاهده</p>`}`;

  if (!canWrite) return;
  host.onsubmit = async (event) => {
    event.preventDefault();
    try {
      const formData = Object.fromEntries(new FormData(host).entries());
      const result = await postJson("api.php?resource=sms-settings", {
        section: "credentials",
        sms_username: formData.sms_username || "",
        sms_password: formData.sms_password || "",
      });
      smsSettingsState = result.settings || result;
      showToast("حساب API ذخیره شد.", "success");
      renderSmsCredentialsForm(smsSettingsState);
      renderSmsLineForm(smsSettingsState);
      renderSmsSettingsStats(smsSettingsState);
    } catch (error) {
      showToast(error.message, "error");
    }
  };
};

const renderSmsLineForm = (data) => {
  const host = document.getElementById("smsLineForm");
  if (!host) return;
  const lines = Array.isArray(data.sms_line_numbers) ? data.sms_line_numbers : [];
  const lineOptions = lines.map((line) =>
    `<option value="${escapeHtml(line)}" ${line === data.sms_from_number ? "selected" : ""}>${escapeHtml(line)}</option>`
  ).join("");
  const hasCredentials = Boolean((data.sms_username || "").trim() && data.sms_password_set);
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>خط ارسال</span>
        <select name="sms_from_number" ${canWrite ? "" : "disabled"}>
          <option value="">انتخاب خط…</option>
          ${lineOptions}
          ${data.sms_from_number && !lines.includes(data.sms_from_number) ? `<option value="${escapeHtml(data.sms_from_number)}" selected>${escapeHtml(data.sms_from_number)} (فعلی)</option>` : ""}
        </select>
      </label>
      <label><span>سقف ارسال روزانه (پنل)</span><input name="sms_daily_limit" type="number" min="1" value="${escapeHtml(data.sms_daily_limit || 500)}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>هزینه هر پیامک (ریال — از API)</span><input name="sms_unit_cost" type="number" value="${escapeHtml(data.sms_base_price || data.sms_unit_cost || 0)}" readonly /></label>
    </div>
    <p class="hint">${data.sms_lines_queried_at ? `آخرین استعلام خطوط: ${escapeHtml(data.sms_lines_queried_at)}` : hasCredentials ? "خطی یافت نشد — دکمه «استعلام مجدد خطوط» را بزنید." : "ابتدا حساب API را ذخیره کنید."}</p>
    ${!data.sms_configured && hasCredentials ? `<p class="hint">برای تکمیل تنظیمات، یک خط ارسال انتخاب و ذخیره کنید.</p>` : ""}
    ${canWrite ? `<div class="modal-actions"><button class="button" type="submit">ذخیره خط و محدودیت</button></div>` : `<p class="hint">فقط مشاهده</p>`}`;

  if (!canWrite) return;
  host.onsubmit = async (event) => {
    event.preventDefault();
    try {
      const formData = Object.fromEntries(new FormData(host).entries());
      const result = await postJson("api.php?resource=sms-settings", {
        section: "line",
        sms_from_number: formData.sms_from_number || "",
        sms_daily_limit: formData.sms_daily_limit || 500,
      });
      smsSettingsState = result.settings || result;
      showToast("خط ارسال ذخیره شد.", "success");
      renderSmsLineForm(smsSettingsState);
      renderSmsSettingsStats(smsSettingsState);
    } catch (error) {
      showToast(error.message, "error");
    }
  };
};

const renderSmsSettingsStats = (data) => {
  const host = document.getElementById("smsSettingsStats");
  if (!host) return;
  host.innerHTML = `
    <div class="month-stats">
      <div class="month-stat"><span>وضعیت اتصال</span><strong>${data.sms_configured ? "آماده ارسال" : "ناقص"}</strong></div>
      <div class="month-stat"><span>موجودی پنل</span><strong>${data.sms_credit != null ? Number(data.sms_credit).toLocaleString("fa-IR") : "—"}</strong></div>
      <div class="month-stat"><span>تعرفه پایه</span><strong>${formatMoney(data.sms_base_price || data.sms_unit_cost || 0)}</strong></div>
      <div class="month-stat"><span>آخرین همگام‌سازی تاریخچه</span><strong>${escapeHtml(data.sms_history_synced_at || "—")}</strong></div>
    </div>`;
};

const renderTemplateEditor = (value) => {
  const host = document.getElementById("smsChargeTemplateEditor");
  if (!host) return;
  if (host.dataset.ready) {
    templateEditor?.setValue(value);
    return;
  }
  host.dataset.ready = "1";
  templateEditor = createSmsEditor(host, {
    label: "الگوی یادآور شارژ",
    value,
    readonly: !canWrite,
    variables: SMS_EDITOR_VARS,
    rows: 8,
  });
};

const bindSmsSettingsActions = () => {
  if (smsSettingsReady) return;
  smsSettingsReady = true;

  document.getElementById("smsSaveTemplate")?.addEventListener("click", async () => {
    if (!canWrite || !templateEditor) return;
    try {
      await postJson("api.php?resource=sms-settings", {
        section: "template",
        sms_charge_template: templateEditor.getValue(),
      });
      showToast("الگوی یادآور ذخیره شد.", "success");
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("smsRefreshLiveStats")?.addEventListener("click", async () => {
    try {
      await loadSmsSettingsPage(true);
      showToast("آمار زنده از API بروز شد.", "success");
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("smsManualQueryLines")?.addEventListener("click", async () => {
    if (!canWrite) return;
    try {
      const result = await postJson("api.php?resource=sms-query-lines", {});
      showToast(`خطوط به‌روز شد — ${(result.result?.numbers || []).length} خط`, "success");
      await loadSmsSettingsPage();
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("smsSyncHistory")?.addEventListener("click", async () => {
    try {
      const result = await postJson("api.php?resource=sms-sync-history", {});
      const synced = result.result?.synced ?? 0;
      const confirmed = result.result?.confirmed ?? 0;
      showToast(`همگام‌سازی انجام شد — تایید: ${confirmed}، جدید: ${synced}`, "success");
      await loadSmsSettingsPage();
    } catch (error) {
      showToast(error.message, "error");
    }
  });
};

window.initSmsSettingsPanel = () => {
  bindSmsSettingsActions();
  loadSmsSettingsPage().catch((error) => showToast(error.message, "error"));
};
