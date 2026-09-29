<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

csrf_check();

$data = [
    'username_or_email' => trim((string) ($_POST['username_or_email'] ?? '')),
    'password' => (string) ($_POST['password'] ?? ''),
    'next' => (string) ($_POST['next'] ?? ''),
];
$errors = validate($data, [
    'username_or_email' => 'required|max:100',
    'password' => 'required|min:1',
]);

if ($errors !== []) {
    stash_old($data);
    stash_errors($errors);
    flash('Enter your username or email and password to sign in.', 'error');
    redirect(login_form_path($data['next']));
}

if (!attempt_login($data['username_or_email'], $data['password'])) {
    stash_old($data);
    flash('We could not sign you in with those details.', 'error');
    redirect(login_form_path($data['next']));
}

$user = current_user();
$roleHome = match ((string) ($user['role'] ?? '')) {
    'doctor' => '/doctor/home.php',
    'patient' => '/patient/home.php',
    'admin' => '/admin/console.php',
    default => '/index.php',
};
redirect($data['next'] !== '' ? login_return_path($data['next']) : $roleHome);

function login_return_path(string $next): string
{
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
        return '/index.php';
    }

    return $next;
}

function login_form_path(string $next): string
{
    return '/index.php' . ($next === '' ? '' : '?next=' . rawurlencode($next));
}
