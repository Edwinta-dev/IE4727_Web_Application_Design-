<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'booking.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

require_login();
csrf_check();

$home = is_doctor() ? '/doctor/home.php' : '/patient/home.php';
if (!is_patient() && !is_doctor()) {
    flash('Please sign in as a patient or doctor to manage appointments.', 'error');
    redirect('/index.php?next=' . rawurlencode($home));
}

$user = current_user();
$appointmentInput = $_POST['appointment_id'] ?? '';
$appointmentId = is_string($appointmentInput) && ctype_digit($appointmentInput)
    ? (int) $appointmentInput
    : 0;
$appointment = $appointmentId > 0 ? find_appointment($appointmentId) : null;

// Ownership is checked here even when the controls were rendered for a page
// belonging to the current patient; posted ids are never trusted.
if ($appointment === null
    || (is_patient() && (int) $appointment['PatientID'] !== (int) $user['id'])
    || (is_doctor() && (int) $appointment['DoctorID'] !== (int) $user['id'])) {
    flash('That appointment does not belong to you.', 'error');
    redirect($home);
}

if (is_doctor() && isset($_POST['status'])) {
    $status = is_string($_POST['status']) ? $_POST['status'] : '';
    if (!in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true)
        || !in_array($status, ['Completed', 'No show'], true)) {
        flash('Only a future appointment can be marked for attendance.', 'error');
    } else {
        $updated = set_appointment_status($appointmentId, $status, (int) $user['id']);
        flash($updated ? 'Appointment marked as ' . $status . '.' : 'Attendance can only be recorded from the appointment start time by its doctor.', $updated ? 'success' : 'error');
    }
    redirect($home . '?date=' . rawurlencode(substr((string) $appointment['appointmentDateTime'], 0, 10)));
}

if (is_patient() && isset($_POST['cancel'])) {
    if (!in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true)
        || strtotime((string) $appointment['appointmentDateTime']) <= time()) {
        flash('Only a future appointment can be cancelled.', 'error');
        redirect($home);
    }

    $cancelled = cancel_appointment($appointmentId, 'patient');
    flash(
        $cancelled ? 'Your appointment has been cancelled.' : 'The appointment could not be cancelled.',
        $cancelled ? 'success' : 'error'
    );
    redirect($home);
}

$slotInput = $_POST['slot_id'] ?? '';
$slotId = is_string($slotInput) && ctype_digit($slotInput) ? (int) $slotInput : 0;
$slot = $slotId > 0 ? find_slot($slotId) : null;
if (!isset($_POST['reschedule']) || $slot === null
    || !in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true)
    || strtotime((string) $appointment['appointmentDateTime']) <= time()
    || booking_date_error(substr((string) $slot['SlotDateTime'], 0, 10), new DateTimeImmutable('today'), BROWSE_DAYS) !== null) {
    flash('Choose a valid future slot to reschedule this appointment.', 'error');
    redirect('/book.php?reschedule=' . $appointmentId . '&doctor=' . (int) $appointment['DoctorID']);
}

$result = reschedule_appointment($appointmentId, $slotId, is_doctor() ? 'doctor' : 'patient');
flash(
    $result['ok'] ? 'Your appointment has been rescheduled.' : (string) ($result['error'] ?? 'The appointment could not be rescheduled.'),
    $result['ok'] ? 'success' : 'error'
);
redirect($home);
