<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

$today = new DateTimeImmutable('today');
$lastBrowseDate = $today->modify('+' . (BROWSE_DAYS - 1) . ' days');

$doctorInput = $_GET['doctor'] ?? $_GET['doctor_id'] ?? '';
$doctorId = is_string($doctorInput) && ctype_digit($doctorInput) ? (int) $doctorInput : 0;
$doctor = $doctorId > 0 ? find_doctor($doctorId) : null;
if ($doctor === null) {
    $doctorId = 0;
}

$dateInput = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : '';
$selectedDate = $today->format('Y-m-d');
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
if ($parsedDate !== false && $parsedDate->format('Y-m-d') === $dateInput
    && $parsedDate >= $today && $parsedDate <= $lastBrowseDate) {
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

$pageTitle = 'Book an Appointment';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="page-intro">
        <h1>Book an appointment</h1>
        <p>Choose a doctor and a time within the next seven days. The schedule shows available, booked, blocked and past slots.</p>
        <img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal" class="page-intro-image">
    </section>

    <form class="booking-filters" method="get" action="/book.php">
        <?= csrf_field() ?>
        <p>
            <label for="doctor">Doctor</label>
            <select id="doctor" name="doctor">
                <option value="">Choose a doctor</option>
<?php foreach ($doctors as $candidate): ?>
                <option value="<?= e((string) $candidate['DoctorID']) ?>"<?= $doctorId === (int) $candidate['DoctorID'] ? ' selected' : '' ?>><?= e((string) $candidate['FullName']) ?></option>
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
        <a class="day-tab<?= $selectedDate === $day->format('Y-m-d') ? ' selected' : '' ?>" href="<?= e('/book.php?' . $dayQuery) ?>"><?= e($day->format('D d M')) ?></a>
<?php endfor; ?>
    </nav>

<?php flash_render(); ?>

<?php if ($doctor === null): ?>
    <p class="empty-state">Choose a doctor to view their schedule.</p>
<?php else: ?>
    <h2><?= e((string) $doctor['FullName']) ?> — <?= e(fmt_date($selectedDate)) ?></h2>
    <p>Every listed slot is shown. Only a future free slot can be selected.</p>
    <div class="schedule-grid" aria-label="Appointment schedule">
<?php if ($slots === []): ?>
        <p class="empty-state">No slots are available for this day and time range. Sunday and fully-booked days may have no selectable slots.</p>
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
            <form method="post" action="/actions/book.php">
                <?= csrf_field() ?>
                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                <input type="hidden" name="doctor" value="<?= e((string) $doctorId) ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <input type="hidden" name="from" value="<?= e($fromTime) ?>">
                <input type="hidden" name="to" value="<?= e($toTime) ?>">
                <p>Confirm this appointment with <?= e((string) $doctor['FullName']) ?> on <?= e(fmt_date($selectedDate)) ?> at <?= e(fmt_time((string) $slot['SlotDateTime'])) ?>.</p>
                <label for="reason-<?= e((string) $slot['slotID']) ?>">Reason for visit</label>
                <input id="reason-<?= e((string) $slot['slotID']) ?>" name="reason" type="text" maxlength="255" required>
                <button type="submit">Confirm booking</button>
            </form>
<?php else: ?>
            <span class="slot-state"><?= e($stateClass === 'taken' ? 'Booked' : ucfirst($stateClass)) ?></span>
<?php endif; ?>
        </div>
<?php endforeach; ?>
<?php endif; ?>
    </div>
<?php endif; ?>
</main>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
