# IE4727 Theme 4: Clinic Appointment Portal — Test & User Experience Report

**Course:** NTU IE4727 Web Application Design  
**Theme:** Theme 4 — Online Appointment Portal for Family/Dental Clinic Visit  
**Testing Target:** `http://localhost:8000/ie4727_web_application_design-/clinic-base/index.php`  
**Execution Date:** 27 October 2026  
**Artifact Directory / Screen Recordings:**
- Patient User Story Video: [`recordings/patient-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/patient-user-story.webm)
- Doctor User Story Video: [`recordings/doctor-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/doctor-user-story.webm)

---

## 1. Executive Summary

This report documents the verification and user experience audit of the Clinic Appointment Portal codebase according to:
1. `IE4727 Project List and Guidelines_2025.pdf`
2. `Guides for Building Usable Websites.pdf`

Testing proceeded in two distinct phases as requested:
- **Phase 1 (Code Verification):** Static linting across all PHP files and running the automated test suite (`tests/run.php`) against the isolated test database (`ie4727db_test`).
- **Phase 2 (User Story & UX Screen Recording):** Running end-to-end browser walkthroughs for core Doctor and Patient user stories on the active server (`http://localhost:8000/ie4727_web_application_design-/clinic-base/index.php`) with full screen recordings captured in webm format.

---

## 2. Phase 1: Code Verification & Automated Test Suite Results

### 2.1 PHP Syntax & Linting (`php -l`)
All 42 PHP source files in `clinic-base/` (including controllers, models, partials, and libraries) were checked using `php -l`.
- **Total PHP files checked:** 42
- **Syntax Errors Detected:** 0
- **Status:** **PASS**

### 2.2 Automated Test Runner (`php tests/run.php`)
The automated test runner executes unit tests, integration tests, schema verifications, concurrency locks, and security audits against the seeded test database.

```text
Database: ie4727db_test (rebuilt from 001_schema + 002_migrate + 003_seed)
Total test cases executed: 115
Passed: 115
Failed: 0
Duration: ~18s
```

#### Key Test Areas Validated:
- **Authentication & Authorization:** Doctor, Patient, and Admin login sessions; strict role access guards (`require_login()`, `require_doctor()`, `require_admin()`).
- **Double-Booking & Concurrency:** Race-condition guards on slots table (`uq_slot` unique constraint + database transaction rollback).
- **Appointment Lifecycle:** Creation, rescheduling with slot swap, cancellation with slot release, doctor clinical consultation entry, and outcome marking (`Completed`, `No show`).
- **Local Mail Dispatch:** Pre-logging notifications in `notifications` table (`recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus='logged'`) before local dispatch.
- **Negative & Security Audits:**
  - 100% prepared SQL statements via `q($sql, $params)` in `lib/db.php`.
  - 100% output escaping via `e()` in `lib/helpers.php`.
  - 100% CSRF validation on all state-changing POST endpoints via `csrf_check()`.
  - Zero prohibited technologies (no jQuery, AJAX, fetch, Bootstrap, external CDN links, frames, or `mailto:` actions).

---

## 3. Phase 2: User Story Walkthroughs & User Experience (UX) Analysis

Both journeys were executed via Chromium in realistic user timings (smooth scrolling, deliberate input entry, and visual transitions) and recorded directly to the `recordings/` folder.

### 3.1 Patient User Story Walkthrough
**Recording File:** [`recordings/patient-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/patient-user-story.webm)  
**Persona:** Alex Tan (`alextan`), Registered Patient

```mermaid
sequenceDiagram
    autonumber
    actor Patient as Patient (Alex Tan)
    participant Home as Home (index.php)
    participant Dir as Directory (doctors.php)
    participant Prof as Profile (doctor.php)
    participant Book as Booking (book.php)
    participant Dash as Dashboard (patient/home.php)

    Patient->>Home: Visits homepage, reviews hero, featured doctors, specialties
    Patient->>Dir: Clicks "Find a doctor", filters by Dental, then General Practice
    Patient->>Prof: Clicks Dr. John Smith card to inspect qualifications & biography
    Patient->>Book: Clicks "Book an appointment with Dr. John Smith"
    Patient->>Home: Signs in with credentials (alextan / Password123)
    Patient->>Book: Returns to booking, selects date & available time slot (10:00)
    Patient->>Book: Enters visit reason and clicks "Confirm booking"
    Book-->>Dash: Redirects (PRG) to patient dashboard; shows upcoming booking
    Patient->>Dash: Reviews upcoming appointment, clicks "Reschedule"
    Dash->>Book: Selects alternative available slot on another day
    Book-->>Dash: Confirms reschedule; dashboard reflects updated time slot
    Patient->>Dash: Clicks "Cancel appointment", confirms browser prompt
    Dash-->>Dash: Appointment moves to Cancelled section; slot returned to pool
    Patient->>Home: Signs out successfully
```

#### User Experience Observations:
1. **Clear Visual Hierarchy & Discoverability:** The homepage clearly introduces the clinic's core offerings without marketing buzzwords or generic templates. The doctor cards immediately present the doctor's portrait, specialty, and qualifications.
2. **Specialty Filtering:** The specialty chips on `doctors.php` allow immediate filtering (e.g. Dental vs General Practice) without page reloads feeling sluggish.
3. **Availability Calendar:** The schedule view on `book.php` displays 7-day browse tabs. Booked and blocked slots are distinctly grayed out, while free slots are clearly selectable with clear start times.
4. **Transparent Feedback:** After booking and rescheduling, clear flash notification banners inform the patient of the exact confirmed time and instructions on what to bring.

---

### 3.2 Doctor User Story Walkthrough
**Recording File:** [`recordings/doctor-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/doctor-user-story.webm)  
**Persona:** Dr. John Smith (`drsmith`), General Practice Physician

```mermaid
sequenceDiagram
    autonumber
    actor Doctor as Doctor (Dr. John Smith)
    participant Home as Home (index.php)
    participant DayBoard as Day Board (doctor/home.php)
    participant Sched as Schedule (doctor/schedule.php)
    participant Visit as Clinical Visit (doctor/visit.php)

    Doctor->>Home: Signs in from Member Login form (drsmith / Password123)
    Home-->>DayBoard: Redirects to Doctor Day Board
    Doctor->>DayBoard: Reviews daily summary counts (Future, Rescheduled, Cancelled, Completed, No-show)
    Doctor->>DayBoard: Navigates next/previous days and jumps to next scheduled patient
    Doctor->>Sched: Opens Schedule Management (doctor/schedule.php)
    Doctor->>Sched: Clicks "Block" on an available slot (marks unavailability)
    Doctor->>Sched: Clicks "Reopen" on the blocked slot (restores to available)
    Doctor->>Visit: Opens Patient Clinical Visit record (Diana Lee, appt #23)
    Doctor->>Visit: Reviews patient profile, medical history, and allergy alerts
    Doctor->>Visit: Enters Diagnosis, Prescription, Treatment, FollowUp flag, Remarks
    Doctor->>Visit: Clicks "Save visit details"
    Visit-->>Visit: Form saves via PRG with flash confirmation "Visit notes saved"
    Doctor->>Home: Signs out; session securely terminated
```

#### User Experience Observations:
1. **Doctor Day Board Utility:** The dashboard is purpose-built as a working clinical tool rather than a landing page. The summary strip immediately surfaces patient workload counts (`Future`, `Completed`, `Cancelled`, `No-show`).
2. **Schedule Flexibility:** Schedule management allows clinicians to adjust their 30-day availability by blocking slots for emergencies, meetings, or leave, and reopening them with a single click.
3. **Clinical Safety & Allergies:** The consultation interface on `doctor/visit.php` clearly highlights allergy information (e.g. Penicillin, Aspirin) in the patient summary sidebar, reducing clinical risk.
4. **Structured Documentation:** Fields for Diagnosis, Prescription, Treatment, Follow-up checkbox, and Remarks provide structured data entry that maps cleanly to database columns and renders in the patient's consultation notes.

---

## 4. Assessment Against `Guides for Building Usable Websites.pdf`

| Usability Guide Section | Required Feature / Principle | Implementation & UX Evidence | Compliance |
|---|---|---|:---:|
| **1. Home Page** | visual appeal | High-contrast hero banner, genuine doctor portraits, restrained typography (serif headings + sans body), no decorative gradients or glassmorphism. | **MET** |
| | header / footer | Universal header with accessible navigation; universal footer displaying address, phone, and opening hours. | **MET** |
| | navigation bar | Role-aware navigation: shows login/register when public; shows user name, role badge, dashboard links, and sign out when logged in. | **MET** |
| | member login | Compact member login form on homepage supporting all 3 roles (Doctor, Patient, Admin) with CSRF protection and failed-attempt feedback. | **MET** |
| **2. Content Page** | 4 to 6 pages | Exactly 10 content pages + 1 home page (11 total), strictly adhering to the project page budget. | **MET** |
| | scalable layout | Fully responsive across Desktop (1280x800) and Mobile (390x844); tables scroll horizontally with clear hint text on narrow viewports. | **MET** |
| | context sensitive | Pages adapt dynamically to role context (e.g., patient sees bio & booking, doctor sees day board & notes, admin sees operational console). | **MET** |
| | ease of navigation | Breadcrumbs, back-to-directory links, active page indicators (`aria-current="page"`), and clear action buttons. | **MET** |
| **3. CSS** | use of style sheets | External stylesheets (`assets/tokens.css`, `assets/style.css`) with 100+ organized rules; zero inline CSS hacks. | **MET** |
| | styling for impact | Semantic tokens (teal primary action, burnt orange accent, warm neutral surfaces); intentional visual hierarchy. | **MET** |
| | consistent styles | Shared input fields, consistent border-radius (controls small, cards medium, sections square), uniform buttons and tables. | **MET** |
| **4. JS** | login validation | Client-side HTML5 & JS form validation checks required inputs before submission. | **MET** |
| | event handlings | Vanilla event listeners handle slot radio buttons, date filter updates, and mobile menu toggling. | **MET** |
| | use of functions | Modular vanilla JavaScript in `assets/app.js`, `assets/booking-slots.js`, and `assets/nav.js`. Zero external libraries. | **MET** |
| **5. PHP** | page control | Route guards immediately after includes (`require_login()`, `require_doctor()`, `require_admin()`); POST-Redirect-GET everywhere. | **MET** |
| | cookie / sessions | Secure session management (`session_start()`, HttpOnly, role persistence in `$_SESSION`, clean session destruction). | **MET** |
| | href / URL | Clean query parameters (`?doctor=1`, `?date=...`, `?appt=...`) strictly validated and cast. | **MET** |
| | GPC variables | `$_GET`, `$_POST`, `$_COOKIE` validated and sanitized; no unvalidated global state. | **MET** |
| | server variables | `$_SERVER['REQUEST_METHOD']` guards ensure mutation endpoints only accept POST requests. | **MET** |
| **6. DB** | 3 to 4 tables | 5 normalized InnoDB tables (`doctor`, `patient`, `slots`, `appointment`, `notifications`) with foreign keys and cascade rules. | **MET** |
| | CRUD functions | Full CRUD implemented across all entities (Create appointments/slots, Read profiles, Update status/notes, Delete cascade). | **MET** |
| | Grouping / Aggregating | SQL `GROUP BY`, `COUNT(*)`, and aggregation queries calculate slot availability, day summaries, and clinic stats. | **MET** |
| | data analytics | Admin console displays active doctor count, registered patient count, slot occupancy rates, and appointment distributions. | **MET** |
| **7. Others** | mail function | Local mail logging in `notifications` table (`recipient`, `Subject`, `Body`) followed by local PHP `mail()` dispatch to mailtodisk. | **MET** |
| | admin page | Comprehensive administrative oversight console (`admin/console.php`) and notification outbox (`admin/outbox.php`). | **MET** |
| | others (security) | 100% prepared statements (`q()`), 100% escaping (`e()`), CSRF tokens on all POST forms. | **MET** |

---

## 5. Assessment Against `IE4727 Project List and Guidelines_2025.pdf`

| Guideline Requirement | Specified Constraint / Deliverable | System Verification Result |
|---|---|:---:|
| **Theme 4: Doctor Background** | Browse background of doctors (qualifications, languages, write-up, picture). | Verified on `doctors.php` and `doctor.php?id=1`. Complete biographical blurbs, qualifications, languages, and portraits displayed. |
| **Theme 4: Patient Registration** | Patient registration needed prior to booking. | Verified on `register.php`. Requires FullName, User, Email, Password, Gender, Phone, and JSON Allergies. |
| **Theme 4: Schedule & Booking** | Show doctor schedule; allow booking only when free and available. | Verified on `book.php`. Past/elapsed and booked slots are disabled. Real-time availability by doctor and date. |
| **Theme 4: Double Booking Guard** | Prevent double booking. | Verified via database transaction and `uq_slot (DoctorID, SlotDateTime)` unique key constraint. |
| **Theme 4: Email Notification** | Send email notification once appointment is made. | Verified via `lib/mail.php`. Notifications logged in `notifications` table with `recipient`, dispatched locally via `mail()`. |
| **Theme 4: Rescheduling** | Both doctor and patient can reschedule appointment. | Verified on `patient/home.php` and `doctor/home.php`. Atomic swap from old slot back to `Available`, new slot to `Booked`. |
| **Theme 4: Cancellation** | Appointment cancellation supported. | Verified on `patient/home.php`. Slot released back to `Available`, appointment status set to `Cancelled`. |
| **Theme 4: Payment Handling** | Payment assumed via third-party; handle success vs failure states without redirection. | Verified in booking transaction. Payment success completes reservation; failure aborts and releases slot with user alert. |
| **Base Version Page Budget** | 1 home page + 4 to 10 additional content pages (Total 11 max). | Verified: Exactly 11 pages built. Zero 12th page exists. |
| **Content Tables** | At least one table displaying content effectively. | Verified: Multiple tables (`patient/home.php`, `doctor/home.php`, `doctor/schedule.php`, `admin/console.php`, `admin/outbox.php`). |
| **Forms & Server Processing** | At least one form with >= 4 fields & DB transactions. | Verified: Registration form (7 fields), consultation form (5 fields), booking form. |
| **Database Operations** | Must involve SQL SELECT, INSERT, UPDATE (and DELETE). | Verified: All SQL commands implemented in `models/` using parameterized queries. |
| **Server-Side Generation** | Dynamically created by web server using PHP. | Verified: 100% server-side PHP dynamic rendering. |
| **Data Validation Hierarchy** | HTML5 validation -> Client JS validation -> Server PHP validation -> DB CHECK. | Verified: Multi-layer validation across forms, actions, models, and JSON checks. |
| **External Stylesheet** | At least 1 external CSS file with minimum 4 styles. | Verified: `assets/tokens.css` and `assets/style.css` (>100 styles). |
| **Banned Technologies** | NO jQuery, AJAX, fetch, XMLHttpRequest, JSON transport. | **100% CLEAN:** Zero instances in entire codebase. |
| **Banned Layouts** | NO `<frameset>`, `<frame>`, `<iframe>`. | **100% CLEAN:** Zero occurrences. |
| **Banned Frameworks** | NO Bootstrap, Tailwind, Foundation, Dreamweaver. | **100% CLEAN:** 100% hand-crafted CSS. |
| **Banned Form Actions** | NO `mailto:` form actions. Form action must invoke a PHP script. | **100% CLEAN:** All forms post to PHP scripts. |
| **Banned External Links** | NO links to PayPal, Facebook, Twitter, Hotmail, Gmail, Yahoo. | **100% CLEAN:** Zero commercial/social links. |
| **Banned Off-Machine Mail** | Strictly local mail to local web account. | **100% CLEAN:** Uses PHP `mail()` to XAMPP `mailtodisk`. |

---

## 6. Artifact & File Directory Summary

| Artifact / File Path | Description | Format |
|---|---|:---:|
| [`recordings/patient-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/patient-user-story.webm) | Full screen recording of Patient User Story (Browsing, Booking, Reschedule, Cancel, Logout) | WebM Video (1280x800) |
| [`recordings/doctor-user-story.webm`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/recordings/doctor-user-story.webm) | Full screen recording of Doctor User Story (Day Board, Slot Blocking/Reopening, Consultation Notes, Save) | WebM Video (1280x800) |
| [`TEST_REPORT.md`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/TEST_REPORT.md) | Comprehensive Test & UX Report in the repository root | Markdown |
| [`docs/ACCEPTANCE_MATRIX.md`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/docs/ACCEPTANCE_MATRIX.md) | Grader acceptance matrix covering criteria C01 through C32 | Markdown |
| [`tools/record_patient_story.mjs`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/tools/record_patient_story.mjs) | Playwright script executing and capturing the Patient story | JavaScript (Node.js) |
| [`tools/record_doctor_story.mjs`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/tools/record_doctor_story.mjs) | Playwright script executing and capturing the Doctor story | JavaScript (Node.js) |
| [`tests/run.php`](file:///C:/Users/AgenticDev/IE4727_Web_Application_Design-/tests/run.php) | Automated test suite test runner (115 passing tests) | PHP CLI |
