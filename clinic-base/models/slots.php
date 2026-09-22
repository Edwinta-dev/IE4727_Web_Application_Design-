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
function slots_for_day(int $doctorId, string $date): array
{
    return q_all(
        slot_select() . "
            WHERE `DoctorID` = :doctor_id
              AND DATE(`SlotDateTime`) = :slot_date
            ORDER BY `SlotDateTime`",
        ['doctor_id' => $doctorId, 'slot_date' => $date]
    );
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
              AND `Status` = 'Available'
            ORDER BY `SlotDateTime`
            LIMIT :slot_limit",
        ['doctor_id' => $doctorId, 'slot_limit' => $limit]
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
