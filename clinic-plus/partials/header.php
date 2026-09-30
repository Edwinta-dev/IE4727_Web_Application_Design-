<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';

$pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== ''
    ? $pageTitle
    : 'Clinic Appointment Portal';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <a class="clinic-logo" href="<?= e(url('/index.php')) ?>">
        <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" alt="Clinic Appointment Portal logo">
        <span>Clinic Appointment Portal</span>
    </a>
</header>
