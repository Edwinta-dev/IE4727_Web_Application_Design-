<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'slots.php';

/**
 * Return the columns used by the schedule views, including derived date/time
 * values while keeping SlotDateTime as the persisted value.
 */
function slot_select(): string
{
    return 'SELECT `slotID`, `DoctorID`, `SlotDateTime`, `CreatedAt`, `Status`,
                   DATE(`SlotDateTime`) AS `SlotDate`, TIME(`SlotDateTime`) AS `SlotTime`
            FROM `slots`';
}

/**
 * Materialise a doctor's generated schedule without deleting existing slots.
 */
function regenerate_schedule(int $doctorId, string $from, int $days, array $opts = []): int
{
    $created = 0;

    foreach (generate_slots($from, $days, $opts) as $slot) {
        $statement = q(
            'INSERT IGNORE INTO `slots` (`DoctorID`, `SlotDateTime`)
             VALUES (:doctor_id, :slot_date_time)',
            [
                'doctor_id' => $doctorId,
                'slot_date_time' => $slot['slot_date'] . ' ' . $slot['start_time'] . ':00',
            ]
        );
        $created += $statement->rowCount();
    }

    return $created;
}

/** @return list<array<string, mixed>> */
function free_slots(int $doctorId, string $date): array
{
    return q_all(
        slot_select() . "
            WHERE `DoctorID` = :doctor_id
              AND DATE(`SlotDateTime`) = :slot_date
              AND `Status` = 'Available'
            ORDER BY `SlotDateTime`",
        ['doctor_id' => $doctorId, 'slot_date' => $date]
    );
}

/** @return list<array<string, mixed>> */
function slots_for_day(int $doctorId, string $date, ?string $fromTime = null, ?string $toTime = null): array
{
    $sql = slot_select() . "
            WHERE `DoctorID` = :doctor_id
              AND DATE(`SlotDateTime`) = :slot_date";
    $params = ['doctor_id' => $doctorId, 'slot_date' => $date];

    if ($fromTime !== null) {
        $sql .= ' AND TIME(`SlotDateTime`) >= :from_time';
        $params['from_time'] = $fromTime;
    }
    if ($toTime !== null) {
        $sql .= ' AND TIME(`SlotDateTime`) <= :to_time';
        $params['to_time'] = $toTime;
    }

    return q_all($sql . " ORDER BY `SlotDateTime`", $params);
}

/**
 * Read-only availability for the existing patient window and time filters.
 * Use the same captured now as the grid, including the strict future boundary.
 * @return array<string, array{total: int, matching: int, future: int, available: int}>
 */
function booking_day_availability(int $doctorId, DateTimeImmutable $now, int $days, string $fromTime, string $toTime): array
{
    $today = $now->setTime(0, 0);
    $availability = [];
    for ($offset = 0; $offset < $days; $offset++) {
        $availability[$today->modify('+' . $offset . ' days')->format('Y-m-d')] =
            ['total' => 0, 'matching' => 0, 'future' => 0, 'available' => 0];
    }
    $rows = q_all(
        slot_select() . ' WHERE `DoctorID` = :doctor_id
            AND `SlotDateTime` >= :start AND `SlotDateTime` < :end ORDER BY `SlotDateTime`',
        ['doctor_id' => $doctorId, 'start' => $today->format('Y-m-d H:i:s'),
            'end' => $today->modify('+' . $days . ' days')->format('Y-m-d H:i:s')]
    );
    foreach ($rows as $slot) {
        $counts = &$availability[(string) $slot['SlotDate']];
        $counts['total']++;
        $time = (string) $slot['SlotTime'];
        if ($time >= $fromTime . ':00' && $time <= $toTime . ':00') {
            $counts['matching']++;
            if (new DateTimeImmutable((string) $slot['SlotDateTime']) > $now) {
                $counts['future']++;
                if ($slot['Status'] === 'Available') {
                    $counts['available']++;
                }
            }
        }
        unset($counts);
    }

    return $availability;
}

/**
 * Return state counts for each day in a doctor's requested schedule window.
 * The page fills in dates with no rows so an empty day is still visible.
 *
 * @return array<string, array{Available: int, Booked: int, Blocked: int}>
 */
function slot_counts_for_range(int $doctorId, string $from, string $to): array
{
    $rows = q_all(
        'SELECT DATE(`SlotDateTime`) AS `SlotDate`, `Status`, COUNT(*) AS `Count`
         FROM `slots`
         WHERE `DoctorID` = :doctor_id
           AND DATE(`SlotDateTime`) BETWEEN :from_date AND :to_date
         GROUP BY DATE(`SlotDateTime`), `Status`
         ORDER BY `SlotDate`',
        ['doctor_id' => $doctorId, 'from_date' => $from, 'to_date' => $to]
    );

    $counts = [];
    foreach ($rows as $row) {
        $date = (string) $row['SlotDate'];
        $status = (string) $row['Status'];
        if (!isset($counts[$date])) {
            $counts[$date] = ['Available' => 0, 'Booked' => 0, 'Blocked' => 0];
        }
        if (array_key_exists($status, $counts[$date])) {
            $counts[$date][$status] = (int) $row['Count'];
        }
    }

    return $counts;
}

/** Allow a doctor to revisit owned future rows generated before the management limit. */
function has_owned_slots_on_day(int $doctorId, string $date): bool
{
    return (int) q_val(
        'SELECT COUNT(*) FROM `slots` WHERE `DoctorID` = :doctor_id AND DATE(`SlotDateTime`) = :slot_date',
        ['doctor_id' => $doctorId, 'slot_date' => $date]
    ) > 0;
}

/** @return list<array<string, mixed>> */
function next_available(int $doctorId, int $limit = 3): array
{
    if ($limit <= 0) {
        return [];
    }

    return q_all(
        slot_select() . "
            WHERE `DoctorID` = :doctor_id
              AND `SlotDateTime` >= NOW()
              AND DATE(`SlotDateTime`) <= :last_date
              AND `Status` = 'Available'
            ORDER BY `SlotDateTime`
            LIMIT :slot_limit",
        ['doctor_id' => $doctorId, 'last_date' => (new DateTimeImmutable('today'))->modify('+' . (BROWSE_DAYS - 1) . ' days')->format('Y-m-d'), 'slot_limit' => $limit]
    );
}

/** @return list<array<string, mixed>> */
function free_slots_across(
    string $date,
    ?int $doctorId = null,
    ?string $fromTime = null,
    ?string $toTime = null
): array {
    $start = new DateTimeImmutable($date . ' 00:00:00');
    $end = $start->modify('+7 days')->format('Y-m-d');

    $where = [
        "DATE(`SlotDateTime`) >= ?",
        "DATE(`SlotDateTime`) < ?",
        "`Status` = 'Available'",
    ];
    $params = [$start->format('Y-m-d'), $end];

    if ($doctorId !== null) {
        $where[] = '`DoctorID` = ?';
        $params[] = $doctorId;
    }

    if ($fromTime !== null && $toTime !== null) {
        $where[] = 'TIME(`SlotDateTime`) BETWEEN ? AND ?';
        $params[] = $fromTime;
        $params[] = $toTime;
    }

    return q_all(
        slot_select() . "\n            WHERE " . implode("\n              AND ", $where) . "\n            ORDER BY `SlotDateTime`",
        $params
    );
}

function set_slot_status(int $slotId, string $status): void
{
    if (!in_array($status, ['Available', 'Blocked', 'Booked'], true)) {
        throw new InvalidArgumentException('Invalid slot status.');
    }

    q(
        'UPDATE `slots` SET `Status` = :status WHERE `slotID` = :slot_id',
        ['status' => $status, 'slot_id' => $slotId]
    );
}

/** @return array<string, mixed>|null */
function find_slot(int $slotId): ?array
{
    return q_one(
        slot_select() . "
            WHERE `slotID` = :slot_id
            LIMIT 1",
        ['slot_id' => $slotId]
    );
}
