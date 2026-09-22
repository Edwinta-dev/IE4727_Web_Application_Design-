<?php

declare(strict_types=1);

final class TestAssertionFailure extends RuntimeException
{
}

function assert_eq(mixed $actual, mixed $expected, string $label = ''): void
{
    if ($actual !== $expected) {
        throw new TestAssertionFailure(assertion_message(
            $label,
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        ));
    }
}

function assert_true(mixed $condition, string $label = ''): void
{
    if (!$condition) {
        throw new TestAssertionFailure(assertion_message($label, 'condition was false'));
    }
}

function assert_count(mixed $actual, int $expected, string $label = ''): void
{
    if (!is_countable($actual)) {
        throw new TestAssertionFailure(assertion_message($label, 'value is not countable'));
    }

    $count = count($actual);
    if ($count !== $expected) {
        throw new TestAssertionFailure(assertion_message(
            $label,
            "expected count {$expected}, got {$count}"
        ));
    }
}

function assert_contains(string $haystack, string $needle, string $label = ''): void
{
    if (strpos($haystack, $needle) === false) {
        throw new TestAssertionFailure(assertion_message(
            $label,
            'expected to find ' . var_export($needle, true)
        ));
    }
}

function fetch_page(string $path): string
{
    $url = 'http://localhost:8000' . $path;
    $body = @file_get_contents($url);
    if ($body === false) {
        throw new RuntimeException('could not fetch ' . $url);
    }

    return $body;
}

function assertion_message(string $label, string $detail): string
{
    return ($label === '' ? 'assertion failed' : $label) . ': ' . $detail;
}
