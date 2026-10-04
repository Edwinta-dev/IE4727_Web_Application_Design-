# Demo data separation (#131)

## Read-only reconciliation, 4 October 2026

The supplied finding is UI_Defects.pdf p. 5, new finding 12. That round-2 PDF
is absent from this checkout; `UIPROBLEMS/Open/clinicissues.pdf` is an older
report and has not been substituted. Baseline: clean `0ab1bd6`; #120 isolation
and #124 relative-time chronology are already implemented. The preserved
visit/history work and audit report were inspected and not overwritten.
Carry-over #114 is separate; this change does not claim it is resolved.

An explicit live **READ ONLY transaction** counted 7 doctors, 11 patients,
3,640 slots, 28 appointments and 12 notifications. Five elapsed appointments
remain `Future`. There was no live deletion, reset, attendance update or mail.

Three account rows (one doctor, two patients) correlate with the exact old
`eb9a791:tests/ui_register.mjs` fixture run: the complete shared random run
suffix triple, generated username/email relationship, every submitted profile
field, and bcrypt verification against that fixture's password all match.
The primary IDs and full-row SHA-256 fingerprints are recorded privately.
This agrees with #120's historical contamination report; classification was
not based on display names. Dependency closure found **zero slots, appointments
or notifications**, including notices addressed to either fixture email.
The bounded proposed cleanup is those three exact rows only.

Eight accounts match a freshly rebuilt seed by **primary ID and every field**.
They are seed-identical, not cleanup targets. The intended fresh seed contains
5 doctors and 8 patients, 27 appointments and 2 notifications. Changed seed
profiles and unproven rows are not silently treated as untouched seed data.
The remaining seven account rows are preserved as ambiguous; that group includes
the two owner-reported manual accounts. No owner creation ledger was available
to distinguish their IDs conclusively from changed seed profiles. All other
rows remain preserved, irrespective of synthetic-looking names or elapsed dates.

Private evidence lives under gitignored `UIPROBLEMS/private/`: the initial and
classified inventories, `issue131-provenance.json` (IDs, fingerprints, evidence),
`issue131-source-preview.json` (bounded references/counts/token), and the local
read-only source comparison script. Never commit these artifacts, raw backups,
credentials, usernames, emails or medical records. Public reports use counts.
Absence of evidence means **ambiguous**, never permission to delete.

## Isolated fresh demo

Stop other tests first. All resets and browser fixtures share `ie4727db_test`;
run them serially. Do not change `config.local.php` to point the live app at a
demo database. A local override of DB_NAME must agree with the explicit test
environment or config refuses before connection.

```powershell
$env:CLINIC_DB_NAME = 'ie4727db_test'
php tools/db_reset.php --test
node --test tools/ui/isolation.test.mjs
node tools/ui/outcome-seed.test.mjs
node tests/ui_register.mjs
Remove-Item Env:CLINIC_DB_NAME
```

The browser launchers verify effective PHP config before launching, then verify
the owned child server's resolved database header at
`127.0.0.1:8123/clinic-base/`. Local delivery is disabled. Never reuse port 8000
or an external server. The outcome regression rebuilds twice, authenticates a
doctor without JavaScript and checks the relative-time seed. Registration uses
exact per-run fixtures and cleans them up. See `tools/ui/README.md`.

Seed SQL uses one Asia/Singapore `@seed_now`, with rolling slots and consistent
booking lead times; generated calendar dates change with the day. Reproducibility
means the same relationships, counts and temporal rules, not identical timestamps.
Existing seed regressions prove this across two resets. Past pending visits are
intentional attendance examples. A stale demonstration is addressed by rebuilding
the isolated demo; a real elapsed `Future` appointment still requires the doctor's
attendance decision. This tooling never converts it to `Completed` or `No show`.

`tools/db_reset.php` now supports **only** the exact test database, including when
called without arguments. `--production`, unknown arguments and other `_test`
names refuse before connecting. The suite and browser callers continue using
that entry point, with no live fallback. Raw `001_schema.sql` and `003_seed.sql`
remain destructive imports containing historical `USE ie4727db` statements:
never import them directly for a demo. The guarded reset strips those statements.
There is deliberately no unattended live reset command.

## Inventory, review, backup, apply and restore

`tools/reconcile_data.php` is CLI-only; JSON here is a local artifact, not web
transport. `inventory` and `preview` use read-only transactions. Each inventory
entry has an opaque `table:ID`, row fingerprint and FK references, with no stored
identity or clinical text. Supply a private manifest to assign proven categories:

```json
{
  "patient:12345": {
    "category": "synthetic",
    "sha256": "full-row fingerprint from the reviewed inventory",
    "evidence": "exact fixture/run provenance and recorded primary ID"
  }
}
```

Supported categories are `synthetic`, `demo` and `manual`; missing, changed or
incomplete evidence becomes `ambiguous`. A manifest is a review record, not a
name-based discovery rule. Review its evidence against the actual fixture source
and creation/run records. Placeholder IDs below are illustrative, not live targets.

```text
php tools/reconcile_data.php inventory --database=ie4727db_test
php tools/reconcile_data.php preview --database=ie4727db_test --manifest=UIPROBLEMS/private/review.json --targets=patient:12345
php -d disable_functions=mail tools/reconcile_data.php apply --database=ie4727db_test --manifest=UIPROBLEMS/private/review.json --targets=patient:12345 --token=PREVIEW_TOKEN --backup=UIPROBLEMS/private/cleanup-backup.json
php -d disable_functions=mail tools/reconcile_data.php restore --database=ie4727db_test --backup=UIPROBLEMS/private/cleanup-backup.json --token=PREVIEW_TOKEN
```

Live inventory/preview can explicitly select `--database=ie4727db`; apply and
restore categorically refuse it before configuration/connection. Never run apply
on the live database in this issue. No implicit target is accepted.

Preview expands all doctor/patient cascade dependencies, slot links, appointment
notices and account-email-linked notices (including unlinked notices). Removing
an appointment also requires review of its slot and every other appointment
using that slot. Any manual, seed or unproven dependency blocks the whole plan.
Missing targets fail. There are at most 100 explicit roots; all dependencies are
listed before approval. No account is selected by name or substring.

Apply locks a current snapshot, repeats provenance/dependency checks, and requires
the preview token to match every selected row. New dependencies or changed row
content invalidate approval. It writes the exact selected full rows to a new,
exclusive private backup **before deleting anything**, inside the existing
gitignored UIPROBLEMS directory. Do not put backups on shared/public storage;
restrict directory access to the owner. It deletes children before parents in a
single transaction. Unrelated rows and statuses remain untouched.

Restore checks the backup token, explicit test target and original IDs, then
inserts parents before children in a transaction. Conflicting existing IDs or
unique keys fail and roll back; it never overwrites a record. Keep the backup until
the restoration and preserved-row comparison are verified. The backup contains
personal data: retain it locally only as long as needed. Before any future live
owner action, take an independent full database backup with the owner's existing
backup procedure and prove restoration into a test copy. This issue supplies no
live apply/reset route; actual live cleanup remains **pending owner action**.

## Validation

The regression creates recorded synthetic IDs in the verified test DB, exercises
all five tables and email-only notifications, proves preview writes nothing,
blocks manual/shared or changed dependencies, deletes only the approved closure,
compares every unrelated row byte-for-byte and restores exact rows/FKs. A separate
CLI round trip proves the documented exclusive backup/apply/restore route.
Refusal tests exercise argument/config paths, never a live connection.

No application page, stylesheet, schema or notification delivery code changed.
No page-specific geometry audit is needed. Relevant existing browser captures
are the authenticated outcome demo and registration regressions, at 1280x800
and 390x844, on the verified test server. Full command results are recorded in
the issue result and private suite log; historical audit counts remain historical.

Commands executed serially after resolving the obsolete production-mode tool
assertion: focused reconciliation regression (3 passed), all touched PHP lints,
`node --test tools/ui/isolation.test.mjs` (7 passed),
`node tools/ui/outcome-seed.test.mjs` (OK), `node tests/ui_register.mjs` (PASS),
and `php tests/run.php` (85 passed, 0 failed). PHP's existing session/header
warnings remain non-fatal. An accidentally overlapped suite/browser attempt was
discarded and both commands were rerun serially; that failed attempt is not
validation evidence.

The actual three source-correlated rows were copied to a fresh verified test
database using their recorded IDs, then previewed, deleted with a private backup
and restored through the documented CLI route. Every unrelated row's fingerprint
matched the baseline after cleanup; every copied row matched after restore.
The committed regression also preserves an explicit manual sentinel whose name
looks like a fixture. Stale tokens, existing backup paths and repeated restore
ID conflicts refuse without deleting or overwriting records.

Final read-only live fingerprint comparison: **all 3,698 rows unchanged**.
Classification: 3 source-correlated synthetic accounts, 8 seed-identical accounts,
3,687 preserved ambiguous rows; five elapsed Future visits unchanged. Private
references permit owner review without exposing identities in this report.

Opened and inspected: `UIPROBLEMS/after/outcome-seed-actionable/doctor-home-1280.png`
and `doctor-home-390.png`, their `outcome-seed-outcomes/` counterparts, and the
doctor/patient-js captures at both widths in `UIPROBLEMS/after/issue120-after/`.
The authenticated board regression measures 0px viewport overflow at both widths;
the phone table has its existing horizontal scrolling region. Capture labels
describe demo state transitions, not changes to page styling. No new page or
visual redesign was part of this issue.
