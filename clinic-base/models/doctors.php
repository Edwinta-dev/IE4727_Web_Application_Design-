<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__) . '/lib/specialties.php';

/**
 * Upcoming generated slots are the persisted record of doctors' working days.
 * One row per doctor and day (first start, last start, smallest 1-60 minute
 * gap) so the footer reads tens of rows, not every slot. Cached per request.
 */
function clinic_schedule_days(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = q_all(
            'SELECT `DoctorID`, `SlotDate`, MIN(`SlotTime`) AS `FirstTime`, MAX(`SlotTime`) AS `LastTime`,
                    MIN(CASE WHEN `Gap` BETWEEN 1 AND 60 THEN `Gap` END) AS `Step`
             FROM (SELECT `DoctorID`, DATE(`SlotDateTime`) AS `SlotDate`, TIME(`SlotDateTime`) AS `SlotTime`,
                          TIMESTAMPDIFF(MINUTE, LAG(`SlotDateTime`) OVER (
                              PARTITION BY `DoctorID`, DATE(`SlotDateTime`) ORDER BY `SlotDateTime`), `SlotDateTime`) AS `Gap`
                   FROM `slots`
                   WHERE `SlotDateTime` >= CURDATE()
                     AND `SlotDateTime` < DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS `day_slots`
             GROUP BY `DoctorID`, `SlotDate`
             ORDER BY `DoctorID`, `SlotDate`'
        );
    }
    return $rows;
}

/** Footer opening hours; a database failure must not break every page. */
function clinic_opening_hours_text(): string
{
    try {
        return clinic_opening_hours(clinic_schedule_days());
    } catch (Throwable $error) {
        error_log('Footer opening hours unavailable: ' . $error->getMessage());
        return 'See Find a Doctor for available times';
    }
}

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
