import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

const cases = [
  ['doctors.php', '.specialty-filter', 'specialty', 'General Practice'],
  ['admin/console.php', '.console-filters form', 'status', 'Future'],
  ['admin/outbox.php', '.outbox-filters form', 'deliveryStatus', 'logged'],
  ['book.php', '.booking-filters', 'doctor', '1'],
  ['doctor/home.php', '.day-board-filter', 'date', null],
  ['doctor/schedule.php', '.month-grid form', 'date', null],
];

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const [route, selector, field, choice] of cases) {
      const page = await browser.newPage({ javaScriptEnabled: false });
      try {
        await visit(page, `${route}?_csrf=obsolete`, base);
        const form = page.locator(selector);
        if (await form.count() !== 1) throw Error(`${route}: filter form missing`);
        if (await form.locator('input[name="_csrf"]').count()) throw Error(`${route}: GET field leaks token`);
        const value = choice ?? await form.locator(`[name="${field}"]`).inputValue();
        if (choice !== null) await form.locator(`[name="${field}"]`).selectOption(choice);
        await Promise.all([page.waitForNavigation(), form.locator('button[type="submit"]').click()]);
        const url = new URL(page.url());
        if (url.searchParams.has('_csrf') || url.searchParams.get(field) !== value)
          throw Error(`${route}: filtered URL has wrong parameters: ${url}`);
        if (await page.locator(`${selector} input[name="_csrf"]`).count())
          throw Error(`${route}: rendered GET field leaks token`);
        for (const href of await page.locator('a[href]').evaluateAll(anchors => anchors.map(a => a.href))) {
          if (new URL(href).searchParams.has('_csrf')) throw Error(`${route}: navigation link carries token`);
        }
        if (['doctors.php', 'admin/console.php', 'admin/outbox.php'].includes(route)) {
          const clear = page.locator(`${selector} a`, { hasText: 'Clear' });
          await clear.click();
          if (new URL(page.url()).searchParams.has('_csrf')) throw Error(`${route}: clear URL carries token`);
        }
        console.log(`PASS: ${route} ${field} filter`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});
