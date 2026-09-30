# UI screenshots and geometry audit

These scripts are development tools. They run the PHP app from this checkout at
`http://127.0.0.1:8123/clinic-base/`; they are not referenced by shipped pages.
Port 8000 is rejected because it can serve a stale XAMPP copy.

## Capture pages

From the repository root, run `node tools/ui/shoot.mjs --serve --label <label>
<pages|all>`. Page arguments are app paths such as `index.php`,
`doctor.php?id=1`, or `patient/home.php`. `all` captures the eleven pages listed
in `docs/PAGES.md`. Each page is captured full-page at 1280x800 and 390x844;
files go to `UIPROBLEMS/after/<label>/`.

Protected pages log in through the real form on `index.php`: patient `alextan`,
doctor `drsmith`, admin `admin`, all with `Password123`. Override credentials
with `UI_PATIENT`, `UI_DOCTOR`, `UI_ADMIN`, and `UI_PASSWORD`. A login failure
names the role and stops the run.

## Audit

Run `node tools/ui/audit.mjs --serve layout,tap-targets,slop all` to measure
horizontal overflow, navigation and header geometry, image and table geometry,
interactive hit areas, copy, typography, and decorative effects at both widths.
Failures print page, width, selector, and measured value. Available additional
checks are `palette`, `contrast`, and `focus`; choose them with
`--checks=palette,contrast,focus`. Run `node tools/ui/audit.mjs --selftest` to
verify the good and bad HTML fixtures against every audit category.

Open captured PNGs at both viewport widths and compare with the owner references
in `UIPROBLEMS/` before declaring a visual task complete. Screenshots are
gitignored evidence and must not be committed.
