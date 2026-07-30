<?php

declare(strict_types=1);

final class RoomReservations
{
    private const ACTIVE_STATUSES = ['pending', 'approved'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $this->ensureSettingsColumns();
        $row = $this->pdo->query(
            'SELECT room_auto_approve, room_max_advance_days, room_max_hours_per_day, room_slot_minutes, room_public_enabled
             FROM center_settings WHERE id = 1'
        )->fetch() ?: [];

        return [
            'room_auto_approve' => (int) ($row['room_auto_approve'] ?? 1) === 1,
            'room_max_advance_days' => max(1, (int) ($row['room_max_advance_days'] ?? 14)),
            'room_max_hours_per_day' => max(1, (int) ($row['room_max_hours_per_day'] ?? 2)),
            'room_slot_minutes' => in_array((int) ($row['room_slot_minutes'] ?? 60), [30, 60], true)
                ? (int) ($row['room_slot_minutes'] ?? 60)
                : 60,
            'room_public_enabled' => (int) ($row['room_public_enabled'] ?? 1) === 1,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveRooms(): array
    {
        if (!Schema::tableExists($this->pdo, 'meeting_rooms')) {
            return [];
        }

        $statement = $this->pdo->query(
            "SELECT id, name, code, capacity, floor, equipment, open_time, close_time, slot_minutes, notes
             FROM meeting_rooms
             WHERE is_active = 1
             ORDER BY name, id"
        );

        return $statement->fetchAll() ?: [];
    }

    /**
     * @return array{date: string, room: array<string, mixed>, slots: list<array<string, mixed>>}
     */
    public function availability(int $roomId, string $date): array
    {
        $room = $this->roomRow($roomId);
        if ((int) ($room['is_active'] ?? 0) !== 1) {
            throw new InvalidArgumentException('اتاق فعال نیست.');
        }

        $normalizedDate = JalaliDate::normalize($date);
        $this->assertBookableDate($normalizedDate);

        $slotMinutes = max(15, (int) ($room['slot_minutes'] ?? $this->settings()['room_slot_minutes']));
        $open = self::normalizeTime((string) ($room['open_time'] ?? '08:00'));
        $close = self::normalizeTime((string) ($room['close_time'] ?? '20:00'));
        $slots = self::buildSlots($open, $close, $slotMinutes);

        $booked = $this->reservationsForRoomDay($roomId, $normalizedDate);
        $slotStates = [];
        foreach ($slots as $slot) {
            $slotStates[$slot['time']] = 'free';
        }
        foreach ($booked as $reservation) {
            $start = self::timeToMinutes((string) $reservation['start_time']);
            $end = self::timeToMinutes((string) $reservation['end_time']);
            foreach ($slots as $slot) {
                $slotStart = self::timeToMinutes($slot['time']);
                $slotEnd = $slotStart + $slotMinutes;
                if ($slotStart < $end && $slotEnd > $start) {
                    $slotStates[$slot['time']] = ($reservation['status'] ?? '') === 'pending' ? 'pending' : 'busy';
                }
            }
        }

        $result = [];
        foreach ($slots as $slot) {
            $result[] = [
                'time' => $slot['time'],
                'end' => $slot['end'],
                'status' => $slotStates[$slot['time']] ?? 'free',
            ];
        }

        return [
            'date' => $normalizedDate,
            'room' => $room,
            'slots' => $result,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createFromPayload(array $payload, string $source = 'public'): array
    {
        $settings = $this->settings();
        if ($source === 'public' && !$settings['room_public_enabled']) {
            throw new InvalidArgumentException('رزرو عمومی اتاق جلسه غیرفعال است.');
        }

        $roomId = (int) ($payload['room_id'] ?? 0);
        $date = JalaliDate::normalize((string) ($payload['reserved_date'] ?? $payload['date'] ?? ''));
        $startTime = self::normalizeTime((string) ($payload['start_time'] ?? ''));
        $endTime = self::normalizeTime((string) ($payload['end_time'] ?? ''));
        $bookerName = trim((string) ($payload['booker_name'] ?? ''));
        $bookerPhone = self::normalizePhone((string) ($payload['booker_phone'] ?? ''));
        $bookerOrg = trim((string) ($payload['booker_org'] ?? ''));
        $purpose = trim((string) ($payload['purpose'] ?? ''));
        $teamId = (int) ($payload['team_id'] ?? 0);
        $memberId = (int) ($payload['member_id'] ?? 0);

        if ($source === 'public') {
            self::assertPublicRateLimit($bookerPhone);
        }

        if ($bookerName === '') {
            throw new InvalidArgumentException('نام رزروکننده الزامی است.');
        }
        if ($bookerPhone === '') {
            throw new InvalidArgumentException('شماره موبایل معتبر وارد کنید.');
        }

        if ($source === 'team') {
            $scopedTeamId = Access::scopedTeamId();
            if ($scopedTeamId === null || $scopedTeamId <= 0) {
                throw new InvalidArgumentException('نهاد معتبر نیست.');
            }
            $teamId = $scopedTeamId;
            if ($bookerOrg === '') {
                $teamRow = $this->pdo->prepare('SELECT name FROM teams WHERE id = :id');
                $teamRow->execute(['id' => $teamId]);
                $bookerOrg = (string) ($teamRow->fetchColumn() ?: '');
            }
        } elseif ($teamId > 0 && $bookerOrg === '') {
            $teamRow = $this->pdo->prepare('SELECT name FROM teams WHERE id = :id');
            $teamRow->execute(['id' => $teamId]);
            $bookerOrg = (string) ($teamRow->fetchColumn() ?: '');
        }

        $room = $this->roomRow($roomId);
        $this->validateBookingWindow($room, $date, $startTime, $endTime, $bookerPhone);

        $duration = self::timeToMinutes($endTime) - self::timeToMinutes($startTime);
        $status = $settings['room_auto_approve'] ? 'approved' : 'pending';
        $today = JalaliDate::todayParts()['formatted'];
        $token = bin2hex(random_bytes(16));

        $statement = $this->pdo->prepare(
            'INSERT INTO room_reservations (
                room_id, reserved_date, start_time, end_time, duration_minutes,
                team_id, member_id, booker_name, booker_phone, booker_org, purpose,
                status, source, public_token, submitted_at, created_at, updated_at
             ) VALUES (
                :room_id, :reserved_date, :start_time, :end_time, :duration_minutes,
                :team_id, :member_id, :booker_name, :booker_phone, :booker_org, :purpose,
                :status, :source, :public_token, :submitted_at, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'room_id' => $roomId,
            'reserved_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $duration,
            'team_id' => $teamId > 0 ? $teamId : null,
            'member_id' => $memberId > 0 ? $memberId : null,
            'booker_name' => $bookerName,
            'booker_phone' => $bookerPhone,
            'booker_org' => $bookerOrg !== '' ? $bookerOrg : null,
            'purpose' => $purpose !== '' ? $purpose : null,
            'status' => $status,
            'source' => $source,
            'public_token' => $token,
            'submitted_at' => $today,
            'created_at' => $today,
            'updated_at' => $today,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $id, string $reason = '', ?string $publicToken = null): array
    {
        $row = $this->findById($id);
        if ($publicToken !== null && !hash_equals((string) ($row['public_token'] ?? ''), $publicToken)) {
            throw new InvalidArgumentException('کد پیگیری معتبر نیست.');
        }
        if (!in_array((string) ($row['status'] ?? ''), ['pending', 'approved'], true)) {
            throw new InvalidArgumentException('این رزرو قابل لغو نیست.');
        }

        if (Access::isTeam()) {
            $teamId = Access::scopedTeamId();
            if ($teamId === null || (int) ($row['team_id'] ?? 0) !== $teamId) {
                throw new InvalidArgumentException('دسترسی به این رزرو مجاز نیست.');
            }
        }

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE room_reservations
             SET status = 'cancelled', cancel_reason = :reason, updated_at = :updated_at, reviewed_at = :reviewed_at
             WHERE id = :id"
        )->execute([
            'reason' => $reason !== '' ? $reason : null,
            'updated_at' => $today,
            'reviewed_at' => $today,
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(int $id): array
    {
        $row = $this->findById($id);
        if (($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این رزرو در انتظار تأیید نیست.');
        }

        $this->validateBookingWindow(
            $this->roomRow((int) $row['room_id']),
            (string) $row['reserved_date'],
            (string) $row['start_time'],
            (string) $row['end_time'],
            (string) $row['booker_phone'],
            (int) $row['id']
        );

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE room_reservations
             SET status = 'approved', reviewed_at = :reviewed_at, reviewed_by = :reviewed_by, updated_at = :updated_at, rejection_reason = NULL
             WHERE id = :id"
        )->execute([
            'reviewed_at' => $today,
            'reviewed_by' => Access::userId() > 0 ? Access::userId() : null,
            'updated_at' => $today,
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(int $id, string $reason = ''): array
    {
        $row = $this->findById($id);
        if (($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این رزرو در انتظار تأیید نیست.');
        }

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE room_reservations
             SET status = 'rejected', reviewed_at = :reviewed_at, reviewed_by = :reviewed_by,
                 rejection_reason = :reason, updated_at = :updated_at
             WHERE id = :id"
        )->execute([
            'reviewed_at' => $today,
            'reviewed_by' => Access::userId() > 0 ? Access::userId() : null,
            'reason' => $reason !== '' ? $reason : null,
            'updated_at' => $today,
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function findByToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('کد پیگیری معتبر نیست.');
        }

        $statement = $this->pdo->prepare(
            'SELECT rr.*, mr.name AS room_name, mr.code AS room_code
             FROM room_reservations rr
             INNER JOIN meeting_rooms mr ON mr.id = rr.room_id
             WHERE rr.public_token = :token'
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('رزروی با این کد پیگیری یافت نشد.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function findById(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT rr.*, mr.name AS room_name, mr.code AS room_code, mr.capacity AS room_capacity,
                    t.name AS team_name
             FROM room_reservations rr
             INNER JOIN meeting_rooms mr ON mr.id = rr.room_id
             LEFT JOIN teams t ON t.id = rr.team_id
             WHERE rr.id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('رزرو پیدا نشد.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $room
     */
    private function validateBookingWindow(
        array $room,
        string $date,
        string $startTime,
        string $endTime,
        string $bookerPhone,
        int $ignoreReservationId = 0
    ): void {
        if ((int) ($room['is_active'] ?? 0) !== 1) {
            throw new InvalidArgumentException('اتاق فعال نیست.');
        }

        $this->assertBookableDate($date);

        $open = self::normalizeTime((string) ($room['open_time'] ?? '08:00'));
        $close = self::normalizeTime((string) ($room['close_time'] ?? '20:00'));
        $slotMinutes = max(15, (int) ($room['slot_minutes'] ?? $this->settings()['room_slot_minutes']));
        $settings = $this->settings();

        $startMin = self::timeToMinutes($startTime);
        $endMin = self::timeToMinutes($endTime);
        $openMin = self::timeToMinutes($open);
        $closeMin = self::timeToMinutes($close);

        if ($startMin >= $endMin) {
            throw new InvalidArgumentException('بازه زمانی معتبر نیست.');
        }
        if ($startMin < $openMin || $endMin > $closeMin) {
            throw new InvalidArgumentException('بازه خارج از ساعات کاری اتاق است.');
        }

        $duration = $endMin - $startMin;
        if ($duration > $settings['room_max_hours_per_day'] * 60) {
            throw new InvalidArgumentException('حداکثر ' . $settings['room_max_hours_per_day'] . ' ساعت در هر رزرو مجاز است.');
        }
        if ($duration % $slotMinutes !== 0) {
            throw new InvalidArgumentException('بازه باید مضرب ' . $slotMinutes . ' دقیقه باشد.');
        }

        $this->assertDailyCap($bookerPhone, $date, $duration, $ignoreReservationId);
        $this->assertNoOverlap((int) $room['id'], $date, $startTime, $endTime, $ignoreReservationId);
    }

    private function assertBookableDate(string $date): void
    {
        $settings = $this->settings();
        $today = JalaliDate::todayParts()['formatted'];
        if (JalaliDate::compare($date, $today) < 0) {
            throw new InvalidArgumentException('رزرو برای تاریخ گذشته مجاز نیست.');
        }

        $maxDate = JalaliDate::addDays($today, $settings['room_max_advance_days']);
        if (JalaliDate::compare($date, $maxDate) > 0) {
            throw new InvalidArgumentException('حداکثر ' . $settings['room_max_advance_days'] . ' روز جلو می‌توانید رزرو کنید.');
        }
    }

    private function assertDailyCap(string $phone, string $date, int $newDuration, int $ignoreId = 0): void
    {
        $settings = $this->settings();
        $maxMinutes = $settings['room_max_hours_per_day'] * 60;
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
        $sql = "SELECT COALESCE(SUM(duration_minutes), 0)
                FROM room_reservations
                WHERE booker_phone = ? AND reserved_date = ? AND status IN ({$placeholders})";
        if ($ignoreId > 0) {
            $sql .= ' AND id <> ?';
        }
        $statement = $this->pdo->prepare($sql);
        $params = array_merge([$phone, $date], self::ACTIVE_STATUSES);
        if ($ignoreId > 0) {
            $params[] = $ignoreId;
        }
        $statement->execute($params);
        $existing = (int) $statement->fetchColumn();
        if ($existing + $newDuration > $maxMinutes) {
            throw new InvalidArgumentException('سقف ' . $settings['room_max_hours_per_day'] . ' ساعت رزرو در این روز تکمیل شده است.');
        }
    }

    private function assertNoOverlap(int $roomId, string $date, string $start, string $end, int $ignoreId = 0): void
    {
        $booked = $this->reservationsForRoomDay($roomId, $date, $ignoreId);
        $startMin = self::timeToMinutes($start);
        $endMin = self::timeToMinutes($end);
        foreach ($booked as $row) {
            $otherStart = self::timeToMinutes((string) $row['start_time']);
            $otherEnd = self::timeToMinutes((string) $row['end_time']);
            if ($startMin < $otherEnd && $endMin > $otherStart) {
                throw new InvalidArgumentException('این بازه با رزرو دیگری تداخل دارد.');
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservationsForRoomDay(int $roomId, string $date, int $ignoreId = 0): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
        $sql = "SELECT id, start_time, end_time, status
                FROM room_reservations
                WHERE room_id = ? AND reserved_date = ? AND status IN ({$placeholders})";
        if ($ignoreId > 0) {
            $sql .= ' AND id <> ?';
        }
        $statement = $this->pdo->prepare($sql);
        $params = array_merge([$roomId, $date], self::ACTIVE_STATUSES);
        if ($ignoreId > 0) {
            $params[] = $ignoreId;
        }
        $statement->execute($params);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function roomRow(int $roomId): array
    {
        if ($roomId <= 0) {
            throw new InvalidArgumentException('اتاق معتبر انتخاب کنید.');
        }
        $statement = $this->pdo->prepare('SELECT * FROM meeting_rooms WHERE id = :id');
        $statement->execute(['id' => $roomId]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('اتاق پیدا نشد.');
        }

        return $row;
    }

    private function ensureSettingsColumns(): void
    {
        if (!Schema::tableExists($this->pdo, 'center_settings')) {
            return;
        }
        Schema::migrate($this->pdo);
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', JalaliDate::normalizeDigits($phone)) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (preg_match('/^09\d{9}$/', $digits) !== 1) {
            return '';
        }

        return $digits;
    }

    public static function normalizeTime(string $time): string
    {
        $time = trim(JalaliDate::normalizeDigits($time));
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            throw new InvalidArgumentException('ساعت معتبر نیست.');
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('ساعت معتبر نیست.');
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    public static function timeToMinutes(string $time): int
    {
        $normalized = self::normalizeTime($time);
        [$hour, $minute] = array_map('intval', explode(':', $normalized));

        return ($hour * 60) + $minute;
    }

    /**
     * @return list<array{time: string, end: string}>
     */
    public static function buildSlots(string $open, string $close, int $slotMinutes): array
    {
        $slots = [];
        $cursor = self::timeToMinutes($open);
        $end = self::timeToMinutes($close);
        while ($cursor + $slotMinutes <= $end) {
            $slots[] = [
                'time' => self::minutesToTime($cursor),
                'end' => self::minutesToTime($cursor + $slotMinutes),
            ];
            $cursor += $slotMinutes;
        }

        return $slots;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSettings(array $payload): array
    {
        $this->ensureSettingsColumns();
        $current = $this->settings();
        $data = [
            'room_auto_approve' => array_key_exists('room_auto_approve', $payload)
                ? ((int) $payload['room_auto_approve'] === 1 ? 1 : 0)
                : ($current['room_auto_approve'] ? 1 : 0),
            'room_max_advance_days' => array_key_exists('room_max_advance_days', $payload)
                ? max(1, (int) $payload['room_max_advance_days'])
                : $current['room_max_advance_days'],
            'room_max_hours_per_day' => array_key_exists('room_max_hours_per_day', $payload)
                ? max(1, (int) $payload['room_max_hours_per_day'])
                : $current['room_max_hours_per_day'],
            'room_slot_minutes' => array_key_exists('room_slot_minutes', $payload)
                ? (in_array((int) $payload['room_slot_minutes'], [30, 60], true) ? (int) $payload['room_slot_minutes'] : 60)
                : $current['room_slot_minutes'],
            'room_public_enabled' => array_key_exists('room_public_enabled', $payload)
                ? ((int) $payload['room_public_enabled'] === 1 ? 1 : 0)
                : ($current['room_public_enabled'] ? 1 : 0),
        ];

        $this->pdo->prepare(
            'UPDATE center_settings SET
                room_auto_approve = :room_auto_approve,
                room_max_advance_days = :room_max_advance_days,
                room_max_hours_per_day = :room_max_hours_per_day,
                room_slot_minutes = :room_slot_minutes,
                room_public_enabled = :room_public_enabled
             WHERE id = 1'
        )->execute($data);

        return $this->settings();
    }

    private static function minutesToTime(int $minutes): string
    {
        $hour = intdiv($minutes, 60);
        $minute = $minutes % 60;

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function assertPublicRateLimit(string $phone): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = hash('sha256', 'room-book|' . $ip . '|' . $phone);
        $dir = app_base_path() . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/room_book_throttle.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $now = time();
        $attempts = array_values(array_filter(
            (array) ($data[$key]['attempts'] ?? []),
            static fn (int $ts): bool => ($now - $ts) < 900
        ));
        if (count($attempts) >= 8) {
            throw new InvalidArgumentException('تعداد درخواست زیاد است. چند دقیقه بعد دوباره تلاش کنید.');
        }
        $attempts[] = $now;
        $data[$key] = ['attempts' => $attempts];
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
