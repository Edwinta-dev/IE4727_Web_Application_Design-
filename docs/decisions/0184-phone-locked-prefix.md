# 0184: Singapore +65 as a locked phone prefix
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #184 · Date: 2026-10-16

## In short
The patient registration form now shows "+65" as a fixed label beside the phone box. Patients type only their 8-digit local number, and the server accepts only 8 digits starting with 6, 8 or 9. Numbers are stored exactly as the existing seed data stores them: 8 digits, no prefix.

## How it works
1. The registration page draws the phone field as a small group: a grey "+65" label that cannot be edited, joined to a text box.
2. The box accepts at most 8 characters and tells the browser that only a local Singapore number (first digit 6, 8 or 9) is valid, so most mistakes are caught before the form is sent.
3. When the form is sent, the server checks the same rule again, because a browser check can be skipped.
4. If the number is wrong, the form comes back with the message "Enter 8 digits after +65, starting with 6, 8 or 9." and the other answers kept.
5. A valid number is saved as the 8 digits only, the same shape as every seeded patient, so every page that already shows phone numbers keeps working unchanged.

## Main design choice
Store the 8 local digits rather than a normalised "+65 XXXX XXXX" string. The seed data (`schema/003_seed.sql`, e.g. `'91112223'`) and the admin console and visit pages already show the stored value as-is, so keeping the same shape needs no migration and no display change. Storing the full international form would win if the clinic ever accepted non-Singapore numbers; that would need a migration and a display formatter.

## Traps: why the obvious fix is wrong
- Do not loosen the server rule back to the old free-form pattern to "be forgiving": the "+65" label is outside the input, so a user typing "+65 8123 4567" would now store a doubled prefix. The test rejects `+65 81234567` on purpose.
- The `/D` modifier on the server regex stops a trailing newline from passing `$`; removing it lets "81234567\n" through.
- The prefix label is `aria-hidden`; the input carries `aria-label="Phone number after +65"` so screen readers still hear the prefix. Removing either breaks that.

## Components
- `clinic-base/register.php` - phone field markup (prefix label, pattern, maxlength).
- `clinic-base/actions/register.php` - server rule and error message for `Phone`.
- `clinic-base/assets/style.css` - `.phone-input` / `.phone-prefix` joined-control styling.
- `tests/test_register.php`, `tests/test_accounts.php`, `tests/ui_register.mjs` - updated contract.

## Breaks or needs follow-up
- Patients who registered earlier with a free-form number (spaces, "+65") keep it; it still displays, but they cannot re-save that exact value through a form that applies the new rule. No such edit form exists for patients today. None otherwise.

## Problem and constraints
Issue #184: show "+65" as a non-editable prefix, accept 8 local digits (`[689]\d{7}`), validate on the server, keep the existing column and format, and keep existing records displaying correctly. AGENTS.md forbids JS libraries and schema renames.

## Alternatives considered
- A `<select>` of country codes - rejected: the clinic is Singapore-only and the issue asks for a fixed prefix.
- Stripping spaces and "+65" on the server before validating - rejected for now: it hides input mistakes and the field is short enough to type cleanly.

## Trade-offs
Overseas numbers can no longer be registered. That matters only if the clinic accepts foreign patients without a local number.

## Key parameters
- Pattern `[689][0-9]{7}` (`clinic-base/register.php` input `pattern`, server rule in `clinic-base/actions/register.php`): from the issue text.
- Max length 8 (`maxlength` on the input).

## Assumptions
- Singapore mobile and landline numbers all start with 6, 8 or 9 and have 8 digits; 3-prefix (VoIP) numbers are not accepted.

## Edge cases
- Handled: letters, slashes, 7- and 9-digit numbers, a typed "+65" prefix and a leading 7 are rejected on both client and server.
- Not handled: pasting "8123 4567" with a space is rejected rather than cleaned; the user sees the error and retypes.

## Not verified
- Screen-reader announcement of the prefix was not tested with a real reader.
- The Playwright script `tests/ui_register.mjs` was updated but not run in this session.

## Changes outside the scope
- `clinic-base/assets/style.css`: the issue's Scope line names only PHP files, but the joined prefix-plus-input control needs its own styling; no other rule was touched.

## How it was verified
`tests/test_register.php` (prefix markup, pattern, accepted and rejected numbers) and `tests/test_accounts.php` pass via `php tests/run.php`; the full suite was run before commit (see the commit's facts block).

## To change this
To accept overseas numbers, replace the fixed label with a country select, store E.164 strings, and add a display formatter. Bugs are most likely where another form (a future profile edit) applies a different phone rule.

## Rollback
Reverting restores the free-form field; stored 8-digit numbers stay valid under the old rule, so nothing else needs to change.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 11:52 UTC for issue #184; compared HEAD..working tree (base a26e889). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +1/-1 lines
- `clinic-base/actions/`: 1 file(s), 0 component(s), +2/-2 lines
- `clinic-base/assets/`: 1 file(s), 0 component(s), +34/-0 lines
- plus 3 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/register.php`, `clinic-base/actions/register.php`, `clinic-base/lib/validate.php`, `clinic-base/models/accounts.php`. Verdict: violation.
- out of scope: `clinic-base/assets/style.css` (modified); explained in the record: yes

**Supervisor checks passed**

project test harness

**Evidence**

3 checked by the tool, 1 tested in this run, 12 claimed only (of 16 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 3 checked, 0 tested, 1 claimed
- Key parameters: 0 checked, 0 tested, 2 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 1 tested, 0 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 2 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/register.php` - phone field markup (prefix label, pattern, maxlength). (named code exists and changed)
  - [tested] How it was verified: `tests/test_register.php` (prefix markup, pattern, accepted and rejected numbers) and `... (test gate passed: project test harness)
  - [checked] Components: `clinic-base/actions/register.php` - server rule and error message for `Phone`. (named code exists and changed)
  - [checked] Components: `clinic-base/assets/style.css` - `.phone-input` / `.phone-prefix` joined-control styling. (named code exists and changed)
  - [claimed] Components: `tests/test_register.php`, `tests/test_accounts.php`, `tests/ui_register.mjs` - updated... (names no code)
  - [claimed] Key parameters: Pattern `[689][0-9]{7}` (`clinic-base/register.php` input `pattern`, server rule in `cl... (no cited constant with a matching value)
  - [claimed] Edge cases: Handled: letters, slashes, 7- and 9-digit numbers, a typed "+65" prefix and a leading 7... (names no code)
  - [claimed] Key parameters: Max length 8 (`maxlength` on the input). (no cited constant with a matching value)
  - full per-claim list (16) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0184-phone-locked-prefix.md` gives the commit.
<!-- verified-facts:end -->
