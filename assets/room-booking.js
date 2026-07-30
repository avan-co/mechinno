(() => {
  const csrfToken = window.MECHINNO?.csrfToken || "";
  const today = window.MECHINNO?.today || "";

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
    if (!response.ok) {
      throw new Error(data.error || "خطا در ثبت رزرو");
    }
    return data;
  };

  const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || "خطا در دریافت اطلاعات");
    }
    return data;
  };

  const renderSlots = (container, slots, onSelect) => {
    if (!container) return;
    if (!slots.length) {
      container.innerHTML = '<p class="hint">برای این روز بازه‌ای موجود نیست.</p>';
      return;
    }
    container.innerHTML = slots
      .map((slot) => {
        const disabled = slot.status !== "free";
        const label = slot.status === "busy" ? "رزرو" : slot.status === "pending" ? "انتظار" : slot.time;
        return `<button type="button" class="room-slot room-slot--${slot.status}" data-time="${slot.time}" data-end="${slot.end}" ${disabled ? "disabled" : ""}>${label}</button>`;
      })
      .join("");
    container.querySelectorAll(".room-slot:not([disabled])").forEach((button) => {
      button.addEventListener("click", () => {
        container.querySelectorAll(".room-slot").forEach((el) => el.classList.remove("is-selected"));
        button.classList.add("is-selected");
        onSelect(button.dataset.time || "", button.dataset.end || "");
      });
    });
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
        form.room_slot_minutes.value = String(settings.room_slot_minutes || 60);
      })
      .catch((error) => window.showToast?.(error.message, "error"));
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-settings", payload);
        window.showToast?.("تنظیمات ذخیره شد.", "success");
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });
  };

  const initPanelBooking = () => {
    const form = document.getElementById("panelRoomBookingForm");
    if (!form) return;
    const roomSelect = form.querySelector('[name="room_id"]');
    const dateInput = form.querySelector('[name="reserved_date"]');
    const slotGrid = document.getElementById("panelRoomSlotGrid");
    const preview = document.getElementById("panelRoomTimePreview");
    let selectedStart = "";
    let selectedEnd = "";

    const loadRooms = async () => {
      const data = await fetchJson("api.php?resource=meeting-rooms&per_page=100");
      const rooms = (data.rows || []).filter((room) => Number(room.is_active) === 1);
      roomSelect.innerHTML = rooms
        .map((room) => `<option value="${room.id}">${room.name}</option>`)
        .join("");
    };

    const loadSlots = async () => {
      const roomId = roomSelect.value;
      const date = dateInput.value || today;
      if (!roomId || !date) return;
      const data = await fetchJson(
        `api.php?resource=room-availability&room_id=${encodeURIComponent(roomId)}&date=${encodeURIComponent(date)}`
      );
      selectedStart = "";
      selectedEnd = "";
      if (preview) preview.textContent = "";
      renderSlots(slotGrid, data.slots || [], (start, end) => {
        selectedStart = start;
        selectedEnd = end;
        if (preview) preview.textContent = `بازه: ${start} تا ${end}`;
      });
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!selectedStart || !selectedEnd) {
        window.showToast?.("یک بازه زمانی انتخاب کنید.", "error");
        return;
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.start_time = selectedStart;
      payload.end_time = selectedEnd;
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-reservations&action=create", payload);
        window.showToast?.("رزرو ثبت شد.", "success");
        form.reset();
        dateInput.value = today;
        await loadSlots();
        document.querySelector('#meeting-rooms data-table[endpoint*="room-reservations"]')?.load?.();
        document.querySelector('#room-reservations data-table')?.load?.();
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    roomSelect?.addEventListener("change", loadSlots);
    dateInput?.addEventListener("change", loadSlots);
    if (dateInput && !dateInput.value) dateInput.value = today;

    loadRooms()
      .then(loadSlots)
      .catch((error) => window.showToast?.(error.message, "error"));
  };

  document.addEventListener("DOMContentLoaded", () => {
    initRoomSettings();
    initPanelBooking();
  });
})();
