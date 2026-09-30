<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';

require_login();

$user = current_user();
$patientId = (int) $user['id'];
$upcoming = appointments_for_patient($patientId, 'upcoming');
$past = appointments_for_patient($patientId, 'past');
$viewIdInput = $_GET['view'] ?? '';
$viewId = is_string($viewIdInput) && ctype_digit($viewIdInput) ? (int) $viewIdInput : 0;
$visit = $viewId > 0 ? completed_appointment_for_patient($patientId, $viewId) : null;

$pageTitle = 'My Appointments';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="appointments">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1><img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" width="40" height="40" loading="eager" decoding="async" alt="" class="page-intro-mark">My appointments</h1>
            <p>Check appointment times, change a future booking, or read notes from a completed visit.</p>
        </div>
    </section>

<?php flash_render(); ?>

    <section class="appointments upcoming-appointments">
        <h2>Upcoming appointments</h2>
        <table>
            <thead><tr><th>Doctor</th><th>Specialty</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
<?php if ($upcoming === []): ?>
                <tr><td colspan="6"><p class="empty-state">You have no upcoming appointments.</p></td></tr>
<?php else: ?>
<?php foreach ($upcoming as $appointment): ?>
                <tr>
                    <td><?= e((string) $appointment['DoctorName']) ?></td>
                    <td><?= e((string) ($appointment['Specialty'] ?? '')) ?></td>
                    <td><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?></td>
                    <td><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                    <td><?= e((string) $appointment['Status']) ?></td>
                    <td>
                        <a class="button appointment-action" href="<?= e(url('/book.php?' . http_build_query(['reschedule' => (int) $appointment['appointmentID'], 'doctor' => (int) $appointment['DoctorID']]))) ?>">Reschedule</a>
                        <form method="post" action="<?= e(url('/actions/appointment.php')) ?>" onsubmit="return confirm('Cancel this appointment?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['appointmentID']) ?>">
                            <input type="hidden" name="cancel" value="1">
                            <button class="appointment-action appointment-cancel" type="submit">Cancel</button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="appointments past-appointments">
        <h2>Past appointments</h2>
        <table>
            <thead><tr><th>Doctor</th><th>Specialty</th><th>Date</th><th>Time</th><th>Status</th><th>Visit notes</th></tr></thead>
            <tbody>
<?php if ($past === []): ?>
                <tr><td colspan="6"><p class="empty-state">You have no past appointments.</p></td></tr>
<?php else: ?>
<?php foreach ($past as $appointment): ?>
                <tr>
                    <td><?= e((string) $appointment['DoctorName']) ?></td>
                    <td><?= e((string) ($appointment['Specialty'] ?? '')) ?></td>
                    <td><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?></td>
                    <td><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                    <td><?= e((string) $appointment['Status']) ?></td>
                    <td>
<?php if ((string) $appointment['Status'] === 'Completed'): ?>
                        <a href="<?= e(url('/patient/home.php?view=' . (int) $appointment['appointmentID'])) ?>">View visit notes</a>
<?php else: ?>
                        Not available
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
            </tbody>
        </table>
    </section>

<?php if ($visit !== null): ?>
    <section class="visit-notes" aria-label="Read-only visit notes">
        <h2>Visit notes</h2>
        <p><strong>Doctor:</strong> <?= e((string) $visit['DoctorName']) ?> (<?= e((string) ($visit['Specialty'] ?? '')) ?>)</p>
        <p><strong>Date:</strong> <?= e(fmt_date((string) $visit['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $visit['appointmentDateTime'])) ?></p>
        <p><strong>Diagnosis:</strong> <?= e((string) ($visit['Diagnosis'] ?? '')) ?></p>
        <p><strong>Prescription:</strong> <?= e((string) ($visit['Prescription'] ?? '')) ?></p>
        <p><strong>Treatment:</strong> <?= e((string) ($visit['Treatment'] ?? '')) ?></p>
        <p><strong>Follow-up:</strong> <?= e((int) $visit['FollowUp'] === 1 ? 'Yes' : 'No') ?></p>
        <p><strong>Remarks:</strong> <?= e((string) ($visit['Remarks'] ?? '')) ?></p>
    </section>
<?php endif; ?>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
