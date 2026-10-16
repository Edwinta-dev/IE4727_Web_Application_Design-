# 0195: Mail transport as a parameter, not a global
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #195 · Date: 2026-10-16

## In short
Mail delivery no longer looks for a test replacement in a global variable. Code that wants a different sender passes it in directly; everything else uses PHP's normal mail function, as before.

## How it works
1. When a booking, reschedule or cancellation commits, the site hands the new notification ids to the delivery step.
2. The delivery step loads each notification and sends it with PHP's mail function, unless the caller supplied its own sending function for that call.
3. Each notification is then marked sent or failed. Sending errors are logged and never undo the booking.
4. Test scripts that need to watch delivery switch off PHP's built-in mail function when they start and define their own in its place, so production code carries no test switch.

## Main design choice
An optional `$transport` parameter on `deliver_notifications`, rather than a global variable any code could set. A global let anything in the process silently replace real delivery and tied the library to test code; a parameter is visible at the call site. A config constant naming a transport was considered and rejected: constants are fixed per process and are still a hidden switch. The existing tests already replace mail() in child processes, which covers the need the global served.

## Traps: why the obvious fix is wrong
- Do not reintroduce a global or static "set transport" function so the audit probe can reach `deliver_notifications` through `book_appointment`. The probe and fixture replace mail() itself under `-d disable_functions=mail`; that keeps production code free of test switches.
- The audit probe declares mail() inside `if (!function_exists('mail'))`; an unconditional declaration fails to compile (and fails `php -l`) whenever mail() exists.

## Components
- `clinic-base/lib/mail.php::deliver_notifications` - optional `$transport` callable.
- `tools/audit_mail_transaction.php` - defines its own mail() instead of setting the global.
- `tests/fixtures/mail_delivery.php` - checks an explicit transport bypasses mail().
- `tests/test_mail_delivery.php` - fails if lib/mail.php reads `$GLOBALS`.

## Breaks or needs follow-up
None. `tools/audit_mail_transaction.php` must be run with `php -d disable_functions=mail` (it now refuses to run otherwise); `docs/UI_ITERATION6_GATE.md` already runs it that way.

## Problem and constraints
The review of #160 flagged `$GLOBALS['clinic_mail_transport']` in production delivery code. Constraints: no frameworks or dependency containers (AGENTS.md), and the four delivery callers in `clinic-base/models/booking.php` stay unchanged.

## Alternatives considered
- Thread a transport through `book_appointment` and the other booking functions - rejected: widens four model signatures for test-only use.

## Trade-offs
Tests that observe delivery through the booking functions must run in a child process with mail() disabled. That is slower than an in-process swap, which would start to matter only if many more delivery tests were added.

## Key parameters
None.

## Assumptions
- No production code set `$GLOBALS['clinic_mail_transport']`; a repository grep found only the audit probe.

## Edge cases
- Handled: an explicit transport receives both notifications and mail() is not called (fixture, mode `on`).
- Not handled: a transport that returns a non-boolean is cast with `(bool)`, as before.

## Not verified
- Real SMTP delivery through mail() was not exercised; only the disabled-mail doubles ran.

## Changes outside the scope
- `tools/audit_mail_transaction.php`, `tests/fixtures/mail_delivery.php`, `tests/test_mail_delivery.php`: the only users of the removed global, and its tests.

## How it was verified
`php tests/run.php` on `tests/test_mail_delivery.php` (five child runs: off, on/failed, on/sent, on/throw, default, plus the explicit-transport check) and `php -d disable_functions=mail tools/audit_mail_transaction.php`, which printed `inTransaction=false` twice and OK. Full suite with #194 and #195 applied: 107 passed, 1 failed; the failure is `tests/test_issue161.php` checking `clinic-base/book.php` source, a file this branch does not touch, so it predates these changes.

## To change this
To let production pick a transport (for example SMTP), pass a callable at each `deliver_notifications` call in `clinic-base/models/booking.php`; cost: four call sites. Likeliest bug source: a transport whose argument order differs from mail().

## Rollback
Reverting restores the global hook; the audit probe and fixture must be reverted with it or the probe will define mail() and never set the global.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 11:57 UTC for issue #195; compared HEAD..working tree (base 87cfdd3). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/lib/`: 1 file(s), 0 component(s), +6/-4 lines
- `tools/`: 1 file(s), 0 component(s), +15/-9 lines
- plus 2 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/lib/mail.php`. Verdict: violation.
- out of scope: `tools/audit_mail_transaction.php` (modified); explained in the record: yes

**Supervisor checks passed**

project test harness

**Evidence**

2 checked by the tool, 1 tested in this run, 9 claimed only (of 12 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 2 checked, 0 tested, 2 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 1 tested, 0 claimed
- Traps: 0 checked, 0 tested, 2 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/lib/mail.php::deliver_notifications` - optional `$transport` callable. (named code exists and changed)
  - [tested] How it was verified: `php tests/run.php` on `tests/test_mail_delivery.php` (five child runs: off, on/failed,... (test gate passed: project test harness)
  - [checked] Components: `tools/audit_mail_transaction.php` - defines its own mail() instead of setting the global. (named code exists and changed)
  - [claimed] Components: `tests/fixtures/mail_delivery.php` - checks an explicit transport bypasses mail(). (names no code)
  - [claimed] Edge cases: Handled: an explicit transport receives both notifications and mail() is not called (fi... (names no code)
  - [claimed] Components: `tests/test_mail_delivery.php` - fails if lib/mail.php reads `$GLOBALS`. (names no code)
  - full per-claim list (12) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0195-mail-transport-parameter.md` gives the commit.
<!-- verified-facts:end -->
