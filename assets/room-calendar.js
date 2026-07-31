(() => {
  const today = window.MECHINNO?.today || "";

  const state = {
    weekStart: today,
    roomId: 0,
    initialized: false,
  };

  const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || "خطا در دریافت تقویم");
    }
    return data;
  };

  const escapeHtml = (value) => String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

  const dayMap = (data) => {
    const detail = data.days_detail || [];
    const map = new Map();
    detail.forEach((item) => map.set(item.date, item));
    (data.days || []).forEach((date) => {
      if (!map.has(date)) {
        map.set(date, { date, weekday: "", day: Number(String(date).slice(-2)) });
      }
    });
    return map;
  };

  const formatDayLabel = (meta) => {
    if (!meta) return "";
    return `<span class="room-calendar-day-name">${escapeHtml(meta.weekday || "")}</span><span class="room-calendar-day-num">${meta.day || ""}</span>`;
  };

  const renderCalendar = (data) => {
    const grid = document.getElementById("roomCalendarGrid");
    const rangeLabel = document.getElementById("roomCalendarRangeLabel");
    if (!grid) return;

    const rooms = data.rooms || [];
    const days = data.days || [];
    const events = data.events || [];
    const daysMeta = dayMap(data);

    if (rangeLabel) {
      rangeLabel.textContent = `از ${data.from || ""} تا ${data.to || ""}`;
    }

    if (!rooms.length || !days.length) {
      grid.innerHTML = '<p class="hint">اتاق یا بازه‌ای برای نمایش وجود ندارد.</p>';
      return;
    }

    const header = `<div class="room-calendar-row room-calendar-row--head">
      <div class="room-calendar-room-head">اتاق</div>
      ${days.map((day) => {
        const meta = daysMeta.get(day);
        const closed = Boolean(meta?.is_closed);
        return `<div class="room-calendar-day-head${day === data.today ? " is-today" : ""}${closed ? " is-closed" : ""}" data-date="${day}" title="${escapeHtml(closed ? (meta.closed_note || "تعطیل") : "")}">${formatDayLabel(meta)}${closed ? '<small class="room-calendar-closed-tag">تعطیل</small>' : ""}</div>`;
      }).join("")}
    </div>`;

    const rows = rooms.map((room) => {
      const cells = days.map((day) => {
        const meta = daysMeta.get(day);
        const dayEvents = events.filter(
          (event) => Number(event.room_id) === Number(room.id) && event.reserved_date === day
        );
        const blocks = dayEvents.map((event) => {
          const statusClass = event.status === "pending" ? "is-pending" : "is-approved";
          const title = `${event.start_time}–${event.end_time} · ${event.booker_name || ""}`;
          return `<button type="button" class="room-calendar-block ${statusClass}" title="${escapeHtml(title)}" data-date="${day}" data-room-id="${room.id}">
            <span>${event.start_time}–${event.end_time}</span>
            <small>${escapeHtml(event.booker_name || "—")}</small>
          </button>`;
        }).join("");
        return `<div class="room-calendar-cell${day === data.today ? " is-today" : ""}${meta?.is_closed ? " is-closed" : ""}" data-date="${day}" data-room-id="${room.id}">
          ${meta?.is_closed ? '<span class="room-calendar-empty">تعطیل</span>' : (blocks || '<span class="room-calendar-empty">—</span>')}
        </div>`;
      }).join("");
      return `<div class="room-calendar-row">
        <div class="room-calendar-room">
          <strong>${escapeHtml(room.name)}</strong>
          <small>${room.capacity ? `${room.capacity} نفر` : ""}${room.floor ? ` · ${escapeHtml(room.floor)}` : ""}</small>
        </div>
        ${cells}
      </div>`;
    }).join("");

    grid.innerHTML = header + rows;

    grid.querySelectorAll(".room-calendar-cell, .room-calendar-day-head").forEach((cell) => {
      cell.addEventListener("click", (event) => {
        if (event.target.closest(".room-calendar-block")) return;
        const date = cell.dataset.date;
        const roomId = cell.dataset.roomId;
        if (date) window.setPanelBookingDate?.(date);
        if (roomId) window.setPanelBookingRoom?.(Number(roomId));
      });
    });
  };

  const syncRoomFilter = (rooms) => {
    const select = document.getElementById("roomCalendarRoomFilter");
    if (!select) return;
    const current = String(state.roomId || 0);
    select.innerHTML = '<option value="0">همه اتاق‌ها</option>' + rooms.map(
      (room) => `<option value="${room.id}"${String(room.id) === current ? " selected" : ""}>${escapeHtml(room.name)}</option>`
    ).join("");
  };

  const loadCalendar = async (shiftDays = 0) => {
    const roomQuery = state.roomId > 0 ? `&room_id=${encodeURIComponent(state.roomId)}` : "";
    let url = `api.php?resource=room-calendar&from=${encodeURIComponent(state.weekStart || today)}${roomQuery}`;
    if (shiftDays !== 0) {
      url = `api.php?resource=room-calendar&shift_from=${encodeURIComponent(state.weekStart || today)}&shift_days=${shiftDays}${roomQuery}`;
    }
    const data = await fetchJson(url);
    state.weekStart = data.from || state.weekStart || today;
    syncRoomFilter(data.rooms || []);
    renderCalendar(data);
  };

  const bindControls = () => {
    if (state.initialized) return;
    state.initialized = true;

    document.getElementById("roomCalendarPrev")?.addEventListener("click", () => {
      loadCalendar(-7).catch((error) => window.showToast?.(error.message, "error"));
    });
    document.getElementById("roomCalendarNext")?.addEventListener("click", () => {
      loadCalendar(7).catch((error) => window.showToast?.(error.message, "error"));
    });
    document.getElementById("roomCalendarToday")?.addEventListener("click", () => {
      state.weekStart = today;
      loadCalendar(0).catch((error) => window.showToast?.(error.message, "error"));
    });
    document.getElementById("roomCalendarRoomFilter")?.addEventListener("change", (event) => {
      state.roomId = Number(event.target.value || 0);
      loadCalendar(0).catch((error) => window.showToast?.(error.message, "error"));
    });
  };

  window.initRoomCalendar = () => {
    const panel = document.getElementById("roomCalendarPanel");
    if (!panel) return;
    bindControls();
    if (!state.weekStart) state.weekStart = today;
    loadCalendar(0).catch((error) => window.showToast?.(error.message, "error"));
  };

  window.refreshRoomCalendar = () => {
    if (!document.getElementById("roomCalendarPanel")) return;
    loadCalendar(0).catch(() => {});
  };

  // This script loads after app.js, so activateSection() on initial page load
  // runs before window.initRoomCalendar is defined. Self-initialize here when the
  // reservation section is already active (e.g. a direct #meeting-rooms link).
  document.addEventListener("DOMContentLoaded", () => {
    const activeCalendarSection = document.querySelector(
      "#meeting-rooms.section.active, #room-reservations.section.active"
    );
    if (activeCalendarSection) window.initRoomCalendar();
  });
})();
