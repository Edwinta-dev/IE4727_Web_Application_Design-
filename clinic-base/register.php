<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';

$pageTitle = 'Register - Clinic Appointment Portal';
$role = (string) old('role', 'patient');
if (!in_array($role, ['patient', 'doctor'], true)) {
    $role = 'patient';
}
$fields = [
    'FullName', 'User', 'Email', 'Gender', 'Phone', 'Allergies',
    'Specialty', 'Qualifications', 'WriteUp', 'Languages',
];
$oldValues = [];
foreach ($fields as $field) {
    $oldValues[$field] = old($field);
}

require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="main-content" class="registration-page">
    <section class="page-intro public-page-intro registration-intro">
        <div class="page-intro-copy">
            <h1>Create your clinic account</h1>
            <p>Register for patient appointments or join the clinic as a doctor.</p>
        </div>
    </section>

    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php'; ?>

    <div class="registration-layout">
    <form id="registration-form" method="post" action="<?= e(url('/actions/register.php')) ?>" onsubmit="return validateRegistrationForm(event)">
        <?= csrf_field() ?>
        <fieldset class="registration-type" id="registration-type">
            <legend>Account type</legend>
            <div class="registration-role-options">
                <label><input type="radio" name="role" value="patient"<?= e($role === 'patient' ? ' checked' : '') ?>> <span>Patient</span></label>
                <label><input type="radio" name="role" value="doctor"<?= e($role === 'doctor' ? ' checked' : '') ?>> <span>Doctor</span></label>
            </div>
            <div class="registration-role-switcher" hidden>
                <button type="button" class="registration-role-arrow" data-role-switch="patient" aria-label="Switch to patient account">←</button>
                <h2 class="registration-role-heading" aria-live="polite"><?= e(ucfirst($role)) ?> account</h2>
                <button type="button" class="registration-role-arrow" data-role-switch="doctor" aria-label="Switch to doctor account">→</button>
            </div>
        </fieldset>

        <fieldset class="registration-details">
            <legend>Account details</legend>
            <p>
                <label for="full-name">Full name</label>
                <input id="full-name" name="FullName" type="text" value="<?= e((string) $oldValues['FullName']) ?>" placeholder="Your full name" required>
                <span class="field-error" data-error-for="FullName" role="alert"></span>
            </p>
            <p>
                <label for="username">Username</label>
                <input id="username" name="User" type="text" value="<?= e((string) $oldValues['User']) ?>" placeholder="Choose a username" required>
                <span class="field-error" data-error-for="User" role="alert"><?= e(errors_for('User')) ?></span>
            </p>
            <p>
                <label for="email">Email</label>
                <input id="email" name="Email" type="email" value="<?= e((string) $oldValues['Email']) ?>" placeholder="you@example.com" required>
                <span class="field-error" data-error-for="Email" role="alert"><?= e(errors_for('Email')) ?></span>
            </p>
            <p>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" minlength="8" placeholder="At least 8 characters" required>
                <span class="field-error" data-error-for="password" role="alert"><?= e(errors_for('password')) ?></span>
            </p>
            <p>
                <label for="confirm-password">Confirm password</label>
                <input id="confirm-password" name="confirm_password" type="password" minlength="8" placeholder="Repeat your password" required>
                <span class="field-error" data-error-for="confirm_password" role="alert"><?= e(errors_for('confirm_password')) ?></span>
            </p>
        </fieldset>

        <div class="registration-role-viewport">
        <div class="registration-role-track" data-active-role="<?= e($role) ?>">
        <fieldset id="patient-fields" data-role-fields="patient"<?= e($role === 'patient' ? '' : ' hidden') ?>>
            <legend>Patient details</legend>
            <p>
                <label for="gender">Gender</label>
                <select id="gender" name="Gender" required>
                    <option value="">Choose your gender</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                        <option value="<?= e($gender) ?>"<?= e($oldValues['Gender'] === $gender ? ' selected' : '') ?>><?= e($gender) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field-error" data-error-for="Gender" role="alert"><?= e(errors_for('Gender')) ?></span>
            </p>
            <p>
                <label for="phone">Phone</label>
                <input id="phone" name="Phone" type="tel" value="<?= e((string) $oldValues['Phone']) ?>" pattern="^\+?[0-9 ()-]{7,20}$" placeholder="e.g. +65 6123 4567" required>
                <span class="field-error" data-error-for="Phone" role="alert"><?= e(errors_for('Phone')) ?></span>
            </p>
            <p>
                <label for="allergies">Allergies</label>
                <textarea id="allergies" name="Allergies" placeholder="List allergies, separated by commas"><?= e((string) $oldValues['Allergies']) ?></textarea>
                <span class="field-error" data-error-for="Allergies" role="alert"><?= e(errors_for('Allergies')) ?></span>
            </p>
        </fieldset>

        <fieldset id="doctor-fields" data-role-fields="doctor"<?= e($role === 'doctor' ? '' : ' hidden') ?>>
            <legend>Doctor details</legend>
            <?php foreach ([
                'Specialty' => ['Specialty', 'Your medical specialty'],
                'Qualifications' => ['Qualifications', 'Your qualifications'],
                'Languages' => ['Languages', 'Languages you speak'],
            ] as $field => [$label, $placeholder]): ?>
                <p>
                    <label for="<?= e(strtolower($field)) ?>"><?= e($label) ?></label>
                    <input id="<?= e(strtolower($field)) ?>" name="<?= e($field) ?>" type="text" value="<?= e((string) $oldValues[$field]) ?>" placeholder="<?= e($placeholder) ?>" required disabled>
                    <span class="field-error" data-error-for="<?= e($field) ?>" role="alert"><?= e(errors_for($field)) ?></span>
                </p>
            <?php endforeach; ?>
            <p>
                <label for="write-up">Professional background</label>
                <textarea id="write-up" name="WriteUp" placeholder="Tell patients about your background" required disabled><?= e((string) $oldValues['WriteUp']) ?></textarea>
                <span class="field-error" data-error-for="WriteUp" role="alert"><?= e(errors_for('WriteUp')) ?></span>
            </p>
        </fieldset>
        </div>
        </div>

        <button type="submit">Create account</button>
    </form>
    <aside class="registration-support" aria-label="Registration information">
        <img src="<?= e(url('/assets/img/Clinic_Assisting_Elderly_woman.jpg')) ?>" width="600" height="400" loading="eager" decoding="async" alt="Clinic staff assisting an older patient" class="registration-support-image">
        <section>
            <h2>What you’ll need</h2>
            <p>Patients need a phone number and a list of known allergies. Doctors should have their specialty, qualifications and spoken languages ready.</p>
        </section>
        <section>
            <h2>What happens next</h2>
            <p>After registration, patients can choose a doctor and request an appointment. Doctors can sign in to manage their clinic schedule.</p>
        </section>
    </aside>
    </div>
</main>
<script src="<?= e(url('/assets/app.js')) ?>"></script>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
