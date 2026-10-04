<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

$visitNow = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
foreach (['Future', 'Rescheduled', 'Completed', 'Cancelled', 'No show'] as $status) {
    foreach ([-1, 0, 1] as $offset) {
        $visit = ['DoctorID' => 1, 'Status' => $status,
            'appointmentDateTime' => $visitNow->modify(($offset >= 0 ? '+' : '') . $offset . ' second')->format('Y-m-d H:i:s')];
        $expected = in_array($status, ['Cancelled', 'No show'], true) ? 'disallowed_status'
            : ($offset > 0 ? 'not_started' : 'editable');
        assert_eq(visit_notes_state($visit, 1, $visitNow), $expected, "$status offset $offset explanation");
        assert_eq(visit_notes_editable($visit, 1, $visitNow), $expected === 'editable', 'UI and save eligibility agree');
        assert_eq(visit_notes_state($visit, 2, $visitNow), 'foreign_doctor', 'ownership takes precedence');
        assert_eq(visit_notes_editable($visit, 2, $visitNow), false, 'foreign doctor denied');
    }
}
echo "PASS: visit state precedence and start boundary\n";
