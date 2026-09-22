<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';

$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$currentPath = is_string($currentPath) && $currentPath !== '' ? $currentPath : '/index.php';
$user = current_user();
$role = is_array($user) ? (string) ($user['role'] ?? '') : '';

$links = match ($role) {
    'patient' => [
        ['label' => 'Home', 'href' => '/patient/home.php', 'page' => '/patient/home.php'],
        ['label' => 'Find a doctor', 'href' => '/doctors.php', 'page' => '/doctors.php'],
        ['label' => 'My appointments', 'href' => '/patient/home.php#appointments', 'page' => '/patient/home.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1', 'page' => '/index.php'],
    ],
    'doctor' => [
        ['label' => 'Home', 'href' => '/doctor/home.php', 'page' => '/doctor/home.php'],
        ['label' => 'Schedule', 'href' => '/doctor/schedule.php', 'page' => '/doctor/schedule.php'],
        ['label' => 'Appointments', 'href' => '/doctor/home.php#appointments', 'page' => '/doctor/home.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1', 'page' => '/index.php'],
    ],
    'admin' => [
        ['label' => 'Console', 'href' => '/admin/console.php', 'page' => '/admin/console.php'],
        ['label' => 'Outbox', 'href' => '/admin/outbox.php', 'page' => '/admin/outbox.php'],
        ['label' => 'Log out', 'href' => '/index.php?logout=1', 'page' => '/index.php'],
    ],
    default => [
        ['label' => 'Home', 'href' => '/index.php', 'page' => '/index.php'],
        ['label' => 'Find a doctor', 'href' => '/doctors.php', 'page' => '/doctors.php'],
        ['label' => 'Book an appointment', 'href' => '/book.php', 'page' => '/book.php'],
        ['label' => 'Register', 'href' => '/register.php', 'page' => '/register.php'],
    ],
};
?>
<nav class="site-nav" aria-label="Main navigation">
    <ul>
<?php foreach ($links as $link): ?>
        <li>
            <a href="<?= e($link['href']) ?>"<?= $currentPath === $link['page'] ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        </li>
<?php endforeach; ?>
    </ul>
</nav>
