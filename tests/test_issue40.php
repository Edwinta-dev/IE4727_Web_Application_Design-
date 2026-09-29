<?php
declare(strict_types=1);
$events = file_get_contents(dirname(__DIR__) . '/clinic-plus/events.php');
$board = file_get_contents(dirname(__DIR__) . '/clinic-plus/doctor/board.php');
$model = file_get_contents(dirname(__DIR__) . '/clinic-plus/models/appointments.php');
$decisions = file_get_contents(dirname(__DIR__) . '/docs/DECISIONS.md');
if ($events === false || $board === false || $model === false || $decisions === false) throw new RuntimeException('issue #40 files are missing');
foreach (['session_write_close();', 'set_time_limit(0);', 'text/event-stream', '45', 'latest_appointment_update_for_doctor'] as $needle) if (strpos($events, $needle) === false) throw new RuntimeException("events endpoint missing {$needle}");
foreach (['EventSource(', 'board-update', 'window.location.reload()', 'require_doctor();'] as $needle) if (strpos($board, $needle) === false) throw new RuntimeException("board missing {$needle}");
if (strpos($model, '`updatedAt`') === false || strpos($decisions, 'Server-Sent Events') === false) throw new RuntimeException('updatedAt polling or SSE decision is missing');
echo "OK: issue #40 live board checks\n";
