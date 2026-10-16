<?php

declare(strict_types=1);
// Authorization responses use http_response_code(401) and http_response_code(403).

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';

defined('AUTH_MAX_FAILURES') || define('AUTH_MAX_FAILURES', 5);
defined('AUTH_FAILURE_WINDOW') || define('AUTH_FAILURE_WINDOW', 900);
// Looser cap across all usernames from one client, so spraying many accounts is still throttled.
defined('AUTH_MAX_CLIENT_FAILURES') || define('AUTH_MAX_CLIENT_FAILURES', 20);

/**
 * Log a user in using either role table or the configuration-defined admin.
 * Repeated failures lock out further attempts for AUTH_FAILURE_WINDOW seconds.
 */
function attempt_login(string $userOrEmail, string $plain): bool
{
    start_session_once();

    if (auth_is_limited($userOrEmail)) {
        return false;
    }

    if (!auth_try_credentials($userOrEmail, $plain)) {
        auth_record_failure($userOrEmail);

        return false;
    }

    auth_clear_failures($userOrEmail);

    return true;
}

function auth_try_credentials(string $userOrEmail, string $plain): bool
{
    $login = find_login($userOrEmail);
    if ($login !== null && password_verify($plain, $login['hash'])) {
        session_regenerate_id(true);
        $_SESSION['role'] = $login['role'];
        $_SESSION['id'] = $login['id'];
        auth_clear_current_user_cache();

        return true;
    }

    if (verify_admin($userOrEmail, $plain)) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        $_SESSION['id'] = 'admin';
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
    // Clear any legacy remember cookie issued before the checkbox was removed.
    setcookie('clinic_remember', '', [
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

function auth_redirect_to_login(string $message = 'Please sign in to continue.'): never
{
    flash($message, 'error');
    $next = app_request_uri();
    redirect('/index.php?next=' . rawurlencode($next));
}

function auth_client_key(): string
{
    return hash('sha256', 'client|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/** One counter per client and login name, so one person's typos never lock out other accounts. */
function auth_user_key(string $userOrEmail): string
{
    return hash('sha256', 'user|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower(trim($userOrEmail)));
}

function auth_record_is_live(mixed $record): bool
{
    return is_array($record) && time() - (int) ($record['started'] ?? 0) < AUTH_FAILURE_WINDOW;
}

/** Read-only check: shared lock, never rewrites the store. A store error allows the attempt. */
function auth_is_limited(string $userOrEmail): bool
{
    $limited = auth_failure_store_read(static function (array $entries) use ($userOrEmail): bool {
        $user = $entries[auth_user_key($userOrEmail)] ?? null;
        $client = $entries[auth_client_key()] ?? null;

        return (auth_record_is_live($user) && (int) ($user['count'] ?? 0) >= AUTH_MAX_FAILURES)
            || (auth_record_is_live($client) && (int) ($client['count'] ?? 0) >= AUTH_MAX_CLIENT_FAILURES);
    });

    return $limited === true;
}

function auth_record_failure(string $userOrEmail): void
{
    auth_failure_store_change(static function (array &$entries) use ($userOrEmail): void {
        foreach ([[auth_user_key($userOrEmail), AUTH_MAX_FAILURES], [auth_client_key(), AUTH_MAX_CLIENT_FAILURES]] as [$key, $cap]) {
            $record = $entries[$key] ?? null;
            if (!auth_record_is_live($record)) {
                $record = ['started' => time(), 'count' => 0, 'client' => auth_client_key()];
            }
            $record['count'] = min($cap, (int) $record['count'] + 1);
            $entries[$key] = $record;
        }
    });
}

/**
 * Store file for this OS user. Apache and the CLI test runner usually run as different
 * users; a shared 0600 file created by one would be unreadable by the other.
 */
function auth_failure_store_path(): string
{
    $owner = function_exists('posix_geteuid') ? (string) posix_geteuid() : (string) (getenv('USERNAME') ?: getenv('USER') ?: 'shared');
    $owner = preg_replace('/[^A-Za-z0-9_-]/', '', $owner) ?: 'shared';

    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ie4727-clinic-auth-failures-' . $owner . '.store';
}

/** @return array<string, array<string, mixed>> */
function auth_failure_store_decode(string|false $contents): array
{
    $entries = is_string($contents) && $contents !== '' ? @unserialize($contents, ['allowed_classes' => false]) : [];

    return is_array($entries) ? $entries : [];
}

/** Read the store under a shared lock. Returns null (and logs) on any store error; a missing store is empty. */
function auth_failure_store_read(callable $read): mixed
{
    $path = auth_failure_store_path();
    if (!is_file($path)) {
        return $read([]);
    }
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        error_log('auth: login throttle store unreadable; allowing the attempt: ' . $path);
        return null;
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            error_log('auth: login throttle store could not be locked; allowing the attempt: ' . $path);
            return null;
        }
        $entries = auth_failure_store_decode(stream_get_contents($handle));
        flock($handle, LOCK_UN);

        return $read($entries);
    } finally {
        fclose($handle);
    }
}

/** Apply one atomic change under an exclusive lock. Only hashed keys are persisted. Store errors are logged, never fatal. */
function auth_failure_store_change(callable $change): mixed
{
    $path = auth_failure_store_path();
    $handle = @fopen($path, 'c+b');
    if ($handle === false) {
        error_log('auth: login throttle store not writable; failure not recorded: ' . $path);
        return null;
    }
    @chmod($path, 0600);
    try {
        if (!flock($handle, LOCK_EX)) {
            error_log('auth: login throttle store could not be locked; failure not recorded: ' . $path);
            return null;
        }
        rewind($handle);
        $entries = auth_failure_store_decode(stream_get_contents($handle));
        foreach ($entries as $key => $record) {
            if (!is_string($key) || !auth_record_is_live($record)) {
                unset($entries[$key]);
            }
        }
        $result = $change($entries);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, serialize($entries)) === false || !fflush($handle)) {
            error_log('auth: login throttle store write failed: ' . $path);
            return null;
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Clear the counter for one login name; with no name, clear every counter from this client. */
function auth_clear_failures(string $userOrEmail = ''): void
{
    auth_failure_store_change(static function (array &$entries) use ($userOrEmail): void {
        if ($userOrEmail !== '') {
            unset($entries[auth_user_key($userOrEmail)]);
            return;
        }
        foreach ($entries as $key => $record) {
            if ($key === auth_client_key() || (is_array($record) && ($record['client'] ?? null) === auth_client_key())) {
                unset($entries[$key]);
            }
        }
    });
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
