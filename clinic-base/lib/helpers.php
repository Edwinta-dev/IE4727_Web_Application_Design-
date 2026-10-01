<?php

declare(strict_types=1);

/**
 * Escape a value for use in HTML.
 */
function e(mixed $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
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

    // e.g. "/doctor/home.php": the script's path inside the app folder.
    $inApp = str_replace('\\', '/', substr($file, strlen($root)));
    if ($inApp !== '' && strcasecmp(substr($script, -strlen($inApp)), $inApp) === 0) {
        $base = rtrim(substr($script, 0, -strlen($inApp)), '/');
    }

    return $base;
}

/**
 * Turn an app path such as "/book.php?doctor=1" into a URL that works under any base folder.
 */
function url(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return $path;
    }

    return base_path() . $path;
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
 * Practice photo for a doctor's specialty, shown on their profile page.
 */
function specialty_image(string $specialty): string
{
    $images = [
        'General Practice' => 'General_Practice_in_Action.jpg',
        'Dental' => 'Dentist_in_action.jpg',
        'Paediatrics' => 'Paediatrics_in_Action.jpg',
        'Dermatology' => 'Dermatologist_in_Action.jpg',
        'Physiotherapy' => 'Physiotherapist_in_Action.jpg',
    ];

    return '/assets/img/' . ($images[$specialty] ?? 'Clinic_Assisting_Elderly_woman.jpg');
}

/** @return array{summary: string, who_should_book: list<string>} */
function specialty_profile(string $specialty): array
{
    $profiles = [
        'General Practice' => [
            'summary' => 'Our general practice team provides first assessments, routine health reviews and ongoing care for common illnesses and long-term conditions. Your doctor can help coordinate referrals when specialist care is needed.',
            'who_should_book' => [
                'Adults seeking a check-up or advice about a new, non-emergency concern.',
                'People managing conditions such as high blood pressure, diabetes or asthma.',
                'Families arranging vaccinations and routine preventive care.',
                'Patients who need follow-up after a recent illness or treatment.',
            ],
        ],
        'Dental' => [
            'summary' => 'Dental appointments at our clinic cover preventive checks, common tooth and gum concerns, restorative treatment and orthodontic assessment. Your dentist will explain findings and options before treatment begins.',
            'who_should_book' => [
                'People due for a routine dental examination or cleaning.',
                'Patients with toothache, sensitivity or bleeding gums.',
                'Adults and young people seeking advice about alignment or bite concerns.',
                'Anyone who wants a review of a filling, crown or other previous dental work.',
            ],
        ],
        'Paediatrics' => [
            'summary' => 'Our paediatric service assesses children from infancy through adolescence, including everyday illness, growth and development, and ongoing childhood conditions. Parents and caregivers are included in each care plan.',
            'who_should_book' => [
                'Parents seeking an assessment for a child who is unwell.',
                'Families arranging growth, development or nutrition reviews.',
                'Children who need follow-up for asthma, allergies or another ongoing condition.',
                'Caregivers with questions about age-appropriate preventive care.',
            ],
        ],
        'Dermatology' => [
            'summary' => 'Our dermatology consultations assess skin, hair and nail concerns, including persistent rashes and changes that need a closer examination. The dermatologist will discuss diagnosis, treatment choices and skin care relevant to your routine.',
            'who_should_book' => [
                'People with recurring eczema, acne or an unexplained rash.',
                'Patients concerned about a changing mole or other skin growth.',
                'Anyone with persistent itching, pigmentation or nail changes.',
                'Patients needing review of a skin condition that has not settled with initial care.',
            ],
        ],
        'Physiotherapy' => [
            'summary' => 'Physiotherapy appointments assess pain, movement and recovery after injury or surgery. Your physiotherapist will agree practical exercises and activity changes with you, then review progress against your everyday goals.',
            'who_should_book' => [
                'People recovering from a sports injury or an operation.',
                'Patients with back, neck or joint pain affecting daily activity.',
                'Anyone working to regain strength, balance or range of movement.',
                'People who want guidance on returning safely to work, exercise or sport.',
            ],
        ],
    ];

    return $profiles[$specialty] ?? [
        'summary' => 'Our clinicians assess your concern, explain suitable care options and arrange follow-up or referral when needed.',
        'who_should_book' => [
            'Patients seeking an assessment related to this specialty.',
            'People who need follow-up for an existing concern.',
            'Anyone unsure which appointment best fits their needs.',
            'Patients seeking advice on next steps after an earlier assessment.',
        ],
    ];
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
function flash_render(): void
{
    start_session_once();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    if (!is_array($message) || !isset($message['message'])) {
        return;
    }

    $type = (string) ($message['type'] ?? 'info');
    echo '<div class="flash flash-' . e($type) . '" role="alert">'
        . e($message['message'])
        . '</div>';
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
