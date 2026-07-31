(() => {
  const csrfToken = window.MECHINNO?.csrfToken || "";
  const today = window.MECHINNO?.today || "";
  const todayYear = Number(window.MECHINNO?.fiscalYear || String(today).slice(0, 4) || 1404);
  const todayMonth = Number(window.MECHINNO?.monthIndex || String(today).slice(5, 7) || 1);

  const postJson = async (url, payload) => {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || "خطا در ثبت رزرو");
    return data;
  };

  const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || "خطا در دریافت اطلاعات");
    return data;
  };

  const escapeHtml = (value) => String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

  const Range = window.MechinnoRoomRange;
  if (!Range) {
    console.error("MechinnoRoomRange is required. Load assets/room-range.js first.");
  }
  const { minutesToTime, timeToMinutes, resolveRange, canUseAsEnd, durationLabel, slotPresentation } = Range || {};

  const weekdayOffset = (weekdayName) => {
    const map = {
      شنبه: 0, یکشنبه: 1, دوشنبه: 2, سه‌شنبه: 3, "سه شنبه": 3, چهارشنبه: 4, پنجشنبه: 5, جمعه: 6,
    };
    return map[weekdayName] ?? 0;
  };

  const renderRoomCards = (grid, rooms, selectedRoomId, onSelect) => {
    if (!grid) return;
    if (!rooms.length) {
      grid.innerHTML = '<p class="hint">اتاق فعالی برای رزرو وجود ندارد.</p>';
      return;
    }
    grid.innerHTML = rooms.map((room) => {
      const selected = Number(room.id) === Number(selectedRoomId);
      return `<button type="button" class="room-room-option${selected ? " is-selected" : ""}" data-room-id="${room.id}" role="option" aria-selected="${selected}">
        <span class="room-room-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
        <span class="room-room-copy">
          <strong>${escapeHtml(room.name)}${room.code ? ` · ${escapeHtml(room.code)}` : ""}</strong>
          <small>${room.floor ? `${escapeHtml(room.floor)} · ` : ""}${room.open_time || "08:00"} تا ${room.close_time || "20:00"} · بازه ${room.slot_minutes || 30}د</small>
        </span>
        <span class="room-room-capacity">${room.capacity} نفر</span>
      </button>`;
    }).join("");
    grid.querySelectorAll(".room-room-option").forEach((button) => {
      button.addEventListener("click", () => onSelect(Number(button.dataset.roomId || 0)));
    });
  };

  const renderRoomOverview = (container, rooms, events, todayDate) => {
    if (!container) return;
    if (!rooms.length) {
      container.innerHTML = "";
      return;
    }
    const todayEvents = events.filter((event) => event.reserved_date === todayDate);
    container.innerHTML = rooms.map((room) => {
      const count = todayEvents.filter((event) => Number(event.room_id) === Number(room.id)).length;
      return `<article class="room-overview-card">
        <span class="room-room-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
        <div class="room-overview-copy">
          <strong>${escapeHtml(room.name)}</strong>
          <small>${room.floor ? `${escapeHtml(room.floor)} · ` : ""}${room.capacity} نفر · ${room.open_time || "08:00"}–${room.close_time || "20:00"}</small>
        </div>
        <span class="room-overview-badge${count > 0 ? " is-busy" : ""}">${count > 0 ? `${count} رزرو امروز` : "آزاد امروز"}</span>
      </article>`;
    }).join("");
  };

  const initRoomSettings = () => {
    const form = document.getElementById("roomSettingsForm");
    if (!form) return;
    fetchJson("api.php?resource=room-settings")
      .then((settings) => {
        form.room_auto_approve.value = settings.room_auto_approve ? "1" : "0";
        form.room_public_enabled.value = settings.room_public_enabled ? "1" : "0";
        form.room_max_advance_days.value = settings.room_max_advance_days || 14;
        form.room_max_hours_per_day.value = settings.room_max_hours_per_day || 2;
        form.room_slot_minutes.value = String(settings.room_slot_minutes || 30);
      })
      .catch((error) => window.showToast?.(error.message, "error"));

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-settings", payload);
        window.showToast?.("تنظیمات ذخیره شد. بازه‌های اتاق‌ها همگام شد.", "success");
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });
  };

  const initClosedDays = () => {
    const form = document.getElementById("roomClosedDayForm");
    const list = document.getElementById("roomClosedDaysList");
    if (!form || !list) return;

    const load = async () => {
      const data = await fetchJson("api.php?resource=room-closed-days");
      const rows = data.rows || [];
      if (!rows.length) {
        list.innerHTML = '<p class="hint">روز تعطیلی ثبت نشده است.</p>';
        return;
      }
      list.innerHTML = rows.map((row) => `
        <div class="room-closed-item">
          <div>
            <strong>${escapeHtml(row.closed_date)}</strong>
            <small>${escapeHtml(row.note || "بدون توضیح")}</small>
          </div>
          <button type="button" class="mini-button" data-remove-closed="${row.id}">حذف</button>
        </div>`).join("");
      list.querySelectorAll("[data-remove-closed]").forEach((button) => {
        button.addEventListener("click", async () => {
          try {
            await postJson("api.php?resource=room-closed-days", {
              action: "remove",
              id: Number(button.dataset.removeClosed || 0),
            });
            window.showToast?.("روز تعطیل حذف شد.", "success");
            await load();
            window.refreshPanelMonthPicker?.();
            window.refreshRoomCalendar?.();
          } catch (error) {
            window.showToast?.(error.message, "error");
          }
        });
      });
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-closed-days", {
          action: "add",
          date: payload.date,
          note: payload.note || "",
        });
        form.reset();
        window.showToast?.("روز تعطیل ثبت شد.", "success");
        await load();
        window.refreshPanelMonthPicker?.();
        window.refreshRoomCalendar?.();
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    load().catch((error) => {
      list.innerHTML = `<p class="hint">${escapeHtml(error.message)}</p>`;
    });
  };

  const initPanelBooking = () => {
    const form = document.getElementById("panelRoomBookingForm");
    if (!form) return;

    const roomInput = form.querySelector('[name="room_id"]');
    const dateInput = form.querySelector('[name="reserved_date"]');
    const cardGrid = document.getElementById("panelRoomCardGrid");
    const slotGrid = document.getElementById("panelRoomSlotGrid");
    const preview = document.getElementById("panelRoomTimePreview");
    const teamSelect = document.getElementById("panelTeamSelect");
    const dayLabel = document.getElementById("panelSelectedDayLabel");
    const monthLabel = document.getElementById("panelMonthLabel");
    const monthGrid = document.getElementById("panelMonthGrid");
    const rangeHint = document.getElementById("panelRangeHint");

    const state = {
      rooms: [],
      teams: [],
      selectedRoomId: 0,
      rangeAnchor: "",
      selectedStart: "",
      selectedEnd: "",
      slots: [],
      slotMinutes: 30,
      maxHours: 2,
      closeTime: "20:00",
      year: todayYear,
      month: todayMonth,
      monthData: null,
    };

    const selectedRoom = () => state.rooms.find((room) => Number(room.id) === Number(state.selectedRoomId));
    const pickingEnd = () => Boolean(state.rangeAnchor && !(state.selectedStart && state.selectedEnd));

    const updatePreview = () => {
      if (!preview) return;
      if (state.selectedStart && state.selectedEnd) {
        const minutes = timeToMinutes(state.selectedEnd) - timeToMinutes(state.selectedStart);
        preview.textContent = `بازه انتخابی: ${state.selectedStart} تا ${state.selectedEnd} (${durationLabel(minutes)})`;
        if (rangeHint) rangeHint.textContent = "برای تغییر، دوباره روی یک ساعت آزاد کلیک کنید.";
        return;
      }
      if (state.rangeAnchor) {
        preview.textContent = `شروع: ${state.rangeAnchor} — حالا ساعت پایان را بزنید (مثلاً ${minutesToTime(timeToMinutes(state.rangeAnchor) + state.maxHours * 60)} برای ${state.maxHours} ساعت).`;
        if (rangeHint) rangeHint.textContent = "ساعت پایان یعنی زمان اتمام جلسه — مثلاً ۱۰:۰۰ تا ۱۲:۰۰ = ۲ ساعت.";
        return;
      }
      preview.textContent = "ابتدا ساعت شروع، سپس ساعت پایان را انتخاب کنید.";
      if (rangeHint) rangeHint.textContent = "۱) شروع  ۲) پایان (ساعت اتمام) — بازه‌های پر وسط مسیر قابل عبور نیستند.";
    };

    const clearRange = () => {
      state.rangeAnchor = "";
      state.selectedStart = "";
      state.selectedEnd = "";
    };

    const paintSlots = () => {
      if (!slotGrid) return;
      if (!state.slots.length) {
        slotGrid.innerHTML = '<p class="hint">برای این روز بازه‌ای موجود نیست.</p>';
        return;
      }

      const choosingEnd = pickingEnd();
      const complete = Boolean(state.selectedStart && state.selectedEnd);
      const slotTimes = new Set(state.slots.map((slot) => slot.time));
      const buttons = state.slots.map((slot) => {
        const validEnd = choosingEnd && canUseAsEnd({
          anchor: state.rangeAnchor,
          candidate: slot.time,
          slotMinutes: state.slotMinutes,
          maxHours: state.maxHours,
          slots: state.slots,
        });
        const visual = slotPresentation({
          time: slot.time,
          status: slot.status,
          selectedStart: state.selectedStart,
          selectedEnd: state.selectedEnd,
          rangeAnchor: state.rangeAnchor,
          choosingEnd,
          validEnd,
        });
        return `<button type="button" class="${visual.classes.join(" ")}" data-time="${slot.time}" data-end="${slot.end}" ${visual.disabled ? "disabled" : ""}>${visual.label}</button>`;
      });

      // Closing / exclusive end marker when it is not a normal slot start.
      const endMarker = complete ? state.selectedEnd : (choosingEnd ? state.closeTime : "");
      if (endMarker && !slotTimes.has(endMarker)) {
        const validClose = !complete && canUseAsEnd({
          anchor: state.rangeAnchor,
          candidate: endMarker,
          slotMinutes: state.slotMinutes,
          maxHours: state.maxHours,
          slots: state.slots,
        });
        if (complete || validClose) {
          const visual = slotPresentation({
            time: endMarker,
            status: "free",
            selectedStart: state.selectedStart,
            selectedEnd: state.selectedEnd,
            rangeAnchor: state.rangeAnchor,
            choosingEnd,
            validEnd: validClose,
          });
          buttons.push(
            `<button type="button" class="${visual.classes.join(" ")}" data-time="${endMarker}" data-end-only="1" ${visual.disabled ? "disabled" : ""}>${visual.label}</button>`
          );
        }
      }

      slotGrid.innerHTML = buttons.join("");

      slotGrid.querySelectorAll(".room-slot:not([disabled])").forEach((button) => {
        button.addEventListener("click", () => {
          const time = button.dataset.time || "";
          if (!time) return;

          if (!state.rangeAnchor || (state.selectedStart && state.selectedEnd)) {
            if (button.dataset.endOnly === "1") return;
            state.rangeAnchor = time;
            state.selectedStart = time;
            state.selectedEnd = "";
            paintSlots();
            updatePreview();
            return;
          }

          const resolved = resolveRange({
            anchor: state.rangeAnchor,
            clicked: time,
            slotMinutes: state.slotMinutes,
            maxHours: state.maxHours,
            slots: state.slots,
          });
          if (!resolved.ok) {
            window.showToast?.(resolved.error, "error");
            return;
          }

          state.selectedStart = resolved.start;
          state.selectedEnd = resolved.end;
          state.rangeAnchor = resolved.start;
          paintSlots();
          updatePreview();
        });
      });
    };

    const renderMonth = () => {
      const data = state.monthData;
      if (!monthGrid || !data) return;
      if (monthLabel) monthLabel.textContent = `${data.month_name || ""} ${data.year || ""}`;
      const days = data.days || [];
      const firstOffset = days[0] ? weekdayOffset(days[0].weekday) : 0;
      const blanks = Array.from({ length: firstOffset }, () => '<span class="room-month-day is-empty"></span>');
      const cells = days.map((day) => {
        const classes = ["room-month-day"];
        if (day.is_today) classes.push("is-today");
        if (day.is_past || day.is_beyond || !day.bookable) classes.push("is-disabled");
        if (day.is_closed) classes.push("is-closed");
        if (day.date === dateInput?.value) classes.push("is-selected");
        const title = day.is_closed ? (day.closed_note || "تعطیل") : (day.bookable ? "قابل رزرو" : "غیرقابل رزرو");
        return `<button type="button" class="${classes.join(" ")}" data-date="${day.date}" ${day.bookable ? "" : "disabled"} title="${escapeHtml(title)}"><span>${day.day}</span></button>`;
      });
      monthGrid.innerHTML = blanks.join("") + cells.join("");
      monthGrid.querySelectorAll(".room-month-day[data-date]:not([disabled])").forEach((button) => {
        button.addEventListener("click", () => {
          dateInput.value = button.dataset.date || "";
          if (dayLabel) dayLabel.textContent = `روز انتخاب‌شده: ${dateInput.value}`;
          clearRange();
          renderMonth();
          loadSlots();
        });
      });
    };

    const loadMonth = async () => {
      state.monthData = await fetchJson(
        `api.php?resource=room-month&year=${encodeURIComponent(state.year)}&month=${encodeURIComponent(state.month)}`
      );
      renderMonth();
    };

    const loadSlots = async () => {
      const roomId = state.selectedRoomId || roomInput?.value;
      const date = dateInput?.value || today;
      if (!roomId || !date || !slotGrid) return;
      slotGrid.innerHTML = '<p class="hint">در حال بارگذاری بازه‌ها…</p>';
      try {
        const data = await fetchJson(
          `api.php?resource=room-availability&room_id=${encodeURIComponent(roomId)}&date=${encodeURIComponent(date)}`
        );
        if (data.closed) {
          state.slots = [];
          clearRange();
          slotGrid.innerHTML = `<p class="hint room-closed-hint">${escapeHtml(data.closed_note || "این روز تعطیل است.")}</p>`;
          updatePreview();
          return;
        }
        if (data.error) {
          state.slots = [];
          clearRange();
          slotGrid.innerHTML = `<p class="hint">${escapeHtml(data.error)}</p>`;
          updatePreview();
          return;
        }
        state.slots = data.slots || [];
        state.slotMinutes = Number(data.slot_minutes || data.room?.slot_minutes || state.slotMinutes || 30);
        state.maxHours = Number(data.max_hours || state.maxHours || 2);
        state.closeTime = data.room?.close_time || selectedRoom()?.close_time || state.closeTime || "20:00";
        paintSlots();
        updatePreview();
      } catch (error) {
        slotGrid.innerHTML = `<p class="hint">${escapeHtml(error.message)}</p>`;
      }
    };

    const selectRoom = (roomId) => {
      state.selectedRoomId = roomId;
      if (roomInput) roomInput.value = roomId > 0 ? String(roomId) : "";
      const room = selectedRoom();
      state.slotMinutes = Number(room?.slot_minutes || state.slotMinutes || 30);
      clearRange();
      renderRoomCards(cardGrid, state.rooms, state.selectedRoomId, selectRoom);
      loadSlots();
    };

    const loadRooms = async () => {
      const data = await fetchJson("api.php?resource=meeting-rooms&per_page=100");
      state.rooms = (data.rows || []).filter((room) => Number(room.is_active) === 1);
      if (!state.selectedRoomId && state.rooms[0]) state.selectedRoomId = Number(state.rooms[0].id);
      if (roomInput) roomInput.value = state.selectedRoomId > 0 ? String(state.selectedRoomId) : "";
      const room = selectedRoom();
      state.slotMinutes = Number(room?.slot_minutes || state.slotMinutes || 30);
      renderRoomCards(cardGrid, state.rooms, state.selectedRoomId, selectRoom);
    };

    const loadTeams = async () => {
      if (!teamSelect) return;
      const data = await fetchJson("api.php?resource=teams&per_page=200");
      state.teams = (data.rows || []).filter((team) => Number(team.is_active ?? 1) === 1);
      teamSelect.innerHTML = '<option value="">— بدون نهاد / مهمان —</option>' + state.teams.map((team) => {
        const label = `${team.name || "—"}${team.entity_code ? ` (${team.entity_code})` : ""}`;
        return `<option value="${team.id}">${escapeHtml(label)}</option>`;
      }).join("");
    };

    const applyTeamDefaults = () => {
      const teamId = Number(teamSelect?.value || 0);
      const team = state.teams.find((row) => Number(row.id) === teamId);
      if (!team) return;
      const orgInput = form.querySelector('[name="booker_org"]');
      const nameInput = form.querySelector('[name="booker_name"]');
      const phoneInput = form.querySelector('[name="booker_phone"]');
      if (orgInput) orgInput.value = team.name || "";
      if (nameInput && team.leader) nameInput.value = team.leader;
      if (phoneInput && team.phone) phoneInput.value = team.phone;
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!state.selectedRoomId) {
        window.showToast?.("یک اتاق انتخاب کنید.", "error");
        return;
      }
      if (!dateInput?.value) {
        window.showToast?.("یک روز از تقویم انتخاب کنید.", "error");
        return;
      }
      if (!state.selectedStart || !state.selectedEnd) {
        window.showToast?.("بازه ساعت را کامل انتخاب کنید (شروع و پایان).", "error");
        return;
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.room_id = state.selectedRoomId;
      payload.reserved_date = dateInput.value;
      payload.start_time = state.selectedStart;
      payload.end_time = state.selectedEnd;
      if (!payload.team_id) delete payload.team_id;
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-reservations&action=create", payload);
        window.showToast?.("رزرو ثبت شد.", "success");
        ["booker_name", "booker_phone", "booker_org", "purpose"].forEach((name) => {
          const field = form.querySelector(`[name="${name}"]`);
          if (field) field.value = "";
        });
        if (teamSelect) teamSelect.value = "";
        clearRange();
        await loadSlots();
        document.querySelector('#meeting-rooms data-table[endpoint*="room-reservations"]')?.load?.();
        document.querySelector('#meeting-rooms data-table[endpoint*="pending-room-reservations"]')?.load?.();
        window.refreshRoomCalendar?.();
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    teamSelect?.addEventListener("change", applyTeamDefaults);

    document.getElementById("panelMonthPrev")?.addEventListener("click", () => {
      state.month -= 1;
      if (state.month < 1) {
        state.month = 12;
        state.year -= 1;
      }
      loadMonth().catch((error) => window.showToast?.(error.message, "error"));
    });
    document.getElementById("panelMonthNext")?.addEventListener("click", () => {
      state.month += 1;
      if (state.month > 12) {
        state.month = 1;
        state.year += 1;
      }
      loadMonth().catch((error) => window.showToast?.(error.message, "error"));
    });

    window.setPanelBookingDate = (date) => {
      if (!dateInput) return;
      dateInput.value = date;
      if (dayLabel) dayLabel.textContent = `روز انتخاب‌شده: ${date}`;
      const parts = String(date).split("/");
      if (parts.length === 3) {
        state.year = Number(parts[0]);
        state.month = Number(parts[1]);
      }
      clearRange();
      loadMonth().then(loadSlots).catch((error) => window.showToast?.(error.message, "error"));
    };
    window.setPanelBookingRoom = (roomId) => selectRoom(Number(roomId));
    window.refreshPanelMonthPicker = () => loadMonth().catch(() => {});

    Promise.all([
      fetchJson("api.php?resource=room-settings").then((settings) => {
        state.slotMinutes = Number(settings.room_slot_minutes || 30);
        state.maxHours = Number(settings.room_max_hours_per_day || 2);
      }).catch(() => {}),
      loadRooms(),
      loadTeams(),
      loadMonth(),
    ])
      .then(loadSlots)
      .catch((error) => window.showToast?.(error.message, "error"));
  };

  const loadRoomOverview = async () => {
    const container = document.getElementById("panelRoomOverview");
    if (!container) return;
    try {
      const data = await fetchJson(`api.php?resource=room-calendar&from=${encodeURIComponent(today)}&to=${encodeURIComponent(today)}`);
      renderRoomOverview(container, data.rooms || [], data.events || [], data.today || today);
    } catch {
      container.innerHTML = "";
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    initRoomSettings();
    initPanelBooking();
    initClosedDays();
    loadRoomOverview();
  });

  window.renderRoomCards = renderRoomCards;
})();
