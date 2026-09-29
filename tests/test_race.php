<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patients = q_all(
    'SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 2'
);
if ($doctor === null || count($patients) !== 2) {
    throw new RuntimeException('seed did not provide a doctor and two patients');
}

$slotDateTime = '';
for ($attempt = 0; $attempt < 10; $attempt++) {
    $candidate = (new DateTimeImmutable('today'))
        ->modify('+' . random_int(30, 365) . ' days')
        ->setTime(random_int(8, 17), random_int(0, 59), 0)
        ->format('Y-m-d H:i:s');
    $exists = q_val(
        'SELECT COUNT(*) FROM `slots`
         WHERE `DoctorID` = :doctor_id AND `SlotDateTime` = :slot_date_time',
        [
            'doctor_id' => (int) $doctor['DoctorID'],
            'slot_date_time' => $candidate,
        ]
    );
    if ((int) $exists === 0) {
        $slotDateTime = $candidate;
        break;
    }
}
if ($slotDateTime === '') {
    throw new RuntimeException('could not find an unused future slot timestamp');
}

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`)
     VALUES (:doctor_id, :slot_date_time, \'Available\')',
    [
        'doctor_id' => (int) $doctor['DoctorID'],
        'slot_date_time' => $slotDateTime,
    ]
);
$slotId = (int) db()->lastInsertId();

// Both callers make their availability decision before either booking writes.
$firstRead = q_val(
    'SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id',
    ['slot_id' => $slotId]
);
$secondRead = q_val(
    'SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id',
    ['slot_id' => $slotId]
);
assert_eq($firstRead, 'Available', 'first caller sees an available slot');
assert_eq($secondRead, 'Available', 'second caller sees an available slot');

$first = book_appointment(
    (int) $patients[0]['PatientID'],
    $slotId,
    'Race test caller one'
);
$second = book_appointment(
    (int) $patients[1]['PatientID'],
    $slotId,
    'Race test caller two'
);

$successfulBookings = (int) $first['ok'] + (int) $second['ok'];
assert_eq($successfulBookings, 1, 'exactly one booking succeeds');

$loser = $first['ok'] ? $second : $first;
assert_eq($loser['error'], 'That slot has just been taken. Please choose another.', 'loser message');
assert_eq(
    (int) q_val(
        'SELECT COUNT(*) FROM `appointment` WHERE `slotID` = :slot_id',
        ['slot_id' => $slotId]
    ),
    1,
    'exactly one appointment is linked to the slot'
);

echo "Race simulation: both callers read Available; one booking succeeded, "
    . "the other received the friendly taken-slot message, and one appointment row exists.\n";
