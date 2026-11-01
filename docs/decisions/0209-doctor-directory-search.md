# 0209: Doctor directory live client-side search filter
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #209 · Date: 2026-10-27

## In short

Prospective patients can instantly filter the doctor directory across doctor name, specialty, qualifications, and languages as they type using pure client-side vanilla JavaScript without AJAX or server reload.

## Problem and constraints

In `doctors.php`, users previously filtered doctors solely via a specialty dropdown or category links. Visitors wanting to find a doctor by name (e.g. "Smith"), spoken language (e.g. "Mandarin"), or qualification had to scan the whole table or reload per specialty. Under the clinic base-tier rules, AJAX, `fetch()`, `XMLHttpRequest`, and JSON transport are strictly banned, and external libraries or additional pages beyond the 11-page limit are not permitted.

## Main design choice

All doctor rows are already rendered server-side by PHP in the doctor directory table. Client-side DOM filtering was chosen over two alternatives: server-side query reloads (which disrupt typing and require round-trips) and AJAX/JSON endpoints (which violate the base rules). The search filter is implemented in pure vanilla JavaScript by listening to input events on the search bar, normalizing queries to lowercase, and checking whether each table row text includes the query string. Unmatched rows have their hidden property toggled, and an empty state notification appears when no doctors match. A CSS override ensures hidden rows remain hidden on mobile screens where grid layouts would otherwise override standard HTML hidden attributes.

## Traps

- On mobile viewports, `.doctor-directory tbody tr` uses `display: grid`. Without a `tr[hidden] { display: none !important; }` rule, setting `row.hidden = true` has no visual effect because user-agent `[hidden] { display: none; }` has specificity (0, 0, 1) and is overridden by class rules.
- Base rules forbid AJAX and asynchronous JSON fetch endpoints: the search must operate solely on DOM text content.
- Pressing Enter inside an `<input type="search">` within a `<form>` triggers GET form submission; a `keydown` handler on Enter prevents unwanted page refreshes while allowing search clearing.
- Progressive enhancement: when JavaScript is disabled, the `<form class="specialty-filter">` and `<noscript>` submit button continue to work seamlessly for server-side specialty filtering.

## Key parameters

- Search input: `#doctor-search`, `name="q"`, inside `.doctor-search-box`.
- Empty state: `#doctor-search-empty`, class `empty-state`, hidden by default.
- Target selector: `.doctor-directory tbody tr`.
- Focus token: `--c-action` mapped to `--c-primary` (`#0f766e`).

## Assumptions

- The doctor directory table renders complete doctor text (Name, Specialty, Qualifications, Languages) within each row's cells.
- Client-side DOM filtering on existing rows provides immediate sub-millisecond response without network overhead.

## Alternatives considered

- Server-side text search via `q` GET parameter: rejected because it requires full page round-trips on every search, losing responsiveness and typing continuity.
- AJAX / fetch endpoint with JSON payload: explicitly banned by AGENTS.md base constraints.

## How it works

When the document loads, the script binds an input event listener to the search box. When the user types or clears the field, the input query string is trimmed and converted to lowercase. The handler loops through the doctor directory rows, comparing normalized text content against the query. If the row contains the search term or if the search field is empty, the row remains visible; otherwise it is marked hidden. A match counter tracks visible results: if no doctors match, the empty state notification becomes visible, and as soon as matches are found or the search is cleared, the message is hidden again.

## Edge cases

- When JS is disabled (`<noscript>`), the search input remains a standard form control and the server-side specialty filter continues functioning without script dependencies.
- Clearing the search input immediately restores visibility to all rows matching the active server-side specialty.
- Typing search queries matching no doctors hides all rows and clearly shows `#doctor-search-empty`.

## Components

- `clinic-base/doctors.php`: `#doctor-search` input control and `#doctor-search-empty` container.
- `clinic-base/assets/app.js`: `initialiseDoctorDirectorySearch()` with `input`, `search`, and `keydown` event handlers.
- `clinic-base/assets/css/style.css`: `.doctor-search-box` layout, `:focus-visible` ring using `--c-action`, `#doctor-search-empty` styling, and `.doctor-directory tbody tr[hidden]` rule.
- `tests/test_doctors_page.php`: markup and asset assertions for search input, styles, and script logic.
- `tests/ui_issue209.mjs`: Playwright headless browser suite verifying live filtering, empty states, restore on clear, zero network requests, mobile viewport styling, and noscript fallback.

## Changes outside the scope

None. Companion test and verification files.

## How it was verified

- `php tests/run.php` passed with 115 passing tests.
- `php -l clinic-base/doctors.php` and `tests/test_doctors_page.php` passed syntax checks.
- `node -c clinic-base/assets/app.js` passed JavaScript syntax check.
- `node tests/ui_issue209.mjs` verified live search ("Smith", "Mandarin", "xyz", clear), confirmed zero fetch/XHR network requests during typing, tested focus visible styles, and validated noscript fallback at 1280px and 390px viewports.

## Trade-offs

The live search filters the doctors currently loaded in the DOM (subject to the selected specialty, if any). Since all doctors fit comfortably in the directory table, client-side filtering offers instantaneous response with zero network load.

## Breaks or needs follow-up

None.

## Not verified

Off-box mail delivery and external hosting environments; testing is strictly isolated to `ie4727db_test`.

## Rollback

Revert changes in `clinic-base/doctors.php`, `clinic-base/assets/app.js`, and `clinic-base/assets/css/style.css`.

## To change this

If search fields or table columns change, adjust DOM row text mapping or selector in `initialiseDoctorDirectorySearch()` in `clinic-base/assets/app.js`.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-27 14:27 UTC for issue #209 attempt 4; compared HEAD..working tree (base 97d83dd). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +3/-0 lines
- `clinic-base/assets/`: 2 file(s), 0 component(s), +80/-0 lines
- plus 2 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/doctors.php`, `clinic-base/assets/app.js`, `clinic-base/assets/css/style.css`, `tests/test_doctors_page.php`. Verdict: pass.

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, node syntax check changed files, scope check, dangling reference check

**Evidence**

3 checked by the tool, 1 tested in this run, 20 claimed only (of 24 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 3 checked, 0 tested, 2 claimed
- Key parameters: 0 checked, 0 tested, 4 claimed
- Edge cases: 0 checked, 0 tested, 3 claimed
- How it was verified: 0 checked, 1 tested, 3 claimed
- Traps: 0 checked, 0 tested, 4 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/doctors.php`: `#doctor-search` input control and `#doctor-search-empty` co... (named code exists and changed)
  - [tested] How it was verified: `php -l clinic-base/doctors.php` and `tests/test_doctors_page.php` passed syntax checks. (test gate passed: project test harness)
  - [checked] Components: `clinic-base/assets/app.js`: `initialiseDoctorDirectorySearch()` with `input`, `search`... (named code exists and changed)
  - [checked] Components: `clinic-base/assets/css/style.css`: `.doctor-search-box` layout, `:focus-visible` ring... (named code exists and changed)
  - [claimed] Components: `tests/test_doctors_page.php`: markup and asset assertions for search input, styles, an... (names no code)
  - [claimed] Key parameters: Search input: `#doctor-search`, `name="q"`, inside `.doctor-search-box`. (no cited constant with a matching value)
  - [claimed] Edge cases: When JS is disabled (`<noscript>`), the search input remains a standard form control an... (names no code)
  - [claimed] How it was verified: `php tests/run.php` passed with 115 passing tests. (no test named)
  - [claimed] Components: `tests/ui_issue209.mjs`: Playwright headless browser suite verifying live filtering, em... (names no code)
  - [claimed] Key parameters: Empty state: `#doctor-search-empty`, class `empty-state`, hidden by default. (no cited constant with a matching value)
  - full per-claim list (24) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0209-doctor-directory-search.md` gives the commit.
<!-- verified-facts:end -->
