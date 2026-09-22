# AGENTS.md — read this before every task

Clinic Appointment Portal · NTU **IE4727 Web Application Design** · Theme 4.
This file is the contract. An issue never overrides it. If an issue and this file disagree, **this file wins and you open a comment on the issue saying so.**

---

## 0. The database and the app already exist

This is **not a greenfield build.** The current, cleaned MariaDB schema (`ie4727db`) is committed as `schema/001_schema.sql`, and partly-built PHP/HTML already lives in the repo. Two consequences:

- **The schema is fixed. Do not redesign it.** You may only add to it, and only through the single additive `schema/002_migrate.sql` (issue #4). Never drop or rename an existing column — the built app references these exact names.
- **Files may already exist.** Before creating one, check. If it exists, **refactor it to satisfy the issue and keep whatever markup already works** — do not blank it and start over.

The schema uses PascalCase names, single `DATETIME` columns, and per-role credential tables. All of that is load-bearing. Match it exactly.

> **Schema was recently cleaned — two things changed from the old dump.** (a) The notifications column is now correctly spelled **`recipient`** (the old `receipient` typo is gone — use `recipient` everywhere). (b) The columns that an earlier version planned to add via `002_migrate.sql` are **already present in `001_schema.sql`**. See the note under section 1 before writing any migration.

---

## 1. The exact schema — match these names

Five InnoDB tables, `utf8mb4`. **Column names are case-sensitive in your SQL strings — copy them verbatim.**

| Table | Columns |
|---|---|
| `doctor` | `DoctorID` PK · `FullName` · `User` (unique) · `HashPass` · `Email` (unique) · `Specialty` · `Qualifications` · `Languages` · `WriteUp` (MEDIUMTEXT, the background blurb) · `ImageURL` |
| `patient` | `PatientID` PK · `FullName` · `User` (unique) · `HashPass` · `Email` (unique) · `Gender` enum(`Male`/`Female`/`Other`) · `Phone` · `Allergies` **(JSON — LONGTEXT with a `json_valid` CHECK)** |
| `slots` | `slotID` PK · `DoctorID` FK · `SlotDateTime` (DATETIME) · `CreatedAt` (TIMESTAMP) · `Status` enum(`Available`,`Booked`,`Blocked`) default `Available` · **UNIQUE `uq_slot` (`DoctorID`,`SlotDateTime`)** · KEY `idx_slot_lookup` (`DoctorID`,`SlotDateTime`,`Status`) |
| `appointment` | `appointmentID` PK · `DoctorID` FK · `PatientID` FK · `slotID` FK (→ `slots`, `ON DELETE SET NULL`) · `appointmentDateTime` (DATETIME) · `CreatedAt` (DATETIME) · `updatedAt` (TIMESTAMP, `ON UPDATE`) · `Status` enum(`Future`,`Cancelled`,`No show`,`Completed`,`Rescheduled`) default `Future` · `Diagnosis` · `Prescription` · `Treatment` · `FollowUp` (TINYINT(1), 0/1) · `Remarks` |
| `notifications` | `notificationID` PK · `sender` · **`recipient`** · `Subject` · `Body` · `appointmentID` FK (→ `appointment`, `ON DELETE SET NULL`) · `deliveryStatus` enum(`logged`,`sent`,`failed`) default `logged` · `SentAt` (DATETIME) |

Rules that follow from the schema:

- **There is no `users` table.** Credentials live in `doctor` and `patient`, each with `User`/`HashPass`/`Email`. Login checks both. **The admin is config-defined** (`ADMIN_USER` / `ADMIN_HASH` in `config.local.php`) — there is no admin table.
- **Times are single `DATETIME` columns.** Derive the date with `DATE(SlotDateTime)` and the time with `TIME(SlotDateTime)`. **Do not migrate to split date/time columns.**
- **`Allergies` is JSON.** `json_decode` on read, `json_encode` on write. A bare string fails the CHECK.
- **`FollowUp` is a 0/1 flag** (TINYINT(1)). Treat it as boolean.
- **No DOB column** on `patient`, and **no `active` flag** on `doctor`. Deleting a doctor is a hard `DELETE` and cascades their slots and appointments (`ON DELETE CASCADE`). That is what story 11's "clean up Doctors Accounts" means here.
- **`appointment.slotID` links an appointment to the exact slot it booked**, and `uq_slot` on `slots` is the database-level double-booking guard. The booking transaction relies on both.
- **Enum casing and spelling are exact:** `Future`, `Completed`, `No show` (with the space), `Available`, `Booked`, `Blocked`; delivery `logged`/`sent`/`failed`.

> **On `schema/002_migrate.sql` (issue #4).** The unique key, `appointment.slotID`, `appointment.updatedAt`, `notifications.deliveryStatus`/`appointmentID`, and the three `doctor` content columns are **already in `001_schema.sql`**. **Do not re-add them** — a duplicate `ADD COLUMN`/`ADD KEY` will error and fail `db_reset`. `002_migrate.sql` should be an **idempotent no-op** (or use `IF NOT EXISTS` guards) unless a *later* issue genuinely introduces a brand-new column. If issue #4 as written tells you to add columns that already exist, implement it as a no-op migration and note the conflict in a comment.

Seed data (from `001`): 5 doctors, 8 patients, 30 days of half-hour slots generated **relative to `CURDATE()`** (so it's never stale), a spread of past and future appointments, and sample notifications. All demo passwords are `Password123` (stored as bcrypt `HashPass`).

---

## 2. Why the constraints are strange

This is a graded university project, not a production app. The marking scheme forbids things that are normally good practice. **An agent optimising for "modern best practice" will fail this project.**

The base version is **85% of the project mark**, and an incomplete base caps the entire project at 15% regardless of what else exists.

### Hard bans inside `clinic-base/` — no exceptions

| Banned | Why |
|---|---|
| **AJAX, `fetch`, `XMLHttpRequest`, JSON as a transport** | Explicitly not permitted in the base version |
| **jQuery or any JS library** | Same clause |
| **Bootstrap, Tailwind, Foundation, any CSS framework** | "Templates or features from … any other web site development tools" |
| **Composer, `vendor/`, any package manager** | Against "traditional technologies"; adds a network step to deployment |
| **Any CDN link or external `<script src>` / `<link href>`** | Everything ships in the repo |
| **Laravel, Slim, any PHP framework** | The rubric marks *your* SQL; an ORM hides the layer being assessed |
| **`<frameset>`, `<frame>`, `<iframe>`** | Explicitly banned |
| **`mailto:` as a form action** | Explicitly banned. Form actions invoke a PHP script |
| **Links to Gmail / Hotmail / Yahoo / PayPal / Facebook / Twitter** | Explicitly banned |
| **Sending mail off the machine** | "You must not send email to any email account except your own web account in your local web server" |

Permitted stack: **HTML5, CSS3, vanilla JavaScript, PHP, MySQL/MariaDB.** Nothing else.

### The page cap — do not break it

The brief allows **one home page plus a maximum of ten additional content pages** — the eleven are listed in `docs/PAGES.md`. **Never create a twelfth page.** If a task seems to need one, comment on the issue instead.

---

## 3. Coding rules — checked by the audit issues

1. **Every database call goes through `q($sql, $params)`** in `lib/db.php`. No `->query()`, no `mysqli_`, no string interpolation into SQL, ever. This is what makes SQL injection impossible here, and it is a demo Q&A question.
2. **Every value echoed into HTML goes through `e()`** in `lib/helpers.php` — including values you believe are safe.
3. **Every `<form>` contains `csrf_field()`** and every POST handler calls `csrf_check()` before touching the database.
4. **Every protected page calls its guard on the first line after the includes** — `require_login()`, `require_doctor()`, `require_admin()`.
5. **All SQL lives in `models/`.** A page calls a model function. A page never contains the word `SELECT`.
6. **POST-Redirect-GET for every mutation.** A refresh must never re-submit.
7. **Every page has a distinct `<title>`** and **both text and at least one image** — stated requirements, trivially missed.
8. **No CSS until milestone M5.** Emit the real markup with the real class names and every real state (`.slot.free`, `.slot.taken`, `.slot.blocked`, `.slot.past`, empty states, error banners, repopulated fields) and leave it unstyled — so M5 is a styling pass, not a rewrite.
9. **Derive date and time from the `DATETIME` columns** with `DATE()` / `TIME()`. Never add split columns.
10. **JSON for `Allergies`**; the exact column names and enum casing from section 1 everywhere.

---

## 4. Verification — how you prove a task is done

No browser in this environment. Everything is verified from the command line.

```bash
php -l <file>                      # lint every file you touch
php tools/db_reset.php             # drop, recreate, apply 001 + 002 + seed
php tests/run.php                  # the whole suite
php tests/run.php test_slots       # one file
bash tools/serve.sh &              # php -S localhost:8000 for smoke tests only
                                   # (production is XAMPP/Apache — never rely on -S behaviour)
```

A task is done when: every file you touched lints, `php tests/run.php` is green, and the issue's own acceptance command prints `OK`. **Write the test in the same commit as the code.**

---

## 5. When you get stuck

Do **not** improvise around a constraint. In order:

1. Re-read this file, `docs/PAGES.md` and `schema/001_schema.sql`.
2. If the issue is ambiguous, implement the smallest thing that satisfies the acceptance command and note the ambiguity in a comment.
3. If satisfying the issue would require breaking a rule in section 1, 2 or 3 — **stop**, comment on the issue explaining the conflict, and move to the next unblocked issue.
4. Never add a dependency. Never create a twelfth page. Never send mail off-box. Never rename a schema column. Never re-add a column that already exists in `001`.

---

## 6. Commits

One issue per branch, `issue/<number>-<slug>`.

```
feat(book): render server-side slot grid for a doctor and date

Closes #27.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
```

Never commit `config.local.php`, `.env`, or anything under `mail/`.

---

## 7. Mail

`send_mail()` in `lib/mail.php` does two things, **in this order**:

1. **Writes a `notifications` row** — `sender`, **`recipient`** (correct spelling), `Subject`, `Body`, `appointmentID` (when the mail concerns an appointment), `deliveryStatus='logged'`.
2. *Then* attempts delivery via PHP `mail()`, which XAMPP dumps to disk via `mailtodisk`, and updates `deliveryStatus` to `sent` or `failed`.

Row first, delivery second. If delivery fails the demo still works, because `admin/outbox.php` reads the table, not the mailbox. Delivery failure is logged, never fatal, never shown to the patient.
