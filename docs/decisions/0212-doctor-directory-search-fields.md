# 0212: Doctor directory search fields and empty state
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #212 · Date: 2026-10-28

## In short

Restrict doctor directory live search to each doctor's name, specialty, and languages, while keeping search progressively enhanced and announcing result counts.

## Problem and constraints

The existing search matched every cell in a row, so action labels and availability dates could produce false matches. A specialty filter with no results also exposed competing empty states, and the search control did not work without JavaScript because the server ignored its query parameter. The clinic base rules require server-rendered forms and vanilla JavaScript without AJAX, libraries, or external assets.

## Main design choice

Keep filtering local in the existing vanilla JavaScript, with the server explicitly marking the three permitted fields. A server-side `q` filter was also possible, but it would require a page round-trip on every search and would change the intended live-search interaction. Matching the whole row was the old behavior, but it includes dates and action labels, so it cannot enforce the promised search fields. The PHP template writes an escaped `data-search` attribute from only `FullName`, `Specialty`, and `Languages`. The client filter trims and lowercases both query and marked text. Search markup starts hidden and JavaScript reveals it only when doctor rows exist. The specialty GET form remains the no-JavaScript filter; the search field is not part of its query string. The polite live region reports the match count or the existing no-match copy. With zero doctor rows, initialization returns without showing the search or the client-side empty message, leaving the table's single server-rendered empty state.

## How it works

Each rendered doctor row receives an escaped `data-search` attribute composed from the doctor's name, specialty, and languages. On DOM ready, the script selects only rows carrying that attribute and exits before revealing the search when the set is empty. For populated results, it reveals the search input, filters rows on input using a trimmed, case-insensitive substring match, and updates the polite live region with either a count or the no-match message. The existing table empty state remains the sole message for a specialty that has no rows.

## Components

- `clinic-base/doctors.php`: searchable row fields, hidden search control, and polite result message.
- `clinic-base/assets/app.js`: data-search matching, zero-row early return, and count announcement.
- `clinic-base/assets/css/style.css`: hidden control handling and focus outline using `--c-primary`.
- `tests/test_doctors_page.php`, `tests/test_standout_features.php`, `tools/ui/standout-features.test.mjs`: markup, script, CSS, and browser regression assertions.

## Alternatives considered

- Server-side query filtering: rejected because this issue keeps immediate client-side search and the no-JavaScript specialty filter already provides a working fallback.
- Matching row text: rejected because it includes non-searchable action and availability content.
- Showing the live search empty message when the specialty already returns no rows: rejected because that duplicates the table's server-rendered empty state.

## Edge cases

- Query matching only a booking label such as “book” returns no doctors.
- A specialty with no doctor rows keeps the search unavailable and shows exactly the table empty state.
- JavaScript disabled leaves the search control hidden while the specialty GET filter remains available.
- On an ordinary populated directory, the live region reports the number of matching doctors and announces no matches when the count is zero.

## Traps

- Keep the search wrapper's initial `hidden` attribute and the explicit `[hidden]` CSS rule: the mobile table uses grid display rules that can otherwise override browser hiding behavior.
- Do not add a `name` to the search input; it must not be submitted as an ignored `q` parameter in the specialty GET form.
- Keep the no-row early return before revealing the search or changing the empty message, or the specialty filter can show two empty states.

## Breaks or needs follow-up

None known. If the directory's three promised searchable fields change, update the server attribute and browser assertions together.

## Trade-offs

The client receives a small search attribute on each doctor row and performs filtering without another request. Without JavaScript, the name/specialty/language search itself is unavailable, but the specialty GET filter remains functional and visible.

## Key parameters

- Search fields: `FullName`, `Specialty`, and `Languages` only.
- Matching: trimmed, case-insensitive substring matching.
- Empty state: the polite live region announces a doctor count or the no-match copy; a zero-row table keeps only its server-rendered empty row.
- Search visibility: hidden in source markup, revealed only when JavaScript finds doctor rows.

## Assumptions

- Doctor directory values are already available in the server-rendered `$doctors` list and need no additional query.
- The specialty filter is the intended no-JavaScript fallback for this client-side search enhancement.

## Not verified

None after the current issue validation; see the run results reported with the issue completion.

## Rollback

Revert this issue's changes to the listed page, script, stylesheet, tests, and this decision record together. Do not retain assertions for the previous whole-row search behavior.

## How it was verified

- `node tools/ui/standout-features.test.mjs` passed Chromium checks at 1280px and 390px, including `book`, Mandarin, specialty-with-no-results, no-JavaScript visibility, and the print slip.
- `php tests/run.php` passed all 116 tests.
- PHP lint passed for `clinic-base/doctors.php`, `tests/test_doctors_page.php`, and `tests/test_standout_features.php`; Node syntax checks passed for the changed JavaScript files.
- The stylesheet acceptance check confirmed `clinic-base/assets/css/style.css` contains no `c-action`.
- `node tools/ui/shoot.mjs --serve --label issue212/doctors doctors.php` captured 1280px and 390px screenshots, which were opened and reviewed.

## Changes outside the scope

None.

## To change this

If searchable doctor fields change, update the escaped `data-search` construction in `clinic-base/doctors.php` and its PHP and browser assertions.


<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-28 05:49 UTC for issue #212 attempt 3; compared HEAD..working tree (base 15a7d0d). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +3/-3 lines
- `clinic-base/assets/`: 2 file(s), 0 component(s), +11/-8 lines
- plus 3 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/doctors.php`, `clinic-base/assets/app.js`, `clinic-base/assets/css/style.css`, `clinic-base/assets/tokens.css`, `tests/test_doctors_page.php`, `tests/test_standout_features.php`, `tools/ui/standout-features.test.mjs`. Verdict: pass.

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, node syntax check changed files, scope check, dangling reference check

**Evidence**

3 checked by the tool, 1 tested in this run, 21 claimed only (of 25 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 3 checked, 0 tested, 1 claimed
- Key parameters: 0 checked, 0 tested, 4 claimed
- Edge cases: 0 checked, 0 tested, 4 claimed
- How it was verified: 0 checked, 1 tested, 4 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/doctors.php`: searchable row fields, hidden search control, and polite res... (named code exists and changed)
  - [tested] How it was verified: PHP lint passed for `clinic-base/doctors.php`, `tests/test_doctors_page.php`, and `test... (test gate passed: project test harness)
  - [checked] Components: `clinic-base/assets/app.js`: data-search matching, zero-row early return, and count ann... (named code exists and changed)
  - [checked] Components: `clinic-base/assets/css/style.css`: hidden control handling and focus outline using `--... (named code exists and changed)
  - [claimed] Components: `tests/test_doctors_page.php`, `tests/test_standout_features.php`, `tools/ui/standout-f... (names no code)
  - [claimed] Key parameters: Search fields: `FullName`, `Specialty`, and `Languages` only. (no cited constant with a matching value)
  - [claimed] Edge cases: Query matching only a booking label such as “book” returns no doctors. (names no code)
  - [claimed] How it was verified: `node tools/ui/standout-features.test.mjs` passed Chromium checks at 1280px and 390px,... (no test named)
  - [claimed] Key parameters: Matching: trimmed, case-insensitive substring matching. (no cited constant with a matching value)
  - [claimed] Edge cases: A specialty with no doctor rows keeps the search unavailable and shows exactly the tabl... (names no code)
  - full per-claim list (25) in the JSON record

**Consistency**

0 error(s), 1 warning(s) between the rationale above and the change.
- WARN: 'Main design choice' names no alternative; say which 1-2 real options were possible and why this one won

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0212-doctor-directory-search-fields.md` gives the commit.
<!-- verified-facts:end -->
