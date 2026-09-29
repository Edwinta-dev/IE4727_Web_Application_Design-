<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';

$idInput = $_GET['id'] ?? '';
$doctorId = is_string($idInput) && ctype_digit($idInput) ? (int) $idInput : 0;
$doctor = $doctorId > 0 ? find_doctor($doctorId) : null;
if ($doctor === null) {
    render_not_found('The requested doctor profile could not be found.');
}

$image = trim((string) ($doctor['ImageURL'] ?? ''));
$image = $image === '' ? '/assets/img/clinic-logo.svg' : '/' . ltrim($image, '/');
$pageTitle = (string) $doctor['FullName'] . ' - Doctor Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main class="doctor-profile">
    <section class="page-intro">
        <h1><?= e((string) $doctor['FullName']) ?></h1>
        <p><?= e((string) ($doctor['Specialty'] ?? 'General practice')) ?></p>
        <img src="<?= e($image) ?>" alt="Portrait of <?= e((string) $doctor['FullName']) ?>" class="page-intro-image">
    </section>
    <section>
        <h2>About this doctor</h2>
        <p><?= e((string) ($doctor['WriteUp'] ?? 'Profile information is not available.')) ?></p>
        <p><strong>Qualifications:</strong> <?= e((string) ($doctor['Qualifications'] ?? 'Not listed')) ?></p>
        <p><strong>Languages:</strong> <?= e((string) ($doctor['Languages'] ?? 'Not listed')) ?></p>
        <p><a href="<?= e('/book.php?doctor=' . $doctorId) ?>">Book an appointment</a></p>
    </section>
</main>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
