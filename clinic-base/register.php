<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . '/lib/specialties.php';

$pageTitle = 'Register - Clinic Appointment Portal';
$registrationRole = (string) old('role', 'patient');
if (!in_array($registrationRole, ['patient', 'doctor'], true)) {
    $registrationRole = 'patient';
}
$fields = [
    'FullName', 'User', 'Email', 'Gender', 'Phone', 'Allergies',
    'Specialty', 'Qualifications', 'WriteUp', 'Languages',
];
$oldValues = [];
foreach ($fields as $field) {
    $oldValues[$field] = old($field);
}
$registrationErrors = errors_all();

require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="main-content" class="registration-page">
    <section class="page-intro page-intro-spaced public-page-intro registration-intro">
        <div class="page-intro-copy">
            <h1>Create your clinic account</h1>
            <p>Register for patient appointments or join the clinic as a doctor.</p>
        </div>
    </section>

    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php'; ?>

    <?php if ($registrationErrors !== []): ?>
        <div class="error-summary" role="alert" aria-labelledby="registration-error-title" tabindex="-1">
            <h2 id="registration-error-title">Check these registration details</h2>
            <ul><?php foreach ($registrationErrors as $fieldError): ?><li><?= e($fieldError) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="registration-layout">
    <form id="registration-form" method="post" enctype="multipart/form-data" novalidate action="<?= e(url('/actions/register.php')) ?>">
        <?= csrf_field() ?>
        <p class="required-note"><span class="required-mark" aria-hidden="true">*</span> required</p>
        <fieldset class="registration-type" id="registration-type">
            <legend>Account type <span class="required-mark" aria-hidden="true">*</span></legend>
            <div class="registration-role-options">
                <label><input type="radio" name="role" value="patient"<?= e($registrationRole === 'patient' ? ' checked' : '') ?> required aria-required="true"> <span>Patient</span></label>
                <label><input type="radio" name="role" value="doctor"<?= e($registrationRole === 'doctor' ? ' checked' : '') ?> required aria-required="true"> <span>Doctor</span></label>
            </div>
            <?php if (isset($registrationErrors['role'])): ?><span class="field-error" data-error-for="role" role="alert"><?= e($registrationErrors['role']) ?></span><?php endif; ?>
            <div class="registration-role-switcher" hidden>
                <button type="button" class="registration-role-arrow" data-role-switch="patient" aria-label="Switch to patient account">←</button>
                <h2 class="registration-role-heading" aria-live="polite"><?= e(ucfirst($registrationRole)) ?> account</h2>
                <button type="button" class="registration-role-arrow" data-role-switch="doctor" aria-label="Switch to doctor account">→</button>
            </div>
        </fieldset>

        <fieldset class="registration-details">
            <legend>Account details</legend>
            <p>
                <label for="full-name">Full name <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="full-name" name="FullName" type="text" value="<?= e((string) $oldValues['FullName']) ?>" placeholder="Your full name" required aria-required="true">
                <span class="field-error" data-error-for="FullName" role="alert"></span>
            </p>
            <p>
                <label for="username">Username <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="username" name="User" type="text" value="<?= e((string) $oldValues['User']) ?>" placeholder="Choose a username" required aria-required="true">
                <span class="field-error" data-error-for="User" role="alert"><?= e($registrationErrors['User'] ?? '') ?></span>
            </p>
            <p>
                <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="email" name="Email" type="email" value="<?= e((string) $oldValues['Email']) ?>" placeholder="you@example.com" required aria-required="true">
                <span class="field-error" data-error-for="Email" role="alert"><?= e($registrationErrors['Email'] ?? '') ?></span>
            </p>
            <p>
                <label for="password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="password" name="password" type="password" minlength="8" placeholder="At least 8 characters" required aria-required="true">
                <span class="field-error" data-error-for="password" role="alert"><?= e($registrationErrors['password'] ?? '') ?></span>
            </p>
            <p>
                <label for="confirm-password">Confirm password <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="confirm-password" name="confirm_password" type="password" minlength="8" placeholder="Repeat your password" required aria-required="true">
                <span class="field-error" data-error-for="confirm_password" role="alert"><?= e($registrationErrors['confirm_password'] ?? '') ?></span>
            </p>
        </fieldset>

        <div class="registration-role-viewport">
        <div class="registration-role-track" data-active-role="<?= e($registrationRole) ?>">
        <fieldset id="patient-fields" data-role-fields="patient">
            <legend>Patient details</legend>
            <p>
                <label for="gender">Gender <span class="required-mark" aria-hidden="true">*</span></label>
                <select id="gender" name="Gender" required aria-required="true">
                    <option value="">Choose your gender</option>
                    <?php foreach (['Male', 'Female', 'Other', "I'd rather not disclose"] as $gender): ?>
                        <option value="<?= e($gender) ?>"<?= e($oldValues['Gender'] === $gender ? ' selected' : '') ?>><?= e($gender) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field-error" data-error-for="Gender" role="alert"><?= e($registrationErrors['Gender'] ?? '') ?></span>
            </p>
            <p>
                <label for="phone">Phone <span class="required-mark" aria-hidden="true">*</span></label>
                <span class="phone-input"><span class="phone-prefix" aria-hidden="true">+65</span><input id="phone" name="Phone" type="tel" inputmode="numeric" autocomplete="tel-national" value="<?= e((string) $oldValues['Phone']) ?>" pattern="[689][0-9]{7}" maxlength="8" placeholder="81234567" title="Enter an 8-digit Singapore number starting with 6, 8 or 9." aria-label="Phone number after +65" required aria-required="true"></span>
                <span class="field-error" data-error-for="Phone" role="alert"><?= e($registrationErrors['Phone'] ?? '') ?></span>
            </p>
            <p>
                <label for="allergies">Allergies</label>
                <textarea id="allergies" name="Allergies" placeholder="List allergies, separated by commas"><?= e((string) $oldValues['Allergies']) ?></textarea>
                <span class="field-error" data-error-for="Allergies" role="alert"><?= e($registrationErrors['Allergies'] ?? '') ?></span>
            </p>
        </fieldset>

        <fieldset id="doctor-fields" data-role-fields="doctor">
            <legend>Doctor details</legend>
            <p>
                <label for="specialty">Specialty <span class="required-mark" aria-hidden="true">*</span></label>
                <select id="specialty" name="Specialty" required aria-required="true">
                    <option value="">Choose a specialty</option>
<?php foreach (canonical_specialties() as $specialty): ?>
                    <option value="<?= e($specialty) ?>"<?= e($oldValues['Specialty'] === $specialty ? ' selected' : '') ?>><?= e($specialty) ?></option>
<?php endforeach; ?>
                </select>
                <span class="field-error" data-error-for="Specialty" role="alert"><?= e($registrationErrors['Specialty'] ?? '') ?></span>
            </p>
            <?php foreach ([
                'Qualifications' => ['Qualifications', 'Your qualifications'],
                'Languages' => ['Languages', 'Languages you speak'],
            ] as $field => [$label, $placeholder]): ?>
                <p>
                    <label for="<?= e(strtolower($field)) ?>"><?= e($label) ?> <span class="required-mark" aria-hidden="true">*</span></label>
                    <input id="<?= e(strtolower($field)) ?>" name="<?= e($field) ?>" type="text" value="<?= e((string) $oldValues[$field]) ?>" placeholder="<?= e($placeholder) ?>" required aria-required="true">
                    <span class="field-error" data-error-for="<?= e($field) ?>" role="alert"><?= e($registrationErrors[$field] ?? '') ?></span>
                </p>
            <?php endforeach; ?>
            <p>
                <label for="write-up">Professional background <span class="required-mark" aria-hidden="true">*</span></label>
                <textarea id="write-up" name="WriteUp" minlength="50" aria-describedby="write-up-count" placeholder="Tell patients about your training and clinical experience" required aria-required="true"><?= e((string) $oldValues['WriteUp']) ?></textarea>
                <small id="write-up-count" aria-live="polite">0 / 50 characters minimum</small>
                <span class="field-error" data-error-for="WriteUp" role="alert"><?= e($registrationErrors['WriteUp'] ?? '') ?></span>
            </p>
            <p>
                <label for="profile-photo">Profile photo <span class="required-mark" aria-hidden="true">*</span></label>
                <input id="profile-photo" name="ProfilePhoto" type="file" accept="image/jpeg,image/png,image/webp" required aria-required="true" aria-describedby="profile-photo-help">
                <small id="profile-photo-help">JPEG, PNG or WebP; maximum 2 MB.</small>
                <span class="field-error" data-error-for="ProfilePhoto" role="alert"><?= e($registrationErrors['ProfilePhoto'] ?? '') ?></span>
            </p>
        </fieldset>
        </div>
        </div>

        <button type="submit">Create account</button>
    </form>
    <aside class="registration-support" aria-label="Registration information">
        <section>
            <h2>What you’ll need</h2>
            <p>Patients need a phone number and a list of known allergies. Doctors need their specialty, qualifications, languages, a profile photo and at least 50 characters about their background.</p>
        </section>
        <section>
            <h2>What happens next</h2>
            <p>After registration, patients can choose a doctor and request an appointment. Doctors can sign in to manage their clinic schedule.</p>
        </section>
    </aside>
    </div>
</main>
<script src="<?= e(url('/assets/app.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var background = document.getElementById('write-up');
    var count = document.getElementById('write-up-count');
    var updateCount = function () {
        count.textContent = Array.from(background.value.trim()).length + ' / 50 characters minimum';
    };
    background.addEventListener('input', updateCount);
    updateCount();
});
</script>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
