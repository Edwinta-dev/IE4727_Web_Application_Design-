<?php

declare(strict_types=1);

// Browser acceptance: node tools/ui/outbox-layout.test.mjs
// Render in separate processes to exercise both immutable delivery settings.
foreach (['off', 'on'] as $mode) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/outbox_render.php')
        . ' ' . escapeshellarg($mode) . ' 2>&1', $output, $exit);
    assert_eq($exit, 0, 'outbox render: ' . implode("\n", $output));
    $html = implode("\n", $output);
    assert_eq(str_contains($html, 'Logged - local mail delivery not configured'), $mode === 'off', 'legend follows effective mail configuration');
    assert_contains($html, 'aria-label="Message log table" tabindex="0"', 'keyboard-accessible mobile table');
    assert_contains($html, '<time datetime="', 'full timestamp retained');
    assert_true(preg_match('/<span>\d{2} [A-Z][a-z]{2} \d{4}<\/span><span>\d{2}:\d{2}<\/span>/', $html) === 1, 'compact readable date and time');
    assert_eq(substr_count($html, '<th scope="col">'), 4, 'body disclosure shares subject column');
    assert_contains($html, '<details class="notification-details">', 'native no-JS body disclosure');
}
echo "OK: #150 delivery legend on/off, compact dates, accessible table and subject disclosure\n";
