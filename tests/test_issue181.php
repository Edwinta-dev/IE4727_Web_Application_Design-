<?php

declare(strict_types=1);

$css = file_get_contents(__DIR__ . '/../clinic-base/assets/css/style.css');
$tokens = file_get_contents(__DIR__ . '/../clinic-base/assets/tokens.css');
assert_true($css !== false && $tokens !== false, 'focus stylesheets are readable');

assert_true(str_contains($tokens, '--c-focus-ring: var(--c-primary);'), 'focus ring uses shared primary token');
assert_true((bool) preg_match('/:is\(input, select, textarea, \.day-tab\):focus-visible\s*\{[^}]*outline:\s*0\.2rem solid var\(--c-focus-ring\)/s', $css),
    'inputs, selects, textareas and date tabs share one visible keyboard ring');
assert_true((bool) preg_match('/:is\(input, select, textarea, \.day-tab\):focus:not\(:focus-visible\)\s*\{[^}]*outline:\s*none/s', $css),
    'pointer focus does not keep the keyboard outline');

// Grep-style guard: a literal red or danger-token outline must never be added
// back to the form-control stylesheets, including inside a page-specific rule.
$redOutline = '/\boutline(?:-color)?\s*:\s*[^;{}]*(?:\bred\b|#(?:b91c1c|991b1b|dc2626|ef4444|ff0000|f00)\b|var\(--(?:c-danger|danger)\)|rgba?\(\s*255\s*,\s*0\s*,\s*0)/i';
assert_true((bool) preg_match($redOutline, 'input:focus-visible { outline: 3px solid red; }'),
    'red outline guard detects a regression');
foreach ([$css, $tokens, file_get_contents(__DIR__ . '/../clinic-base/assets/style.css')] as $sheet) {
    assert_true($sheet !== false, 'focus stylesheet is readable');
    $withoutComments = preg_replace('~/\*.*?\*/~s', '', $sheet);
    assert_true(!preg_match($redOutline, $withoutComments),
        'no raw red or danger outline declarations');
}

echo "OK: #181 shared focus ring and no raw red outline declarations\n";
