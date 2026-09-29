<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** Find a patient account by its configured username. */
function find_patient_account(string $username): ?array
{
    return q_one(
        'SELECT `PatientID` AS `id`, `FullName`, `User`, `HashPass`, \'patient\' AS `role`
         FROM `patient` WHERE `User` = :username LIMIT 1',
        ['username' => $username]
    );
}

/** Find a doctor account by its configured username. */
function find_doctor_account(string $username): ?array
{
    return q_one(
        'SELECT `DoctorID` AS `id`, `FullName`, `User`, `HashPass`, \'doctor\' AS `role`
         FROM `doctor` WHERE `User` = :username LIMIT 1',
        ['username' => $username]
    );
}
