<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--worker') {
    require_once dirname(__DIR__) . '/clinic-base/models/booking.php';
    [, , $kind, $appointmentId, $oldSlotId, $targetId, $doctorId, $patientId] = $argv;
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    if ($kind === 'reschedule') {
        $result = reschedule_appointment((int) $appointmentId, (int) $targetId, 'patient', (int) $patientId, (int) $oldSlotId);
        echo $result['ok'] ? "1\n" : "0\n";
    } elseif ($kind === 'cancel') {
        echo cancel_appointment((int) $appointmentId, 'patient', (int) $patientId, (int) $oldSlotId) ? "1\n" : "0\n";
    } else {
        echo block_slot((int) $oldSlotId, (int) $doctorId, true) ? "1\n" : "0\n";
    }
    exit(0);
}

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($doctor === null || $patient === null) throw new RuntimeException('missing seeded concurrency actors');
$doctorId = (int) $doctor['DoctorID'];
$patientId = (int) $patient['PatientID'];

function concurrency_slot(int $doctorId, int $offset): int
{
    $when = (new DateTimeImmutable('today'))->modify('+' . $offset . ' days')->setTime(13, $offset % 60)->format('Y-m-d H:i:s');
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :when, \'Available\')', ['doctor_id' => $doctorId, 'when' => $when]);
    return (int) db()->lastInsertId();
}

function concurrency_race(string $left, string $right, int $doctorId, int $patientId): array
{
    $old = concurrency_slot($doctorId, random_int(40, 200));
    $booking = book_appointment($patientId, $old, 'Concurrent mutation test');
    if (!$booking['ok']) throw new RuntimeException('could not create race appointment');
    $appointmentId = (int) $booking['appointment_id'];
    $targets = [concurrency_slot($doctorId, random_int(210, 300)), concurrency_slot($doctorId, random_int(310, 400))];
    $specs = [[$left, $targets[0]], [$right, $targets[1]]];
    $workers = [];
    foreach ($specs as [$kind, $target]) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
            . ' --worker ' . escapeshellarg($kind) . ' ' . $appointmentId . ' ' . $old . ' ' . $target . ' ' . $doctorId . ' ' . $patientId;
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) throw new RuntimeException('could not start concurrent worker');
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [, $pipes]) {
        assert_eq(trim((string) fgets($pipes[1])), 'READY', 'worker reached shared start barrier');
    }
    foreach ($workers as [, $pipes]) fwrite($pipes[0], "go\n");
    $successes = 0;
    foreach ($workers as [$process, $pipes]) {
        $successes += (int) trim((string) stream_get_contents($pipes[1]));
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('race worker failed: ' . $stderr);
    }
    $rows = q_all(
        "SELECT s.`slotID`, s.`Status`, COUNT(a.`appointmentID`) AS `activeCount`
         FROM `slots` s LEFT JOIN `appointment` a ON a.`slotID` = s.`slotID`
           AND a.`Status` IN ('Future', 'Rescheduled')
         WHERE s.`slotID` IN (:old, :first, :second)
         GROUP BY s.`slotID`, s.`Status`",
        ['old' => $old, 'first' => $targets[0], 'second' => $targets[1]]
    );
    foreach ($rows as $row) {
        if ($row['Status'] === 'Booked') assert_eq((int) $row['activeCount'], 1, 'booked slot has one active appointment');
    }
    $active = q_one("SELECT `slotID`, `Status` FROM `appointment` WHERE `appointmentID` = :id", ['id' => $appointmentId]);
    if (in_array($active['Status'], ['Future', 'Rescheduled'], true)) {
        assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => (int) $active['slotID']]), 'Booked', 'active appointment points to booked slot');
    }
    q('DELETE FROM `notifications` WHERE `appointmentID` = :id', ['id' => $appointmentId]);
    q('DELETE FROM `appointment` WHERE `appointmentID` = :id', ['id' => $appointmentId]);
    q('DELETE FROM `slots` WHERE `slotID` IN (:old, :first, :second)',
        ['old' => $old, 'first' => $targets[0], 'second' => $targets[1]]);
    return [$successes, $appointmentId, $old, ...$targets];
}

[$rr] = concurrency_race('reschedule', 'reschedule', $doctorId, $patientId);
assert_eq($rr, 1, 'only one competing reschedule succeeds');
[$cr] = concurrency_race('cancel', 'reschedule', $doctorId, $patientId);
assert_eq($cr, 1, 'only one competing cancel and reschedule succeeds');
[$br] = concurrency_race('block', 'reschedule', $doctorId, $patientId);
assert_eq($br, 1, 'only one competing block and reschedule succeeds');

echo "PASS: concurrent reschedule, cancel and block locking\n";
