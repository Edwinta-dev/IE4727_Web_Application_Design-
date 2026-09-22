<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/**
 * Find a doctor or patient by username or email.
 *
 * Doctors are checked first so the lookup order is deterministic if the same
 * credential value exists in both role tables.
 *
 * @return array{role: 'doctor'|'patient', id: int, hash: string, name: string, email: string}|null
 */
function find_login(string $userOrEmail): ?array
{
    $doctor = q_one(
        'SELECT `DoctorID`, `HashPass`, `FullName`, `Email`
         FROM `doctor`
         WHERE `User` = :username OR `Email` = :email
         LIMIT 1',
        ['username' => $userOrEmail, 'email' => $userOrEmail]
    );

    if ($doctor !== null) {
        return [
            'role' => 'doctor',
            'id' => (int) $doctor['DoctorID'],
            'hash' => (string) $doctor['HashPass'],
            'name' => (string) $doctor['FullName'],
            'email' => (string) $doctor['Email'],
        ];
    }

    $patient = q_one(
        'SELECT `PatientID`, `HashPass`, `FullName`, `Email`
         FROM `patient`
         WHERE `User` = :username OR `Email` = :email
         LIMIT 1',
        ['username' => $userOrEmail, 'email' => $userOrEmail]
    );

    if ($patient === null) {
        return null;
    }

    return [
        'role' => 'patient',
        'id' => (int) $patient['PatientID'],
        'hash' => (string) $patient['HashPass'],
        'name' => (string) $patient['FullName'],
        'email' => (string) $patient['Email'],
    ];
}

function verify_admin(string $userOrEmail, string $plain): bool
{
    if (!defined('ADMIN_USER') || !defined('ADMIN_HASH')) {
        return false;
    }

    $usernameMatches = hash_equals((string) ADMIN_USER, $userOrEmail);
    $passwordMatches = password_verify($plain, (string) ADMIN_HASH);

    return $usernameMatches && $passwordMatches;
}

function user_or_email_taken(string $value): bool
{
    $match = q_one(
        'SELECT 1
         FROM `doctor`
         WHERE `User` = :username OR `Email` = :email
         LIMIT 1',
        ['username' => $value, 'email' => $value]
    );

    if ($match !== null) {
        return true;
    }

    return q_one(
        'SELECT 1
         FROM `patient`
         WHERE `User` = :username OR `Email` = :email
         LIMIT 1',
        ['username' => $value, 'email' => $value]
    ) !== null;
}

/** @param array<string, mixed> $fields */
function create_doctor_account(array $fields): int
{
    $password = account_field($fields, 'password');

    q(
        'INSERT INTO `doctor`
            (`FullName`, `User`, `HashPass`, `Email`, `Specialty`, `Qualifications`, `Languages`, `WriteUp`, `ImageURL`)
         VALUES (:full_name, :user, :hash_pass, :email, :specialty, :qualifications, :languages, :write_up, :image_url)',
        [
            'full_name' => account_field($fields, 'FullName', 'name'),
            'user' => account_field($fields, 'User', 'username'),
            'hash_pass' => password_hash($password, PASSWORD_DEFAULT),
            'email' => account_field($fields, 'Email', 'email'),
            'specialty' => account_optional_field($fields, 'Specialty'),
            'qualifications' => account_optional_field($fields, 'Qualifications'),
            'languages' => account_optional_field($fields, 'Languages'),
            'write_up' => account_optional_field($fields, 'WriteUp'),
            'image_url' => account_optional_field($fields, 'ImageURL'),
        ]
    );

    return (int) db()->lastInsertId();
}

/** @param array<string, mixed> $fields */
function create_patient_account(array $fields): int
{
    $password = account_field($fields, 'password');
    $allergies = $fields['Allergies'] ?? null;

    q(
        'INSERT INTO `patient`
            (`FullName`, `User`, `HashPass`, `Email`, `Gender`, `Phone`, `Allergies`)
         VALUES (:full_name, :user, :hash_pass, :email, :gender, :phone, :allergies)',
        [
            'full_name' => account_field($fields, 'FullName', 'name'),
            'user' => account_field($fields, 'User', 'username'),
            'hash_pass' => password_hash($password, PASSWORD_DEFAULT),
            'email' => account_field($fields, 'Email', 'email'),
            'gender' => account_optional_field($fields, 'Gender'),
            'phone' => account_optional_field($fields, 'Phone'),
            'allergies' => $allergies === null ? null : json_encode($allergies, JSON_THROW_ON_ERROR),
        ]
    );

    return (int) db()->lastInsertId();
}

function delete_doctor(int $id): void
{
    q('DELETE FROM `doctor` WHERE `DoctorID` = :id', ['id' => $id]);
}

function delete_patient(int $id): void
{
    q('DELETE FROM `patient` WHERE `PatientID` = :id', ['id' => $id]);
}

/** @param array<string, mixed> $fields */
function account_field(array $fields, string $key, ?string $alias = null): string
{
    $value = $fields[$key] ?? ($alias !== null ? ($fields[$alias] ?? null) : null);
    if (!is_string($value) || $value === '') {
        throw new InvalidArgumentException("Missing account field: {$key}");
    }

    return $value;
}

/** @param array<string, mixed> $fields */
function account_optional_field(array $fields, string $key): ?string
{
    $value = $fields[$key] ?? null;

    return $value === null ? null : (string) $value;
}
