<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';

if (isset($_GET['logout'])) {
    logout();
    flash('You have been logged out.', 'success');
    redirect('/index.php');
}

$pageTitle = 'Clinic Appointment Portal';
$doctors = array_slice(all_doctors(), 0, 3);
$specialties = all_specialties();
$user = current_user();
$next = isset($_GET['next']) && is_string($_GET['next']) ? $_GET['next'] : '';
$homeByRole = [
    'patient' => '/patient/home.php',
    'doctor' => '/doctor/home.php',
    'admin' => '/admin/console.php',
];
$home = is_array($user) ? ($homeByRole[(string) ($user['role'] ?? '')] ?? '/index.php') : null;
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="hero-band band split">
        <div class="hero-copy">
            <div class="hero-copy-text">
                <p class="eyebrow">Appointments at your neighbourhood clinic</p>
                <h1>Care for every stage of life</h1>
                <p>Choose a doctor in general practice, dental care, paediatrics, dermatology or physiotherapy. Book online and manage your appointments here.</p>
                <div class="hero-actions">
                    <a class="button" href="<?= e(url('/doctors.php')) ?>">Find a doctor</a>
                    <a class="button secondary" href="<?= e(url('/doctors.php')) ?>">Book an appointment</a>
                </div>
            </div>
            <img src="<?= e(url('/assets/img/Doctor_Consulting.jpg')) ?>" width="1280" height="853" decoding="async" alt="A doctor discussing care with a patient" class="hero-image">
        </div>
        <section class="member-login" aria-labelledby="member-login-heading">
<?php if ($user !== null): ?>
        <h2 id="member-login-heading">Your clinic account</h2>
        <p>Review appointments and continue managing your care.</p>
        <a class="button" href="<?= e(url($home ?? '/index.php')) ?>">Go to your home</a>
<?php else: ?>
        <h2 id="member-login-heading">Member login</h2>
        <p>Sign in to view or manage your appointments.</p>
        <form method="post" action="<?= e(url('/actions/login.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <p>
                <label for="username-or-email">Username or email</label>
                <input id="username-or-email" name="username_or_email" type="text" value="<?= e((string) old('username_or_email')) ?>" autocomplete="username" required>
            </p>
            <p>
                <label for="login-password">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
            </p>
            <p>
                <label><input name="remember_me" type="checkbox" value="1"> Remember me</label>
                <button type="submit">Sign in</button>
            </p>
        </form>
        <p class="register-prompt">New here? <a href="<?= e(url('/register.php')) ?>">Create an account</a></p>
<?php endif; ?>
        </section>
    </section>

    <section class="featured-doctors band" aria-labelledby="featured-doctors-heading">
        <h2 id="featured-doctors-heading">Meet our featured doctors</h2>
        <div class="doctor-cards">
<?php if ($doctors === []): ?>
            <p class="empty-state">No doctors are currently available.</p>
<?php else: ?>
<?php foreach ($doctors as $doctor):
    $image = trim((string) ($doctor['ImageURL'] ?? ''));
    $image = $image === '' ? '/assets/img/clinic-logo.svg' : '/' . ltrim($image, '/');
?>
            <article class="doctor-card">
                <img src="<?= e(url($image)) ?>" alt="Portrait of <?= e((string) $doctor['FullName']) ?>">
                <h3><?= e((string) $doctor['FullName']) ?></h3>
<?php if (trim((string) ($doctor['Specialty'] ?? '')) !== ''): ?>
                <p class="doctor-specialty"><?= e((string) $doctor['Specialty']) ?></p>
<?php endif; ?>
                <a href="<?= e(url('/doctor.php?id=' . (int) $doctor['DoctorID'])) ?>">View doctor profile</a>
            </article>
<?php endforeach; ?>
<?php endif; ?>
        </div>
    </section>

    <section class="services" aria-labelledby="services-heading">
        <h2 id="services-heading">Care for the whole you</h2>
        <p>Choose a specialty to see the doctors and appointment times available.</p>
        <div class="specialty-strip">
<?php foreach ($specialties as $specialtyRow):
    $specialty = trim((string) ($specialtyRow['Specialty'] ?? ''));
    if ($specialty === '') { continue; }
    $specialtyCopy = [
        'General Practice' => 'Check-ups, everyday concerns and ongoing care.',
        'Dental' => 'Routine dental care and advice for healthy teeth.',
        'Paediatrics' => 'Health care for infants, children and teenagers.',
        'Dermatology' => 'Assessment and care for skin concerns.',
        'Physiotherapy' => 'Movement and rehabilitation support after injury.',
    ][$specialty] ?? 'Talk with a doctor about your care needs.';
?>
            <a class="specialty-item" href="<?= e(url('/doctors.php?specialty=' . rawurlencode($specialty))) ?>">
                <img src="<?= e(url(specialty_image($specialty))) ?>" alt="" width="640" height="360" loading="lazy">
                <h3><?= e($specialty) ?></h3>
                <p><?= e($specialtyCopy) ?></p>
            </a>
<?php endforeach; ?>
        </div>
    </section>
</main>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
