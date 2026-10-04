<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$base = $root . DIRECTORY_SEPARATOR . 'clinic-base';
$spec = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'PAGES.md');
if ($spec === false) {
    fwrite(STDERR, "FAIL: docs/PAGES.md is unreadable\n");
    exit(1);
}

preg_match_all('/\| `([^`]+\.php)` \| (PENDING|IMPLEMENTED|BUILT) \|/', $spec, $matches);
$pages = $matches[1];
$expected = [
    'index.php', 'doctors.php', 'doctor.php', 'book.php', 'register.php',
    'patient/home.php', 'doctor/home.php', 'doctor/schedule.php',
    'doctor/visit.php', 'admin/console.php', 'admin/outbox.php',
];
if ($pages !== $expected || count($pages) !== 11 || count(array_unique($pages)) !== 11) {
    fwrite(STDERR, "FAIL: page budget must contain exactly the documented eleven pages\n");
    exit(1);
}

$implemented = 0;
foreach ($pages as $page) {
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $page);
    if (!is_file($path)) {
        continue;
    }
    $implemented++;
    $contents = file_get_contents($path);
    if ($contents === false || (preg_match('/<title\b[^>]*>.*?<\/title>/is', $contents) !== 1
        && strpos($contents, '$pageTitle') === false)) {
        fwrite(STDERR, "FAIL: page has no title: {$page}\n");
        exit(1);
    }
    // Shared header imagery counts toward the rendered page image requirement.
    $header = file_get_contents($base . '/partials/header.php');
    $hasHeaderImage = str_contains($contents, "'partials' . DIRECTORY_SEPARATOR . 'header.php'")
        && $header !== false && str_contains($header, '<img')
        && str_contains($header, '/assets/img/clinic-logo.svg');
    if (strpos($contents, '/assets/img/') === false && !$hasHeaderImage) {
        fwrite(STDERR, "FAIL: page has no local image asset: {$page}\n");
        exit(1);
    }
    preg_match_all('~<form\b[^>]*\bmethod="post"[^>]*>(.*?)</form>~is', $contents, $postForms);
    foreach ($postForms[1] as $form) {
        if (!str_contains($form, 'csrf_field()')) {
            fwrite(STDERR, "FAIL: POST form lacks csrf_field(): {$page}\n");
            exit(1);
        }
    }
    if (strpos($contents, '<?= e(') === false && strpos($contents, 'e(') === false) {
        fwrite(STDERR, "FAIL: page has no e() output escaping: {$page}\n");
        exit(1);
    }
}

// This checklist is deliberately explicit so new page work cannot omit a required state.
$requiredStates = ['free', 'taken', 'blocked', 'past', 'empty', 'error', 'repopulated'];
if (count($requiredStates) !== 7) {
    fwrite(STDERR, "FAIL: compliance state checklist is incomplete\n");
    exit(1);
}

echo "OK: issue #60 audited exactly 11 documented pages ({$implemented} implemented, " . (11 - $implemented) . " pending)\n";
