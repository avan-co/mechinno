(() => {
  const cfg = window.MECHINNO_PUBLIC || {};
  const state = {
    rooms: [],
    settings: {},
    today: cfg.today || "",
    selectedRoomId: 0,
    selectedDate: cfg.today || "",
    rangeAnchor: "",
    selectedStart: "",
    selectedEnd: "",
    slots: [],
    slotMinutes: Number(cfg.slotMinutes || 30),
    maxHours: Number(cfg.maxHours || 2),
    year: Number(cfg.year || String(cfg.today || "1404").slice(0, 4)),
    month: Number(cfg.month || String(cfg.today || "1404/01/01").slice(5, 7)),
    monthData: null,
    weekRoomId: 0,
    weekMeta: { day_start: "08:00", day_end: "20:00" },
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));

  const escapeHtml = (value) => String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

  const minutesToTime = (minutes) => {
    const hour = Math.floor(minutes / 60);
    const minute = minutes % 60;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
  };

  const timeToMinutes = (time) => {
    const [hour, minute] = String(time || "0:0").split(":").map(Number);
    return (hour * 60) + minute;
  };

  const weekdayOffset = (weekdayName) => {
    const map = {
      شنبه: 0, یکشنبه: 1, دوشنبه: 2, سه‌شنبه: 3, "سه شنبه": 3, چهارشنبه: 4, پنجشنبه: 5, جمعه: 6,
    };
    return map[weekdayName] ?? 0;
  };

  const showMessage = (text, type = "info") => {
    const host = $("#roomPublicMessage");
    if (!host) return;
    host.textContent = text;
    host.className = `room-alert room-alert--${type}`;
    host.hidden = !text;
    if (text) host.scrollIntoView({ behavior: "smooth", block: "nearest" });
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      headers: { Accept: "application/json", ...(options.headers || {}) },
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || "خطا در ارتباط با سرور");
    return data;
  };

  const selectedRoom = () => state.rooms.find((room) => Number(room.id) === Number(state.selectedRoomId));

  const clearRange = () => {
    state.rangeAnchor = "";
    state.selectedStart = "";
    state.selectedEnd = "";
    syncHiddenFields();
  };

  const syncHiddenFields = () => {
    const roomInput = $("#formRoomId");
    const startInput = $("#formStartTime");
    const endInput = $("#formEndTime");
    const dateInput = $("#reserveDate");
    if (roomInput) roomInput.value = state.selectedRoomId > 0 ? String(state.selectedRoomId) : "";
    if (dateInput) dateInput.value = state.selectedDate || "";
    if (startInput) startInput.value = state.selectedStart || "";
    if (endInput) endInput.value = state.selectedEnd || "";
  };

  const updateSummary = () => {
    const room = selectedRoom();
    const summaryRoom = $("#summaryRoom");
    const summaryDate = $("#summaryDate");
    const summaryTime = $("#summaryTime");
    if (summaryRoom) summaryRoom.textContent = room ? room.name : "—";
    if (summaryDate) summaryDate.textContent = state.selectedDate || "—";
    if (summaryTime) {
      summaryTime.textContent = state.selectedStart && state.selectedEnd
        ? `${state.selectedStart} – ${state.selectedEnd}`
        : "—";
    }
    syncHiddenFields();
  };

  const updateTimePreview = () => {
    const preview = $("#timePreview");
    if (!preview) return;
    if (state.selectedStart && state.selectedEnd) {
      const minutes = timeToMinutes(state.selectedEnd) - timeToMinutes(state.selectedStart);
      const label = minutes < 60 ? `${minutes} دقیقه` : `${(minutes / 60).toFixed(minutes % 60 ? 1 : 0)} ساعت`;
      preview.textContent = `بازه انتخابی: ${state.selectedStart} تا ${state.selectedEnd} (${label})`;
      return;
    }
    if (state.rangeAnchor) {
      preview.textContent = `شروع: ${state.rangeAnchor} — حالا ساعت پایان را بزنید`;
      return;
    }
    preview.textContent = "۱) شروع  ۲) پایان";
  };

  const renderRooms = () => {
    const grid = $("#roomCardGrid");
    if (!grid) return;
    if (!state.rooms.length) {
      grid.innerHTML = '<p class="hint">اتاق فعالی برای رزرو وجود ندارد.</p>';
      return;
    }
    grid.innerHTML = state.rooms.map((room) => {
      const selected = Number(room.id) === Number(state.selectedRoomId);
      return `<button type="button" class="room-room-option${selected ? " is-selected" : ""}" data-room-id="${room.id}" role="option" aria-selected="${selected}">
        <span class="room-room-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
        <span class="room-room-copy">
          <strong>${escapeHtml(room.name)}${room.code ? ` · ${escapeHtml(room.code)}` : ""}</strong>
          <small>${room.floor ? `${escapeHtml(room.floor)} · ` : ""}${room.open_time || "08:00"} تا ${room.close_time || "20:00"}</small>
        </span>
        <span class="room-room-capacity">${room.capacity} نفر</span>
      </button>`;
    }).join("");
    grid.querySelectorAll(".room-room-option").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedRoomId = Number(button.dataset.roomId || 0);
        state.weekRoomId = state.selectedRoomId;
        state.slotMinutes = Number(selectedRoom()?.slot_minutes || state.settings.room_slot_minutes || 30);
        clearRange();
        renderRooms();
        updateSummary();
        const filter = $("#weekRoomFilter");
        if (filter) {
          filter.value = String(state.weekRoomId);
          filter.dataset.ready = "0";
        }
        loadWeek();
        loadAvailability();
      });
    });
    if (state.selectedRoomId <= 0 && state.rooms[0]) {
      state.selectedRoomId = Number(state.rooms[0].id);
      state.weekRoomId = state.selectedRoomId;
      renderRooms();
      updateSummary();
    }
  };

  const paintSlots = () => {
    const grid = $("#slotGrid");
    if (!grid) return;
    if (!state.slots.length) {
      grid.innerHTML = '<p class="hint">برای این روز بازه‌ای موجود نیست.</p>';
      return;
    }
    const maxMinutes = state.maxHours * 60;
    grid.innerHTML = state.slots.map((slot) => {
      const busy = slot.status !== "free";
      const inSelected = state.selectedStart && state.selectedEnd
        && timeToMinutes(slot.time) >= timeToMinutes(state.selectedStart)
        && timeToMinutes(slot.time) < timeToMinutes(state.selectedEnd);
      const isAnchor = state.rangeAnchor === slot.time;
      let label = slot.time;
      if (slot.status === "busy") label = "پر";
      else if (slot.status === "pending") label = "انتظار";
      const classes = ["room-slot", `room-slot--${slot.status}`];
      if (inSelected || isAnchor) classes.push("is-in-range");
      if (isAnchor || state.selectedStart === slot.time) classes.push("is-selected");
      return `<button type="button" class="${classes.join(" ")}" data-time="${slot.time}" ${busy ? "disabled" : ""}>${label}</button>`;
    }).join("");

    grid.querySelectorAll(".room-slot:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        const time = button.dataset.time || "";
        if (!time) return;

        if (!state.rangeAnchor || (state.selectedStart && state.selectedEnd)) {
          state.rangeAnchor = time;
          state.selectedStart = time;
          state.selectedEnd = "";
          paintSlots();
          updateTimePreview();
          updateSummary();
          return;
        }

        let start = state.rangeAnchor;
        let endSlot = time;
        if (timeToMinutes(endSlot) < timeToMinutes(start)) {
          [start, endSlot] = [endSlot, start];
        }
        const end = minutesToTime(timeToMinutes(endSlot) + state.slotMinutes);
        const duration = timeToMinutes(end) - timeToMinutes(start);
        if (duration > maxMinutes) {
          showMessage(`حداکثر ${state.maxHours} ساعت در هر رزرو مجاز است.`, "error");
          return;
        }
        const byTime = new Map(state.slots.map((slot) => [slot.time, slot]));
        let cursor = timeToMinutes(start);
        while (cursor < timeToMinutes(end)) {
          const key = minutesToTime(cursor);
          const slot = byTime.get(key);
          if (!slot || slot.status !== "free") {
            showMessage("بین شروع و پایان، بازهٔ پر وجود دارد.", "error");
            return;
          }
          cursor += state.slotMinutes;
        }

        state.selectedStart = start;
        state.selectedEnd = end;
        state.rangeAnchor = start;
        paintSlots();
        updateTimePreview();
        updateSummary();
      });
    });
  };

  const renderMonth = () => {
    const grid = $("#publicMonthGrid");
    const label = $("#publicMonthLabel");
    const data = state.monthData;
    if (!grid || !data) return;
    if (label) label.textContent = `${data.month_name || ""} ${data.year || ""}`;
    const days = data.days || [];
    const firstOffset = days[0] ? weekdayOffset(days[0].weekday) : 0;
    const blanks = Array.from({ length: firstOffset }, () => '<span class="room-month-day is-empty"></span>');
    const cells = days.map((day) => {
      const classes = ["room-month-day"];
      if (day.is_today) classes.push("is-today");
      if (!day.bookable) classes.push("is-disabled");
      if (day.is_closed) classes.push("is-closed");
      if (day.date === state.selectedDate) classes.push("is-selected");
      return `<button type="button" class="${classes.join(" ")}" data-date="${day.date}" ${day.bookable ? "" : "disabled"}><span>${day.day}</span></button>`;
    });
    grid.innerHTML = blanks.join("") + cells.join("");
    grid.querySelectorAll(".room-month-day[data-date]:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedDate = button.dataset.date || "";
        const dayLabel = $("#publicSelectedDayLabel");
        if (dayLabel) dayLabel.textContent = `روز: ${state.selectedDate}`;
        clearRange();
        renderMonth();
        updateSummary();
        loadAvailability();
        loadWeek();
      });
    });
  };

  const loadMonth = async () => {
    state.monthData = await fetchJson(
      `public-api.php?resource=month&year=${encodeURIComponent(state.year)}&month=${encodeURIComponent(state.month)}`
    );
    renderMonth();
  };

  const loadAvailability = async () => {
    if (!state.selectedRoomId || !state.selectedDate) return;
    const grid = $("#slotGrid");
    if (grid) grid.innerHTML = '<p class="hint">در حال بارگذاری بازه‌ها…</p>';
    try {
      const data = await fetchJson(
        `public-api.php?resource=availability&room_id=${state.selectedRoomId}&date=${encodeURIComponent(state.selectedDate)}`
      );
      if (data.closed) {
        state.slots = [];
        clearRange();
        if (grid) grid.innerHTML = `<p class="hint room-closed-hint">${escapeHtml(data.closed_note || "این روز تعطیل است.")}</p>`;
        updateTimePreview();
        updateSummary();
        return;
      }
      state.slots = data.slots || [];
      state.slotMinutes = Number(data.slot_minutes || data.room?.slot_minutes || state.slotMinutes || 30);
      state.maxHours = Number(data.max_hours || state.settings.room_max_hours_per_day || state.maxHours || 2);
      paintSlots();
      updateTimePreview();
      updateSummary();
    } catch (error) {
      showMessage(error.message, "error");
      if (grid) grid.innerHTML = "";
    }
  };

  const levelLabel = (day) => {
    if (day.is_closed) return "تعطیل";
    if (day.is_past) return "گذشته";
    if (day.level === "busy") return "پر";
    if (day.level === "light") return "نیمه‌پر";
    return "آزاد";
  };

  const renderWeek = (data) => {
    const list = $("#weekStrip");
    const rangeLabel = $("#weekRangeLabel");
    const filter = $("#weekRoomFilter");
    if (!list) return;

    state.weekMeta = {
      day_start: data.day_start || "08:00",
      day_end: data.day_end || "20:00",
    };

    if (rangeLabel) {
      rangeLabel.textContent = `${data.from || ""} تا ${data.to || ""} · ${state.weekMeta.day_start}–${state.weekMeta.day_end}`;
    }

    if (filter && filter.dataset.ready !== "1") {
      filter.innerHTML = '<option value="0">همه اتاق‌ها</option>' + (data.rooms || []).map(
        (room) => `<option value="${room.id}">${escapeHtml(room.name)}</option>`
      ).join("");
      filter.value = String(state.weekRoomId || 0);
      filter.dataset.ready = "1";
    }

    list.innerHTML = (data.days || []).map((day) => {
      const classes = ["pub-week-row", `is-${day.level || "free"}`];
      if (day.is_today) classes.push("is-today");
      if (day.is_past) classes.push("is-past");
      if (day.date === state.selectedDate) classes.push("is-selected");
      const disabled = day.is_closed || day.is_past;
      const pct = day.is_closed ? 100 : Number(day.occupancy_pct || 0);
      const blocks = (day.blocks || []).map((block) => {
        const title = `${block.start_time}–${block.end_time}${block.room_name ? ` · ${block.room_name}` : ""}`;
        return `<span class="pub-week-seg pub-week-seg--${block.status === "pending" ? "pending" : "busy"}" style="inset-inline-start:${block.left_pct}%;width:${block.width_pct}%" title="${escapeHtml(title)}"></span>`;
      }).join("");
      const hours = (day.blocks || []).slice(0, 4).map((block) =>
        `<li>${escapeHtml(block.start_time)}–${escapeHtml(block.end_time)}${state.weekRoomId ? "" : ` <em>${escapeHtml(block.room_name || "")}</em>`}</li>`
      ).join("");

      return `<button type="button" class="${classes.join(" ")}" data-date="${day.date}" ${disabled ? "disabled" : ""}>
        <div class="pub-week-row-meta">
          <div class="pub-week-row-day">
            <strong>${escapeHtml(day.weekday || "")}</strong>
            <span>${day.day}</span>
          </div>
          <div class="pub-week-row-status">
            <b>${levelLabel(day)}</b>
            <small>${day.is_closed ? "رزرو غیرفعال" : (day.busy_count ? `${day.busy_count} بازه · ${pct}٪ اشغال` : "بدون رزرو")}</small>
          </div>
        </div>
        <div class="pub-week-track" aria-hidden="true">
          <span class="pub-week-track-fill" style="width:${pct}%"></span>
          ${blocks}
        </div>
        <ul class="pub-week-hours">${hours || "<li class=\"is-empty\">—</li>"}</ul>
      </button>`;
    }).join("");

    list.querySelectorAll(".pub-week-row[data-date]:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedDate = button.dataset.date || "";
        const dayLabel = $("#publicSelectedDayLabel");
        if (dayLabel) dayLabel.textContent = `روز: ${state.selectedDate}`;
        const parts = String(state.selectedDate).split("/");
        if (parts.length === 3) {
          state.year = Number(parts[0]);
          state.month = Number(parts[1]);
        }
        clearRange();
        updateSummary();
        loadMonth().then(loadAvailability).catch((error) => showMessage(error.message, "error"));
        loadWeek();
        $("#publicMonthPicker")?.scrollIntoView({ behavior: "smooth", block: "nearest" });
      });
    });
  };

  const loadWeek = async () => {
    try {
      const roomQuery = state.weekRoomId > 0 ? `&room_id=${state.weekRoomId}` : "";
      const data = await fetchJson(
        `public-api.php?resource=week&from=${encodeURIComponent(state.today)}${roomQuery}`
      );
      renderWeek(data);
    } catch {
      const list = $("#weekStrip");
      if (list) list.innerHTML = '<p class="hint">وضعیت هفته در دسترس نیست.</p>';
    }
  };

  const showSuccess = (record) => {
    $("#bookingFormShell")?.setAttribute("hidden", "");
    const card = $("#bookingSuccess");
    if (!card) return;
    card.hidden = false;
    const token = record.public_token || "";
    card.innerHTML = `
      <div class="room-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4Z" fill="currentColor"/></svg></div>
      <h2>رزرو ثبت شد</h2>
      <p class="room-card-lead">${record.status === "approved" ? "رزرو تأیید شد." : "رزرو در انتظار تأیید مدیر است."}</p>
      <p class="pub-token-label">کد پیگیری شما</p>
      <div class="room-token-box room-token-box--short" id="successToken">${escapeHtml(token)}</div>
      <button type="button" class="button ghost" id="copyTokenBtn">کپی کد</button>
      <p><strong>${escapeHtml(record.room_name || "")}</strong><br>${escapeHtml(record.reserved_date || "")} · ${escapeHtml(record.start_time || "")} تا ${escapeHtml(record.end_time || "")}</p>
      <p class="hint">این کد را برای پیگیری یا لغو نگه دارید — مثلاً MN-482917</p>`;
    card.querySelector("#copyTokenBtn")?.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(token);
        showMessage("کد پیگیری کپی شد.", "success");
      } catch {
        showMessage("کپی نشد؛ کد را دستی بردارید.", "error");
      }
    });
    card.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const bindLookup = () => {
    $("#lookupForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const token = String(new FormData(event.target).get("token") || "").trim();
      const result = $("#lookupResult");
      if (!token || !result) return;
      try {
        const data = await fetchJson(`public-api.php?resource=lookup&token=${encodeURIComponent(token)}`);
        const record = data.record || {};
        const statusMap = { pending: "در انتظار", approved: "تأیید‌شده", rejected: "رد‌شده", cancelled: "لغو‌شده" };
        result.hidden = false;
        result.innerHTML = `
          <div class="pub-summary-inline">
            <div><span>کد</span><strong dir="ltr">${escapeHtml(record.public_token || token)}</strong></div>
            <div><span>اتاق</span><strong>${escapeHtml(record.room_name || "—")}</strong></div>
            <div><span>تاریخ</span><strong>${escapeHtml(record.reserved_date || "—")}</strong></div>
            <div><span>ساعت</span><strong>${escapeHtml(record.start_time || "")} – ${escapeHtml(record.end_time || "")}</strong></div>
            <div><span>وضعیت</span><strong>${escapeHtml(statusMap[record.status] || record.status || "—")}</strong></div>
          </div>
          <div class="form-actions"><button type="button" class="button ghost" id="cancelLookupBooking">لغو رزرو</button></div>`;
        result.querySelector("#cancelLookupBooking")?.addEventListener("click", async () => {
          await fetchJson("public-api.php?resource=cancel", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: record.id, token: record.public_token }),
          });
          showMessage("رزرو لغو شد.", "success");
          result.hidden = true;
          loadWeek();
        });
      } catch (error) {
        showMessage(error.message, "error");
      }
    });
  };

  const bindForm = () => {
    $("#bookingForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!state.selectedRoomId) {
        showMessage("ابتدا اتاق را انتخاب کنید.", "error");
        return;
      }
      if (!state.selectedStart || !state.selectedEnd) {
        showMessage("بازه ساعت را کامل انتخاب کنید.", "error");
        return;
      }
      const submit = event.target.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const payload = Object.fromEntries(new FormData(event.target).entries());
        payload.room_id = String(state.selectedRoomId);
        payload.reserved_date = state.selectedDate;
        payload.start_time = state.selectedStart;
        payload.end_time = state.selectedEnd;
        const data = await fetchJson("public-api.php?resource=book", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        showSuccess(data.record || {});
        showMessage("", "success");
        loadWeek();
      } catch (error) {
        showMessage(error.message, "error");
      } finally {
        submit.disabled = false;
      }
    });
  };

  const bindControls = () => {
    $("#publicMonthPrev")?.addEventListener("click", () => {
      state.month -= 1;
      if (state.month < 1) {
        state.month = 12;
        state.year -= 1;
      }
      loadMonth().catch((error) => showMessage(error.message, "error"));
    });
    $("#publicMonthNext")?.addEventListener("click", () => {
      state.month += 1;
      if (state.month > 12) {
        state.month = 1;
        state.year += 1;
      }
      loadMonth().catch((error) => showMessage(error.message, "error"));
    });
    $("#weekRoomFilter")?.addEventListener("change", (event) => {
      state.weekRoomId = Number(event.target.value || 0);
      event.target.dataset.ready = "0";
      loadWeek();
    });
    $("#themeToggle")?.addEventListener("click", () => {
      const html = document.documentElement;
      const next = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
      html.setAttribute("data-theme", next);
      try { localStorage.setItem("mechinno-theme", next); } catch (e) {}
    });
    try {
      const t = localStorage.getItem("mechinno-theme");
      if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t);
    } catch (e) {}
  };

  const init = async () => {
    try {
      const data = await fetchJson("public-api.php?resource=config");
      if (!data.settings?.room_public_enabled) {
        showMessage("رزرو عمومی اتاق جلسه در حال حاضر غیرفعال است.", "error");
        return;
      }
      state.rooms = data.rooms || [];
      state.settings = data.settings || {};
      state.today = data.today || state.today;
      state.selectedDate = state.today;
      state.slotMinutes = Number(state.settings.room_slot_minutes || state.slotMinutes || 30);
      state.maxHours = Number(state.settings.room_max_hours_per_day || state.maxHours || 2);
      renderRooms();
      bindForm();
      bindLookup();
      bindControls();
      updateSummary();
      await Promise.all([loadWeek(), loadMonth()]);
      await loadAvailability();
    } catch (error) {
      showMessage(error.message, "error");
    }
  };

  document.addEventListener("DOMContentLoaded", init);
})();
