<?php

declare(strict_types=1);

/**
 * Generate schedule slots without consulting application state.
 *
 * @return list<array{slot_date: string, start_time: string, slot_minutes: int}>
 */
function generate_slots(string $from, int $days, array $opts = []): array
{
    if ($days <= 0) {
        return [];
    }

    $start = slot_time_to_minutes((string) ($opts['start'] ?? '09:00'));
    $end = slot_time_to_minutes((string) ($opts['end'] ?? '17:00'));
    $minutes = (int) ($opts['minutes'] ?? 30);
    $skipWeekdays = $opts['skip_weekdays'] ?? [0];
    $breaks = $opts['breaks'] ?? [['13:00', '14:00']];

    if ($minutes <= 0 || $end <= $start || !is_array($skipWeekdays) || !is_array($breaks)) {
        throw new InvalidArgumentException('Invalid slot schedule options.');
    }

    $skipWeekdays = array_map('intval', $skipWeekdays);
    $breakRanges = [];
    foreach ($breaks as $break) {
        if (is_array($break) && array_key_exists('start', $break) && array_key_exists('end', $break)) {
            $breakStart = slot_time_to_minutes((string) $break['start']);
            $breakEnd = slot_time_to_minutes((string) $break['end']);
        } elseif (is_array($break) && count($break) >= 2) {
            $values = array_values($break);
            $breakStart = slot_time_to_minutes((string) $values[0]);
            $breakEnd = slot_time_to_minutes((string) $values[1]);
        } else {
            throw new InvalidArgumentException('Invalid break range.');
        }

        if ($breakEnd <= $breakStart) {
            throw new InvalidArgumentException('Invalid break range.');
        }
        $breakRanges[] = [$breakStart, $breakEnd];
    }

    try {
        $currentDate = new DateTimeImmutable($from . ' 00:00:00');
    } catch (Exception $exception) {
        throw new InvalidArgumentException('Invalid starting date.', 0, $exception);
    }

    $slots = [];
    for ($day = 0; $day < $days; $day++) {
        if (!in_array((int) $currentDate->format('w'), $skipWeekdays, true)) {
            for ($startTime = $start; $startTime + $minutes <= $end; $startTime += $minutes) {
                $slotEnd = $startTime + $minutes;
                $overlapsBreak = false;
                foreach ($breakRanges as [$breakStart, $breakEnd]) {
                    if ($startTime < $breakEnd && $slotEnd > $breakStart) {
                        $overlapsBreak = true;
                        break;
                    }
                }

                if (!$overlapsBreak) {
                    $slots[] = [
                        'slot_date' => $currentDate->format('Y-m-d'),
                        'start_time' => slot_minutes_to_time($startTime),
                        'slot_minutes' => $minutes,
                    ];
                }
            }
        }
        $currentDate = $currentDate->modify('+1 day');
    }

    return $slots;
}

/**
 * A slot is bookable when its start is not earlier than the supplied time.
 */
function is_bookable(string $date, string $time, ?string $now = null): bool
{
    try {
        $slot = new DateTimeImmutable($date . ' ' . $time);
        $current = new DateTimeImmutable($now ?? 'now');
    } catch (Exception) {
        return false;
    }

    return $slot >= $current;
}

function slot_time_to_minutes(string $time): int
{
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
        throw new InvalidArgumentException('Time must use HH:MM format.');
    }

    $hours = (int) $matches[1];
    $minutes = (int) $matches[2];
    if ($hours > 23 || $minutes > 59) {
        throw new InvalidArgumentException('Invalid time.');
    }

    return ($hours * 60) + $minutes;
}

function slot_minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}
