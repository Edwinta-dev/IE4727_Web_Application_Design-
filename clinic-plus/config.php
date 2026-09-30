<?php

declare(strict_types=1);

// Single source of truth for database and app settings (including
// config.local.php) is lib/config.php; this file adds admin and logging
// settings on top of it.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'config.php';

defined('ADMIN_USER') || define('ADMIN_USER', '');
defined('ADMIN_HASH') || define('ADMIN_HASH', '');
defined('APP_VERBOSE_ERRORS') || define('APP_VERBOSE_ERRORS', false);
defined('APP_LOG_FILE') || define('APP_LOG_FILE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug.log');
