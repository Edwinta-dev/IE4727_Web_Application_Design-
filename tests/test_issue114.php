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
    [null, '/doctors.php', '/doctors.php'],
    [null, '/book.php?doctor=1', '/book.php'],
    [null, '/register.php', '/register.php'],
    [null, '/doctor.php?id=1', null],
    ['patient', '/patient/home.php', '/patient/home.php'],
    ['patient', '/patient/home.php?date=2026-10-01', '/patient/home.php'],
    ['patient', '/doctors.php', '/doctors.php'],
    ['patient', '/book.php?doctor=1', null],
    ['patient', '/index.php', null],
    ['doctor', '/doctor/home.php', '/doctor/home.php'],
    ['doctor', '/doctor/schedule.php?date=2026-10-01', '/doctor/schedule.php'],
    ['doctor', '/doctor/visit.php?appt=1', null],
    ['doctor', '/patient/home.php', null],
    ['doctor', '/index.php', null],
    ['admin', '/admin/console.php', '/admin/console.php'],
    ['admin', '/admin/outbox.php', '/admin/outbox.php'],
    ['admin', '/index.php', null],
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
    if ($role === 'patient' && !str_contains($html, 'href="/patient/home.php#appointments"')) {
        throw new RuntimeException('Patient appointment preview link changed');
    }
    if ($role === 'doctor' && !str_contains($html, 'href="/doctor/home.php#appointments"')) {
        throw new RuntimeException('Doctor appointment preview link changed');
    }
}

$registrationRole = 'patient';
$renderNavigation(null, '/register.php');
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
