# Iteration 4 verification — issue #132

Verified application baseline: `3610f31d0890f50b129f600e201f10a63e1a3816`, on the existing
`automation/ie4727-clinic` branch, 4 October 2026 (Asia/Singapore).
The resumed working tree contained this draft report and
`tools/ui/iter4-roles.test.mjs`, restored from `deferred/issue-132` after
the pre-run cleanup. Both are completed here. This issue changes
tooling/reporting only, including static transport diagnostics and stricter
image capture checks.
No application, schema, audit thresholds or page-specific exemptions were changed.
The generic focus probe now excludes native `:disabled` controls, which cannot
receive focus; enabled controls still require a visible indicator.
The tooling changes described here are committed with this report; the app
and schema remain exactly at that verified baseline.

The requested `UIPROBLEMS/Open/UI_Defects.pdf` is absent. The available
`clinicissues.pdf` and older rendered pages are a different report and are
not substituted or renamed. Page references below are supplied references,
not a claim to have inspected the missing round-2 PDF. Consequently the
fifteen historical repairs cannot be independently reconciled row by row
against PDF pp. 5–6. Their existing source/tests are retained; the report's
historical 77 tests is not the total for this run.

## Source reconciliation

Dependencies #120–#131 are committed in this baseline. `eb9a791` alone is
not the verified app. The original historical report in
`docs/UI_AUDIT_REPORT.md` is retained, including its statement about then
uncommitted visit work; the later #121 completion supersedes that state.
`doctor/visit.php`, the report and `tools/ui/visit-state.test.mjs` have no
initial working-tree diff and are committed, including #121 and #125 work.

`deferred/issue-119` remains intact at `e0161ec`: its tracked difference
against its first parent is two notification timestamp edits and a seed-test
tie-order assertion (`schema/003_seed.sql`, `tests/test_seed.php`). Its third
parent lists no untracked files. Current #124 seed code uses one
`@seed_now` and distinct notification times; it does not contain that exact
deferred patch. No deferred work was silently restored, discarded or called
committed. The overlapping visit-state acceptance is verified under the
existing #121 work, without another implementation issue. GitHub #119 remains
open; its deferred timestamp patch remains preserved. #121 and #125 already
own the committed visit-state/history implementation. No automatic stash
restore, merge, reset or duplicated visit implementation was performed.

Carry-over **#114: partly fixed, unresolved destination defect**. Its commit
`8de52d7` and existing regression are present. Patient Home and My appointments
point to `patient/home.php` and its appointments fragment; doctor Home and
Appointments do the equivalent. Home is current on the appointments page.
One current tab does not resolve the wrong destination. Fresh captures and
supplementary navigation measurements document this. GitHub #114 was closed;
it is now reopened with the reproduction, as #132 requires. No duplicate issue
is created, and this carry-over is separate from the twelve-finding verdicts.

`git diff eb9a791 HEAD -- clinic-plus` and `git diff HEAD -- clinic-plus`
are empty. This run also leaves clinic-plus unchanged against verified HEAD.
Pre-existing divergence: clinic-plus has 66 paths versus local main (the
existing fork); its app sources differ from the repaired base. Neither
comparison means clinic-plus and clinic-base are identical.
Comparing all 66 tracked plus paths to matching base paths yields 29
pre-existing differing/plus-only paths (including board.php, events.php and
slots_fragment.php). This is recorded separately from this run's empty diff.

## Isolation and evidence method

First executable check: `node --test tools/ui/isolation.test.mjs`, **7/7**.
Missing, live and unexpected names refuse before launch/DB includes; conflicting
resolved constants refuse in config-only processes. The occupied-port refusal
uses a stub HTTP server. None of the refusal cases connects to live data.
Mutation regressions use existing `testPhp`/`withTestServer`: config is checked
before launch, the owned child response identifies resolved `ie4727db_test`,
and fixtures verify `SELECT DATABASE()` before writes. Mail is disabled in
CLI and HTTP mutation children. Tests run serially, never alongside a reset.

All-page commands explicitly select `CLINIC_DB_NAME=ie4727db_test` and use
the checkout server at `127.0.0.1:8123/clinic-base/`. Their launcher now applies
the same config-before-launch, prepend guard, disabled mail, unique owned-child
token and resolved database header checks as mutation regressions whenever
the test database is explicitly selected. Supplementary
role captures use the stronger verified launcher, random synthetic patient and
doctor accounts, real form login, explicit appointment IDs and no JavaScript.
The config-defined test admin is supplied by the existing isolated launcher.
Each route must remain its intended HTTP-200 URL, rather than a login redirect.
Viewports are 1280x800 and 390x844.

Initial hero-size attempts failed on interrupted static-image connections,
including a filtered-directory image decode. Diagnostics recorded
`net::ERR_CONNECTION_RESET`; a direct request returned HTTP 200, JPEG bytes
and the complete 119031-byte image. The failure also occurred outside the
sandbox, so it is not attributed solely to sandboxing. PHP logged 200 responses
whose bodies were interrupted. `hero-size-first-attempt.log` and
`hero-diagnostic.log` retain failure evidence in the gate-log directory.

The shared tooling now serializes local asset GET transfers per page through
Playwright's HTTP client, preserving actual server responses and bytes. It
allows two retries only for connection resets/aborted response bodies; HTTP
statuses do not trigger retries. POSTs and non-asset routes are not intercepted.
`static-assets.test.mjs` uses a database-free HTTP stub to verify reset and
truncated-body recovery, serialized transfers, byte preservation, failed 404
and corrupt-image decoding, an unchanged single failed POST and safe teardown
when a page closes during a pending asset request. Active-page failures still
propagate. Navigation waits for DOM readiness, decoded images and the load event.
This changes
transport reliability, not app markup, geometry assertions or audit thresholds.
The capture command makes lazy images eager and requires every image to decode;
it no longer silently ignores failed images. Optional `UI_SERVER_DIAGNOSTICS=1`
prints PHP request logs. The agent-browser CLI is unavailable; the already
installed repository Playwright/Chromium tooling supplies browser verification.

No live database connection, reset, cleanup or delivery was made by #132.
The #131 live inventory and unchanged-row fingerprints are prior evidence,
clearly separate from this run's test-copy verification. Live deletion remains
deferred to the owner. The suite exercises read-only inventory, bounded
test-only apply/backup/restore, provenance/dependency refusals and unchanged
unrelated rows. Its live-target refusals happen before connection.

## Twelve new findings

Screenshot names below are relative to gitignored `UIPROBLEMS/after/`.
Pairs written as `{1280,390}` indicate captures at both widths; the explicitly
inspected examples are listed below.
These are final-state regressions of existing repairs, not twelve new repairs.

| Finding / supplied PDF page | Owner | Verdict | Reproduction and final evidence |
|---|---|---|---|
| 1 / p. 3 | #120 | Verified | Old registration harness could inherit live config. Seven fail-closed checks passed before mutations. Exact test-fixture patient/doctor counts go 0/0 → 2/1 → 0/0 after cleanup. Untouched JS/no-JS patient and switched doctor registration pass; `issue120-after/{patient-js,patient-nojs,doctor}-{1280,390}.png`. |
| 2 / p. 3 (detail p. 14) | #121; overlapping #119 work | Verified | Future Rescheduled visits correctly say they have not started and state their start time. Future/past status matrix, unchanged rejected early/foreign saves, all-field historical saves, reason/remarks preservation and unchanged outbox pass; `visit-state-matrix-after/{rescheduled,editable}-{1280,390}.png`. |
| 3 / p. 3 (detail pp. 14–15) | #122 | Verified | Two fresh resets expose two elapsed owned Future visits. No-JS Completed/No show succeed; future/foreign POSTs preserve appointments, slots and notifications. `outcome-seed-actionable/doctor-home-{1280,390}.png` and `outcome-seed-outcomes/doctor-home-{1280,390}.png`. |
| 4 / p. 3 (detail p. 15) | #123 | Verified | Keyboard and native touch scroll reach rightmost cells; Tab reaches >=44px Delete controls. On phone, 358px regions contain 966/1005/672px patient/doctor/appointment tables without viewport overflow in the final fixture run. `admin-phone-after/{patients,doctors,appointments}-rightmost-390.png`, filtered/empty desktop/phone captures and measurements. CSRF rejection, cancelled confirmation, confirmed cascade and PRG pass. |
| 5 / pp. 3–4 (detail p. 6) | #124 | Verified | Fresh lead-time coverage is 27/27, mean 193/27 days (7.1 displayed); mixed 3/5 has mean 4/3 days, invalid 0/1 and empty 0/0 are unavailable. Timestamps remain unchanged. `leadtime-regression-after/{seed,mixed,invalid,empty}-{1280,390}.png`. |
| 6 / p. 4 (detail p. 14) | #125 | Verified | Exactly two earlier completed visits remain before save and after save/refresh, with zero overflow; empty history has zero. Same-time, later, foreign and ineligible outcomes are excluded. No-JS notes, patient/doctor ownership and every saved field pass. `visit-history-flow-after/{before-save,saved-refresh,empty}-{1280,390}.png`. |
| 7 / p. 4 (detail pp. 11–12) | #126 | Verified | Original/next available landing, explicit/elapsed/booked/blocked/empty/filtered days, no-window explanation, keyboard/no-JS, stale conflict and atomic replacement pass. `reschedule-day-after-flow/{landing,elapsed,no-availability}-{1280,390}.png` and per-capture geometry. Remaining states are exercised programmatically. |
| 8 / p. 4 (detail p. 7) | #128 | Verified | Real composited photograph contrast is >=9.291:1 across all six size/text/zoom cases; both heading and eyebrow stay in bounds, and login does not overlap. `hero-contrast-after-measurements/contrast.json`, `{1280,390}-text100-zoom1-viewport.png`, enlarged-text and zoom PNGs. |
| 9 / p. 4 | #127 | Verified | Three public routes at 390/768/1280/1920 pass with clipping enabled and disabled. Home image/banner is 326×183.38px at 390 and 618.66×371.19px at 1280; directory is 358×240px and 1024×281.59px. Cover, caption/scrim anchors, desktop height cap and no upscaling pass. `hero-size-after-geometry/rectangles.json` and `{home,directory}-{1280,390}.png`. |
| 10 / p. 4 (detail p. 7) | #129 | Verified | Settled, first/middle/last hover/focus and reduced-motion checks confirm the specialty item has a 44px control minimum, replacing 38rem. Footer gap is 60px, visible content-to-footer gap 68.797px, at both widths and motion settings. Shared directory/short-page alignment passes without overlap. `home-gap-after-geometry/measurements.json`, `home-{1280,390}-no-preference.png` and `{directory,short}-{1280,390}.png`. |
| 11 / pp. 4–5 (detail p. 14) | #130 | Bounded deliverable verified | Raw Remarks remains encoded under the fixed schema. PHP checks pass readable CLI inspection, lossless editing, malformed/legacy fallback and zero-write fingerprints. Doctor/patient decoded notes are inspected in history and supplemental captures. `docs/REMARKS.md` records physical-storage/export limitations; no raw plaintext export or schema redesign is claimed. |
| 12 / p. 5 | #131 | Bounded deliverable verified | Prior read-only inventory is 7/11/3640/28/12 with 3698 unchanged fingerprints, three source-correlated accounts, eight seed-identical accounts and preserved ambiguous/manual rows. Three PHP reconciliation cases pass test-only backup/apply/restore, provenance/dependency refusals and preserved manual sentinel. Fresh isolated reset/attendance coverage passes; no live connection is repeated. Proposed live deletion remains owner-only. |

## Commands and route coverage

The full PHP suite reports **85 passed, 0 failed**, including acceptance matrix,
SQL/escaping/guard audits, temporal and ownership protections, double-booking
race, atomic reschedule, remarks inspection/preservation, notification delivery
semantics and bounded data reconciliation. Existing CLI session/header warnings
are non-fatal. There are no touched PHP files to lint in #132.

The all-page manifest covers eleven page identifiers, twenty-two captures.
`doctor/visit.php` is resolved by the existing tool to an owned Future appointment;
the explicit current/historical visit routes are covered separately by fixtures.
The doctor day board's default current-day view is an empty Sunday, so outcome
coverage requires the supplementary actionable seed regression. The PHP suite
leaves deliberate 2040 samples; those are cleared by an explicit guarded test
reset before the final all-page capture/audit. The final fresh seed has 5
doctors, 8 patients, 3570 rolling slots, 27 appointments and 2 logged notices.
The slot count is specific to this run's 30-day weekday window. The supplementary role captures contain deliberate synthetic status/history
fixtures; they are not presented as fresh seed.

Supplementary routes: owned patient completed notes, doctor home, doctor
schedule, explicit owned historical visit, admin console and admin outbox.
All six stay on their expected HTTP-200 URL, expose resolved `ie4727db_test`,
have distinct titles, loaded images, zero browser runtime errors and zero
viewport overflow at both widths. Their exact route IDs, fixture time,
navigation destinations, screenshot paths and unscoped audit result are in
`iter4-roles-final/measurements.json` and `audit.log`. All twelve images were
opened, including the patient and doctor decoded-remarks views. The synthetic
doctor has no slots on its schedule and no visits on today's Sunday; separate
attendance and reschedule regressions cover actionable states.

Commands are run serially from the repository root, with explicit
`CLINIC_DB_NAME=ie4727db_test` for all-page commands:

| Command | Result |
|---|---|
| `node --test tools/ui/isolation.test.mjs` | 7/7; refusal checks precede all mutations |
| `node --test tools/ui/static-assets.test.mjs` | 1/1; transport and negative asset/POST checks |
| `node tests/ui_register.mjs` | PASS; isolated 0/0 → 2/1 → 0/0 fixture counts |
| `node tools/ui/outcome-seed.test.mjs` | OK; two reproducible test resets, attendance and refusals |
| `node tools/ui/visit-state.test.mjs` | OK; future/past status matrix, no-JS saves, ownership |
| `node tools/ui/admin-tables.test.mjs` | OK; keyboard/touch reachability, delete/cascade/CSRF/PRG |
| `node tools/ui/leadtime.test.mjs` | OK; 27/27, 3/5, 0/1, 0/0 and preserved timestamps |
| `node tools/ui/visit-history.test.mjs` | OK; earlier-only history and notes/access flows |
| `node tools/ui/reschedule-day.test.mjs` | OK; landing, empty messages, stale conflict, atomic replacement |
| `node tools/ui/hero-size.test.mjs` | OK; three routes × four widths, clipping disabled too |
| `node tools/ui/hero-contrast.test.mjs` | OK; six glyph/photograph cases and member login |
| `node tools/ui/home-gap.test.mjs` | OK; geometry, focus/hover, reduced motion and alignment |
| `node tools/ui/iter4-roles.test.mjs` | OK; six authenticated routes, twelve captures and unscoped supplementary audit |
| `node tools/ui/audit.mjs --selftest` | OK; good/bad/scoped fixtures |
| `php tests/run.php` | 85 passed, 0 failed; C01–C20 and all existing boundary audits |
| `php tools/db_reset.php --test` | Fresh isolated counts above; no live connection |
| `node tools/ui/shoot.mjs --serve --label iter4-final all` | Eleven page identifiers, 22 desktop/phone captures; all images decode |
| `node tools/ui/audit.mjs --serve layout,tap-targets,palette,contrast,focus,slop all` | OK; unscoped all-page gate |

`node --check` passes for every touched `.mjs`. No PHP source was touched.
The fifteen other historical repairs (#103–#118 excluding #114) retain their
commits and existing regressions. Later #120–#131 changes refine that source;
this gate adds no app/schema changes. The absent round-2 PDF prevents an
independent row-by-row reconciliation of its pp. 5–6, so preservation is proved
by source/diff inspection and fresh executable checks, not a claimed PDF read.

Opened final all-page pairs under `iter4-final/`:
`index`, `doctors`, `doctor`, `book`, `register`, `patient-home`, `doctor-home`,
`doctor-schedule`, `doctor-visit`, `admin-console` and `admin-outbox`, each
`-{1280,390}.png`. Inspected finding-specific examples include all registration
captures, future rescheduled visit, actionable doctor home, filtered desktop
admin and each rightmost phone table crop, seed lead-time, saved/refreshed
history, reschedule landing and no-availability, home/directory hero geometry,
standard desktop/phone hero contrast and home footer geometry. Additional
matrix variants are captured and checked programmatically; not every variant
was opened. Native table scroll is intentional; the admin phone regression proves
rightmost cells/actions remain reachable. These captures are not a claim that
the separate Iteration 5 issues are complete.

Gate command output is kept in gitignored
`UIPROBLEMS/after/iter4-gate-logs/`; screenshots and measurements remain under
`UIPROBLEMS/after/`, never force-added. The final all-page audit is unscoped.
No page-specific exemption or threshold is changed to manufacture a passing gate.

The first final all-page audit reported eight focus failures on disabled
doctor-registration fields in the inactive role panel. Native disabled controls
cannot be focused, although their sliding panel retains geometry. The generic
probe now uses `:disabled` (including disabled fieldsets); its selftest checks
disabled input/textarea/select/button controls pass and an enabled unstyled input
fails at both widths. Focus styling for enabled controls remains mandatory.
`audit-disabled-focus-failure.log` preserves the original result.
