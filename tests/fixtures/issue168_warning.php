<?php

declare(strict_types=1);

$kind = getenv('IE4727_TEST_DIAGNOSTIC') === 'notice' ? 'notice' : 'warning';
trigger_error('issue 168 deliberate ' . $kind, $kind === 'notice' ? E_USER_NOTICE : E_USER_WARNING);
