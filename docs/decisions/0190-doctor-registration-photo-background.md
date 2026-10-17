# 0190: Doctor registration photo and background
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #190 · Date: 2026-10-16

## In short
Doctor registration requires a profile photo and at least 50 characters of professional background. The directory uses the saved photo immediately after registration.

## Problem and constraints
The registration form previously allowed a doctor account without a photo or a useful background. The existing `doctor.ImageURL` and `doctor.WriteUp` columns hold these values. Validation must use a normal multipart POST and keep patient registration working.

## Main design choice
Validate the uploaded image on the server with PHP's upload status, size, `getimagesize()` and MIME detection. Generate a random filename under `assets/uploads/doctors` (`DOCTOR_PHOTO_DIR`), store its relative path in `doctor.ImageURL`, and delete the file if account creation fails. Keep the live character counter local to `register.php`; putting it in shared JavaScript would affect unrelated pages.

## How it works
The doctor form marks the background and photo required, describes the 50-character and 2 MB limits, and updates the background count on input. The registration action validates both fields before moving the uploaded file. Account creation stores the generated image path. The existing Find a Doctor page reads `ImageURL` through its doctor model.

## Alternatives considered
- Trust the browser's `accept` and `minlength` attributes: rejected because crafted POSTs bypass them.
- Keep the counter in shared `assets/app.js`: possible, but the page-local script keeps this one-form behavior within the issue scope.
- Save uploads in `assets/img` beside the site's own images: the first version did this and was changed after review. That folder is tracked by git, so real doctors' photos would appear as untracked files an agent could commit, and `tests/test_issue198.php` caps every image there at 250 KB while uploads may be 2 MB, so one real upload would fail the suite.

## Key parameters
- Background minimum: 50 characters after trimming, using the existing `min` validation rule.
- Upload maximum: 2 * 1024 * 1024 bytes. Accepted detected MIME types: JPEG, PNG, WebP.
- Filename: `doctor-` plus 16 random bytes in hex, with an extension derived from detected MIME.

## Assumptions
PHP can create and write `clinic-base/assets/uploads/doctors` (the action creates it on first upload; it is git-ignored). An uploaded image is retained as submitted after type and size checks.

## Edge cases
Missing, failed, oversized, and non-image uploads return field errors. A short background returns a field error. If account creation fails after a successful move, the action rolls back the database transaction and removes the moved file.

## Traps
- The `accept` attribute and live counter are guidance; the PHP action must reject missing, invalid, oversized and short submissions independently.
- Use the detected MIME for the saved extension, rather than trusting the uploaded filename.
- A database failure after moving a file must remove that file.

## Components
- `clinic-base/register.php`: required markers, photo input, background hint and live counter.
- `clinic-base/actions/register.php`: server validation, generated filename and database handoff.
- `clinic-base/lib/validate.php`: upload validation.
- `clinic-base/assets/style.css`: keep the role viewport from shifting sideways when the photo field receives focus on mobile.
- `tests/ui_issue190.mjs`: browser acceptance with isolated test database, including directory image loading.
- `tests/ui_register.mjs` and `tools/ui/specialty-canonical.test.mjs`: existing browser registrations supply a photo and a long enough background, and remove their generated files.

## Changes outside the scope
- `clinic-base/assets/style.css::registration-role-viewport`: the new file input causes a 35-pixel horizontal shift in Chromium at 390 px without `overflow: clip`; the browser acceptance test checks this behavior.
- `tests/ui_register.mjs` and `tools/ui/specialty-canonical.test.mjs`: existing registration tests must submit the newly required doctor fields and clean up generated photos.
- This decision record is required by the repository's decision gate.

## How it was verified
Before screenshots under `UIPROBLEMS/after/iter7/issue190-doctor-registration/before/` showed that doctor registration lacked a photo input and a background minimum. After screenshots in the adjacent `after/` directory were opened at 1280 and 390 pixels. `node tests/ui_issue190.mjs` passed against `ie4727db_test`, including rejection cases and a loaded directory photo. `node tests/ui_register.mjs`, `node tools/ui/specialty-canonical.test.mjs` and `php tests/run.php` also passed.

## Trade-offs
Original image bytes are retained after type and size validation. The 2 MB cap bounds each upload, but there is no image recompression.

## Breaks or needs follow-up
None known.

## Not verified
Filesystem behavior on the owner's XAMPP deployment was not tested here; browser tests used the repository's PHP test server.

## Rollback
Remove the added doctor form fields and validation together. The generated files from registered doctors would need separate cleanup if those accounts were also removed.

## To change this
If the clinic changes the photo limit or supported types, update the form hint, PHP validation and browser acceptance together.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-16 13:45 UTC for issue #190 attempt 16; compared HEAD..working tree (base 1bea5e9). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/`: 1 file(s), 0 component(s), +21/-3 lines
- `clinic-base/actions/`: 1 file(s), 0 component(s), +22/-1 lines
- `clinic-base/assets/`: 1 file(s), 0 component(s), +1/-0 lines
- `clinic-base/lib/`: 1 file(s), 0 component(s), +23/-0 lines
- plus 3 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/register.php`, `clinic-base/actions/register.php`, `clinic-base/lib/validate.php`, `clinic-base/models/accounts.php`, `clinic-base/models/doctors.php`, `clinic-base/doctors.php`, `clinic-base/assets/img/**`. Verdict: violation.
- out of scope: `clinic-base/assets/style.css` (modified); explained in the record: yes

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, scope check, dangling reference check

**Evidence**

4 checked by the tool, 0 tested in this run, 13 claimed only (of 17 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 4 checked, 0 tested, 2 claimed
- Key parameters: 0 checked, 0 tested, 3 claimed
- Edge cases: 0 checked, 0 tested, 1 claimed
- How it was verified: 0 checked, 0 tested, 1 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 1 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/register.php`: required markers, photo input, background hint and live cou... (named code exists and changed)
  - [checked] Components: `clinic-base/actions/register.php`: server validation, generated filename and database... (named code exists and changed)
  - [checked] Components: `clinic-base/lib/validate.php`: upload validation. (named code exists and changed)
  - [checked] Components: `clinic-base/assets/style.css`: keep the role viewport from shifting sideways when the... (named code exists and changed)
  - [claimed] Components: `tests/ui_issue190.mjs`: browser acceptance with isolated test database, including dire... (names no code)
  - [claimed] Key parameters: Background minimum: 50 characters after trimming, using the existing `min` validation r... (no cited constant with a matching value)
  - [claimed] Edge cases: Missing, failed, oversized, and non-image uploads return field errors. A short backgrou... (names no code)
  - [claimed] How it was verified: Before screenshots under `UIPROBLEMS/after/iter7/issue190-doctor-registration/before/`... (no test named)
  - [claimed] Components: `tests/ui_register.mjs` and `tools/ui/specialty-canonical.test.mjs`: existing browser r... (names no code)
  - [claimed] Key parameters: Upload maximum: 2 * 1024 * 1024 bytes. Accepted detected MIME types: JPEG, PNG, WebP. (no cited constant with a matching value)
  - full per-claim list (17) in the JSON record

**Consistency**

0 error(s), 1 warning(s) between the rationale above and the change.
- WARN: 'Main design choice' names no alternative; say which 1-2 real options were possible and why this one won

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0190-doctor-registration-photo-background.md` gives the commit.
<!-- verified-facts:end -->
