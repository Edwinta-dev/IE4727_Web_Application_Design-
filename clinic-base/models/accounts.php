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

function account_by_id(string $role, int $id): ?array
{
    if ($role === 'doctor') {
        return q_one(
            'SELECT * FROM `doctor` WHERE `DoctorID` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    if ($role === 'patient') {
        return q_one(
            'SELECT * FROM `patient` WHERE `PatientID` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    return null;
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

function email_exists(string $email): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    if (q_one('SELECT `DoctorID` FROM `doctor` WHERE `Email` = :email LIMIT 1', ['email' => $email]) !== null) {
        return true;
    }

    return q_one('SELECT `PatientID` FROM `patient` WHERE `Email` = :email LIMIT 1', ['email' => $email]) !== null;
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

/** @param array<string, mixed> $filters @return list<array<string, mixed>> */
function admin_patients(array $filters = []): array
{
    $where = [];
    $params = [];
    $search = trim((string) ($filters['patient'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.`FullName` LIKE :patient_name OR p.`Email` LIKE :patient_email)';
        $params['patient_name'] = '%' . $search . '%';
        $params['patient_email'] = '%' . $search . '%';
    }

    $appointmentWhere = admin_account_appointment_filters($filters, $params, 'p.`PatientID`');
    if ($appointmentWhere !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM `appointment` ap WHERE ap.`PatientID` = p.`PatientID` AND ' . $appointmentWhere . ')';
    }

    $sql = 'SELECT p.`PatientID`, p.`FullName`, p.`User`, p.`Email`, p.`Gender`, p.`Phone`
            FROM `patient` p';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    return q_all($sql . ' ORDER BY p.`FullName`', $params);
}

/** @param array<string, mixed> $filters @return list<array<string, mixed>> */
function admin_doctors(array $filters = []): array
{
    $where = [];
    $params = [];
    $search = trim((string) ($filters['doctor_search'] ?? ''));
    if ($search !== '') {
        $where[] = '(d.`FullName` LIKE :doctor_name OR d.`Email` LIKE :doctor_email OR d.`Specialty` LIKE :doctor_specialty)';
        $params['doctor_name'] = '%' . $search . '%';
        $params['doctor_email'] = '%' . $search . '%';
        $params['doctor_specialty'] = '%' . $search . '%';
    }

    $appointmentWhere = admin_account_appointment_filters($filters, $params, 'd.`DoctorID`');
    if ($appointmentWhere !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM `appointment` ap WHERE ap.`DoctorID` = d.`DoctorID` AND ' . $appointmentWhere . ')';
    }

    $sql = 'SELECT d.`DoctorID`, d.`FullName`, d.`User`, d.`Email`, d.`Specialty`
            FROM `doctor` d';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    return q_all($sql . ' ORDER BY d.`FullName`', $params);
}

/** @param array<string, mixed> $filters @param array<string, mixed> $params */
function admin_account_appointment_filters(array $filters, array &$params, string $accountColumn): string
{
    $where = [];
    if (($filters['doctor'] ?? '') !== '') {
        $where[] = 'ap.`DoctorID` = :account_doctor';
        $params['account_doctor'] = (int) $filters['doctor'];
    }
    if (($filters['status'] ?? '') !== '') {
        $where[] = 'ap.`Status` = :account_status';
        $params['account_status'] = (string) $filters['status'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 'DATE(ap.`appointmentDateTime`) >= :account_date_from';
        $params['account_date_from'] = (string) $filters['date_from'];
    }
    if (($filters['date_to'] ?? '') !== '') {
        $where[] = 'DATE(ap.`appointmentDateTime`) <= :account_date_to';
        $params['account_date_to'] = (string) $filters['date_to'];
    }
    return implode(' AND ', $where);
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
