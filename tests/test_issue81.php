<?php

declare(strict_types=1);

$pages = [
    'clinic-base/screens/doctors.php',
    'clinic-base/screens/doctor.php',
    'clinic-base/screens/book.php',
    'clinic-base/screens/register.php',
    'clinic-base/screens/patient/home.php',
    'clinic-base/screens/doctor/home.php',
    'clinic-base/screens/doctor/schedule.php',
    'clinic-base/screens/doctor/visit.php',
    'clinic-base/screens/admin/console.php',
    'clinic-base/screens/admin/outbox.php',
];

foreach ($pages as $pagePath) {
    $page = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . $pagePath);
    $usesExpectedIntro = $pagePath === 'clinic-base/screens/doctors.php'
        ? str_contains((string) $page, 'class="photo-banner doctor-directory-banner"')
        : str_contains((string) $page, 'class="page-intro');
    if ($page === false || !$usesExpectedIntro) {
        throw new RuntimeException($pagePath . ' must use the shared page-intro pattern');
    }
    $header = file_get_contents(dirname(__DIR__) . '/clinic-base/partials/header.php');
    $hasHeaderImage = str_contains($page, "'partials' . DIRECTORY_SEPARATOR . 'header.php'")
        && $header !== false && str_contains($header, '<img')
        && str_contains($header, '/assets/img/clinic-logo.svg');
    if (!str_contains($page, '/assets/img/') && !$hasHeaderImage) {
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
