<?php

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
        '/patient/home.php' => '/patient/home.php',
        '/doctors.php' => '/doctors.php',
        '/book.php' => '/book.php',
        default => null,
    },
    'doctor' => match ($currentPath) {
        '/doctor/home.php' => '/doctor/home.php',
        '/doctor/schedule.php' => '/doctor/schedule.php',
        default => null,
    },
    'admin' => match ($currentPath) {
        '/admin/console.php' => '/admin/console.php',
        '/admin/outbox.php' => '/admin/outbox.php',
        default => null,
    },
    default => match ($currentPath) {
        '/' => '/index.php',
        '/index.php' => '/index.php',
        '/doctors.php' => '/doctors.php',
        '/book.php' => '/book.php',
        '/register.php' => '/register.php',
        default => null,
    },
};

$links = match ($navigationRole) {
    'patient' => [
        ['label' => 'My appointments', 'href' => '/patient/home.php'],
        ['label' => 'Find a doctor', 'href' => '/doctors.php'],
        ['label' => 'Book an appointment', 'href' => '/book.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    'doctor' => [
        ['label' => 'Day board', 'href' => '/doctor/home.php'],
        ['label' => 'Schedule', 'href' => '/doctor/schedule.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    'admin' => [
        ['label' => 'Console', 'href' => '/admin/console.php'],
        ['label' => 'Outbox', 'href' => '/admin/outbox.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1'],
    ],
    default => [
        ['label' => 'Home', 'href' => '/index.php'],
        ['label' => 'Find a doctor', 'href' => '/doctors.php'],
        ['label' => 'Book an appointment', 'href' => '/book.php'],
        ['label' => 'Register', 'href' => '/register.php'],
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
<script src="<?= e(url('/assets/nav.js')) ?>"></script>
