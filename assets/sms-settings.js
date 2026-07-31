/* global fetchJson, postJson, showToast, escapeHtml, formatMoney, canWrite, createSmsEditor, SMS_CHARGE_VARS, SMS_WORKFLOW_TEMPLATE_LABELS */

let smsSettingsState = null;
let smsSettingsReady = false;
let chargeTemplateEditor = null;
const workflowTemplateEditors = {};

const loadSmsSettingsPage = async (withLive = false) => {
  const data = await fetchJson(`api.php?resource=sms-settings${withLive ? "&live=1" : ""}`);
  smsSettingsState = data;
  renderSmsCredentialsForm(data);
  renderSmsLineForm(data);
  renderSmsChargeTemplateEditor(data);
  renderSmsWorkflowTemplatesEditor(data);
  renderSmsPatternGuide();
  renderSmsSettingsStats(data);
};

const renderSmsCredentialsForm = (data) => {
  const host = document.getElementById("smsCredentialsForm");
  if (!host) return;
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>نام کاربری ملی‌پیامک</span><input name="sms_username" value="${escapeHtml(data.sms_username || "")}" ${canWrite ? "" : "readonly"} required /></label>
      <label><span>رمز عبور API</span><input name="sms_password" type="password" placeholder="${data.sms_password_set ? "برای تغییر وارد کنید" : "رمز API"}" ${canWrite ? "" : "readonly"} /></label>
    </div>
    <p class="hint">نام کاربری و رمز REST پنل ملی‌پیامک (نه رمز ورود وب‌سایت).</p>
    ${canWrite ? `<div class="panel-actions"><button class="button" type="submit">ذخیره حساب API</button></div>` : `<p class="hint">فقط مشاهده</p>`}`;

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

const renderSmsChargeTemplateEditor = (data) => {
  const host = document.getElementById("smsChargeTemplateEditor");
  const form = document.getElementById("smsChargeTemplateForm");
  if (!host) return;
  if (!chargeTemplateEditor) {
    chargeTemplateEditor = createSmsEditor(host, {
      label: "الگوی پیامک یادآوری شارژ",
      placeholder: "مثال: 12345@{team_name}##{debt_total}##shared",
      value: data.sms_charge_template || "",
      readonly: !canWrite,
      variables: SMS_CHARGE_VARS,
      rows: 5,
    });
  } else {
    chargeTemplateEditor.setValue(data.sms_charge_template || "");
  }
  if (!form || form.dataset.ready) return;
  form.dataset.ready = "1";
  if (!canWrite) return;
  form.onsubmit = async (event) => {
    event.preventDefault();
    try {
      const result = await postJson("api.php?resource=sms-settings", {
        section: "charge_template",
        sms_charge_template: chargeTemplateEditor?.getValue() || "",
      });
      smsSettingsState = result.settings || result;
      showToast("الگوی یادآوری شارژ ذخیره شد.", "success");
      renderSmsChargeTemplateEditor(smsSettingsState);
    } catch (error) {
      showToast(error.message, "error");
    }
  };
};

const renderSmsWorkflowTemplatesEditor = (data) => {
  const host = document.getElementById("smsWorkflowTemplatesEditor");
  const form = document.getElementById("smsWorkflowTemplatesForm");
  if (!host) return;
  const templates = data.sms_workflow_templates || {};
  host.innerHTML = Object.entries(SMS_WORKFLOW_TEMPLATE_LABELS).map(([key, label]) => {
    const editorId = `sms-workflow-${key}`;
    return `<div class="charge-reminder-card" data-workflow-key="${escapeHtml(key)}">
      <div class="charge-reminder-head"><strong>${escapeHtml(label)}</strong></div>
      <div id="${escapeHtml(editorId)}"></div>
    </div>`;
  }).join("");

  Object.keys(SMS_WORKFLOW_TEMPLATE_LABELS).forEach((key) => {
    const mount = document.getElementById(`sms-workflow-${key}`);
    if (!mount) return;
    workflowTemplateEditors[key] = createSmsEditor(mount, {
      label: SMS_WORKFLOW_TEMPLATE_LABELS[key],
      value: templates[key] || "",
      readonly: !canWrite,
      rows: 3,
      showPreview: false,
    });
  });

  if (!form || form.dataset.ready) return;
  form.dataset.ready = "1";
  if (!canWrite) return;
  form.onsubmit = async (event) => {
    event.preventDefault();
    try {
      const payload = {};
      Object.keys(SMS_WORKFLOW_TEMPLATE_LABELS).forEach((key) => {
        payload[key] = workflowTemplateEditors[key]?.getValue() || "";
      });
      const result = await postJson("api.php?resource=sms-settings", {
        section: "workflow_templates",
        sms_workflow_templates: payload,
      });
      smsSettingsState = result.settings || result;
      showToast("پیامک‌های گردش‌کار ذخیره شد.", "success");
      renderSmsWorkflowTemplatesEditor(smsSettingsState);
    } catch (error) {
      showToast(error.message, "error");
    }
  };
};

const renderSmsPatternGuide = async () => {
  const host = document.getElementById("smsPatternGuide");
  if (!host) return;
  try {
    const data = await fetchJson("api.php?resource=sms-patterns");
    host.innerHTML = (data.patterns || []).map((row) => `
      <div class="charge-reminder-card">
        <div class="charge-reminder-head">
          <strong>${escapeHtml(row.title || row.pattern_key || "")}</strong>
          <span class="hint" dir="ltr">bodyId: ${escapeHtml(String(row.body_id || ""))}</span>
        </div>
        <p class="hint">متغیرهای سیستم: ${escapeHtml((row.variables || []).join("، "))}</p>
        <textarea readonly rows="3" dir="rtl">${escapeHtml(row.panel_text || "")}</textarea>
        <p class="hint" dir="ltr">الگوی سیستم: ${escapeHtml(row.system_template || "")}</p>
      </div>`).join("") || `<div class="empty">الگویی ثبت نشده است.</div>`;
  } catch (error) {
    host.innerHTML = `<div class="empty">خطا در بارگذاری راهنما: ${escapeHtml(error.message)}</div>`;
  }
};

const renderSmsLineForm = (data) => {
  const host = document.getElementById("smsLineForm");
  if (!host) return;
  host.innerHTML = `
    <div class="crud-grid">
      <label><span>شماره خط ارسال</span>
        <input name="sms_from_number" type="text" inputmode="numeric" placeholder="مثلاً 3000xxxx یا 5000xxxx" value="${escapeHtml(data.sms_from_number || "")}" ${canWrite ? "required" : "readonly"} />
      </label>
      <label><span>سقف ارسال روزانه (پنل)</span><input name="sms_daily_limit" type="number" min="1" value="${escapeHtml(data.sms_daily_limit || 500)}" ${canWrite ? "" : "readonly"} /></label>
      <label><span>هزینه هر پیامک (ریال — از API)</span><input name="sms_unit_cost" type="number" value="${escapeHtml(data.sms_base_price || data.sms_unit_cost || 0)}" readonly /></label>
    </div>
    <p class="hint">شماره خط را از پنل ملی‌پیامک کپی و اینجا دستی وارد کنید. بدون خط ذخیره‌شده، وضعیت اتصال «ناقص» می‌ماند.</p>
    ${!data.sms_configured ? `<p class="hint">پس از وارد کردن خط، دکمه «ذخیره خط و محدودیت» را بزنید.</p>` : ""}
    ${canWrite ? `<div class="panel-actions"><button class="button" type="submit">ذخیره خط و محدودیت</button></div>` : `<p class="hint">فقط مشاهده</p>`}`;

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

const formatCredit = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  return Number(value).toLocaleString("fa-IR");
};

const renderSmsSettingsStats = (data) => {
  const host = document.getElementById("smsSettingsStats");
  if (!host) return;
  const price = Number(data.sms_base_price ?? data.sms_unit_cost ?? 0);
  host.innerHTML = `
    <div class="month-stats">
      <div class="month-stat"><span>وضعیت اتصال</span><strong>${data.sms_configured ? "آماده ارسال" : "ناقص"}</strong></div>
      <div class="month-stat"><span>موجودی پنل</span><strong>${formatCredit(data.sms_credit)}</strong></div>
      <div class="month-stat"><span>تعرفه پایه</span><strong>${price > 0 ? formatMoney(price) : "—"}</strong></div>
      <div class="month-stat"><span>آخرین بروزرسانی زنده</span><strong>${escapeHtml(data.sms_live_synced_at || "—")}</strong></div>
      <div class="month-stat"><span>آخرین همگام‌سازی تاریخچه</span><strong>${escapeHtml(data.sms_history_synced_at || "—")}</strong></div>
    </div>`;
};

const bindSmsSettingsActions = () => {
  if (smsSettingsReady) return;
  smsSettingsReady = true;

  document.getElementById("smsTestConnection")?.addEventListener("click", async () => {
    try {
      const result = await postJson("api.php?resource=sms-test", {});
      const checks = result.result?.checks || {};
      const credit = checks.credit?.value;
      const price = checks.base_price?.value;
      const details = [
        credit != null ? `موجودی: ${formatCredit(credit)}` : null,
        price != null ? `تعرفه: ${formatMoney(price)}` : null,
      ].filter(Boolean).join(" — ");
      showToast(details ? `${result.result?.message || "اتصال OK"} (${details})` : (result.result?.message || "تست انجام شد."), result.result?.ok ? "success" : "error");
      if (result.result?.ok) await loadSmsSettingsPage(true);
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("smsRefreshLiveStats")?.addEventListener("click", async () => {
    try {
      const data = await fetchJson("api.php?resource=sms-settings&live=1");
      smsSettingsState = data;
      renderSmsLineForm(data);
      renderSmsSettingsStats(data);
      if (data.live_error) {
        showToast(data.live_error, "error");
        return;
      }
      showToast("آمار زنده از API بروز شد.", "success");
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

if (document.getElementById("sms-settings")?.classList.contains("active")) {
  window.initSmsSettingsPanel?.();
}
