<?php

declare(strict_types=1);

$index = file_get_contents(dirname(__DIR__) . '/clinic-base/index.php');
$login = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/login.php');

if ($index === false || $login === false) {
    throw new RuntimeException('homepage or login action could not be read');
}

foreach (['hero-band', 'featured-doctors', 'services', 'csrf_field()', 'name="username_or_email"',
    'name="password"', 'name="remember_me"', 'name="next"', "action=\"<?= e(url('/actions/login.php')) ?>\"", 'doctor.php?id=', 'text_excerpt(', 'class="doctor-tile"', 'class="doctor-tiles"', 'WriteUp'] as $needle) {
    if (strpos($index, $needle) === false) {
        throw new RuntimeException("homepage is missing {$needle}");
    }
}

foreach (['hero-copy-text', 'hero-actions', 'class="member-login"', 'register.php',
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

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
$excerpt = text_excerpt(str_repeat('care ', 35));
if (preg_match_all('/./us', $excerpt) > 141 || !str_ends_with($excerpt, '…') || str_ends_with(substr($excerpt, 0, -3), ' ')) {
    throw new RuntimeException('doctor write-up excerpt is not truncated at a word boundary');
}
