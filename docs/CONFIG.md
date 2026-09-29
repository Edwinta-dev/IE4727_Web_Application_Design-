# New-machine configuration

The application defaults to local MariaDB (`127.0.0.1`), database `ie4727db`,
user `root`, and an empty password. Create `clinic-base/config.local.php` only
when local credentials differ; it is ignored and must not contain committed
secrets.

A reset with no mode, or with `--test`, uses the isolated `ie4727db_test`
database. This safe default supports the schema smoke checks without touching
the application database.
Use `php tools/db_reset.php --production` only when intentionally resetting the
configured application database.
