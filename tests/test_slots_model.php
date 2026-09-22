<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/slots.php';

$dateObject = new DateTimeImmutable('2099-01-01');
do {
    $date = $dateObject->modify('+' . random_int(0, 1000) . ' days')->format('Y-m-d');
} while ((int) (new DateTimeImmutable($date))->format('w') === 0);
$options = ['start' => '09:00', 'end' => '10:30', 'minutes' => 30, 'breaks' => []];

$created = regenerate_schedule(1, $date, 1, $options);
if ($created !== 3) {
    throw new RuntimeException('schedule generation created an unexpected number of slots');
}

if (regenerate_schedule(1, $date, 1, $options) !== 0) {
    throw new RuntimeException('schedule regeneration was not idempotent');
}

$day = slots_for_day(1, $date);
if (count($day) !== 3) {
    throw new RuntimeException('day query did not return every slot');
}

set_slot_status((int) $day[1]['slotID'], 'Booked');
set_slot_status((int) $day[2]['slotID'], 'Blocked');

$free = free_slots(1, $date);
if (count($free) !== 1 || $free[0]['Status'] !== 'Available') {
    throw new RuntimeException('free slot query included a non-available slot');
}

$allStates = slots_for_day(1, $date);
$states = array_column($allStates, 'Status');
sort($states);
if ($states !== ['Available', 'Blocked', 'Booked']) {
    throw new RuntimeException('day query did not preserve all slot states');
}

$ranged = free_slots_across($date, 1, '09:30', '10:00');
if (count($ranged) !== 0) {
    throw new RuntimeException('time range query ignored slot status');
}

set_slot_status((int) $day[1]['slotID'], 'Available');
set_slot_status((int) $day[2]['slotID'], 'Available');
$ranged = free_slots_across($date, 1, '09:30', '10:00');
if (count($ranged) !== 2 || $ranged[0]['SlotTime'] !== '09:30:00' || $ranged[1]['SlotTime'] !== '10:00:00') {
    throw new RuntimeException('time range query did not use TIME(SlotDateTime)');
}

echo "PASS: slots model checks\n";
