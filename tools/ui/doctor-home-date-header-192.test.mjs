import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, login } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ javaScriptEnabled: false });
    await login(page, 'doctor', base);
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      await page.goto(base + 'doctor/home.php', { waitUntil: 'load' });
      const positions = await page.evaluate(() => {
        const schedule = document.querySelector('.day-schedule').getBoundingClientRect();
        const controls = document.querySelector('.day-board-date-controls').getBoundingClientRect();
        const heading = document.querySelector('.day-board-date-controls h2').getBoundingClientRect();
        const previous = document.querySelector('.day-board-date-controls a:first-child').getBoundingClientRect();
        const next = document.querySelector('.day-board-date-controls a:last-child').getBoundingClientRect();
        const table = document.querySelector('.day-schedule table').getBoundingClientRect();
        return {
          scheduleCenter: schedule.x + schedule.width / 2,
          controlsCenter: controls.x + controls.width / 2,
          headingCenter: heading.x + heading.width / 2,
          previousRight: previous.right,
          headingLeft: heading.left,
          headingRight: heading.right,
          nextLeft: next.left,
          headingBottom: heading.bottom,
          tableTop: table.top,
          overflow: document.documentElement.scrollWidth - innerWidth,
        };
      });
      assert.ok(Math.abs(positions.scheduleCenter - positions.controlsCenter) <= 1, `${width}: controls centered over schedule`);
      assert.ok(Math.abs(positions.scheduleCenter - positions.headingCenter) <= 1, `${width}: date centered between controls`);
      assert.ok(positions.previousRight <= positions.headingLeft && positions.headingRight <= positions.nextLeft, `${width}: controls flank date`);
      assert.ok(positions.headingBottom < positions.tableTop, `${width}: date appears above schedule`);
      assert.ok(positions.overflow <= 0, `${width}: no horizontal overflow`);
      const date = await page.locator('#day').inputValue();
      await page.getByRole('link', { name: 'Previous day' }).click();
      assert.notEqual(await page.locator('#day').inputValue(), date, `${width}: previous day navigates`);
      await page.getByRole('link', { name: 'Next day' }).click();
      assert.equal(await page.locator('#day').inputValue(), date, `${width}: next day returns`);
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #192 date and day controls centered at 1280/390; navigation works without JavaScript');
