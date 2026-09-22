<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/validate.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
    session_id('validate-test-' . bin2hex(random_bytes(4)));
}
start_session_once();

test('validation rules and old input', function (): void {
    $data = [
        'name' => '',
        'email' => 'not-an-email',
        'code' => 'ab',
        'amount' => 'nope',
        'date' => '2026-02-31',
        'time' => '25:00',
        'choice' => 'blue',
        'password' => 'secret',
        'password_confirmation' => 'different',
        'username' => 'bad value',
    ];
    $errors = validate($data, [
        'name' => 'required',
        'email' => 'required|email',
        'code' => 'min:3|max:4',
        'amount' => 'numeric',
        'date' => 'date',
        'time' => 'time',
        'choice' => 'in:red,green',
        'password_confirmation' => 'matches:password',
        'username' => 'regex:/^[a-z]+$/',
    ]);

    assert_count($errors, 9, 'all invalid fields return errors');
    assert_eq($errors['email'], 'Enter a valid email address');
    assert_contains($errors['password_confirmation'], 'match');
    assert_contains($errors['code'], '3 characters');

    assert_count(validate([
        'email' => 'person@example.com',
        'code' => 'abcd',
        'amount' => '12.5',
        'date' => '2026-09-22',
        'time' => '09:30',
        'choice' => 'red',
        'password' => 'one',
        'password_confirmation' => 'one',
        'username' => 'alice',
    ], [
        'email' => 'required|email', 'code' => 'min:3|max:4', 'amount' => 'numeric',
        'date' => 'date', 'time' => 'time', 'choice' => 'in:red,green',
        'password_confirmation' => 'matches:password', 'username' => 'regex:/^[a-z]+$/',
    ]), 0, 'valid rules pass');

    assert_eq(string_length('éé'), 2, 'minimum length counts characters');
});

test('stashing excludes passwords and helper reads once', function (): void {
    $_SESSION = [];

    stash_old(['email' => 'person@example.com', 'password' => 'secret', 'password_confirmation' => 'secret']);
    stash_errors(['email' => 'Enter your email address']);

    assert_eq(old('email'), 'person@example.com');
    assert_eq(old('email'), '');
    assert_eq(old('password'), '');
    assert_eq(errors_for('email'), 'Enter your email address');
    assert_eq(errors_for('email'), '');
});

echo "PASS: validation checks\n";
