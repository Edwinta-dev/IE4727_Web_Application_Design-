import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit, login } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

// #183 and #227: the title block that opens a page sits the same distance below the nav on every page.
const groups = [
  [null, ['screens/book.php', 'screens/register.php']],
  ['patient', ['screens/patient/home.php']],
  ['doctor', ['screens/doctor/home.php', 'screens/doctor/schedule.php']],
  ['admin', ['screens/admin/console.php', 'screens/admin/outbox.php']],
];

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      for (const [role, paths] of groups) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
        try {
          if (role) await login(page, role, base);
          for (const path of paths) {
            await visit(page, path, base);
            const spacing = await page.locator('main > .page-intro:first-child').evaluate(intro => {
              const nav = document.querySelector('.site-header');
              const root = getComputedStyle(document.documentElement);
              const token = parseFloat(root.getPropertyValue('--space-4')) * parseFloat(root.fontSize);
              return {
                padding: parseFloat(getComputedStyle(intro).paddingTop),
                gap: intro.querySelector('h1').getBoundingClientRect().top - nav.getBoundingClientRect().bottom,
                token,
              };
            });
            assert.ok(Math.abs(spacing.padding - spacing.token) < 1, `${path} at ${width}px must use --space-4 above its title block`);
            assert.ok(spacing.gap >= spacing.token - 1, `${path} at ${width}px heading is too close to nav`);
          }
        } finally {
          await page.close();
        }
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #183/#227 shared title spacing on booking, registration, patient, doctor and admin pages at 1280/390px');
