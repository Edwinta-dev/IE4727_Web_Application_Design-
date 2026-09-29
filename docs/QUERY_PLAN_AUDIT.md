# Query-plan audit

Issue #71 audits the five hot query families against the fixed schema. The
audit is executable with `php tests/run.php test_query_plans` and records the
observed access type and elapsed time in its output.

| Query family | Access path expected | Relevant fixed-schema index |
|---|---|---|
| Login (doctor and patient) | `const`/`ref` | `uq_doctor_user`, `uq_patient_user` |
| Slot availability | `range`/`ref` | `idx_slot_lookup` |
| Patient history | `ref` | `idx_appt_patient_dt` |
| Notification outbox | `index` (bounded newest-first scan) | `PRIMARY` |
| Doctor dashboard | `ref` | `idx_appt_doctor_dt` |

All result collections are bounded. The audited queries use explicit joins,
have no Cartesian joins, and are intended to run below the 250 ms slow-query
budget on the local MariaDB test database. No additive index is justified by
the plans, so `schema/002_migrate.sql` remains an idempotent no-op.
