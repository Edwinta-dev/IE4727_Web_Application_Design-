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
        await visit(page, 'doctors.php', base);
        const filter = page.locator('.specialty-filter');
        assert.equal(await filter.locator('button:visible').count(), 0, 'no visible submit button with JavaScript');
        await page.keyboard.press('Tab');
        await filter.locator('select').focus();
        const focus = await filter.locator('select').evaluate(select => ({
          visible: select.matches(':focus-visible'),
          color: getComputedStyle(select).outlineColor,
          radius: getComputedStyle(select).borderRadius,
        }));
        assert.equal(focus.visible, true, 'keyboard focus remains visible');
        assert.equal(focus.color, 'rgb(15, 118, 110)', 'focus ring uses clinic teal');
        assert.notEqual(focus.radius, '0px', 'focus ring follows control radius');
        await Promise.all([
          page.waitForURL(url => url.searchParams.get('specialty') === 'General Practice'),
          filter.locator('select').selectOption('General Practice'),
        ]);
        assert.equal(await page.locator('#specialty').inputValue(), 'General Practice');
        assert.match(await page.locator('.active-doctor-filter').innerText(), /General Practice/);
        assert.equal(await page.locator('.doctor-directory tbody tr').count(), 1);
        assert.match(await page.locator('.doctor-directory tbody').innerText(), /General Practice/);
        await page.locator('.clear-doctor-filter').click();
        assert.equal(new URL(page.url()).searchParams.has('specialty'), false);
      } finally {
        await page.close();
      }

      const noJs = await browser.newPage({ javaScriptEnabled: false, viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        await visit(noJs, 'doctors.php', base);
        const filter = noJs.locator('.specialty-filter');
        assert.equal(await filter.locator('button:visible').count(), 1, 'noscript submit is visible');
        await filter.locator('select').selectOption('Dental');
        assert.equal(new URL(noJs.url()).searchParams.has('specialty'), false, 'selection waits for submit without JavaScript');
        await Promise.all([
          noJs.waitForURL(url => url.searchParams.get('specialty') === 'Dental'),
          filter.locator('button').click(),
        ]);
        assert.equal(await noJs.locator('#specialty').inputValue(), 'Dental');
      } finally {
        await noJs.close();
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #176 specialty auto-submits, stays selected, clears, and has a noscript submit at 1280/390');
