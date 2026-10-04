<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'doctors.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'stats.php';

require_admin();

$filters = [
    'doctor' => trim((string) ($_GET['doctor'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null && $id > 0 && in_array($action, ['delete_doctor', 'delete_patient'], true)) {
        $role = $action === 'delete_doctor' ? 'doctor' : 'patient';
        if (account_by_id($role, (int) $id) === null) {
            flash('That account could not be found. No account was removed.', 'error');
        } elseif ($action === 'delete_doctor') {
            delete_doctor((int) $id);
            flash('Doctor account and its slots and appointments were removed.', 'success');
        } else {
            delete_patient((int) $id);
            flash('Patient account and its appointments were removed.', 'success');
        }
    } else {
        flash('Choose a valid account to remove. No account was removed.', 'error');
    }
    redirect('/admin/console.php?' . http_build_query($filters));
}

$doctors = admin_doctors($filters);
$patients = admin_patients($filters);
$appointments = admin_search($filters);
$allDoctors = all_doctors();
$weekly = appointments_per_doctor_this_week();
$noShowOverall = overall_no_show_rate();
$noShowDoctors = no_show_rate_per_doctor();
$peakHours = peak_booking_hours();
$leadTime = mean_booking_lead_time($filters);
$weeklyMax = max(array_map(static fn (array $row): int => (int) $row['Bookings'], $weekly) ?: [0]);
$peakMax = max(array_map(static fn (array $row): int => (int) $row['Bookings'], $peakHours) ?: [0]);
$noShowMax = max(array_map(static fn (array $row): float => (float) $row['Rate'], $noShowDoctors) ?: [0]);

$pageTitle = 'Administrator Console';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main class="admin-console">
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1>Administrator console</h1>
            <p>Review clinic accounts, appointments and booking patterns.</p>
        </div>
    </section>
    <?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php'; ?>
    <p><a href="<?= e(url('/admin/outbox.php')) ?>">Open notification outbox</a></p>

    <section class="stats-strip" aria-labelledby="stats-heading">
        <h2 id="stats-heading">Statistics</h2>
        <div class="stat-card"><h3>Appointments per doctor this week</h3>
            <?php if ($weekly === []): ?><p class="empty-state">No appointment statistics are available.</p><?php else: ?><?php foreach ($weekly as $row): $width = $weeklyMax > 0 ? ((int) $row['Bookings'] / $weeklyMax) * 100 : 0; ?>
                <p><?= e($row['DoctorName']) ?>: <?= e((string) $row['Bookings']) ?> bookings</p><div class="stat-bar" style="width: <?= e((string) $width) ?>%" role="img" aria-label="<?= e($row['DoctorName']) ?> bookings"></div>
            <?php endforeach; ?><?php endif; ?>
        </div>
        <div class="stat-card"><h3>No-show rate overall</h3><p><?= e(number_format($noShowOverall['rate'], 1)) ?>% (<?= e((string) $noShowOverall['no_show']) ?> no shows / <?= e((string) $noShowOverall['total']) ?> appointments)</p><div class="stat-bar" style="width: <?= e((string) $noShowOverall['rate']) ?>%"></div><h3>No-show rate per doctor</h3>
            <?php if ($noShowDoctors === []): ?><p class="empty-state">No doctor no-show statistics are available.</p><?php else: ?><?php foreach ($noShowDoctors as $row): $width = $noShowMax > 0 ? ((float) $row['Rate'] / $noShowMax) * 100 : 0; ?><p><?= e($row['DoctorName']) ?>: <?= e(number_format((float) $row['Rate'], 1)) ?>%</p><div class="stat-bar" style="width: <?= e((string) $width) ?>%"></div><?php endforeach; ?><?php endif; ?>
        </div>
        <div class="stat-card"><h3>Peak booking hours</h3>
            <?php foreach ($peakHours as $row): $width = $peakMax > 0 ? ((int) $row['Bookings'] / $peakMax) * 100 : 0; ?><p><?= e(fmt_time(str_pad((string) $row['BookingHour'], 2, '0', STR_PAD_LEFT) . ':00:00')) ?>: <?= e((string) $row['Bookings']) ?> bookings</p><div class="stat-bar" style="width: <?= e((string) $width) ?>%"></div><?php endforeach; ?>
        </div>
        <div class="stat-card"><h3>Mean booking lead time</h3>
            <?php if ($leadTime['mean_days'] === null): ?>
                <p class="empty-state">Unavailable: no usable booking times.</p>
            <?php else: ?>
                <p><?= e(number_format($leadTime['mean_days'], 1)) ?> days</p>
            <?php endif; ?>
            <details class="metric-note"><summary>Calculation details</summary>
            <p>Average elapsed days (24 hours) from booking to appointment.</p>
            <p><?= e((string) $leadTime['valid']) ?> of <?= e((string) ($leadTime['valid'] + $leadTime['invalid'])) ?> appointments included; <?= e((string) $leadTime['invalid']) ?> excluded.</p>
            <?php if ($leadTime['invalid'] > 0): ?><p>Excluded: booking time missing or after appointment.</p><?php endif; ?>
            </details>
        </div>
    </section>

    <section class="console-filters" aria-labelledby="filters-heading"><h2 id="filters-heading">Filters</h2>
        <form method="get" action="<?= e(url('/admin/console.php')) ?>"><label>Doctor <select name="doctor"><option value="">All doctors</option><?php foreach ($allDoctors as $doctor): ?><option value="<?= e((string) $doctor['DoctorID']) ?>"<?= e($filters['doctor'] === (string) $doctor['DoctorID'] ? ' selected' : '') ?>><?= e($doctor['FullName']) ?></option><?php endforeach; ?></select></label>
            <label>Status <select name="status"><option value="">All statuses</option><?php foreach (['Future', 'Cancelled', 'No show', 'Completed', 'Rescheduled'] as $status): ?><option value="<?= e($status) ?>"<?= e($filters['status'] === $status ? ' selected' : '') ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
            <label>From <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label><label>To <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label><button type="submit">Apply filters</button><a href="<?= e(url('/admin/console.php')) ?>">Clear</a>
        </form>
    </section>

    <section class="console-section"><h2 id="patients-heading">Patients</h2>
        <?php if ($patients !== []): ?><p class="table-scroll-hint" id="patients-scroll-hint">Swipe or scroll to see email, phone numbers and Delete controls. Use arrow keys when the table is focused.</p><?php endif; ?>
        <div class="table-scroll console-table-scroll<?= e($patients === [] ? ' is-empty' : '') ?>" role="region" aria-labelledby="patients-heading"<?php if ($patients !== []): ?> aria-describedby="patients-scroll-hint" tabindex="0"<?php endif; ?>><table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead><tbody><?php if ($patients === []): ?><tr><td colspan="5"><p class="empty-state">No patients match these filters.</p></td></tr><?php else: ?><?php foreach ($patients as $patient): ?><tr><td><?= e($patient['FullName']) ?></td><td><?= e($patient['User']) ?></td><td><?= e($patient['Email']) ?></td><td><?= e($patient['Phone']) ?></td><td><form method="post" action="<?= e(url('/admin/console.php')) ?>" data-confirmation="<?= e('Delete patient ' . $patient['FullName'] . '? This removes the patient account and its appointments.') ?>" onsubmit="return confirm(this.dataset.confirmation);"><?= csrf_field() ?><input type="hidden" name="action" value="delete_patient"><input type="hidden" name="id" value="<?= e((string) $patient['PatientID']) ?>"><button class="destructive-action" type="submit">Delete account</button></form></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
    <section class="console-section"><h2 id="doctors-heading">Doctors</h2>
        <?php if ($doctors !== []): ?><p class="table-scroll-hint" id="doctors-scroll-hint">Swipe or scroll to see email, specialties and Delete controls. Use arrow keys when the table is focused.</p><?php endif; ?>
        <div class="table-scroll console-table-scroll<?= e($doctors === [] ? ' is-empty' : '') ?>" role="region" aria-labelledby="doctors-heading"<?php if ($doctors !== []): ?> aria-describedby="doctors-scroll-hint" tabindex="0"<?php endif; ?>><table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Specialty</th><th>Action</th></tr></thead><tbody><?php if ($doctors === []): ?><tr><td colspan="5"><p class="empty-state">No doctors match these filters.</p></td></tr><?php else: ?><?php foreach ($doctors as $doctor): ?><tr><td><?= e($doctor['FullName']) ?></td><td><?= e($doctor['User']) ?></td><td><?= e($doctor['Email']) ?></td><td><?= e($doctor['Specialty']) ?></td><td><form method="post" action="<?= e(url('/admin/console.php')) ?>" data-confirmation="<?= e('Delete doctor ' . $doctor['FullName'] . '? This removes the doctor account, slots and appointments.') ?>" onsubmit="return confirm(this.dataset.confirmation);"><?= csrf_field() ?><input type="hidden" name="action" value="delete_doctor"><input type="hidden" name="id" value="<?= e((string) $doctor['DoctorID']) ?>"><button class="destructive-action" type="submit">Delete account</button></form></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
    <section class="console-section"><h2 id="appointments-heading">Appointments</h2>
        <?php if ($appointments !== []): ?><p class="table-scroll-hint" id="appointments-scroll-hint">Swipe or scroll to see appointment times, patients and status. Use arrow keys when the table is focused.</p><?php endif; ?>
        <div class="table-scroll console-table-scroll<?= e($appointments === [] ? ' is-empty' : '') ?>" role="region" aria-labelledby="appointments-heading"<?php if ($appointments !== []): ?> aria-describedby="appointments-scroll-hint" tabindex="0"<?php endif; ?>><table class="staff-table appointment-table"><thead><tr><th>Date/time</th><th>Doctor</th><th>Patient</th><th>Status</th></tr></thead><tbody><?php if ($appointments === []): ?><tr><td colspan="4"><p class="empty-state">No appointments match these filters.</p></td></tr><?php else: ?><?php foreach ($appointments as $appointment): ?><tr><td class="nowrap"><time datetime="<?= e(str_replace(' ', 'T', (string) $appointment['appointmentDateTime'])) ?>"><?= e(fmt_date((string) $appointment['appointmentDateTime'])) ?> at <?= e(fmt_time((string) $appointment['appointmentDateTime'])) ?></time></td><td><?= e($appointment['DoctorName']) ?></td><td><?= e($appointment['PatientName']) ?></td><td class="nowrap"><span class="status-label status-<?= e(strtolower(str_replace(' ', '-', (string) $appointment['Status']))) ?>"><?= e($appointment['Status']) ?></span></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
