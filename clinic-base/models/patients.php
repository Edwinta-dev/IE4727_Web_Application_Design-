<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** @return array<string, mixed>|null */
function find_patient(int $id): ?array
{
    $patient = q_one(
        'SELECT `PatientID`, `FullName`, `User`, `HashPass`, `Email`, `Gender`, `Phone`, `Allergies`
         FROM `patient`
         WHERE `PatientID` = :id
         LIMIT 1',
        ['id' => $id]
    );

    if ($patient === null) {
        return null;
    }

    $allergies = json_decode((string) ($patient['Allergies'] ?? '[]'), true);
    $patient['Allergies'] = is_array($allergies) ? $allergies : [];

    return $patient;
}

/** @param array<string, mixed> $fields */
function update_patient_profile(int $id, array $fields): void
{
    $updates = [];
    $params = ['id' => $id];

    foreach (['FullName', 'Gender', 'Phone'] as $field) {
        if (array_key_exists($field, $fields)) {
            $parameter = strtolower($field);
            $updates[] = "`{$field}` = :{$parameter}";
            $params[$parameter] = $fields[$field];
        }
    }

    if (array_key_exists('Allergies', $fields)) {
        $updates[] = '`Allergies` = :allergies';
        $params['allergies'] = json_encode($fields['Allergies'], JSON_THROW_ON_ERROR);
    }

    if ($updates === []) {
        return;
    }

    q(
        'UPDATE `patient` SET ' . implode(', ', $updates) . ' WHERE `PatientID` = :id',
        $params
    );
}
