<?php

declare(strict_types=1);

// The header logo links home for guests and patients only. Doctors and admins
// work from their own pages, so for them it is a plain, non-clickable brand.
require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

$renderHeader = static function (?string $role): string {
    $GLOBALS['_auth_current_user_cache'] = $role === null ? null : ['role' => $role, 'id' => 1];
    $pageTitle = 'Header test';
    ob_start();
    require dirname(__DIR__) . '/clinic-base/partials/header.php';
    return (string) ob_get_clean();
};

foreach ([null, 'patient'] as $role) {
    $html = $renderHeader($role);
    assert_true(strpos($html, '<a class="clinic-logo" href="/index.php">') !== false, ($role ?? 'guest') . ' logo links to the home page');
}
foreach (['doctor', 'admin'] as $role) {
    $html = $renderHeader($role);
    assert_true(strpos($html, 'class="clinic-logo"') !== false, "{$role} still sees the logo and brand name");
    assert_true(strpos($html, '<a class="clinic-logo"') === false, "{$role} logo is not a link");
    assert_true(strpos($html, 'Clinic Appointment Portal</span>') !== false, "{$role} sees the brand name");
}
// Do not leak the fake signed-in user into tests that run after this one.
unset($GLOBALS['_auth_current_user_cache']);
echo "OK: header logo links home only for guests and patients\n";
