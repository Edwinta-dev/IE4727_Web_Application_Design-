<?php

declare(strict_types=1);

$index = file_get_contents(dirname(__DIR__) . '/clinic-base/index.php');
$login = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/login.php');

if ($index === false || $login === false) {
    throw new RuntimeException('homepage or login action could not be read');
}

foreach (['hero-band', 'featured-doctors', 'services', 'csrf_field()', 'name="username_or_email"',
    'name="password"', 'name="remember_me"', 'name="next"', "action=\"<?= e(url('/actions/login.php')) ?>\"", 'doctor.php?id='] as $needle) {
    if (strpos($index, $needle) === false) {
        throw new RuntimeException("homepage is missing {$needle}");
    }
}

foreach (['csrf_check()', 'validate(', 'attempt_login(', 'login_return_path', 'flash('] as $needle) {
    if (strpos($login, $needle) === false) {
        throw new RuntimeException("login action is missing {$needle}");
    }
}

echo "PASS: homepage and login structure checks\n";
