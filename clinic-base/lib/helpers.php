<?php

/*
Shared helpers used by every page: e() escaping, session start, url() and redirect(),
error pages, flash messages and remembered form input, date formatting, booking-window check,
and asset_url(), which versions CSS and JS links so browsers never serve a stale copy.
*/

declare(strict_types=1);

/**
 * Escape a value for use in HTML.
 */
function e(mixed $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Explain why a requested patient booking date is outside the selectable window. */
function booking_date_error(mixed $date, DateTimeImmutable $today, int $days): ?string
{
    if (!is_string($date)) {
        return 'Enter a valid date.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
        return 'Enter a valid date in YYYY-MM-DD format.';
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        return 'Enter a valid date in YYYY-MM-DD format.';
    }
    if ($parsed < $today) {
        return 'Past dates cannot be booked.';
    }
    if ($parsed > $today->modify('+' . ($days - 1) . ' days')) {
        return 'Choose a date within the next ' . $days . ' days.';
    }

    return null;
}

/** Return a word-boundary excerpt for compact doctor previews. */
function text_excerpt(string $text, int $limit = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($limit < 1 || preg_match_all('/./us', $text) <= $limit) {
        return $text;
    }

    preg_match_all('/./us', $text, $characters);
    $excerpt = implode('', array_slice($characters[0], 0, $limit));
    $boundary = strrpos($excerpt, ' ');
    if ($boundary !== false) {
        $excerpt = substr($excerpt, 0, $boundary);
    }

    return rtrim($excerpt) . '…';
}

/**
 * Start the session once so library includes can safely be repeated.
 */
function start_session_once(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * URL prefix of the app folder: "/IE4727_Web_Application_Design-/clinic-base" when served from a
 * subfolder of XAMPP htdocs, "" when the app folder is the web root. Worked out per request from
 * the running script, so a fresh XAMPP install needs no configuration.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $base = '';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = realpath(dirname(__DIR__));
    if ($script === '' || $file === false || $root === false || !str_starts_with($file, $root)) {
        return $base;
    }

    // e.g. "/screens/doctor/home.php": the script's path inside the app folder.
    $inApp = str_replace('\\', '/', substr($file, strlen($root)));
    if ($inApp !== '' && strcasecmp(substr($script, -strlen($inApp)), $inApp) === 0) {
        $base = rtrim(substr($script, 0, -strlen($inApp)), '/');
    }

    return $base;
}

/**
 * Turn an app path such as "/screens/book.php?doctor=1" into a URL that works under any base folder.
 */
function url(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return $path;
    }

    return base_path() . $path;
}

/**
 * URL of a stylesheet or script with its last-modified time appended (?v=...).
 * Browsers keep serving an old copy of an unversioned file, so a CSS or JS change
 * would not reach people who had already visited. A new file gets a new URL.
 */
function asset_url(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $modified = is_file($file) ? filemtime($file) : false;

    return url($path) . ($modified === false ? '' : '?v=' . $modified);
}

/**
 * The current request URI as an app path, with the base folder removed.
 */
function app_request_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = base_path();
    if ($base !== '' && strncasecmp($uri, $base, strlen($base)) === 0) {
        $uri = substr($uri, strlen($base));
    }

    return $uri === '' ? '/' : $uri;
}

/**
 * Redirect to an app path and stop processing the current request.
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Render a user-facing error page with the normal site chrome.
 */
function render_status_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    $pageTitle = $title . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Clinic Appointment Portal');
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
    echo '<main class="status-page">'
        . '<section class="page-intro">'
        . '<div class="page-intro-copy"><h1><img src="' . e(url('/assets/img/clinic-logo.svg')) . '" width="40" height="40" loading="eager" decoding="async" alt="" class="page-intro-mark">' . e($title) . '</h1>'
        . '<p>' . e($message) . '</p></div>'
        . '<p><a href="' . e(url('/index.php')) . '">Return to the home page</a></p>'
        . '</section></main>';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
    exit;
}

function render_not_found(string $message = 'The page or record you requested could not be found.'): never
{
    render_status_page(404, 'Page not found', $message);
}

/**
 * Return an escaped value saved for repopulating a form.
 */
function old(string $key, mixed $default = ''): mixed
{
    start_session_once();
    $value = $_SESSION['old'][$key] ?? $default;
    unset($_SESSION['old'][$key]);
    if (isset($_SESSION['old']) && $_SESSION['old'] === []) {
        unset($_SESSION['old']);
    }

    return $value;
}

/**
 * Save a one-time message for the next response.
 */
function flash(string $msg, string $type = 'info'): void
{
    start_session_once();
    $_SESSION['flash'] = [
        'message' => $msg,
        'type' => $type,
    ];
}

/**
 * Render and consume the saved one-time message.
 */
function flash_booking_confirmation(string $doctor, string $date, string $time, int $appointmentId): void
{
    start_session_once();
    $_SESSION['flash'] = ['type' => 'success', 'booking' => [
        'doctor' => $doctor, 'date' => $date, 'time' => $time, 'appointment_id' => $appointmentId,
    ]];
}

function flash_render(): bool
{
    start_session_once();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    if (!is_array($message) || (!isset($message['message']) && !isset($message['booking']))) {
        return false;
    }

    $type = (string) ($message['type'] ?? 'info');
    $role = $type === 'error' ? 'alert' : 'status';
    echo '<div class="flash flash-' . e($type) . '" role="' . e($role) . '">';
    if (isset($message['booking']) && is_array($message['booking'])) {
        $booking = $message['booking'];
        echo 'Your appointment with ' . e((string) ($booking['doctor'] ?? ''))
            . ' on ' . e((string) ($booking['date'] ?? ''))
            . ' at ' . e((string) ($booking['time'] ?? ''))
            . ' has been booked. <a href="' . e(url('/screens/patient/home.php#appointment-' . (int) ($booking['appointment_id'] ?? 0))) . '">View my appointment</a>';
        echo '</div>';
        return true;
    }
    echo e((string) ($message['message'] ?? '')) . '</div>';
    return false;
}

/**
 * Return the validation error saved for one field.
 */
function errors_for(string $field): string
{
    start_session_once();
    $error = $_SESSION['errors'][$field] ?? '';
    unset($_SESSION['errors'][$field]);
    if (isset($_SESSION['errors']) && $_SESSION['errors'] === []) {
        unset($_SESSION['errors']);
    }

    return is_scalar($error) ? (string) $error : '';
}

/** Consume and return all validation errors for a form summary. @return array<string, string> */
function errors_all(): array
{
    start_session_once();
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);

    return is_array($errors) ? array_filter($errors, 'is_string') : [];
}

/**
 * Format a date value for display.
 */
function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('d M Y', $timestamp);
}

/**
 * Format a time value for display.
 */
function fmt_time(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('g:i A', $timestamp);
}
