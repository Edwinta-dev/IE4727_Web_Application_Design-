# Architecture decisions

1. The project adopts the existing `ie4727db` dump: credentials remain in
   separate `doctor` and `patient` credential tables; administration is config-defined.
2. Times remain single `DATETIME` columns, `SlotDateTime` and
   `appointmentDateTime`. Queries derive date and time with `DATE()` and
   `TIME()` from those single fields.
3. Appointment availability uses materialised rows in `slots`.
4. Administration is config-defined with `ADMIN_USER` and `ADMIN_HASH`.
5. `002_migrate.sql` is an idempotent no-op because the cleaned schema already
   contains the fields and keys previously planned for that migration.
