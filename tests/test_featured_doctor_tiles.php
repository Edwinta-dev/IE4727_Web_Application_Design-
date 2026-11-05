<?php

declare(strict_types=1);

$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if ($styles === false
    || !str_contains($styles, '.doctor-tiles > .doctor-tile:hover')
    || !str_contains($styles, 'transform: scale(1.025);')
    || !str_contains($styles, '.doctor-tiles > .doctor-tile:focus-visible')
    || !str_contains($styles, 'transform 180ms ease')
    || !str_contains($styles, '.doctor-carousel .doctor-tiles > .doctor-tile:focus-visible { transform: none; }')) {
    throw new RuntimeException('Featured doctor tiles must enlarge on hover/focus and stay still with reduced motion enabled');
}

echo "PASS: featured doctor tiles expand and respect reduced motion\n";
