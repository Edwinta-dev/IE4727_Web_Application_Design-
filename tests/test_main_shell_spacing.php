<?php

declare(strict_types=1);

$style = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');

test('shared main shell starts page content flush beneath the header', static function () use ($style): void {
    assert_true(
        preg_match('/\bmain\s*\{[^}]*padding-block:\s*0\s+1\.5rem\s*;/s', $style) === 1,
        'main keeps bottom spacing without a body-colour strip above the first section'
    );
});
