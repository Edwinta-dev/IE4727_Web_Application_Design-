import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const slots = JSON.parse(testPhp(['-r', `require 'clinic-base/lib/db.php';
  echo json_encode(q_all("SELECT slotID, DoctorID, DATE(SlotDateTime) AS day
    FROM slots WHERE DoctorID = 1 AND Status = 'Available' AND SlotDateTime > NOW()
    ORDER BY SlotDateTime LIMIT 2"));`], { encoding: 'utf8' }));
assert.equal(slots.length, 2, 'seed needs two future slots');
const route = `screens/book.php?doctor=${slots[0].DoctorID}&date=${slots[0].day}`;
const shots = resolve('UIPROBLEMS/after/iter8/issue201-booking-time/after');
await mkdir(shots, { recursive: true });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const viewport = { width, height: width === 390 ? 844 : 800 };
      const page = await browser.newPage({ viewport });
      await visit(page, route, base);
      const readout = page.locator('#selected-time');
      assert.equal(await readout.innerText(), 'Select a time slot above');
      const choices = page.locator('.slot-choice');
      assert.ok(await choices.count() >= 2);
      for (const index of [0, 1]) {
        const choice = choices.nth(index);
        await choice.check();
        assert.equal(await readout.innerText(), await choice.getAttribute('data-time'));
        assert.match(await readout.innerText(), /^\d{1,2}:\d{2} [AP]M \([A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2}\)$/);
        assert.ok(!(await page.locator('.booking-confirmation').innerText()).includes('Time selected above'));
      }
      await page.screenshot({ path: resolve(shots, `selected-${width}.png`), fullPage: true });
      const selectedId = await choices.nth(1).inputValue();
      await visit(page, `${route}&slot_id=${selectedId}`, base);
      assert.equal(await page.locator('.slot-choice:checked').count(), 1);
      assert.equal(await readout.innerText(), await page.locator('.slot-choice:checked').getAttribute('data-time'));
      await visit(page, `${route}&slot_id=999999999`, base);
      assert.equal(await readout.innerText(), 'Select a time slot above');
      await page.close();

      const noJs = await browser.newPage({ viewport, javaScriptEnabled: false });
      await visit(noJs, `${route}&slot_id=${selectedId}`, base);
      assert.equal(await noJs.locator('#selected-time').innerText(), await noJs.locator('.slot-choice:checked').getAttribute('data-time'), 'server prefills readout without JavaScript');
      await noJs.close();
    }
  } finally { await browser.close(); }
});
console.log('OK: #201 selected slot readout, query prefill, empty prompt, and no-JS render at 1280/390');
