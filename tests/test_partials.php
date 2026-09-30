<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
}
start_session_once();
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/doctors.php';

$pageTitle = 'Doctors';
ob_start();
require dirname(__DIR__) . '/clinic-base/partials/header.php';
require dirname(__DIR__) . '/clinic-base/partials/nav.php';
flash('Saved', 'success');
require dirname(__DIR__) . '/clinic-base/partials/flash.php';
require dirname(__DIR__) . '/clinic-base/partials/footer.php';
$rendered = ob_get_clean();

foreach (['<!doctype html>', '<title>Doctors</title>', 'assets/css/style.css', 'clinic-logo.svg',
    'aria-current="page"', '<div class="site-header-inner">', '<span class="site-brand">Clinic Appointment Portal</span>',
    '<div class="flash flash-success"', '&copy;', '</html>'] as $needle) {
    if (strpos($rendered, $needle) === false) {
        throw new RuntimeException("partials output is missing {$needle}");
    }
}

// The role-specific links must not be inferred from basename alone: doctor/home.php
// and patient/home.php are different pages.
$_SESSION = ['role' => 'admin', 'id' => 'admin'];
$GLOBALS['_auth_current_user_cache'] = ['role' => 'admin', 'id' => 'admin'];
$_SERVER['REQUEST_URI'] = '/admin/outbox.php';
ob_start();
require dirname(__DIR__) . '/clinic-base/partials/nav.php';
$adminNav = ob_get_clean();
if (strpos($adminNav, 'href="/admin/outbox.php" aria-current="page"') === false
    || strpos($adminNav, 'href="/admin/console.php" aria-current="page"') !== false) {
    throw new RuntimeException('admin navigation did not mark only the current page');
}
if (strpos($adminNav, 'class="nav-logout"') === false || strpos($adminNav, '</nav>') === false
    || strpos($adminNav, '</div>') === false || strpos($adminNav, '</header>') === false) {
    throw new RuntimeException('navigation did not complete the shared header or separate log out');
}

$styles = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
foreach ([
    '/\.site-header-inner\s*\{[^}]*display:\s*flex/s',
    '/\.site-nav ul\s*\{[^}]*display:\s*flex/s',
    '/\.site-nav ul\s*\{[^}]*list-style:\s*none/s',
    '/\.site-nav a\[aria-current="page"\]\s*\{[^}]*box-shadow:/s',
    '/\.site-nav a\s*\{[^}]*min-width:\s*2\.75rem/s',
    '/@media\s*\(max-width:\s*42rem\)/',
] as $requiredStyle) {
    if (preg_match($requiredStyle, $styles) !== 1) {
        throw new RuntimeException("header stylesheet is missing {$requiredStyle}");
    }
}

echo "PASS: partial chrome checks\n";
