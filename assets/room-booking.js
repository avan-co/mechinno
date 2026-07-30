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
          <strong>${room.name}${room.code ? ` · ${room.code}` : ""}</strong>
          <small>${room.floor ? `${room.floor} · ` : ""}${room.open_time || "08:00"} تا ${room.close_time || "20:00"}</small>
        </span>
        <span class="room-room-capacity">${room.capacity} نفر</span>
      </button>`;
    }).join("");
    grid.querySelectorAll(".room-room-option").forEach((button) => {
      button.addEventListener("click", () => {
        onSelect(Number(button.dataset.roomId || 0));
      });
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
      const status = count > 0 ? `${count} رزرو امروز` : "آزاد امروز";
      return `<article class="room-overview-card">
        <span class="room-room-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 3h7v4h-7v-4Z" fill="currentColor"/></svg></span>
        <div class="room-overview-copy">
          <strong>${room.name}</strong>
          <small>${room.floor ? `${room.floor} · ` : ""}${room.capacity} نفر · ${room.open_time || "08:00"}–${room.close_time || "20:00"}</small>
        </div>
        <span class="room-overview-badge${count > 0 ? " is-busy" : ""}">${status}</span>
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
    const roomInput = form.querySelector('[name="room_id"]');
    const cardGrid = document.getElementById("panelRoomCardGrid");
    const dateInput = form.querySelector('[name="reserved_date"]');
    const slotGrid = document.getElementById("panelRoomSlotGrid");
    const preview = document.getElementById("panelRoomTimePreview");
    let rooms = [];
    let selectedRoomId = 0;
    let selectedStart = "";
    let selectedEnd = "";

    const selectRoom = (roomId) => {
      selectedRoomId = roomId;
      if (roomInput) roomInput.value = roomId > 0 ? String(roomId) : "";
      renderRoomCards(cardGrid, rooms, selectedRoomId, selectRoom);
      loadSlots();
    };

    const loadRooms = async () => {
      const data = await fetchJson("api.php?resource=meeting-rooms&per_page=100");
      rooms = (data.rows || []).filter((room) => Number(room.is_active) === 1);
      if (!selectedRoomId && rooms[0]) {
        selectedRoomId = Number(rooms[0].id);
      }
      if (roomInput) roomInput.value = selectedRoomId > 0 ? String(selectedRoomId) : "";
      renderRoomCards(cardGrid, rooms, selectedRoomId, selectRoom);
    };

    const loadSlots = async () => {
      const roomId = selectedRoomId || roomInput?.value;
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
      if (!selectedRoomId) {
        window.showToast?.("یک اتاق انتخاب کنید.", "error");
        return;
      }
      if (!selectedStart || !selectedEnd) {
        window.showToast?.("یک بازه زمانی انتخاب کنید.", "error");
        return;
      }
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.room_id = selectedRoomId;
      payload.start_time = selectedStart;
      payload.end_time = selectedEnd;
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await postJson("api.php?resource=room-reservations&action=create", payload);
        window.showToast?.("رزرو ثبت شد.", "success");
        form.reset();
        if (roomInput) roomInput.value = String(selectedRoomId);
        dateInput.value = today;
        await loadSlots();
        document.querySelector('#meeting-rooms data-table[endpoint*="room-reservations"]')?.load?.();
        document.querySelector('#room-reservations data-table')?.load?.();
        window.refreshRoomCalendar?.();
      } catch (error) {
        window.showToast?.(error.message, "error");
      } finally {
        button.disabled = false;
      }
    });

    dateInput?.addEventListener("change", loadSlots);
    if (dateInput && !dateInput.value) dateInput.value = today;

    window.setPanelBookingDate = (date) => {
      if (!dateInput) return;
      dateInput.value = date;
      loadSlots();
    };

    window.setPanelBookingRoom = (roomId) => {
      selectRoom(Number(roomId));
    };

    loadRooms()
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
    loadRoomOverview();
  });

  window.renderRoomCards = renderRoomCards;
})();
