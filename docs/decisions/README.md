# Decision records

One record per issue: `NNNN-slug.md`, where `NNNN` is the GitHub issue number.
Each record has an agent- or author-written **rationale** (claims to check, not
proof) and a tool-generated **Verified facts** block appended by
`integrity/integrity.py record` from the AutomatedDeveloper supervisor.
Read *In short* and *Main design choice* first; read *Traps* before changing
the code a record describes.

Records start at iteration 7 (16 Oct 2026). Earlier work is summarised in
[`../DECISIONS.md`](../DECISIONS.md).

| Record | Issue | Topic |
|---|---|---|
| [0184](0184-phone-locked-prefix.md) | #184 | Registration phone: fixed +65 prefix, 8 local digits |
| [0194](0194-login-lockout-keying.md) | #194 | Login lockout keyed on client and login name; fails open |
| [0195](0195-mail-transport-parameter.md) | #195 | Mail transport passed as a parameter, no test global |
| [0196](0196-booking-doctor-sets.md) | #196 | Booking by doctor-id sets; reschedule pin; no GET CSRF token |
| [0197](0197-footer-hours-summary.md) | #197 | Footer hours from a day summary with a database fallback |
| [0198](0198-about-image-size.md) | #198 | About Us photo at display size; image size budget |

#168-#183 were committed before the record gate was enabled and have no records.
