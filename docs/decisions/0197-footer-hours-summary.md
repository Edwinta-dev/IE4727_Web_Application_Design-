# 0197: Footer opening hours from a day summary, with a database fallback
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #197 · Date: 2026-10-16

## In short
The footer still shows opening hours worked out from the doctors' real schedules, but it now asks the database for one short summary per doctor per day instead of every single slot. If the database cannot be reached, the footer shows "See Find a Doctor for available times" and the rest of the page still loads, including error pages.

## How it works
1. Every page includes the footer, and the footer asks for the clinic's opening hours.
2. The database groups the next 30 days of appointment slots by doctor and day, and returns only the first start, the last start and the shortest gap between two slots for each.
3. The shortest gap (between 1 and 60 minutes) is taken as the length of an appointment, so the closing time is the last start plus one appointment. A day with a single slot assumes 30 minutes.
4. Those daily summaries are combined by weekday: the earliest opening and the latest closing across all doctors, with days that share the same hours joined ("Mon-Fri 08:00-18:00").
5. The answer is remembered for the rest of that page view, so a page that shows the footer once makes one query.
6. If anything in steps 2-4 fails, the error goes to the server log and the footer shows the fixed fallback line instead of breaking the page.

## Main design choice
Aggregate in SQL with `LAG()` rather than cache the computed text across requests in a file. A cross-request cache would cut the query entirely, but issue #173's acceptance ("changing a doctor's schedule changes the footer hours") would then lag by the cache lifetime, and the cache file would need its own invalidation on every schedule edit. The summary query is cheap (130 rows instead of 1,820 in `ie4727db_test` today) and always current. A file cache would win if the footer query ever showed up in page timings.

## Traps: why the obvious fix is wrong
- Do not hardcode the hours to avoid the query: #173 requires them to follow the schedule data.
- The step is the smallest gap **between 1 and 60 minutes**; a lunch break (a 60+ minute gap) must not count as a slot length. The `CASE WHEN Gap BETWEEN 1 AND 60` keeps that rule from the old PHP loop.
- The fallback must catch `Throwable`, not only `PDOException`: a malformed row or a date parse error should degrade the same way.
- `LAG()` needs MariaDB 10.2+ / MySQL 8; the XAMPP stack here is MariaDB 10.4.32. On an older server the query fails and the footer shows the fallback line (it does not break pages).

## Components
- `clinic-base/models/doctors.php::clinic_schedule_days` - per doctor-day summary query, cached per request (replaces `clinic_schedule_slots`).
- `clinic-base/models/doctors.php::clinic_opening_hours_text` - footer entry point with the fallback.
- `clinic-base/lib/helpers.php::clinic_opening_hours` - now formats day summaries instead of raw slots.
- `clinic-base/partials/footer.php` - calls `clinic_opening_hours_text()`.
- `tests/test_issue173.php` - summary formatting, live query, schedule change and database-down cases.

## Breaks or needs follow-up
- `clinic_schedule_slots()` is removed and `clinic_opening_hours()` now takes day-summary rows; the footer and `tests/test_issue173.php` were the only callers. None otherwise.

## Problem and constraints
Code review of #173: the footer loaded every slot for every doctor over 30 days on every page view, and any database error broke every page, including the 404 page. AGENTS.md requires every query to go through `q()` and every echoed value through `e()`.

## Alternatives considered
- Per-weekday aggregation entirely in SQL - rejected: grouping by weekday first loses the per-doctor-day step that decides the closing time.
- A static footer line - rejected by #173's acceptance criteria.

## Trade-offs
The query still runs once per page view. That would start to matter only with far more doctors or a slow database link; then add the file cache described under To change this.

## Key parameters
- 30-day window (`clinic-base/models/doctors.php::clinic_schedule_days`, `INTERVAL 30 DAY`): unchanged from #173.
- Step bounds 1-60 minutes and the 30-minute default (`clinic-base/lib/helpers.php::clinic_opening_hours`): unchanged rule.
- Fallback text "See Find a Doctor for available times" (`clinic-base/models/doctors.php::clinic_opening_hours_text`).

## Assumptions
- Slots within one doctor's day share a single length; mixed lengths in one day would make the closing time use the shortest.

## Edge cases
- Handled: database missing or unreachable (fallback line, exit status 0); a day with one slot (30-minute default); a lunch gap over 60 minutes (ignored as a step).
- Not handled: a slot crossing midnight would give a closing time earlier than the opening time, as before.

## Not verified
- Page timing before and after was not measured; the row counts (1,820 to 130) were measured with `COUNT(*)` on `ie4727db_test`.
- Behaviour on MySQL 5.7 (no window functions) was reasoned, not run.

## Changes outside the scope
None.

## How it was verified
`tests/test_issue173.php` passes all four cases: weekday formatting from summaries, a live schedule change in a fresh request, one summary row per doctor-day with the 20-minute step detected, and a footer rendered with `CLINIC_DB_NAME` pointing at a missing database (exit 0, fallback line, address still shown). `tests/test_index.php`, `tests/test_routing.php` and `tests/test_errors.php` pass.

## To change this
To drop the per-request query, cache `clinic_opening_hours_text()` in a temp file for N minutes and delete it from the schedule editor's save action; cost: one more write path to keep in step. Bugs are likeliest if slot lengths start varying within a day.

## Rollback
Reverting restores the raw-slot query and the unguarded footer call; nothing else depends on the new functions.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 12:00 UTC for issue #197; compared HEAD..working tree (base 79d473c). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/lib/`: 1 file(s), 0 component(s), +9/-21 lines
- `clinic-base/models/`: 1 file(s), 0 component(s), +27/-8 lines
- `clinic-base/partials/`: 1 file(s), 0 component(s), +1/-1 lines
- plus 1 test file(s)

**Removed symbols**

- referenced in docs only: `clinic-base/models/doctors.php::clinic_schedule_slots`

**Scope**

Declared: `clinic-base/partials/footer.php`, `clinic-base/lib/helpers.php`, `clinic-base/models/doctors.php`, `clinic-base/models/slots.php`. Verdict: pass.

**Supervisor checks passed**

project test harness

**Evidence**

4 checked by the tool, 1 tested in this run, 14 claimed only (of 19 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 4 checked, 0 tested, 1 claimed
- Key parameters: 0 checked, 0 tested, 3 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 1 tested, 0 claimed
- Traps: 0 checked, 0 tested, 4 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 2 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/models/doctors.php::clinic_schedule_days` - per doctor-day summary query,... (named code exists and changed)
  - [tested] How it was verified: `tests/test_issue173.php` passes all four cases: weekday formatting from summaries, a l... (test gate passed: project test harness)
  - [checked] Components: `clinic-base/models/doctors.php::clinic_opening_hours_text` - footer entry point with t... (named code exists and changed)
  - [checked] Components: `clinic-base/lib/helpers.php::clinic_opening_hours` - now formats day summaries instead... (named code exists and changed)
  - [checked] Components: `clinic-base/partials/footer.php` - calls `clinic_opening_hours_text()`. (named code exists and changed)
  - [claimed] Components: `tests/test_issue173.php` - summary formatting, live query, schedule change and databas... (names no code)
  - [claimed] Key parameters: 30-day window (`clinic-base/models/doctors.php::clinic_schedule_days`, `INTERVAL 30 DAY... (no cited constant with a matching value)
  - [claimed] Edge cases: Handled: database missing or unreachable (fallback line, exit status 0); a day with one... (names no code)
  - [claimed] Key parameters: Step bounds 1-60 minutes and the 30-minute default (`clinic-base/lib/helpers.php::clini... (no cited constant with a matching value)
  - [claimed] Key parameters: Fallback text "See Find a Doctor for available times" (`clinic-base/models/doctors.php:... (no cited constant with a matching value)
  - full per-claim list (19) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0197-footer-hours-summary.md` gives the commit.
<!-- verified-facts:end -->
