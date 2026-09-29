# Clinic-base release boundary

This document freezes the graded `clinic-base/` baseline for Theme 4. Changes
inside that directory are limited to defect fixes required by the base
acceptance matrix. Optional enhancements belong outside `clinic-base/` and
must not alter the baseline page budget, routes, schema vocabulary, or
deployment assumptions without review.

## Frozen baseline

- The baseline is PHP, HTML5, CSS3, and local vanilla JavaScript only.
- There is no AJAX/fetch/XMLHttpRequest transport, JSON-as-transport, library,
  framework, Composer package, `vendor/` directory, CDN, iframe, or external
  mail service.
- The content-page budget is exactly the eleven entries in `docs/PAGES.md`.
  The application uses ordinary XAMPP Apache routes; no custom deployment
  route is required.
- Database changes must preserve the fixed PascalCase MariaDB schema, single
  `DATETIME` values, JSON `Allergies`, exact enum values, and `recipient`.
  Unreviewed schema changes are outside the baseline.
- Mail is local-only: `send_mail()` logs the notification row before trying
  PHP `mail()`, and XAMPP `mailtodisk` is the supported delivery destination.

## Clean deployment handoff

Deploy a clean checkout beneath XAMPP `htdocs`, configure the ignored
`clinic-base/config.local.php` when local credentials differ, and use the
default XAMPP Apache/MariaDB services. From the repository root, run:

```text
php tools/db_reset.php --production
php tests/run.php
powershell -ExecutionPolicy Bypass -File tools/verify_clean.ps1
```

The release handoff is complete only when the working tree is clean, all
automated checks pass, and no local secrets, mailbox files, or generated
personal data are committed. The supervisor creates the grading tag/commit;
this repository change does not create or mutate Git refs.

