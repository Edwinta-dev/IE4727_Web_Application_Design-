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
