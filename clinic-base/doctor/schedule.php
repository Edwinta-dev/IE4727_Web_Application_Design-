<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'slots.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'booking.php';

require_doctor();

$user = current_user();
$doctorId = (int) $user['id'];
$today = new DateTimeImmutable('today');
$managementEnd = $today->modify('+' . (SCHEDULE_MANAGEMENT_DAYS - 1) . ' days');

$dateInput = $_GET['date'] ?? '';
$selectedDate = is_string($dateInput) ? $dateInput : '';
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
$dateError = '';
if ($selectedDate !== '' && (
    $parsedDate === false
    || $parsedDate->format('Y-m-d') !== $selectedDate
    || $parsedDate < $today
    || ($parsedDate > $managementEnd && !has_owned_slots_on_day($doctorId, $selectedDate))
)) {
    $dateError = 'That date cannot be opened. Choose a future date within the ' . SCHEDULE_MANAGEMENT_DAYS . '-day management window, or a later date with your existing slots.';
}
if ($selectedDate === '' || $dateError !== '') {
    $selectedDate = $today->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    try {
        if ($action === 'generate') {
            $from = is_string($_POST['start_date'] ?? null) ? $_POST['start_date'] : '';
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
            $days = filter_var($_POST['days'] ?? null, FILTER_VALIDATE_INT);
            $startTime = is_string($_POST['start_time'] ?? null) ? $_POST['start_time'] : '';
            $endTime = is_string($_POST['end_time'] ?? null) ? $_POST['end_time'] : '';
            $minutes = filter_var($_POST['slot_length'] ?? null, FILTER_VALIDATE_INT);
            $skipDays = $_POST['skip_days'] ?? [];
            $skipDays = is_array($skipDays) ? array_map('intval', $skipDays) : [];
            $skipDays = array_values(array_unique(array_filter($skipDays, static fn (int $day): bool => $day >= 0 && $day <= 6)));

            if (
                $start === false
                || $start->format('Y-m-d') !== $from
                || $days === false
                || $days < 1
                || $days > SCHEDULE_DAYS
                || !preg_match('/^\d{2}:\d{2}$/', $startTime)
                || !preg_match('/^\d{2}:\d{2}$/', $endTime)
                || $minutes === false
                || !in_array($minutes, [15, 30, 60], true)
            ) {
                throw new InvalidArgumentException('Please provide a valid date, range and working hours.');
            }
            if ($start < $today || $start->modify('+' . ($days - 1) . ' days') > $managementEnd) {
                throw new InvalidArgumentException('Generate slots only from today through ' . $managementEnd->format('Y-m-d') . ' (' . SCHEDULE_MANAGEMENT_DAYS . '-day management window).');
            }

            $created = regenerate_schedule($doctorId, $from, $days, [
                'start' => $startTime,
                'end' => $endTime,
                'minutes' => $minutes,
                'skip_weekdays' => $skipDays,
                'breaks' => [],
            ]);
            flash(
                $created === 0
                    ? 'No new slots were created because all generated slots already exist.'
                    : $created . ' new schedule slot' . ($created === 1 ? '' : 's') . ' created.',
                'success'
            );
            redirect('/doctor/schedule.php?date=' . rawurlencode($from));
        }

        if ($action === 'toggle') {
            $slotId = filter_var($_POST['slot_id'] ?? null, FILTER_VALIDATE_INT);
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            if ($slotId === false || !in_array($status, ['Available', 'Blocked'], true)) {
                throw new InvalidArgumentException('Invalid slot update.');
            }

            $slot = find_slot((int) $slotId);
            if ($slot === null || (int) $slot['DoctorID'] !== $doctorId) {
                throw new RuntimeException('The slot was not found in your schedule.');
            }
            if (!schedule_slot_editable($slot, $doctorId)) {
                throw new RuntimeException('This slot has already started and can no longer be changed.');
            }
            if ($status === 'Blocked') {
                if ((string) $slot['Status'] === 'Booked' && ($_POST['confirm_booking'] ?? '') !== '1') {
                    throw new InvalidArgumentException('Confirm that blocking this slot cancels the booking and notifies both parties.');
                }
                if (!block_slot((int) $slotId, $doctorId)) {
                    throw new RuntimeException('The slot could not be blocked.');
                }
                flash('The slot was blocked. Any booked appointment was cancelled and both parties were notified.', 'success');
            } else {
                if (!unblock_slot((int) $slotId, $doctorId)) {
                    throw new RuntimeException('Only a blocked slot can be made available.');
                }
                flash('The slot is available again.', 'success');
            }
            redirect('/doctor/schedule.php?date=' . rawurlencode($selectedDate));
        }

        throw new InvalidArgumentException('Unknown schedule action.');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'error');
        redirect('/doctor/schedule.php?date=' . rawurlencode($selectedDate));
    }
}

$gridStart = $parsedDate !== false && $selectedDate !== $today->format('Y-m-d') && $parsedDate > $today->modify('+29 days')
    ? $parsedDate : $today;
$gridEnd = $gridStart->modify('+29 days');
$countsByDate = slot_counts_for_range($doctorId, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
$slots = slots_for_day($doctorId, $selectedDate);

$pageTitle = 'Doctor Schedule Editor';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main id="schedule-editor">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1><img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" width="40" height="40" loading="eager" decoding="async" alt="" class="page-intro-mark">Schedule editor</h1>
            <p>Generate up to 30 days at a time through <?= e(fmt_date($managementEnd->format('Y-m-d'))) ?>. Existing later slots remain available to manage.</p>
        </div>
    </section>

    <?php flash_render(); ?>
    <?php if ($dateError !== ''): ?><p class="flash flash-error" role="alert"><?= e($dateError) ?> Showing <?= e(fmt_date($selectedDate)) ?>.</p><?php endif; ?>

    <section class="schedule-generator" aria-labelledby="generate-heading">
        <h2 id="generate-heading">Generate schedule</h2>
        <form method="post" action="<?= e(url('/doctor/schedule.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">
            <label for="start-date">Start date</label>
            <input id="start-date" name="start_date" type="date" min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($managementEnd->format('Y-m-d')) ?>" value="<?= e($selectedDate) ?>" required>
            <label for="days">Number of days</label>
            <input id="days" name="days" type="number" min="1" max="<?= e((string) SCHEDULE_DAYS) ?>" value="30" required>
            <label for="start-time">Working hours from</label>
            <input id="start-time" name="start_time" type="time" value="09:00" required>
            <label for="end-time">Working hours to</label>
            <input id="end-time" name="end_time" type="time" value="17:00" required>
            <label for="slot-length">Slot length</label>
            <select id="slot-length" name="slot_length">
                <option value="15">15 minutes</option>
                <option value="30" selected>30 minutes</option>
                <option value="60">60 minutes</option>
            </select>
            <fieldset class="days-to-skip">
                <legend>Days to skip</legend>
                <?php foreach ([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayNumber => $dayName): ?>
                    <label><input type="checkbox" name="skip_days[]" value="<?= e((string) $dayNumber) ?>"<?= e($dayNumber === 0 ? ' checked' : '') ?>> <?= e($dayName) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit">Generate slots</button>
        </form>
    </section>

    <section class="month-grid" aria-labelledby="month-heading">
        <h2 id="month-heading">30 days from <?= e(fmt_date($gridStart->format('Y-m-d'))) ?></h2>
        <form method="get" action="<?= e(url('/doctor/schedule.php')) ?>">
            <?= csrf_field() ?>
            <label for="schedule-date">Open a date</label>
            <input id="schedule-date" name="date" type="date" min="<?= e($today->format('Y-m-d')) ?>" value="<?= e($selectedDate) ?>" required>
            <button type="submit">Show date</button>
        </form>
        <div class="schedule-month-grid">
            <?php for ($offset = 0; $offset < 30; $offset++): ?>
                <?php
                $day = $gridStart->modify('+' . $offset . ' days');
                $dayDate = $day->format('Y-m-d');
                $dayCounts = $countsByDate[$dayDate] ?? ['Available' => 0, 'Booked' => 0, 'Blocked' => 0];
                ?>
                <article class="schedule-day<?= e($dayDate === $selectedDate ? ' selected' : '') ?>">
                    <h3><a href="<?= e(url('/doctor/schedule.php?date=' . rawurlencode($dayDate))) ?>"><?= e(fmt_date($dayDate)) ?></a></h3>
                    <p class="slot-count available">Available: <?= e((string) $dayCounts['Available']) ?></p>
                    <p class="slot-count booked">Booked: <?= e((string) $dayCounts['Booked']) ?></p>
                    <p class="slot-count blocked">Blocked: <?= e((string) $dayCounts['Blocked']) ?></p>
                </article>
            <?php endfor; ?>
        </div>
    </section>

    <section class="day-view" aria-labelledby="day-heading">
        <h2 id="day-heading">Slots for <?= e(fmt_date($selectedDate)) ?></h2>
        <?php if ($slots === []): ?>
            <p class="empty-state">No slots have been generated for this day.</p>
        <?php else: ?>
            <ul class="slot-list">
                <?php foreach ($slots as $slot): ?>
                    <?php
                    $status = (string) $slot['Status'];
                    $stateClass = $status === 'Available' ? 'free' : ($status === 'Booked' ? 'taken' : 'blocked');
                    $timeClass = !schedule_slot_editable($slot, $doctorId) ? 'past' : '';
                    ?>
                    <li class="slot <?= e($stateClass) ?><?= e($timeClass !== '' ? ' ' . $timeClass : '') ?>">
                        <span class="slot-time"><?= e(fmt_time((string) $slot['SlotDateTime'])) ?></span>
                        <span class="slot-status status-label status-<?= e($timeClass !== '' ? 'past' : strtolower($status)) ?>"><?= e($timeClass !== '' ? 'Past' : ($status === 'Blocked' ? 'Unavailable' : $status)) ?></span>
                        <?php if ($timeClass !== ''): ?>
                            <span class="slot-readonly">This slot has started; availability can no longer be changed.</span>
                        <?php elseif ($status === 'Booked'): ?>
                            <form method="post" action="<?= e(url('/doctor/schedule.php?date=' . rawurlencode($selectedDate))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                                <input type="hidden" name="status" value="Blocked">
                                <label><input type="checkbox" name="confirm_booking" value="1" required> Cancel the appointment and notify the patient and doctor</label>
                                <button type="submit" onclick="return confirm('This booked slot will cancel the appointment and notify both parties. Continue?');">Block booked slot</button>
                            </form>
                        <?php elseif ($status === 'Blocked'): ?>
                            <form method="post" action="<?= e(url('/doctor/schedule.php?date=' . rawurlencode($selectedDate))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                                <input type="hidden" name="status" value="Available">
                                <button type="submit">Make available</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/doctor/schedule.php?date=' . rawurlencode($selectedDate))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                                <input type="hidden" name="status" value="Blocked">
                                <button type="submit">Block slot</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
