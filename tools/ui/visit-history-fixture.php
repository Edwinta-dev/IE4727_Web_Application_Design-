<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/tests/lib/visit-history-fixture.php';

if (($argv[1] ?? '') === 'seed') {
    echo json_encode(seed_visit_history(), JSON_THROW_ON_ERROR);
} elseif (($argv[1] ?? '') === 'cleanup') {
    cleanup_visit_history(json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
} else {
    throw new InvalidArgumentException('Unknown fixture action.');
}
