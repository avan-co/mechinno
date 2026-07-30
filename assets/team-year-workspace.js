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
      formal_contract_amount: contract?.formal_contract_amount ?? null,
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

  const deskExemptBadges = (row) => {
    const bits = [];
    if (Number(row.charge_exempt) === 1) bits.push('<span class="badge badge-partial">معاف شارژ</span>');
    if (Number(row.rent_exempt) === 1) bits.push('<span class="badge badge-partial">معاف اجاره</span>');
    return bits.length ? bits.join(" ") : "—";
  };

  const renderBillingSummary = (profile, year) => {
    const billing = profile.billing_summaries?.[year]
      || (profile.year_summaries || []).find((row) => String(row.fiscal_year) === String(year))?.billing;
    if (!billing?.has_billing_adjustments) return "";
    return `<article class="year-panel year-panel--billing">
      <h3>تنظیمات مالی ویژه</h3>
      <div class="billing-label-list">${S().teamBillingBadges?.(billing, { compact: true }) || ""}</div>
      ${billing.summary_text ? `<p class="hint billing-summary-hint">${S().escapeHtml(billing.summary_text)}</p>` : ""}
      <p class="hint">این موارد در محاسبه خودکار شارژ لحاظ می‌شوند.</p>
    </article>`;
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
          <div><span>مبلغ قرارداد رسمی</span><strong>${S().escapeHtml(S().formatMoney(contract.formal_contract_amount || 0))}</strong></div>
          ${contract.charge_rate_override ? `<div><span>نرخ شارژ اختصاصی</span><strong>${S().escapeHtml(S().formatMoney(contract.charge_rate_override))}</strong></div>` : ""}
          ${contract.informal_rent_rate_override ? `<div><span>نرخ اجاره اختصاصی</span><strong>${S().escapeHtml(S().formatMoney(contract.informal_rent_rate_override))}</strong></div>` : ""}
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
          <label><span>مبلغ کل قرارداد رسمی (ریال)</span><input name="formal_contract_amount" type="number" min="0" step="1" required value="${S().escapeHtml(contract?.formal_contract_amount ?? "")}" placeholder="مبلغ کل قرارداد رسمی" /></label>
          <label><span>نرخ شارژ اختصاصی</span><input name="charge_rate_override" type="number" min="0" step="1" value="${S().escapeHtml(contract?.charge_rate_override ?? "")}" placeholder="خالی = نرخ عمومی" /></label>
          <label><span>نرخ اجاره اختصاصی</span><input name="informal_rent_rate_override" type="number" min="0" step="1" value="${S().escapeHtml(contract?.informal_rent_rate_override ?? "")}" placeholder="خالی = نرخ عمومی" /></label>
          <label class="wide"><span>توضیحات</span><textarea name="notes" rows="2">${S().escapeHtml(contract?.notes || "")}</textarea></label>
        </div>
        <div class="year-panel-actions">
          <button class="button" type="submit">${contract ? "ذخیره قرارداد" : "ثبت قرارداد"}</button>
          ${contract ? `<button class="button danger ghost" type="button" data-delete-contract data-contract-id="${contract.id}" data-team-id="${teamId}" data-year="${S().escapeHtml(year)}">حذف قرارداد سال</button>` : ""}
        </div>
      </form>
    </article>`;
  };

  const renderDeskPanel = (profile, year) => {
    const desks = desksForYear(profile, year);
    const rows = desks.length
      ? desks.map((row) => `
        <tr>
          <td>${S().deskLink ? S().deskLink(row.desk_number) : S().escapeHtml(row.desk_number)}</td>
          <td>${S().escapeHtml(S().usageLabels[row.usage_type] || row.usage_type || "—")}</td>
          <td>${deskExemptBadges(row)}</td>
          <td>${S().escapeHtml(row.assignment_period || S().formatMonthRange?.(row.assigned_from, row.assigned_until) || S().formatPlain(row.assigned_from))}</td>
          <td>${S().escapeHtml(row.notes || "—")}</td>
        </tr>`).join("")
      : `<tr><td colspan="5">میزی برای این سال ثبت نشده است. از بخش <strong>تاریخچه تخصیص</strong> ثبت کنید.</td></tr>`;

    return `<article class="year-panel">
      <div class="year-panel-head">
        <h3>میزهای سال ${S().escapeHtml(year)}</h3>
        <button type="button" class="button ghost" data-go-desk-history>ثبت / ویرایش در تاریخچه تخصیص</button>
      </div>
      <div class="table-wrap">
        <table class="data-table year-desk-table">
          <thead><tr>
            <th>میز</th><th>نوع</th><th>معافیت</th><th>بازه</th><th>یادداشت</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
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
        <div class="table-wrap"><table class="data-table"><thead><tr><th>ماه</th><th>شارژ</th><th>اجاره</th><th>جمع</th><th>توضیح محاسبه</th></tr></thead>
        <tbody>${chargeRows.map((row) => `<tr>
          <td>${S().escapeHtml(row.month_name || row.month_index)}</td>
          <td>${S().escapeHtml(S().formatMoney(row.charge_amount))}</td>
          <td>${S().escapeHtml(S().formatMoney(row.rent_amount))}</td>
          <td>${S().escapeHtml(S().formatMoney(row.amount))}</td>
          <td class="charge-note-cell">${S().escapeHtml(row.note || "—")}</td>
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
    const memberColumns = S().panelMode === "team"
      ? ["member_code", "full_name", "phone", "national_id"]
      : ["member_code", "full_name", "access_code", "phone", "national_id"];

    return `
      <div class="team-year-workspace" data-team-workspace data-team-id="${teamId}" data-selected-year="${S().escapeHtml(year)}">
        <div class="team-year-header">
          <div>
            <h2 class="team-year-title">${S().escapeHtml(team.name || "نهاد")}</h2>
            <p class="hint">${S().entityBadge(team.entity_type)} · کد ${S().escapeHtml(team.entity_code || "—")} · مسئول ${S().escapeHtml(team.leader || "—")}</p>
          </div>
          ${writable ? `<div class="profile-actions team-year-top-actions">
            <button type="button" class="button" data-profile-action="add-member">افزودن عضو</button>
            <button type="button" class="button ghost" data-go-desk-history>تاریخچه تخصیص میز</button>
            <button type="button" class="button ghost" data-profile-action="charges">شارژ</button>
          </div>` : ""}
        </div>
        ${renderYearTabs(years, year, teamId, writable)}
        ${renderYearChecklist(summary)}
        <div class="year-workspace-panels">
          ${renderContractPanel(profile, year, summary, teamId, writable)}
          ${renderBillingSummary(profile, year)}
          ${renderDeskPanel(profile, year)}
          ${renderChargesPanel(profile, year, teamId, writable)}
        </div>
        <details class="year-extra-section">
          <summary>اعضا، کمدها و پرداخت‌ها</summary>
          ${S().profileSection("اعضا", profile.members || [], memberColumns)}
          ${S().profileSection("کمدها", profile.lockers || [], ["locker_number", "status", "delivered_at", "key_number"])}
          ${S().profileSection("دریافت شارژ از نهاد", profile.payments || [], ["tx_date", "fiscal_year", "month_name", "amount"])}
        </details>
      </div>`;
  };

  const reloadWorkspace = async (container, teamId, selectedYear) => {
    try {
      const profile = await S().fetchJson(`api.php?resource=team-profile&id=${encodeURIComponent(teamId)}`);
      const writable = S().canWrite && S().panelMode === "admin";
      container.innerHTML = renderWorkspace(profile, teamId, selectedYear, { writable });
      bindWorkspace(container, teamId, reloadWorkspace);
      container.classList.add("is-ready");
      return profile;
    } catch (error) {
      container.innerHTML = `<div class="empty">خطا در بارگذاری پروفایل: ${S().escapeHtml(error.message)}</div>`;
      container.classList.add("is-ready");
      throw error;
    }
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
      formal_contract_amount: form.formal_contract_amount?.value?.trim() || "",
      charge_rate_override: form.charge_rate_override?.value?.trim() || "",
      informal_rent_rate_override: form.informal_rent_rate_override?.value?.trim() || "",
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

    container.querySelectorAll("[data-go-desk-history]").forEach((button) => {
      button.addEventListener("click", () => {
        S().closeModal();
        S().activateSection("desk-history");
      });
    });

    container.querySelector("[data-recalc-year]")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const year = button.dataset.year;
      const tid = Number(button.dataset.teamId);
      if (!window.confirm(`شهریه خودکار سال ${year} محاسبه شود؟ ماه‌هایی که دستی ویرایش کرده‌اید حفظ می‌شوند.`)) return;
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

  const openBulkImportModal = () => {
    const modal = S().ensureModal();
    const form = modal.querySelector("#crudForm");
    modal.querySelector("#crudModalTitle").textContent = "ورود سریع سال (CSV)";
    form.innerHTML = `
      <p class="hint">هر خط: <code>نام نهاد,شروع قرارداد,پایان,مبلغ قرارداد,میزها</code> — میزها با کاما جدا شوند.</p>
      <div class="crud-grid">
        <label><span>سال مالی</span><input name="fiscal_year" type="text" required value="${S().escapeHtml(currentFiscalYear())}" /></label>
        <label class="wide"><span>داده CSV</span>
          <textarea name="csv" rows="10" placeholder="تیم آلفا,1403/01/01,1403/12/29,120000000,3,7&#10;شرکت بتا,1403/01/01,1403/12/29,85000000,12"></textarea>
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
        const [teamName, contractStart, contractEnd, formalAmount, ...deskParts] = parts;
        return {
          team_name: teamName,
          contract_start: contractStart || `${fiscalYear}/01/01`,
          contract_end: contractEnd || `${fiscalYear}/12/29`,
          formal_contract_amount: formalAmount || "0",
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

  const monthFromRecord = (row, monthKey, dateKey) => {
    const month = Number(S().validAssignmentMonth?.(row[monthKey] || row[dateKey]));
    return month >= 1 && month <= 12 ? String(month) : "";
  };

  const openDeskAssignModal = async (deskNumber) => {
    console.log("[mechinno:desk-map:open]", { deskNumber });
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

    const year = currentFiscalYear();
    const assignParams = { page: 1, perPage: 100, fiscalYear: year };
    const { rows: assignments } = await S().fetchResource("api.php?resource=desk-assignments", assignParams);
    const deskMatches = assignments.filter((row) => Number(row.desk_id) === Number(desk.id));
    const existing = deskMatches.sort((a, b) => String(b.assigned_from || "").localeCompare(String(a.assigned_from || "")))[0] || null;

    let prefill = {
      desk_id: String(desk.id),
      team_id: desk.team_id ? String(desk.team_id) : "",
      fiscal_year: year,
      usage_type: desk.usage_type || "formal",
      notes: desk.notes || "",
      lockDesk: true,
      assigned_from_month: monthFromRecord(desk, "assignment_from_month", "assignment_from"),
      assigned_until_month: monthFromRecord(desk, "assignment_until_month", "assignment_until"),
    };

    if (existing) {
      prefill = {
        id: existing.id,
        desk_id: String(existing.desk_id),
        team_id: String(existing.team_id),
        fiscal_year: existing.fiscal_year || year,
        usage_type: existing.usage_type || "formal",
        charge_exempt: Number(existing.charge_exempt) === 1 ? "1" : "0",
        rent_exempt: Number(existing.rent_exempt) === 1 ? "1" : "0",
        notes: existing.notes || "",
        lockDesk: true,
        assigned_from_month: monthFromRecord(existing, "assigned_from_month", "assigned_from"),
        assigned_until_month: monthFromRecord(existing, "assigned_until_month", "assigned_until"),
      };
    }

    if (!prefill.assigned_from_month) prefill.assigned_from_month = "1";
    if (!prefill.assigned_until_month) prefill.assigned_until_month = "12";

    console.log("[mechinno:desk-map:prefill]", { desk, existing, prefill });
    await S().openDeskHistoryAssignModal(prefill);
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
    try {
      await reloadWorkspace(inner, teamId, currentFiscalYear());
    } catch (error) {
      inner.innerHTML = `<div class="empty">خطا در بارگذاری پروفایل: ${S().escapeHtml(error.message)}</div>`;
      inner.classList.add("is-ready");
      throw error;
    }
  };

  window.TeamYearWorkspace = {
    openModal,
    mountInline,
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
