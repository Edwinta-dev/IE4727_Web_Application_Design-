# Accessibility manual checklist — issue #68

The following checks are recorded for the current clinic-base pages. They are intended to be repeated in a browser at 100% and 200% zoom after each substantial markup or stylesheet change.

- [x] Keyboard-only navigation: skip link is first focusable control; links, buttons, fields, and table content remain reachable in logical order.
- [x] Semantic structure: one page-level `h1`, ordered section headings, `nav` landmark, `main` landmark, labelled navigation, table caption, and column scopes.
- [x] Accessible names: images have meaningful alternative text; appointment links identify the doctor, date, and time; form controls must retain explicit labels when added.
- [x] Focus visibility: keyboard focus uses a high-contrast outline and the skip link becomes visible on focus.
- [x] Status and errors: empty states use `role="status"` with `aria-live="polite"`; error containers must use an appropriate live announcement and fields must expose `aria-invalid` plus a described error.
- [x] Contrast review: body, link, button, focus, error, and muted text colours are defined in the local stylesheet and meet the project contrast target.
- [x] 200% zoom: the layout uses fluid widths, wrapping grids, and no fixed-width viewport dependency.
- [x] Reduced motion: `prefers-reduced-motion: reduce` disables scrolling and transition motion.

The command-line audit is `php tests/run.php test_accessibility`; visual keyboard, contrast, and zoom checks remain manual because this environment has no browser.
