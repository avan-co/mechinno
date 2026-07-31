/**
 * Shared hotel-style time range selection for admin / team / public booking.
 *
 * 1) First click = start time (slot start)
 * 2) Second click = exclusive end time (the clock time shown on the button)
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

  const inRange = (time, start, endExclusive) => {
    if (!start || !endExclusive) return false;
    const t = timeToMinutes(time);
    return t >= timeToMinutes(start) && t < timeToMinutes(endExclusive);
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

    let start = anchor;
    let end = clicked;
    if (timeToMinutes(end) < timeToMinutes(start)) {
      [start, end] = [end, start];
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
    resolveRange,
    canUseAsEnd,
    durationLabel,
  };
})();
