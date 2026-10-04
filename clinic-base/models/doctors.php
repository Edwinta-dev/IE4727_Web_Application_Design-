<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__) . '/lib/specialties.php';

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

    $sql .= ' ORDER BY `FullName`';

    $doctors = array_map('doctor_display_profile', q_all($sql, $params));
    if ($specialty !== null) {
        $filter = canonical_specialty($specialty) ?? $specialty;
        $doctors = array_values(array_filter($doctors,
            static fn (array $doctor): bool => $doctor['Specialty'] === $filter));
    }
    return $doctors;
}

/** @return list<array{Specialty: string|null}> */
function all_specialties(): array
{
    $rows = q_all(
        'SELECT DISTINCT `Specialty`
         FROM `doctor`
         WHERE `Specialty` IS NOT NULL
         ORDER BY `Specialty`'
    );
    $present = [];
    foreach ($rows as $row) {
        $specialty = canonical_specialty($row['Specialty']);
        if ($specialty !== null) {
            $present[$specialty] = ['Specialty' => $specialty];
        }
    }
    ksort($present);
    return array_values($present);
}

/** @return array<string, mixed>|null */
function find_doctor(int $id): ?array
{
    $doctor = q_one(
        'SELECT `DoctorID`, `FullName`, `User`, `HashPass`, `Email`, `Specialty`,
                `Qualifications`, `Languages`, `WriteUp`, `ImageURL`
         FROM `doctor`
         WHERE `DoctorID` = :id
         LIMIT 1',
        ['id' => $id]
    );
    return $doctor === null ? null : doctor_display_profile($doctor);
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
