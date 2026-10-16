<?php

test('mobile action tables retain labelled fields and narrow-screen layouts', static function (): void {
    $root = dirname(__DIR__);
    $style = (string) file_get_contents($root . '/clinic-base/assets/css/style.css');
    $pages = [
        'directory' => (string) file_get_contents($root . '/clinic-base/doctors.php'),
        'day board' => (string) file_get_contents($root . '/clinic-base/doctor/home.php'),
        'patient dashboard' => (string) file_get_contents($root . '/clinic-base/patient/home.php'),
        'outbox' => (string) file_get_contents($root . '/clinic-base/admin/outbox.php'),
    ];
    foreach ($pages as $name => $source) {
        if (!str_contains($source, 'data-label=')) {
            throw new RuntimeException($name . ' table rows need mobile field labels');
        }
    }
    foreach (['.doctor-directory tbody tr', '.day-board tbody tr', '.appointment-table-scroll:not(.is-empty) tbody tr', '.admin-outbox tbody tr'] as $selector) {
        if (!str_contains($style, $selector)) {
            throw new RuntimeException('Missing compact mobile layout for ' . $selector);
        }
    }
    if (!str_contains($style, '@media (max-width: 30rem)') || !str_contains($style, 'text-decoration: underline')) {
        throw new RuntimeException('Mobile breakpoint or linked doctor-name treatment missing');
    }
});
