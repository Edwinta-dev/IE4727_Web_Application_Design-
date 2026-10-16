# Architecture decisions

1. The project adopts the existing `ie4727db` dump: credentials remain in
   separate `doctor` and `patient` credential tables; administration is config-defined.
2. Times remain single `DATETIME` columns, `SlotDateTime` and
   `appointmentDateTime`. Queries derive date and time with `DATE()` and
   `TIME()` from those single fields.
3. Appointment availability uses materialised rows in `slots`.
4. Administration is config-defined with `ADMIN_USER` and `ADMIN_HASH`; there
   is no admin table.
5. The schema is fixed and adopted from the cleaned `ie4727db` dump; existing
   columns and names are not redesigned.
6. `002_migrate.sql` is additive-only. The adopted dump already contains the
   later milestone fields and keys, so the migration is an intentional no-op
   that preserves existing data and avoids duplicate `ADD` errors.
7. Account removal uses a hard `DELETE`. Removing a doctor therefore cascades
   to their slots and appointments through the schema foreign keys; there is no
   doctor `active` flag or soft-delete state.
8. The M4 escaping audit permits helper-generated output such as
   `<?= csrf_field() ?>` and `flash_render()`'s static wrapper because they do
   not echo a raw variable; all dynamic page values use `e()` on the output
   line. SQL is kept in `models/`; authentication, validation, and mail
   helpers call model functions instead of embedding queries.
9. The live clinic board uses Server-Sent Events rather than WebSockets because
   the enhancement is one-way and PHP can provide the stream without requiring
   a second server on a machine we do not own. The stream is capped at 45
   seconds so the browser can reconnect, and it releases the session lock before
   polling.
10. CSRF tokens are required on state-changing (POST) forms only. Read-only GET
    filter forms carry no token, because it would leak into the URL; AGENTS.md
    rule 3 says so explicitly since #196.
11. Login throttling counts failures per client and login name (5), with a
    looser per-client cap (20) so spraying many names is still slowed. A
    broken throttle store fails open with a log line rather than refusing every
    login (#194).
12. Booking resolves a specialty to doctor ids in PHP (`all_doctors()` applies
    the legacy-name aliases) and queries slots by doctor id, so the alias table
    lives in one place (#196). A reschedule stays with its doctor and specialty.
13. The footer derives opening hours from a per doctor-day summary query and
    shows a fixed line if the database is unavailable, so no page depends on the
    database just to render its footer (#197).

Per-issue decision records (rationale, traps, verified facts) live in
[`docs/decisions/`](decisions/README.md).

