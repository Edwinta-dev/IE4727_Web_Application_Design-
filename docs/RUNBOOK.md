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

An isolated `php tools/db_reset.php` reports 5 doctors, 8 patients, 25
appointments and 2 notifications (the slot count varies with Sundays in the
rolling 60-day window). The two notifications are synthetic `logged` rows for
the first future appointment: one for its patient and one for its doctor.
Their bodies identify them as fixtures. Seeding does not attempt mail delivery,
so neither row claims `sent` or `failed`. A fresh admin outbox shows both rows,
newest first, and “Messages generated today: 2”. Booking, cancellation and
rescheduling append live audit rows after reset.
