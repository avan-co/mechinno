/* global escapeHtml */

window.SMS_EDITOR_VARS = [
  { key: "{team_name}", label: "نام نهاد" },
  { key: "{leader_name}", label: "نام مسئول" },
  { key: "{bank_info}", label: "اطلاعات بانکی" },
  { key: "{card_number}", label: "شماره کارت" },
];

window.SMS_CHARGE_VARS = [
  { key: "{team_name}", label: "نام نهاد" },
  { key: "{leader_name}", label: "نام مسئول" },
  { key: "{debt_total}", label: "مبلغ بدهی (عدد)" },
  { key: "{debt_total_formatted}", label: "مبلغ بدهی (با جداکننده)" },
  { key: "{debt_summary}", label: "ماه‌های بدهی" },
  { key: "{bank_info}", label: "اطلاعات بانکی" },
  { key: "{card_number}", label: "شماره کارت" },
  { key: "{account_number}", label: "شماره حساب" },
  { key: "{sheba}", label: "شماره شبا" },
];

window.SMS_WORKFLOW_TEMPLATE_LABELS = {
  room_pending: "رزرو — ثبت درخواست",
  room_approved: "رزرو — تأیید",
  room_rejected: "رزرو — رد",
  room_cancelled: "رزرو — لغو",
  member_approved: "عضو — تأیید",
  member_rejected: "عضو — رد",
  member_request_approved: "درخواست عضو — تأیید",
  member_request_rejected: "درخواست عضو — رد",
};

window.createSmsEditor = (host, options = {}) => {
  if (!host) return null;
  const {
    id = `sms-editor-${Math.random().toString(36).slice(2, 8)}`,
    label = "متن پیامک",
    placeholder = "متن پیامک را بنویسید…",
    value = "",
    readonly = false,
    variables = [],
    showPreview = true,
    rows = 6,
  } = options;

  host.innerHTML = `
    <div class="sms-editor" data-editor-id="${escapeHtml(id)}">
      <div class="sms-editor-head">
        <label class="sms-editor-label" for="${escapeHtml(id)}">${escapeHtml(label)}</label>
        <div class="sms-editor-meta">
          <span data-segments>۰ پیامک</span>
          <span data-chars>۰ / ۷۰</span>
        </div>
      </div>
      ${variables.length ? `<div class="sms-editor-vars">${variables.map((item) =>
        `<button type="button" class="chip-button" data-insert-var="${escapeHtml(item.key)}" ${readonly ? "disabled" : ""}>${escapeHtml(item.label)}</button>`
      ).join("")}</div>` : ""}
      <textarea id="${escapeHtml(id)}" class="sms-editor-input" rows="${rows}" placeholder="${escapeHtml(placeholder)}" ${readonly ? "readonly" : ""}>${escapeHtml(value)}</textarea>
      ${showPreview ? `<div class="sms-editor-preview"><span class="sms-editor-preview-label">پیش‌نمایش</span><pre data-preview></pre></div>` : ""}
    </div>`;

  const textarea = host.querySelector("textarea");
  const segmentsEl = host.querySelector("[data-segments]");
  const charsEl = host.querySelector("[data-chars]");
  const previewEl = host.querySelector("[data-preview]");

  const segmentInfo = (text) => {
    const len = [...text].length;
    const perSegment = 70;
    const segments = len === 0 ? 0 : Math.ceil(len / perSegment);
    const inCurrent = len === 0 ? 0 : ((len - 1) % perSegment) + 1;
    return { len, segments, inCurrent };
  };

  const renderMeta = () => {
    const text = textarea.value || "";
    const info = segmentInfo(text);
    if (segmentsEl) segmentsEl.textContent = `${info.segments.toLocaleString("fa-IR")} پیامک`;
    if (charsEl) charsEl.textContent = `${info.inCurrent.toLocaleString("fa-IR")} / ۷۰`;
    if (previewEl) previewEl.textContent = text || "—";
  };

  textarea.addEventListener("input", renderMeta);
  host.querySelectorAll("[data-insert-var]").forEach((button) => {
    button.addEventListener("click", () => {
      const token = button.dataset.insertVar || "";
      const start = textarea.selectionStart ?? textarea.value.length;
      const end = textarea.selectionEnd ?? textarea.value.length;
      const next = `${textarea.value.slice(0, start)}${token}${textarea.value.slice(end)}`;
      textarea.value = next;
      const caret = start + token.length;
      textarea.focus();
      textarea.setSelectionRange(caret, caret);
      renderMeta();
      textarea.dispatchEvent(new Event("input"));
    });
  });
  renderMeta();

  return {
    getValue: () => textarea.value.trim(),
    setValue: (text) => {
      textarea.value = text;
      renderMeta();
    },
    element: textarea,
    onChange: (handler) => textarea.addEventListener("input", handler),
  };
};
