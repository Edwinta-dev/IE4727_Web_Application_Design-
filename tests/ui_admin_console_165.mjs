import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';
import { allPages, root, visit, withTestServer } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';
testPhp(['tools/db_reset.php', '--test'], { cwd: root, env: process.env, encoding: 'utf8' });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const measure = async (route, width) => {
      await page.setViewportSize({ width, height: 844 });
      if (/^(?:admin|doctor|patient)\//.test(route)) await page.context().clearCookies();
      await visit(page, route, base);
      return page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        dates: [...document.querySelectorAll('.console-filters input[type="date"]')].map(input => {
          const rect = input.getBoundingClientRect();
          return { left: rect.left, right: rect.right, width: rect.width };
        }),
      }));
    };

    for (const width of [320, 360, 390]) {
      for (const route of ['admin/console.php', 'admin/console.php?date_from=2026-10-01&date_to=2026-10-31']) {
        const result = await measure(route, width);
        assert.equal(result.scrollWidth, result.clientWidth, `${route} at ${width}px must not overflow`);
        assert.equal(result.dates.length, 2, `${route} at ${width}px must render both date controls`);
        assert.ok(result.dates.every(date => date.left >= 0 && date.right <= width), `${route} date controls must fit at ${width}px: ${JSON.stringify(result.dates)}`);
      }
    }

    for (const route of allPages.filter(path => path !== 'admin/console.php')) {
      const result = await measure(route, 320);
      assert.equal(result.scrollWidth, result.clientWidth, `${route} at 320px must not overflow`);
    }
    console.log('OK: #165 admin filters fit at 320/360/390px and all other pages fit at 320px');
  } finally {
    await browser.close();
  }
});
