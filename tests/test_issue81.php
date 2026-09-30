<?php

declare(strict_types=1);

$pages = [
    'clinic-base/doctors.php',
    'clinic-base/doctor.php',
    'clinic-base/book.php',
    'clinic-base/register.php',
    'clinic-base/patient/home.php',
    'clinic-base/doctor/home.php',
    'clinic-base/doctor/schedule.php',
    'clinic-base/doctor/visit.php',
    'clinic-base/admin/console.php',
    'clinic-base/admin/outbox.php',
];

foreach ($pages as $pagePath) {
    $page = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . $pagePath);
    if ($page === false || !str_contains($page, 'class="page-intro')) {
        throw new RuntimeException($pagePath . ' must use the shared page-intro pattern');
    }
    if (!str_contains($page, '/assets/img/')) {
        throw new RuntimeException($pagePath . ' must reference a local image');
    }
}

$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if ($styles === false || !str_contains($styles, '.page-intro-image') || !str_contains($styles, 'max-height: 200px')) {
    throw new RuntimeException('The shared intro image must have a compact fixed frame');
}

$helpers = file_get_contents(dirname(__DIR__) . '/clinic-base/lib/helpers.php');
if ($helpers === false || !str_contains($helpers, 'class="page-intro')) {
    throw new RuntimeException('Status pages must use the shared page-intro pattern');
}

echo "PASS: compact page intros use local images across all requested pages\n";
