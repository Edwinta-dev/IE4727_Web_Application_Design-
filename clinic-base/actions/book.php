<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'booking.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

require_login();
csrf_check();

$returnUrl = static function (): string {
    $params = [];
    foreach (['doctor', 'date', 'from', 'to'] as $key) {
        if (isset($_POST[$key]) && is_string($_POST[$key]) && $_POST[$key] !== '') {
            $params[$key] = $_POST[$key];
        }
    }

    return '/book.php' . ($params === [] ? '' : '?' . http_build_query($params));
};

if (!is_patient()) {
    flash('Please sign in as a patient to book an appointment.', 'error');
    redirect('/index.php?next=' . rawurlencode($returnUrl()));
}

$slotId = isset($_POST['slot_id']) && is_string($_POST['slot_id']) && ctype_digit($_POST['slot_id'])
    ? (int) $_POST['slot_id']
    : 0;
$reason = isset($_POST['reason']) && is_string($_POST['reason']) ? trim($_POST['reason']) : '';
$slot = $slotId > 0 ? find_slot($slotId) : null;

if ($slot === null || $reason === '') {
    flash('Choose a valid slot and enter a reason for the visit.', 'error');
    redirect($returnUrl());
}

$user = current_user();
$booking = book_appointment((int) $user['id'], $slotId, $reason);
if ($booking['ok']) {
    flash('Your appointment has been booked successfully.', 'success');
} else {
    flash((string) ($booking['error'] ?? 'That slot is no longer available. Please choose another.'), 'error');
}

$params = [
    'doctor' => (string) $slot['DoctorID'],
    'date' => substr((string) $slot['SlotDateTime'], 0, 10),
];
foreach (['from', 'to'] as $key) {
    if (isset($_POST[$key]) && is_string($_POST[$key]) && $_POST[$key] !== '') {
        $params[$key] = $_POST[$key];
    }
}
redirect('/book.php?' . http_build_query($params));
