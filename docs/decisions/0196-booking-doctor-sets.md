# 0196: Booking by doctor set: reschedule pinning, legacy specialties, one availability query, no GET token
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #196 · Date: 2026-10-16

## In short
Four review findings on the booking page are fixed together. Rescheduling now stays with the appointment's doctor and specialty, so changing the specialty can no longer break the page. Doctors stored under an old specialty name ("Dentistry") now show up when patients browse by specialty. The per-specialty slot queries that copied the per-doctor ones are gone: both now use one query over a list of doctors. The booking filter form no longer carries a disabled CSRF token, matching the other five filter forms.

## How it works
1. When the booking page opens, it works out which specialty to show. During a reschedule this is always the appointment doctor's specialty, and the specialty menu is shown greyed out with a short note: "A reschedule stays with the same doctor."
2. The page then builds the list of doctors to show. For a specialty it asks the doctor list, which already maps old names such as "Dentistry" to "Dental". The pinned or chosen doctor is always added to that list.
3. The same short list of doctor numbers is used both to count free times on each day (for the day tabs) and to fetch the slots for the chosen day.
4. Each slot on the grid is matched to a doctor from that list, so no slot can point at a missing doctor any more.
5. The specialty, date and time filters are a read-only search form. Like every other search form on the site it sends no security token; the forms that change data (booking, cancelling) still do.

## Main design choice
Resolve a specialty to doctor ids in PHP, then query slots by `DoctorID IN (...)`, rather than filtering slots by `d.Specialty` in SQL. The canonical-specialty mapping (`clinic-base/lib/specialties.php::canonical_specialty`) lives in PHP and is already applied by `all_doctors()`; doing the specialty match in SQL would need a second copy of the alias table (a `CASE` or `IN ('Dental','Dentistry')`) that drifts from the PHP one. Filtering in SQL would win only if specialties had thousands of doctors, where an id list becomes long; the clinic has a handful per specialty.

## Traps: why the obvious fix is wrong
- Do not reintroduce a `WHERE d.Specialty = :specialty` query for speed: stored values are not canonical (seed and legacy rows use "Dentistry"), so the alias must be applied before querying.
- `$doctorById` must include the pinned doctor even when the specialty filter would exclude it; that line is what keeps a reschedule from producing an empty `data-doctor-id` and a permanently disabled Confirm button.
- The disabled specialty `<select>` is not submitted. That is intended: the server ignores the specialty during a reschedule. Do not "fix" it by adding a hidden specialty field, which would let the URL override the pin again.
- `test_book_page` checks that `book.php` still contains the literal `slots_for_day($doctorId, $selectedDate, $fromTime, $toTime)`; keep that call form for the single-doctor branch.
- GET filter forms must stay token-free: `tests/test_issue117.php` now fails if a CSRF field reappears in one.

## Components
- `clinic-base/models/slots.php::doctor_id_filter` - `IN (...)` clause with named placeholders.
- `clinic-base/models/slots.php::slots_for_doctors_day` - slots for a doctor list; `slots_for_day` delegates to it.
- `clinic-base/models/slots.php::booking_doctors_day_availability` - day counts for a doctor list; `booking_day_availability` delegates to it.
- `clinic-base/book.php` - reschedule specialty pin, `$doctorById` built before the queries, locked specialty select, CSRF fieldset removed.
- `tests/test_issue196.php`, `tests/test_issue179.php`, `tests/fixtures/booking_179.php`, `tests/test_issue117.php` - regression coverage.
- `AGENTS.md` - rule 3 now names POST forms.

## Breaks or needs follow-up
- `specialty_slots_for_day` and `booking_specialty_day_availability` are removed; their only callers (`book.php`, `tests/test_issue179.php`) are updated. Nothing else referenced them.
- Within one start time, merged-grid alternatives are now ordered by doctor id instead of doctor name, so the doctor shown first in a grouped cell may change. The doctor panel still lists every covering doctor. None otherwise.

## Problem and constraints
Code review of #178/#179 found: (1) a null doctor lookup when the specialty changes during a reschedule; (2) specialty queries that ignore the canonical alias; (3) duplicated availability logic; (4) a disabled CSRF field that exists only to satisfy an audit. AGENTS.md requires `q()` for every query, exact schema names and no JS library.

## Alternatives considered
- Leave reschedule mode when the specialty changes - rejected: a patient would silently lose the "replace this appointment" context and could book a second appointment.
- Keep the specialty functions and fix only their WHERE clause - rejected: the duplication was itself a finding, and the copy had already drifted (no `slot_select()`, no ORDER BY in the availability copy).
- Keep the disabled token and document it - rejected: five other GET filter forms carry no token, so the rule was already applied only to POST forms in practice.

## Trade-offs
One extra PHP pass builds the doctor list before the slot queries; it reuses the `all_doctors()` result the page already loads, so no extra query is made for the list itself.

## Key parameters
- Booking window `BROWSE_DAYS` (`clinic-base/lib/slots.php`): unchanged.
- Placeholder names `doctor_id_N` (`clinic-base/models/slots.php::doctor_id_filter`).

## Assumptions
- `all_doctors()` returns every doctor, including ones with no future slots; if it ever filters by availability, the specialty grid will lose doctors whose only slots are booked.
- `find_doctor()` returns a canonical specialty (it applies `doctor_display_profile`), so the reschedule pin always matches an option in the menu.

## Edge cases
- Handled: reschedule with `?specialty=` for another specialty (pinned back, menu locked); a doctor with the legacy "Dentistry" value under Dental; an empty doctor list (empty grid, zeroed day counts).
- Not handled: a doctor whose stored specialty matches no canonical value is reachable only by doctor id, not by any specialty, as before.

## Not verified
- The Playwright scripts under `tools/ui/` (for example `tools/ui/booking-179.test.mjs`) were not run in this session.
- Query speed with the `IN (...)` filter was not measured; the doctor lists are a handful of ids.

## Changes outside the scope
- `AGENTS.md`: rule 3 reworded to name state-changing (POST) forms, because the issue's CSRF finding is a contract clarification, not only a code change.
- `clinic-base/lib/specialties.php` and `clinic-base/lib/slots.php` were in scope but needed no change.

## How it was verified
`tests/test_issue196.php` renders `book.php` as the patient in a child PHP process with `?specialty=Dental` on a General Practice reschedule and checks there are no PHP diagnostics, no empty `data-doctor-id`, the pinned doctor is offered and General Practice stays selected. `tests/test_issue179.php` now covers the legacy alias, the empty list and the single-doctor delegate. `tests/test_issue117.php` asserts GET filters carry no token. `tests/test_book_page.php`, `tests/test_reschedule.php`, `tests/test_reschedule_day.php`, `tests/test_slots.php`, `tests/test_slots_model.php`, `tests/test_booking.php`, `tests/test_audit_guards.php` and `tests/test_csrf.php` pass.

## To change this
To let a reschedule move to another doctor, drop the pin in `book.php`, keep the specialty menu enabled, and make `actions/book.php` accept a different `DoctorID` for the replacement; cost: the cancel-and-replace path must then notify two doctors. Bugs are likeliest where a new page queries slots by specialty directly instead of going through a doctor list.

## Rollback
Reverting restores the two specialty functions and the disabled token; `tests/test_issue117.php` and `AGENTS.md` revert with it, so the contract and the audit stay consistent.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 11:57 UTC for issue #196; compared HEAD..working tree (base c8fac8e). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +19/-10 lines
- `clinic-base/models/`: 1 file(s), 0 component(s), +48/-54 lines
- plus 4 test file(s), 1 doc file(s)

**Removed symbols**

- referenced in docs only: `clinic-base/models/slots.php::booking_specialty_day_availability`, `clinic-base/models/slots.php::specialty_slots_for_day`

**Scope**

Declared: `clinic-base/book.php`, `clinic-base/models/slots.php`, `clinic-base/lib/slots.php`, `clinic-base/lib/specialties.php`, `clinic-base/assets/booking-slots.js`, `tests/**`, `tools/**`. Verdict: violation.
- out of scope: `AGENTS.md` (modified); explained in the record: yes

**Supervisor checks passed**

project test harness

**Evidence**

4 checked by the tool, 1 tested in this run, 17 claimed only (of 22 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 4 checked, 0 tested, 2 claimed
- Key parameters: 0 checked, 0 tested, 2 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 1 tested, 0 claimed
- Traps: 0 checked, 0 tested, 5 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 2 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 2 claimed
  - [checked] Components: `clinic-base/models/slots.php::doctor_id_filter` - `IN (...)` clause with named placeho... (named code exists and changed)
  - [tested] How it was verified: `tests/test_issue196.php` renders `book.php` as the patient in a child PHP process with... (test gate passed: project test harness)
  - [checked] Components: `clinic-base/models/slots.php::slots_for_doctors_day` - slots for a doctor list; `slots... (named code exists and changed)
  - [checked] Components: `clinic-base/models/slots.php::booking_doctors_day_availability` - day counts for a doc... (named code exists and changed)
  - [checked] Components: `clinic-base/book.php` - reschedule specialty pin, `$doctorById` built before the queri... (named code exists and changed)
  - [claimed] Components: `tests/test_issue196.php`, `tests/test_issue179.php`, `tests/fixtures/booking_179.php`,... (names no code)
  - [claimed] Key parameters: Booking window `BROWSE_DAYS` (`clinic-base/lib/slots.php`): unchanged. (no cited constant with a matching value)
  - [claimed] Edge cases: Handled: reschedule with `?specialty=` for another specialty (pinned back, menu locked)... (names no code)
  - [claimed] Components: `AGENTS.md` - rule 3 now names POST forms. (names no code)
  - [claimed] Key parameters: Placeholder names `doctor_id_N` (`clinic-base/models/slots.php::doctor_id_filter`). (no cited constant with a matching value)
  - full per-claim list (22) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0196-booking-doctor-sets.md` gives the commit.
<!-- verified-facts:end -->
