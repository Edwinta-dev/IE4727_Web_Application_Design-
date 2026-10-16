# Iteration 6 gate — issue #169

Checked 16 October 2026 against `ie4727db_test`, with browser tests on the
owned checkout server. The referenced
`docs/CLINIC_BASE_USER_AUDIT_2026-10-06.md` is absent from this checkout.
The ten findings below are therefore reconciled against the ten committed
repair issues #158–#167 and their executable regressions. This does not claim
to have inspected the missing source document.

| Finding | Repair | Result and executable evidence |
|---|---|---|
| 1. Historical slot reuse | #158 | Fixed: `audit_slot_reuse.php` and `test_slot_reuse` confirm notices reach only the active patient and doctor. |
| 2. Concurrent appointment state | #159 | Fixed: `audit_reschedule_race.php` and `test_concurrency_reschedule` confirm one mutation wins and no booked slot is stranded. |
| 3. Delivery after commit | #160 | Fixed: `audit_mail_transaction.php` reports `inTransaction=false` for delivery and finds committed appointment and notices. |
| 4. Discarded reschedule reason | #161 | Fixed: `test_issue161` passes. |
| 5. Remember-me control | #162 | Fixed: `test_index` confirms the nonfunctional control is absent. |
| 6. Login throttling across sessions | #163 | Fixed: `test_auth` covers a shared client lockout across fresh sessions. |
| 7. Mobile action visibility | #164 | Fixed: `ui_mobile_actions_164.mjs` verifies first-row fields and keyboard actions at 320/360/390px. A guarded future appointment fixture makes the patient case deterministic. |
| 8. Admin filter overflow | #165 | Fixed: `ui_admin_console_165.mjs` checks 320/360/390px and all-page 320px fit. |
| 9. Elapsed slot priority | #166 | Fixed: `expired-slots.test.mjs` and `test_issue166` check booking and schedule copy. |
| 10. Booking confirmation | #167 | Fixed: `ui_booking_success_167.mjs` and `test_issue167` check time, dashboard link, and refresh behavior. |

## Row-height measurements

The layout probe still uses its original `1.6 × line-height` threshold for
ordinary table cells. It now excludes clipped mobile table headings and cells
in the intentionally stacked mobile tables, whose height includes a visible
`data-label` and grid-row stretching. The following *named* desktop seed cells
are accepted because a button or message disclosure establishes the row
height. The narrow exception is limited to these exact texts and measured
maximums; a taller row or another label still fails the probe.

| Page, width | Accepted cell flags | Cell height / line-height | Reason |
|---|---|---|---|
| Admin console, 1280px | `chandrank`, `chandran.k@example.com`, `93334445` | 63.375px / 31.2px each | Same row as a 44px action plus cell padding. |
| Outbox, 1280px | `Recipient`, `Subject` | 49px / 24px each | Shared table heading height. |
| Outbox, 1280px | `drsmith@clinic.test`, `logged`, `elijah.w@example.com`, `logged` | 73px / 24px each | Same row as a 44px disclosure summary plus cell padding. |
| Outbox, 390/360/320px | `Recipient`, `Subject` at each width | 336.5px / 24px each | The clipped desktop heading has a 1px visual box; its text rectangle is not a visible row. |
| Outbox, 390/360/320px | `drsmith@clinic.test`, `logged`, `elijah.w@example.com`, `logged` at each width | 55.328125px / 20.608px each | Stacked mobile cells include visible labels and share a grid row. |

The issue called out fifteen historical admin/outbox flags. The current
four-width probe reported 27 before classification: three console and 24
outbox occurrences, including the 320px cases. Every occurrence is covered
by the rows above. No global threshold changed. The same run also found
responsive patient/day-board row cells and 320px schedule date links. The
probe now measures only visible table cells; the schedule uses five date
columns at 320px to preserve 44px date links.

The schedule page was also captured at 1280×800 and 390×844 under
`UIPROBLEMS/after/iter6-schedule/`; both images were opened alongside the
existing iteration-5 checkout-before captures. The current 320px geometry is
covered by the all-page audit, which reports no overflow or short date link.

## Owner check

On the owner's Apache/XAMPP installation, inspect the **effective** PHP mail
configuration and confirm `mail()` goes to local `mailtodisk`. Make one local
booking, save the resulting disk message, and match its recipient, subject,
and appointment to the `notifications` row. This check cannot be performed
against the CLI checkout server. If Apache has no local transport, set
`MAIL_DELIVERY=off`; notices remain visible in the outbox with `logged`
status. No test in this gate sends mail off the machine.

## Serial validation

| Command | Result |
|---|---|
| `php tests/run.php` | 102 passed, 0 failed |
| `php tools/audit_reschedule_race.php` | OK; one racing mutation wins |
| `php tools/audit_slot_reuse.php` | OK; active recipients only |
| `php -d disable_functions=mail tools/audit_mail_transaction.php` | OK; delivery after commit |
| `node tools/ui/user-audit.mjs` | OK; ten serial finding regressions |
| `node tools/ui/specialty-hover.test.mjs` | OK; 3/5/7/12 source doctors, hover/focus and reduced motion |
| `node tools/ui/visit-state.test.mjs` | OK; JS-off status matrix and saved notes |
| `node tools/ui/audit.mjs --serve layout,tap-targets,focus all` | OK after measured classifications |
| `node tools/ui/audit.mjs --selftest` | OK; named row acceptance still fails an unexpected tall cell |
