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
        await visit(page, 'book.php?doctor=1', base);
        const days = await page.locator('.day-tab').allTextContents();
        const nextClinicDay = days.findIndex((day, index) => index > 0 && !day.trim().startsWith('Sun'));
        assert.ok(nextClinicDay > 0, 'a future clinic day is available');
        await Promise.all([page.waitForNavigation(), page.locator('.day-tab').nth(nextClinicDay).click()]);
        const gap = await page.locator('.booking-confirmation').evaluate(section => {
          const field = section.querySelector('#reason').getBoundingClientRect();
          const button = section.querySelector('.booking-form-actions button').getBoundingClientRect();
          const token = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--space-3'))
            * parseFloat(getComputedStyle(document.documentElement).fontSize);
          return { actual: button.top - field.bottom, token };
        });
        assert.ok(gap.actual >= gap.token - 1, `${width}px: gap ${gap.actual}px is smaller than --space-3 (${gap.token}px)`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #182 booking field to action spacing at 1280/390px');
