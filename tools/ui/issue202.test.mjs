import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const date = testPhp(['-r', `require 'clinic-base/lib/db.php';
  echo q_one("SELECT DATE(SlotDateTime) AS day FROM slots
    WHERE DoctorID = 1 AND Status = 'Available' AND SlotDateTime > NOW()
    ORDER BY SlotDateTime LIMIT 1")['day'];`], { encoding: 'utf8' }).trim();
assert.match(date, /^\d{4}-\d{2}-\d{2}$/);
const shots = resolve('UIPROBLEMS/after/iter8/issue202-reason-placeholder/after');
await mkdir(shots, { recursive: true });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        await visit(page, `screens/book.php?doctor=1&date=${date}`, base);
        const reason = page.locator('#reason');
        assert.ok(await reason.isVisible(), 'Reason field is visible');
        assert.equal(await reason.getAttribute('placeholder'), 'e.g. Cough and mild fever for 3 days');
        assert.equal(await reason.inputValue(), '', 'Example is not the field value');
        assert.ok(await reason.evaluate(el => el.matches(':placeholder-shown')), 'Example is shown while empty');
        assert.equal(await reason.evaluate(el => getComputedStyle(el, '::placeholder').color), 'rgb(87, 83, 78)', 'Placeholder uses the muted token');
        assert.equal(await reason.evaluate(el => new FormData(el.form).get('reason')), '', 'Example is not submitted as the reason');
        await page.screenshot({ path: resolve(shots, `book-${width}.png`), fullPage: true });
        await reason.fill('Cough');
        assert.ok(!await reason.evaluate(el => el.matches(':placeholder-shown')), 'Placeholder disappears on input');
        assert.equal(await reason.evaluate(el => new FormData(el.form).get('reason')), 'Cough');
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
});
console.log('OK: #202 muted symptom example is visible at 1280/390, absent from empty form data, and hides while typing');
