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
    "doctor-profile-identity",
    "doctor-profile-content",
    "doctor-specialty-content",
    "doctor-book-button",
    "Who should book",
    "specialty_profile(",
    "nl2br(e(",
    "e(",
] as $needle) {
    if (strpos($page, $needle) === false) {
        throw new RuntimeException('missing doctor profile requirement: ' . $needle);
    }
}

$helpers = file_get_contents(dirname(__DIR__) . '/clinic-base/lib/helpers.php');
if ($helpers === false) {
    throw new RuntimeException('doctor profile helpers could not be read');
}
require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';
foreach (['General Practice', 'Dental', 'Paediatrics', 'Dermatology', 'Physiotherapy', 'Unknown'] as $specialty) {
    $profile = specialty_profile($specialty);
    if (trim($profile['summary']) === '' || count($profile['who_should_book']) < 4 || count($profile['who_should_book']) > 6) {
        throw new RuntimeException('specialty profile content is incomplete: ' . $specialty);
    }
}

$seed = file_get_contents(dirname(__DIR__) . '/schema/003_seed.sql');
$migration = file_get_contents(dirname(__DIR__) . '/schema/002_migrate.sql');
if ($seed === false || $migration === false) {
    throw new RuntimeException('doctor seed or migration could not be read');
}
foreach (['drsmith', 'drnair', 'drtan', 'drlim', 'drrao'] as $user) {
    if (!str_contains($seed, "'$user'")) {
        throw new RuntimeException('missing seeded doctor bio: ' . $user);
    }
    if (!str_contains($migration, "WHERE `User` = '$user' AND CHAR_LENGTH(`WriteUp`) < 400")) {
        throw new RuntimeException('missing guarded doctor bio migration: ' . $user);
    }
}
foreach (range(1, 5) as $doctorId) {
    $doctor = find_doctor($doctorId);
    $bio = is_array($doctor) ? trim((string) $doctor['WriteUp']) : '';
    $wordCount = $bio === '' ? 0 : count(preg_split('/\s+/u', $bio) ?: []);
    if ($wordCount < 120 || $wordCount > 180) {
        throw new RuntimeException('seeded doctor biography word count is outside 120-180: ' . $doctorId . ' (' . $wordCount . ')');
    }
}

echo "PASS: doctor page markup checks\n";
