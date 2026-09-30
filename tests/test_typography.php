<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tokens = (string) file_get_contents($root . '/clinic-base/assets/tokens.css');
$style = (string) file_get_contents($root . '/clinic-base/assets/style.css');

test('typography and surface system uses the clinic design tokens', static function () use ($tokens, $style): void {
    foreach ([
        '--c-surface-band:',
        '--c-surface-tile:',
        '--shadow-doctor-tile:',
        '--radius-control:',
        '--radius-tile:',
        '--radius-section:',
    ] as $token) {
        assert_contains($tokens, $token, 'missing surface token');
    }

    assert_contains($style, '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif', 'display font stack');
    assert_contains($style, '"Segoe UI", "Gill Sans", "Trebuchet MS", sans-serif', 'body font stack');
    assert_contains($style, 'font-size: clamp(2rem, 4vw, 2.5rem)', 'responsive heading scale');
    assert_contains($style, '.band {', 'full bleed section utility');
    assert_contains($style, '.split {', 'column utility');
    assert_contains($style, '@media (max-width: 56rem)', 'split mobile breakpoint');
    assert_contains($style, '.doctor-tile img {', 'sized doctor preview image');
    assert_contains($style, '.clinic-logo img {', 'sized clinic logo');
    assert_contains($style, '.site-nav ul {', 'unbulleted flexible navigation');
    assert_contains($style, 'prefers-reduced-motion: reduce', 'reduced motion support');
});
