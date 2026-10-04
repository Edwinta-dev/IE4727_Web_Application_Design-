# UI screenshots and geometry audit

For booking URLs (#144), run `node tools/ui/booking-urls.test.mjs` as the
acceptance command, then `php tests/run.php test_issue144` and
`php tests/run.php`. The browser test uses only `ie4727db_test`, disables mail,
restores the seed in `finally`, and covers 3/7/12 doctors and slots at 1280x800
and 390x844 without JavaScript. Raw specialty fixtures have 3/7/12 values;
the existing canonical specialty filter exposes at most five supported services.
Do not expand that vocabulary for this booking issue.

`--before` records the current DOM and screenshots without requiring the new
URL/availability state. Before evidence in
`UIPROBLEMS/after/issue144-before-matrix/measurements.json` shows a Book link
for both a doctor without slots and one whose only slot is on day eight, with
all directory links using `doctor_id`. Valid legacy deep-link dates already
worked before this change; session-derived actor handling was also already
present. Generated URLs now use `doctor`, include the next bookable date, and
omit Book for unavailable doctors. The legacy input alias remains compatible.
The acceptance test forges POST actor values in both patient and doctor sessions
and checks the logged notification actor. After evidence is in
`UIPROBLEMS/after/issue144-after-matrix/`. Run serially with other DB tests.

These scripts are development tools. They run the PHP app from this checkout at
`http://127.0.0.1:8123/clinic-base/`; they are not referenced by shipped pages.
Port 8000 is rejected because it can serve a stale XAMPP copy.

## Capture pages

For home card heights (#137), run `node tools/ui/home-card-height.test.mjs`.
It prints `OK` after comparing actual card heights with unconstrained copies of
the same content at the same width, allowing equal-height carousel siblings and
only the existing vertical hover padding. It covers 1280/1707/390px, the seed
and 3/7/12 database-backed doctors and specialties, hover, keyboard focus and
reduced motion. Measurements and screenshots go to
`UIPROBLEMS/after/issue137-height/`. The guarded fixture/server use only
`ie4727db_test` and restore the seed in `finally`; run serially with other tests.

For specialty paging (#136), run `node tools/ui/specialty-strip.test.mjs`.
It verifies database-backed 3/6/10/7/12 specialties at 1280/1024/768/390,
hover allowance, keyboard paging, resize, reduced motion and no-JS access.
Use `--before` to record the original widths and row counts without asserting
the new paging behavior. Fixtures and the server use only `ie4727db_test`.

From the repository root, run `node tools/ui/shoot.mjs --serve --label <label>
<pages|all>`. Page arguments are app paths such as `index.php`,
`doctor.php?id=1`, or `patient/home.php`. `all` captures the eleven pages listed
in `docs/PAGES.md`. Each page is captured full-page at 1280x800 and 390x844;
files go to `UIPROBLEMS/after/<label>/`.

Protected pages log in through the real form on `index.php`: patient `alextan`,
doctor `drsmith`, admin `admin`, all with `Password123`. Override credentials
with `UI_PATIENT`, `UI_DOCTOR`, `UI_ADMIN`, and `UI_PASSWORD`. A login failure
names the role and stops the run.

## Audit

Run `node tools/ui/audit.mjs --serve layout,tap-targets,slop all` to measure
horizontal overflow, navigation and header geometry, image and table geometry,
interactive hit areas, copy, typography, and decorative effects at both widths.
Failures print page, width, selector, and measured value. Available additional
checks are `palette`, `contrast`, and `focus`; choose them with
`--checks=palette,contrast,focus`. Run `node tools/ui/audit.mjs --selftest` to
verify the good and bad HTML fixtures against every audit category.

### Auditing one region: `--within <selector>`

An issue that owns one part of the page (the header, the doctor tiles, the
directory table) gates on that part only, so it is not blocked by defects that
later issues own:

```
node tools/ui/audit.mjs --serve --within .site-header layout,tap-targets,focus,slop index.php
```

With `--within`, element checks (images, tap targets, focus, table cells,
borders, effects, copy) only look inside elements matching the selector, and
the overflow rule asks whether that region overflows the viewport. Page-wide
typography rules (body/heading font, body size) are skipped; they belong to
the full-page gate. The header/nav geometry rules always run. A selector that
matches nothing is a failure, not a pass. The final gate always runs without
`--within`.

Open captured PNGs at both viewport widths and compare with the owner references
in `UIPROBLEMS/` before declaring a visual task complete. Screenshots are
gitignored evidence and must not be committed.

## Isolated browser regressions (#120)

Run from the repository root, one command at a time (all use port 8123):

```text
node --test tools/ui/isolation.test.mjs
php tests/run.php
node tests/ui_register.mjs
node tools/ui/booking-dates.test.mjs
node tools/ui/schedule-horizon.test.mjs
node tools/ui/admin-flash.test.mjs
node tools/ui/get-csrf.test.mjs
```

Each executable browser test explicitly selects `ie4727db_test` before launching
PHP. Mutation tests use `withTestServer()` and `testPhp()`: these reject missing,
production and unexpected database names, load the effective local PHP config
without connecting, then guard the child again before any database include.
Do not replace them with an unguarded PHP spawn or `withServer()`.
`UI_BASE_URL` and reuse of an existing server are forbidden for these tests.
A per-run response token and resolved database header identify the owned server;
an occupied port fails rather than accepting a stale/live server. Mail is disabled
in both CLI fixtures and the HTTP child; notifications still log before attempted
delivery. Ordinary site configuration is unchanged.

Booking, schedule and admin tests rebuild **only** `ie4727db_test` and print
its seeded fixture counts. Registration does not reset: it verifies the database
identity and counts its exact randomly suffixed usernames before/after the three
registrations, then deletes only those usernames in `finally`, including failures.
It prints no passwords and leaves no synthetic registration profiles behind.
Its authenticated desktop/mobile captures are in gitignored
`UIPROBLEMS/after/issue120-before/` and `issue120-after/`.
Never run these regressions concurrently or with the PHP suite because the three
resetting tests share the isolated database. Refusal regressions use launcher
stubs, config-only PHP processes and a stub HTTP server; they never open a DB.

Historical contamination and the audited baseline are recorded in
[UI_TEST_ISOLATION.md](../../docs/UI_TEST_ISOLATION.md). Live cleanup is a separate
owner-reviewed task; these commands do not delete or reset `ie4727db`.

## Iteration 4 gate (#132)

The report and full command results are in
[UI_ITERATION4_GATE.md](../../docs/UI_ITERATION4_GATE.md). Run
`node tools/ui/iter4-roles.test.mjs` serially after the isolation checks to
capture six explicit synthetic authenticated routes at both widths, verify
HTTP status/URL and resolved test database, and audit those same routes.

The shared browser helpers retrieve local `assets/` GETs from the same PHP
server through Playwright's HTTP client, one static transfer per page at a time.
Only connection resets or interrupted response bodies receive up to
two transport retries; responses and asset bytes are preserved. POSTs and
non-asset requests are not intercepted. This handles observed Windows `php -S`
static-socket resets without using the Apache copy or replacing failed images.
`node --test tools/ui/static-assets.test.mjs` verifies reset recovery, unchanged
404/corrupt-image failures, and a non-retried failed POST against a database-free
stub server. Set `UI_SERVER_DIAGNOSTICS=1` to include PHP request logs when
investigating a run; avoid publishing logs containing private route identifiers.

Full-page capture makes lazy images eager and requires successful decoding of
every image. A blank or broken image must fail the capture, not produce evidence
that silently ignores it. Existing audit rules and thresholds are unchanged.

The focus audit tests enabled controls. Native `:disabled` controls, including
controls in disabled fieldsets, cannot receive focus; selftests verify these
pass while an enabled input without a focus indicator fails at both widths.
Asset teardown errors are ignored only after the page closes. Active-page
failures still propagate; navigation waits for decoded images and the load event.

For featured-doctor paging (#134), run `node tools/ui/featured-doctors.test.mjs`.
It prints `OK` after testing 3, 7 and 12 database-backed doctors at 1280,
1024, 768 and 390px, including hover/focus stability, keyboard arrows and end
states, resizing, reduced motion and the no-JS scroll-snap fallback. Screenshots
and measurements are saved under `UIPROBLEMS/after/issue134-after-matrix`.
`--before` captures the original geometry without asserting carousel behavior.
The fixture and server require resolved `ie4727db_test`; the test rebuilds it
before each doctor count and restores the seed in `finally`. Run serially with
other database tests.

For specialty magnification (#135), run `node tools/ui/specialty-hover.test.mjs`.
It prints `OK` after testing hover and keyboard focus at 1280, 1707 and 390px,
with the seeded specialties and 3/7/12 distinct database-backed specialties.
It checks uniform magnification, 16:9 images, unchanged text line counts,
stationary siblings/footer, no overlap or horizontal overflow, and no geometry
change under reduced motion. `--before` records the original geometry.
Measurements and hover screenshots go to `UIPROBLEMS/after/issue135-*-matrix`.
The guarded fixture/server use only `ie4727db_test`; the seed is restored in
`finally`. Run serially with other database/browser tests.

For booking slot expansion (#139), run `node tools/ui/booking-slots.test.mjs`.
It prints `OK` after measuring unchanged sibling heights and a single open
confirmation with JavaScript, keyboard opening, and usable native forms without
JavaScript at 1280 and 390px with 3, 7 and 12 database-backed slots. Existing
time filters select the list lengths from the seed. `--before` captures original
measurements; screenshots and measurements go to `UIPROBLEMS/after/issue139-*-matrix`.
The guarded server uses `ie4727db_test`, and the seed is restored in `finally`.
Run serially with other database/browser tests.

For patient dashboard acceptance (#143), run `php tests/run.php test_issue143`
and then `node tests/ui_patient_dashboard.mjs`. The guarded browser test uses
`ie4727db_test` and verifies distinct patient navigation, recipient isolation,
escaped subject/body and keyboard reveal without JavaScript, pending past visits,
and all five day-board counters with 3, 7 and 12 messages/appointments. It captures
1280x800 and 390x844 views under `UIPROBLEMS/after/issue143-functional` and checks
zero page overflow. The test resets the test DB first and removes its fixtures
in `finally`. Run serially with all
other database tests, including `php tests/run.php` (which resets the test DB).
