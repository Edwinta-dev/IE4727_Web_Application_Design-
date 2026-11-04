<?php

/*
Signed-in patient dashboard. Lists upcoming, past and cancelled appointments,
the printable visit summary for ?view=id, and the latest 20 notifications.
Cancel and reschedule buttons post to actions/appointment.php.
*/

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

require_patient();

$user = current_user();
$patientId = (int) $user['id'];
$upcoming = appointments_for_patient($patientId, 'upcoming');
$past = appointments_for_patient($patientId, 'past');
$cancelled = appointments_for_patient($patientId, 'cancelled');
$notifications = notifications_for_patient($patientId);
$viewIdInput = $_GET['view'] ?? '';
$viewId = is_string($viewIdInput) && ctype_digit($viewIdInput) ? (int) $viewIdInput : 0;
$visit = $viewId > 0 ? completed_appointment_for_patient($patientId, $viewId) : null;

$pageTitle = 'My Appointments';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="appointments">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1>My appointments</h1>
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
                <tr><td colspan="6"><div class="empty-state appointment-empty-state"><p>You have no upcoming appointments.</p><a class="button appointment-action" href="<?= e(url('/screens/book.php')) ?>">Book an appointment</a></div></td></tr>
<?php else: ?>
<?php foreach ($upcoming as $appointment): ?>
                <tr id="appointment-<?= e((string) $appointment['appointmentID']) ?>">
                    <td data-label="Doctor"><?= e((string) $appointment['DoctorName']) ?></td>
                    <td data-label="Specialty"><?= e((string) ($appointment['Specialty'] ?? '')) ?></td>
                    <td data-label="Date"><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Time"><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Status"><?= e((string) $appointment['Status']) ?></td>
                    <td data-label="Actions">
                        <a class="button appointment-action" href="<?= e(url('/screens/book.php?' . http_build_query(['reschedule' => (int) $appointment['appointmentID'], 'doctor' => (int) $appointment['DoctorID']]))) ?>">Reschedule</a>
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
                    <td data-label="Doctor"><?= e((string) $appointment['DoctorName']) ?></td>
                    <td data-label="Specialty"><?= e((string) ($appointment['Specialty'] ?? '')) ?></td>
                    <td data-label="Date"><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Time"><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Status"><?= e(in_array((string) $appointment['Status'], ['Future', 'Rescheduled'], true) ? 'Awaiting outcome' : (string) $appointment['Status']) ?></td>
                    <td data-label="Visit notes">
<?php if ((string) $appointment['Status'] === 'Completed'): ?>
                        <a href="<?= e(url('/screens/patient/home.php?view=' . (int) $appointment['appointmentID'])) ?>">View visit notes</a>
<?php else: ?>
                        <?= e("\u{2014}") ?>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

<?php if ($cancelled !== []): ?>
    <section class="appointments cancelled-appointments">
        <h2 id="cancelled-heading">Cancelled appointments</h2>
        <p class="table-scroll-hint">Swipe or scroll the table to see times and status.</p>
        <div class="appointment-table-scroll" role="region" aria-labelledby="cancelled-heading" tabindex="0">
        <table>
            <thead><tr><th scope="col">Doctor</th><th scope="col">Specialty</th><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Status</th></tr></thead>
            <tbody>
<?php foreach ($cancelled as $appointment): ?>
                <tr>
                    <td data-label="Doctor"><?= e((string) $appointment['DoctorName']) ?></td>
                    <td data-label="Specialty"><?= e((string) ($appointment['Specialty'] ?? '')) ?></td>
                    <td data-label="Date"><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Time"><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td>
                    <td data-label="Status"><?= e((string) $appointment['Status']) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endif; ?>

    <section class="patient-notifications" aria-labelledby="notifications-heading">
        <h2 id="notifications-heading">Latest notifications</h2>
<?php if ($notifications === []): ?>
        <p class="empty-state">You have no notifications.</p>
<?php else: ?>
        <p>Booking updates and messages from the clinic. Your latest 20 messages are shown.</p>
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

<?php if ($visit !== null): ?>
    <?php $visitRemarks = decode_visit_remarks($visit['Remarks'], (string) $visit['Status']); ?>
    <section class="visit-notes" aria-label="Read-only visit notes">
        <div class="visit-notes-header">
            <h2>Visit notes</h2>
            <button type="button" class="button print-slip-button" onclick="window.print()">Print consultation summary</button>
        </div>
        <article class="medical-slip">
            <div class="slip-header">
                <div class="slip-letterhead">
                    <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" alt="Clinic Appointment Portal logo" class="slip-logo" width="48" height="48">
                    <div class="slip-clinic-details">
                        <p class="slip-clinic-name">Clinic Appointment Portal</p>
                        <address class="slip-clinic-address">NTU South Spine S2-B3A-06</address>
                    </div>
                </div>
                <h3 class="slip-title">Consultation summary</h3>
            </div>

            <section class="slip-metadata" aria-label="Consultation metadata">
                <div class="slip-meta-grid">
                    <div class="slip-meta-item">
                        <span class="slip-label">Consultation date:</span>
                        <span class="slip-value"><?= e(fmt_date((string) $visit['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $visit['appointmentDateTime'])) ?></span>
                    </div>
                    <div class="slip-meta-item">
                        <span class="slip-label">Printed on:</span>
                        <span class="slip-value"><?= e(fmt_date(date('Y-m-d'))) ?></span>
                    </div>
                    <div class="slip-meta-item">
                        <span class="slip-label">Patient Name:</span>
                        <span class="slip-value"><?= e((string) ($user['FullName'] ?? '')) ?></span>
                    </div>
                    <div class="slip-meta-item">
                        <span class="slip-label">Patient ID:</span>
                        <span class="slip-value"><?= e((string) $patientId) ?></span>
                    </div>
                    <div class="slip-meta-item">
                        <span class="slip-label">Attending Doctor:</span>
                        <span class="slip-value"><?= e((string) $visit['DoctorName']) ?></span>
                    </div>
                    <?php if (trim((string) ($visit['Specialty'] ?? '')) !== ''): ?>
                    <div class="slip-meta-item">
                        <span class="slip-label">Medical Specialty:</span>
                        <span class="slip-value"><?= e((string) $visit['Specialty']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="slip-findings" aria-label="Clinical findings">
                <h4 class="slip-section-heading">Clinical findings</h4>
                <div class="slip-findings-list">
                    <div class="slip-finding-item">
                        <p><strong>Diagnosis:</strong> <?= e((string) ($visit['Diagnosis'] ?? '')) ?></p>
                    </div>
                    <div class="slip-finding-item">
                        <p><strong>Prescribed Medications:</strong> <?= e((string) ($visit['Prescription'] ?? '')) ?></p>
                    </div>
                    <div class="slip-finding-item">
                        <p><strong>Treatment Plan:</strong> <?= e((string) ($visit['Treatment'] ?? '')) ?></p>
                    </div>
                    <div class="slip-finding-item">
                        <p><strong>Follow-up instructions:</strong> <?= e((int) $visit['FollowUp'] === 1 ? 'Yes' : 'No') ?></p>
                    </div>
                    <?php if ($visitRemarks['doctor_remarks'] !== ''): ?>
                    <div class="slip-finding-item">
                        <p><strong>Doctor remarks:</strong> <?= e($visitRemarks['doctor_remarks']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="slip-finding-item">
                        <p><strong>Your reason for booking:</strong> <?= e($visitRemarks['reason'] !== '' ? $visitRemarks['reason'] : 'Not separately recorded') ?></p>
                    </div>
                    <?php if ($visitRemarks['legacy'] !== null): ?>
                    <div class="slip-finding-item">
                        <p><strong>Earlier text (author unknown):</strong> <?= e($visitRemarks['legacy']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="slip-footer">
                <div class="slip-validation">
                    <div class="slip-signature-block">
                        <div class="slip-signature-line" aria-hidden="true"></div>
                        <p class="slip-signature-label">Attending Physician Signature</p>
                        <p class="slip-doctor-name"><?= e((string) $visit['DoctorName']) ?></p>
                    </div>
                    <div class="slip-stamp-block">
                        <div class="slip-stamp-placeholder">
                            <span class="stamp-text">Clinic stamp</span>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </section>
<?php endif; ?>
</main>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
