# Architecture decisions

1. The project adopts the existing `ie4727db` dump: credentials remain in
   separate `doctor` and `patient` tables; there is no `users` table.
2. Times remain single `DATETIME` columns, `SlotDateTime` and
   `appointmentDateTime`. Queries derive date and time with `DATE()` and
   `TIME()`; the columns are not migrated to split fields.
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
