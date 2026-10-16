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
$rescheduleReason = '';
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
        $rescheduleRemarks = decode_visit_remarks($candidate['Remarks'], (string) $candidate['Status']);
        $rescheduleReason = $rescheduleRemarks['reason'];
        $rescheduleActor = is_doctor() ? 'doctor' : 'patient';
    } else {
        flash('That appointment cannot be rescheduled.', 'error');
        redirect(is_doctor() ? '/doctor/home.php' : '/patient/home.php');
    }
}

$now = new DateTimeImmutable();
$today = $now->setTime(0, 0);

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
$specialtyInput = $_GET['specialty'] ?? null;
$selectedSpecialty = is_string($specialtyInput) ? canonical_specialty($specialtyInput) : null;
if ($selectedSpecialty === null) {
    $selectedSpecialty = $doctor !== null
        ? (string) $doctor['Specialty']
        : 'General Practice';
}
if ($reschedule === null && $doctor !== null && (string) $doctor['Specialty'] !== $selectedSpecialty) {
    $doctor = null;
    $doctorId = 0;
}
$availability = $doctorId > 0
    ? booking_day_availability($doctorId, $now, BROWSE_DAYS, $fromTime, $toTime)
    : booking_specialty_day_availability($selectedSpecialty, $now, BROWSE_DAYS, $fromTime, $toTime);
$availableDays = array_keys(array_filter($availability, static fn (array $counts): bool => $counts['available'] > 0));
if ($reschedule !== null && !$dateRequested) {
    $selectedDate = reschedule_landing_date(
        substr((string) $reschedule['appointmentDateTime'], 0, 10), $availability, $selectedDate
    );
}
$dayEmptyMessage = booking_day_empty_message($availability[$selectedDate]);
$availableDayUrl = $availableDays !== [] ? url('/book.php?' . http_build_query(array_merge(
    ['specialty' => $selectedSpecialty, 'date' => $availableDays[0], 'from' => $fromTime, 'to' => $toTime],
    $doctorId > 0 ? ['doctor' => $doctorId] : [],
    $reschedule !== null ? ['reschedule' => $rescheduleId] : []
))) : null;
$slots = $doctorId > 0
    ? slots_for_day($doctorId, $selectedDate, $fromTime, $toTime)
    : specialty_slots_for_day($selectedSpecialty, $selectedDate, $fromTime, $toTime);
$slots = booking_visible_slots($slots, $now);
$doctorById = [];
foreach ($doctors as $candidate) {
    if ((string) $candidate['Specialty'] === $selectedSpecialty) {
        $doctorById[(int) $candidate['DoctorID']] = $candidate;
    }
}
$representedDoctors = [];
$timeGroups = [];
$groupedSlots = [];
$bookableSlots = [];
$otherFutureSlots = [];
foreach ($slots as $slot) {
    if ((string) $slot['Status'] === 'Available') {
        $bookableSlots[] = $slot;
        $representedDoctors[(int) $slot['DoctorID']] = $doctorById[(int) $slot['DoctorID']];
        $timeGroups[(string) $slot['SlotDateTime']][] = $slot;
    } else {
        $otherFutureSlots[] = $slot;
    }
}
$slots = array_merge($bookableSlots, $otherFutureSlots);
$tomorrowUrl = $selectedDate === $today->format('Y-m-d')
    && $availability[$selectedDate]['matching'] > 0
    && $availability[$selectedDate]['future'] === 0
    ? url('/book.php?' . http_build_query(array_merge(
        ['specialty' => $selectedSpecialty, 'date' => $today->modify('+1 day')->format('Y-m-d'), 'from' => $fromTime, 'to' => $toTime],
        $doctorId > 0 ? ['doctor' => $doctorId] : [],
        $reschedule !== null ? ['reschedule' => $rescheduleId] : []
    )))
    : null;
if ($doctorId === 0) {
    // One visible cell per start time; its alternatives stay on the server and in data attributes.
    foreach ($timeGroups as $time => $alternatives) $groupedSlots[] = $alternatives[0];
    $slots = $groupedSlots;
}

$pageTitle = $reschedule !== null ? 'Reschedule an Appointment' : 'Book an Appointment';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="page-intro page-intro-spaced booking-intro">
        <div class="page-intro-copy">
            <h1><?= e($reschedule !== null ? 'Reschedule an appointment' : 'Book an appointment') ?></h1>
<?php if ($reschedule !== null): ?>
            <p class="reschedule-context">Current appointment: <?= e((string) $reschedule['DoctorName']) ?> on <?= e(fmt_date((string) $reschedule['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $reschedule['appointmentDateTime'])) ?>.</p>
            <p>Your current booking stays in place until the replacement is confirmed. <a href="<?= e(url($rescheduleActor === 'doctor' ? '/doctor/home.php' : '/patient/home.php')) ?>">Back to appointments</a></p>
<?php else: ?>
            <p>Choose a specialty, date and appointment time. Bring your medication list and any relevant test results.</p>
<?php endif; ?>
        </div>
    </section>

    <form class="booking-filters" method="get" action="<?= e(url('/book.php')) ?>">
        <fieldset disabled hidden><?= csrf_field() ?></fieldset>
        <input id="date" type="hidden" name="date" value="<?= e($selectedDate) ?>">
<?php if ($reschedule !== null): ?>
        <input type="hidden" name="reschedule" value="<?= e((string) $rescheduleId) ?>">
<?php endif; ?>
        <p class="booking-specialty-filter">
            <label for="specialty">Specialty</label>
            <select id="specialty" name="specialty">
<?php foreach (canonical_specialties() as $specialty): ?>
                <option value="<?= e($specialty) ?>"<?= e($selectedSpecialty === $specialty ? ' selected' : '') ?>><?= e($specialty) ?></option>
<?php endforeach; ?>
            </select>
        </p>
        <div class="booking-date-filter">
            <span class="booking-filter-label">Date</span>
            <div class="booking-days">
                <button class="day-scroll" type="button" data-direction="-1" aria-label="Show earlier days" aria-controls="booking-day-tabs" hidden>&larr;</button>
                <nav id="booking-day-tabs" class="day-tabs" aria-label="Choose a day" aria-describedby="day-scroll-hint">
<?php for ($offset = 0; $offset < BROWSE_DAYS; $offset++):
    $day = $today->modify('+' . $offset . ' days');
    $dayQuery = http_build_query(array_merge(
        ['specialty' => $selectedSpecialty, 'date' => $day->format('Y-m-d'), 'from' => $fromTime, 'to' => $toTime],
        $doctorId > 0 ? ['doctor' => $doctorId] : [],
        $reschedule !== null ? ['reschedule' => $rescheduleId] : []
    ));
?>
                    <a class="day-tab<?= e($selectedDate === $day->format('Y-m-d') ? ' selected' : '') ?>" href="<?= e(url('/book.php?' . $dayQuery)) ?>"<?php if ($selectedDate === $day->format('Y-m-d')): ?> aria-current="date"<?php endif; ?>><?= e($day->format('D d M')) ?></a>
<?php endfor; ?>
                </nav>
                <button class="day-scroll" type="button" data-direction="1" aria-label="Show later days" aria-controls="booking-day-tabs" hidden>&rarr;</button>
            </div>
            <p id="day-scroll-hint" class="day-scroll-hint">Scroll sideways to see all seven days.</p>
            <details class="booking-time-filter"<?= e($fromTime !== '00:00' || $toTime !== '23:59' ? ' open' : '') ?>>
            <summary>Filter by time</summary>
            <div class="booking-time-fields">
                <p>
                    <label for="from">From</label>
                    <input id="from" name="from" type="time" value="<?= e($fromTime) ?>" required>
                </p>
                <p>
                    <label for="to">To</label>
                    <input id="to" name="to" type="time" value="<?= e($toTime) ?>" required>
                </p>
            </div>
            </details>
        </div>
        <p class="booking-doctor-filter">
            <label for="doctor">Doctor</label>
            <select id="doctor" name="doctor">
                <option value="">All doctors</option>
<?php foreach ($doctors as $candidate): ?>
                <option value="<?= e((string) $candidate['DoctorID']) ?>" data-specialty="<?= e((string) $candidate['Specialty']) ?>"<?= e($doctorId === (int) $candidate['DoctorID'] ? ' selected' : '') ?>><?= e((string) $candidate['FullName']) ?></option>
<?php endforeach; ?>
            </select>
        </p>
        <noscript><button type="submit">Apply filters</button></noscript>
    </form>

<?php if ($dateError !== null): ?>
    <p class="error-summary" role="alert"><?= e($dateError) ?> Showing <?= e(fmt_date($selectedDate)) ?> instead. Choose another date to view its schedule.</p>
<?php endif; ?>

<?php $bookingConfirmed = flash_render(); ?>

    <section class="booking-results" aria-label="Doctor schedule">
        <aside class="booking-doctor" aria-labelledby="showing-doctors-heading">
            <h2 id="showing-doctors-heading">Showing slots for:</h2>
            <p><?= e($selectedSpecialty) ?></p>
            <p><?= e(fmt_date($selectedDate)) ?></p>
<?php if ($representedDoctors === []): ?>
            <p class="booking-no-doctors">No doctors have available times for this date. Try another date.</p>
<?php else: ?>
            <p class="booking-doctor-instruction">Choose a doctor and a time to book.</p>
            <noscript><p>To book without JavaScript, choose a doctor in the filter above before selecting a time.</p></noscript>
            <div class="booking-doctor-list">
<?php foreach ($representedDoctors as $candidate): ?>
                <label class="booking-doctor-option" data-doctor-id="<?= e((string) $candidate['DoctorID']) ?>">
                    <input type="radio" name="doctor" value="<?= e((string) $candidate['DoctorID']) ?>" form="booking-post"<?= e($doctorId === (int) $candidate['DoctorID'] ? ' checked' : '') ?> required>
                    <img src="<?= e(url(!empty($candidate['ImageURL']) ? '/' . ltrim((string) $candidate['ImageURL'], '/') : '/assets/img/General_Practice_in_Action.jpg')) ?>" width="56" height="56" loading="eager" decoding="async" alt="">
                    <span><strong><?= e((string) $candidate['FullName']) ?></strong><small><?= e((string) $candidate['Specialty']) ?></small></span>
                </label>
<?php endforeach; ?>
            </div>
<?php endif; ?>
        </aside>
        <div class="booking-slots">
<?php if ($dayEmptyMessage !== null): ?>
    <p class="empty-state"><?= e($dayEmptyMessage) ?>
<?php if ($tomorrowUrl !== null): ?>
        <a href="<?= e($tomorrowUrl) ?>">See tomorrow's times</a>.
<?php elseif ($availableDayUrl !== null): ?>
        <a href="<?= e($availableDayUrl) ?>">View available times on <?= e(fmt_date($availableDays[0])) ?></a>, or choose a date above.
<?php else: ?>
        No available appointment times within the next <?= e((string) BROWSE_DAYS) ?> days with these time filters. Choose another date or change the time filters above.
<?php endif; ?>
    </p>
<?php endif; ?>
<?php if (!$bookingConfirmed && $slots !== []): ?>
    <div class="slot-booking">
            <form id="booking-post" method="post" action="<?= e(url($reschedule === null ? '/actions/book.php' : '/actions/appointment.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <input type="hidden" name="specialty" value="<?= e($selectedSpecialty) ?>">
                <input type="hidden" name="from" value="<?= e($fromTime) ?>">
                <input type="hidden" name="to" value="<?= e($toTime) ?>">
<?php if ($reschedule !== null): ?>
                <input type="hidden" name="appointment_id" value="<?= e((string) $reschedule['appointmentID']) ?>">
                <input type="hidden" name="reschedule" value="1">
<?php endif; ?>
    <div class="schedule-grid" role="group" aria-label="Appointment time">
<?php foreach ($slots as $slot):
    $status = (string) ($slot['Status'] ?? 'Blocked');
    $stateClass = match ($status) {
        'Booked' => 'taken',
        'Blocked' => 'blocked',
        default => 'free',
    };
?>
        <div class="slot <?= e($stateClass) ?>">
<?php if ($stateClass === 'free'): ?>
            <input class="slot-choice" id="slot-<?= e((string) $slot['slotID']) ?>" type="radio" name="slot_id" value="<?= e((string) $slot['slotID']) ?>" data-doctor-id="<?= e((string) $slot['DoctorID']) ?>" data-time="<?= e(fmt_time((string) $slot['SlotDateTime'])) ?>" required>
            <label class="slot-time" for="slot-<?= e((string) $slot['slotID']) ?>"><?= e(fmt_time((string) $slot['SlotDateTime'])) ?></label>
<?php if ($doctorId === 0): ?>
            <span class="slot-doctors">
<?php foreach ($timeGroups[(string) $slot['SlotDateTime']] as $coveringSlot): ?>
                <span data-doctor-id="<?= e((string) $coveringSlot['DoctorID']) ?>" data-slot-id="<?= e((string) $coveringSlot['slotID']) ?>"><?= e((string) $doctorById[(int) $coveringSlot['DoctorID']]['FullName']) ?></span>
<?php endforeach; ?>
            </span>
<?php else: ?>
            <span class="slot-doctors"><span><?= e((string) $doctor['FullName']) ?></span></span>
<?php endif; ?>
<?php else: ?>
            <span class="slot-time"><?= e(fmt_time((string) $slot['SlotDateTime'])) ?></span>
            <span class="slot-state"><?= e($stateClass === 'taken' ? 'Booked' : 'Unavailable') ?></span>
<?php endif; ?>
        </div>
<?php endforeach; ?>
    </div>
<?php if ($dayEmptyMessage === null): ?>
    <section class="booking-confirmation" aria-labelledby="confirmation-heading">
                <h3 id="confirmation-heading"><?= e($reschedule === null ? 'Confirm appointment' : 'Confirm replacement') ?></h3>
                <p><span id="selected-doctor" aria-live="polite"><?= e($doctor !== null ? (string) $doctor['FullName'] : 'Choose a doctor') ?></span> &middot; <?= e(fmt_date($selectedDate)) ?> &middot; <span id="selected-time" aria-live="polite">Time selected above</span></p>
<?php if ($reschedule !== null): ?>
                <p class="booking-reason">Reason for visit: <?= e($rescheduleReason !== '' ? $rescheduleReason : 'Not separately recorded') ?></p>
<?php else: ?>
                <label for="reason">Reason for visit</label>
                <input id="reason" name="reason" type="text" maxlength="255" required>
<?php endif; ?>
                <div class="booking-form-actions">
                    <button type="submit"><?= e($reschedule === null ? 'Confirm booking' : 'Confirm reschedule') ?></button>
                </div>
    </section>
<?php endif; ?>
            </form>
    </div>
<?php endif; ?>
        </div>
    </section>
</main>
<script src="<?= e(url('/assets/booking-slots.js')) ?>" defer></script>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
