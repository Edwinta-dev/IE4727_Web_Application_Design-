import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const output = resolve(`UIPROBLEMS/after/issue146-after-matrix`);
await mkdir(output, { recursive: true });
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const records = [];
const heights = page => page.locator('.schedule-grid > .slot').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().height));

try {
  php('tools/db_reset.php', '--test');
  // Use real seeded rows and the existing time filters to vary list length.
  const fixture = JSON.parse(php('-r', `require 'clinic-base/models/slots.php';
    for ($offset = 1; $offset < 7; $offset++) {
      $date = date('Y-m-d', strtotime("+$offset days"));
      $slots = slots_for_day(1, $date);
      if (count($slots) >= 12 && count(free_slots(1, $date)) >= 2) {
        echo json_encode(['date' => $date, 'slots' => $slots]); break;
      }
    }`));
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of [3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        php('tools/ui/featured-doctors-fixture.php', String(count));
        const path = `book.php?doctor=1&date=${fixture.date}&from=${fixture.slots[0].SlotTime.slice(0, 5)}&to=${fixture.slots[count - 1].SlotTime.slice(0, 5)}`;
        for (const width of [1280, 390]) {
          for (const js of [true, false]) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, javaScriptEnabled: js });
            try {
              await visit(page, path, base);
              assert.equal(await page.locator('.schedule-grid > .slot').count(), count, 'Database-backed slot count');
              assert.equal(await page.locator('#doctor option[value]:not([value=""])').count(), count, 'Database-backed doctor count');
              const choices = page.locator('.slot-choice');
              assert.ok(await choices.count() >= 2, 'Need two available times');
              assert.equal(await page.locator('.booking-confirmation').count(), 1, 'One shared confirmation');
              assert.equal(await page.locator('.slot-booking form').count(), 1, 'One native POST form');
              assert.ok(!(await page.locator('.schedule-grid').innerText()).includes('Select time'));
              const closed = await heights(page);
              await page.screenshot({ path: resolve(output, `${count}-${width}-${js ? 'js' : 'nojs'}-closed.png`), fullPage: true });
              await choices.first().focus();
              await page.keyboard.press('Space');
              assert.ok(await choices.first().isChecked(), 'Keyboard selects a time');
              if (js) assert.equal(await page.locator('#selected-time').innerText(), await choices.first().getAttribute('data-time'));
              const opened = await heights(page);
              assert.deepEqual(opened, closed, 'Selection never expands a slot');
              await page.screenshot({ path: resolve(output, `${count}-${width}-${js ? 'js' : 'nojs'}-one-open.png`), fullPage: true });
              await choices.nth(1).check();
              assert.equal(await page.locator('.slot-choice:checked').count(), 1, 'Exactly one time selected with or without JS');
              assert.ok(!(await choices.first().isChecked()), 'Previous selection cleared');
              assert.deepEqual(await heights(page), closed, 'Switching preserves slot heights');
              if (js) assert.equal(await page.locator('#selected-time').innerText(), await choices.nth(1).getAttribute('data-time'));
              const form = page.locator('.slot-booking form');
              await form.locator('input[name="reason"]').fill('Slot regression visit');
              assert.equal(await form.getAttribute('method'), 'post');
              assert.ok(await form.locator('button').isVisible());
              const submitted = await form.evaluate(el => [...new FormData(el).entries()]);
              assert.equal(submitted.filter(([name]) => name === 'slot_id').length, 1);
              assert.equal(submitted.find(([name]) => name === 'slot_id')[1], await choices.nth(1).inputValue());
              assert.ok(submitted.some(([name, value]) => name === '_csrf' && value.length > 0));
              assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width, 'No document overflow');
              records.push({ count, width, js, closed, opened, selected: await choices.nth(1).inputValue() });
              console.log(`${count}/${width}/${js ? 'JS' : 'no-JS'}: one confirmation; slot heights ${closed.map(Math.round)}; selection and POST data OK`);
              await page.screenshot({ path: resolve(output, `${count}-${width}-${js ? 'js' : 'nojs'}-final.png`), fullPage: true });
            } finally { await page.close(); }
          }
        }
      }
    } finally { await browser.close(); }
  });
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify(records, null, 2));
  php('tools/db_reset.php', '--test');
}
console.log('OK: one shared confirmation; time-only native controls; unchanged slot heights; 3/7/12 slots and doctors at 1280/390px with and without JS');
