<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** @return list<array<string, mixed>> */
function all_doctors(?string $specialty = null): array
{
    $sql = 'SELECT `DoctorID`, `FullName`, `Specialty`, `Qualifications`, `Languages`, `WriteUp`, `ImageURL`
                , (SELECT MIN(`SlotDateTime`)
                   FROM `slots`
                   WHERE `slots`.`DoctorID` = `doctor`.`DoctorID`
                     AND `slots`.`Status` = \'Available\'
                     AND `slots`.`SlotDateTime` >= NOW()) AS `NextAvailable`
            FROM `doctor`';
    $params = [];

    if ($specialty !== null) {
        $sql .= ' WHERE `Specialty` = :specialty';
        $params['specialty'] = $specialty;
    }

    $sql .= ' ORDER BY `FullName`';

    return q_all($sql, $params);
}

/** @return list<array{Specialty: string|null}> */
function all_specialties(): array
{
    return q_all(
        'SELECT DISTINCT `Specialty`
         FROM `doctor`
         WHERE `Specialty` IS NOT NULL
         ORDER BY `Specialty`'
    );
}

/** @return array<string, mixed>|null */
function find_doctor(int $id): ?array
{
    return q_one(
        'SELECT `DoctorID`, `FullName`, `User`, `HashPass`, `Email`, `Specialty`,
                `Qualifications`, `Languages`, `WriteUp`, `ImageURL`
         FROM `doctor`
         WHERE `DoctorID` = :id
         LIMIT 1',
        ['id' => $id]
    );
}

/** @param array<string, mixed> $fields */
function update_doctor_profile(int $id, array $fields): void
{
    $updates = [];
    $params = ['id' => $id];

    foreach (['WriteUp', 'Specialty', 'Qualifications', 'Languages', 'ImageURL'] as $field) {
        if (array_key_exists($field, $fields)) {
            $parameter = strtolower($field);
            $updates[] = "`{$field}` = :{$parameter}";
            $params[$parameter] = $fields[$field];
        }
    }

    if ($updates === []) {
        return;
    }

    q(
        'UPDATE `doctor` SET ' . implode(', ', $updates) . ' WHERE `DoctorID` = :id',
        $params
    );
}
