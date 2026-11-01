# 0211: Iteration 9 gate: automated regression suite and Playwright UI verification for search and print slip
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #211 · Date: 2026-10-28

## In short

Automated PHP regression harness and browser-in-the-loop Playwright Chromium tests verify the two iteration 9 standout clinical features: client-side doctor directory search with zero network transport and patient consultation slip print rendering under `@media print`.

## Problem and constraints

Iteration 9 introduced two standout user-facing features to the clinic-base portal:
1. Live client-side doctor directory filtering (#209).
2. Printable patient consultation certificate with native `@media print` styling (#210).
Before closing iteration 9, deterministic automated regression coverage was required in `tests/` and an end-to-end browser test was required in `tools/ui/` to guarantee that:
- Search filtering behaves reliably without AJAX or JSON transport.
- The print action button triggers `window.print()` and `@media print` stylesheets cleanly conceal page navigation/chrome.
- Mobile viewports (390px) and desktop viewports (1280px) render without layout overflow or breakage.
- Cross-patient isolation prevents patients from viewing consultation slips for appointments belonging to others.
- The overall test suite `php tests/run.php` remains 100% green without regressions (116 passing tests).

## Main design choice

Rather than relying on PHP unit tests alone or combining all checks into existing test scripts, we chose a two-tier verification strategy. A fast PHP regression test validates markup, style rules, absence of network calls, and database model isolation directly in the test suite. An end-to-end browser test spins up the isolated PHP test server to verify live DOM filtering, Enter key behavior, zero network requests, noscript fallback, and print media emulation in headless Chromium across desktop and mobile screens.

## How it works

The regression check begins during the standard test suite execution. It scans the doctor directory template, client-side script, stylesheet, and patient home template to confirm all required search and printable slip elements are present, confirming that no network transport mechanisms exist. It then queries the test database directly to ensure completed appointment records are retrieved correctly and that cross-patient access attempts return null.

The browser suite launches an isolated test server using the test database and opens Chromium sessions at desktop and mobile widths. It monitors network traffic during live typing to ensure zero background requests occur, verifies that doctor rows filter dynamically by name and language, checks that unmatched queries show the empty state, and confirms Enter key presses do not cause page reloads. It then tests noscript fallback with JavaScript disabled. Next, it signs in as a patient, views a completed consultation, verifies the certificate layout and print button, and emulates print media to confirm that page navigation, footers, and buttons are hidden while the certificate remains visible and crisp. Finally, it confirms that attempts to view visits belonging to other patients render no certificate.

## Breaks or needs follow-up

None.

## Alternatives considered

- Relying solely on PHP unit assertions: rejected because client-side JavaScript DOM manipulation, CSS grid specificity overrides on mobile (`tr[hidden]`), and `@media print` layout emulation require a real browser engine.
- Adding tests to existing test files: rejected because issue #211 defines a dedicated iteration 9 verification gate with its own scope (`tests/test_standout_features.php` and `tools/ui/standout-features.test.mjs`).

## Assumptions

- Headless Chromium is installed and executable via Playwright in the environment.
- The test database `ie4727db_test` is seeded with completed appointment 2 for patient 1 (Alex Tan) with attending doctor Dr. Priya Nair.

## Edge cases

- Unmatched doctor search query ("xyznonexistent") hides all table rows and shows `#doctor-search-empty`.
- Clearing search input immediately restores visibility to all table rows.
- Mobile viewport (390px) where `display: grid` on table rows could override `hidden` attribute without `tr[hidden] { display: none !important; }`.
- Print media emulation hides all navigation, footers, appointment tables, and flash notices.
- Patients attempting to view another patient's appointment (`patient/home.php?view=3`) or a non-existent appointment (`?view=999999`) see no consultation slip and no print button.

## Rollback

Delete `tests/test_standout_features.php` and `tools/ui/standout-features.test.mjs`.

## Traps

- `assets/style.css` contains broad rules hiding `<header>`; ensure print rules for `.medical-slip` keep header details visible while concealing `.site-header`.
- `tests/run.php` executes all files matching `tests/test_*.php`; `tests/test_standout_features.php` must follow the convention and print `PASS: test_standout_features\n` on success.
- Chromium print media emulation must be restored to `screen` after print assertions to avoid polluting subsequent assertions in the test context.

## Key parameters

- PHP test script: `tests/test_standout_features.php`.
- UI test script: `tools/ui/standout-features.test.mjs`.
- Test database: `ie4727db_test`.
- Desktop viewport: `1280x800`.
- Mobile viewport: `390x844`.
- Screenshots output: `UIPROBLEMS/after/iter9/standout-features/`.

## Components

- `tests/test_standout_features.php`: PHP unit and integration assertions for search markup, zero AJAX, app.js logic, print styles, and appointment model isolation.
- `tools/ui/standout-features.test.mjs`: Playwright Chromium test suite covering live search filtering, zero network transport, noscript fallback, print media styles, and cross-patient isolation.

## Changes outside the scope

The decision record and the two test files are verification artifacts for issue #211.

## How it was verified

- `php -l tests/test_standout_features.php` passed syntax checks.
- `php tests/run.php` passed with all 116 tests green.
- `node tools/ui/standout-features.test.mjs` passed cleanly, verifying DOM search filtering, zero fetch/XHR network requests, mobile CSS overrides, and print layout at both 1280px and 390px viewports.

## Trade-offs

Running Chromium browser tests alongside PHP CLI tests increases test runtime slightly (~15 seconds) but provides genuine end-to-end assurance that DOM manipulation, print stylesheets, and responsive CSS rules function correctly.

## Not verified

Off-box mail delivery and external hosting environments; all test runs are strictly isolated to `ie4727db_test`.

## To change this

If search attributes or consultation certificate selectors change in future iterations, update the selectors and assertions in both `tests/test_standout_features.php` and `tools/ui/standout-features.test.mjs`.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-28 01:35 UTC for issue #211 attempt 9; compared HEAD..working tree (base cca0d92). Records are claims to check against the code, not proof.

**Change summary**

No code components changed.
- plus 2 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `tests/test_standout_features.php`, `tools/ui/standout-features.test.mjs`. Verdict: pass.

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, scope check, dangling reference check

**Evidence**

0 checked by the tool, 2 tested in this run, 21 claimed only (of 23 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 0 checked, 0 tested, 2 claimed
- Key parameters: 0 checked, 2 tested, 4 claimed
- Edge cases: 0 checked, 0 tested, 5 claimed
- How it was verified: 0 checked, 0 tested, 3 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
  - [tested] Key parameters: PHP test script: `tests/test_standout_features.php`. (test gate passed: project test harness)
  - [tested] Key parameters: UI test script: `tools/ui/standout-features.test.mjs`. (test gate passed: project test harness)
  - [claimed] Components: `tests/test_standout_features.php`: PHP unit and integration assertions for search mark... (names no code)
  - [claimed] Key parameters: Test database: `ie4727db_test`. (no cited constant with a matching value)
  - [claimed] Edge cases: Unmatched doctor search query ("xyznonexistent") hides all table rows and shows `#docto... (names no code)
  - [claimed] How it was verified: `php -l tests/test_standout_features.php` passed syntax checks. (no test named)
  - [claimed] Components: `tools/ui/standout-features.test.mjs`: Playwright Chromium test suite covering live sea... (names no code)
  - [claimed] Key parameters: Desktop viewport: `1280x800`. (no cited constant with a matching value)
  - [claimed] Edge cases: Clearing search input immediately restores visibility to all table rows. (names no code)
  - [claimed] How it was verified: `php tests/run.php` passed with all 116 tests green. (no test named)
  - full per-claim list (23) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0211-standout-features-verification.md` gives the commit.
<!-- verified-facts:end -->
