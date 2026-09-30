<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

$idInput = $_GET['id'] ?? '';
$doctorId = is_string($idInput) && ctype_digit($idInput) ? (int) $idInput : 0;
$doctor = $doctorId > 0 ? find_doctor($doctorId) : null;
if ($doctor === null) {
    render_not_found('The requested doctor profile could not be found.');
}

$slots = next_available($doctorId);
$image = trim((string) ($doctor['ImageURL'] ?? ''));
$image = $image === '' ? '/assets/img/clinic-logo.svg' : '/' . ltrim($image, '/');
$pageTitle = (string) $doctor['FullName'] . ' - Doctor Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="main-content" class="doctor-profile">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1><?= e((string) $doctor['FullName']) ?></h1>
            <p><?= e((string) ($doctor['Specialty'] ?? 'General practice')) ?> at our clinic</p>
        </div>
        <img src="<?= e(url($image)) ?>" width="600" height="400" loading="eager" decoding="async" alt="Portrait of <?= e((string) $doctor['FullName']) ?>" class="page-intro-image">
    </section>
    <section>
        <h2>About this doctor</h2>
        <p><?= e((string) ($doctor['WriteUp'] ?? 'Profile information is not available.')) ?></p>
        <p><strong>Qualifications:</strong> <?= e((string) ($doctor['Qualifications'] ?? 'Not listed')) ?></p>
        <p><strong>Languages:</strong> <?= e((string) ($doctor['Languages'] ?? 'Not listed')) ?></p>
        <p><a aria-label="Book an appointment with <?= e($doctor['FullName']) ?>" href="<?= e(url('/book.php?doctor=' . $doctorId)) ?>">Book an appointment</a></p>
        <figure class="specialty-photo">
            <img src="<?= e(url(specialty_image((string) ($doctor['Specialty'] ?? '')))) ?>" width="1280" height="853" loading="lazy" decoding="async" alt="<?= e((string) ($doctor['Specialty'] ?? 'General practice')) ?> care at our clinic">
        </figure>
    </section>
    <section class="doctor-availability">
        <h2>Next available appointments</h2>
        <?php if ($slots === []): ?>
            <p class="empty-state">This doctor has no availability in the current booking window.</p>
        <?php else: ?>
            <ul class="available-slots">
                <?php foreach ($slots as $slot):
                    $date = (string) $slot['SlotDate'];
                    $href = '/book.php?doctor=' . $doctorId . '&date=' . rawurlencode($date) . '&slot=' . (int) $slot['slotID'];
                ?>
                    <li class="slot free">
                        <a href="<?= e(url($href)) ?>"><?= e(fmt_date($date)) ?> at <?= e(fmt_time((string) $slot['SlotTime'])) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a class="full-schedule-link" href="<?= e(url('/book.php?doctor=' . $doctorId)) ?>">View the full schedule</a></p>
    </section>
</main>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
