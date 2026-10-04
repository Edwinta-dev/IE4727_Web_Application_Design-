<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';
require_once __DIR__ . '/visit_remarks.php';

/** Local reviewer only: fetch no names, contact details or other visit notes. */
function inspect_appointment_remarks(int $appointmentId, string $database): ?array
{
    if (!in_array($database, ['ie4727db', 'ie4727db_test'], true)
        || DB_NAME !== $database || q_val('SELECT DATABASE()') !== $database) {
        throw new RuntimeException('Inspection database does not match the requested database.');
    }
    // Enforce read-only operation on the server, including future changes here.
    q('START TRANSACTION READ ONLY');
    try {
        $row = q_one('SELECT `Remarks`, `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $appointmentId]);
        $parts = $row === null ? null : decode_visit_remarks($row['Remarks'], $row['Status']);
        q('COMMIT');
        return $parts;
    } catch (Throwable $exception) {
        q('ROLLBACK');
        throw $exception;
    }
}

/** Plain terminal report; preserve newlines, visibly escape other control bytes. */
function format_remarks_inspection(array $parts): string
{
    $report = '';
    foreach (['reason' => 'Patient reason', 'doctor_remarks' => 'Doctor remarks', 'legacy' => 'Earlier text (author unknown)'] as $key => $label) {
        $value = $parts[$key];
        $text = $value === null || $value === '' ? '(empty)' : $value;
        $text = preg_replace_callback('/[\x00-\x09\x0B-\x1F\x7F]/',
            static fn(array $match): string => sprintf('\\x%02X', ord($match[0])), $text);
        $report .= $label . ":\n  " . str_replace("\n", "\n  ", $text) . "\n";
    }
    return $report;
}
