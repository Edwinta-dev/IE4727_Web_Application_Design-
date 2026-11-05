<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

$renderNavigation = static function (?string $role, string $route): string {
    $GLOBALS['_auth_current_user_cache'] = $role === null ? null : ['role' => $role, 'id' => 1];
    $_SERVER['REQUEST_URI'] = $route;
    ob_start();
    require dirname(__DIR__) . '/clinic-base/partials/nav.php';
    return (string) ob_get_clean();
};

$navigationCases = [
    [null, '/', '/index.php'],
    [null, '/index.php', '/index.php'],
    [null, '/screens/doctors.php', '/screens/doctors.php'],
    [null, '/screens/book.php?doctor=1', '/screens/book.php'],
    [null, '/screens/register.php', '/screens/register.php'],
    [null, '/screens/doctor.php?id=1', null],
    [null, '/other/home.php', null],
    [null, '/unknown.php?next=/index.php', null],
    ['patient', '/screens/patient/home.php', '/screens/patient/home.php'],
    ['patient', '/screens/patient/home.php?date=2026-10-01', '/screens/patient/home.php'],
    ['patient', '/screens/doctors.php', '/screens/doctors.php'],
    ['patient', '/screens/book.php?doctor=1', '/screens/book.php'],
    ['patient', '/index.php', null],
    ['patient', '/screens/doctor.php?id=1', null],
    ['patient', '/other/patient/home.php', null],
    ['doctor', '/screens/doctor/home.php', '/screens/doctor/home.php'],
    ['doctor', '/screens/doctor/schedule.php?date=2026-10-01', '/screens/doctor/schedule.php'],
    ['doctor', '/screens/doctor/visit.php?appt=1', null],
    ['doctor', '/screens/patient/home.php', null],
    ['doctor', '/index.php', null],
    ['doctor', '/screens/doctor/schedule.php/unknown', null],
    ['admin', '/screens/admin/console.php', '/screens/admin/console.php'],
    ['admin', '/screens/admin/outbox.php', '/screens/admin/outbox.php'],
    ['admin', '/index.php', null],
    ['admin', '/unknown.php?next=/screens/admin/console.php', null],
];

foreach ($navigationCases as [$role, $route, $expected]) {
    $html = $renderNavigation($role, $route);
    preg_match_all('/<a href="([^"]+)" aria-current="page">/', $html, $matches);
    if ($matches[1] !== ($expected === null ? [] : [$expected])) {
        throw new RuntimeException("Wrong current tab for {$role} {$route}: " . implode(', ', $matches[1]));
    }
    if (substr_count($html, 'aria-current="page"') !== count($matches[1])) {
        throw new RuntimeException("Unexpected current state for {$role} {$route}");
    }
    if ($role === 'patient') {
        preg_match_all('/<a href="([^"]+)"/', $html, $patientLinks);
        if (count(array_unique($patientLinks[1])) !== count($patientLinks[1])
            || !str_contains($html, 'href="/screens/patient/home.php"')
            || !str_contains($html, 'href="/screens/book.php"')
            || str_contains($html, 'href="/screens/patient/home.php#appointments"')) {
            throw new RuntimeException('Patient navigation must have distinct appointments, doctor search and booking destinations');
        }
    }
    if ($role === 'admin') {
        preg_match_all('/<a href="([^"]+)"/', $html, $adminLinks);
        if ($adminLinks[1] !== ['/screens/admin/console.php', '/screens/admin/outbox.php', '/index.php?logout=1']) {
            throw new RuntimeException('Admin navigation must have console, outbox and logout destinations only');
        }
    }
    if ($role === 'doctor') {
        preg_match_all('/<a href="([^"]+)"/', $html, $doctorLinks);
        if ($doctorLinks[1] !== ['/screens/doctor/home.php', '/screens/doctor/schedule.php', '/index.php?logout=1']
            || !str_contains($html, '>Day board</a>')) {
            throw new RuntimeException('Doctor navigation must have day board, schedule and logout destinations only');
        }
    }
}

$registrationRole = 'patient';
$renderNavigation(null, '/screens/register.php');
if ($registrationRole !== 'patient') {
    throw new RuntimeException('Navigation changed the registration form role');
}

$styles = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if (preg_match('/\.site-nav a\[aria-current="page"\]::before\s*\{[^}]*box-shadow:/s', $styles) !== 1
    || preg_match('/\.site-nav a:hover::before,\s*\.site-nav a:focus-visible::before/s', $styles) !== 1
    || preg_match('/@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?\.site-nav a\[aria-current="page"\]::before\s*\{[^}]*animation:\s*none;/s', $styles) !== 1) {
    throw new RuntimeException('Current, preview, or reduced-motion tab style is missing');
}

echo "PASS: role-specific single current tab and registration isolation\n";
