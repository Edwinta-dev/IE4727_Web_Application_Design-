<?php

/*
Doctor only. Day board for ?date= (if none given and today is empty, jumps to the next day with bookings).
Attendance buttons open at the start time and post to actions/appointment.php.
?patient=id shows that patient's history with this doctor. Also lists the latest 20 notifications.
*/

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

require_doctor();

$user = current_user();
$doctorId = (int) $user['id'];
$today = new DateTimeImmutable('today');
$dateInput = $_GET['date'] ?? '';
$date = is_string($dateInput) ? $dateInput : '';
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$explicitDate = $parsedDate !== false && $parsedDate->format('Y-m-d') === $date;
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
    $date = $today->format('Y-m-d');
}

$appointments = appointments_for_doctor_day($doctorId, $date);
if (!$explicitDate && $appointments === []) {
    $date = next_appointment_day_for_doctor($doctorId, $today->format('Y-m-d')) ?? $date;
    $appointments = appointments_for_doctor_day($doctorId, $date);
}
$boardDay = new DateTimeImmutable($date);
$previousDay = $boardDay->modify('-1 day')->format('Y-m-d');
$followingDay = $boardDay->modify('+1 day')->format('Y-m-d');
$nextAppointmentDay = next_appointment_day_for_doctor($doctorId, max($followingDay, $today->format('Y-m-d')));
$counts = ['Future' => 0, 'Rescheduled' => 0, 'Cancelled' => 0, 'Completed' => 0, 'No show' => 0];
foreach ($appointments as $appointment) {
    $status = (string) $appointment['Status'];
    if (array_key_exists($status, $counts)) {
        $counts[$status]++;
    }
}

$historyPatientInput = $_GET['patient'] ?? '';
$historyPatientId = is_string($historyPatientInput) && ctype_digit($historyPatientInput)
    ? (int) $historyPatientInput
    : 0;
$history = $historyPatientId > 0 ? patient_history_for_doctor($historyPatientId, $doctorId) : [];
$notifications = notifications_for_doctor($doctorId);

$pageTitle = 'Doctor Day Board';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="appointments">
    <section class="page-intro day-board-heading">
        <div class="page-intro-copy">
            <h1>Day board</h1>
        </div>
        <form class="day-board-filter" method="get" action="<?= e(url('/screens/doctor/home.php')) ?>">
            <label for="day">Schedule date</label>
            <input id="day" name="date" type="date" value="<?= e($date) ?>" required>
            <button type="submit">Show day</button>
        </form>
    </section>

    <?php flash_render(); ?>

    <section class="day-summary summary-strip" aria-label="Day summary">
        <p class="summary-future">Future: <?= e((string) $counts['Future']) ?></p>
        <p>Rescheduled: <?= e((string) $counts['Rescheduled']) ?></p>
        <p>Cancelled: <?= e((string) $counts['Cancelled']) ?></p>
        <p class="summary-completed">Completed: <?= e((string) $counts['Completed']) ?></p>
        <p class="summary-no-show">No-show: <?= e((string) $counts['No show']) ?></p>
    </section>

    <section class="day-schedule" aria-label="Appointments for <?= e($date) ?>">
        <nav class="day-board-navigation" aria-label="Day navigation">
            <div class="day-board-date-controls">
                <a href="<?= e(url('/screens/doctor/home.php?' . http_build_query(['date' => $previousDay]))) ?>" aria-label="Previous day"><span aria-hidden="true">&larr;</span><span class="day-board-link-label">Previous day</span></a>
                <h2><?= e(fmt_date($date)) ?></h2>
                <a href="<?= e(url('/screens/doctor/home.php?' . http_build_query(['date' => $followingDay]))) ?>" aria-label="Next day"><span class="day-board-link-label">Next day</span><span aria-hidden="true">&rarr;</span></a>
            </div>
            <?php if ($nextAppointmentDay !== null): ?>
                <a href="<?= e(url('/screens/doctor/home.php?' . http_build_query(['date' => $nextAppointmentDay]))) ?>">Jump to next appointment</a>
            <?php else: ?>
                <span>No appointments on later days.</span>
            <?php endif; ?>
        </nav>
        <?php if ($appointments === []): ?>
            <p class="empty-state">There are no appointments for this day.</p>
        <?php else: ?>
            <div class="table-scroll staff-table-scroll"><table class="day-board">
                <thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td data-label="Time"><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                        <td data-label="Patient">
                            <a href="<?= e(url('/screens/doctor/home.php?' . http_build_query(['date' => $date, 'patient' => (int) $appointment['PatientID']]))) ?>">
                                <?= e((string) $appointment['PatientName']) ?>
                            </a>
                        </td>
                        <?php $boardRemarks = decode_visit_remarks($appointment['Remarks'], (string) $appointment['Status']); ?>
                        <td data-label="Reason"><?= e($boardRemarks['reason'] !== '' ? $boardRemarks['reason'] : ($boardRemarks['legacy'] !== null ? 'Earlier text (author unknown)' : 'Not separately recorded')) ?></td>
                        <td data-label="Status"><?= e((string) $appointment['Status']) ?></td>
                        <td data-label="Actions">
                            <?php $isOpen = in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true); ?>
                            <?php if ($isOpen): ?>
                                <?php $started = appointment_outcome_eligible($appointment, $doctorId); ?>
                                <?php /* Only what is true now: before the start time an appointment can be moved but not started; once started it can be started or marked no-show but not moved. */ ?>
                                <?php if ($started): ?>
                                    <div class="row-actions">
                                        <a class="button" href="<?= e(url('/screens/doctor/visit.php?appt=' . (int) $appointment['appointmentID'])) ?>">Start visit</a>
                                        <form method="post" action="<?= e(url('/actions/appointment.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['appointmentID']) ?>">
                                            <input type="hidden" name="status" value="No show">
                                            <button class="button secondary" type="submit">Mark no-show</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="row-actions">
                                        <a class="button secondary" href="<?= e(url('/screens/book.php?' . http_build_query(['reschedule' => (int) $appointment['appointmentID'], 'doctor' => $doctorId, 'date' => $date, 'actor' => 'doctor']))) ?>">Reschedule</a>
                                    </div>
                                    <p class="row-note">Visit opens at <?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></p>
                                <?php endif; ?>
                            <?php elseif ((string) $appointment['Status'] === 'Completed'): ?>
                                <div class="row-actions">
                                    <a class="button secondary" href="<?= e(url('/screens/doctor/visit.php?appt=' . (int) $appointment['appointmentID'])) ?>">Edit visit notes</a>
                                </div>
                            <?php else: ?>
                                <span class="row-note">Nothing to do</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <?php if ($historyPatientId > 0): ?>
    <section class="patient-history" aria-label="Patient history">
        <h2>Patient history</h2>
        <?php if ($history === []): ?>
            <p class="empty-state">This patient has no completed visits with you.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Status</th><th>Diagnosis</th><th>Treatment</th></tr></thead>
                <tbody>
                <?php foreach ($history as $visit): ?>
                    <tr>
                        <td><?= e(fmt_date((string) $visit['appointmentDateTime'])) ?></td>
                        <td><?= e((string) $visit['Status']) ?></td>
                        <td><?= e((string) ($visit['Diagnosis'] ?? '')) ?></td>
                        <td><?= e((string) ($visit['Treatment'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="patient-notifications" aria-labelledby="notifications-heading">
        <h2 id="notifications-heading">Latest notifications</h2>
<?php if ($notifications === []): ?>
        <p class="empty-state">You have no notifications.</p>
<?php else: ?>
        <p>Booking updates for your appointments. Your latest 20 messages are shown.</p>
<?php foreach ($notifications as $notification): ?>
        <details class="patient-notification">
            <summary>
                <span><?= e((string) $notification['Subject']) ?></span>
                <time datetime="<?= e(str_replace(' ', 'T', (string) $notification['SentAt'])) ?>"><?= e(fmt_date((string) $notification['SentAt']) . ' at ' . fmt_time((string) $notification['SentAt'])) ?></time>
            </summary>
            <div class="notification-body"><?= nl2br(e(patient_notification_body((string) $notification['Body'])), false) ?></div>
        </details>
<?php endforeach; ?>
<?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
