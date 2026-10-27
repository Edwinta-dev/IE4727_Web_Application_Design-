# 0206: Registration account type dropdown
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #206 · Date: 2026-10-27

## In short

The registration form opens with Patient selected in a labelled account type dropdown. Choosing Doctor shows doctor details; choosing Patient shows patient details.

## Main design choice

Use one native `select[name="role"]` as the submitted role instead of keeping the radio tabs or adding a server round trip on each change. The existing vanilla JavaScript role function can hide and disable the inactive fieldset without losing unsent values. The server's saved `old('role', 'patient')` value selects the role again after validation redirects back to the form.

## How it works

`register.php` renders Patient as the default option and selects Doctor when the saved role is Doctor. On dropdown changes, `setRegistrationRole()` hides and disables the other role's fields. Registration still posts to the existing action, which validates the role and redirects back with saved input on errors.

## Breaks or needs follow-up

Historical arrow-specific browser scripts (`tools/ui/registration-arrows.test.mjs` and `tools/ui/iter5-gate.test.mjs`) still describe the retired control and are superseded for registration by `tests/ui_issue206.mjs`.

## Assumptions

The two supported roles remain `patient` and `doctor`; the server action continues to reject other role values. JavaScript handles switching the visible fieldset after the page loads.

## To change this

Update the select options, `setRegistrationRole()` and the server's role validation together. Keep the PHP rendered selection aligned with the saved role so validation errors reopen the correct fields.

## Problem and constraints

The existing page exposed both radio choices and a second arrow control for the same role switch. The extra control confused visitors. Registration must retain the existing form, server validation, CSRF field, and clinic design styles.

## Alternatives considered

- Keep the radio choices and remove only the arrows: this still leaves a custom two-choice toggle instead of the requested dropdown.
- Add a server round trip when the role changes: this would discard unsent form values and add an unnecessary request.

## Trade-offs

Role-specific field visibility updates when JavaScript runs. Server-side validation still checks the submitted role independently.

## Key parameters

- `patient` is the default and `doctor` is the other allowed value.
- The selected option is rendered from the server's validated saved role.
- Hidden role fields are disabled so they do not affect client-side validation or form submission.

## Edge cases

- A rejected Doctor registration returns with Doctor selected and its fields visible.
- Switching Doctor to Patient re-enables patient fields and disables doctor fields.
- The dropdown keeps the shared rounded keyboard focus ring.

## Traps

- Keep `actions/register.php` as the authority for role validation and POST-Redirect-GET.
- Do not reintroduce radio or arrow controls in the registration form.

## Components

- `clinic-base/register.php`: labelled dropdown, selected role, and role-specific fieldsets.
- `clinic-base/assets/app.js`: switches visible and enabled role-specific fields when the dropdown changes.
- `clinic-base/assets/css/style.css`: sets the dropdown width and removes overrides for the old control.
- `clinic-base/assets/style.css`: removes unused radio, arrow, and sliding track rules imported by the scoped stylesheet.
- `tests/test_register.php`: checks dropdown markup and absence of the old controls.
- `tests/ui_issue206.mjs`: verifies both roles and validation re-render in Chromium at 1280 and 390 pixels.
- `tests/ui_issue185.mjs`, `tests/ui_issue189.mjs`, `tests/ui_issue190.mjs`, `tests/ui_register.mjs`, and `tests/ui_registration_sticky.mjs`: use the new role control in existing browser regressions.

## Changes outside the scope

`clinic-base/assets/style.css` is imported by `clinic-base/assets/css/style.css` and contains the old registration arrow and track rules. Removing them there is necessary to satisfy the issue's explicit dead CSS removal. The tests and this record are verification artifacts.

## How it was verified

The saved before and after Chromium captures at 1280 and 390 pixels are under `UIPROBLEMS/after/iter8/create-account-role-dropdown/`. Run `node tests/ui_issue206.mjs` for the issue acceptance check and `php tests/run.php` for the full suite.

## Not verified

The browser acceptance test posts invalid registration data to verify the redirect and saved role; it does not create an account.

## Rollback

Restore the prior role controls and their JavaScript and styles together, then update the registration browser tests.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-27 03:27 UTC for issue #206 attempt 14; compared HEAD..working tree (base 05f784a). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +7/-16 lines
- `clinic-base/assets/`: 3 file(s), 0 component(s), +6/-195 lines
- plus 7 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/register.php`, `clinic-base/assets/app.js`, `clinic-base/assets/css/style.css`. Verdict: violation.
- out of scope: `clinic-base/assets/style.css` (modified); explained in the record: yes

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, node syntax check changed files, scope check, dangling reference check

**Evidence**

4 checked by the tool, 0 tested in this run, 16 claimed only (of 20 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 4 checked, 0 tested, 3 claimed
- Key parameters: 0 checked, 0 tested, 3 claimed
- Edge cases: 0 checked, 0 tested, 3 claimed
- How it was verified: 0 checked, 0 tested, 1 claimed
- Traps: 0 checked, 0 tested, 2 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/register.php`: labelled dropdown, selected role, and role-specific fieldsets. (named code exists and changed)
  - [checked] Components: `clinic-base/assets/app.js`: switches visible and enabled role-specific fields when the... (named code exists and changed)
  - [checked] Components: `clinic-base/assets/css/style.css`: sets the dropdown width and removes overrides for t... (named code exists and changed)
  - [checked] Components: `clinic-base/assets/style.css`: removes unused radio, arrow, and sliding track rules im... (named code exists and changed)
  - [claimed] Components: `tests/test_register.php`: checks dropdown markup and absence of the old controls. (names no code)
  - [claimed] Key parameters: `patient` is the default and `doctor` is the other allowed value. (no cited constant with a matching value)
  - [claimed] Edge cases: A rejected Doctor registration returns with Doctor selected and its fields visible. (names no code)
  - [claimed] How it was verified: The saved before and after Chromium captures at 1280 and 390 pixels are under `UIPROBLE... (no test named)
  - [claimed] Components: `tests/ui_issue206.mjs`: verifies both roles and validation re-render in Chromium at 12... (names no code)
  - [claimed] Key parameters: The selected option is rendered from the server's validated saved role. (no cited constant with a matching value)
  - full per-claim list (20) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0206-registration-role-dropdown.md` gives the commit.
<!-- verified-facts:end -->
