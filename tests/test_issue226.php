<?php

declare(strict_types=1);

// #226: the booking day tabs wrap so every day in the window is visible.
$css = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_true(preg_match('/^\.day-tabs\s*\{[^}]*flex-wrap:\s*wrap/m', $css) === 1, 'day tabs wrap onto extra rows');
assert_true(preg_match('/^\.day-tabs\s*\{[^}]*overflow-x:\s*auto/m', $css) === 0, 'day tabs no longer scroll sideways');
$js = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/booking-slots.js');
assert_true(strpos($js, 'scrollLeft') === false, 'no script scrolls the day tabs');
echo "OK: #226 day tabs wrap\n";
