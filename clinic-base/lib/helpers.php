<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function render_not_found(string $message = 'Doctor not found.'): never
{
    http_response_code(404);
    $pageTitle = 'Doctor not found';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
    ?>
    <main class="status-page">
        <h1>Doctor not found</h1>
        <p><?= e($message) ?></p>
        <img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal logo">
    </main>
    <?php
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
    exit;
}

function fmt_date(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d M Y', $timestamp);
}

function fmt_time(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('g:i A', $timestamp);
}
