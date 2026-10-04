<?php

declare(strict_types=1);

$base = dirname(__DIR__) . '/clinic-base';
$pages = [
    'doctors.php',
    'admin/console.php',
    'admin/outbox.php',
    'book.php',
    'doctor/home.php',
    'doctor/schedule.php',
];

foreach ($pages as $page) {
    $source = file_get_contents($base . '/' . $page);
    assert_true($source !== false, "Cannot read {$page}");

    preg_match_all('~<form\b[^>]*\bmethod="get"[^>]*>(.*?)</form>~is', $source, $getForms);
    assert_true($getForms[1] !== [], "Expected a GET filter in {$page}");
    foreach ($getForms[1] as $form) {
        assert_true(!str_contains($form, 'csrf_field()') && !str_contains($form, 'name="_csrf"'),
            "GET filter exposes a CSRF token in {$page}");
    }

    preg_match_all('~<form\b[^>]*\bmethod="post"[^>]*>(.*?)</form>~is', $source, $postForms);
    foreach ($postForms[1] as $form) {
        assert_contains($form, 'csrf_field()', "POST form lost CSRF protection in {$page}");
    }
    assert_true(!preg_match('~<a\b[^>]*href="[^"]*_csrf~i', $source),
        "Navigation link carries an obsolete token in {$page}");
}

assert_contains((string) file_get_contents($base . '/admin/console.php'), 'csrf_check()',
    'Admin deletion must verify CSRF before mutation');
foreach (['actions/book.php', 'actions/register.php', 'actions/appointment.php'] as $action) {
    assert_contains((string) file_get_contents($base . '/' . $action), 'csrf_check()',
        "{$action} must verify CSRF before mutation");
}

// Exercise the actual role handlers against the isolated test database. A rejected
// request must leave rows unchanged, including the notification log.
require_once $base . '/lib/db.php';
$counts = static function (): array {
    $result = [];
    foreach ([
        'patient' => 'SELECT COUNT(*) FROM `patient`',
        'doctor' => 'SELECT COUNT(*) FROM `doctor`',
        'slots' => 'SELECT COUNT(*) FROM `slots`',
        'appointment' => 'SELECT COUNT(*) FROM `appointment`',
        'notifications' => 'SELECT COUNT(*) FROM `notifications`',
    ] as $table => $sql) {
        $result[$table] = (int) q($sql)->fetchColumn();
    }
    return $result;
};
$rowCountsBefore = $counts();
$requests = [
    ['patient', 1, 'actions/book.php', ['slot_id' => '1', 'reason' => 'Check-up']],
    ['doctor', 1, 'doctor/schedule.php', ['action' => 'generate', 'start_date' => date('Y-m-d'), 'days' => '1']],
    ['admin', 'admin', 'admin/console.php', ['action' => 'delete_patient', 'id' => '1']],
];
foreach ($requests as [$role, $id, $handler, $fields]) {
    foreach (['missing', 'invalid'] as $case) {
        $submitted = $fields + ($case === 'invalid' ? ['_csrf' => 'wrong'] : []);
        $runner = tempnam(sys_get_temp_dir(), 'issue117-');
        assert_true($runner !== false, 'Cannot create CSRF handler runner');
        $script = '<?php ' . PHP_EOL
            . 'require_once ' . var_export($base . '/lib/csrf.php', true) . ';' . PHP_EOL
            . 'session_save_path(sys_get_temp_dir());' . PHP_EOL
            . 'session_id(' . var_export('issue117-' . bin2hex(random_bytes(8)), true) . ');' . PHP_EOL
            . 'start_session_once();' . PHP_EOL
            . '$_SESSION["role"] = ' . var_export($role, true) . ';' . PHP_EOL
            . '$_SESSION["id"] = ' . var_export($id, true) . ';' . PHP_EOL
            . 'csrf_token();' . PHP_EOL
            . '$_SERVER["REQUEST_METHOD"] = "POST";' . PHP_EOL
            . '$_POST = ' . var_export($submitted, true) . ';' . PHP_EOL
            . 'require ' . var_export($base . '/' . $handler, true) . ';' . PHP_EOL;
        file_put_contents($runner, $script);
        try {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1', $output, $status);
            assert_eq($status, 0, "{$role} {$case} CSRF runner failed");
            assert_contains(implode("\n", $output), 'Your form could not be verified',
                "{$role} {$case} CSRF request was not rejected");
            assert_eq($counts(), $rowCountsBefore, "{$role} {$case} CSRF request changed database rows");
        } finally {
            unlink($runner);
        }
    }
}

echo "PASS: GET filters omit tokens; POST forms and handlers retain CSRF guards\n";
