<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** @return list<array<string, mixed>> */
function appointments_per_doctor_this_week(): array
{
    return q_all(
        'SELECT d.`DoctorID`, d.`FullName` AS `DoctorName`, COUNT(a.`appointmentID`) AS `Bookings`
         FROM `doctor` d
         LEFT JOIN `appointment` a ON a.`DoctorID` = d.`DoctorID`
             AND YEARWEEK(a.`appointmentDateTime`, 1) = YEARWEEK(CURDATE(), 1)
         GROUP BY d.`DoctorID`, d.`FullName`
         ORDER BY `Bookings` DESC, d.`FullName`'
    );
}

/** @return array{total: int, no_show: int, rate: float} */
function overall_no_show_rate(): array
{
    $row = q_one(
        'SELECT COUNT(*) AS `Total`,
                COALESCE(SUM(CASE WHEN `Status` = \'No show\' THEN 1 ELSE 0 END), 0) AS `NoShows`
         FROM `appointment`'
    ) ?? ['Total' => 0, 'NoShows' => 0];
    $total = (int) $row['Total'];
    $noShows = (int) $row['NoShows'];
    return ['total' => $total, 'no_show' => $noShows, 'rate' => $total > 0 ? ($noShows / $total) * 100 : 0.0];
}

/** @return list<array<string, mixed>> */
function no_show_rate_per_doctor(): array
{
    return q_all(
        'SELECT d.`DoctorID`, d.`FullName` AS `DoctorName`, COUNT(a.`appointmentID`) AS `Total`,
                COALESCE(SUM(CASE WHEN a.`Status` = \'No show\' THEN 1 ELSE 0 END), 0) AS `NoShows`,
                COALESCE(SUM(CASE WHEN a.`Status` = \'No show\' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.`appointmentID`), 0) * 100, 0) AS `Rate`
         FROM `doctor` d
         LEFT JOIN `appointment` a ON a.`DoctorID` = d.`DoctorID`
         GROUP BY d.`DoctorID`, d.`FullName`
         ORDER BY `Rate` DESC, d.`FullName`'
    );
}

/** @return list<array<string, mixed>> */
function peak_booking_hours(): array
{
    return q_all(
        'SELECT HOUR(`appointmentDateTime`) AS `BookingHour`, COUNT(*) AS `Bookings`
         FROM `appointment`
         GROUP BY HOUR(`appointmentDateTime`)
         ORDER BY `Bookings` DESC, `BookingHour`'
    );
}

/**
 * Mean elapsed days from the appointment's booking record to its scheduled start.
 * All appointment statuses represent bookings. DATETIME values are clinic-local;
 * exclude missing and reversed times rather than treating them as zero days.
 *
 * @param array<string, mixed> $filters
 * @return array{mean_days: ?float, valid: int, invalid: int}
 */
function mean_booking_lead_time(array $filters = []): array
{
    $where = [];
    $params = [];
    if (($filters['doctor'] ?? '') !== '') {
        $where[] = '`DoctorID` = :lead_doctor';
        $params['lead_doctor'] = (int) $filters['doctor'];
    }
    if (($filters['status'] ?? '') !== '') {
        $where[] = '`Status` = :lead_status';
        $params['lead_status'] = (string) $filters['status'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 'DATE(`appointmentDateTime`) >= :lead_from';
        $params['lead_from'] = (string) $filters['date_from'];
    }
    if (($filters['date_to'] ?? '') !== '') {
        $where[] = 'DATE(`appointmentDateTime`) <= :lead_to';
        $params['lead_to'] = (string) $filters['date_to'];
    }
    $sql = 'SELECT AVG(CASE WHEN `CreatedAt` IS NOT NULL AND `appointmentDateTime` >= `CreatedAt`
                       THEN TIMESTAMPDIFF(SECOND, `CreatedAt`, `appointmentDateTime`) / 86400 END) AS `MeanDays`,
                   COALESCE(SUM(CASE WHEN `CreatedAt` IS NOT NULL AND `appointmentDateTime` >= `CreatedAt` THEN 1 ELSE 0 END), 0) AS `ValidSamples`,
                   COALESCE(SUM(CASE WHEN `CreatedAt` IS NULL OR `appointmentDateTime` < `CreatedAt` THEN 1 ELSE 0 END), 0) AS `InvalidSamples`
            FROM `appointment`';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $row = q_one($sql, $params);
    return [
        'mean_days' => $row['MeanDays'] === null ? null : (float) $row['MeanDays'],
        'valid' => (int) $row['ValidSamples'],
        'invalid' => (int) $row['InvalidSamples'],
    ];
}

// Descriptive aliases keep the model convenient for callers and CLI checks.
function stats_appointments_per_doctor_this_week(): array { return appointments_per_doctor_this_week(); }
function stats_no_show_rate(): array { return overall_no_show_rate(); }
function stats_no_show_rate_per_doctor(): array { return no_show_rate_per_doctor(); }
function stats_peak_booking_hours(): array { return peak_booking_hours(); }
function stats_mean_booking_lead_time(array $filters = []): array { return mean_booking_lead_time($filters); }
