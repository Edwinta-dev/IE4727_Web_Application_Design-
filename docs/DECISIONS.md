# Architecture decisions

1. The project adopts the existing `ie4727db` dump: credentials remain in
   separate `doctor` and `patient` tables; there is no `users` table.
2. Times remain single `DATETIME` columns, `SlotDateTime` and
   `appointmentDateTime`. Queries derive date and time with `DATE()` and
   `TIME()`; the columns are not migrated to split fields.
3. Appointment availability uses materialised rows in `slots`.
4. Administration is config-defined with `ADMIN_USER` and `ADMIN_HASH`; there
   is no admin table.
5. `002_migrate.sql` is additive-only so existing rows survive migration.
