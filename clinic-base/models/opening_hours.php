<?php

/*
Clinic opening hours shown in the footer, derived from the next 30 days of generated slots.
*/

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';

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

/**
 * Summarise the earliest start and latest finish for each weekday.
 * Each row is one doctor's working day (clinic_schedule_days()): its first and
 * last slot start and the smallest gap between slots (1-60 minutes), which is
 * taken as the slot length; a day without such a gap uses the normal 30 minutes.
 *
 * @param list<array{SlotDate: string, FirstTime: string, LastTime: string, Step: int|string|null}> $rows
 */
function clinic_opening_hours(array $rows): string
{
    $week = [];
    foreach ($rows as $row) {
        $weekday = (int) date('N', strtotime((string) $row['SlotDate']));
        $start = substr((string) $row['FirstTime'], 0, 5);
        $step = $row['Step'] === null ? 30 : (int) $row['Step'];
        $end = date('H:i', strtotime((string) $row['LastTime']) + $step * 60);
        if (!isset($week[$weekday])) {
            $week[$weekday] = [$start, $end];
        } else {
            $week[$weekday][0] = min($week[$weekday][0], $start);
            $week[$weekday][1] = max($week[$weekday][1], $end);
        }
    }

    $names = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $groups = [];
    for ($day = 1; $day <= 7; $day++) {
        $hours = isset($week[$day]) ? implode('-', $week[$day]) : 'closed';
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last]['hours'] === $hours) {
            $groups[$last]['end'] = $day;
        } else {
            $groups[] = ['start' => $day, 'end' => $day, 'hours' => $hours];
        }
    }
    return implode(', ', array_map(static function (array $group) use ($names): string {
        $label = $names[$group['start']];
        if ($group['end'] !== $group['start']) {
            $label .= '-' . $names[$group['end']];
        }
        return $label . ' ' . $group['hours'];
    }, $groups));
}
