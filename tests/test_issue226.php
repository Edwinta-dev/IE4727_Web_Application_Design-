<?php

declare(strict_types=1);

// #226: the booking day tabs wrap so every day in the window is visible.
$css = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
// The stylesheet holds an older .day-tabs rule and a newer one after it; the last one wins.
preg_match_all('/^\.day-tabs\s*\{([^}]*)\}/m', $css, $dayTabRules);
$winning = (string) end($dayTabRules[1]);
assert_true(preg_match('/flex-wrap:\s*wrap/', $winning) === 1, 'day tabs wrap onto extra rows');
assert_true(strpos($winning, 'overflow-x') === false && strpos($winning, 'scrollbar') === false, 'the winning rule sets no sideways scrolling');
$js = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/booking-slots.js');
assert_true(strpos($js, 'scrollLeft') === false, 'no script scrolls the day tabs');
echo "OK: #226 day tabs wrap\n";
