# Runbook

## XAMPP deployment

Copy the repository folders beneath XAMPP's `htdocs` directory so both
versions are available at the same time:

```text
htdocs/clinic-base/
htdocs/clinic-plus/
```

Both folders use the same `ie4727db` database. Configure each ignored
`config.local.php` with the local XAMPP credentials if they differ from the
defaults, then start Apache and MariaDB.

## Demo sequence

1. Open `http://localhost/clinic-base/` and demonstrate the completed base
   flow first: public doctor browsing, patient login, slot booking, and the
   doctor/admin pages.
2. Leave the base tab open as the frozen baseline, then switch to
   `http://localhost/clinic-plus/` to demonstrate enhancements against the
   same database and seed accounts.
3. Use the same seeded credentials in either folder (`Password123` for demo
   doctor and patient accounts). Do not reset the database between the two
   demonstrations unless restarting the demo from the beginning.

`clinic-base/` is frozen for enhancement work. Any defect fix discovered
after this fork must be applied to both folders so the two deployments remain
independent but equivalent at their shared baseline.

## Demo database credentials

The demo seed in `schema/003_seed.sql` uses `Password123` for every doctor and
patient account. Each `HashPass` value is a genuine bcrypt output produced by
PHP's `password_hash('Password123', PASSWORD_DEFAULT)`; the plaintext password
is never stored in the database.

An isolated `php tools/db_reset.php` reports 5 doctors, 8 patients, 27
appointments and 2 notifications (the slot count varies with Sundays in the
rolling 60-day window). The two notifications are synthetic `logged` rows for
the first future appointment: one for its patient and one for its doctor.
Their bodies identify them as fixtures. Seeding does not attempt mail delivery,
so neither row claims `sent` or `failed`. A fresh admin outbox shows both rows,
newest first, and “Messages generated today: 2”. Booking, cancellation and
rescheduling append live audit rows after reset.


For the attendance demo, sign in as `drsmith` / `Password123` and select the
most recent clinic day before today on the day board (yesterday, or Saturday
when today is Monday). Alex Tan at 09:00 and Bethany Ong at 09:30 are pending
`Future` appointments whose start times have passed. Mark one Completed and
one No show; their linked slots remain Booked. The other 25 examples remain:
18 Completed, 2 No show, 1 Cancelled and 4 upcoming Future appointments.
The two pending rows deliberately retain Future until the doctor records
attendance; historical Future records are never automatically completed.

The seed captures one Asia/Singapore now (SQL session offset +08:00). Slots
are created before booking, and historical appointments are booked seven days
before their start. Future bookings are created seven days before seed now.
Use only the existing isolated `php tools/db_reset.php` workflow for tests;
this change does not authorize a live reset. No mail is sent during seeding.
