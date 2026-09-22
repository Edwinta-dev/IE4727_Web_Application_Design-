<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctor.php');
if ($page === false) {
    throw new RuntimeException('doctor page could not be read');
}

foreach ([
    "ctype_digit",
    "render_not_found",
    "find_doctor(",
    "next_available(",
    "ImageURL",
    "FullName",
    "Specialty",
    "Qualifications",
    "Languages",
    "WriteUp",
    "book.php?doctor=",
    "date=",
    "slot=",
    "empty-state",
    "full-schedule-link",
    "e(",
] as $needle) {
    if (strpos($page, $needle) === false) {
        throw new RuntimeException('missing doctor profile requirement: ' . $needle);
    }
}

echo "PASS: doctor page markup checks\n";
