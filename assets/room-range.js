/**
 * Shared hotel-style time range selection for admin / team / public booking.
 *
 * 1) First click = start time (slot start)
 * 2) Second click = a later, exclusive end time (the clock time shown on the button)
 *    Example: 10:00 then 12:00 => 2 hours
 * Same-slot second click => one slot (start + slotMinutes).
 */
(() => {
  const minutesToTime = (minutes) => {
    const hour = Math.floor(minutes / 60);
    const minute = minutes % 60;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
  };

  const timeToMinutes = (time) => {
    const [hour, minute] = String(time || "0:0").split(":").map(Number);
    return (hour * 60) + minute;
  };

  const isFreeBetween = (slots, start, endExclusive, slotMinutes) => {
    const byTime = new Map((slots || []).map((slot) => [slot.time, slot]));
    let cursor = timeToMinutes(start);
    const end = timeToMinutes(endExclusive);
    while (cursor < end) {
      const slot = byTime.get(minutesToTime(cursor));
      if (!slot || slot.status !== "free") return false;
      cursor += slotMinutes;
    }
    return true;
  };

  /** Occupied slot starts inside [start, end) — used for booking logic. */
  const inRange = (time, start, endExclusive) => {
    if (!start || !endExclusive) return false;
    const t = timeToMinutes(time);
    return t >= timeToMinutes(start) && t < timeToMinutes(endExclusive);
  };

  /**
   * Visual highlight for UI: include the exclusive end button so
   * 08:00→10:00 lights 08:00 … 10:00 (not only through 09:30).
   */
  const inRangeDisplay = (time, start, endExclusive) => {
    if (!start || !endExclusive) return false;
    const t = timeToMinutes(time);
    return t >= timeToMinutes(start) && t <= timeToMinutes(endExclusive);
  };

  /**
   * Label/classes for a slot button while selecting or after a range is chosen.
   * Booking still uses exclusive end; display marks start + end clearly.
   */
  const slotPresentation = ({
    time,
    status = "free",
    selectedStart = "",
    selectedEnd = "",
    rangeAnchor = "",
    choosingEnd = false,
    validEnd = false,
  }) => {
    const busy = status !== "free";
    const complete = Boolean(selectedStart && selectedEnd);
    const isStart = complete
      ? time === selectedStart
      : (rangeAnchor === time || selectedStart === time);
    const isEnd = complete && time === selectedEnd;
    const highlighted = complete
      ? inRangeDisplay(time, selectedStart, selectedEnd)
      : (rangeAnchor === time);

    let label = time;
    if (complete && isStart && isEnd) {
      label = time; // zero-width shouldn't happen; keep clock
    } else if (complete && isStart) {
      label = `شروع ${time}`;
    } else if (complete && isEnd) {
      label = `پایان ${time}`;
    } else if (choosingEnd && validEnd) {
      label = time === rangeAnchor ? `شروع ${time}` : `تا ${time}`;
    } else if (status === "busy") {
      label = "پر";
    } else if (status === "pending") {
      label = "انتظار";
    }

    const classes = ["room-slot", `room-slot--${status}`];
    if (highlighted) classes.push("is-in-range");
    if (isStart) classes.push("is-selected", "is-range-start");
    if (isEnd) classes.push("is-selected", "is-range-end");
    if (choosingEnd && validEnd) classes.push("is-end-candidate");
    if (choosingEnd && !validEnd && !busy) classes.push("is-out-of-reach");

    return {
      label,
      classes,
      highlighted,
      isStart,
      isEnd,
      // While picking end only valid ends are clickable; otherwise only free starts.
      disabled: choosingEnd ? !validEnd : busy,
    };
  };

  /**
   * Resolve a completed range from anchor start and second click.
   * @returns {{ ok: true, start: string, end: string, minutes: number } | { ok: false, error: string }}
   */
  const resolveRange = ({
    anchor,
    clicked,
    slotMinutes = 30,
    maxHours = 2,
    slots = [],
  }) => {
    const step = Math.max(1, Number(slotMinutes) || 30);
    const maxMinutes = Math.max(step, (Number(maxHours) || 2) * 60);
    if (!anchor || !clicked) {
      return { ok: false, error: "بازه زمانی معتبر نیست." };
    }

    const start = anchor;
    let end = clicked;
    if (timeToMinutes(end) < timeToMinutes(start)) {
      return { ok: false, error: "ساعت پایان باید بعد از ساعت شروع باشد." };
    }
    // Same button twice => book exactly one slot.
    if (timeToMinutes(end) === timeToMinutes(start)) {
      end = minutesToTime(timeToMinutes(start) + step);
    }

    const minutes = timeToMinutes(end) - timeToMinutes(start);
    if (minutes < step) {
      return { ok: false, error: "بازه زمانی معتبر نیست." };
    }
    if (minutes > maxMinutes) {
      return { ok: false, error: `حداکثر ${maxHours} ساعت در هر رزرو مجاز است.` };
    }
    if (minutes % step !== 0) {
      return { ok: false, error: `بازه باید مضرب ${step} دقیقه باشد.` };
    }
    if (!isFreeBetween(slots, start, end, step)) {
      return { ok: false, error: "بین شروع و پایان، بازهٔ پر یا در انتظار وجود دارد." };
    }

    return { ok: true, start, end, minutes };
  };

  /** Whether a clock time can be used as exclusive end for the current start. */
  const canUseAsEnd = ({
    anchor,
    candidate,
    slotMinutes = 30,
    maxHours = 2,
    slots = [],
  }) => resolveRange({
    anchor,
    clicked: candidate,
    slotMinutes,
    maxHours,
    slots,
  }).ok;

  const durationLabel = (minutes) => {
    if (minutes < 60) return `${minutes} دقیقه`;
    if (minutes % 60 === 0) return `${minutes / 60} ساعت`;
    return `${(minutes / 60).toFixed(1)} ساعت`;
  };

  window.MechinnoRoomRange = {
    minutesToTime,
    timeToMinutes,
    isFreeBetween,
    inRange,
    inRangeDisplay,
    slotPresentation,
    resolveRange,
    canUseAsEnd,
    durationLabel,
  };
})();
