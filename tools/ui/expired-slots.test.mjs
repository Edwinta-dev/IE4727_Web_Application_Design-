import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { testPhp } from './isolation.mjs';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const output = resolve('UIPROBLEMS/after/issue166');
await mkdir(output, { recursive: true });
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const day = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Singapore', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());

try {
  php('tools/db_reset.php', '--test');
  const doctorId = Number(php('-r', `require 'clinic-base/lib/db.php';
    $doctor = (int) q_val('SELECT DoctorID FROM doctor WHERE User = :user', ['user' => 'drsmith']);
    for ($minute = 8 * 60; $minute < 23 * 60; $minute += 30) {
      $when = date('Y-m-d', strtotime('today')) . ' ' . sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);
      q("INSERT IGNORE INTO slots (DoctorID, SlotDateTime, Status) VALUES (:doctor, :when, 'Available')", ['doctor' => $doctor, 'when' => $when]);
    }
    echo $doctor;`));
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const width of [390, 1280]) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, javaScriptEnabled: false });
        await visit(page, `doctor/schedule.php?date=${day}`, base);
        assert.ok(await page.locator('.elapsed-slots').count() === 1, 'schedule groups elapsed slots');
        assert.ok(await page.locator('.schedule-generator').isVisible(), 'generation form is visible');
        const generator = await page.locator('.schedule-generator').boundingBox();
        const firstAvailable = await page.locator('.day-view .slot.free').first().boundingBox();
        console.log(`${width}px: generator ${JSON.stringify(generator)}, first available ${JSON.stringify(firstAvailable)}`);
        assert.ok(generator && generator.y + generator.height < (width === 390 ? 844 : 800), `generation form is in first viewport at ${width}px`);
        const availableTime = await page.locator('.day-view .slot.free .slot-time').first().boundingBox();
        assert.ok(firstAvailable && availableTime && availableTime.y + availableTime.height < (width === 390 ? 844 : 800), `first available time is in first viewport at ${width}px`);
        const explanation = await page.locator('.elapsed-slots').textContent();
        assert.equal((explanation.match(/availability can no longer be changed/g) ?? []).length, 1, 'schedule explanation appears once');
        await page.locator('.day-view .elapsed-slots summary').click();
        assert.ok(await page.locator('.day-view .elapsed-slots p').isVisible(), 'native disclosure reveals the explanation without JavaScript');
        await page.screenshot({ path: resolve(output, `schedule-${width}.png`), fullPage: true });
        await page.close();
      }
      const booking = await browser.newPage({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
      await visit(booking, `book.php?doctor=${doctorId}&date=${day}`, base);
      assert.ok(await booking.locator('.elapsed-slots').count() === 1, 'booking groups elapsed times');
      const firstSlot = await booking.locator('.schedule-grid > .slot').first().innerText();
      assert.equal(firstSlot.includes('Past'), false, 'bookable times lead the displayed slot list');
      assert.equal((await booking.locator('.elapsed-slots').textContent()).split('cannot be booked').length - 1, 1, 'booking explanation appears once');
      await booking.locator('.elapsed-slots summary').click();
      assert.ok(await booking.locator('.elapsed-slots p').isVisible(), 'booking disclosure works without JavaScript');
      await booking.screenshot({ path: resolve(output, 'booking-390.png'), fullPage: true });
      await booking.close();
    } finally { await browser.close(); }
  });
} finally {
  php('tools/db_reset.php', '--test');
}
console.log('OK: remaining availability leads elapsed slots; generation and first available slot fit at 390/1280; no-JS details work');
