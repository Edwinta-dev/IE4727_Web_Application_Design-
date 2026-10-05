<?php

declare(strict_types=1);

// Shipped deployments must never expose PHP diagnostics to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Local deployment credentials belong in config.local.php, which is ignored by git.
$localConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
// CLINIC_DB_NAME is set only by the test runner (tests/run.php) and dev tools;
// XAMPP never sets it, so the site always uses ie4727db.
defined('DB_NAME') || define('DB_NAME', getenv('CLINIC_DB_NAME') ?: 'ie4727db');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('APP_NAME') || define('APP_NAME', 'Clinic Appointment Portal');
defined('CLINIC_EMAIL') || define('CLINIC_EMAIL', 'clinic@example.local');
// Local demos can log notifications without waiting for an unavailable SMTP service.
defined('MAIL_DELIVERY') || define('MAIL_DELIVERY', getenv('MAIL_DELIVERY') ?: 'on');
defined('SLOT_MINUTES') || define('SLOT_MINUTES', 30);
defined('SCHEDULE_DAYS') || define('SCHEDULE_DAYS', 30);
// Doctors can generate and manage dates within one year; each generation spans at most SCHEDULE_DAYS.
defined('SCHEDULE_MANAGEMENT_DAYS') || define('SCHEDULE_MANAGEMENT_DAYS', 365);
defined('BROWSE_DAYS') || define('BROWSE_DAYS', 7);
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Singapore');

// A config.local.php that hard-codes DB_NAME must not silently redirect a test
// run (which asked for its own database) onto the application database.
$requestedDatabase = getenv('CLINIC_DB_NAME');
if (is_string($requestedDatabase) && $requestedDatabase !== '' && DB_NAME !== $requestedDatabase) {
    throw new RuntimeException('CLINIC_DB_NAME requests ' . $requestedDatabase . ' but config defines DB_NAME as ' . DB_NAME . '.');
}
unset($requestedDatabase);

// PHP and MariaDB must agree on "now"; db() applies the same offset to its session.
date_default_timezone_set(APP_TIMEZONE);
