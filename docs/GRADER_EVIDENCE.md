# Grader evidence pack

## Command evidence

Run from the repository root in this order:

```text
php tests/run.php
PASS: issue #1 scaffold checks

php tests/test_acceptance_matrix.php
OK: issue #59 acceptance matrix covers all criteria and pages

php tools/db_reset.php
Database reset complete

php tests/test_verify_clean.php
PASS: issue #58 verifier checks
```

`db_reset.php` uses the isolated test database by default. Use
`--production` only when intentionally resetting the configured application
database. If MariaDB is unavailable, database evidence is blocked; do not
replace it with a claim of success.

For a full local gate, also run `php -l` on every touched PHP file and
`php tests/run.php test_slots` when that focused test exists. The expected
result is an exit code of zero and the named `PASS`/`OK` line.

## Role demonstration script

These are short, repeatable XAMPP/Apache demonstrations. They are deliberately
server-rendered form flows; no browser automation or external mail service is
required. Use the seeded password `Password123` and the configured admin
credentials from `clinic-base/config.local.php`.

### Doctor

1. Sign in as a seeded doctor.
2. Open `doctor/home.php` and confirm only that doctor's appointments appear.
3. Open `doctor/schedule.php`, create or block a future slot, then refresh and
   confirm the state is persisted.
4. Open `doctor/visit.php`, save visit details, and confirm the POST redirects
   before the updated record is displayed.

Evidence: screenshot or screen recording plus the relevant appointment/slot
rows from the isolated database; never include credentials in the artefact.

### Patient

1. Register or sign in as a seeded patient.
2. Open `doctors.php`, inspect a profile, and choose a future available slot.
3. Book it, confirm the redirect and confirmation, then open
   `patient/home.php` to verify the appointment.
4. Attempt the same slot from a second patient; the booking must be rejected
   and the original appointment must remain intact.

Evidence: the two account views and the slot/appointment records from the
isolated database.

### Admin

1. Sign in with `ADMIN_USER` and `ADMIN_HASH` configured locally.
2. Open `admin/console.php` and verify the account/operational summary.
3. Open `admin/outbox.php` and verify notification rows show `recipient` and
   `deliveryStatus`.
4. Exercise only the documented cleanup action in the isolated database and
   verify the doctor cascade; do not run it against production data.

Evidence: console and outbox views plus before/after row counts.

## Evidence handling

Keep command transcripts and screenshots outside the application `mail/`
directory. Do not commit `config.local.php`, `.env`, credentials, mailbox
files, or generated personal data. A manual demo supplements the automated
gate; it never substitutes for a failing automated test.
