<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctors.php');
if ($page === false) {
    throw new RuntimeException('doctors page could not be read');
}

foreach (['<table', '<caption>', '<thead>', '<tbody>', 'all_specialties()', 'name="specialty"',
    'method="get"', 'doctor.php?id=', 'book.php?doctor_id=', 'No doctors match that specialty',
    'class="doctor-photo"', 'csrf_field()'] as $needle) {
    assert_contains($page, $needle);
}

echo "PASS: doctors page markup checks\n";
