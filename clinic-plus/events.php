<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
start_session_once();
$user = current_user();
$role = $_SESSION['role'] ?? null;
$userId = $_SESSION['id'] ?? null;
session_write_close();
if ($user === null || $role !== 'doctor' || (!is_int($userId) && !(is_string($userId) && ctype_digit($userId)))) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Unauthorized\n");
}
$dateInput = $_GET['date'] ?? date('Y-m-d');
$date = is_string($dateInput) ? $dateInput : date('Y-m-d');
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) $date = date('Y-m-d');
set_time_limit(0);
while (ob_get_level() > 0) ob_end_flush();
header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
$last = isset($_GET['since']) && is_string($_GET['since']) ? $_GET['since'] : null;
$deadline = microtime(true) + 45;
echo ": clinic board stream\n\n";
flush();
while (microtime(true) < $deadline) {
    $latest = latest_appointment_update_for_doctor((int) $userId, $date);
    if ($latest !== null && $latest !== $last) {
        echo "event: board-update\n" . 'data: ' . $latest . "\n\n";
        $last = $latest;
    } else echo ": keep-alive\n\n";
    flush();
    if (connection_aborted()) break;
    sleep(2);
}
