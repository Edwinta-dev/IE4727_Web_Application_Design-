<?php

declare(strict_types=1);

// Layout of clinic-base/: pages live in screens/, shared code in lib/ models/ partials/ actions/,
// and the root holds only the entry stub and configuration.
$base = dirname(__DIR__) . '/clinic-base/';

$rootPhp = array_map('basename', glob($base . '*.php') ?: []);
sort($rootPhp);
assert_eq($rootPhp, ['config.php', 'index.php'], 'only the entry stub and config.php sit at the root');
assert_true(is_file($base . 'config.local.php.example'), 'config example stays at the root');

foreach (['book', 'doctor', 'doctors', 'register'] as $moved) {
    assert_true(!is_file($base . $moved . '.php') && is_file($base . 'screens/' . $moved . '.php'), "{$moved}.php lives in screens/");
}
foreach (['doctor/home', 'doctor/schedule', 'doctor/visit', 'patient/home', 'admin/console', 'admin/outbox'] as $page) {
    assert_true(is_file($base . 'screens/' . $page . '.php'), "screens/{$page}.php exists");
}
foreach (['doctor', 'patient', 'admin'] as $oldDir) {
    assert_true(!is_dir($base . $oldDir), "{$oldDir}/ is no longer at the root");
}

// The stub does nothing but hand over to the real home page.
$stub = (string) file_get_contents($base . 'index.php');
assert_true(substr_count($stub, 'require ') === 1 && strpos($stub, "'/screens/index.php'") !== false, 'index.php only includes screens/index.php');
assert_true(strlen($stub) < 600, 'index.php stays a stub');

// Every page under screens/ reaches the shared code through the right number of dirname() levels.
foreach (glob($base . 'screens/*.php') ?: [] as $file) {
    assert_true(preg_match('/(?<!dirname\()__DIR__ \. DIRECTORY_SEPARATOR \. \'(?:lib|models|partials)\'/', (string) file_get_contents($file)) === 0, basename($file) . ' loads shared code from the parent folder');
}
foreach (glob($base . 'screens/*/*.php') ?: [] as $file) {
    $source = (string) file_get_contents($file);
    assert_true(preg_match('/dirname\(__DIR__\) \. DIRECTORY_SEPARATOR/', $source) === 0, str_replace($base, '', $file) . ' climbs two levels to reach shared code');
}

echo "OK: clinic-base layout (screens/ plus entry stub)\n";
