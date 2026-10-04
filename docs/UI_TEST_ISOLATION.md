# Issue #120 evidence and live-data handoff

Finding: supplied UI_Defects.pdf p. 3, new finding 1 (C01?C03/C16).
The issue reports confirmed prior contamination of `ie4727db`: one synthetic
doctor and two synthetic patients from `tests/ui_register.mjs`. These are
historical report counts, not a new live database inventory. Preserve them for
the separate live-demo reconciliation; do not delete synthetic-looking live
rows without owner review. This issue performs no production DB reads or writes.

Baseline inspected: HEAD `eb9a791`, initially clean working tree. The registration
entry point had no CLINIC_DB_NAME assignment; withServer inherited the ordinary
site default and accepted any HTTP 200 on port 8123. Booking, schedule and admin
entry points selected the test name but used unguarded CLI PHP launches and the
same server readiness check. The GET-filter test only reads database rows and
logs in, but now also uses the isolated launcher. Screenshot/audit tools remain
read-only development tools with their existing interface.

No doctor/visit.php, docs/UI_AUDIT_REPORT.md or tools/ui/visit-state.test.mjs
changes were present at this baseline; none were overwritten. Carry-over #114
remains separately reported as partly fixed; it was not assessed or changed here.
The supplied round-2 PDF is not present under UI_Defects.pdf in this checkout.
The available Open/clinicissues.pdf and pdf-page-3.png concern the earlier audit,
so they were not renamed or presented as round-2 evidence. The written issue
finding supplies this task's reproduction contract.

Isolation evidence: invalid/missing environment values refuse before PHP launch;
wrong resolved constants refuse in config-only PHP processes before DB includes;
a stub server returning HTTP 200 cannot invoke a mutation callback. Allowed
children report `ie4727db_test` from resolved config, authenticated registration
creates exactly two patients and one doctor in that DB, and exact-run cleanup
returns both counts to zero. No application markup, schema or production config
was changed. PHP mail is disabled in the test processes; logging semantics remain.

Validation on 4 Oct 2026, Asia/Singapore: `php tests/run.php` passed 77 tests.
Registration passed JS on/off patient signup and doctor signup. The seven
isolation/config regressions passed without a database connection. The browser
fixture dates use current Asia/Singapore day 2026-10-04, booking through Oct 10,
schedule management through 2027-10-03. Reset output identifies ie4727db_test,
5 doctors, 8 patients, 3570 slots, 25 appointments and 2 notifications; these are
observed fixture counts, not production counts. Exact commands and serial-run
requirements are in tools/ui/README.md.

All writing browser entry points passed with effective child-config verification:
booking dates, schedule horizon and admin flash/cascade. GET filters also passed
on the isolated child. Desktop/mobile captures of untouched registration and
newly authenticated patient/doctor homes were opened and inspected in
`UIPROBLEMS/after/issue120-before/` and `issue120-after/` (patient-js, patient-nojs,
doctor at 1280x800 and 390x844). These capture real role sessions rather than login
redirects. No visual redesign or geometry audit was needed for this harness-only
change. The observed doctor Home/Appointment tab duplication remains #114.
PHP lint passed for all three touched/added PHP helpers; JS syntax and git diff
whitespace checks passed. The PHP suite emits existing session/header warnings
but exits successfully with 77 passed, 0 failed.

Capture labels mean before signup and after signup on the isolated server, not
an unsafe replay of the old production-writing harness. The no-JS form captures
show existing field-panel clipping; signup still passes without JavaScript.
That visual limitation is outside #120 and the page was left unchanged.
