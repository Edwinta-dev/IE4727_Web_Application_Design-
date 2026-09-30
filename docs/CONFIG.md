# New-machine configuration

The application defaults to local MariaDB (`127.0.0.1`), database `ie4727db`,
user `root`, and an empty password. Create `clinic-base/config.local.php` only
when local credentials differ; it is ignored and must not contain committed
secrets.

A reset with no mode, or with `--test`, uses the isolated `ie4727db_test`
database. This safe default supports the schema smoke checks without touching
the application database.

`php tests/run.php` always runs against `ie4727db_test`: it sets the
`CLINIC_DB_NAME` environment variable (inherited by any PHP process a test
starts) and rebuilds that database from the seed before the first test.
`clinic-base/lib/config.php` reads `CLINIC_DB_NAME` and throws if a
`config.local.php` defines a different `DB_NAME`, so a hard-coded local
config can never redirect tests onto `ie4727db`. XAMPP never sets the
variable, so the site always uses `ie4727db`.
Use `php tools/db_reset.php --production` only when intentionally resetting the
configured application database.
