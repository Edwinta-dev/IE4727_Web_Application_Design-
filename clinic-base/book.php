<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

$rescheduleRequested = array_key_exists('reschedule', $_GET);
$rescheduleIdInput = $_GET['reschedule'] ?? '';
$rescheduleId = is_string($rescheduleIdInput) && ctype_digit($rescheduleIdInput)
    ? (int) $rescheduleIdInput
    : 0;
$reschedule = null;
$rescheduleActor = 'patient';
if ($rescheduleRequested) {
    require_login();
    $user = current_user();
    $candidate = $rescheduleId > 0 && (is_patient() || is_doctor()) ? find_appointment($rescheduleId) : null;
    $ownsAppointment = is_patient()
        ? ($candidate !== null && (int) $candidate['PatientID'] === (int) $user['id'])
        : ($candidate !== null && (int) $candidate['DoctorID'] === (int) $user['id']);
    if ($ownsAppointment
        && in_array((string) $candidate['Status'], ['Future', 'Rescheduled'], true)
        && strtotime((string) $candidate['appointmentDateTime']) > time()) {
        $reschedule = $candidate;
        $rescheduleActor = is_doctor() ? 'doctor' : 'patient';
    } else {
        flash('That appointment cannot be rescheduled.', 'error');
        redirect(is_doctor() ? '/doctor/home.php' : '/patient/home.php');
    }
}

$now = new DateTimeImmutable();
$today = $now->setTime(0, 0);
$lastBrowseDate = $today->modify('+' . (BROWSE_DAYS - 1) . ' days');

$doctorInput = $_GET['doctor'] ?? $_GET['doctor_id'] ?? '';
$doctorId = is_string($doctorInput) && ctype_digit($doctorInput) ? (int) $doctorInput : 0;
$doctor = $doctorId > 0 ? find_doctor($doctorId) : null;
if ($reschedule !== null) {
    $doctorId = (int) $reschedule['DoctorID'];
    $doctor = find_doctor($doctorId);
}
if ($doctor === null) {
    if ($doctorInput !== '') {
        render_not_found('The selected doctor could not be found.');
    }
    $doctorId = 0;
}

$dateRequested = array_key_exists('date', $_GET);
$dateInput = $_GET['date'] ?? null;
$selectedDate = $today->format('Y-m-d');
$dateError = $dateRequested ? booking_date_error($dateInput, $today, BROWSE_DAYS) : null;
if ($dateRequested && $dateError === null) {
    $selectedDate = $dateInput;
}

$fromTime = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '00:00';
$toTime = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : '23:59';
$validTime = static function (string $time): bool {
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);

    return $parsed !== false && $parsed->format('H:i') === $time;
};
if (!$validTime($fromTime) || !$validTime($toTime) || $fromTime > $toTime) {
    $fromTime = '00:00';
    $toTime = '23:59';
}

$doctors = all_doctors();
$availability = $doctorId > 0
    ? booking_day_availability($doctorId, $now, BROWSE_DAYS, $fromTime, $toTime)
    : [];
$availableDays = array_keys(array_filter($availability, static fn (array $counts): bool => $counts['available'] > 0));
if ($reschedule !== null && !$dateRequested) {
    $selectedDate = reschedule_landing_date(
        substr((string) $reschedule['appointmentDateTime'], 0, 10), $availability, $selectedDate
    );
}
$dayEmptyMessage = $doctorId > 0 ? booking_day_empty_message($availability[$selectedDate]) : null;
$availableDayUrl = $availableDays !== [] ? url('/book.php?' . http_build_query(array_merge(
    ['doctor' => $doctorId, 'date' => $availableDays[0], 'from' => $fromTime, 'to' => $toTime],
    $reschedule !== null ? ['reschedule' => $rescheduleId] : []
))) : null;
$slots = $doctorId > 0
    ? slots_for_day($doctorId, $selectedDate, $fromTime, $toTime)
    : [];

$pageTitle = $reschedule !== null ? 'Reschedule an Appointment' : 'Book an Appointment';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="page-intro booking-intro">
        <div class="page-intro-copy">
            <h1><?= e($reschedule !== null ? 'Reschedule an appointment' : 'Book an appointment') ?></h1>
<?php if ($reschedule !== null): ?>
            <p class="reschedule-context">Current appointment: <?= e((string) $reschedule['DoctorName']) ?> on <?= e(fmt_date((string) $reschedule['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $reschedule['appointmentDateTime'])) ?>.</p>
            <p>Your current booking stays in place until the replacement is confirmed. <a href="<?= e(url($rescheduleActor === 'doctor' ? '/doctor/home.php' : '/patient/home.php')) ?>">Back to appointments</a></p>
<?php else: ?>
            <p>Choose a clinician and a time in the next seven days. Bring your medication list and any relevant test results.</p>
<?php endif; ?>
        </div>
    </section>

    <form class="booking-filters" method="get" action="<?= e(url('/book.php')) ?>">
<?php if ($reschedule !== null): ?>
        <input type="hidden" name="reschedule" value="<?= e((string) $rescheduleId) ?>">
<?php endif; ?>
        <p>
            <label for="doctor">Doctor</label>
            <select id="doctor" name="doctor">
                <option value="">Choose a doctor</option>
<?php foreach ($doctors as $candidate): ?>
                <option value="<?= e((string) $candidate['DoctorID']) ?>"<?= e($doctorId === (int) $candidate['DoctorID'] ? ' selected' : '') ?>><?= e((string) $candidate['FullName']) ?></option>
<?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="date">Date</label>
            <input id="date" name="date" type="date" min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($lastBrowseDate->format('Y-m-d')) ?>" value="<?= e($selectedDate) ?>" required>
        </p>
        <p>
            <label for="from">From</label>
            <input id="from" name="from" type="time" value="<?= e($fromTime) ?>" required>
        </p>
        <p>
            <label for="to">To</label>
            <input id="to" name="to" type="time" value="<?= e($toTime) ?>" required>
        </p>
        <button type="submit">Show schedule</button>
    </form>

<?php if ($dateError !== null): ?>
    <p class="error-summary" role="alert"><?= e($dateError) ?> Showing <?= e(fmt_date($selectedDate)) ?> instead. Choose another date to view its schedule.</p>
<?php endif; ?>

    <nav class="day-tabs" aria-label="Choose a day">
<?php for ($offset = 0; $offset < BROWSE_DAYS; $offset++):
    $day = $today->modify('+' . $offset . ' days');
    $dayQuery = http_build_query(array_merge(
        ['doctor' => $doctorId, 'date' => $day->format('Y-m-d'), 'from' => $fromTime, 'to' => $toTime],
        $reschedule !== null ? ['reschedule' => $rescheduleId] : []
    ));
?>
        <a class="day-tab<?= e($selectedDate === $day->format('Y-m-d') ? ' selected' : '') ?>" href="<?= e(url('/book.php?' . $dayQuery)) ?>"><?= e($day->format('D d M')) ?></a>
<?php endfor; ?>
    </nav>

<?php flash_render(); ?>

<?php if ($doctor === null): ?>
    <p class="empty-state">Choose a doctor to view their schedule.</p>
<?php else: ?>
    <section class="booking-results" aria-label="Doctor schedule">
        <aside class="booking-doctor">
            <img src="<?= e(url(!empty($doctor['ImageURL']) ? '/' . ltrim((string) $doctor['ImageURL'], '/') : '/assets/img/General_Practice_in_Action.jpg')) ?>" width="112" height="112" loading="eager" decoding="async" alt="Portrait of <?= e((string) $doctor['FullName']) ?>">
            <div><h2><?= e((string) $doctor['FullName']) ?></h2><p><?= e((string) $doctor['Specialty']) ?></p><p><?= e(fmt_date($selectedDate)) ?></p></div>
        </aside>
        <div class="booking-slots">
    <p>Choose a free time. Your appointment is confirmed after you submit the reason for your visit.</p>
<?php if ($dayEmptyMessage !== null): ?>
    <p class="empty-state"><?= e($dayEmptyMessage) ?>
<?php if ($availableDayUrl !== null): ?>
        <a href="<?= e($availableDayUrl) ?>">View available times on <?= e(fmt_date($availableDays[0])) ?></a>, or choose a date above.
<?php else: ?>
        No available appointment times within the next <?= e((string) BROWSE_DAYS) ?> days with these time filters. Choose another date or change the time filters above.
<?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($slots !== []): ?>
    <div class="schedule-grid" aria-label="Appointment schedule">
<?php foreach ($slots as $slot):
    $slotDateTime = new DateTimeImmutable((string) $slot['SlotDateTime']);
    $isPast = $slotDateTime <= $now;
    $status = (string) ($slot['Status'] ?? 'Blocked');
    $stateClass = match ($status) {
        'Booked' => 'taken',
        'Blocked' => 'blocked',
        default => $isPast ? 'past' : 'free',
    };
?>
        <div class="slot <?= e($stateClass) ?>">
            <span class="slot-time"><?= e(fmt_time((string) $slot['SlotDateTime'])) ?></span>
<?php if ($stateClass === 'free'): ?>
            <details class="slot-booking"><summary>Select time</summary>
            <form method="post" action="<?= e(url($reschedule === null ? '/actions/book.php' : '/actions/appointment.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                <input type="hidden" name="doctor" value="<?= e((string) $doctorId) ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <input type="hidden" name="from" value="<?= e($fromTime) ?>">
                <input type="hidden" name="to" value="<?= e($toTime) ?>">
<?php if ($reschedule !== null): ?>
                <input type="hidden" name="appointment_id" value="<?= e((string) $reschedule['appointmentID']) ?>">
                <input type="hidden" name="reschedule" value="1">
                <input type="hidden" name="actor" value="<?= e($rescheduleActor) ?>">
<?php endif; ?>
<?php if ($reschedule !== null): ?>
                <p>Replace your appointment with <?= e((string) $reschedule['DoctorName']) ?> on <?= e(fmt_date((string) $reschedule['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $reschedule['appointmentDateTime'])) ?> with this time: <?= e(fmt_date($selectedDate)) ?> at <?= e(fmt_time((string) $slot['SlotDateTime'])) ?>. Your current booking stays in place until this change succeeds.</p>
<?php else: ?>
                <p>Confirm this appointment with <?= e((string) $doctor['FullName']) ?> on <?= e(fmt_date($selectedDate)) ?> at <?= e(fmt_time((string) $slot['SlotDateTime'])) ?>.</p>
<?php endif; ?>
                <label for="reason-<?= e((string) $slot['slotID']) ?>">Reason for visit</label>
                <input id="reason-<?= e((string) $slot['slotID']) ?>" name="reason" type="text" maxlength="255" required>
                <button type="submit"><?= e($reschedule === null ? 'Confirm booking' : 'Confirm reschedule') ?></button>
            </form>
            </details>
<?php else: ?>
            <span class="slot-state"><?= e($stateClass === 'taken' ? 'Booked' : ($stateClass === 'blocked' ? 'Unavailable' : 'Past')) ?></span>
<?php endif; ?>
        </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
        </div>
    </section>
<?php endif; ?>
</main>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
