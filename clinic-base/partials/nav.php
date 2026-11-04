<?php

/*
Navigation links for the current role (guest, patient, doctor, admin), with aria-current on the active page.
Doctors and admins get only their working pages and Log out. Loads nav.js, versioned.
Log out links to index.php?logout=1.
*/

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';

$currentPath = parse_url(app_request_uri(), PHP_URL_PATH);
$currentPath = is_string($currentPath) && $currentPath !== '' ? $currentPath : '/index.php';
$user = current_user();
$navigationRole = is_array($user) ? (string) ($user['role'] ?? '') : '';

// A route selects one destination. Anchor previews on the same page do not
// become a second current tab, and the logout action is never a page.
$currentTab = match ($navigationRole) {
    'patient' => match ($currentPath) {
        '/screens/patient/home.php' => '/screens/patient/home.php',
        '/screens/doctors.php' => '/screens/doctors.php',
        '/screens/book.php' => '/screens/book.php',
        default => null,
    },
    'doctor' => match ($currentPath) {
        '/screens/doctor/home.php' => '/screens/doctor/home.php',
        '/screens/doctor/schedule.php' => '/screens/doctor/schedule.php',
        default => null,
    },
    'admin' => match ($currentPath) {
        '/screens/admin/console.php' => '/screens/admin/console.php',
        '/screens/admin/outbox.php' => '/screens/admin/outbox.php',
        default => null,
    },
    default => match ($currentPath) {
        '/' => '/index.php',
        '/index.php' => '/index.php',
        '/screens/doctors.php' => '/screens/doctors.php',
        '/screens/book.php' => '/screens/book.php',
        '/screens/register.php' => '/screens/register.php',
        default => null,
    },
};

$links = match ($navigationRole) {
    'patient' => [
        ['label' => 'My appointments', 'href' => '/screens/patient/home.php'],
        ['label' => 'Find a doctor', 'href' => '/screens/doctors.php'],
        ['label' => 'Book an appointment', 'href' => '/screens/book.php'],
        ['label' => 'About Us', 'href' => '/index.php#about'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    'doctor' => [
        ['label' => 'Day board', 'href' => '/screens/doctor/home.php'],
        ['label' => 'Schedule', 'href' => '/screens/doctor/schedule.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    'admin' => [
        ['label' => 'Console', 'href' => '/screens/admin/console.php'],
        ['label' => 'Outbox', 'href' => '/screens/admin/outbox.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    default => [
        ['label' => 'Home', 'href' => '/index.php'],
        ['label' => 'About Us', 'href' => '/index.php#about'],
        ['label' => 'Find a doctor', 'href' => '/screens/doctors.php'],
        ['label' => 'Book an appointment', 'href' => '/screens/book.php'],
        ['label' => 'Register', 'href' => '/screens/register.php'],
        ['label' => 'Log in', 'href' => '/index.php#login-card'],
    ],
};
?>
<button class="nav-toggle" type="button" aria-controls="site-navigation" aria-expanded="false" hidden>Menu</button>
<nav id="site-navigation" class="site-nav" aria-label="Main navigation">
    <ul>
<?php foreach ($links as $link): ?>
        <li<?php if ($link['label'] === 'Log out'): ?> class="nav-logout"<?php endif; ?>>
            <a href="<?= e(url($link['href'])) ?>"<?php if ($currentTab === $link['href']): ?> aria-current="page"<?php endif; ?>><?= e($link['label']) ?></a>
        </li>
<?php endforeach; ?>
    </ul>
</nav>
</div>
</header>
<script src="<?= e(asset_url('/assets/nav.js')) ?>"></script>
