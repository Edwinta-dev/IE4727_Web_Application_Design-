import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const day = testPhp(['-r', `require 'clinic-base/lib/db.php';
  echo q_one("SELECT DATE(SlotDateTime) AS day FROM slots WHERE DoctorID = 1
    AND SlotDateTime > NOW() GROUP BY DATE(SlotDateTime)
    HAVING COUNT(*) > 1 ORDER BY day LIMIT 1")['day'];`], { encoding: 'utf8' }).trim();
assert.match(day, /^\d{4}-\d{2}-\d{2}$/);
const expected = Number(testPhp(['-r', `require 'clinic-base/lib/db.php';
  echo q_val("SELECT COUNT(*) FROM slots WHERE DoctorID = 1
    AND DATE(SlotDateTime) = ? AND SlotDateTime > NOW()", ['${day}']);`], { encoding: 'utf8' }).trim());

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      for (const javaScriptEnabled of [true, false]) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, javaScriptEnabled });
        try {
          await visit(page, `screens/book.php?doctor=1&date=${day}&from=15:00&to=15:30`, base);
          assert.equal(await page.locator('.booking-time-filter, [name="from"], [name="to"]').count(), 0);
          assert.equal(await page.locator('.schedule-grid > .slot').count(), expected, 'Every future slot is shown despite obsolete time query parameters');
          assert.ok(await page.locator('#specialty').isVisible());
          assert.ok(await page.locator('#doctor').isVisible());
          assert.ok(await page.locator('.day-tab.selected').isVisible());
          assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);
          const dayLink = await page.locator('.day-tab').nth(1).getAttribute('href');
          assert.ok(!new URL(dayLink, base).searchParams.has('from'));
          assert.ok(!new URL(dayLink, base).searchParams.has('to'));
          if (javaScriptEnabled) {
            await Promise.all([
              page.waitForURL(url => url.searchParams.get('doctor') === ''),
              page.locator('#doctor').selectOption(''),
            ]);
            assert.equal(await page.locator('#date').inputValue(), day);
          } else {
            assert.ok(await page.locator('.booking-filters noscript button').isVisible());
            await page.locator('.booking-filters noscript button').click();
            assert.equal(await page.locator('#date').inputValue(), day);
          }
        } finally { await page.close(); }
      }
    }
  } finally { await browser.close(); }
});
console.log('OK: #203 full-day booking slots and specialty/date/doctor filters at 1280/390 with JS on/off');
