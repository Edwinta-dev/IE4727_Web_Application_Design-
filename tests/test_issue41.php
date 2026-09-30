<?php
declare(strict_types=1);

$book = file_get_contents(dirname(__DIR__) . '/clinic-plus/book.php');
$fragment = file_get_contents(dirname(__DIR__) . '/clinic-plus/slots_fragment.php');
if ($book === false || $fragment === false) {
    throw new RuntimeException('issue #41 files are missing');
}
foreach (["hx-get=\"<?= e(url('/book.php')) ?>\"", 'hx-target="#schedule-panel"', 'hx-swap="innerHTML"', 'htmx.org'] as $needle) {
    if (strpos($book, $needle) === false) throw new RuntimeException("book page missing {$needle}");
}
foreach (['slots_for_day', 'csrf_field', 'slot-time', 'book.php'] as $needle) {
    if (strpos($book . $fragment, $needle) === false) throw new RuntimeException("fragment flow missing {$needle}");
}
echo "OK: issue #41 async booking checks\n";
