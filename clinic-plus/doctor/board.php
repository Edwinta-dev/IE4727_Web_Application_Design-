<?php
declare(strict_types=1);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_doctor();
$user = current_user();
$doctorId = (int) $user['id'];
$dateInput = $_GET['date'] ?? date('Y-m-d');
$date = is_string($dateInput) ? $dateInput : date('Y-m-d');
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) $date = date('Y-m-d');
$appointments = appointments_for_doctor_day($doctorId, $date);
$latest = latest_appointment_update_for_doctor($doctorId, $date);
$pageTitle = 'Live Clinic Board';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="live-board"><section class="page-intro"><h1>Live clinic board</h1><p>Appointment changes appear automatically while this board is open.</p><img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal" class="page-intro-image"></section>
<form class="day-board-filter" method="get" action="/doctor/board.php"><label for="day">Schedule date</label><input id="day" name="date" type="date" value="<?= e($date) ?>" required><button type="submit">Show day</button></form>
<p id="board-status" role="status">Live updates connected.</p><section aria-label="Live appointments for <?= e($date) ?>"><h2><?= e(fmt_date($date)) ?></h2>
<?php if ($appointments === []): ?><p class="empty-state">There are no appointments for this day.</p><?php else: ?><table class="day-board"><thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th></tr></thead><tbody><?php foreach ($appointments as $appointment): ?><tr><td><?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></td><td><?= e((string) $appointment['PatientName']) ?></td><td><?= e((string) ($appointment['Remarks'] ?? '')) ?></td><td><?= e((string) $appointment['Status']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section></main>
<script>
const boardStream = new EventSource('/events.php?date=<?= e(rawurlencode($date)) ?>&since=<?= e(rawurlencode((string) ($latest ?? ''))) ?>');
boardStream.addEventListener('board-update', () => window.location.reload());
boardStream.onerror = () => { document.getElementById('board-status').textContent = 'Reconnecting to live updates…'; };
</script>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
