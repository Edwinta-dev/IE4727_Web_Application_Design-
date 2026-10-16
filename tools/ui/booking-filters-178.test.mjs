import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        await visit(page, 'book.php', base);
        const today = await page.locator('#date').inputValue();
        assert.equal(await page.locator('#specialty').inputValue(), 'General Practice');
        assert.equal(await page.locator('.day-tab.selected').count(), 1);
        assert.equal(await page.locator('.booking-doctor p').first().innerText(), 'General Practice');
        assert.ok(await page.locator('.schedule-grid .slot').count() > 0);
        assert.equal(await page.getByText('Show schedule').count(), 0);
        assert.equal(await page.locator('.booking-filters noscript button').isVisible(), false);
        assert.equal(await page.locator('.booking-filters fieldset:disabled input[name="_csrf"]').count(), 1);
        assert.ok(await page.locator('.day-tab.selected').isVisible(), 'Selected day stays visible');
        assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);

        await Promise.all([
          page.waitForURL(url => url.searchParams.get('specialty') === 'Dental'),
          page.locator('#specialty').selectOption('Dental'),
        ]);
        assert.equal(await page.locator('#date').inputValue(), today);
        assert.equal(new URL(page.url()).searchParams.has('_csrf'), false);
        assert.equal(await page.locator('.booking-doctor p').first().innerText(), 'Dental');
        assert.ok(await page.locator('.schedule-grid .slot').count() > 0);

        const tomorrow = await page.locator('.day-tab').nth(1).getAttribute('href');
        await page.locator('.day-tab').nth(1).click();
        assert.ok(page.url().endsWith(tomorrow));
        assert.equal(await page.locator('#specialty').inputValue(), 'Dental');
        assert.notEqual(await page.locator('#date').inputValue(), today);
        assert.equal(await page.locator('.booking-doctor p').first().innerText(), 'Dental');

        await page.locator('#specialty').focus();
        await page.keyboard.press('Tab');
        const focus = await page.evaluate(() => {
          const element = document.activeElement;
          const style = getComputedStyle(element);
          return { visible: element.matches(':focus-visible'), outline: style.outlineColor, radius: style.borderRadius };
        });
        assert.equal(focus.visible, true);
        assert.equal(focus.outline, 'rgb(15, 118, 110)');
        assert.notEqual(focus.radius, '0px');
      } finally { await page.close(); }

      const noJs = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, javaScriptEnabled: false });
      try {
        await visit(noJs, 'book.php', base);
        assert.ok(await noJs.locator('.booking-filters noscript button').isVisible());
        await noJs.locator('#specialty').selectOption('Dental');
        await noJs.locator('.booking-filters noscript button').click();
        assert.equal(await noJs.locator('.booking-doctor p').first().innerText(), 'Dental');
      } finally { await noJs.close(); }
    }
  } finally { await browser.close(); }
});
console.log('OK: #178 default schedule, auto-applied specialty/date, focus ring and no-JS fallback at 1280/390');
