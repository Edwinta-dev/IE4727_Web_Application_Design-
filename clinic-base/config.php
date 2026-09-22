<?php

declare(strict_types=1);

$localConfig = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'ie4727db');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('APP_NAME') || define('APP_NAME', 'Clinic Appointment Portal');
