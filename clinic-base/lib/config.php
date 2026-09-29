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
defined('DB_NAME') || define('DB_NAME', 'ie4727db');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('APP_NAME') || define('APP_NAME', 'Clinic Appointment Portal');
defined('CLINIC_EMAIL') || define('CLINIC_EMAIL', 'clinic@example.local');
defined('SLOT_MINUTES') || define('SLOT_MINUTES', 30);
defined('SCHEDULE_DAYS') || define('SCHEDULE_DAYS', 30);
defined('BROWSE_DAYS') || define('BROWSE_DAYS', 7);
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Singapore');

// PHP and MariaDB must agree on "now"; db() applies the same offset to its session.
date_default_timezone_set(APP_TIMEZONE);
