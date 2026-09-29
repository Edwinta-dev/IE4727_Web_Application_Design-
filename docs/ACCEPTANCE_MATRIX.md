# Acceptance matrix

This is the grading index for the Theme 4 clinic portal. Every criterion has an
owner page (or a shared boundary), the model/API surface that supplies it, the
authorization boundary, and a reproducible check. `php tests/run.php` is the
automated gate; browser steps are smoke-test evidence only.

## Story and criterion matrix

| ID | Story / acceptance criterion | Page or boundary | Model / implementation | Authorization | Evidence |
|---|---|---|---|---|---|
| C01 | Public home explains the service and offers role entry points | `index.php` | `models/doctors.php` | Public | `php tests/run.php` |
| C02 | Public doctor directory lists searchable doctor information | `doctors.php` | `models/doctors.php` | Public | `php tests/run.php` + browser smoke |
| C03 | Doctor profile shows specialty, qualifications, languages, write-up and image | `doctor.php` | `find_doctor()` | Public | `php tests/run.php` + browser smoke |
| C04 | Doctor profile exposes only valid future availability | `doctor.php` / `book.php` | `models/slots.php` | Public to view; patient to book | `php tests/run.php` + `test_slots` when present |
| C05 | Patient registration validates required fields and stores credentials safely | `register.php` | patient account model | Public; CSRF on POST | `php -l clinic-base/register.php` + browser smoke |
| C06 | Login identifies doctor, patient, or config-defined admin | shared auth boundary | `doctor`/`patient` credentials; `ADMIN_USER`/`ADMIN_HASH` | Public entry; role session after success | `php tests/run.php` + role smoke |
| C07 | Patient dashboard lists upcoming and historical appointments | `patient/home.php` | patient appointment model | `require_login()` + patient ownership | `php -l clinic-base/patient/home.php` |
| C08 | Patient books one available slot and receives confirmation | `book.php` | slot/appointment transaction | Patient only; CSRF; ownership | `php tests/run.php` + booking smoke |
| C09 | Double booking is rejected without corrupting slot state | `book.php` / model boundary | transaction plus `uq_slot` | Patient only; database guard | `php tests/run.php test_slots` |
| C10 | Patient cancels only their own eligible appointment | `patient/home.php` | appointment model | Patient ownership; CSRF | `php -l clinic-base/patient/home.php` + smoke |
| C11 | Doctor dashboard shows only that doctor's appointments | `doctor/home.php` | doctor appointment model | `require_doctor()` | `php -l clinic-base/doctor/home.php` |
| C12 | Doctor creates, blocks and reopens future availability | `doctor/schedule.php` | `models/slots.php` | `require_doctor()`; CSRF | `php -l clinic-base/doctor/schedule.php` + smoke |
| C13 | Doctor cannot alter another doctor's slot or appointment | doctor boundary | model ownership predicates | `require_doctor()` + DoctorID scope | `php tests/run.php` |
| C14 | Doctor records diagnosis, prescription, treatment, follow-up and remarks | `doctor/visit.php` | appointment model | `require_doctor()` + appointment ownership; CSRF | `php -l clinic-base/doctor/visit.php` + smoke |
| C15 | Doctor can mark supported appointment outcomes | `doctor/visit.php` | appointment model | Doctor ownership; enum values only | `php tests/run.php` |
| C16 | Admin console lists operational accounts and supports permitted cleanup | `admin/console.php` | admin models | `require_admin()`; CSRF | `php -l clinic-base/admin/console.php` + smoke |
| C17 | Admin cleanup removes a doctor with the documented cascade | `admin/console.php` | doctor deletion model | Admin only; CSRF | `php tests/run.php` + database smoke |
| C18 | Admin outbox shows notification audit rows and delivery status | `admin/outbox.php` | notification model | `require_admin()` | `php -l clinic-base/admin/outbox.php` |
| C19 | Appointment notifications are logged before local delivery is attempted | shared mail boundary | `send_mail()` | Calling role's authorization | `php tests/run.php` + outbox smoke |
| C20 | Failed local delivery is non-fatal and remains visible as `failed` | shared mail boundary | notifications model | Admin visibility only | `php tests/run.php` + outbox smoke |
| C21 | Every mutation uses POST-Redirect-GET | all mutation pages | page handlers | CSRF before database access | `php tests/run.php` + source audit |
| C22 | Every form contains a CSRF field and every POST validates it | all forms | `csrf_field()` / `csrf_check()` | Session boundary | `php tests/run.php` + source audit |
| C23 | Every protected page invokes its guard immediately after includes | protected pages | `require_login()`, `require_doctor()`, `require_admin()` | Role boundary | `php tests/run.php` + source audit |
| C24 | Every database operation uses `q($sql, $params)` | models and shared DB layer | `lib/db.php` | N/A | `php tests/run.php` + source audit |
| C25 | All rendered values are escaped through `e()` | PHP views/partials | `lib/helpers.php` | N/A | `php tests/run.php` + source audit |
| C26 | Schema names, enums and relationships match the fixed contract | database | `schema/001_schema.sql` + `002_migrate.sql` | N/A | `php tools/db_reset.php` |
| C27 | Slot and appointment date/time are derived from single DATETIME columns | slot/appointment views | `DATE()` / `TIME()` | N/A | `php tests/run.php` + source audit |
| C28 | Allergies round-trip as JSON, never a bare string | registration/profile model | `json_encode()` / `json_decode()` | Patient ownership | `php tests/run.php` + database smoke |
| C29 | No prohibited transport, framework, dependency, or external mail is used | `clinic-base/` | source/dependency boundary | N/A | `php tests/test_verify_clean.php` |
| C30 | The page budget contains exactly the eleven approved content pages | `docs/PAGES.md` | page inventory | N/A | `php tests/run.php` |
| C31 | Each page has a distinct title, text, and an image | all eleven pages | page templates/partials | N/A | `php tests/run.php` + source audit |
| C32 | Empty, invalid, past, booked and blocked states are represented clearly | `book.php`, dashboards | slot/appointment models | Role-specific visibility | `php tests/run.php` + browser smoke |

There are no unmapped criteria: C01–C20 cover the user stories and C21–C32
cover the cross-cutting grading rules that apply to those stories.

## Required schema vocabulary

Evidence must use `recipient`, JSON `Allergies`, `SlotDateTime`,
`appointmentDateTime`, `FollowUp` as a 0/1 flag, and the exact enum spellings
from `AGENTS.md`. A check that uses an old spelling or split date/time fields
does not satisfy the corresponding row above.
