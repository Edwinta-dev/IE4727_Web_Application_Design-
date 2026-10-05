<?php

declare(strict_types=1);

if (getenv('CLINIC_DB_NAME') !== 'ie4727db_test') {
    throw new RuntimeException('Outbox rendering requires ie4727db_test.');
}
define('MAIL_DELIVERY', $argv[1]);
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['id'] = 'admin';
require dirname(__DIR__, 2) . '/clinic-base/admin/outbox.php';
