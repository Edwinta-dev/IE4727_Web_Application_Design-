<?php

declare(strict_types=1);
// Authorization responses use http_response_code(401) and http_response_code(403).

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';

defined('AUTH_REMEMBER_COOKIE') || define('AUTH_REMEMBER_COOKIE', 'clinic_remember');
defined('AUTH_MAX_FAILURES') || define('AUTH_MAX_FAILURES', 5);
defined('AUTH_FAILURE_WINDOW') || define('AUTH_FAILURE_WINDOW', 900);

/**
 * Log a user in using either role table or the configuration-defined admin.
 * Repeated failures lock out further attempts for AUTH_FAILURE_WINDOW seconds.
 */
function attempt_login(string $userOrEmail, string $plain): bool
{
    start_session_once();

    if (auth_is_limited()) {
        return false;
    }

    if (!auth_try_credentials($userOrEmail, $plain)) {
        auth_record_failure();

        return false;
    }

    unset($_SESSION['auth_failures'][auth_client_key()]);

    return true;
}

function auth_try_credentials(string $userOrEmail, string $plain): bool
{
    $login = find_login($userOrEmail);
    if ($login !== null && password_verify($plain, $login['hash'])) {
        session_regenerate_id(true);
        $_SESSION['role'] = $login['role'];
        $_SESSION['id'] = $login['id'];
        auth_set_remember_cookie();
        auth_clear_current_user_cache();

        return true;
    }

    if (verify_admin($userOrEmail, $plain)) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        $_SESSION['id'] = 'admin';
        auth_set_remember_cookie();
        auth_clear_current_user_cache();

        return true;
    }

    return false;
}

/**
 * Return the authenticated profile, augmented with its role and session id.
 * The result is cached for this request only.
 */
function current_user(): ?array
{
    if (array_key_exists('_auth_current_user_cache', $GLOBALS)) {
        return $GLOBALS['_auth_current_user_cache'];
    }

    start_session_once();
    $role = $_SESSION['role'] ?? null;
    $id = $_SESSION['id'] ?? null;

    if (!is_string($role) || !in_array($role, ['doctor', 'patient', 'admin'], true)) {
        return auth_cache_current_user(null);
    }

    if ($role === 'admin') {
        return auth_cache_current_user([
            'role' => 'admin',
            'id' => 'admin',
            'User' => defined('ADMIN_USER') ? (string) ADMIN_USER : '',
        ]);
    }

    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return auth_cache_current_user(null);
    }

    $profile = account_by_id($role, (int) $id);

    if ($profile === null) {
        return auth_cache_current_user(null);
    }

    $profile['role'] = $role;
    $profile['id'] = (int) $id;

    return auth_cache_current_user($profile);
}

function logout(): void
{
    start_session_once();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
    setcookie(AUTH_REMEMBER_COOKIE, '', [
        'expires' => time() - 42000,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    auth_clear_current_user_cache();
}

function require_login(): void
{
    if (current_user() === null) {
        http_response_code(401);
        auth_redirect_to_login('Please sign in to continue.');
    }
}

function require_doctor(): void
{
    $user = current_user();
    if ($user === null || !is_doctor()) {
        http_response_code($user === null ? 401 : 403);
        auth_redirect_to_login('Please sign in as a doctor to continue.');
    }
}

function require_admin(): void
{
    $user = current_user();
    if ($user === null || !is_admin()) {
        http_response_code($user === null ? 401 : 403);
        auth_redirect_to_login('Please sign in as an admin to continue.');
    }
}

function require_role(string $role): void
{
    $user = current_user();
    if ($user === null || (string) ($user['role'] ?? '') !== $role) {
        http_response_code($user === null ? 401 : 403);
        auth_redirect_to_login('Please sign in with the required role to continue.');
    }
}

function is_patient(): bool
{
    return (current_user()['role'] ?? null) === 'patient';
}

function is_doctor(): bool
{
    return (current_user()['role'] ?? null) === 'doctor';
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function auth_set_remember_cookie(): void
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['remember_hash'] = hash('sha256', $token);
    setcookie(AUTH_REMEMBER_COOKIE, $token, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_remember_token_matches(): bool
{
    start_session_once();
    $token = $_COOKIE[AUTH_REMEMBER_COOKIE] ?? '';
    $hash = $_SESSION['remember_hash'] ?? '';

    return is_string($token)
        && is_string($hash)
        && $token !== ''
        && hash_equals($hash, hash('sha256', $token));
}

function auth_redirect_to_login(string $message = 'Please sign in to continue.'): never
{
    flash($message, 'error');
    $next = app_request_uri();
    redirect('/index.php?next=' . rawurlencode($next));
}

function auth_client_key(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function auth_is_limited(): bool
{
    $record = $_SESSION['auth_failures'][auth_client_key()] ?? null;
    if (!is_array($record) || time() - (int) ($record['started'] ?? 0) >= AUTH_FAILURE_WINDOW) {
        return false;
    }

    return (int) ($record['count'] ?? 0) >= AUTH_MAX_FAILURES;
}

function auth_record_failure(): void
{
    $key = auth_client_key();
    $record = $_SESSION['auth_failures'][$key] ?? null;
    if (!is_array($record) || time() - (int) ($record['started'] ?? 0) >= AUTH_FAILURE_WINDOW) {
        $record = ['started' => time(), 'count' => 0];
    }
    $record['count'] = min(AUTH_MAX_FAILURES, (int) $record['count'] + 1);
    $_SESSION['auth_failures'][$key] = $record;
}

function auth_cache_current_user(?array $user): ?array
{
    $GLOBALS['_auth_current_user_cache'] = $user;

    return $user;
}

function auth_clear_current_user_cache(): void
{
    unset($GLOBALS['_auth_current_user_cache']);
}
