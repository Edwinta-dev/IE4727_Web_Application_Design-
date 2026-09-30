<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';

require_doctor();

$user = current_user();
$doctorId = (int) $user['id'];
$today = new DateTimeImmutable('today');
$dateInput = $_GET['date'] ?? '';
$date = is_string($dateInput) ? $dateInput : '';
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
    $date = $today->format('Y-m-d');
}

$appointments = appointments_for_doctor_day($doctorId, $date);
$counts = ['Future' => 0, 'Completed' => 0, 'No show' => 0];
foreach ($appointments as $appointment) {
    $status = (string) $appointment['Status'];
    if ($status === 'Rescheduled') {
        $counts['Future']++;
    } elseif (array_key_exists($status, $counts)) {
        $counts[$status]++;
    }
}

$historyPatientInput = $_GET['patient'] ?? '';
$historyPatientId = is_string($historyPatientInput) && ctype_digit($historyPatientInput)
    ? (int) $historyPatientInput
    : 0;
$history = $historyPatientId > 0 ? patient_history_for_doctor($historyPatientId, $doctorId) : [];

$pageTitle = 'Doctor Day Board';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="appointments">
    <section class="page-intro">
        <h1>Day board</h1>
        <p>Review appointments, attendance and patient history for your clinic schedule.</p>
        <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" alt="Clinic Appointment Portal" class="page-intro-image">
    </section>

    <?php flash_render(); ?>

    <form class="day-board-filter" method="get" action="<?= e(url('/doctor/home.php')) ?>">
        <?= csrf_field() ?>
        <label for="day">Schedule date</label>
        <input id="day" name="date" type="date" value="<?= e($date) ?>" required>
        <button type="submit">Show day</button>
    </form>

    <section class="day-summary summary-strip" aria-label="Day summary">
        <p class="summary-future">Future: <?= e((string) $counts['Future']) ?></p>
        <p class="summary-completed">Completed: <?= e((string) $counts['Completed']) ?></p>
        <p class="summary-no-show">No-show: <?= e((string) $counts['No show']) ?></p>
    </section>

    <section class="day-schedule" aria-label="Appointments for <?= e($date) ?>">
        <h2><?= e(fmt_date($date)) ?></h2>
        <?php if ($appointments === []): ?>
            <p class="empty-state">There are no appointments for this day.</p>
        <?php else: ?>
            <table class="day-board">
                <thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                        <td>
                            <a href="<?= e(url('/doctor/home.php?' . http_build_query(['date' => $date, 'patient' => (int) $appointment['PatientID']]))) ?>">
                                <?= e((string) $appointment['PatientName']) ?>
                            </a>
                        </td>
                        <td><?= e((string) ($appointment['Remarks'] ?? '')) ?></td>
                        <td><?= e((string) $appointment['Status']) ?></td>
                        <td>
                            <?php if (in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true)): ?>
                                <a href="<?= e(url('/book.php?' . http_build_query(['reschedule' => (int) $appointment['appointmentID'], 'doctor' => $doctorId, 'date' => $date, 'actor' => 'doctor']))) ?>">Reschedule</a>
                                <form method="post" action="<?= e(url('/actions/appointment.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['appointmentID']) ?>">
                                    <input type="hidden" name="status" value="Completed">
                                    <button type="submit">Mark completed</button>
                                </form>
                                <form method="post" action="<?= e(url('/actions/appointment.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['appointmentID']) ?>">
                                    <input type="hidden" name="status" value="No show">
                                    <button type="submit">Mark no-show</button>
                                </form>
                                <a href="<?= e(url('/doctor/visit.php?appt=' . (int) $appointment['appointmentID'])) ?>">Start visit</a>
                            <?php else: ?>
                                <span>No attendance action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
