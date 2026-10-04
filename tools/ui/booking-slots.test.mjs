import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve(`UIPROBLEMS/after/issue139-${before ? 'before' : 'after'}-matrix`);
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
              const details = page.locator('.slot-booking');
              assert.ok(await details.count() >= 2, 'Need two available times');
              const firstIndex = await details.first().evaluate(el => [...el.closest('.schedule-grid').children].indexOf(el.parentElement));
              const closed = await heights(page);
              await details.first().locator('summary').focus();
              await page.keyboard.press('Enter');
              await page.waitForFunction(() => document.querySelector('.slot-booking').open);
              const opened = await heights(page);
              await page.screenshot({ path: resolve(output, `${count}-${width}-${js ? 'js' : 'nojs'}-one-open.png`), fullPage: true });
              await details.nth(1).locator('summary').click();
              if (!before && js) await page.waitForFunction(() => document.querySelectorAll('.slot-booking[open]').length === 1 && !document.querySelector('.slot-booking').open);
              const second = await heights(page);
              const secondIndex = await details.nth(1).evaluate(el => [...el.closest('.schedule-grid').children].indexOf(el.parentElement));
              const openCount = await page.locator('.slot-booking[open]').count();
              await page.screenshot({ path: resolve(output, `${count}-${width}-${js ? 'js' : 'nojs'}-second-open.png`), fullPage: true });
              records.push({ count, width, js, closed, opened, second, openCount });
              console.log(`${count}/${width}/${js ? 'JS' : 'no-JS'}: closed ${closed.map(Math.round)}; first open ${opened.map(Math.round)}; open count after second ${openCount}`);
              if (!before) {
                assert.ok(opened[firstIndex] > closed[firstIndex], 'Selected slot expands');
                for (let i = 0; i < count; i++) if (i !== firstIndex) assert.ok(Math.abs(opened[i] - closed[i]) < 1, 'Other slot heights stay unchanged');
                if (js) {
                  assert.equal(openCount, 1, 'Only one confirmation is open');
                  assert.ok(Math.abs(second[firstIndex] - closed[firstIndex]) < 1, 'Previous slot returns to closed height');
                  for (let i = 0; i < count; i++) if (i !== secondIndex) assert.ok(Math.abs(second[i] - closed[i]) < 1, 'Switching preserves other slot heights');
                }
                await details.nth(1).locator('input[name="reason"]').fill('Slot regression visit');
                assert.equal(await details.nth(1).locator('input[name="reason"]').inputValue(), 'Slot regression visit', 'Native form remains usable');
                assert.equal(await details.nth(1).locator('form').getAttribute('method'), 'post');
                assert.ok(await details.nth(1).locator('button').isVisible(), 'Confirmation button remains visible');
                if (js) {
                  await details.nth(1).locator('summary').click();
                  await page.waitForFunction(() => document.querySelectorAll('.slot-booking[open]').length === 0);
                  assert.deepEqual(await heights(page), closed, 'Closing restores all heights');
                }
              }
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
console.log(before ? 'OK: before measurements captured' : 'OK: only selected slot height changes; one open with JS; native no-JS forms; 3/7/12 slots at 1280/390px');
