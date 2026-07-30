(() => {
  const state = {
    step: 1,
    rooms: [],
    settings: {},
    today: "",
    selectedRoomId: 0,
    selectedDate: "",
    selectedStart: "",
    selectedEnd: "",
    slots: [],
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));

  const showMessage = (text, type = "info") => {
    const host = $("#roomPublicMessage");
    if (!host) return;
    host.textContent = text;
    host.className = `room-alert room-alert--${type}`;
    host.hidden = !text;
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
  };

  const setStep = (step) => {
    state.step = step;
    $$("[data-step-pill]").forEach((pill) => {
      const pillStep = Number(pill.dataset.stepPill || 0);
      pill.classList.toggle("is-active", pillStep === step);
      pill.classList.toggle("is-done", pillStep < step);
    });
    $("#stepRooms")?.toggleAttribute("hidden", step !== 1);
    $("#stepSchedule")?.toggleAttribute("hidden", step !== 2);
    $("#stepDetails")?.toggleAttribute("hidden", step !== 3);
    const nextButton = $("#nextStepButton");
    if (!nextButton) return;
    if (step === 1) {
      nextButton.textContent = "انتخاب زمان";
      nextButton.disabled = state.selectedRoomId <= 0;
    } else if (step === 2) {
      nextButton.textContent = "اطلاعات رزروکننده";
      nextButton.disabled = !state.selectedStart || !state.selectedEnd;
    } else {
      nextButton.hidden = true;
    }
    updateSummary();
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
          <strong>${room.name}${room.code ? ` · ${room.code}` : ""}</strong>
          <small>${room.floor ? `${room.floor} · ` : ""}${room.open_time || "08:00"} تا ${room.close_time || "20:00"}</small>
        </span>
        <span class="room-room-capacity">${room.capacity} نفر</span>
      </button>`;
    }).join("");
    grid.querySelectorAll(".room-room-option").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedRoomId = Number(button.dataset.roomId || 0);
        renderRooms();
        setStep(1);
      });
    });
    if (state.selectedRoomId <= 0 && state.rooms[0]) {
      state.selectedRoomId = Number(state.rooms[0].id);
      renderRooms();
    }
  };

  const renderSlots = () => {
    const grid = $("#slotGrid");
    if (!grid) return;
    if (!state.slots.length) {
      grid.innerHTML = '<p class="hint">برای این روز بازه‌ای موجود نیست.</p>';
      return;
    }
    grid.innerHTML = state.slots.map((slot) => {
      const disabled = slot.status !== "free";
      const label = slot.status === "busy" ? "پر" : slot.status === "pending" ? "انتظار" : slot.time;
      return `<button type="button" class="room-slot room-slot--${slot.status}${state.selectedStart === slot.time ? " is-selected" : ""}" data-time="${slot.time}" data-end="${slot.end}" ${disabled ? "disabled" : ""}>${label}</button>`;
    }).join("");
    grid.querySelectorAll(".room-slot:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedStart = button.dataset.time || "";
        $("#durationSlots").value = "1";
        updateEndTime();
        renderSlots();
        setStep(2);
      });
    });
  };

  const updateEndTime = () => {
    if (!state.selectedStart) return;
    const slotCount = Number($("#durationSlots")?.value || 1);
    const room = selectedRoom();
    const slotMinutes = Number(room?.slot_minutes || state.settings.room_slot_minutes || 60);
    const [hour, minute] = state.selectedStart.split(":").map(Number);
    const endMinutes = (hour * 60 + minute) + (slotCount * slotMinutes);
    state.selectedEnd = `${String(Math.floor(endMinutes / 60)).padStart(2, "0")}:${String(endMinutes % 60).padStart(2, "0")}`;
    const preview = $("#timePreview");
    if (preview) preview.textContent = `بازه انتخابی: ${state.selectedStart} تا ${state.selectedEnd}`;
    updateSummary();
    setStep(2);
  };

  const loadAvailability = async () => {
    if (!state.selectedRoomId || !state.selectedDate) return;
    const grid = $("#slotGrid");
    if (grid) grid.innerHTML = '<p class="hint">در حال بارگذاری بازه‌ها…</p>';
    try {
      const data = await fetchJson(`public-api.php?resource=availability&room_id=${state.selectedRoomId}&date=${encodeURIComponent(state.selectedDate)}`);
      state.slots = data.slots || [];
      state.selectedStart = "";
      state.selectedEnd = "";
      renderSlots();
      updateSummary();
    } catch (error) {
      showMessage(error.message, "error");
      if (grid) grid.innerHTML = "";
    }
  };

  const showSuccess = (record) => {
    $("#bookingLayout")?.setAttribute("hidden", "");
    $$(".room-steps [data-step-pill]").forEach((pill) => pill.classList.add("is-done"));
    const card = $("#bookingSuccess");
    if (!card) return;
    card.hidden = false;
    card.innerHTML = `
      <div class="room-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4Z" fill="currentColor"/></svg></div>
      <h2>رزرو شما ثبت شد</h2>
      <p class="room-card-lead">${record.status === "approved" ? "رزرو تأیید شد و آماده استفاده است." : "رزرو در انتظار تأیید مدیر است."}</p>
      <div class="room-token-box">${record.public_token || ""}</div>
      <p><strong>${record.room_name || ""}</strong> · ${record.reserved_date || ""} · ${record.start_time || ""} تا ${record.end_time || ""}</p>
      <p class="hint">کد بالا را برای پیگیری یا لغو نگه دارید.</p>`;
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
          <div class="room-summary-item"><span>اتاق</span><strong>${record.room_name || "—"}</strong></div>
          <div class="room-summary-item"><span>تاریخ</span><strong>${record.reserved_date || "—"}</strong></div>
          <div class="room-summary-item"><span>ساعت</span><strong>${record.start_time || ""} – ${record.end_time || ""}</strong></div>
          <div class="room-summary-item"><span>وضعیت</span><strong>${statusMap[record.status] || record.status || "—"}</strong></div>
          <div class="form-actions"><button type="button" class="button ghost" id="cancelLookupBooking">لغو رزرو</button></div>`;
        result.querySelector("#cancelLookupBooking")?.addEventListener("click", async () => {
          await fetchJson("public-api.php?resource=cancel", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: record.id, token: record.public_token }),
          });
          showMessage("رزرو لغو شد.", "success");
          result.hidden = true;
        });
      } catch (error) {
        showMessage(error.message, "error");
      }
    });
  };

  const bindForm = () => {
    $("#bookingForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
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
      } catch (error) {
        showMessage(error.message, "error");
      } finally {
        submit.disabled = false;
      }
    });
  };

  const bindControls = () => {
    $("#nextStepButton")?.addEventListener("click", () => {
      if (state.step === 1) {
        setStep(2);
        loadAvailability();
        return;
      }
      if (state.step === 2) setStep(3);
    });
    $("#backToSchedule")?.addEventListener("click", () => setStep(2));
    $("#reserveDate")?.addEventListener("change", (event) => {
      state.selectedDate = event.target.value || state.today;
      loadAvailability();
    });
    $("#durationSlots")?.addEventListener("change", updateEndTime);
  };

  const init = async () => {
    try {
      const data = await fetchJson("public-api.php?resource=config");
      if (!data.settings?.room_public_enabled) {
        showMessage("رزرو عمومی اتاق جلسه در حال حاضر غیرفعال است.", "error");
        $("#bookingLayout")?.setAttribute("hidden", "");
        return;
      }
      state.rooms = data.rooms || [];
      state.settings = data.settings || {};
      state.today = data.today || "";
      state.selectedDate = state.today;
      const dateInput = $("#reserveDate");
      if (dateInput) dateInput.value = state.today;
      renderRooms();
      bindForm();
      bindLookup();
      bindControls();
      setStep(1);
    } catch (error) {
      showMessage(error.message, "error");
      $("#bookingLayout")?.setAttribute("hidden", "");
    }
  };

  document.addEventListener("DOMContentLoaded", init);
})();
