<?php

/*
Opens the HTML document and site header. Title comes from $pageTitle set by the page.
Links tokens.css and then style.css (each versioned with asset_url), in that order.
The logo links home for guests and patients; for doctors and admins it is a plain brand.
nav.php closes the header and footer.php closes the document.
*/

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/lib/auth.php';

// Doctors and admins work from their own pages; the home page is not a destination for them.
$logoLinksHome = !is_doctor() && !is_admin();

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
    <link rel="stylesheet" href="<?= e(asset_url('/assets/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="site-header-inner">
        <?php if ($logoLinksHome): ?>
        <a class="clinic-logo" href="<?= e(url('/index.php')) ?>">
            <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" alt="Clinic Appointment Portal logo">
            <span class="site-brand">Clinic Appointment Portal</span>
        </a>
        <?php else: ?>
        <div class="clinic-logo">
            <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" alt="Clinic Appointment Portal logo">
            <span class="site-brand">Clinic Appointment Portal</span>
        </div>
        <?php endif; ?>
