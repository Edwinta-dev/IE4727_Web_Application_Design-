import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const context = await browser.newContext({ viewport: { width, height: width === 1280 ? 800 : 844 }, hasTouch: true });
      const page = await context.newPage();
      try {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await visit(page, 'index.php', base);
        for (const selector of ['#featured-doctor-tiles', '#specialty-tiles']) {
          const track = page.locator(selector);
          const carousel = track.locator('..');
          const state = await track.evaluate(element => ({
            scrollbarWidth: getComputedStyle(element).scrollbarWidth,
            webkitScrollbarDisplay: getComputedStyle(element, '::-webkit-scrollbar').display,
            overflowX: getComputedStyle(element).overflowX,
            maximum: element.scrollWidth - element.clientWidth,
          }));
          assert.equal(state.scrollbarWidth, 'none', `${width} ${selector}: Firefox scrollbar hidden`);
          assert.equal(state.webkitScrollbarDisplay, 'none', `${width} ${selector}: Chromium scrollbar hidden`);
          assert.equal(state.overflowX, 'auto', `${width} ${selector}: track remains scrollable`);
          assert.ok(state.maximum > 0, `${width} ${selector}: track has offscreen content`);

          await carousel.locator('[data-doctor-next]').click();
          await assert.doesNotReject(() => page.waitForFunction(id => document.querySelector(id).scrollLeft > 1, selector));
          await carousel.locator('[data-doctor-previous]').focus();
          await page.keyboard.press('Enter');
          await page.waitForFunction(id => document.querySelector(id).scrollLeft < 1, selector);

          const bounds = await track.boundingBox();
          assert.ok(bounds, `${width} ${selector}: track is visible`);
          const client = await context.newCDPSession(page);
          const y = Math.round(bounds.y + bounds.height / 2);
          const start = Math.round(bounds.x + bounds.width * 0.85);
          const distance = Math.round(bounds.width * 0.7);
          await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: start, y }] });
          for (let step = 1; step <= 12; step++) {
            await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: start - distance * step / 12, y }] });
            await page.waitForTimeout(16);
          }
          await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
          await page.waitForFunction(id => document.querySelector(id).scrollLeft > 1, selector);
          await client.detach();
        }
      } finally {
        await context.close();
      }
    }
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log('OK home carousel scrollbars');
