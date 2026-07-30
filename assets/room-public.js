(() => {
  const state = {
    rooms: [],
    settings: {},
    today: "",
    selectedRoomId: 0,
    selectedDate: "",
    selectedStart: "",
    selectedEnd: "",
    slots: [],
    lastBooking: null,
  };

  const $ = (selector) => document.querySelector(selector);

  const showMessage = (text, type = "info") => {
    const host = $("#roomPublicMessage");
    if (!host) return;
    host.textContent = text;
    host.className = `room-public-message room-public-message--${type}`;
    host.hidden = !text;
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      headers: { Accept: "application/json", ...(options.headers || {}) },
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || "خطا در ارتباط با سرور");
    }
    return data;
  };

  const renderRooms = () => {
    const select = $("#roomSelect");
    if (!select) return;
    select.innerHTML = state.rooms
      .map((room) => `<option value="${room.id}">${room.name}${room.code ? ` (${room.code})` : ""} — ${room.capacity} نفر</option>`)
      .join("");
    if (state.rooms.length > 0) {
      state.selectedRoomId = Number(state.rooms[0].id);
      select.value = String(state.selectedRoomId);
    }
  };

  const renderSlots = () => {
    const grid = $("#slotGrid");
    if (!grid) return;
    if (!state.slots.length) {
      grid.innerHTML = '<p class="hint">برای این روز بازه‌ای موجود نیست.</p>';
      return;
    }
    grid.innerHTML = state.slots
      .map((slot) => {
        const disabled = slot.status !== "free";
        const cls = `room-slot room-slot--${slot.status}${state.selectedStart === slot.time ? " is-selected" : ""}`;
        const label = slot.status === "busy" ? "رزرو شده" : slot.status === "pending" ? "در انتظار" : slot.time;
        return `<button type="button" class="${cls}" data-time="${slot.time}" data-end="${slot.end}" ${disabled ? "disabled" : ""}>${label}</button>`;
      })
      .join("");
    grid.querySelectorAll(".room-slot:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        state.selectedStart = button.dataset.time || "";
        state.selectedEnd = button.dataset.end || "";
        grid.querySelectorAll(".room-slot").forEach((el) => el.classList.remove("is-selected"));
        button.classList.add("is-selected");
        const endSelect = $("#durationSlots");
        if (endSelect) {
          endSelect.value = "1";
          updateEndTime();
        }
      });
    });
  };

  const updateEndTime = () => {
    const durationSelect = $("#durationSlots");
    if (!durationSelect || !state.selectedStart) return;
    const slotCount = Number(durationSelect.value || 1);
    const room = state.rooms.find((item) => Number(item.id) === Number(state.selectedRoomId));
    const slotMinutes = Number(room?.slot_minutes || state.settings.room_slot_minutes || 60);
    const [hour, minute] = state.selectedStart.split(":").map(Number);
    const startMinutes = hour * 60 + minute;
    const endMinutes = startMinutes + slotCount * slotMinutes;
    const endHour = String(Math.floor(endMinutes / 60)).padStart(2, "0");
    const endMinute = String(endMinutes % 60).padStart(2, "0");
    state.selectedEnd = `${endHour}:${endMinute}`;
    const preview = $("#timePreview");
    if (preview) {
      preview.textContent = state.selectedStart && state.selectedEnd
        ? `بازه انتخابی: ${state.selectedStart} تا ${state.selectedEnd}`
        : "";
    }
  };

  const loadAvailability = async () => {
    if (!state.selectedRoomId || !state.selectedDate) return;
    showMessage("");
    const grid = $("#slotGrid");
    if (grid) grid.innerHTML = '<p class="hint">در حال بارگذاری…</p>';
    try {
      const data = await fetchJson(
        `public-api.php?resource=availability&room_id=${encodeURIComponent(state.selectedRoomId)}&date=${encodeURIComponent(state.selectedDate)}`
      );
      state.slots = data.slots || [];
      state.selectedStart = "";
      state.selectedEnd = "";
      renderSlots();
    } catch (error) {
      showMessage(error.message, "error");
      if (grid) grid.innerHTML = "";
    }
  };

  const showSuccess = (record) => {
    state.lastBooking = record;
    const card = $("#bookingSuccess");
    if (!card) return;
    card.hidden = false;
    card.innerHTML = `
      <h2>رزرو ثبت شد</h2>
      <p><strong>کد پیگیری:</strong> <span dir="ltr">${record.public_token || ""}</span></p>
      <p><strong>اتاق:</strong> ${record.room_name || ""}</p>
      <p><strong>تاریخ:</strong> ${record.reserved_date || ""}</p>
      <p><strong>ساعت:</strong> ${record.start_time || ""} تا ${record.end_time || ""}</p>
      <p><strong>وضعیت:</strong> ${record.status === "approved" ? "تأیید شده" : "در انتظار تأیید"}</p>
      <p class="hint">این کد را برای پیگیری یا لغو رزرو نگه دارید.</p>`;
    $("#bookingForm")?.scrollIntoView({ behavior: "smooth", block: "start" });
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
        result.hidden = false;
        result.innerHTML = `
          <p><strong>اتاق:</strong> ${record.room_name || ""}</p>
          <p><strong>تاریخ:</strong> ${record.reserved_date || ""}</p>
          <p><strong>ساعت:</strong> ${record.start_time || ""} تا ${record.end_time || ""}</p>
          <p><strong>وضعیت:</strong> ${record.status || ""}</p>
          <button type="button" class="button ghost" id="cancelLookupBooking" data-id="${record.id}" data-token="${record.public_token || ""}">لغو رزرو</button>`;
        result.querySelector("#cancelLookupBooking")?.addEventListener("click", async () => {
          try {
            await fetchJson("public-api.php?resource=cancel", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: record.id, token: record.public_token }),
            });
            showMessage("رزرو لغو شد.", "success");
            result.hidden = true;
          } catch (error) {
            showMessage(error.message, "error");
          }
        });
      } catch (error) {
        showMessage(error.message, "error");
      }
    });
  };

  const bindForm = () => {
    const form = $("#bookingForm");
    if (!form) return;
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!state.selectedStart || !state.selectedEnd) {
        showMessage("یک بازه زمانی انتخاب کنید.", "error");
        return;
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.room_id = String(state.selectedRoomId);
      payload.reserved_date = state.selectedDate;
      payload.start_time = state.selectedStart;
      payload.end_time = state.selectedEnd;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const data = await fetchJson("public-api.php?resource=book", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        showSuccess(data.record || {});
        showMessage("رزرو با موفقیت ثبت شد.", "success");
        await loadAvailability();
      } catch (error) {
        showMessage(error.message, "error");
      } finally {
        submit.disabled = false;
      }
    });
  };

  const init = async () => {
    try {
      const data = await fetchJson("public-api.php?resource=config");
      if (!data.settings?.room_public_enabled) {
        showMessage("رزرو عمومی اتاق جلسه در حال حاضر غیرفعال است.", "error");
        $("#bookingForm")?.setAttribute("hidden", "");
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
      $("#roomSelect")?.addEventListener("change", (event) => {
        state.selectedRoomId = Number(event.target.value || 0);
        loadAvailability();
      });
      $("#reserveDate")?.addEventListener("change", (event) => {
        state.selectedDate = event.target.value || state.today;
        loadAvailability();
      });
      $("#durationSlots")?.addEventListener("change", updateEndTime);
      await loadAvailability();
    } catch (error) {
      showMessage(error.message, "error");
    }
  };

  document.addEventListener("DOMContentLoaded", init);
})();
