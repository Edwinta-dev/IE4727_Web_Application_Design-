<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';
require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';
require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';
require_once dirname(__DIR__) . '/clinic-base/models/slots.php';

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($doctor === null || $patient === null) {
    throw new RuntimeException('seed did not provide a doctor and patient');
}

$doctorId = (int) $doctor['DoctorID'];
$patientId = (int) $patient['PatientID'];
$free = [];
$date = '';
for ($offset = 1; $offset <= 30 && $free === []; $offset++) {
    $date = (new DateTimeImmutable('today'))->modify('+' . $offset . ' days')->format('Y-m-d');
    $free = free_slots($doctorId, $date);
}

if ($free === []) {
    throw new RuntimeException('no free seeded slot found');
}

$slot = $free[0];
$booking = book_appointment($patientId, (int) $slot['slotID'], 'Booked from the CLI demo.');
if (!$booking['ok'] || $booking['appointment_id'] === null) {
    throw new RuntimeException('CLI booking failed: ' . (string) $booking['error']);
}
$appointmentId = $booking['appointment_id'];

$upcoming = appointments_for_patient($patientId, 'upcoming');
$appointment = null;
foreach ($upcoming as $candidate) {
    if ((int) $candidate['appointmentID'] === $appointmentId) {
        $appointment = $candidate;
        break;
    }
}

if ($appointment === null || $appointment['Status'] !== 'Future') {
    throw new RuntimeException('booked appointment was not returned as Future');
}

$bookedSlot = find_slot((int) $slot['slotID']);
if ($bookedSlot === null || $bookedSlot['Status'] !== 'Booked') {
    throw new RuntimeException('booked slot did not change to Booked');
}

$notifications = notifications_for_appointment($appointmentId);
if ($notifications === []) {
    throw new RuntimeException('booking did not write a notification row');
}

echo "PASS: CLI booking end-to-end checks\n";
