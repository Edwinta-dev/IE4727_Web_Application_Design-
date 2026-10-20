# 0191: Notification sign-off shown as "Clinic Appointment Service"
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #191 · Date: 2026-10-16

## In short
Every clinic notification ends with a sign-off line, "Clinic: Clinic Appointment Portal", which patients read as "Clinic: Clinic". The patient's notification list now shows that line as "Clinic Appointment Service". The stored messages are not changed.

## How it works
1. The patient opens a notification under "Latest notifications" on My appointments.
2. Before the message is shown, any line that starts with "Clinic:" is replaced with "Clinic Appointment Service".
3. The message is then escaped and its line breaks are kept, as before.
4. The database row is only read, never updated, so the notification history stays exactly as it was sent.

## Main design choice
Rewrite the sign-off line when the page is drawn, rather than changing the text the mail builders store. Changing `clinic-base/lib/mail.php` would fix new messages only, leave the 12 existing live messages showing "Clinic: Clinic", and go outside this issue's scope; the issue also says to fix it at render time. An earlier attempt in this run added a separate "From Clinic Appointment Service" line from the `sender` column instead; that left the "Clinic: Clinic" sign-off on screen, which was the actual complaint, so it was replaced.

## Traps: why the obvious fix is wrong
- Do not `UPDATE notifications SET Body = ...`: the issue requires historical rows to stay as sent.
- The match is anchored to a whole line starting with "Clinic:", so a sentence that merely contains "Clinic:" mid-line is untouched.
- Map first, escape second: `e()` must wrap the mapped text, never the reverse, or the mapping could be applied to escaped HTML.

## Components
- `clinic-base/models/notifications.php::patient_notification_body` - replaces the "Clinic: ..." sign-off line with the service name.
- `clinic-base/models/notifications.php::notifications_for_patient` - unchanged query (the earlier attempt's `sender` column was removed again).
- `clinic-base/patient/home.php` - passes each body through `patient_notification_body()` before `e()` and `nl2br()`.
- `tests/test_issue191.php` - sign-off mapping on a real `mail_cancelled()` body, CRLF history, mid-line text, and the stored row left unchanged.
- `tests/ui_issue191.mjs` - Chromium check at 1280 and 390 pixels against `ie4727db_test`.

## Breaks or needs follow-up
Emails that were actually sent, and staff views of the notification log, still show "Clinic: Clinic Appointment Portal". Changing the mail text itself would be a separate issue touching `clinic-base/lib/mail.php`.

## Problem and constraints
Iteration 7 walkthrough: patient notifications showed "Clinic: Clinic". The text comes from the sign-off line `"Clinic: " . APP_NAME` that the mail builders append, with `APP_NAME` = "Clinic Appointment Portal". Scope is limited to the patient page and the notifications model, and stored rows must not be rewritten.

## Alternatives considered
- Change the sign-off in `clinic-base/lib/mail.php` - rejected: out of scope and leaves existing messages unchanged.
- Show a "From" line built from the `sender` column - rejected: the "Clinic: Clinic" line stays visible.

## Trade-offs
The sign-off is rewritten on every render, a single regular expression per message for at most 20 messages; negligible.

## Key parameters
- Display name "Clinic Appointment Service" (`clinic-base/models/notifications.php::patient_notification_body`): wording requested by the issue.
- Pattern `^Clinic:[ \t]*\S[^\r\n]*$` with the multiline flag: a whole line that starts with "Clinic:" and has text after it.

## Assumptions
- Only the mail builders' sign-off line starts a line with "Clinic:".

## Edge cases
- Handled: historical bodies with CRLF line endings; a "Clinic:" in the middle of a sentence (left alone).
- Not handled: a body whose sign-off was written another way would show as stored.

## Not verified
- The original 16OctRun screenshot was not available to compare against; the reproduction used a message with the real sign-off line.

## Changes outside the scope
None. The decision record and the two test files are allowed by the scope rules.

## How it was verified
`tests/test_issue191.php` passes, and the full suite (`php tests/run.php`, `CLINIC_DB_NAME=ie4727db_test`) passes, 114 tests with 0 failures. `node tests/ui_issue191.mjs before` on the original code showed "Clinic: Clinic Appointment Portal"; `node tests/ui_issue191.mjs after` shows "Clinic Appointment Service" and no "Clinic:" at 1280 and 390 pixels. Screenshots are under `UIPROBLEMS/after/iter7/issue191-notification-sender/`.

## To change this
To fix the source text as well, change the sign-off in the four builders in `clinic-base/lib/mail.php`; keep `patient_notification_body()` for messages already stored.

## Rollback
Revert the commit; the stored rows were never touched, so nothing else needs undoing.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 13:57 UTC for issue #191; compared HEAD..working tree (base 89180ed). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/models/`: 1 file(s), 0 component(s), +8/-0 lines
- `clinic-base/patient/`: 1 file(s), 0 component(s), +1/-1 lines
- plus 2 test file(s)

**Removed symbols**

None.

**Scope**

No Scope line in the issue.

**Supervisor checks passed**

not available

**Evidence**

3 checked by the tool, 0 tested in this run, 13 claimed only (of 16 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 3 checked, 0 tested, 2 claimed
- Key parameters: 0 checked, 0 tested, 2 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 0 tested, 1 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/models/notifications.php::patient_notification_body` - replaces the "Clini... (named code exists and changed)
  - [checked] Components: `clinic-base/models/notifications.php::notifications_for_patient` - unchanged query (th... (named code exists and changed)
  - [checked] Components: `clinic-base/patient/home.php` - passes each body through `patient_notification_body()`... (named code exists and changed)
  - [claimed] Components: `tests/test_issue191.php` - sign-off mapping on a real `mail_cancelled()` body, CRLF hi... (names no code)
  - [claimed] Key parameters: Display name "Clinic Appointment Service" (`clinic-base/models/notifications.php::patie... (no cited constant with a matching value)
  - [claimed] Edge cases: Handled: historical bodies with CRLF line endings; a "Clinic:" in the middle of a sente... (names no code)
  - [claimed] How it was verified: `tests/test_issue191.php` passes, and the full suite (`php tests/run.php`, `CLINIC_DB_N... (cited test exists; gate results not available)
  - [claimed] Components: `tests/ui_issue191.mjs` - Chromium check at 1280 and 390 pixels against `ie4727db_test`. (names no code)
  - [claimed] Key parameters: Pattern `^Clinic:[ \t]*\S[^\r\n]*$` with the multiline flag: a whole line that starts w... (no cited constant with a matching value)
  - full per-claim list (16) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0191-notification-sender.md` gives the commit.
<!-- verified-facts:end -->
