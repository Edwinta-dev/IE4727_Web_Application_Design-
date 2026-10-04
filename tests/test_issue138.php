<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';
require_once dirname(__DIR__) . '/clinic-base/lib/validate.php';

$rule = ['Specialty' => 'required|in:' . implode(',', canonical_specialties())];
foreach (canonical_specialties() as $specialty) {
    assert_eq(validate(['Specialty' => $specialty], $rule), []);
}
foreach (['', 'Dentistry', 'dental', 'Unlisted care'] as $specialty) {
    assert_true(isset(validate(['Specialty' => $specialty], $rule)['Specialty']));
}
assert_eq(doctor_display_name('dr edwin'), 'Dr Edwin');
assert_eq(doctor_display_name('Dr McDonald'), 'Dr McDonald');
db()->beginTransaction();
try {
    q('UPDATE doctor SET Specialty = :specialty, FullName = :name WHERE DoctorID = :id',
        ['specialty' => 'Dentistry', 'name' => 'dr edwin', 'id' => 1]);
    assert_eq(find_doctor(1)['Specialty'], 'Dental');
    assert_eq(find_doctor(1)['FullName'], 'Dr Edwin');
    assert_true(in_array(1, array_column(all_doctors('Dental'), 'DoctorID')));
    $values = array_column(all_specialties(), 'Specialty');
    assert_eq(count(array_keys($values, 'Dental', true)), 1);
    assert_eq(array_diff($values, canonical_specialties()), []);
    q('UPDATE doctor SET Specialty = :specialty WHERE DoctorID = :id',
        ['specialty' => 'Legacy care', 'id' => 1]);
    assert_eq(find_doctor(1)['Specialty'], 'Legacy care');
    assert_eq(count(all_doctors()), 5);
    assert_true(!in_array('Legacy care', array_column(all_specialties(), 'Specialty'), true));
} finally {
    db()->rollBack();
}
echo "OK: canonical specialty validation, legacy filtering and doctor names\n";
