<?php

declare(strict_types=1);

$index = file_get_contents(dirname(__DIR__) . '/clinic-base/index.php');
$login = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/login.php');

if ($index === false || $login === false) {
    throw new RuntimeException('homepage or login action could not be read');
}

foreach (['hero-band', 'featured-doctors', 'services', 'csrf_field()', 'name="username_or_email"',
    'name="password"', 'name="remember_me"', 'name="next"', "action=\"<?= e(url('/actions/login.php')) ?>\"", 'doctor.php?id=', 'text_excerpt(', 'class="doctor-tile"', 'class="doctor-tiles"', 'data-doctor-carousel', 'Previous featured doctors', 'Next featured doctors', 'featured-doctors.js', 'WriteUp'] as $needle) {
    if (strpos($index, $needle) === false) {
        throw new RuntimeException("homepage is missing {$needle}");
    }
}

foreach (['class="photo-banner"', 'class="photo-scrim"', 'class="photo-caption"', 'class="hero-subtitle"', 'hero-actions', 'class="member-login"', 'register.php',
    'class="specialty-strip"', 'specialty_image($specialty)', 'rawurlencode($specialty)',
    'class="specialty-item"', 'class="featured-doctors band"'] as $needle) {
    if (strpos($index, $needle) === false) {
        throw new RuntimeException("homepage is missing redesigned section {$needle}");
    }
}

foreach (['csrf_check()', 'validate(', 'attempt_login(', 'login_return_path', 'flash('] as $needle) {
    if (strpos($login, $needle) === false) {
        throw new RuntimeException("login action is missing {$needle}");
    }
}

echo "PASS: homepage and login structure checks\n";

$style = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if ($style === false) {
    throw new RuntimeException('shared stylesheet could not be read');
}
foreach (['.specialty-item:hover', 'transform: translateY(-0.15rem) scale(1.025);', '.specialty-item:focus-visible { transform: none; }', 'prefers-reduced-motion: reduce', '.specialty-item img', 'aspect-ratio: 16 / 9', 'border-radius: var(--radius-tile)'] as $needle) {
    if (strpos($style, $needle) === false) {
        throw new RuntimeException("specialty magnification behaviour is missing {$needle}");
    }
}

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
$excerpt = text_excerpt(str_repeat('care ', 35));
if (preg_match_all('/./us', $excerpt) > 141 || !str_ends_with($excerpt, '…') || str_ends_with(substr($excerpt, 0, -3), ' ')) {
    throw new RuntimeException('doctor write-up excerpt is not truncated at a word boundary');
}
