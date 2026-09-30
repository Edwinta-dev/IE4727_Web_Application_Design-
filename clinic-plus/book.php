<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

$rescheduleIdInput = $_GET['reschedule'] ?? '';
$rescheduleId = is_string($rescheduleIdInput) && ctype_digit($rescheduleIdInput)
    ? (int) $rescheduleIdInput
    : 0;
$reschedule = null;
$rescheduleActor = 'patient';
if ($rescheduleId > 0) {
    require_login();
    $user = current_user();
    $candidate = (is_patient() || is_doctor()) ? find_appointment($rescheduleId) : null;
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
        redirect('/patient/home.php');
    }
}

$today = new DateTimeImmutable('today');
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

$dateInput = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : '';
$selectedDate = $today->format('Y-m-d');
$pastDateRequested = false;
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
if ($parsedDate !== false && $parsedDate->format('Y-m-d') === $dateInput) {
    $pastDateRequested = $parsedDate < $today;
    if (!$pastDateRequested && $parsedDate <= $lastBrowseDate) {
        $selectedDate = $dateInput;
    }
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
$slots = $doctorId > 0
    ? slots_for_day($doctorId, $selectedDate, $fromTime, $toTime)
    : [];
$now = new DateTimeImmutable();
$hasFreeSlot = false;
foreach ($slots as $slot) {
    if ((string) ($slot['Status'] ?? '') === 'Available'
        && new DateTimeImmutable((string) $slot['SlotDateTime']) > $now) {
        $hasFreeSlot = true;
        break;
    }
}

// htmx requests receive only the schedule markup; normal requests keep the
// complete page and the existing progressive-enhancement fallback.
$isHtmxRequest = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
if ($isHtmxRequest) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'slots_fragment.php';
    exit;
}

$pageTitle = 'Book an Appointment';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="page-intro">
        <h1>Book an appointment</h1>
        <p>Choose a doctor and a time within the next seven days. The schedule shows available, booked, blocked and past slots.</p>
        <img src="<?= e(url('/assets/img/Doctor_Consulting.jpg')) ?>" width="320" height="213" alt="A doctor discussing appointment options with a patient" class="page-intro-image">
    </section>

    <form class="booking-filters" method="get" action="<?= e(url('/book.php')) ?>"
          hx-get="<?= e(url('/book.php')) ?>" hx-target="#schedule-panel" hx-swap="innerHTML"
          hx-push-url="true">
        <?= csrf_field() ?>
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

    <nav class="day-tabs" aria-label="Choose a day">
<?php for ($offset = 0; $offset < BROWSE_DAYS; $offset++):
    $day = $today->modify('+' . $offset . ' days');
    $dayQuery = http_build_query(['doctor' => $doctorId, 'date' => $day->format('Y-m-d'), 'from' => $fromTime, 'to' => $toTime]);
?>
        <a class="day-tab<?= e($selectedDate === $day->format('Y-m-d') ? ' selected' : '') ?>"
           href="<?= e(url('/book.php?' . $dayQuery)) ?>"
           hx-get="<?= e(url('/book.php?' . $dayQuery)) ?>" hx-target="#schedule-panel"
           hx-swap="innerHTML" hx-push-url="true"><?= e($day->format('D d M')) ?></a>
<?php endfor; ?>
    </nav>

<?php flash_render(); ?>

    <div id="schedule-panel">
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'slots_fragment.php'; ?>
<?php if (false): ?>
<?php if ($doctor === null): ?>
    <p class="empty-state">Choose a doctor to view their schedule.</p>
<?php else: ?>
    <h2><?= e((string) $doctor['FullName']) ?> — <?= e(fmt_date($selectedDate)) ?></h2>
    <p>Every listed slot is shown. Only a future free slot can be selected.</p>
    <div class="schedule-grid" aria-label="Appointment schedule">
<?php if ($slots === []): ?>
        <p class="empty-state"><?= e($pastDateRequested ? 'Past dates cannot be booked.' : $doctor['FullName'] . ' is not available on this date.') ?></p>
<?php else: ?>
<?php if (!$hasFreeSlot): ?>
        <p class="empty-state">This day is fully booked or has no future free slots. Please choose another day.</p>
<?php endif; ?>
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
                <p>Confirm this appointment with <?= e((string) $doctor['FullName']) ?> on <?= e(fmt_date($selectedDate)) ?> at <?= e(fmt_time((string) $slot['SlotDateTime'])) ?>.</p>
                <label for="reason-<?= e((string) $slot['slotID']) ?>">Reason for visit</label>
                <input id="reason-<?= e((string) $slot['slotID']) ?>" name="reason" type="text" maxlength="255" required>
                <button type="submit"><?= e($reschedule === null ? 'Confirm booking' : 'Confirm reschedule') ?></button>
            </form>
<?php else: ?>
            <span class="slot-state"><?= e($stateClass === 'taken' ? 'Booked' : ucfirst($stateClass)) ?></span>
<?php endif; ?>
        </div>
<?php endforeach; ?>
<?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
    </div>
</main>
<script src="https://unpkg.com/htmx.org@2.0.4"></script>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
