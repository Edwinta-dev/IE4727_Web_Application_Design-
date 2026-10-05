# Iteration 5 verification — issue #155

Application baseline: `293ca6b` (the existing #134–#154 repairs), 5 October
2026, Asia/Singapore. This gate changes capture tooling and documentation only.
No application, schema, dependency, test threshold or clinic-plus file changes.

Two existing browser selectors are reconciled with the current UI as part of
the gate. The #147 menu check now selects the uniquely named link inside Main
navigation, because #151 added a second booking link in the empty state. The
#146 outbox check now reads each disclosure summary, because #150 moved the
full message body into the subject cell. The exact subject assertion and
full-message count remain; the navigation check additionally requires one
matching link. Neither application behaviour nor an assertion threshold changes.

Acceptance command: `node tools/ui/iter5-gate.test.mjs`. It is the maintained
adaptation of `UIPROBLEMS/Open/iter5/shoot_iter5.cjs`. The local script now
delegates to this tracked runner, because `UIPROBLEMS/` is gitignored. Running
the tracked command works without that local wrapper. Original images are
retained unchanged; the runner requires the complete original 21 PNGs and JPG
and fails on missing captures or failed assertions.

## Isolation and comparison method

The runner uses existing `testPhp` and `withTestServer` guards: resolved database
`ie4727db_test`, owned checkout server `127.0.0.1:8123/clinic-base/`, and disabled
mail in CLI and HTTP children. It resets only the test database and restores
the seed in `finally`. It reads no XAMPP credentials and never uses port 8000.
All database tests and screenshot runs are serial.

All 22 original names are reproduced under `UIPROBLEMS/after/iter5/`.
Paired BEFORE/AFTER images are under `UIPROBLEMS/after/iter5/comparisons/`,
using the original stem and `.png`; `sheet-1.png` through `sheet-6.png` provide
an overview. Capture URLs, viewports, installed-Chrome version, hover geometry
and count measurements are in `UIPROBLEMS/after/iter5/measurements.json`.
These local image artifacts remain gitignored, consistent with project policy.

Dates come from the current PHP seed rather than October 2026 literals.
Doctor and specialty scaling uses existing guarded database fixtures rather
than DOM clones. The originals include extra doctor rows and failed-mail
history from the owner's old XAMPP copy; these are not imported into the test
database. Default AFTER views use the clean five-doctor/two-notification seed.
Layout assertions and count fixtures distinguish data changes from UI defects;
this is not a pixel-equality test. The old `06-book-slots-two-open-1280.png` name now shows two
successive selections in the repaired single-radio-group form; exactly one
selection and one confirmation remain. The old registration “specialty-text”
name now shows the repaired select. The old guard “no-message” name now shows
the required admin sign-in message. These names identify historical evidence,
not assertions that the old UI still exists.

The 10-specialty historical capture uses ten source doctors with legacy raw
categories. The current clinic deliberately exposes only its five canonical
services. The 3/7/12 matrix verifies real doctor and slot counts and supplies
3/7/12 distinct raw specialty rows; expected rendered service counts are 3/5/5.
Adding seven or twelve public services would contradict the existing #138
contract. Unsupported legacy categories are not invented as public services.

The JPG is re-shot with installed Google Chrome in headless mode, not bundled
Chromium labelled as Chrome. The owner's original is a manually cropped,
headed Chrome screenshot; its crop and OS chrome are not reproduced. The
same Paediatrics hover is measured and captured in the services section.

All eleven existing pages also have fresh `shoot.mjs` captures at 1280x800
and 390x844 under `UIPROBLEMS/after/iter5-checkout-before/` and
`UIPROBLEMS/after/iter5-checkout-after/`. These bracket unchanged application
code; the historical defect comparison is the original-name set above.
The six local overview sheets in `iter5/checkout-comparisons/` cover all
22 page/viewport pairs. Both these sheets and the six original-evidence
comparison sheets were opened for visual inspection.

Across the seeded captures and 3/7/12 matrix, ordinary hover lifts doctor images
by 6.3–6.6px and specialty images by 5.1–5.2px. Image crop ratios, object-fit,
object-position, source image, layout widths/heights and text dimensions stay
unchanged; neighbours keep identical measured geometry. Reduced-motion
captures have zero geometry change. Doctor portraits retain 4:5 proportions
and specialty photographs retain 16:9 proportions.

## Issue verdicts

“Already fixed” means the repair was in the checked-out baseline, before this
gate. The gate does not claim authorship of those application repairs. Paths
in this table are relative to `UIPROBLEMS/after/iter5/`; their same-named
originals are in `UIPROBLEMS/Open/iter5/`.

| Issue | Verdict | AFTER evidence and verification |
|---|---|---|
| #134 featured doctors carousel | Already fixed | `01-featured-doctors-{1280,1024}.png`, `01-featured-doctors-hover-1280.png`, `01-featured-doctors-12-items-1280.png`; real DB counts, stable hover layout |
| #135 specialty hover | Already fixed | `02-specialty-strip-1280.png`, `02-specialty-strip-hover-1280.png`, `02-specialty-strip-hover-realchrome.jpg`; no downward jump, stable crop ratio and neighbour text, reduced motion |
| #136 specialty scaling | Already fixed | `03-specialty-strip-10-items-1280.png`, `matrix/*-home.png`; canonical service cap respected with 3/7/12 raw source rows |
| #137 card minimum-height gap | Already fixed | `01-featured-doctors-1280.png`, `02-specialty-strip-1280.png`; content-sized rows in paired comparison |
| #138 specialty normalization | Already fixed | `05-doctors-specialty-filter-1280.png`, `05-register-doctor-specialty-text-1280.png`; canonical dropdowns and legacy normalization |
| #139 expanding slot rows | Already fixed | `06-book-slots-closed-1280.png`, `06-book-slots-two-open-1280.png`, `06-book-slots-390.png`; slot heights unchanged after replacing selection |
| #140 mail people/templates | Already fixed | No original screenshot exists; PHP mail regression checks both recipients, greetings, people, old/new times and actors |
| #141 blocking mail delivery | Already fixed | No original screenshot exists; delivery-off HTTP regression and PHP delivery regression; no off-box mail |
| #142 admin access/guard | Already fixed | `09-admin-landing-1280.png`, `09-outbox-guard-redirect-no-message-1280.png`, `07-outbox-list-1280.png`; real config-defined test-admin login and explicit sign-in alert |
| #143 patient notifications/status/nav | Already fixed | `10-patient-dashboard-1280.png`; authenticated notification and counter regression |
| #144 booking URLs/availability | Already fixed | `11-doctors-book-unavailable-1280.png`; canonical URLs and unavailable-doctor explanation regression |
| #145 mobile header | Already fixed | `12-phone-header-home-390.png`, `12-phone-register-390.png`; mobile menu and header geometry regression |
| #146 copy/confirmation hygiene | Already fixed | `06-book-slots-*.png`, `07-outbox-list-1280.png`, `09-admin-landing-1280.png`, `13-doctor-schedule-1280.png`; shared confirmation and copy regression |
| #147 booking controls | Already fixed | `06-book-slots-closed-1280.png`, `06-book-slots-390.png`; single date control and optional time filters |
| #148 schedule editor | Already fixed | `13-doctor-schedule-1280.png`; weekday alignment, single picker and 3/7/12 slot regression |
| #149 registration arrows | Already fixed | `05-register-doctor-specialty-text-1280.png`, `12-phone-register-390.png`; contrast, glyph fit, keyboard and reduced-motion regression |
| #150 outbox layout/status | Already fixed | `07-outbox-list-1280.png`; four-column layout, body disclosure, neutral delivery labels and legend |
| #151 dashboard chronology | Already fixed | `10-patient-dashboard-1280.png`; cancelled/upcoming/past and empty-action regression |
| #152 doctor day board | Already fixed | `10-doctor-dayboard-1280.png`; previous/next days and next-booked-day regression |
| #153 admin layout/filter placement | Already fixed | `09-admin-landing-1280.png`; doctor-table fit and unified filters regression |
| #154 portraits/specialty labels | Already fixed | `01-featured-doctors-{1280,1024}.png`, `01-featured-doctors-12-items-1280.png`; neutral avatar, contained labels and equal card heights regression |
| #155 capture/comparison gate | Fixed | Tracked capture runner, complete original-name manifest, paired comparisons and this report |

## Validation

Final gate and PHP suite are green. No iteration-5 UI finding is deferred.
The two initial selector failures are preserved in `validation-7.txt` and
`validation-8.txt`; their corrected full runs pass in `final-validation-0.txt`
and `final-validation-1.txt`. These were harness incompatibilities with the
already-repaired UI, not application regressions. No test was skipped or weakened.

| Command | Final result |
|---|---|
| `node tools/ui/iter5-gate.test.mjs` | OK; 22/22 original-name captures and 12 desktop/mobile/motion count combinations |
| `php tests/run.php` | 95 passed, 0 failed |
| `node --test tools/ui/isolation.test.mjs` | 7 passed, 0 failed, 0 skipped |
| `node tools/ui/shoot.mjs --serve --label iter5-checkout-before all` | 22 page/viewport captures |
| `node tools/ui/shoot.mjs --serve --label iter5-checkout-after all` | 22 page/viewport captures |
| `node tools/ui/featured-doctors.test.mjs` | OK |
| `node tools/ui/specialty-canonical.test.mjs` | OK |
| `node tools/ui/booking-slots.test.mjs` | OK |
| `node tools/ui/booking-urls.test.mjs` | OK |
| `node tools/ui/booking-controls.test.mjs` | OK, corrected selector rerun |
| `node tools/ui/copy-hygiene.test.mjs` | OK, corrected selector rerun |
| `node tools/ui/schedule-layout.test.mjs` | OK |
| `node tools/ui/registration-arrows.test.mjs` | OK |
| `node tools/ui/outbox-layout.test.mjs` | OK |
| `node tests/ui_admin_outbox.mjs` | OK |
| `node tests/ui_patient_dashboard.mjs` | OK |
| `node tests/ui_patient_dashboard_151.mjs` | OK |
| `node tests/ui_doctor_dayboard_152.mjs` | OK |
| `node tests/ui_admin_console_153.mjs` | OK |
| `node tests/ui_header.mjs` | OK |
| `node tools/ui/doctor-tiles.test.mjs` | OK |
| `node tests/ui_mail_delivery.mjs` | OK; delivery-off booking redirect 0.139s, distinct patient/doctor rows logged |
| `php tools/db_reset.php --test` | Reset test database only |
| `node --check` on the three changed `.mjs` files and local capture wrapper | Pass |
| `git diff --check` | Pass |

The PHP suite includes the mail-template people/actor/time checks and
delivery-off/on row-first regressions. Native mail is disabled in the browser
children; no off-box delivery occurs. Existing nonfatal CLI session/header
warnings in PHP tests do not affect their results. No PHP file changed, so
there is no new PHP lint target.

Serial command exit codes/log paths are in `iter5/validation-results.json`;
the final four required reruns are in `iter5/final-validation-results.json`.
Installed Google Chrome version is `154.0.8037.93` (headless). Local screenshots
and logs are evidence artifacts, not additional tracked project dependencies.
