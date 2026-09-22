<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** @return array<string, mixed>|null */
function find_doctor(int $id): ?array
{
    return q_one(
        'SELECT `DoctorID`, `FullName`, `Specialty`, `Qualifications`, `Languages`, `WriteUp`, `ImageURL`
         FROM `doctor`
         WHERE `DoctorID` = :id
         LIMIT 1',
        ['id' => $id]
    );
}
