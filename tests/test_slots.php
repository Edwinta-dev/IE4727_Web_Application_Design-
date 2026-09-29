<?php

declare(strict_types=1);

require_once __DIR__ . '/../clinic-base/lib/slots.php';

test('normal Tuesday schedule', static function (): void {
    $slots = generate_slots('2026-09-22', 1, []);
    assert_count($slots, 14);
    assert_eq($slots[0], ['slot_date' => '2026-09-22', 'start_time' => '09:00', 'slot_minutes' => 30]);
    assert_eq($slots[8]['start_time'], '14:00');
    assert_eq($slots[13]['start_time'], '16:30');
});

test('Sunday is skipped by default', static function (): void {
    assert_count(generate_slots('2026-09-20', 1, []), 0);
});

test('lunch break is excluded', static function (): void {
    $slots = generate_slots('2026-09-22', 1, []);
    foreach ($slots as $slot) {
        assert_true($slot['start_time'] < '13:00' || $slot['start_time'] >= '14:00');
    }
});

test('slot must end by closing time', static function (): void {
    $slots = generate_slots('2026-09-22', 1, ['start' => '16:45', 'end' => '17:00', 'minutes' => 30, 'breaks' => []]);
    assert_count($slots, 0);
});

test('custom twenty minute slots', static function (): void {
    $slots = generate_slots('2026-09-22', 1, ['start' => '09:00', 'end' => '10:00', 'minutes' => 20, 'breaks' => []]);
    assert_count($slots, 3);
    assert_eq($slots[2]['start_time'], '09:40');
    assert_eq($slots[2]['slot_minutes'], 20);
});

test('thirty day span has expected weekday count', static function (): void {
    assert_count(generate_slots('2026-09-01', 30, []), 364);
});

test('today suppresses earlier slots', static function (): void {
    assert_true(!is_bookable('2026-09-22', '09:00', '2026-09-22 14:00:00'));
    assert_true(is_bookable('2026-09-22', '14:00', '2026-09-22 14:00:00'));
});

test('past date is not bookable', static function (): void {
    assert_true(!is_bookable('2026-09-21', '09:00', '2026-09-22 00:00:00'));
});

test('future date is bookable', static function (): void {
    assert_true(is_bookable('2026-09-23', '09:00', '2026-09-22 23:59:59'));
});

test('midnight boundary is deterministic', static function (): void {
    assert_true(is_bookable('2026-09-22', '00:00', '2026-09-22 00:00:00'));
    assert_true(!is_bookable('2026-09-21', '23:59', '2026-09-22 00:00:00'));
});

test('empty break list keeps the whole day', static function (): void {
    assert_count(generate_slots('2026-09-22', 1, ['breaks' => []]), 16);
});

test('half day schedule', static function (): void {
    $slots = generate_slots('2026-09-22', 1, ['start' => '09:00', 'end' => '13:00']);
    assert_count($slots, 8);
    assert_eq($slots[7]['start_time'], '12:30');
});
