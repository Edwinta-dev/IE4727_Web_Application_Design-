# Navigation current-tab verification — issue #114

The 1 October 2026 PDF (`UIPROBLEMS/Open/clinicissues.pdf`, pages 4,
8–9 and 11) reports two raised tabs on signed-in patient and doctor pages,
stories C07/C11. Its blank screenshot boxes were not treated as visual evidence.
The printed finding was checked against fresh captures of this checkout.

At baseline `5c4d846`, the finding is already resolved. The shared navigation
uses `app_request_uri()` to remove the deployment prefix, strips the query,
and matches exact paths against role-specific destinations. Patient navigation
has separate appointments, directory and booking links; doctor navigation has
separate day-board and schedule links. Profile and visit subpages have no
mapped tab. Only `aria-current="page"` receives the persistent inset accent;
hover/focus previews raise their background without acquiring current state.
The latest navigation change is already present from issue #152 (`8cd5891`).

This change adds regression evidence, without changing app routing, guards,
logout, shared CSS/motion or registration. `tests/test_issue114.php` now also
rejects misleading route suffixes and query-string destinations.
`tests/ui_navigation_114.mjs` covers every existing page in its intended role
and each public page as visitor, patient, doctor and admin. At 1280×800 and
390×844 it checks exact current hrefs, the unique persistent accent, raised
geometry, hover, keyboard focus, reduced motion and untouched registration
FormData with and without JavaScript. Phone menus are opened using the keyboard.

Browser runs use the guarded server at `127.0.0.1:8123/clinic-base/`, explicitly
resolved `ie4727db_test`, with mail disabled. Seed logins are `alextan`, `drsmith`
and the isolated server's configured admin. Registration submission verification
uses the existing synthetic-user test, which cleans up its own users. Tests
run serially. No live database reset or off-machine delivery is performed.
The run date is 6 October 2026, Asia/Singapore; navigation has no time cutoff.

Evidence is gitignored under `UIPROBLEMS/after/`:

- `nav-current-before/`: all eleven pages at both widths, PDF page renders,
  and an inspected header contact sheet (`headers-inspected.png`).
- `nav-current-after/`: the issue's five acceptance routes at both widths.
- `nav-current-matrix/`: role/route screenshots with open phone menus and
  `measurements.json` containing each rest/hover/keyboard/reduced-motion state;
  inspected `visitor-headers-inspected.png`, `patient-headers-inspected.png`,
  `doctor-headers-inspected.png` and `admin-headers-inspected.png`.

Both before/after `headers-inspected.png` contact sheets were opened. The
patient and doctor home matrix PNGs were also opened individually at both
widths. Matrix screenshots intentionally retain keyboard focus: the outlined
preview is distinct from the current tab's inset accent. There are 52 route/width
views (22 mapped and 30 unmapped), with 208 measured interaction states.

For a 16px root font the current tab's final pseudo-element translation is
`matrix(1, 0, 0, 1, 0, -4)`. Preview state retains exactly the same current href
and persistent accent count. Reduced motion has no tab animation and at most
the shared 0.01ms transition reset. The existing scoped audit supplies the
layout, 44px tap-target, contrast and focus checks without relaxed thresholds.

Commands run serially from the repository root (browser acceptance commands
inherit `CLINIC_DB_NAME=ie4727db_test`):

```text
php -l clinic-base/partials/nav.php
php -l tests/test_issue114.php
node --check tests/ui_navigation_114.mjs
node tools/ui/shoot.mjs --serve --label nav-current-before all
node tests/ui_navigation_114.mjs
node tools/ui/shoot.mjs --serve --label nav-current-after index.php patient/home.php doctor/home.php doctor/schedule.php admin/console.php
node tools/ui/audit.mjs --serve --within .site-header layout,tap-targets,contrast,focus,slop index.php patient/home.php doctor/home.php doctor/schedule.php admin/console.php
node tools/ui/audit.mjs --serve --within .site-header layout,tap-targets,contrast,focus,slop all
node tests/ui_register.mjs
php tests/run.php test_issue114
php tests/run.php
git diff --check
```

Lint/syntax checks and screenshots succeeded. The navigation browser matrix
and both scoped audits printed `OK`. Registration passed actual native POSTs
with JavaScript on/off, counted two synthetic patients and one doctor in the
test database, then verified zero remaining synthetic accounts. The focused
PHP regression passed (1 passed, 0 failed). No audit exemptions, threshold
changes, new pages or clinic-plus edits were made. No unresolved navigation
contract limitation was found.

The full `php tests/run.php` finished with **95 passed, 0 failed** and exit 0.
It emitted nonfatal PHP session/header warnings in unchanged helpers/auth and
test setup. These were not suppressed or repaired as part of this navigation
issue. `git diff --check` passed. Touched files are this report,
`tests/test_issue114.php` and `tests/ui_navigation_114.mjs`.
