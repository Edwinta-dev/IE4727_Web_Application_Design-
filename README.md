# Clinic Portal — agent development kit

Five files. IE4727 Theme 4, clinic appointment portal, built by an autonomous agent
against a fixed backlog — **adapted to the existing `ie4727db` database**, not a
greenfield design.

| File | What it is |
|---|---|
| `AGENTS.md` | **The constitution.** Commit to the repo root first. Every issue defers to it. Now carries the real schema and column names. |
| `ISSUES.md` | All 42 issues in one readable file — review before firing the script. |
| `create-issues.sh` | Creates the labels, milestones and issues via the `gh` CLI. |
| `ie4727db.sql` | Your current dump, committed as `schema/001_schema.sql` by issue #4. |
| `README.md` | This file. |

## What changed to fit your schema

The backlog originally assumed it could dictate the database. Your dump exists and the
app is partly built, so the issues now **adopt what you have** and only add to it:

- **No `users` table** — auth checks the `doctor` and `patient` tables; admin is
  config-defined. (Issues #9, #17.)
- **Single `DATETIME` columns** (`SlotDateTime`, `appointmentDateTime`) — queries derive
  date/time with `DATE()`/`TIME()`; no split-column migration. (Issues #11, #12, #18.)
- **PascalCase names, and `receipient` is misspelled** — agents must match exactly;
  the column reference is pinned in `AGENTS.md`. (Issue #13 and throughout.)
- **`Allergies` is JSON**, there is **no DOB**, and **no `active` flag** — deleting a
  doctor is a hard cascade, which is what "clean up accounts" means here. (Issues #10,
  #24, #31, #32.)
- **One additive migration** — `schema/002_migrate.sql` (issue #4) adds a `uq_slot`
  unique key, `appointment.slotID`, `appointment.updatedAt`, `notifications.deliveryStatus`,
  and three optional `doctor` content columns (`Specialty`/`Qualifications`/`Languages`).
  Nothing is dropped or renamed, so your existing data survives.
- **Page issues reconcile existing files** — each M3 issue tells the agent to refactor a
  file that already exists rather than replace it.

> **Note:** you mentioned the HTML was already built, but only the SQL reached me. The
> page issues (#22–#33) therefore describe the *target* each page must reach; an agent
> should diff that against the existing PHP and close the gap. If you want the issues
> tightened against the real markup, share the `clinic-base/` files and I'll refine them.

## Use

```bash
gh repo create <you>/clinic-portal --private --clone
cd clinic-portal
mkdir -p schema
cp ../AGENTS.md .
cp ../ie4727db.sql schema/001_schema.sql
git add . && git commit -m "chore: schema + agent constitution" && git push

../create-issues.sh <you>/clinic-portal
```

Run the script on a repo with **no existing issues** — the `Depends on: #N` references
assume issue numbers start at 1 and are created in order. The script warns and asks if it
finds any.

## Pointing an agent at it

```
Read AGENTS.md and schema/001_schema.sql. Then pick the lowest-numbered open issue
whose dependencies are all closed and whose milestone is not M6. Implement it against
the existing schema and files, make its acceptance command pass, run `php tests/run.php`,
commit on a branch named issue/<n>-<slug>, and open a PR that closes the issue. If
satisfying the issue would require breaking a rule in AGENTS.md section 1–3, stop and
comment on the issue instead.
```

## Milestones

| | Issues | Gate |
|---|---|---|
| **M0 Scaffold** | 1–6 | Dump + additive migration + seed apply; `db_reset` works |
| **M1 Engine** | 7–14 | An appointment is created and read back from the CLI |
| **M2 Auth** | 15–21 | The double-booking race test passes |
| **M3 Pages** | 22–33 | The demo script runs end to end (existing files reconciled) |
| **M4 Harden** | 34–36 | **The 85% is banked** |
| **M5 Styling** | 37–38 | Base version presentable |
| **M6 Enhancement** | 39–42 | **Blocked until M5 closes** |

Issues 39–42 carry a `blocked` label and a BLOCKED banner. An incomplete base caps the
whole project at 15% regardless of enhancement quality, which is why the agent is told to
skip M6 while M5 is open.

## What the constitution prevents

Left alone, an agent will reach for AJAX, a framework, Composer and Bootstrap, rename
`receipient` to `recipient`, and split the `DATETIME` columns — all reasonable instincts,
all wrong here. `AGENTS.md` sections 1–3 are the guard, and issues #34–35 are audits that
fail the build if any of it leaks in.
