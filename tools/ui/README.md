# UI screenshots and geometry audit

These scripts are development tools. They run the PHP app from this checkout at
`http://127.0.0.1:8123/clinic-base/`; they are not referenced by shipped pages.
Port 8000 is rejected because it can serve a stale XAMPP copy.

## Capture pages

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
