<?php

declare(strict_types=1);

$log = tempnam(sys_get_temp_dir(), 'clinic-errors-');
define('APP_LOG_FILE', $log);
define('APP_VERBOSE_ERRORS', false);
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'errors.php';

app_log('test diagnostic', ['secret' => 'not rendered']);
$contents = file_get_contents($log);
if ($contents === false || strpos($contents, 'test diagnostic') === false || strpos($contents, 'correlation_id') === false || strpos($contents, 'timestamp') === false) {
    fwrite(STDERR, "FAIL: diagnostic log is missing required fields\n");
    exit(1);
}

ob_start();
$safeHandler = static function (Throwable $exception): void {
    app_log('test exception', ['exception' => $exception]);
    echo 'Something went wrong. Reference: ' . htmlspecialchars(correlation_id(), ENT_QUOTES, 'UTF-8') . '.';
};
$safeHandler(new RuntimeException('database password must not leak'));
$output = ob_get_clean();
if (strpos($output, 'database password') !== false || strpos($output, 'Something went wrong.') === false) {
    fwrite(STDERR, "FAIL: unsafe exception output\n");
    exit(1);
}

@unlink($log);
echo "OK\n";
