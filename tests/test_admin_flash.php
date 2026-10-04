<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

foreach (['console', 'outbox'] as $page) {
    $source = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/admin/' . $page . '.php');
    assert_eq(substr_count($source, "'partials' . DIRECTORY_SEPARATOR . 'flash.php'"), 1, $page . ' renders shared flash once');
    assert_true(strpos($source, "'partials' . DIRECTORY_SEPARATOR . 'flash.php'") > strpos($source, '<main'), $page . ' renders flash inside main');
}

$console = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/admin/console.php');
foreach (['csrf_check()', 'require_admin()', 'account_by_id(', "flash('That account could not be found.", "flash('Choose a valid account", "redirect('/admin/console.php?'"] as $needle) {
    assert_contains($console, $needle, 'console feedback and PRG');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
}
start_session_once();
$_SESSION = [];
foreach ([['Saved <account>', 'success', 'status', 'Saved &lt;account&gt;'], ['Rejected <account>', 'error', 'alert', 'Rejected &lt;account&gt;']] as [$message, $type, $role, $escaped]) {
    flash($message, $type);
    ob_start();
    require dirname(__DIR__) . '/clinic-base/partials/flash.php';
    $html = (string) ob_get_clean();
    assert_contains($html, 'role="' . $role . '"', 'flash semantic role');
    assert_contains($html, $escaped, 'flash escaped text');
    assert_true(strpos($html, $message) === false, 'flash raw markup absent');
    ob_start();
    require dirname(__DIR__) . '/clinic-base/partials/flash.php';
    assert_eq((string) ob_get_clean(), '', 'flash consumed once');
}
session_write_close();
