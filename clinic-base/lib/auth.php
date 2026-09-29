<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

const AUTH_MAX_FAILURES = 5;
const AUTH_FAILURE_WINDOW = 900;

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_name('CLINICSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_client_key(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function auth_is_limited(): bool
{
    auth_start_session();
    $now = time();
    $record = $_SESSION['auth_failures'][auth_client_key()] ?? null;
    if (!is_array($record) || $now - (int) ($record['started'] ?? 0) >= AUTH_FAILURE_WINDOW) {
        return false;
    }
    return (int) ($record['count'] ?? 0) >= AUTH_MAX_FAILURES;
}

function auth_record_failure(): void
{
    auth_start_session();
    $key = auth_client_key();
    $now = time();
    $record = $_SESSION['auth_failures'][$key] ?? [];
    if (!is_array($record) || $now - (int) ($record['started'] ?? 0) >= AUTH_FAILURE_WINDOW) {
        $record = ['started' => $now, 'count' => 0];
    }
    $record['count'] = min(AUTH_MAX_FAILURES, (int) $record['count'] + 1);
    $_SESSION['auth_failures'][$key] = $record;
}

/** @return array{ok: bool, error: string} */
function authenticate(string $username, string $password): array
{
    auth_start_session();
    $generic = 'Invalid username or password.';
    if (auth_is_limited()) {
        return ['ok' => false, 'error' => $generic];
    }

    $account = q_one('SELECT `PatientID` AS `id`, `FullName`, `User`, `HashPass`, \'patient\' AS `role` FROM `patient` WHERE `User` = :username LIMIT 1', ['username' => $username]);
    if ($account === null) {
        $account = q_one('SELECT `DoctorID` AS `id`, `FullName`, `User`, `HashPass`, \'doctor\' AS `role` FROM `doctor` WHERE `User` = :username LIMIT 1', ['username' => $username]);
    }
    $valid = $account !== null && password_verify($password, (string) $account['HashPass']);
    if (!$valid && hash_equals((string) ADMIN_USER, $username) && ADMIN_HASH !== '' && password_verify($password, ADMIN_HASH)) {
        $account = ['id' => 0, 'FullName' => 'Administrator', 'User' => ADMIN_USER, 'role' => 'admin'];
        $valid = true;
    }
    if (!$valid) {
        auth_record_failure();
        return ['ok' => false, 'error' => $generic];
    }

    session_regenerate_id(true);
    unset($_SESSION['auth_failures'][auth_client_key()]);
    $_SESSION['user'] = ['id' => (int) $account['id'], 'name' => (string) $account['FullName'], 'username' => (string) $account['User'], 'role' => (string) $account['role']];
    return ['ok' => true, 'error' => ''];
}

function logout_user(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    auth_start_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): void
{
    if (current_user() === null) {
        http_response_code(401);
        exit('Authentication required.');
    }
}

function require_role(string $role): void
{
    $user = current_user();
    if ($user === null || ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

