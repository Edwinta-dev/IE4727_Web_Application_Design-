# Runbook

## XAMPP deployment

Deployment instructions will be filled in during M4. This scaffold reserves
the runbook location and does not yet include application code.

## Demo database credentials

The demo seed in `schema/003_seed.sql` uses `Password123` for every doctor and
patient account. Each `HashPass` value is a genuine bcrypt output produced by
PHP's `password_hash('Password123', PASSWORD_DEFAULT)`; the plaintext password
is never stored in the database.
