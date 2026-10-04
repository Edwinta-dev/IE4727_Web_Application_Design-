<?php

declare(strict_types=1);

// This is an owner-operated local command, never an HTTP endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(database|appointment)=(.+)$/D', $argument, $match)
        || isset($options[$match[1]])) {
        fwrite(STDERR, "Usage: php tools/inspect_remarks.php --database=ie4727db_test --appointment=<positive ID>\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}
$database = $options['database'] ?? '';
$id = filter_var($options['appointment'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!in_array($database, ['ie4727db', 'ie4727db_test'], true) || $id === false
    || (getenv('CLINIC_DB_NAME') !== false && getenv('CLINIC_DB_NAME') !== '' && getenv('CLINIC_DB_NAME') !== $database)) {
    fwrite(STDERR, "Specify a positive appointment ID and an explicit database matching CLINIC_DB_NAME, if set.\n");
    exit(2);
}
putenv('CLINIC_DB_NAME=' . $database);
try {
    require_once dirname(__DIR__) . '/clinic-base/models/remarks_inspection.php';
    $parts = inspect_appointment_remarks($id, $database);
    if ($parts === null) {
        fwrite(STDERR, "Appointment not found.\n");
        exit(1);
    }
    echo 'Database: ' . $database . "\nAppointment: " . $id . "\n" . format_remarks_inspection($parts);
} catch (Throwable $exception) {
    // Do not emit database credentials, raw stored values or unrelated notes.
    fwrite(STDERR, "Inspection failed; check local database configuration and read access.\n");
    exit(1);
}
