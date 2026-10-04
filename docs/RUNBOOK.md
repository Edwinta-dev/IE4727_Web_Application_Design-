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

## Base local admin setup (#142)

Admin credentials are disabled until configured; the seeded doctor/patient
password does not enable an admin account. From a fresh clone, run this in
PowerShell at the repository root and choose your own local password:

```powershell
$adminPassword = Read-Host 'Local admin password (8-72 bytes)' -AsSecureString
[System.Net.NetworkCredential]::new('', $adminPassword).Password | php tools/setup_admin.php admin
Remove-Variable adminPassword
```

In Bash, use `read -r -s admin_password`, then
`printf '%s\n' "$admin_password" | php tools/setup_admin.php admin` and
`unset admin_password`. The script creates the ignored
`clinic-base/config.local.php` containing only `ADMIN_USER` and a PHP password
hash. It does not set `DB_NAME`, reset a database, or change mail delivery.
Never commit this file. Run setup before copying `clinic-base/` into htdocs,
or copy the generated ignored file into the deployed base folder afterward.
Sign in at `/clinic-base/index.php` as `admin` with your chosen password, then
open Outbox. Opening the protected URL while signed out displays a sign-in
message and preserves the outbox return URL.

The script refuses to overwrite an existing local config. In that case, keep
its database/mail settings, generate a hash with
`php -r '$p = rtrim(fgets(STDIN), "\r\n"); echo password_hash($p, PASSWORD_DEFAULT), PHP_EOL;'`
(password on stdin), and set `ADMIN_USER`/`ADMIN_HASH` in that ignored file.
Do not add a fixed `DB_NAME`: test tools must resolve `ie4727db_test`.

Issue acceptance (synthetic rows only, no live reset or mail delivery):

```powershell
php tests/run.php test_admin_setup
php tests/run.php test_outbox
php tools/db_reset.php
node tests/ui_admin_outbox.mjs
php tests/run.php
```

The UI check temporarily creates a local admin config through the setup
script when absent, removes only that file afterward, and verifies login,
guard feedback, newest-first rows, body disclosure, status filters, today's
count, and empty state at 3, 7 and 12 synthetic messages. If a local config
already exists, set `UI_ADMIN` and `UI_PASSWORD` to its admin credentials.
All fixture writes use `ie4727db_test`; local mail is disabled.

## Base local mail delivery

When PHP's `SMTP=localhost` has no local listener, each `mail()` attempt can
delay a booking, reschedule or cancellation by about two seconds. To keep the
base demo responsive, add this to the ignored `clinic-base/config.local.php`:

```php
define('MAIL_DELIVERY', 'off');
```

Alternatively set the `MAIL_DELIVERY=off` environment variable in the PHP
process. Local configuration takes precedence. The default is `on`; remove
the override or set it to `on` to resume delivery through local XAMPP
`mailtodisk`. This option applies to `clinic-base/` only.

With delivery off, `send_mail()` still writes each notification first,
including its appointment link, then returns without calling `mail()`.
Rows remain `logged` and are visible in the admin outbox. With delivery on,
the row is written first and delivery updates it to `sent` or `failed`;
failure remains non-fatal. Do not configure an external mail service.

Acceptance checks (isolated `ie4727db_test` only):

```powershell
php tests/run.php test_mail_delivery
$env:CLINIC_DB_NAME='ie4727db_test'
$env:MAIL_DELIVERY='off'
node tests/ui_mail_delivery.mjs
php tests/run.php
```

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
before their start. The four upcoming bookings are created exactly eight days
before their start, also before seed now. All 27 appointments have valid
booking times: 23 at seven days and four at eight days, totalling 193 elapsed
days. Mean lead time is 193/27 = 7.148148 days (displayed as 7.1 days), with
27/27 included and zero excluded, independent of reset time or weekday.
Use only the existing isolated `php tools/db_reset.php` workflow for tests;
this change does not authorize a live reset. No mail is sent during seeding.

### Booking lead-time definition

The admin metric uses the current doctor, status and appointment-date filters.
Every status represents a booking, including cancellations and pending outcomes.
It averages `TIMESTAMPDIFF(SECOND, CreatedAt, appointmentDateTime) / 86400`
over records with a non-null `CreatedAt` at or before the scheduled start.
The DATETIME values are Asia/Singapore clinic time; a day is 24 elapsed hours,
and the display rounds to one decimal. Same-time bookings contribute zero.
Missing or reversed booking times are counted as excluded, never made positive
or clamped to zero. Included plus excluded is the selected appointment total.
An empty or all-invalid selection displays Unavailable, rather than zero days.

Only synthetic reset fixtures receive the documented lead times. Do not infer
or backfill booking timestamps in the owner's live records. Invalid regression
samples live in the isolated test database: three valid leads (0, 1, 3 days)
and two excluded records (one reversed, one missing), mean 4/3 days, 3/5
included. `tests/test_stats.php` checks those cases and empty/all-invalid
selections; the authenticated UI regression checks their displayed coverage.
