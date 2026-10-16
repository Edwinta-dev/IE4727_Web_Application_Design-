# 0194: Login lockout keyed on client and login name
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #194 · Date: 2026-10-16

## In short
Wrong passwords now lock out only the login name that was mistyped, from that client, instead of every account behind the same IP address. A looser per-client cap still stops one client from guessing across many accounts, and a broken lockout store no longer blocks every login.

## How it works
1. When someone signs in, the site first checks two counters: one for this computer and this login name, and one for this computer overall.
2. If the login name has 5 recent failures, or the computer has 20 recent failures across any names, the attempt is refused without checking the password.
3. Checking only reads the counters; it never rewrites the counter file, so many people can check at once.
4. A wrong password adds one to both counters. A correct password clears that login name's counter only.
5. Counters expire 15 minutes after the first failure.
6. If the counter file cannot be opened, locked or written, the site logs a line to the PHP error log and lets the attempt go ahead, rather than refusing everyone.
7. Each operating-system user (Apache, or a developer running tests) gets its own counter file, so a file created by one cannot lock out the other.

## Main design choice
Key the lockout on client plus login name, with a looser per-client cap, rather than on the client alone (the #163 design). Client-only keying protects against guessing but punishes everyone who shares an address: in the XAMPP demo every user is 127.0.0.1, so one person's typos locked out the admin. Keying on login name alone was rejected because one client could then try 5 passwords on every account without limit; the per-client cap of 20 closes that. Client-only keying would be the better choice only if each client were reliably one person.

## Traps: why the obvious fix is wrong
- Do not make `auth_is_limited` fail closed again "for safety". A store error then denies every login site-wide; the #163 version did exactly this when a CLI test run created the file as 0600 and Apache could not open it.
- Do not drop the per-client counter to simplify. Without it, per-name keying allows unlimited guessing across accounts from one client.
- Do not go back to one shared store file name. Different OS users (Apache vs CLI) cannot share a 0600 file; the per-owner suffix in the path is what prevents the cross-user lockout.
- `auth_clear_failures()` with no argument clears every counter from the client (tests use this); with a name it clears only that name. A successful login must not clear the client cap, or a sprayer could reset it by logging into their own account.

## Components
- `clinic-base/lib/auth.php::attempt_login` - passes the login name to the throttle.
- `clinic-base/lib/auth.php::auth_user_key` - hashed client + login name key.
- `clinic-base/lib/auth.php::auth_client_key` - hashed client key for the cap.
- `clinic-base/lib/auth.php::auth_record_is_live` - window expiry test.
- `clinic-base/lib/auth.php::auth_is_limited` - read-only check of both counters.
- `clinic-base/lib/auth.php::auth_record_failure` - increments both counters.
- `clinic-base/lib/auth.php::auth_clear_failures` - clears one name or the whole client.
- `clinic-base/lib/auth.php::auth_failure_store_read` - shared-lock read, fails open.
- `clinic-base/lib/auth.php::auth_failure_store_change` - exclusive-lock write, logs errors.
- `clinic-base/lib/auth.php::auth_failure_store_decode` - tolerant unserialize.
- `clinic-base/lib/auth.php::auth_failure_store_path` - per-OS-user store path.
- `tests/test_auth.php` - lockout, cap and fail-open cases.

## Breaks or needs follow-up
None. The store file name changes, so failure counts recorded under the old shared file are ignored (they would have expired within 15 minutes anyway).

## Problem and constraints
The code review of #163 found three faults: a global per-IP lock, fail-closed behaviour on store errors, and an exclusive lock plus rewrite on every read. Constraints from AGENTS.md: no new tables or packages, so the throttle stays in a temp-directory file outside the web root.

## Alternatives considered
- Store counters in the database - rejected: the schema is fixed and adopted, and a new table is out of bounds.
- Resolve the login name to an account id before counting - rejected: costs a DB lookup on every attempt; username and email for one account count separately instead.
- Make the store world-writable (0666) - rejected: lets any local user edit lockout state; a per-owner file keeps 0600.

## Trade-offs
Username and email for the same account are separate counters, so an attacker gets up to 10 tries per account before the per-client cap of 20 bites. Fail-open means a broken store disables throttling silently except for the error log line; that matters if the temp directory becomes unwritable in production.

## Key parameters
- 5 failures per login name (`clinic-base/lib/auth.php::AUTH_MAX_FAILURES`), unchanged from #163.
- 20 failures per client (`clinic-base/lib/auth.php::AUTH_MAX_CLIENT_FAILURES`), new; chosen as 4 accounts' worth of typos.
- 900 s window (`clinic-base/lib/auth.php::AUTH_FAILURE_WINDOW`), unchanged.

## Assumptions
- `REMOTE_ADDR` identifies the client; behind a proxy every user would share the proxy's address and only the per-name counter would separate them.
- On Windows the OS user comes from `USERNAME`; Apache running as a service and a developer shell therefore get different files.

## Edge cases
- Handled: one user's typos while another user on the same IP logs in; spraying 20 different names from one client; an unopenable store (blocked by a directory at the path).
- Not handled: a store that opens but was corrupted by a crash mid-write is read as empty, which resets counters early. A user would see their lockout end early.

## Not verified
- Behaviour under Apache as a different OS user was not run; the per-owner path is reasoned, not observed.
- Lock contention under concurrent logins was not measured.
- The `posix_geteuid` path on Linux/macOS was not exercised (tests ran on Windows).

## Changes outside the scope
None.

## How it was verified
`php tests/run.php` on `tests/test_auth.php`: "authentication and role guards" (lockout across sessions, other account on the same client allowed, window reset, per-client cap, client-wide clear, source checks for the shared lock) and "login throttle fails open when the store cannot be used". The full suite was run once, after #195 was applied on the same branch (see record 0195).

## To change this
- Stricter: lower `AUTH_MAX_CLIENT_FAILURES` (cost: shared-IP classrooms hit it sooner).
- Fail closed for a hardened deployment: return true from `auth_is_limited` when the read returns null (cost: a store fault blocks all logins).
- Likeliest bug sources: the client-wide clear matching on the stored `client` field, and the path suffix on hosts without `USERNAME`/`USER`.

## Rollback
Reverting restores the #163 per-IP lock and the shared store name. Nothing else depends on the new function names; `tests/test_auth.php` would need its #194 assertions reverted with it.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 11:56 UTC for issue #194; compared HEAD..working tree (base a26e889). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/lib/`: 1 file(s), 0 component(s), +94/-35 lines
- plus 1 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/lib/auth.php`, `clinic-base/actions/login.php`. Verdict: pass.

**Supervisor checks passed**

project test harness

**Evidence**

11 checked by the tool, 1 tested in this run, 16 claimed only (of 28 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 11 checked, 0 tested, 1 claimed
- Key parameters: 0 checked, 0 tested, 3 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 1 tested, 0 claimed
- Traps: 0 checked, 0 tested, 4 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 3 claimed
- Breaks or needs follow-up: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/lib/auth.php::attempt_login` - passes the login name to the throttle. (named code exists and changed)
  - [tested] How it was verified: `php tests/run.php` on `tests/test_auth.php`: "authentication and role guards" (lockout... (test gate passed: project test harness)
  - [checked] Components: `clinic-base/lib/auth.php::auth_user_key` - hashed client + login name key. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_client_key` - hashed client key for the cap. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_record_is_live` - window expiry test. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_is_limited` - read-only check of both counters. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_record_failure` - increments both counters. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_clear_failures` - clears one name or the whole client. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_failure_store_read` - shared-lock read, fails open. (named code exists and changed)
  - [checked] Components: `clinic-base/lib/auth.php::auth_failure_store_change` - exclusive-lock write, logs errors. (named code exists and changed)
  - full per-claim list (28) in the JSON record

**Consistency**

0 error(s), 0 warning(s) between the rationale above and the change.

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0194-login-lockout-keying.md` gives the commit.
<!-- verified-facts:end -->
