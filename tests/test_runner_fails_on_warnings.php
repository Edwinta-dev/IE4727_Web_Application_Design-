<?php

declare(strict_types=1);

test('test runner fails on PHP warnings and notices', static function (): void {
    $runner = __DIR__ . '/run.php';
    foreach (['warning', 'notice'] as $kind) {
        putenv('IE4727_TEST_DIAGNOSTIC=' . $kind);
        $output = [];
        try {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' fixtures/issue168_warning.php 2>&1', $output, $status);
        } finally {
            putenv('IE4727_TEST_DIAGNOSTIC');
        }

        assert_eq($status, 1, $kind . ' must fail the runner');
        assert_contains(implode("\n", $output), 'issue 168 deliberate ' . $kind);
    }
});
