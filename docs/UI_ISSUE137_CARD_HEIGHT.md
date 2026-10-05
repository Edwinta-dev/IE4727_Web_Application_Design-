# Home card height verification (#137)

The reported `min-height: 38rem` is already absent from the base stylesheet.
The home markup also no longer applies `grow-row` or `grow-item`: doctors and
specialties use the shared horizontal carousel. No application change is needed.

Measurements from the seeded, isolated `ie4727db_test` home page:

| Viewport width | Doctor card height | Specialty card height | Vertical row allowance |
|---|---:|---:|---:|
| 1280px | 581px | 248px | 36px |
| 1707px | 504px | 270px | 36px |
| 390px | 555px | 240px | 36px |

Card heights match the tallest unconstrained copy of the same row content at
the same card width within one pixel of rounding. The row adds only its existing
18px top and bottom padding for hover/focus magnification. Equal-height siblings
remain part of the existing carousel layout.

Acceptance command: `node tools/ui/home-card-height.test.mjs`. It checks the
seed plus 3, 7 and 12 database-backed doctors and distinct specialties at all
three widths, with normal and reduced motion. It compares intrinsic and actual
heights, tests hover and keyboard focus without row reflow, and verifies that
magnification fits the vertical allowance. It restores the test seed on exit.

Evidence is saved in `UIPROBLEMS/after/issue137-height/measurements.json` and
the accompanying screenshots. The required `shoot.mjs` captures at 1280x800
and 390x844 are in `UIPROBLEMS/after/issue137-before/` and
`UIPROBLEMS/after/issue137-after/`. These local artifacts are gitignored.

Validation: the acceptance command prints `OK`; `php tests/run.php` reports
85 passed, 0 failed; `node --check tools/ui/home-card-height.test.mjs` and
`git diff --check` pass. All database checks were completed sequentially for
the final validated runs.
