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

function mean_booking_lead_time(): float
{
    return (float) (q_val(
        'SELECT COALESCE(AVG(DATEDIFF(DATE(`appointmentDateTime`), DATE(`CreatedAt`))), 0)
         FROM `appointment`'
    ) ?? 0);
}

// Descriptive aliases keep the model convenient for callers and CLI checks.
function stats_appointments_per_doctor_this_week(): array { return appointments_per_doctor_this_week(); }
function stats_no_show_rate(): array { return overall_no_show_rate(); }
function stats_no_show_rate_per_doctor(): array { return no_show_rate_per_doctor(); }
function stats_peak_booking_hours(): array { return peak_booking_hours(); }
function stats_mean_booking_lead_time(): float { return mean_booking_lead_time(); }
