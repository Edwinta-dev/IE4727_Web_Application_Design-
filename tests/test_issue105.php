<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$stylesheet = file_get_contents($root . '/clinic-base/assets/css/style.css');
$sharedStylesheet = file_get_contents($root . '/clinic-base/assets/style.css');
$tokens = file_get_contents($root . '/clinic-base/assets/tokens.css');

if ($stylesheet === false || $sharedStylesheet === false || $tokens === false) {
    throw new RuntimeException('Dashboard shell styles could not be read');
}
if (preg_match('/#appointments\s*\{[^}]*\bwidth\s*:/i', $stylesheet) === 1) {
    throw new RuntimeException('Dashboard content must inherit the shared main width');
}
if (preg_match('/\.site-header-inner,\s*main,\s*\.site-footer/', $sharedStylesheet) !== 1 || !str_contains($sharedStylesheet, 'width: var(--page-width);')) {
    throw new RuntimeException('Main must use the shared page-width token');
}
if (!str_contains($tokens, '--page-width: calc(100% - 2rem);') || !str_contains($tokens, '--page-width: 80vw;')) {
    throw new RuntimeException('Shared page-width token must retain its mobile gutters and desktop width');
}

echo "PASS: patient and doctor dashboards inherit the shared page width\n";
