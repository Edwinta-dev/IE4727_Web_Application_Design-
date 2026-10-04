# Final demo and release handoff

This is the timed, server-rendered demonstration for the Theme 4 clinic portal.
It is designed for a fresh XAMPP installation and takes about 12 minutes. Use
Apache and MariaDB from XAMPP; do not use a hosted mail service or a second
application route.

## Before the timer starts (0:00)

1. Copy the repository into the XAMPP `htdocs` directory and create the
   `ie4727db` database if it does not already exist.
2. If local MariaDB credentials differ from the defaults, create the ignored
   `clinic-base/config.local.php` using [CONFIG.md](CONFIG.md). Never commit it.
3. From the repository root run:

   ```text
   php tools/db_reset.php --production
   php tests/run.php
   powershell -ExecutionPolicy Bypass -File tools/verify_clean.ps1
   ```

   Expected output includes `Database reset complete`, the full-suite pass line,
   and `OK: clean-checkout verification complete`. Stop and fix any failed
   command before the browser demonstration.

The seeded doctor and patient accounts use usernames shown in the database
seed and the shared password `Password123`. The admin username and password are
the local `ADMIN_USER` and `ADMIN_HASH` configuration values; do not record
those values in evidence. Registration below uses a new, temporary patient
username and a local-only email address.

## Timed walkthrough

### 0:00–1:30 — registration and login

Open `http://localhost/<repo>/register.php`, submit a new patient with a name,
username, local email, gender, phone, and allergy values, then sign in through
the login form. Expected result: the registration succeeds, the request
redirects, and `patient/home.php` displays the authenticated patient's empty
appointment state. This demonstrates validation, bcrypt credentials, CSRF, and
the JSON `Allergies` round trip.

### 1:30–3:00 — doctors and slots

Open `doctors.php`, inspect a seeded doctor profile, and select a future
available slot. Point out that the displayed date and time come from the
single `SlotDateTime` value. In a second browser session, sign in as a doctor,
open `doctor/schedule.php`, create one future slot, block it, and reopen it.
Expected result: the slot states persist after each POST-Redirect-GET refresh;
past, booked, blocked, and available states remain distinct.

### 3:00–5:00 — booking and double-book protection

As the temporary patient, book the selected future slot. Expected output is a
redirected confirmation and one `Future` appointment on `patient/home.php`.
Attempt the same slot as a second patient. Expected output is a rejection and
the original appointment and `Booked` slot remain intact. This demonstrates
the transaction and the database `uq_slot` guard.

### 5:00–6:30 — cancellation and rescheduling

From the patient dashboard, cancel the temporary appointment, then book a
different available future slot and use the reschedule action if present in the
dashboard. Expected output: the old appointment is no longer bookable, the new
appointment points to its exact slot, and the status/history shows the supported
`Cancelled`/`Rescheduled` values without a duplicate booking.

### 6:30–8:30 — doctor visit record

As the seeded doctor who owns the appointment, open `doctor/home.php` and then
`doctor/visit.php`. Save diagnosis, prescription, treatment, a `FollowUp` flag,
remarks, and a supported completed outcome. Expected output: the POST redirects
back to the visit page and the saved values are visible. A doctor account must
not see or edit another doctor's appointment.

### 8:30–10:00 — notifications

Repeat one appointment mutation that sends a notification, such as booking or
rescheduling. Sign in as the patient and confirm the notification message is
visible in the patient flow. Expected database evidence is a row written first
with `recipient`, `Subject`, `Body`, and the related `appointmentID`; local
delivery may be `sent` or `failed` and must not make the user operation fail.

### 10:00–11:00 — admin console and outbox

Sign in with the configured admin credentials, open `admin/console.php`, then
`admin/outbox.php`. Expected output: account/operational counts, notification
rows with `recipient` and `deliveryStatus` (`logged`, `sent`, or `failed`), and
newest rows first. Immediately after reset there are two synthetic `logged`
rows, one for a patient and one for a doctor; both say they were not delivered.
The page shows “Messages generated today: 2” until real actions add rows.
If demonstrating doctor cleanup, use only the isolated reset
database and record before/after counts; never delete a seeded doctor from a
shared or production database.

### 11:00–12:00 — release gate and evidence handoff

Run the commands below from a clean checkout. Expected output is zero exit
status for each command.

```text
php tests/test_release_handoff.php
php tests/run.php
php tools/db_reset.php
php tests/test_verify_clean.php
powershell -ExecutionPolicy Bypass -File tools/verify_clean.ps1
```

The final verifier must end with `OK: clean-checkout verification complete`.
The reset command intentionally targets the isolated test database unless
`--production` is supplied.

## Evidence checklist

Keep the following outside `clinic-base/mail/` and exclude credentials,
`config.local.php`, `.env`, mailbox files, and generated personal data:

- command transcript containing the five release-gate commands and their exit
  status;
- registration/login and patient booking/reschedule screenshots;
- doctor schedule and visit screenshots showing POST-Redirect-GET results;
- patient notification and admin outbox screenshots showing `recipient` and
  delivery status;
- isolated database query output showing the exact slot, appointment,
  notification, and (if used) doctor cascade rows.

Map the screenshots and transcript to [ACCEPTANCE_MATRIX.md](ACCEPTANCE_MATRIX.md)
criteria C01–C32. Automated checks are authoritative; screenshots supplement
them and do not replace a failed test. The fixed schema vocabulary remains
`SlotDateTime`, `appointmentDateTime`, JSON `Allergies`, `FollowUp` 0/1, and
`recipient`.
