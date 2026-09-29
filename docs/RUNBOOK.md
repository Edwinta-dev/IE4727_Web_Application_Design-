# Runbook

## XAMPP deployment

## MariaDB dump and restore

Use the same database name and `utf8mb4` connection settings for both commands.
The dump includes InnoDB foreign keys, unique keys, checks, enums, and seed data;
do not use `--skip-opt` or disable foreign-key checks during restore.

```bash
mysqldump --default-character-set=utf8mb4 --single-transaction --routines --triggers ie4727db > backup/ie4727db.sql
mysql --default-character-set=utf8mb4 ie4727db < backup/ie4727db.sql
```

For a clean isolated verification, run `php tools/db_reset.php --test` twice.
The second run recreates the schema and applies the intentionally idempotent
`schema/002_migrate.sql`; it must not add columns or keys already present in
`schema/001_schema.sql`.
