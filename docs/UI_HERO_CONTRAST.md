# Issue #128: home photo caption

Finding: C01, new finding 8, reported UI_Defects.pdf pages 4 and 7.
The requested report is absent from this checkout: UIPROBLEMS/Open contains
clinicissues.pdf and older rendered page images. Those page 4/7 images were
opened, but describe older findings, so they are not substituted for the
missing report. The issue's written reproduction is confirmed by fresh images.

Actual baseline: clean automation/ie4727-clinic at 5c51d83, including #127's
responsive image sizing. Existing doctor/visit.php, UI_AUDIT_REPORT.md and
visit-state.test.mjs are preserved. This change makes no claim about carry-over
#114 or other old findings.

At 390px the eyebrow crosses the bright photo above the original bottom fade.
Desktop also fails the requested 4.5:1 threshold. The home caption now occupies
the same grid row as the existing shared photo-scrim, which fades only in the
padding above the text. The text region has 72% black coverage. On phones an
intrinsic 16:9 spacer preserves normal sizing while allowing the caption's row
to grow with larger text. A zero-minimum grid column permits phone reflow.
The image, faces at normal sizes, text, desktop placement and login are retained.
The directory's shared overlay is unchanged. No text shadow or coloured overlay.

## Pixel measurement

tools/ui/hero-contrast.test.mjs captures the actual rendered photo and overlay
with only caption glyphs transparent, then captures a white glyph mask over
black at identical positions. It decodes the PNGs using browser canvas and
computes WCAG sRGB relative luminance for every background pixel marked by a
glyph, including antialiased edges. The specified white foreground is compared
to that background, rather than using the antialiased foreground pixel.
Only the outer two CSS pixels of the banner capture are excluded to avoid
fractional locator edges containing the surrounding page surface; all caption
glyphs are inset farther than this. Both eyebrow and heading are sampled.
This is a composited-photo measurement, independent of the audit's CSS checks.

Baseline minima: **1.320:1 at 390px**, **3.872:1 at 1280px**.
After minima: **9.291:1 at 390px**, **9.490:1 at 1280px**;
the minimum across the enlargement/reflow cases is **9.291:1**.
Cases include both widths at 100%/200% root text, 320px at 200% text,
and a 640 CSS-pixel viewport at device scale 2 (1280px window/200% zoom
equivalent). This emulates zoom geometry/density, not a browser toolbar action.
Assertions check full eyebrow/heading bounds, viewport containment, no hero/login
overlap and successful existing patient form login. Large-text screenshots show
existing wrapping/overflow elsewhere in the page, outside this caption issue;
the normal login layout is unaffected and login remains functional.

## Fixtures and evidence

Anonymous home plus the existing seeded patient for the login regression.
No appointment/date fixture or application writes are needed. Browser regression
uses withTestServer(): config and the child response verify ie4727db_test before
database use; local mail is disabled. Acceptance capture/audit commands inherit
explicit CLINIC_DB_NAME=ie4727db_test. Tests reset only that database.

Before/after images opened at both normal widths:
UIPROBLEMS/after/hero-contrast-{before,after}/index-{390,1280}.png.
Enlargement/zoom screenshots and inspected viewport captures:
UIPROBLEMS/after/hero-contrast-after-measurements/.
Per-case geometry, sample counts and exact minima: contrast.json in that folder.
Baseline measurements: hero-contrast-before-measurements/contrast.json.
All images/measurement JSON remain gitignored.

Verification commands (from repository root, sequentially):

```text
node --test tools/ui/isolation.test.mjs
node tools/ui/hero-contrast.test.mjs --before
node tools/ui/shoot.mjs --serve --label hero-contrast-before index.php
node tools/ui/shoot.mjs --serve --label hero-contrast-after index.php
node tools/ui/audit.mjs --serve --within .photo-banner layout,tap-targets,palette,contrast,focus,slop index.php
node tools/ui/hero-contrast.test.mjs
node tools/ui/hero-size.test.mjs
php tests/run.php
node --check tools/ui/hero-contrast.test.mjs
git diff --check
```

Baseline measurement used HEAD's stylesheet temporarily, restored in finally;
the final acceptance runs use the edited stylesheet. No Git mutations occur.
No PHP files changed. Isolation regressions: 7 passed. Scoped audit: OK.
The full PHP suite reports **81 passed, 0 failed**, with existing CLI session
and header warnings. The contrast regression prints OK for all six cases and
the patient login; browser syntax and diff whitespace checks pass.
The existing hero-size regression prints OK for all 12 home/directory/filtered
route-width combinations, including clipping disabled; normal banner dimensions
are unchanged and document overflow is zero.
Preliminary browser runs encountered intermittent image decode errors (also
documented for #127); successful complete reruns are the validation evidence.
