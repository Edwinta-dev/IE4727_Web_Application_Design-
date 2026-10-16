<?php

declare(strict_types=1);

// #198: page images ship at display size. The largest existing photo is about
// 120 KB, so 250 KB leaves room while still catching an unoptimised export.
const PAGE_IMAGE_MAX_BYTES = 250 * 1024;

$imageDir = dirname(__DIR__) . '/clinic-base/assets/img';
foreach (glob($imageDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [] as $image) {
    assert_true(filesize($image) <= PAGE_IMAGE_MAX_BYTES,
        basename($image) . ' is ' . filesize($image) . ' bytes; export it at display size (max ' . PAGE_IMAGE_MAX_BYTES . ')');
}
$index = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/index.php');
assert_contains($index, "/assets/img/about-clinic-consultation.jpg')) ?>\" width=\"960\" height=\"720\"",
    'About Us uses the compressed photo with its real dimensions');
echo "OK: #198 page images within size budget\n";
