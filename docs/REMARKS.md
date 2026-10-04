# Reviewing appointment remarks

The fixed schema has one `appointment.Remarks` TEXT column for both the
patient's booking reason and the doctor's visit remarks. Overwriting it with
either value loses the other. Issue #111 therefore stores a lossless versioned
record; inspection must decode it, not simplify or bulk-rewrite storage.

The exact prefix is the byte `0x1E` (ASCII record separator), followed by
`clinic-remarks:1:`. The payload is compact JSON with keys in this exact order:
`{"reason":"...","doctor_remarks":"...","legacy":null}`. The first two
values are strings; `legacy` is null or a string. The encoder uses unescaped
Unicode and slashes, escapes newlines as JSON requires, and limits the whole
record to TEXT's 65,535 bytes. This is internal storage, not JSON transport.

`models/visit_remarks.php` is the single encoder/decoder. It accepts only the
exact prefix, key order/types and canonical JSON produced by the encoder
(re-encoding must equal the payload). Missing keys, invalid JSON, alternate
whitespace/order/escaping, unknown versions and a prefix without `0x1E` are
treated as plain text. No malformed text is discarded or partially decoded.
For unencoded text, `Completed` means author unknown: the complete text is
returned in `legacy`, with empty reason and doctor remarks. Every other status
returns the text as reason with empty doctor remarks and null legacy. Null or
empty storage returns empty fields and null legacy. The decoder cannot recover
provenance or values already overwritten before #111.

Booking encodes the reason. Saving/editing visit notes decodes the current row,
replaces only doctor remarks, and re-encodes with reason and legacy intact.
Rescheduling leaves Remarks intact. Do not add columns or rewrite old records.

## Existing readers

- `doctor/home.php`: decoded reason in the appointment board; an author-unknown
  marker for completed legacy text. Full notes belong to the visit page.
- `doctor/visit.php`: separately labelled patient reason, editable doctor
  remarks and author-unknown earlier text; prior-visit history uses the same decoder.
- `patient/home.php`: owned completed-visit notes label reason, doctor remarks
  and author-unknown earlier text separately.
- `admin/console.php` and `admin/outbox.php`: do not render the Remarks column.
  There is no existing appointment remarks export subsystem. Notifications
  are delivery records, not a raw Remarks export.
- Visit-state fixture snapshots already use this decoder. SQL dumps/reset
  utilities intentionally preserve physical storage rather than render a report.

## Read-only local inspection

An authorized owner/reviewer with local shell and database read access can run
from the repository root, selecting one known appointment ID:

```text
php tools/inspect_remarks.php --database=ie4727db_test --appointment=1
```

The database argument is required. Use `ie4727db` only for an authorized
read-only review of an owner's actual record. Executable tests and synthetic
fixtures must use `ie4727db_test`. If `CLINIC_DB_NAME` is already set, the command
refuses a different selection; local config and the resolved server database
must also match. Invalid options fail before connection. It uses existing local
config credentials; do not put passwords on the command line.

The CLI-only command performs a server-enforced read-only transaction and
selects only Remarks and Status for the requested ID through `q()` in a model.
It prints database/appointment identifiers and three labelled fields; no names,
contact details, diagnosis, treatment, prescription or notifications. Empty
fields display `(empty)`, newlines remain on indented lines, and other ASCII
control bytes display as `\xHH` (including a malformed prefix's `\x1E`) to avoid
terminal control effects. This display escaping does not change stored text.
Missing rows and connection/config failures return nonzero without clinical
data. There is no write/reset/mail operation, HTTP report or bulk export.
Shell access is the authorization boundary: do not expose the command through
a web wrapper. Review output contains sensitive remarks; do not publish or log
it. Tests use synthetic text and report checks rather than clinical contents.

Direct SQL, phpMyAdmin and raw SQL/CSV exports of the physical column still
show the versioned storage representation, including the control byte and JSON.
That is a fixed-schema storage limitation, not a UI failure. Use the decoded
app views or this inspection command for readable review; plaintext raw
storage is neither promised nor safe when both values must survive edits.
