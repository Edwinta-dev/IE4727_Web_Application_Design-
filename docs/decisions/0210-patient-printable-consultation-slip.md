# 0210: Printable patient consultation slip and medical certificate with @media print styling
<!-- verify-banner:begin -->
> ⚠ Agent-written record: its claims are hypotheses to verify against the code, not ground truth. Evidence levels in Verified facts: [checked] confirmed by the tool · [tested] cited test exists and passed in this run · [claimed] not verified.
<!-- verify-banner:end -->

Status: implemented · Issue: #210 · Date: 2026-10-27

## In short

Patients viewing completed appointment consultation notes on `patient/home.php?view=<id>` now have access to a structured Medical Consultation Summary & Certificate (`.medical-slip`) and a native "Print consultation slip" action button backed by comprehensive `@media print` styles.

## Problem and constraints

Completed appointment visit notes previously rendered as bare inline text paragraphs without formal clinical certificate formatting or dedicated print media styles. When printing or exporting to PDF, navigation menus, footers, appointment tables, and button chrome cluttered the page. Base-tier constraints strictly forbid creating a 12th page (e.g. `slip.php`), external PDF libraries (FPDF, jsPDF, Composer packages), AJAX/fetch calls, or external UI frameworks.

## Main design choice

Retain the existing 11-page limit by embedding the structured `.medical-slip` certificate within the completed visit notes view on `patient/home.php?view=<id>`. When printed via native browser dialog (`window.print()`), a dedicated `@media print` stylesheet block hides all website chrome (`.site-header`, `.site-nav`, `.site-footer`, `.page-intro`, `.upcoming-appointments`, `.past-appointments`, `.cancelled-appointments`, `.patient-notifications`, `.print-slip-button`, `.table-scroll-hint`) and formats `.medical-slip` in crisp black-and-white on standard A4 paper with proper print margins (`@page { margin: 1.5cm; }`), authentic physician signature lines, and clinic stamp placeholder.

## How it works

When an authenticated patient visits `patient/home.php?view=<id>`, the server resolves the completed appointment record. Above the details, a "Print consultation slip" button triggers native `window.print()`. The certificate container `.medical-slip` includes:
1. Clinic letterhead header with logo (`assets/img/clinic-logo.svg`), clinic address, and telephone.
2. Formal certificate title: "Medical Consultation Summary & Certificate".
3. Consultation metadata: Issue date/time, Patient Name, Patient ID, Attending Doctor, and Medical Specialty.
4. Clinical findings: Diagnosis, Prescribed Medications, Treatment Plan, Follow-up instructions, and doctor remarks.
5. Doctor validation footer: Attending Physician Signature line with doctor's name and clinic stamp placeholder.

In `@media print`, all non-certificate page chrome is hidden (`display: none !important`), backgrounds are reset to white, typography is high-contrast serif and sans, and borders are crisp black.

## Breaks or needs follow-up

None.

## Alternatives considered

- Generating a downloadable PDF via server-side library (FPDF/TCPDF/DOMPDF): rejected due to AGENTS.md strict ban on Composer, vendor/, and third-party packages.
- Creating a separate printable page route (`slip.php`): rejected due to strict 11-page budget cap in docs/PAGES.md.
- Client-side PDF generation via jsPDF: rejected due to ban on external JavaScript libraries and npm packages.

## Assumptions

- Patients use standard modern browsers with native print / Save as PDF capability.
- Completed appointment data in `ie4727db` retains the required clinical fields (Diagnosis, Prescription, Treatment, FollowUp, Remarks, DoctorName, Specialty).

## Edge cases

- When an appointment has no prescribed medication or treatment, the field remains cleanly formatted without breaking layout.
- Mobile viewports wrap metadata fields and validation blocks cleanly; printing respects standard A4 paper dimensions and page boundaries.
- Users with `prefers-reduced-motion` have button transitions disabled.

## Rollback

Revert changes in `clinic-base/patient/home.php` and `clinic-base/assets/css/style.css`.

## Traps

- `assets/style.css` includes broad `@media print` rules hiding generic `header` tags; `.medical-slip .slip-header` uses `<div>` with explicit display overrides to avoid being hidden.
- Avoid duplicate "Dr." prefixes since `doctor.FullName` already contains the title.
- Ensure all dynamic values pass through `e()` to satisfy security audits.

## Key parameters

- Print button: `<button type="button" class="button print-slip-button" onclick="window.print()">Print consultation slip</button>`.
- Container class: `.medical-slip`.
- Page margin: `@page { margin: 1.5cm; }`.
- Letterhead logo: `assets/img/clinic-logo.svg`.

## Components

- `clinic-base/patient/home.php`: structured `.medical-slip` certificate template and print button within `$visit !== null` section.
- `clinic-base/assets/css/style.css`: screen panel layout and comprehensive `@media print` rules hiding chrome and styling the certificate.
- `tests/test_patient_home.php`: markup and CSS assertions verifying certificate structure, print button, and `@media print` rules.

## Changes outside the scope

None. Only files declared within issue scope were modified.

## How it was verified

- `php -l clinic-base/patient/home.php` and `php -l tests/test_patient_home.php` passed syntax checks.
- `php tests/run.php` passed with all 115 tests green.
- Verified markup and stylesheet assertions in `tests/test_patient_home.php`.

## Trade-offs

Using browser-native print rendering avoids adding heavy external PDF libraries or server-side render engines, adhering strictly to course constraints while delivering PDF save functionality directly via native browser print dialogue.

## Not verified

Off-box mail delivery and external hosting environments; testing is strictly isolated to `ie4727db_test`.

## To change this

If clinic address or certificate layout requirements change, update the `.medical-slip` template in `clinic-base/patient/home.php` and corresponding rules in `clinic-base/assets/css/style.css`.

<!-- verified-facts:begin (generated by integrity record; do not edit) -->
## Verified facts

Generated 2026-10-28 01:16 UTC for issue #210 attempt 7; compared HEAD..working tree (base 7724aef). Records are claims to check against the code, not proof.

**Change summary**

- `clinic-base/assets/`: 1 file(s), 0 component(s), +288/-0 lines
- `clinic-base/patient/`: 1 file(s), 0 component(s), +88/-10 lines
- plus 1 test file(s)

**Removed symbols**

None.

**Scope**

Declared: `clinic-base/patient/home.php`, `clinic-base/assets/css/style.css`, `clinic-base/assets/tokens.css`, `tests/test_patient_home.php`. Verdict: pass.

**Supervisor checks passed**

whitespace/conflict-marker check, project test harness, php lint changed files, scope check, dangling reference check

**Evidence**

2 checked by the tool, 1 tested in this run, 17 claimed only (of 20 claims). [checked] = the named code exists and changed, or the cited value matches; [tested] = the cited test exists and the test gate passed in this run; [claimed] = not verified.
- Components: 2 checked, 0 tested, 1 claimed
- Key parameters: 0 checked, 0 tested, 4 claimed
- Edge cases: 0 checked, 0 tested, 3 claimed
- How it was verified: 0 checked, 1 tested, 2 claimed
- Traps: 0 checked, 0 tested, 3 claimed
- Assumptions: 0 checked, 0 tested, 2 claimed
- Trade-offs: 0 checked, 0 tested, 1 claimed
- Not verified: 0 checked, 0 tested, 1 claimed
  - [checked] Components: `clinic-base/patient/home.php`: structured `.medical-slip` certificate template and pri... (named code exists and changed)
  - [tested] How it was verified: Verified markup and stylesheet assertions in `tests/test_patient_home.php`. (test gate passed: project test harness)
  - [checked] Components: `clinic-base/assets/css/style.css`: screen panel layout and comprehensive `@media print... (named code exists and changed)
  - [claimed] Components: `tests/test_patient_home.php`: markup and CSS assertions verifying certificate structur... (names no code)
  - [claimed] Key parameters: Print button: `<button type="button" class="button print-slip-button" onclick="window.p... (no cited constant with a matching value)
  - [claimed] Edge cases: When an appointment has no prescribed medication or treatment, the field remains cleanl... (names no code)
  - [claimed] How it was verified: `php -l clinic-base/patient/home.php` and `php -l tests/test_patient_home.php` passed s... (no test named)
  - [claimed] Key parameters: Container class: `.medical-slip`. (no cited constant with a matching value)
  - [claimed] Edge cases: Mobile viewports wrap metadata fields and validation blocks cleanly; printing respects... (names no code)
  - [claimed] How it was verified: `php tests/run.php` passed with all 115 tests green. (no test named)
  - full per-claim list (20) in the JSON record

**Consistency**

0 error(s), 3 warning(s) between the rationale above and the change.
- WARN: 'How it works' has 6 code references (path::Name or `identifiers`); it is for human readers - describe the steps in plain language, put names in Components
- WARN: 'Main design choice' names no alternative; say which 1-2 real options were possible and why this one won
- WARN: references something that doesn't exist: slip.php

**Commit**

This record is committed with the change it describes; `git log -1 -- docs/decisions/0210-patient-printable-consultation-slip.md` gives the commit.
<!-- verified-facts:end -->
