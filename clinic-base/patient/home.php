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
        <h2 id="upcoming-heading">Upcoming appointments</h2>
<?php if ($upcoming !== []): ?>
        <p class="table-scroll-hint">Swipe or scroll the table to see times, status and actions.</p>
<?php endif; ?>
        <div class="appointment-table-scroll<?= e($upcoming === [] ? ' is-empty' : '') ?>" role="region" aria-labelledby="upcoming-heading"<?php if ($upcoming !== []): ?> tabindex="0"<?php endif; ?>>
        <table>
            <thead><tr><th scope="col">Doctor</th><th scope="col">Specialty</th><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
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
        </div>
    </section>

    <section class="appointments past-appointments">
        <h2 id="past-heading">Past appointments</h2>
<?php if ($past !== []): ?>
        <p class="table-scroll-hint">Swipe or scroll the table to see times, status and visit notes.</p>
<?php endif; ?>
        <div class="appointment-table-scroll<?= e($past === [] ? ' is-empty' : '') ?>" role="region" aria-labelledby="past-heading"<?php if ($past !== []): ?> tabindex="0"<?php endif; ?>>
        <table>
            <thead><tr><th scope="col">Doctor</th><th scope="col">Specialty</th><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Status</th><th scope="col">Visit notes</th></tr></thead>
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
        </div>
    </section>

<?php if ($visit !== null): ?>
    <?php $visitRemarks = decode_visit_remarks($visit['Remarks'], (string) $visit['Status']); ?>
    <section class="visit-notes" aria-label="Read-only visit notes">
        <h2>Visit notes</h2>
        <p><strong>Doctor:</strong> <?= e((string) $visit['DoctorName']) ?> (<?= e((string) ($visit['Specialty'] ?? '')) ?>)</p>
        <p><strong>Date:</strong> <?= e(fmt_date((string) $visit['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $visit['appointmentDateTime'])) ?></p>
        <p><strong>Diagnosis:</strong> <?= e((string) ($visit['Diagnosis'] ?? '')) ?></p>
        <p><strong>Prescription:</strong> <?= e((string) ($visit['Prescription'] ?? '')) ?></p>
        <p><strong>Treatment:</strong> <?= e((string) ($visit['Treatment'] ?? '')) ?></p>
        <p><strong>Follow-up:</strong> <?= e((int) $visit['FollowUp'] === 1 ? 'Yes' : 'No') ?></p>
        <?php if ($visitRemarks['legacy'] !== null): ?><p><strong>Earlier text (author unknown):</strong> <?= e($visitRemarks['legacy']) ?></p><?php endif; ?>
        <p><strong>Your reason for booking:</strong> <?= e($visitRemarks['reason'] !== '' ? $visitRemarks['reason'] : 'Not separately recorded') ?></p>
        <p><strong>Doctor remarks:</strong> <?= e($visitRemarks['doctor_remarks']) ?></p>
    </section>
<?php endif; ?>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
