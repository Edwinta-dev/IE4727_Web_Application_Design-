<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';

$specialties = all_specialties();
$specialtyOptions = array_values(array_filter(
    array_map(
        static fn (array $specialty): string => trim((string) ($specialty['Specialty'] ?? '')),
        $specialties
    ),
    static fn (string $specialty): bool => $specialty !== ''
));
$selectedSpecialty = isset($_GET['specialty']) && is_string($_GET['specialty'])
    ? trim($_GET['specialty'])
    : '';
$doctors = all_doctors($selectedSpecialty !== '' ? $selectedSpecialty : null);

$pageTitle = 'Find a Doctor';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main>
    <section class="page-intro public-page-intro">
        <div class="page-intro-copy">
            <h1>Find a doctor</h1>
            <p>Compare our doctors by specialty, experience and language before choosing a visit.</p>
        </div>
        <img src="<?= e(url('/assets/img/Doctor_Consulting.jpg')) ?>" width="600" height="400" loading="eager" decoding="async" alt="A doctor speaking with a patient" class="page-intro-image">
    </section>

<?php if ($specialtyOptions !== []): ?>
    <form class="specialty-filter" method="get" action="<?= e(url('/doctors.php')) ?>">
        <?= csrf_field() ?>
        <label for="specialty">Filter by specialty</label>
        <select id="specialty" name="specialty">
            <option value="">All specialties</option>
<?php foreach ($specialtyOptions as $value): ?>
            <option value="<?= e($value) ?>"<?= e($selectedSpecialty === $value ? ' selected' : '') ?>><?= e($value) ?></option>
<?php endforeach; ?>
        </select>
        <button type="submit">Filter doctors</button>
    </form>
<?php endif; ?>

    <div class="table-scroll" role="region" aria-label="Doctor directory" tabindex="0">
    <table class="doctor-directory">
        <caption>Clinic doctor directory</caption>
        <thead>
            <tr>
                <th scope="col">Photo</th>
                <th scope="col">Full name</th>
                <th scope="col">Specialty</th>
                <th scope="col">Qualifications</th>
                <th scope="col">Languages</th>
                <th scope="col">Next available</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
<?php if ($doctors === []): ?>
            <tr class="empty-state">
                <td colspan="7">No doctors match that specialty</td>
            </tr>
<?php else: ?>
<?php foreach ($doctors as $doctor):
    $doctorId = (int) ($doctor['DoctorID'] ?? 0);
    $image = trim((string) ($doctor['ImageURL'] ?? ''));
    $image = $image === '' ? '/assets/img/clinic-logo.svg' : '/' . ltrim($image, '/');
    $nextAvailable = $doctor['NextAvailable'] ?? null;
?>
            <tr>
                <td>
                    <img src="<?= e(url($image)) ?>" alt="Photo of <?= e((string) ($doctor['FullName'] ?? 'doctor')) ?>" class="doctor-photo">
                </td>
                <th scope="row"><a href="<?= e(url('/doctor.php?id=' . $doctorId)) ?>"><?= e((string) ($doctor['FullName'] ?? '')) ?></a></th>
                <td><?= e((string) ($doctor['Specialty'] ?? 'Not listed')) ?></td>
                <td><?= e((string) ($doctor['Qualifications'] ?? 'Not listed')) ?></td>
                <td><?= e((string) ($doctor['Languages'] ?? 'Not listed')) ?></td>
                <td><?= e($nextAvailable !== null ? fmt_date((string) $nextAvailable) . ' ' . fmt_time((string) $nextAvailable) : 'Not available') ?></td>
                <td><a href="<?= e(url('/book.php?doctor_id=' . $doctorId)) ?>">Book</a></td>
            </tr>
<?php endforeach; ?>
<?php endif; ?>
        </tbody>
    </table>
    </div>
</main>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
