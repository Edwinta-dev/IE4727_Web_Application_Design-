<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';

$idInput = $_GET['id'] ?? '';
$doctorId = is_string($idInput) && ctype_digit($idInput) ? (int) $idInput : 0;
$doctor = $doctorId > 0 ? find_doctor($doctorId) : null;
if ($doctor === null) {
    render_not_found();
}

$slots = next_available($doctorId);
$image = trim((string) ($doctor['ImageURL'] ?? ''));
if ($image === '') {
    $image = '/assets/img/clinic-logo.svg';
} elseif (!preg_match('/^https?:\\/\\//i', $image) && $image[0] !== '/') {
    $image = '/' . $image;
}
$pageTitle = (string) $doctor['FullName'] . ' - Doctor Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main class="doctor-profile">
    <section class="doctor-profile-header">
        <img class="doctor-photo" src="<?= e($image) ?>" alt="Portrait of <?= e((string) $doctor['FullName']) ?>">
        <div>
            <h1><?= e((string) $doctor['FullName']) ?></h1>
            <p class="doctor-specialty"><?= e((string) ($doctor['Specialty'] ?? 'General practice')) ?></p>
        </div>
    </section>

    <section class="doctor-details">
        <h2>Background</h2>
        <p><?= e((string) ($doctor['WriteUp'] ?? 'Profile information is not available.')) ?></p>
        <p><strong>Qualifications:</strong> <?= e((string) ($doctor['Qualifications'] ?? 'Not listed')) ?></p>
        <p><strong>Languages:</strong> <?= e((string) ($doctor['Languages'] ?? 'Not listed')) ?></p>
    </section>

    <section class="doctor-availability">
        <h2>Next available appointments</h2>
        <?php if ($slots === []): ?>
            <p class="empty-state">This doctor has no availability in the current booking window.</p>
        <?php else: ?>
            <ul class="available-slots">
                <?php foreach ($slots as $slot):
                    $date = (string) $slot['SlotDate'];
                    $slotId = (int) $slot['slotID'];
                    $href = '/book.php?doctor=' . $doctorId . '&date=' . rawurlencode($date) . '&slot=' . $slotId;
                ?>
                    <li class="slot free">
                        <a href="<?= e($href) ?>">
                            <?= e(fmt_date($date)) ?> at <?= e(fmt_time((string) $slot['SlotTime'])) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a class="full-schedule-link" href="<?= e('/book.php?doctor=' . $doctorId) ?>">View the full schedule</a></p>
    </section>
</main>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
