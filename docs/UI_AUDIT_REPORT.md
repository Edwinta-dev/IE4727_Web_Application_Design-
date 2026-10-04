# Clinic portal UI audit

**Date:** 1 October 2026  
**Target:** `http://localhost:8000/ie4727_web_application_design-/clinic-base/`  
**Reference:** [`docs/ACCEPTANCE_MATRIX.md`](ACCEPTANCE_MATRIX.md), [`docs/PAGES.md`](PAGES.md), and the UI requirements in [`AGENTS.md`](../AGENTS.md).

## Scope and method

Opened the supplied Apache URL in Chromium and inspected eight pages at 1280×800 and 390×844: home, doctor directory, doctor profile, booking, registration, patient home, doctor day board, and doctor schedule. All 16 captures returned HTTP 200. The inspected pages each had one H1 and a distinct title, no horizontal viewport overflow, loaded visible images, and no page errors. The interaction-size pass found no visible link, button, submit control, or select smaller than 44×44 px.

The repository audit harness was not used against this URL because it deliberately rejects port 8000 as potentially stale. The browser checks instead used the exact Apache URL supplied for this audit. Screenshots are local, gitignored evidence; the report links to them below.

## Findings

### High — Home page leaves a large, empty desktop section

At desktop width the specialty links have five grid columns but six specialties. The sixth item wraps to a second row. The shared `.grow-row > .grow-item` rule also applies `min-height: 38rem` to each specialty link, so both rows reserve at least 38rem even though their content is short. The result is a long blank gap around the specialty section and a 3,049 px home page at 1280 px wide. The mobile layout has no horizontal overflow, but the desktop spacing is visibly broken and conflicts with the owner’s direction to use compact, intentional sections.

Evidence: [home, desktop](../UIPROBLEMS/after/live-audit/index-php-1280.png), [home, mobile](../UIPROBLEMS/after/live-audit/index-php-390.png). Relevant markup is in [`index.php`](../clinic-base/index.php:103); the shared minimum height is in [`style.css`](../clinic-base/assets/style.css:856), and the specialty grid has five columns in [`style.css`](../clinic-base/assets/style.css:1262).

### High — Live doctor data includes two non-seed records in public listings

The home page and directory show seven doctors, including “dr edwin” and “Synthetic Registration.” The committed demo seed defines five doctors. The two extra records look like development or registration fixtures and appear as real clinic providers, including a logo in place of a portrait. This makes the public directory and featured-doctor section fail the expected seeded demo presentation (acceptance criteria C01–C03) even though the images themselves load.

Evidence: [home, desktop](../UIPROBLEMS/after/live-audit/index-php-1280.png), [directory, desktop](../UIPROBLEMS/after/live-audit/doctors-php-1280.png). The expected seed is [`003_seed.sql`](../schema/003_seed.sql:12). This is a live database-content issue; I did not alter or reset the database during the audit.

### High — Patient history contains an expired appointment still marked “Future”

On 1 October, the patient dashboard showed a 30 September appointment with status “Future” in the Past appointments table. The date grouping is understandable, but the status contradicts the appointment date and makes the record look actionable or unfinished. The committed seed generates its sample past and future appointments relative to `CURDATE()`, so this live record does not match a fresh seed state. This prevents a reliable check of the intended upcoming/history examples (C07, C10, and C32) until the demo data is refreshed or corrected.

Evidence: [patient home, mobile](../UIPROBLEMS/after/live-audit/patient-home-php-390.png). No database writes were made.

## Pages and flows not fully reviewed

- The doctor day board loaded for `drsmith`, but showed no appointments for 1 October. I did not reach a doctor visit record through that board, so visit-note states (C14–C15) remain unverified.
- Admin console and outbox could not be opened. The audit harness defaults (`admin` / `Password123`) did not work because [`clinic-base/config.php`](../clinic-base/config.php:10) defaults `ADMIN_USER` and `ADMIN_HASH` to empty strings, and there is no local override in this checkout. Those harness defaults are not an admin account unless configured. Admin criteria C16–C20 remain unverified; I did not create or change local credentials.
- I did not submit registration, booking, schedule-generation, cancellation, or account-deletion forms. Those actions write data, and this audit was scoped to inspection and read-only navigation.

## Evidence captures

All captures are under `UIPROBLEMS/after/live-audit/`:

| Page | Desktop | Mobile |
|---|---|---|
| Home | [1280 px](../UIPROBLEMS/after/live-audit/index-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/index-php-390.png) |
| Doctor directory | [1280 px](../UIPROBLEMS/after/live-audit/doctors-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/doctors-php-390.png) |
| Doctor profile | [1280 px](../UIPROBLEMS/after/live-audit/doctor-php-id-1-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/doctor-php-id-1-390.png) |
| Booking | [1280 px](../UIPROBLEMS/after/live-audit/book-php-doctor-1-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/book-php-doctor-1-390.png) |
| Registration | [1280 px](../UIPROBLEMS/after/live-audit/register-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/register-php-390.png) |
| Patient home | [1280 px](../UIPROBLEMS/after/live-audit/patient-home-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/patient-home-php-390.png) |
| Doctor day board | [1280 px](../UIPROBLEMS/after/live-audit/doctor-home-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/doctor-home-php-390.png) |
| Doctor schedule | [1280 px](../UIPROBLEMS/after/live-audit/doctor-schedule-php-1280.png) | [390 px](../UIPROBLEMS/after/live-audit/doctor-schedule-php-390.png) |

The audit added this report and generated ignored screenshots only. Existing uncommitted changes in `clinic-base/doctor/visit.php` and `tools/ui/visit-state.test.mjs` were left untouched.

## Issue #121 completion — visit state (4 October 2026)

This section is fresh checkout evidence for UI_Defects.pdf pp. 3 and 14,
C14 / new finding 2. The earlier sections remain a historical, read-only live
audit; their URL, data findings and counts are not this run's validation.
The requested round-2 `UIPROBLEMS/Open/UI_Defects.pdf` is absent, as already
recorded in the #120 handoff. `Open/clinicissues.pdf` is an older audit and was
not renamed. Reproduction uses the written finding supplied with #121.

Baseline: HEAD `ed64a8e` includes the #120 isolation fix, following `eb9a791`.
The restored working tree contained the audited #119 visit-page edit plus this
report and `tools/ui/visit-state.test.mjs`. Kept #119's conditional form,
read-only existing notes, focus checks, and no-JS coverage. Extended that same
implementation/test for #121 rather than duplicating it. Replaced hard-coded
seed appointment IDs with generated synthetic role/appointment fixtures; the
original test had not verified the child's resolved database. These intended
files, the shared model decision, PHP boundary test, and isolated fixture helper
belong together in the supervisor's one #121 change. No git/GitHub mutation was
performed by this agent.

Reproduction: the restored page checked only `Status === 'Future'` when choosing
the explanation. A future Rescheduled record displayed “This appointment is
Rescheduled. Visit notes cannot be changed.” The baseline explanation was
captured on an authenticated synthetic doctor's visit at both widths, then the
finished source was restored before after/acceptance checks. The finished page
says the appointment has not started and gives its actual date and time.

The shared `visit_notes_state()` decision is used by the UI and by
`visit_notes_editable()` inside the locked save transaction. Precedence is:

| Owned record | Before start | At/after start |
|---|---|---|
| Future | Not started; no form; save rejected | Notes form; save completes visit |
| Rescheduled | Not started; no form; save rejected | Notes form; save completes visit |
| Completed | Not started; no form; save rejected | Notes remain editable (existing policy) |
| Cancelled / No show | Status reason; no form; save rejected | Status reason; no form; save rejected |
| Foreign doctor, any status | 404; save rejected | 404; save rejected |

Rejection flashes use the same state precedence. `Completed` remains an allowed
notes-editing status, although attendance outcomes permit only Future and
Rescheduled. Neither guard was relaxed. `reschedule_appointment()` updates the
same appointment ID to the new slot/time with status Rescheduled and releases
the old slot. There is no separate superseded original appointment in this
model/schema. Existing `test_reschedule.php` verifies the replacement slot,
time, same record, released old slot, and rollback behaviour. No speculative
legacy status/link/schema convention was introduced.

Fixtures: synthetic randomly suffixed doctor and patient, real login/session,
JavaScript disabled, Asia/Singapore now captured once at fixture creation,
appointments at now ±2 days, all five statuses. A controlled PHP now also checks
one second before, exactly at, and one second after the start for every status
and foreign ownership. Mutation CLI and HTTP children load the #120 guard,
verify resolved `ie4727db_test`, identify the owned port-8123 server, and disable
mail. Exact generated role records are cleaned in `finally`; no live DB is read,
written, reset or cleaned. The browser compares full appointment snapshots
(including updatedAt) and all notification rows for each rejected POST. Three
eligible historical saves verify Diagnosis, Treatment, Prescription, FollowUp,
Remarks, completion and preservation of the patient reason. Foreign GET/POST
both return 404 without exposing a form or changing data.

Fresh screenshots opened and inspected at 1280x800 and 390x844 (full-page):

- `UIPROBLEMS/after/visit-state-before/doctor-visit-{1280,390}.png`
- `UIPROBLEMS/after/visit-state-matrix-before/rescheduled-{1280,390}.png`
- `UIPROBLEMS/after/visit-state-after/doctor-visit-{1280,390}.png`
- `UIPROBLEMS/after/visit-state-matrix-after/rescheduled-{1280,390}.png`
- `UIPROBLEMS/after/visit-state-matrix-after/editable-{1280,390}.png`

The status explanation wraps within its section, desktop retains the two
columns, mobile stacks the sections, and the eligible form has a visible focus
outline. Matrix screenshots contain deliberately inconsistent future Completed
records to exercise the guard; their appearance in the existing history list
is fixture evidence, not a new claim about normal clinic data.

Validation commands (serial, explicitly test DB):

```text
node tools/ui/visit-state.test.mjs
node tools/ui/shoot.mjs --serve --label visit-state-after doctor/visit.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctor/visit.php
php -l clinic-base/doctor/visit.php
php -l clinic-base/models/booking.php
php -l tests/test_visit_state.php
php -l tools/ui/visit-state-fixture.php
php tests/run.php
```

The browser matrix prints OK; PHP lint and JS syntax pass. The fresh full suite
passes 78 checks (the audit's 77 is historical); existing CLI session/header
warnings remain. Acceptance audit and screenshots use authenticated drsmith
visits on the freshly reset test seed. Horizontal overflow is 0px at both
widths. The scoped audit covers layout, 44px targets, palette, contrast, focus
and slop; it prints OK with zero failures at both widths. The same audit also
prints OK on explicit synthetic future Rescheduled and editable past Future
routes (`doctor/visit.php?appt=<generated ID>`), using the owned, verified
`withTestServer()` session and `UI_DOCTOR` fixture credentials. Those route
checks invoke `node tools/ui/audit.mjs --within main
layout,tap-targets,palette,contrast,focus,slop <route>` against that running
server. No stylesheet/check relaxation was made. Screenshots remain ignored.

Carry-over #114 remains partly fixed: Home and Appointments still target the
same doctor/patient page with an anchor variation; current-tab logic marks Home.
This issue does not change that navigation or claim all older findings fixed.

## Issue #122 — outcome demo seed (4 October 2026)

Finding: C15, new finding 3, cited UI_Defects.pdf p. 3 and pp. 14–15.
The exact named export is absent from this checkout; the available
`UIPROBLEMS/Open/clinicissues.pdf` and its rendered pages are an earlier audit.
Those pages were inspected, and the local round-two supplemental evidence
`UIPROBLEMS/story-audit-2/log-supp.json` explicitly records manually inserted
past-due fixtures 33/34. No replacement PDF was invented or renamed.

Actual base: clean `a0eeec2` (includes #120 isolation and #121 visit state),
with no initial working-tree diff. Before reset had 25 appointments and no
past-due pending attendance rows. The change touches the seed, seed/stats
regressions, read-only UI fixture inspection, browser regression and docs.
Doctor pages, outcome guards, #119/#121 visit edits and existing tests remain
intact. The seed now has 27 appointments: 18 Completed, 2 No show, 1 Cancelled,
4 future bookings and 2 past-due Future appointments. Counts are identical
across two fresh resets; today's slot count is 3570, varying with Sundays.

Role/database/time: `drsmith` / `Password123`, resolved `ie4727db_test`,
one seed-local Asia/Singapore now (+08:00). Pending rows use the last clinic
day before today, 09:00 Alex Tan and 09:30 Bethany Ong (3 October in this run).
The date is derived from the fresh seed rather than hardcoded in tests.
Slot creation precedes booking and booking precedes appointment start;
all seed booking timestamps are chronological. Versioned patient reasons
remain readable after attendance is recorded. No live reset or live cleanup
was performed. Mutation browser children verify the resolved DB and owned
8123 server, with PHP mail disabled; seeding only inserts logged notices.

Commands/results:

```text
php tests/run.php test_seed_outcomes       PASS (two fresh resets, model outcomes)
php tests/run.php test_seed                PASS
php tests/run.php test_stats               PASS
node tools/ui/isolation.test.mjs           PASS (7 checks)
node tools/ui/outcome-seed.test.mjs         OK (two resets, authenticated, JS off)
node tools/ui/shoot.mjs --serve --label outcome-seed-after doctor/home.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctor/home.php
node tools/ui/shoot.mjs --serve --label outcome-seed-actionable-after doctor/home.php?date=<seed date>
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctor/home.php?date=<seed date>
php tests/run.php                          79 passed, 0 failed
```

Both scoped audits print OK. Touched PHP lints, JS syntax and `git diff --check`
pass. Browser tests record Completed and No show through real no-JS forms,
verify exact enum and unchanged clinical/patient/slot data, and compare full
appointment/slot/notification snapshots for rejected future and foreign POSTs
for both outcomes. Attendance does not send mail or alter the outbox.
An initial full-suite attempt overlapped a reset and failed during setup;
the subsequent serial full run passed. Existing CLI session/header warnings
remain; 77/78 checks in earlier reports are historical counts.

Opened and inspected local ignored images at 1280x800 and 390x844:

- `UIPROBLEMS/after/outcome-seed-before/doctor-home-{1280,390}.png`
- `UIPROBLEMS/after/outcome-seed-after/doctor-home-{1280,390}.png`
- `UIPROBLEMS/after/outcome-seed-actionable-after/doctor-home-{1280,390}.png`
- `UIPROBLEMS/after/outcome-seed-outcomes/doctor-home-{1280,390}.png`

The literal acceptance route defaults to today (Sunday, empty); the explicit
seed-date route proves both actual pending rows and controls. Document-level
horizontal overflow is 0px at both widths; the existing phone table scrolls
horizontally to its actions. The populated main audit also passes layout,
44px targets, palette, contrast, focus and slop without check/style changes.
Carry-over #114 remains partly fixed as recorded above; no broader UI repair
or automatic completion of historical Future appointments is claimed.


## Issue #123 ? admin phone tables (4 October 2026)

Finding: C16, new finding 4, cited UI_Defects.pdf pp. 3 and 15.
The named round-two PDF is absent. Available older rendered pages 3/15 were
opened; `UIPROBLEMS/story-audit-2/log-supp.json` records the original console
measurement (390px viewport, 457px table, 465px document). No older PDF was
renamed to impersonate the missing export.

Actual base: clean `1d3ad69`, with #120 isolation and #122 seeded outcomes.
Fresh authenticated reproduction reconciles the stale report: current account
sections already scroll by pointer, and appointments have a scroll wrapper.
At 390px their tables measured 739.23/734.67/672px, inside 358px sections
at x=16, with a 390px document. However, none had a named/focusable region
or visible scrolling guidance. This change wraps only the three console tables
in labelled keyboard-accessible regions, moves scrolling off the account
sections, retains 16px text and the existing destructive actions, and reuses
the patient empty-state pattern. Empty tables have a full-span message without
irrelevant column headings, and no scroll/tab stop. Populated columns remain
visible through scrolling. No handlers, filters, schema or navigation changed.
Existing doctor/visit.php, visit-state tests and prior report entries are intact.

Role/database/time: real admin login (`admin` / `Password123`), resolved
`ie4727db_test` verified by the existing #120 config and child-server guards.
Browser mutation tests own port 8123 and disable PHP mail. Synthetic account
fixtures reuse the guarded admin-flash fixture; slots/appointments are relative
to Asia/Singapore NOW (+120/+121 days), rather than expired fixed dates.
No production database writes, cleanup or reset occurred.

Commands/results (run serially for final validation):

```text
node --test tools/ui/isolation.test.mjs    7 passed
php -l clinic-base/admin/console.php       PASS
node --check tools/ui/admin-tables.test.mjs PASS
node tools/ui/admin-tables.test.mjs         OK
node tools/ui/admin-flash.test.mjs          OK (includes no-JS deletion)
node tools/ui/shoot.mjs --serve --label admin-phone-before admin/console.php
node tools/ui/shoot.mjs --serve --label admin-phone-after admin/console.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop admin/console.php
php tests/run.php                          79 passed, 0 failed
```

Both screenshot commands completed; the final scoped audit printed OK.
PHP/JS syntax and `git diff --check` passed.

The new browser regression exercises ArrowRight scrolling, real emulated touch
swipes (CDP touch start/move/end), Tab to Delete, focus rings and 44px actions.
It checks filtered Future rows and empty doctor-filter results at both widths,
invalid CSRF (419, unchanged rows), dismissed confirmations (unchanged accounts
and dependents), accepted doctor/patient deletion, cascades, retained notification
rows, PRG and one-time flashes. Native scrolling requires no new application JS.
Initial attempts with CDP synthesized scroll gestures did not move the viewport;
the final test uses dispatched touch events in a mobile Chromium context.

Final synthetic-data bounds, Patients / Doctors / Appointments:

| Viewport | Region x / width | Table widths | Document width |
|---|---|---|---|
| 390 | 16 / 358 | 974.27 / 1012.77 / 672 | 390 |
| 1280 | 128 / 1024 | 1024 / 1024 / 1024 | 1280 |

After keyboard and touch scrolling, rightmost cell bounds were respectively
x=188.84..374.27, 188.34..373.77, and 250.19..374 (subpixel tolerance <1px).
Delete controls are fully inside those cells, with existing red styling and
visible focus. Empty region scrollWidth equals clientWidth: 358 at phone and
1024 at desktop; colspan equals the table's 5/5/4 columns.
Random fixture suffixes can slightly change populated intrinsic widths.
Exact measurements are saved in ignored `admin-phone-after/measurements.json`.

Opened/inspected evidence under gitignored `UIPROBLEMS/after/`:

- `admin-phone-before/admin-console-{1280,390}.png`
- `admin-phone-after/admin-console-{1280,390}.png`
- `admin-phone-after/{patients,doctors,appointments}-rightmost-390.png`
- `admin-phone-after/admin-empty-{1280,390}.png`
- `admin-phone-after/admin-filtered-{1280,390}.png`

Missing round-two PDF is an evidence limitation, not an unverified UI repair.
Carry-over #114 remains partly fixed as recorded above; this task does not
claim older findings are all resolved. Existing CLI session/header warnings
are outside this issue's table changes.

## Issue #124 — lead-time coverage (4 October 2026)

Finding: C16, new finding 5, cited UI_Defects.pdf pp. 3–4 and 6.
The named round-two PDF is still absent from the checkout (only the earlier
`UIPROBLEMS/Open/clinicissues.pdf` is available); no export was invented.
Reproduction used actual clean base `cd1451f`, including #119/#121 visit work,
#122 pending fixtures and #123 tables, with no initial working-tree diff.
The reported 7/28 coverage is historical: #122 already corrected historical
chronology, giving 27/27 valid samples before this change. Before screenshots
still show the long technical explanation. This change keeps those fixes and
gives the four upcoming bookings explicit eight-day lead times, instead of
lead times varying with reset time. The other 23 remain seven-day bookings.
Expected seed mean is 193 elapsed days / 27 = 7.148148 days, displayed as 7.1.
There are no intentionally invalid seed rows; invalid samples are separate
regressions. No model sign/exclusion logic, schema, styles or doctor pages
changed. Definition/provenance now lives in `docs/RUNBOOK.md`.

Role/database/time: admin / Password123, explicitly resolved `ie4727db_test`,
Asia/Singapore seed clock (+08:00). Mutation browser tests verify the child
server database and disable mail. Literal screenshot/audit commands inherit
the explicit test DB after PHP configuration verification; config refuses
any local override. No command targets the live database and no live booking
timestamps were changed. The regression compares timestamp snapshots before
and after authenticated no-JS console reads.

Validation commands:

```text
php tests/run.php test_seed_outcomes
php tests/run.php test_stats
node tools/ui/leadtime.test.mjs
node tools/ui/outcome-seed.test.mjs
node tools/ui/shoot.mjs --serve --label leadtime-fixtures-before admin/console.php
node tools/ui/shoot.mjs --serve --label leadtime-fixtures-after admin/console.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop admin/console.php
php tests/run.php
```

Focused PHP checks pass, including exact coverage/mean across two fresh resets,
slot-before-booking chronology and unchanged pending outcomes. Browser tests
print OK: seed 27/27 at 7.1 days; mixed 3/5 at 1.3 days (0, 1, 3-day leads);
all-invalid 0/1 and empty 0/0 both Unavailable. The two intentionally invalid
samples have reversed and missing booking times; they stay excluded. Existing
outcome regression passes two resets, valid outcomes, rejected future/foreign
POSTs and unchanged relations/outbox. Scoped audit prints OK for both widths.
An initial browser attempt overlapped a test reset and failed; the serial
rerun passed. No assertion was weakened. PHP lint and diff whitespace checks
pass; full-suite result is recorded below.

Opened/inspected ignored evidence at 1280x800 and 390x844:

- `UIPROBLEMS/after/leadtime-fixtures-before/admin-console-{1280,390}.png`
- `UIPROBLEMS/after/leadtime-fixtures-after/admin-console-{1280,390}.png`
- `UIPROBLEMS/after/leadtime-regression-after/{seed,mixed,invalid,empty}-{1280,390}.png`

Metric text is 16px; document horizontal overflow is 0px in all eight states.
Phone metric heights are 171.86px (seed), 241.05px (mixed), 261.05px (invalid),
191.86px (empty); desktop grid height is 346.23px. Coverage and exclusion reason
wrap within the section, with no truncation. Exact results are saved in ignored
`leadtime-regression-after/measurements.json`. Detailed definitions are absent
from the normal console. Carry-over #114 remains partly fixed as documented
above; no broader repair is claimed.

Final full suite: `php tests/run.php` reports 79 passed, 0 failed. Existing CLI
session/header warnings remain. All four touched/new PHP files lint; the new
browser script passes syntax checking and `git diff --check` passes.

## Issue #125 — History relative to the viewed encounter (4 October 2026)

Reported source: UI_Defects.pdf pp. 4 and 14, C14, new finding 6. That PDF is
not present in this checkout, so it could not be copied to `UIPROBLEMS/Open/`.
The available `pdf-page-4.png` and `pdf-page-14.png` were opened and show an
older report with different findings. Fresh reproduction below is the evidence
for this repair; no claim is made that the missing PDF was inspected.

Actual base: `7493ea2`, active `automation/ie4727-clinic`, initially clean.
Existing visit-state protections, reason/doctor-remarks separation, markup and
`tools/ui/visit-state.test.mjs` are preserved. The page now passes the current
appointment ID to the existing history model. Its parameterized query excludes
that ID and requires a strictly earlier appointment datetime, with the anchor
matching the patient and doctor. Completed-only status and newest-first order
are retained; missing or foreign anchors return no history.

The shared regression fixture creates randomly suffixed doctor/patient accounts
and dynamic appointment IDs in a verified `ie4727db_test` connection. One
Asia/Singapore reference instant supplies relative encounter dates. It includes
two earlier completed visits, a current editable visit, simultaneous and later
completed visits (both before wall-clock now), foreign patient/doctor visits,
and excluded statuses. Cleanup targets only the exact synthetic accounts.
Browser mutations use `withTestServer()` and `testPhp()`, verify the child
database header and run with mail disabled. No live data is reset or written.

`node tools/ui/visit-history.test.mjs --baseline` temporarily used the HEAD
history model, restored in `finally`, with the new page's optional argument
ignored by that original PHP function. It printed OK: four records before
saving and five after save/refresh, including the current saved notes once,
plus later and simultaneous encounters. With the fix,
`node tools/ui/visit-history.test.mjs` printed OK: exactly two earlier records
before save, after save and refresh; all current fields remain in the form;
patient notes retain diagnosis, treatment, prescription, follow-up, reason and
doctor remarks. The first encounter has zero history and the existing honest
empty state. Foreign doctor GET/POST return 404; foreign patient notes are
absent; a patient request to the doctor route is redirected by its guard.

Opened/inspected ignored captures at both 1280x800 and 390x844:

- `UIPROBLEMS/after/visit-history-before/saved-refresh-{1280,390}.png`
- `UIPROBLEMS/after/visit-history-flow-after/saved-refresh-{1280,390}.png`
- `UIPROBLEMS/after/visit-history-flow-after/empty-{1280,390}.png`
- `UIPROBLEMS/after/visit-history-after/doctor-visit-{1280,390}.png`
- `UIPROBLEMS/after/visit-history-current-after/doctor-visit-{1280,390}.png`

The saved current notes no longer repeat in the history section. Desktop keeps
its two columns; mobile keeps all visit fields visible. The flow measurements
in `visit-history-flow-after/measurements.json` report 0px horizontal overflow
at both widths for before-save, saved-refresh and empty states; history counts
are respectively 2, 2 and 0. Carry-over #114 remains separately recorded as
partly fixed; this issue makes no navigation repair claim.

Validation completed serially against the test database:

```text
php tests/run.php test_visit_history
node tools/ui/visit-history.test.mjs --baseline
node tools/ui/visit-history.test.mjs
php tests/run.php
node tools/ui/visit-state.test.mjs
node tools/ui/shoot.mjs --serve --label visit-history-after doctor/visit.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctor/visit.php
node tools/ui/shoot.mjs --serve --label visit-history-current-after doctor/visit.php?appt=<dynamic-current-id>
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctor/visit.php?appt=<dynamic-current-id> doctor/visit.php?appt=<dynamic-earliest-id>
```

The full suite reports 80 passed, 0 failed, with the pre-existing CLI
session/header warnings. The existing visit-state browser test prints OK for
the future/past status matrix, foreign GET/POST, rejected-save non-mutation
and all-field historical saves. Both scoped audits print OK. All six
touched/new PHP files lint; browser syntax and `git diff --check` pass.
The bare-route acceptance tools authenticate a newly seeded synthetic doctor
and select its upcoming encounter; the additional explicit dynamic routes
audit the edited historical encounter and first-visit empty state. These
read-only tools inherit `CLINIC_DB_NAME=ie4727db_test`; successful synthetic
login also confirms they read the accounts created only in the verified test
database. Save/refresh evidence uses the independently guarded HTTP child
described above. The acceptance fixture's exact accounts were removed using
the guarded CLI workflow after screenshots/audits.

## Issue #126: reschedule landing and unavailable days (4 October 2026)

Finding: C10 / new finding 7, cited in UI_Defects.pdf p. 4 and pp. 11-12.
That PDF is absent from this checkout; the supplied written finding and fresh
captures are the evidence. The actual starting base was `fc89703` on
`automation/ie4727-clinic`, with a clean working tree, rather than the report's
historical `eb9a791` plus uncommitted work. Existing visit-page, visit-state
test and audit-report work was retained. The #114 duplicate Home/My appointments
destination remains a separate carry-over; this change does not resolve it.

Reproduction: an authenticated patient opened reschedule without a date while
the selected doctor's only slot today had elapsed. Entry selected today even
though the original appointment day had a free replacement; the mixed
"fully booked" message occupied the first schedule-grid cell.

The page now prefers the authorized original day when selectable, otherwise
the earliest permitted day with a future Available slot. Valid explicit dates
stay selected. Availability uses the same captured PHP now as the grid, the
existing seven-day window, the authorized doctor and the time filters. Day
feedback distinguishes ungenerated days, filtered-out times, elapsed times,
and booked/blocked future times. It sits above the grid and offers a link to
an available day, or explains that none exists within the filtered window.
The existing replacement transaction and ownership checks are unchanged.

Fixtures use relative Asia/Singapore dates. The PHP regression supplies today's
18:00 as controlled now, including a slot exactly at now, both window edges,
booked/blocked/empty days and filter boundaries. Browser fixtures use elapsed
midnight today and synthetic doctor `reschedule_day_fixture`, with bookings at
today +4 and +10 days; the patient logs in through the real form as `alextan`.
`testPhp()` and `withTestServer()` verify the child resolves `ie4727db_test` and
disable delivery before writes. The fixture independently checks SELECT DATABASE().
Only the test database is reset; no live database writes or cleanup occur.

Verification commands:

```text
php tests/run.php test_reschedule_day
node tools/ui/reschedule-day.test.mjs --before
node tools/ui/reschedule-day.test.mjs
node tools/ui/booking-dates.test.mjs
node tools/ui/shoot.mjs --serve --label reschedule-day-before patient/home.php book.php
node tools/ui/shoot.mjs --serve --label reschedule-day-after patient/home.php book.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop patient/home.php book.php
php tests/run.php
```

Read-only shoot/audit commands inherit explicit `CLINIC_DB_NAME=ie4727db_test`.
The reschedule browser regression additionally runs that scoped audit on the
authenticated landing, elapsed day and no-availability state, using supported
`patient/../book.php?reschedule=<fixture-id>` routes and its verified owned
server. It exercises valid explicit dates, outside-window originals, filtered
landing, all empty-day reasons, foreign ownership rejection, keyboard Enter
on time selection, JS-off date submission, stale-selection non-mutation and
successful atomic replacement. The old empty-state source assertion was
updated to the new specific feedback; executable behaviour coverage was added.

The serial full PHP suite reports **81 passed, 0 failed** (existing CLI
session/header warnings remain). PHP lint, browser syntax and diff whitespace
checks pass. The scoped audits print OK. A preliminary overlapping suite/fixture
run was discarded; the reported full-suite result comes from the serial rerun.

Inspected images at both 1280x800 and 390x844 are in gitignored
`UIPROBLEMS/after/reschedule-day-before/`, `reschedule-day-after/`,
`reschedule-day-before-flow/` and `reschedule-day-after-flow/`. The flow images
are `landing-1280.png`, `landing-390.png`, `elapsed-1280.png`, `elapsed-390.png`,
and after-only `no-availability-1280.png` / `no-availability-390.png`.
Adjacent JSON measurements record 0px viewport overflow and zero day-feedback
grid cells; elapsed feedback ends 12px above the first slot at both widths.
The bare book.php captures cover the ordinary filter page, while the flow
captures and audits prove the authenticated reschedule states.

## Issue #127: responsive hero geometry (4 October 2026)

Finding: C01/C02, new finding 9, cited in UI_Defects.pdf p. 4. That named
PDF is absent; the available `Open/clinicissues.pdf` and its rendered page 4
describe different findings. The supplied written finding and fresh browser
evidence are used here. Starting source was `5087f8d`, clean working tree;
the historical `eb9a791` is not this task's baseline. Existing visit edits,
visit-state regression and report sections are preserved. The #114 duplicate
destination remains a separate carry-over.

The reported 1280px phone image is **already fixed in the current source**:
the shared image uses width/height 100%, global images have max-width 100%,
home grid tracks use minmax(0, ...), and `.hero-copy` has min-width 0.
Both banners fit at every tested width even after disabling body and banner
overflow clipping. No additional width rules or overflow hiding were needed.
The desktop directory image did cut off both subjects' heads; its focal
position now uses 20% vertically instead of 42%. Home framing, shared
component, page-width token, 1280px banner width limit, height caps, intrinsic
HTML metadata and cover cropping are retained.

`tools/ui/hero-size.test.mjs` tests home, unfiltered directory and the real
General Practice filtered route at 390, 768, 1280 and 1920px. It measures
actual image/banner rectangles, checks all four image bounds against the
banner, caption/scrim anchors, cover cropping, natural-width/no-upscaling and
height caps, then repeats measurements with clipping disabled. The extra
1920px case exercises the existing 1280px banner limit. JSON records every
rectangle, natural dimensions, focal position and document client/scroll width.
These are browser assertions, not CSS source-string tests.

Before and after rectangles are identical (CSS pixels, rounded here only):

| Route | Viewport | Image and banner (x, y, width, height) | Document scroll/client |
|---|---:|---|---|
| Home | 390 | 32, 208.375, 326, 183.375 | 390 / 390 |
| Home | 768 | 32, 147.188, 704, 240 | 768 / 768 |
| Home | 1280 | 164, 100, 618.656, 371.188 | 1280 / 1280 |
| Home | 1920 | 228, 100, 960, 440 | 1920 / 1920 |
| Directory, both states | 390 | 16, 184.375, 358, 240 | 390 / 390 |
| Directory, both states | 768 | 16, 123.188, 736, 240 | 768 / 768 |
| Directory, both states | 1280 | 128, 76, 1024, 281.594 | 1280 / 1280 |
| Directory, both states | 1920 | 192, 76, 1280, 320 | 1920 / 1920 |

Fixtures: anonymous public routes, existing five-doctor test seed, no writes
or date-specific fixture needed. The regression uses `withTestServer()` to
verify the child PHP resolves `ie4727db_test`, with mail disabled. The read-only
acceptance screenshot/audit commands inherit explicit
`CLINIC_DB_NAME=ie4727db_test`; the same PHP config rejects overrides. Test
suite resets are confined to that test database. No live cleanup occurs.

Commands run:

```text
node --test tools/ui/isolation.test.mjs
node tools/ui/hero-size.test.mjs --before
node tools/ui/shoot.mjs --serve --label hero-size-before index.php doctors.php
node tools/ui/hero-size.test.mjs
node tools/ui/shoot.mjs --serve --label hero-size-after index.php doctors.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop index.php doctors.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop doctors.php?specialty=General%20Practice
php tests/run.php
node --check tools/ui/hero-size.test.mjs
git diff --check
```

The required unfiltered audit prints OK. The additional full-main filtered
audit reports an existing 35.859px-wide Clear link at both viewport sizes;
that unrelated tap-target defect is recorded, not hidden or changed here.
The full PHP suite reports **81 passed, 0 failed**, with existing CLI session
and header warnings. Isolation regressions report **7 passed, 0 failed**.
Browser syntax and diff whitespace checks pass. No PHP files were changed.
A preliminary after-geometry run stopped with an
image decode error; only the successful rerun is acceptance evidence.
The rerun prints OK for all 12 route/viewport combinations, including the
clipping-disabled checks, with zero document overflow in every case.

Inspected before/after images are in gitignored
`UIPROBLEMS/after/hero-size-{before,after}/` (index/doctors, 390/1280) and
`hero-size-{before,after}-geometry/` (home/directory/filtered, all four widths).
Adjacent `rectangles.json` files preserve actual measurements with and without
clipping. Desktop directory subjects' heads now remain visible; captions and
filter/home controls remain visible. The directory's existing mobile table
scrolls within its own region, without document overflow.

## Issue #129: home specialty-to-footer spacing (4 October 2026)

Finding: C01, new finding 10, supplied references UI_Defects.pdf pp. 4 and 7.
The named PDF is absent from this checkout; the supplied written finding is
the source, verified by fresh browser measurements. Starting source is
`64354f5` (includes dependency #128), with a clean working tree. The historical
`eb9a791` is not the current baseline. Existing visit edits, visit-state test
and prior report entries are preserved. The #114 duplicate-destination
carry-over remains separate and is not claimed resolved here.

Reproduction: desktop `.grow-row > .grow-item` gave the specialty links the
same `min-height: 38rem` (608px) as doctor tiles. The apparent gap was inside
those links, not an oversized footer margin. Move that minimum to
`.doctor-tiles > .doctor-tile` only. Specialty links now size to their content;
the unchanged hover/focus image height and padding grow the strip in normal
flow and push the footer down. No negative margins, clipped content, hidden
sections, font changes or page-height rules were added. Shared page width,
doctor tiles and short-page footer behaviour are retained.

Document coordinates in CSS pixels, settled anonymous home state; normal and
reduced-motion measurements are identical:

| Viewport | State | Strip bottom | Text bottom | Footer top | Strip/footer gap | Text/footer gap |
|---|---|---:|---:|---:|---:|---:|
| 1280x800 | Before | 2117.719 | 1728.969 | 2177.719 | 60 | 448.750 |
| 1280x800 | After | 1737.766 | 1728.969 | 1797.766 | 60 | 68.797 |
| 390x844 | Before | 4144.016 | 4135.219 | 4204.016 | 60 | 68.797 |
| 390x844 | After | 4144.016 | 4135.219 | 4204.016 | 60 | 68.797 |

The unnecessary reservation removed is 379.953px. Remaining 60px is the
services section's `--space-5` bottom padding (36px) plus main's existing
24px bottom padding; the additional 8.797px is the link's bottom padding.
At desktop, hovering or keyboard-focusing the first/middle/last item moves
strip bottom to 2000.578 and footer top to 2060.578, preserving the 60px gap.
The regression checks item/content/footer bounds and horizontal overflow
for all six interactions at both widths and in both motion modes. Focus
cases use keyboard traversal and assert `:focus-visible`. Existing grid
placement, hover padding/image growth and reduced-motion rules are preserved.

The directory and empty filtered short route
`doctors.php?specialty=NoSuchSpecialty` retain main/footer alignment. Footer
tops are respectively 1092.813/638.406 at desktop and 1193.188/788.734 at
mobile, identical before and after. The short footer remains immediately
after main, as before; no sticky-footer redesign is introduced.

Fixtures: public anonymous routes, five-doctor test seed, no authenticated
flow, synthetic mutation or fixed date needed. Measurements use
`withTestServer()` and verify the child resolves `ie4727db_test`, with mail
disabled. Screenshot/audit commands explicitly inherit
`CLINIC_DB_NAME=ie4727db_test`; PHP config rejects a local override. No live
database reset or cleanup was performed. PHP suite resets are test-only.

Commands/results:

```text
node --test tools/ui/isolation.test.mjs
node tools/ui/home-gap.test.mjs --before
node tools/ui/shoot.mjs --serve --label home-gap-before index.php doctors.php
node tools/ui/home-gap.test.mjs
node tools/ui/shoot.mjs --serve --label home-gap-after index.php doctors.php
node tools/ui/audit.mjs --serve --within main layout,tap-targets,palette,contrast,focus,slop index.php doctors.php
node tools/ui/hero-size.test.mjs
php tests/run.php
node --check tools/ui/home-gap.test.mjs
git diff --check
```

The home geometry regression, existing 12-case hero geometry regression and
required main-scoped audit print OK; isolation tests pass 7/7. The final serial
PHP suite reports **81 passed, 0 failed**, with the existing CLI session/header
warnings. Browser syntax and diff whitespace checks pass. No PHP source was
changed. Before/after screenshots
were opened at both widths in gitignored
`UIPROBLEMS/after/home-gap-{before,after}/` (index/doctors). Additional settled
home, directory, short-page and focused-item images and document rectangles
are in `home-gap-{before,after}-geometry/measurements.json` and adjacent PNGs.
Opened focused middle/last normal/reduced-motion images show the footer
below the grown item. Opened short-page before/after desktop and after phone
images retain the existing alignment. An initial capture run was interrupted
and a preliminary overly broad horizontal-growth assertion was corrected:
the existing specialty strip uses grid placement. Successful completed runs
are the evidence reported above.
