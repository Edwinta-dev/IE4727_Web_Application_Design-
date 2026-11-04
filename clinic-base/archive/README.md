# clinic-base/archive

Code that the running application no longer loads. Kept for reference and so its
tests still pass; nothing in `index.php`, `lib/`, `models/`, `partials/` or the
role folders may `require` anything from here.

| File | Archived | Why | Still referenced by |
|---|---|---|---|
| `errors.php` | 2026-11-04 | `install_error_handling()`, `app_log()` and `correlation_id()` are never loaded by any page; the app relies on `lib/config.php` (`display_errors=0`, `log_errors=1`). | `tests/test_errors.php` |

To revive a file, move it back to its old location and fix its `require` paths.

Not archived, despite looking dead: `clinic-base/config.php` (loaded by `tools/db_reset.php`)
and `actions/*.php` (the POST handlers for the login, register, book and appointment forms).
