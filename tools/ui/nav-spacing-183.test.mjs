import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        const gaps = [];
        for (const path of ['book.php', 'register.php']) {
          await visit(page, path, base);
          const spacing = await page.locator('.page-intro-spaced').evaluate(intro => {
            const nav = document.querySelector('.site-header');
            const token = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--space-4'))
              * parseFloat(getComputedStyle(document.documentElement).fontSize);
            return {
              gap: intro.querySelector('h1').getBoundingClientRect().top - nav.getBoundingClientRect().bottom,
              padding: parseFloat(getComputedStyle(intro).paddingTop),
              token,
            };
          });
          assert.ok(Math.abs(spacing.padding - spacing.token) < 1, `${path} at ${width}px must use --space-4`);
          assert.ok(spacing.gap >= spacing.token - 1, `${path} at ${width}px heading is too close to nav`);
          gaps.push(spacing.gap);
        }
        assert.ok(Math.abs(gaps[0] - gaps[1]) < 1, `${width}px headings need the same nav gap`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #183 shared booking and registration heading spacing at 1280/390px');
