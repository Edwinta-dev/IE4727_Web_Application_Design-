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
    <section class="hero-band">
        <div class="hero-copy">
            <p class="eyebrow">Thoughtful care, close to home</p>
            <h1>Clinic Appointment Portal</h1>
            <p>Find trusted care, choose a convenient time and keep your health journey moving with one welcoming clinic team.</p>
        </div>
        <img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal" class="hero-image">
    </section>

    <section class="member-login" aria-labelledby="member-login-heading">
<?php if ($user !== null): ?>
        <h2 id="member-login-heading">Welcome back, <?= e((string) ($user['FullName'] ?? $user['User'] ?? 'member')) ?>.</h2>
        <p>Your clinic portal is ready when you are.</p>
        <a class="button" href="<?= e($home ?? '/index.php') ?>">Go to your home</a>
<?php else: ?>
        <h2 id="member-login-heading">Member login</h2>
        <p>Sign in to manage appointments and stay connected with your care team.</p>
        <form method="post" action="/actions/login.php">
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
            </p>
            <button type="submit">Sign in</button>
        </form>
<?php endif; ?>
    </section>

    <section class="featured-doctors" aria-labelledby="featured-doctors-heading">
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
                <img src="<?= e($image) ?>" alt="Portrait of <?= e((string) $doctor['FullName']) ?>">
                <h3><?= e((string) $doctor['FullName']) ?></h3>
<?php if (trim((string) ($doctor['Specialty'] ?? '')) !== ''): ?>
                <p class="doctor-specialty"><?= e((string) $doctor['Specialty']) ?></p>
<?php endif; ?>
                <a href="<?= e('/doctor.php?id=' . (int) $doctor['DoctorID']) ?>">View doctor profile</a>
            </article>
<?php endforeach; ?>
<?php endif; ?>
        </div>
    </section>

    <section class="services" aria-labelledby="services-heading">
        <h2 id="services-heading">Care for the whole you</h2>
        <p>Our general practice team helps with everyday health concerns, preventive check-ups and long-term conditions, creating a clear plan that fits your life.</p>
        <p>From children’s health and dental care to dermatology and physiotherapy, our specialists listen carefully, explain your options and work with you towards practical, lasting wellbeing.</p>
    </section>
</main>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
