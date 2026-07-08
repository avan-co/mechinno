/* global MechinnoShared */
(() => {
  const S = () => window.MechinnoShared;
  const currentFiscalYear = () => S()?.MECHINNO?.fiscalYear || "1405";

  const fiscalYearFromDate = (value) => {
    const normalized = String(value || "").trim();
    return normalized.length >= 4 ? normalized.slice(0, 4) : "";
  };

  const collectYears = (profile) => {
    const years = new Set([currentFiscalYear()]);
    (profile.year_summaries || []).forEach((row) => {
      if (row.fiscal_year) years.add(String(row.fiscal_year));
    });
    (profile.contracts || []).forEach((row) => {
      if (row.fiscal_year) years.add(String(row.fiscal_year));
    });
    (profile.desk_assignments || []).forEach((row) => {
      const year = row.fiscal_year || fiscalYearFromDate(row.assigned_from);
      if (year) years.add(String(year));
    });
    return [...years].sort((a, b) => Number(b) - Number(a));
  };

  const yearSummary = (profile, year) => {
    const found = (profile.year_summaries || []).find((row) => String(row.fiscal_year) === String(year));
    if (found) return found;
    const contract = (profile.contracts || []).find((row) => String(row.fiscal_year) === String(year));
    const desks = (profile.desk_assignments || []).filter((row) => {
      const rowYear = row.fiscal_year || fiscalYearFromDate(row.assigned_from);
      return String(rowYear) === String(year);
    });
    return {
      fiscal_year: year,
      has_contract: Boolean(contract),
      contract_id: contract?.id || null,
      contract_start: contract?.contract_start || null,
      contract_end: contract?.contract_end || null,
      contract_notes: contract?.notes || null,
      desk_count: desks.length,
      charge_total: 0,
      paid_total: 0,
      debt_total: 0,
      is_current_year: String(year) === String(currentFiscalYear()),
    };
  };

  const desksForYear = (profile, year) => (profile.desk_assignments || []).filter((row) => {
    const rowYear = row.fiscal_year || fiscalYearFromDate(row.assigned_from);
    return String(rowYear) === String(year);
  });

  const chargesForYear = (profile, year) => (profile.charges || []).filter((row) => String(row.fiscal_year) === String(year));

  const checklistItem = (ok, label) => {
    const cls = ok ? "year-check-ok" : "year-check-miss";
    const icon = ok ? "✓" : "○";
    return `<span class="year-check-item ${cls}"><span class="year-check-icon">${icon}</span>${S().escapeHtml(label)}</span>`;
  };

  const renderYearChecklist = (summary) => `
    <div class="year-checklist">
      ${checklistItem(summary.has_contract, "قرارداد")}
      ${checklistItem(Number(summary.desk_count || 0) > 0, `${summary.desk_count || 0} میز`)}
      ${checklistItem(Number(summary.debt_total || 0) <= 0, Number(summary.debt_total || 0) > 0 ? `بدهی ${S().formatMoney(summary.debt_total)}` : "بدون بدهی")}
    </div>`;

  const renderYearTabs = (years, selectedYear, teamId, canAdd) => `
    <div class="year-tabs" role="tablist" aria-label="سال‌های مالی">
      ${years.map((year) => {
        const active = String(year) === String(selectedYear);
        return `<button type="button" class="year-tab${active ? " active" : ""}${String(year) === String(currentFiscalYear()) ? " is-current" : ""}"
          data-year-tab="${S().escapeHtml(year)}" role="tab" aria-selected="${active ? "true" : "false"}">${S().escapeHtml(year)}</button>`;
      }).join("")}
      ${canAdd ? `<button type="button" class="year-tab year-tab-add" data-year-add data-team-id="${teamId}">+ ثبت سال</button>` : ""}
    </div>`;

  const renderContractPanel = (profile, year, summary, teamId, writable) => {
    const contract = (profile.contracts || []).find((row) => String(row.fiscal_year) === String(year));
    const isPast = String(year) !== String(currentFiscalYear());
    if (!writable) {
      if (!contract) {
        return `<article class="year-panel"><h3>قرارداد</h3><div class="empty">قراردادی برای این سال ثبت نشده است.</div></article>`;
      }
      return `<article class="year-panel">
        <h3>قرارداد</h3>
        <div class="year-contract-readonly">
          <div><span>شروع</span><strong>${S().escapeHtml(S().formatPlain(contract.contract_start))}</strong></div>
          <div><span>پایان</span><strong>${S().escapeHtml(S().formatPlain(contract.contract_end))}</strong></div>
          ${contract.notes ? `<p class="hint">${S().escapeHtml(contract.notes)}</p>` : ""}
        </div>
      </article>`;
    }

    return `<article class="year-panel">
      <div class="year-panel-head">
        <h3>قرارداد ${S().escapeHtml(year)}</h3>
        ${isPast ? `<span class="badge badge-partial">سال گذشته — ویرایش با احتیاط</span>` : ""}
      </div>
      <form class="year-contract-form" data-year-contract data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">
        <input type="hidden" name="contract_id" value="${contract?.id || ""}" />
        <div class="crud-grid year-form-grid">
          <label><span>شروع قرارداد</span><input name="contract_start" type="text" required value="${S().escapeHtml(contract?.contract_start || `${year}/01/01`)}" placeholder="${year}/01/01" /></label>
          <label><span>پایان قرارداد</span><input name="contract_end" type="text" required value="${S().escapeHtml(contract?.contract_end || `${year}/12/29`)}" placeholder="${year}/12/29" /></label>
          <label class="wide"><span>توضیحات</span><textarea name="notes" rows="2">${S().escapeHtml(contract?.notes || "")}</textarea></label>
        </div>
        <div class="year-panel-actions">
          <button class="button" type="submit">${contract ? "ذخیره قرارداد" : "ثبت قرارداد"}</button>
          ${contract ? `<button class="button danger ghost" type="button" data-delete-contract data-contract-id="${contract.id}" data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">حذف قرارداد سال</button>` : ""}
        </div>
      </form>
    </article>`;
  };

  const renderDeskPanel = (profile, year, teamId, writable) => {
    const desks = desksForYear(profile, year);
    const isCurrent = String(year) === String(currentFiscalYear());
    const rows = desks.length
      ? desks.map((row) => `
        <tr>
          <td>${S().deskLink ? S().deskLink(row.desk_number) : S().escapeHtml(row.desk_number)}</td>
          <td>${S().escapeHtml(S().usageLabels[row.usage_type] || row.usage_type || "—")}</td>
          <td>${S().escapeHtml(S().formatPlain(row.assigned_from))}</td>
          <td>${S().escapeHtml(S().formatPlain(row.assigned_until || "—"))}</td>
          <td>${S().escapeHtml(row.notes || "—")}</td>
          ${writable ? `<td class="row-actions">
            <button class="mini-button" type="button" data-edit-assignment data-id="${row.id}">ویرایش</button>
            <button class="mini-button danger" type="button" data-delete-assignment data-id="${row.id}">حذف</button>
          </td>` : ""}
        </tr>`).join("")
      : `<tr><td colspan="${writable ? 6 : 5}">میزی برای این سال ثبت نشده است.</td></tr>`;

    return `<article class="year-panel">
      <div class="year-panel-head">
        <h3>میزهای سال ${S().escapeHtml(year)}</h3>
        ${isCurrent && writable ? `<button type="button" class="button ghost" data-go-desks-map>نقشه میز (سال جاری)</button>` : ""}
      </div>
      ${!isCurrent && writable ? `<p class="hint warning-text">ویرایش میز سال گذشته فقط روی تاریخچه اثر می‌گذارد و وضعیت فعلی میزها را عوض نمی‌کند.</p>` : ""}
      <div class="table-wrap">
        <table class="data-table year-desk-table">
          <thead><tr>
            <th>میز</th><th>نوع</th><th>از</th><th>تا</th><th>یادداشت</th>${writable ? "<th>عملیات</th>" : ""}
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
      ${writable ? `<button type="button" class="button ghost" data-add-assignment data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">+ افزودن میز به این سال</button>` : ""}
    </article>`;
  };

  const renderChargesPanel = (profile, year, teamId, writable) => {
    const summary = yearSummary(profile, year);
    const chargeRows = chargesForYear(profile, year);
    return `<article class="year-panel">
      <div class="year-panel-head"><h3>بدهی سال ${S().escapeHtml(year)}</h3></div>
      <div class="year-finance-grid">
        <div class="month-stat"><span>جمع شارژ</span><strong>${S().escapeHtml(S().formatMoney(summary.charge_total || 0))}</strong></div>
        <div class="month-stat"><span>پرداخت‌شده</span><strong>${S().escapeHtml(S().formatMoney(summary.paid_total || 0))}</strong></div>
        <div class="month-stat"><span>مانده</span><strong class="debt-value">${S().escapeHtml(S().formatMoney(summary.debt_total || 0))}</strong></div>
      </div>
      ${writable ? `<div class="year-panel-actions">
        <button type="button" class="button ghost" data-recalc-year data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">محاسبه مجدد شهریه</button>
        <button type="button" class="button ghost" data-profile-deposit data-team-id="${teamId}">ثبت دریافت</button>
        <button type="button" class="button ghost" data-go-charges>مشاهده کلاژ شارژ</button>
      </div>` : ""}
      ${chargeRows.length ? `<details class="year-charge-details"><summary>جزئیات ماهانه (${chargeRows.length} ماه)</summary>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>ماه</th><th>شارژ</th><th>اجاره</th><th>جمع</th></tr></thead>
        <tbody>${chargeRows.map((row) => `<tr>
          <td>${S().escapeHtml(row.month_name || row.month_index)}</td>
          <td>${S().escapeHtml(S().formatMoney(row.charge_amount))}</td>
          <td>${S().escapeHtml(S().formatMoney(row.rent_amount))}</td>
          <td>${S().escapeHtml(S().formatMoney(row.amount))}</td>
        </tr>`).join("")}</tbody></table></div>
      </details>` : ""}
    </article>`;
  };

  const renderWorkspace = (profile, teamId, selectedYear, options = {}) => {
    const writable = options.writable === true;
    const years = collectYears(profile);
    const year = selectedYear && years.includes(String(selectedYear)) ? String(selectedYear) : years[0];
    const summary = yearSummary(profile, year);
    const team = profile.team || {};

    return `
      <div class="team-year-workspace" data-team-workspace data-team-id="${teamId}" data-selected-year="${S().escapeHtml(year)}">
        <div class="team-year-header">
          <div>
            <h2 class="team-year-title">${S().escapeHtml(team.name || "نهاد")}</h2>
            <p class="hint">${S().entityBadge(team.entity_type)} · کد ${S().escapeHtml(team.entity_code || "—")} · مسئول ${S().escapeHtml(team.leader || "—")}</p>
          </div>
          ${writable ? `<div class="profile-actions team-year-top-actions">
            <button type="button" class="button" data-profile-action="add-member">افزودن عضو</button>
            <button type="button" class="button ghost" data-year-wizard data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">ویزارد ثبت سابقه</button>
            <button type="button" class="button ghost" data-profile-action="charges">شارژ</button>
          </div>` : ""}
        </div>
        ${renderYearTabs(years, year, teamId, writable)}
        ${renderYearChecklist(summary)}
        <div class="year-workspace-panels">
          ${renderContractPanel(profile, year, summary, teamId, writable)}
          ${renderDeskPanel(profile, year, teamId, writable)}
          ${renderChargesPanel(profile, year, teamId, writable)}
        </div>
        <details class="year-extra-section">
          <summary>اعضا، کمدها و پرداخت‌ها</summary>
          ${S().profileSection("اعضا", profile.members || [], ["member_code", "full_name", "access_code", "phone", "national_id"])}
          ${S().profileSection("کمدها", profile.lockers || [], ["locker_number", "status", "delivered_at", "key_number"])}
          ${S().profileSection("دریافت شارژ از نهاد", profile.payments || [], ["tx_date", "fiscal_year", "month_name", "amount"])}
        </details>
      </div>`;
  };

  const reloadWorkspace = async (container, teamId, selectedYear) => {
    const profile = await S().fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
    const writable = S().canWrite && S().panelMode === "admin";
    container.innerHTML = renderWorkspace(profile, teamId, selectedYear, { writable });
    bindWorkspace(container, teamId, reloadWorkspace);
    container.classList.add("is-ready");
    return profile;
  };

  const saveContractForm = async (form) => {
    const teamId = Number(form.dataset.teamId);
    const year = form.dataset.year;
    const contractId = Number(form.contract_id?.value || 0);
    const payload = {
      team_id: String(teamId),
      fiscal_year: year,
      contract_start: form.contract_start.value,
      contract_end: form.contract_end.value,
      notes: form.notes.value,
    };
    const isPast = String(year) !== String(currentFiscalYear());
    if (isPast && !window.confirm(`قرارداد سال ${year} ویرایش شود؟ شهریه این سال دوباره محاسبه می‌شود.`)) {
      return;
    }
    if (contractId > 0) {
      await S().postJson("api.php?resource=team_contracts&action=update", { id: contractId, ...payload });
    } else {
      await S().postJson("api.php?resource=team_contracts&action=create", payload);
    }
    S().showToast("قرارداد ذخیره شد.", "success");
  };

  const bindWorkspace = (container, teamId, reloadFn) => {
    container.querySelectorAll("[data-year-tab]").forEach((button) => {
      button.addEventListener("click", () => {
        reloadFn(container, teamId, button.dataset.yearTab).catch((error) => S().showToast(error.message, "error"));
      });
    });

    container.querySelector("[data-year-contract]")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        await saveContractForm(form);
        await reloadFn(container, teamId, form.dataset.year);
        await S().refreshAfterMutation("teams");
      } catch (error) {
        S().showToast(error.message, "error");
      } finally {
        submit.disabled = false;
      }
    });

    container.querySelector("[data-delete-contract]")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const year = button.dataset.year;
      if (!window.confirm(`قرارداد سال ${year} حذف شود؟ این عمل قابل بازگشت نیست.`)) return;
      button.disabled = true;
      try {
        await S().postJson("api.php?resource=team_contracts&action=delete", { id: Number(button.dataset.contractId) });
        S().showToast("قرارداد حذف شد.", "success");
        await reloadFn(container, teamId, year);
        await S().refreshAfterMutation("teams");
      } catch (error) {
        S().showToast(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    container.querySelector("[data-add-assignment]")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      await openAssignmentEditor({
        teamId: Number(button.dataset.teamId),
        year: button.dataset.year,
        onSaved: () => reloadFn(container, teamId, button.dataset.year),
      });
    });

    container.querySelectorAll("[data-edit-assignment]").forEach((button) => {
      button.addEventListener("click", async () => {
        const year = container.dataset.selectedYear;
        await openAssignmentEditor({
          assignmentId: Number(button.dataset.id),
          teamId,
          year,
          onSaved: () => reloadFn(container, teamId, year),
        });
      });
    });

    container.querySelectorAll("[data-delete-assignment]").forEach((button) => {
      button.addEventListener("click", async () => {
        if (!window.confirm("این رکورد تخصیص میز حذف شود؟")) return;
        const year = container.dataset.selectedYear;
        try {
          await S().postJson("api.php?resource=desk-assignments&action=delete", { id: Number(button.dataset.id) });
          S().showToast("حذف شد.", "success");
          await reloadFn(container, teamId, year);
          await S().refreshAfterMutation("desks");
        } catch (error) {
          S().showToast(error.message, "error");
        }
      });
    });

    container.querySelector("[data-recalc-year]")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const year = button.dataset.year;
      const tid = Number(button.dataset.teamId);
      if (!window.confirm(`شهریه خودکار سال ${year} برای این نهاد محاسبه شود؟`)) return;
      button.disabled = true;
      try {
        await S().postJson("api.php?resource=recalculate-charges", { fiscal_year: year, team_id: tid });
        S().showToast("محاسبه انجام شد.", "success");
        await reloadFn(container, tid, year);
        await S().refreshAfterMutation("charges");
      } catch (error) {
        S().showToast(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    container.querySelector("[data-year-wizard]")?.addEventListener("click", () => {
      openYearWizard(Number(container.querySelector("[data-year-wizard]")?.dataset.teamId || teamId), container.dataset.selectedYear)
        .then(() => reloadFn(container, teamId, container.dataset.selectedYear))
        .catch((error) => S().showToast(error.message, "error"));
    });

    container.querySelector("[data-year-add]")?.addEventListener("click", () => {
      openYearWizard(teamId, "")
        .then((year) => reloadFn(container, teamId, year || container.dataset.selectedYear))
        .catch((error) => { if (error.message !== "cancelled") S().showToast(error.message, "error"); });
    });

    container.querySelector("[data-go-desks-map]")?.addEventListener("click", () => {
      S().closeModal();
      S().activateSection("desks");
    });

    container.querySelector("[data-go-charges]")?.addEventListener("click", () => {
      S().closeModal();
      S().activateSection("charges");
    });

    container.querySelector("[data-profile-deposit]")?.addEventListener("click", async () => {
      const profile = await S().fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
      const month = profile.current_month || {};
      S().openDepositModal({
        teamId,
        teamName: profile.team?.name,
        fiscalYear: month.fiscal_year || currentFiscalYear(),
        monthIndex: month.month_index || S().MECHINNO?.monthIndex || 1,
        monthName: month.month_name || "",
        amountDue: Number(month.charge_total || 0),
        amountPaid: Number(month.paid_total || 0),
      });
    });

    container.querySelector('[data-profile-action="add-member"]')?.addEventListener("click", async () => {
      const profile = await S().fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
      S().closeModal();
      S().activateSection("members");
      const meta = await S().loadCrudMeta();
      S().openRecordModal({
        resource: "members",
        definition: meta.resources.members,
        title: `افزودن عضو — ${profile.team?.name || ""}`,
        record: { team_id: String(teamId) },
        onSaved: async () => {
          await S().refreshAfterMutation("members");
          S().showToast("عضو ثبت شد.", "success");
        },
      });
    });

    container.querySelector('[data-profile-action="charges"]')?.addEventListener("click", () => {
      S().closeModal();
      S().activateSection("charges");
    });
  };

  const openAssignmentEditor = async ({ assignmentId = 0, teamId, year, onSaved }) => {
    const meta = await S().loadCrudMeta();
    const definition = meta.resources.desk_assignments;
    let record = {
      team_id: String(teamId),
      usage_type: "formal",
      assigned_from: `${year}/01/01`,
      assigned_until: `${year}/12/29`,
    };
    if (assignmentId > 0) {
      const { rows } = await S().fetchResource("api.php?resource=desk-assignments", { page: 1, perPage: 200 });
      const existing = rows.find((row) => Number(row.id) === assignmentId);
      if (existing) record = { ...existing };
    }
    const isPast = String(year) !== String(currentFiscalYear());
    if (isPast && assignmentId > 0 && !window.confirm("ویرایش میز سال گذشته ممکن است شهریه را تغییر دهد. ادامه می‌دهید؟")) {
      throw new Error("cancelled");
    }
    S().openRecordModal({
      resource: "desk-assignments",
      definition,
      title: `${assignmentId ? "ویرایش" : "افزودن"} میز — سال ${year}`,
      record,
      onSaved: async () => {
        await onSaved?.();
        await S().refreshAfterMutation("desks");
        S().showToast("تخصیص میز ذخیره شد.", "success");
      },
    });
  };

  const openYearWizard = (teamId, prefillYear = "") => new Promise((resolve, reject) => {
    const modal = S().ensureModal();
    const form = modal.querySelector("#crudForm");
    modal.querySelector("#crudModalTitle").textContent = "ویزارد ثبت سال / سابقه";
    const state = {
      step: 1,
      year: prefillYear || "",
      contractStart: "",
      contractEnd: "",
      notes: "",
      desksText: "",
      recalculate: true,
    };

    const renderStep = () => {
      if (state.step === 1) {
        form.innerHTML = `
          <p class="hint">برای ورود یک‌باره قرارداد و میزهای یک سال (جاری یا گذشته).</p>
          <div class="crud-grid">
            <label><span>سال مالی</span><input id="wizardYear" type="text" required value="${S().escapeHtml(state.year)}" placeholder="1403" /></label>
          </div>
          <div class="modal-actions">
            <button type="button" class="button ghost" data-wizard-cancel>انصراف</button>
            <button type="button" class="button" data-wizard-next>بعدی</button>
          </div>`;
      } else if (state.step === 2) {
        form.innerHTML = `
          <p class="hint">قرارداد سال <strong>${S().escapeHtml(state.year)}</strong></p>
          <div class="crud-grid">
            <label><span>شروع</span><input id="wizardStart" type="text" value="${S().escapeHtml(state.contractStart || `${state.year}/01/01`)}" /></label>
            <label><span>پایان</span><input id="wizardEnd" type="text" value="${S().escapeHtml(state.contractEnd || `${state.year}/12/29`)}" /></label>
            <label class="wide"><span>یادداشت</span><textarea id="wizardNotes" rows="2">${S().escapeHtml(state.notes)}</textarea></label>
          </div>
          <div class="modal-actions">
            <button type="button" class="button ghost" data-wizard-back>قبلی</button>
            <button type="button" class="button" data-wizard-next>بعدی</button>
          </div>`;
      } else if (state.step === 3) {
        form.innerHTML = `
          <p class="hint">میزهای سال ${S().escapeHtml(state.year)} — هر خط: <code>شماره میز,نوع,از تاریخ,تا تاریخ</code></p>
          <label class="wide"><span>ردیف‌های میز</span>
            <textarea id="wizardDesks" rows="8" placeholder="3,formal,${S().escapeHtml(state.year)}/01/01,${S().escapeHtml(state.year)}/12/29">${S().escapeHtml(state.desksText)}</textarea>
          </label>
          <div class="modal-actions">
            <button type="button" class="button ghost" data-wizard-back>قبلی</button>
            <button type="button" class="button" data-wizard-next>بعدی</button>
          </div>`;
      } else {
        const deskLines = state.desksText.trim().split("\n").filter(Boolean);
        form.innerHTML = `
          <div class="year-wizard-preview">
            <h3>خلاصه قبل از ذخیره</h3>
            <ul>
              <li>سال: <strong>${S().escapeHtml(state.year)}</strong></li>
              <li>قرارداد: ${S().escapeHtml(state.contractStart)} تا ${S().escapeHtml(state.contractEnd)}</li>
              <li>تعداد میز: <strong>${deskLines.length}</strong></li>
            </ul>
            <label><input type="checkbox" id="wizardRecalc" ${state.recalculate ? "checked" : ""} /> محاسبه خودکار شهریه پس از ذخیره</label>
            ${String(state.year) !== String(currentFiscalYear()) ? `<p class="hint warning-text">سال گذشته — وضعیت فعلی نقشه میز تغییر نمی‌کند مگر میزهای باز تا امروز باشند.</p>` : ""}
          </div>
          <div class="modal-actions">
            <button type="button" class="button ghost" data-wizard-back>قبلی</button>
            <button type="button" class="button" data-wizard-save>ذخیره</button>
          </div>`;
      }

      form.querySelector("[data-wizard-cancel]")?.addEventListener("click", () => {
        S().closeModal();
        reject(new Error("cancelled"));
      });
      form.querySelector("[data-wizard-back]")?.addEventListener("click", () => {
        if (state.step === 3) {
          state.desksText = form.querySelector("#wizardDesks")?.value || state.desksText;
        }
        state.step -= 1;
        renderStep();
      });
      form.querySelector("[data-wizard-next]")?.addEventListener("click", () => {
        if (state.step === 1) {
          state.year = form.querySelector("#wizardYear").value.trim();
          if (!state.year) return;
          state.contractStart = `${state.year}/01/01`;
          state.contractEnd = `${state.year}/12/29`;
        }
        if (state.step === 2) {
          state.contractStart = form.querySelector("#wizardStart").value;
          state.contractEnd = form.querySelector("#wizardEnd").value;
          state.notes = form.querySelector("#wizardNotes").value;
        }
        if (state.step === 3) {
          state.desksText = form.querySelector("#wizardDesks").value;
        }
        state.step += 1;
        renderStep();
      });
      form.querySelector("[data-wizard-save]")?.addEventListener("click", async () => {
        state.recalculate = form.querySelector("#wizardRecalc")?.checked !== false;
        const desks = state.desksText.trim().split("\n").filter(Boolean).map((line) => {
          const [deskNumber, usageType, assignedFrom, assignedUntil] = line.split(",").map((part) => part.trim());
          return {
            desk_number: deskNumber,
            usage_type: usageType || "formal",
            assigned_from: assignedFrom || `${state.year}/01/01`,
            assigned_until: assignedUntil || `${state.year}/12/29`,
          };
        });
        const saveButton = form.querySelector("[data-wizard-save]");
        saveButton.disabled = true;
        try {
          await S().postJson("api.php?resource=bulk-year-import", {
            fiscal_year: state.year,
            recalculate: state.recalculate,
            rows: [{
              team_id: teamId,
              contract_start: state.contractStart,
              contract_end: state.contractEnd,
              notes: state.notes,
              desks,
            }],
          });
          S().showToast("سال با موفقیت ثبت شد.", "success");
          S().closeModal();
          resolve(state.year);
        } catch (error) {
          S().showToast(error.message, "error");
        } finally {
          saveButton.disabled = false;
        }
      });
    };

    renderStep();
    modal.hidden = false;
    S().trapFocus(modal);
  });

  const openBulkImportModal = () => {
    const modal = S().ensureModal();
    const form = modal.querySelector("#crudForm");
    modal.querySelector("#crudModalTitle").textContent = "ورود سریع سال (CSV)";
    form.innerHTML = `
      <p class="hint">هر خط: <code>نام نهاد,شروع قرارداد,پایان,میزها</code> — میزها با کاما جدا شوند.</p>
      <div class="crud-grid">
        <label><span>سال مالی</span><input name="fiscal_year" type="text" required value="${S().escapeHtml(currentFiscalYear())}" /></label>
        <label class="wide"><span>داده CSV</span>
          <textarea name="csv" rows="10" placeholder="تیم آلفا,1403/01/01,1403/12/29,3,7&#10;شرکت بتا,1403/01/01,1403/12/29,12"></textarea>
        </label>
        <label class="wide"><input type="checkbox" name="recalculate" checked /> محاسبه خودکار شهریه پس از import</label>
      </div>
      <div class="modal-actions">
        <button type="button" class="button ghost" data-close-modal>انصراف</button>
        <button type="button" class="button" id="bulkImportSubmit">ورود داده</button>
      </div>`;
    form.querySelector("[data-close-modal]").addEventListener("click", S().closeModal);
    form.querySelector("#bulkImportSubmit").addEventListener("click", async () => {
      const fiscalYear = form.fiscal_year.value.trim();
      const lines = form.csv.value.trim().split("\n").filter(Boolean);
      const rows = lines.map((line) => {
        const parts = line.split(",").map((part) => part.trim());
        const [teamName, contractStart, contractEnd, ...deskParts] = parts;
        return {
          team_name: teamName,
          contract_start: contractStart || `${fiscalYear}/01/01`,
          contract_end: contractEnd || `${fiscalYear}/12/29`,
          desk_numbers: deskParts.join(","),
        };
      });
      const button = form.querySelector("#bulkImportSubmit");
      button.disabled = true;
      try {
        const result = await S().postJson("api.php?resource=bulk-year-import", {
          fiscal_year: fiscalYear,
          recalculate: form.recalculate.checked,
          rows,
        });
        S().showToast(`${result.imported} نهاد وارد شد — ${result.skipped} رد شد.`, "success");
        S().closeModal();
        await S().refreshAfterMutation("teams");
      } catch (error) {
        S().showToast(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });
    modal.hidden = false;
    S().trapFocus(modal);
  };

  const openDeskAssignModal = async (deskNumber) => {
    const mapData = await S().fetchJson("api.php?resource=desks-map");
    const mapDesk = (mapData.rows || []).find((row) => Number(row.number) === Number(deskNumber));
    if (!mapDesk) {
      S().showToast("میز پیدا نشد.", "error");
      return;
    }
    const { rows } = await S().fetchResource("api.php?resource=desks", { page: 1, perPage: 100 });
    const desk = rows.find((row) => Number(row.id) === Number(mapDesk.id)) || mapDesk;
    if (!desk) {
      S().showToast("میز پیدا نشد.", "error");
      return;
    }
    const meta = await S().loadCrudMeta();
    const year = currentFiscalYear();
    const today = S().MECHINNO?.today || `${year}/01/01`;
    S().openRecordModal({
      resource: "desks",
      definition: meta.resources.desks,
      title: `تخصیص میز ${desk.number} — سال جاری`,
      record: {
        id: String(desk.id),
        team_id: desk.team_id ? String(desk.team_id) : "",
        usage_type: desk.usage_type || "formal",
        assignment_from: desk.assignment_from || today,
        assignment_until: desk.assignment_until || "",
        notes: desk.notes || "",
      },
      onSaved: async () => {
        await S().loadDeskGrid?.();
        await S().refreshAfterMutation("desks");
        await S().refreshAfterMutation("desk-history");
        S().showToast("میز به‌روز شد.", "success");
      },
    });
  };

  const renderTeamStatusChecklist = (row) => {
    if (row.has_contract_year === undefined) return "—";
    return `<div class="year-checklist year-checklist--compact">
      ${checklistItem(Number(row.has_contract_year) === 1, "قرارداد")}
      ${checklistItem(Number(row.year_desk_count || 0) > 0, `${row.year_desk_count || 0} میز`)}
      ${checklistItem(Number(row.year_debt || 0) <= 0, Number(row.year_debt || 0) > 0 ? "بدهی" : "تسویه")}
    </div>`;
  };

  const openModal = async (teamId, options = {}) => {
    const modal = S().ensureModal();
    modal.querySelector("#crudModalTitle").textContent = "پروفایل نهاد";
    const form = modal.querySelector("#crudForm");
    form.innerHTML = `<div class="team-year-workspace-host">در حال بارگذاری…</div>`;
    modal.hidden = false;
    S().trapFocus(modal);
    const host = form.querySelector(".team-year-workspace-host");
    await reloadWorkspace(host, teamId, options.year || currentFiscalYear());
    form.insertAdjacentHTML("beforeend", `<div class="modal-actions"><button class="button ghost" type="button" data-close-modal>بستن</button></div>`);
    form.querySelector("[data-close-modal]").addEventListener("click", S().closeModal);
  };

  const mountInline = async (host, teamId) => {
    if (!host) return;
    host.innerHTML = `<div class="team-year-workspace-host">در حال بارگذاری…</div>`;
    const inner = host.querySelector(".team-year-workspace-host");
    await reloadWorkspace(inner, teamId, currentFiscalYear());
  };

  window.TeamYearWorkspace = {
    openModal,
    mountInline,
    openYearWizard,
    openBulkImportModal,
    openDeskAssignModal,
    renderTeamStatusChecklist,
    reloadWorkspace,
  };

  const activeProfile = document.querySelector("#profile.section.active");
  if (activeProfile && window.MECHINNO?.teamId) {
    const host = document.getElementById("teamProfileContent");
    if (host) {
      mountInline(host, window.MECHINNO.teamId).catch((error) => S()?.showToast?.(error.message, "error"));
    }
  }
})();
