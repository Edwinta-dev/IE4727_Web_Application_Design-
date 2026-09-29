<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';

/**
 * Validate submitted form data using pipe-separated or array rules.
 *
 * The first error for each field is returned so a form can display one
 * actionable message beside each input.
 *
 * @param array<string, mixed> $data
 * @param array<string, string|array<int, string>> $rules
 * @return array<string, string>
 */
function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? null;
        $parsedRules = is_array($fieldRules)
            ? $fieldRules
            : explode('|', $fieldRules);

        foreach ($parsedRules as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '') {
                continue;
            }

            [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
            $name = strtolower($name);

            if ($name !== 'required' && is_blank_value($value)) {
                continue;
            }

            $failed = match ($name) {
                'required' => is_blank_value($value),
                'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false,
                'min' => string_length((string) $value) < (int) $argument,
                'max' => string_length((string) $value) > (int) $argument,
                'numeric' => !is_numeric($value),
                'date' => !valid_date((string) $value),
                'time' => !valid_time((string) $value),
                'in' => !in_allowed_values((string) $value, (string) $argument),
                'matches' => (string) $value !== (string) ($data[(string) $argument] ?? ''),
                'regex' => !valid_regex((string) $argument, (string) $value),
                'unique_email' => email_exists((string) $value),
                default => false,
            };

            if ($failed) {
                $errors[$field] = validation_message($field, $name, $argument);
                break;
            }
        }
    }

    return $errors;
}

function is_blank_value(mixed $value): bool
{
    return $value === null || (is_string($value) && trim($value) === '')
        || (is_array($value) && $value === []);
}

function string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date !== false && DateTime::getLastErrors() === false;
}

function valid_time(string $value): bool
{
    $time = DateTime::createFromFormat('!H:i', $value);

    return $time !== false && DateTime::getLastErrors() === false;
}

function in_allowed_values(string $value, string $argument): bool
{
    $allowed = array_map('trim', explode(',', $argument));

    return in_array($value, $allowed, true);
}

function valid_regex(string $pattern, string $value): bool
{
    return $pattern !== '' && @preg_match($pattern, $value) === 1;
}

function validation_message(string $field, string $rule, ?string $argument): string
{
    $label = friendly_field_name($field);

    return match ($rule) {
        'required' => "Enter your {$label}",
        'email' => "Enter a valid {$label}",
        'min' => "Enter at least {$argument} characters for your {$label}",
        'max' => "Enter no more than {$argument} characters for your {$label}",
        'numeric' => "Enter a number for your {$label}",
        'date' => "Enter a valid date for your {$label}",
        'time' => "Enter a valid time for your {$label}",
        'in' => "Choose a valid {$label}",
        'matches' => "Your {$label} must match your " . friendly_field_name((string) $argument),
        'regex' => "Enter a valid {$label}",
        'unique_email' => "Use an email address that is not already registered",
        default => "Enter a valid {$label}",
    };
}

function friendly_field_name(string $field): string
{
    $field = str_replace(['_', '-'], ' ', $field);
    $field = preg_replace('/(?<!^)[A-Z]/', ' $0', $field) ?? $field;
    $field = strtolower(trim($field));

    return $field === 'email' ? 'email address' : $field;
}

/** @param array<string, mixed> $data */
function stash_old(array $data, array $except = ['password']): void
{
    start_session_once();
    $excluded = array_fill_keys(array_map('strtolower', $except), true);
    $old = [];

    foreach ($data as $key => $value) {
        $lowerKey = strtolower((string) $key);
        if (isset($excluded[$lowerKey]) || str_contains($lowerKey, 'password')) {
            continue;
        }
        $old[$key] = $value;
    }

    $_SESSION['old'] = $old;
}

/** @param array<string, string> $errors */
function stash_errors(array $errors): void
{
    start_session_once();
    $_SESSION['errors'] = $errors;
}

/**
 * Save validation state and redirect using POST-Redirect-GET.
 *
 * @param array<string, string> $errors
 * @param array<string, mixed> $data
 */
function validation_failed(array $errors, array $data, string $redirectTo): never
{
    stash_old($data);
    stash_errors($errors);
    redirect($redirectTo);
}
