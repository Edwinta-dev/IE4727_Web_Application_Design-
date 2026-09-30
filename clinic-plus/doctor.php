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
$specialty = (string) ($doctor['Specialty'] ?? 'General Practice');
$profile = specialty_profile($specialty);
$image = trim((string) ($doctor['ImageURL'] ?? ''));
$image = $image === '' ? '/assets/img/clinic-logo.svg' : '/' . ltrim($image, '/');
$name = (string) $doctor['FullName'];
$pageTitle = $name . ' - Doctor Profile';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="main-content" class="doctor-profile">
    <aside class="doctor-profile-identity" aria-label="Doctor details">
        <img src="<?= e(url($image)) ?>" width="560" height="700" loading="eager" decoding="async" alt="Portrait of <?= e($name) ?>" class="doctor-profile-portrait">
        <h1><?= e($name) ?></h1>
        <p class="doctor-profile-specialty"><?= e($specialty) ?></p>
        <dl class="doctor-profile-facts">
            <div><dt>Qualifications</dt><dd><?= e((string) ($doctor['Qualifications'] ?? 'Not listed')) ?></dd></div>
            <div><dt>Languages</dt><dd><?= e((string) ($doctor['Languages'] ?? 'Not listed')) ?></dd></div>
        </dl>
        <a class="button doctor-book-button" aria-label="Book an appointment with <?= e($name) ?>" href="<?= e(url('/book.php?doctor=' . $doctorId)) ?>">Book with <?= e($name) ?></a>
    </aside>

    <div class="doctor-profile-content">
        <section class="page-intro doctor-about">
            <h2>About <?= e($name) ?></h2>
            <?php foreach (preg_split('/\n\s*\n/', trim((string) ($doctor['WriteUp'] ?? 'Profile information is not available.'))) ?: [] as $paragraph): ?>
                <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
            <?php endforeach; ?>
        </section>

        <section class="doctor-specialty-section">
            <h2><?= e($specialty) ?> at our clinic</h2>
            <div class="doctor-specialty-content">
                <img src="<?= e(url(specialty_image($specialty))) ?>" width="1280" height="720" loading="eager" alt="<?= e($specialty) ?> care at our clinic">
                <div>
                    <p><?= e($profile['summary']) ?></p>
                    <h3>Who should book</h3>
                    <ul>
                        <?php foreach ($profile['who_should_book'] as $reason): ?>
                            <li><?= e($reason) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
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
                        <li class="slot free"><a href="<?= e(url($href)) ?>"><?= e(fmt_date($date)) ?> at <?= e(fmt_time((string) $slot['SlotTime'])) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><a class="full-schedule-link" href="<?= e(url('/book.php?doctor=' . $doctorId)) ?>">View the full schedule</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
