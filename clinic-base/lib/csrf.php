<?php

/*
One CSRF token per session. csrf_field() goes in every POST form;
csrf_check() runs first in every POST handler and shows a 419 page on mismatch.
*/

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';

/**
 * Return the request's CSRF token, creating it once per session.
 */
function csrf_token(): string
{
    start_session_once();

    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/**
 * Render the hidden CSRF field included in every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'
        . e(csrf_token())
        . '">';
}

/**
 * Reject a request whose submitted token does not match the session token.
 */
function csrf_check(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    $submitted = is_string($submitted) ? $submitted : '';

    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        error_log('CSRF token validation failed');
        render_status_page(419, 'Session expired', 'Your form could not be verified. Please go back and try again.');
    }
}
