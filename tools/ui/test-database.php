<?php

declare(strict_types=1);

// Test tooling only: check before loading config, and again before any DB include.
function ui_test_database_guard(?string $resolved = null): void
{
    if (getenv('CLINIC_DB_NAME') !== 'ie4727db_test'
        || ($resolved !== null && $resolved !== 'ie4727db_test')) {
        throw new RuntimeException('UI mutations require explicit, resolved ie4727db_test.');
    }
}

ui_test_database_guard();
require_once dirname(__DIR__, 2) . '/clinic-base/lib/config.php';
ui_test_database_guard(DB_NAME);
