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
    flash('That appointment does not belong to you.', 'error');
    redirect('/doctor/home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_visit_notes($appointmentId, [
        'Diagnosis' => is_string($_POST['Diagnosis'] ?? null) ? $_POST['Diagnosis'] : '',
        'Treatment' => is_string($_POST['Treatment'] ?? null) ? $_POST['Treatment'] : '',
        'Prescription' => is_string($_POST['Prescription'] ?? null) ? $_POST['Prescription'] : '',
        'FollowUp' => isset($_POST['FollowUp']) && $_POST['FollowUp'] === '1' ? 1 : 0,
        'Remarks' => is_string($_POST['Remarks'] ?? null) ? $_POST['Remarks'] : '',
    ]);

    flash('Visit notes saved and appointment marked completed.', 'success');
    redirect('/doctor/home.php?date=' . rawurlencode(substr((string) $appointment['appointmentDateTime'], 0, 10)));
}

$patient = find_patient((int) $appointment['PatientID']);
if ($patient === null) {
    flash('The patient record could not be found.', 'error');
    redirect('/doctor/home.php');
}

$history = patient_history((int) $appointment['PatientID'], $doctorId);
$allergies = is_array($patient['Allergies'] ?? null) ? $patient['Allergies'] : [];
$pageTitle = 'Doctor Visit';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="visit">
    <section class="page-intro">
        <h1>Patient visit</h1>
        <p>Review the patient's medical history before recording this visit.</p>
        <img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal" class="page-intro-image">
    </section>

    <?php flash_render(); ?>

    <section class="patient-profile" aria-label="Patient profile">
        <h2><?= e((string) $patient['FullName']) ?></h2>
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
                        <p><strong>Remarks:</strong> <?= e((string) ($visit['Remarks'] ?? '')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="visit-form-section" aria-label="Record visit notes">
        <h2>Record this visit</h2>
        <form class="visit-form" method="post" action="<?= e('/doctor/visit.php?appt=' . $appointmentId) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="appointment_id" value="<?= e((string) $appointmentId) ?>">

            <p><strong>Appointment:</strong> <?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></p>

            <label for="diagnosis">Diagnosis</label>
            <textarea id="diagnosis" name="Diagnosis" rows="3"><?= e((string) ($appointment['Diagnosis'] ?? '')) ?></textarea>

            <label for="treatment">Treatment</label>
            <textarea id="treatment" name="Treatment" rows="3"><?= e((string) ($appointment['Treatment'] ?? '')) ?></textarea>

            <label for="prescription">Prescription</label>
            <textarea id="prescription" name="Prescription" rows="3"><?= e((string) ($appointment['Prescription'] ?? '')) ?></textarea>

            <label for="remarks">Remarks</label>
            <textarea id="remarks" name="Remarks" rows="3"><?= e((string) ($appointment['Remarks'] ?? '')) ?></textarea>

            <label for="follow-up">
                <input id="follow-up" type="checkbox" name="FollowUp" value="1"<?= (int) ($appointment['FollowUp'] ?? 0) === 1 ? ' checked' : '' ?>>
                Follow-up required
            </label>

            <button type="submit">Save visit notes</button>
        </form>
    </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
