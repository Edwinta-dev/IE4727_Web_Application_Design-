<?php

declare(strict_types=1);

// This fragment deliberately uses the same server-side variables and markup
// as book.php. htmx changes the transport, not the booking transaction.
?>
<?php if ($doctor === null): ?>
    <p class="empty-state">Choose a doctor to view their schedule.</p>
<?php else: ?>
    <h2><?= e((string) $doctor['FullName']) ?> &mdash; <?= e(fmt_date($selectedDate)) ?></h2>
    <p>Every listed slot is shown. Only a future free slot can be selected.</p>
    <div class="schedule-grid" aria-label="Appointment schedule">
<?php if ($slots === []): ?>
        <p class="empty-state"><?= e($pastDateRequested ? 'Past dates cannot be booked.' : $doctor['FullName'] . ' is not available on this date.') ?></p>
<?php else: ?>
<?php if (!$hasFreeSlot): ?>
        <p class="empty-state">This day is fully booked or has no future free slots. Please choose another day.</p>
<?php endif; ?>
<?php foreach ($slots as $slot):
    $slotDateTime = new DateTimeImmutable((string) $slot['SlotDateTime']);
    $isPast = $slotDateTime <= $now;
    $status = (string) ($slot['Status'] ?? 'Blocked');
    $stateClass = match ($status) {
        'Booked' => 'taken', 'Blocked' => 'blocked', default => $isPast ? 'past' : 'free',
    };
?>
        <div class="slot <?= e($stateClass) ?>">
            <span class="slot-time"><?= e(fmt_time((string) $slot['SlotDateTime'])) ?></span>
<?php if ($stateClass === 'free'): ?>
            <form method="post" action="<?= e(url($reschedule === null ? '/actions/book.php' : '/actions/appointment.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="slot_id" value="<?= e((string) $slot['slotID']) ?>">
                <input type="hidden" name="doctor" value="<?= e((string) $doctorId) ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <input type="hidden" name="from" value="<?= e($fromTime) ?>">
                <input type="hidden" name="to" value="<?= e($toTime) ?>">
<?php if ($reschedule !== null): ?>
                <input type="hidden" name="appointment_id" value="<?= e((string) $reschedule['appointmentID']) ?>">
                <input type="hidden" name="reschedule" value="1">
                <input type="hidden" name="actor" value="<?= e($rescheduleActor) ?>">
<?php endif; ?>
                <p>Confirm this appointment with <?= e((string) $doctor['FullName']) ?> on <?= e(fmt_date($selectedDate)) ?> at <?= e(fmt_time((string) $slot['SlotDateTime'])) ?>.</p>
                <label for="reason-<?= e((string) $slot['slotID']) ?>">Reason for visit</label>
                <input id="reason-<?= e((string) $slot['slotID']) ?>" name="reason" type="text" maxlength="255" required>
                <button type="submit"><?= e($reschedule === null ? 'Confirm booking' : 'Confirm reschedule') ?></button>
            </form>
<?php else: ?><span class="slot-state"><?= e($stateClass === 'taken' ? 'Booked' : ucfirst($stateClass)) ?></span>
<?php endif; ?></div>
<?php endforeach; ?>
<?php endif; ?></div>
<?php endif; ?>
