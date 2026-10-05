<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/clinic-base/';
$directory = file_get_contents($root . 'doctors.php');
$booking = file_get_contents($root . 'book.php');
$action = file_get_contents($root . 'actions/appointment.php');
assert_contains($directory, 'book.php?doctor=');
assert_true(!str_contains($directory, 'book.php?doctor_id='), 'directory emits canonical booking parameter');
assert_contains($directory, '&date=');
assert_contains($directory, 'No times in the next seven days');
assert_contains($directory, '$bookingWindowEnd');
assert_true(!str_contains($booking, 'name="actor"'), 'reschedule form does not submit an actor');
assert_true(!str_contains($action, "\$_POST['actor']"), 'actor cannot come from POST');
assert_contains($action, "reschedule_appointment(\$appointmentId, \$slotId, is_doctor() ? 'doctor' : 'patient')");
assert_contains($action, '(int) $appointment[\'PatientID\'] !== (int) $user[\'id\']');
assert_contains($action, '(int) $appointment[\'DoctorID\'] !== (int) $user[\'id\']');
echo "OK: #144 canonical booking links, availability explanation and session-derived actor\n";
