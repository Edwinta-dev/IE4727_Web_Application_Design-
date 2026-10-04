<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'booking.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'patients.php';

require_doctor();

$user = current_user();
$doctorId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

$appointmentInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['appointment_id'] ?? '')
    : ($_GET['appt'] ?? '');
$appointmentId = is_string($appointmentInput) && ctype_digit($appointmentInput)
    ? (int) $appointmentInput
    : 0;
$appointment = $appointmentId > 0 ? find_appointment($appointmentId) : null;

if ($appointment === null || (int) $appointment['DoctorID'] !== $doctorId) {
    render_not_found('The appointment could not be found for this doctor.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $saved = save_visit_notes($appointmentId, $doctorId, [
            'Diagnosis' => is_string($_POST['Diagnosis'] ?? null) ? $_POST['Diagnosis'] : '',
            'Treatment' => is_string($_POST['Treatment'] ?? null) ? $_POST['Treatment'] : '',
            'Prescription' => is_string($_POST['Prescription'] ?? null) ? $_POST['Prescription'] : '',
            'FollowUp' => isset($_POST['FollowUp']) && $_POST['FollowUp'] === '1' ? 1 : 0,
            'Remarks' => is_string($_POST['Remarks'] ?? null) ? $_POST['Remarks'] : '',
        ]);
        if ($saved) {
            flash('Visit notes saved.', 'success');
        } else {
            $currentAppointment = find_appointment($appointmentId);
            $rejectedState = $currentAppointment === null ? 'missing' : visit_notes_state($currentAppointment, $doctorId);
            $message = match ($rejectedState) {
                'not_started' => 'This appointment has not started. Visit notes can only be saved from its start time.',
                'disallowed_status' => 'This appointment is ' . (string) $currentAppointment['Status'] . '. Visit notes cannot be changed.',
                default => 'Visit notes could not be changed for this appointment.',
            };
            flash($message, 'error');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    }
    redirect('/doctor/visit.php?appt=' . $appointmentId);
}

$patient = find_patient((int) $appointment['PatientID']);
if ($patient === null) {
    flash('The patient record could not be found.', 'error');
    redirect('/doctor/home.php');
}

$history = patient_history((int) $appointment['PatientID'], $doctorId, $appointmentId);
$allergies = is_array($patient['Allergies'] ?? null) ? $patient['Allergies'] : [];
$visitState = visit_notes_state($appointment, $doctorId);
$visitEligible = $visitState === 'editable';
$visitRemarks = decode_visit_remarks($appointment['Remarks'], (string) $appointment['Status']);
$pageTitle = 'Doctor Visit';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="visit">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1><img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" width="40" height="40" loading="eager" decoding="async" alt="" class="page-intro-mark">Patient visit</h1>
            <p>Review the patient's medical history before recording this visit.</p>
        </div>
    </section>

    <?php flash_render(); ?>

    <div class="visit-layout">
    <aside class="visit-sidebar">
    <section class="patient-profile" aria-label="Patient profile">
        <h2><?= e((string) $patient['FullName']) ?></h2>
        <p><strong>Appointment:</strong> <?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></p>
        <p><strong>Gender:</strong> <?= e((string) ($patient['Gender'] ?? 'Not provided')) ?></p>
        <p><strong>Phone:</strong> <?= e((string) ($patient['Phone'] ?? 'Not provided')) ?></p>
        <p><strong>Allergies:</strong>
            <?php if ($allergies === []): ?>
                <?= e('None recorded') ?>
            <?php else: ?>
                <?= e(implode(', ', array_map(static fn (mixed $allergy): string => (string) $allergy, $allergies))) ?>
            <?php endif; ?>
        </p>
    </section>

    <section class="patient-history" aria-label="Past medical history">
        <h2>Past medical history</h2>
        <?php if ($history === []): ?>
            <p class="empty-state">No previous visits recorded</p>
        <?php else: ?>
            <div class="visit-history-records">
                <?php foreach ($history as $visit): ?>
                    <article class="visit-history-record">
                        <h3><?= e(fmt_date((string) $visit['appointmentDateTime'])) ?></h3>
                        <p><strong>Doctor:</strong> <?= e((string) ($visit['DoctorName'] ?? '')) ?></p>
                        <p><strong>Diagnosis:</strong> <?= e((string) ($visit['Diagnosis'] ?? '')) ?></p>
                        <p><strong>Treatment:</strong> <?= e((string) ($visit['Treatment'] ?? '')) ?></p>
                        <p><strong>Prescription:</strong> <?= e((string) ($visit['Prescription'] ?? '')) ?></p>
                        <p><strong>Follow-up:</strong> <?= e((int) ($visit['FollowUp'] ?? 0) === 1 ? 'Yes' : 'No') ?></p>
                        <?php $previousRemarks = decode_visit_remarks($visit['Remarks'], (string) $visit['Status']); ?>
                        <?php if ($previousRemarks['legacy'] !== null): ?><p><strong>Earlier text (author unknown):</strong> <?= e($previousRemarks['legacy']) ?></p><?php endif; ?>
                        <?php if ($previousRemarks['reason'] !== ''): ?><p><strong>Patient reason:</strong> <?= e($previousRemarks['reason']) ?></p><?php endif; ?>
                        <p><strong>Doctor remarks:</strong> <?= e($previousRemarks['doctor_remarks']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    </aside>
    <section class="visit-form-section" aria-label="Record visit notes">
        <h2>Record this visit</h2>
        <?php if ($visitRemarks['legacy'] !== null): ?><p><strong>Earlier text (author unknown):</strong> <?= e($visitRemarks['legacy']) ?></p><?php endif; ?>
        <p><strong>Patient reason:</strong> <?= e($visitRemarks['reason'] !== '' ? $visitRemarks['reason'] : 'Not separately recorded') ?></p>
        <?php if (!$visitEligible): ?>
            <?php if ($visitState === 'not_started'): ?>
                <p class="empty-state">This appointment has not started. Visit notes can be recorded from <?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?> on <?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?>.</p>
            <?php else: ?>
                <p class="empty-state">This appointment is <?= e((string) $appointment['Status']) ?>. Visit notes cannot be changed.</p>
                <?php foreach (['Diagnosis', 'Treatment', 'Prescription'] as $field): ?>
                    <?php if ((string) ($appointment[$field] ?? '') !== ''): ?><p><strong><?= e($field) ?>:</strong> <?= e((string) $appointment[$field]) ?></p><?php endif; ?>
                <?php endforeach; ?>
                <?php if ($visitRemarks['doctor_remarks'] !== ''): ?><p><strong>Doctor remarks:</strong> <?= e($visitRemarks['doctor_remarks']) ?></p><?php endif; ?>
                <?php if ((int) ($appointment['FollowUp'] ?? 0) === 1): ?><p><strong>Follow-up:</strong> <?= e('Required') ?></p><?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
        <form class="visit-form" method="post" action="<?= e(url('/doctor/visit.php?appt=' . $appointmentId)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="appointment_id" value="<?= e((string) $appointmentId) ?>">

            <label for="diagnosis">Diagnosis</label>
            <textarea id="diagnosis" name="Diagnosis" rows="3"><?= e((string) ($appointment['Diagnosis'] ?? '')) ?></textarea>

            <label for="treatment">Treatment</label>
            <textarea id="treatment" name="Treatment" rows="3"><?= e((string) ($appointment['Treatment'] ?? '')) ?></textarea>

            <label for="prescription">Prescription</label>
            <textarea id="prescription" name="Prescription" rows="3"><?= e((string) ($appointment['Prescription'] ?? '')) ?></textarea>

            <label for="remarks">Doctor remarks</label>
            <textarea id="remarks" name="Remarks" rows="3"><?= e($visitRemarks['doctor_remarks']) ?></textarea>

            <div class="visit-form-actions"><label for="follow-up">
                <input id="follow-up" type="checkbox" name="FollowUp" value="1"<?= e((int) ($appointment['FollowUp'] ?? 0) === 1 ? ' checked' : '') ?>>
                Follow-up required
            </label><button type="submit">Save visit notes</button></div>
        </form>
        <?php endif; ?>
    </section>
    </div>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
