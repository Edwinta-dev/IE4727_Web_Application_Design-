import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { withTestServer, login, prepareStaticAssets, appBase } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const output = 'UIPROBLEMS/after/iter7/booking-page-not-found';
const php = (...args) => testPhp(args, { encoding: 'utf8' });
await mkdir(output, { recursive: true });
php('tools/db_reset.php', '--test');
try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const width of [1280, 390]) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
        try {
          await prepareStaticAssets(page, base);
          await login(page, 'patient', base);
          if (width === 390) await page.getByRole('button', { name: 'Menu' }).click();
          await page.getByRole('navigation', { name: 'Main navigation' })
            .getByRole('link', { name: 'Book an appointment' }).click();
          await page.waitForLoadState('load');
          await page.screenshot({ path: `${output}/before-${width}-booking.png`, fullPage: true });
          const form = page.locator('.booking-filters');
          assert.equal(new URL(page.url()).pathname, new URL('book.php', base).pathname);
          assert.equal(await form.getAttribute('action'), new URL('book.php', base).pathname);
          await page.locator('#doctor').selectOption('1');
          await page.locator('.booking-time-filter summary').click();
          await page.locator('#from').fill('09:00');
          await page.locator('#to').fill('16:30');
          await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Show schedule' }).click()]);
          assert.equal(new URL(page.url()).searchParams.get('doctor'), '1');
          assert.equal(new URL(page.url()).searchParams.get('from'), '09:00');
          assert.equal(new URL(page.url()).searchParams.get('to'), '16:30');
          assert.equal(await page.title(), 'Book an Appointment');
          const days = await page.locator('.day-tab').allTextContents();
          const nextClinicDay = days.findIndex((day, index) => index > 0 && !day.trim().startsWith('Sun'));
          assert.ok(nextClinicDay > 0, 'a future clinic day exists in the booking window');
          await Promise.all([page.waitForNavigation(), page.locator('.day-tab').nth(nextClinicDay).click()]);
          assert.equal(await page.title(), 'Book an Appointment');
          assert.ok(await page.locator('.slot-choice').count() > 1);
          assert.equal(await page.locator('.slot-booking form').getAttribute('action'), new URL('actions/book.php', base).pathname);
          const choice = page.locator('.slot-choice').nth(width === 390 ? 1 : 0);
          const slotId = await choice.inputValue();
          await choice.check();
          await page.locator('#reason').fill('Routine health review');
          await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirm booking' }).click()]);
          assert.equal(new URL(page.url()).pathname, new URL('book.php', base).pathname);
          assert.match(await page.locator('body').innerText(), /has been booked/i);
          const booked = php('-r', `require 'clinic-base/lib/db.php'; echo q_val('SELECT COUNT(*) FROM appointment WHERE slotID = ? AND Status = ?', [${Number(slotId)}, 'Future']);`).trim();
          assert.equal(booked, '1');
          await page.screenshot({ path: `${output}/after-${width}-booking.png`, fullPage: true });
        } finally { await page.close(); }
      }
    } finally { await browser.close(); }
  });
} finally { php('tools/db_reset.php', '--test'); }
console.log('OK: #177 patient filters, date, slot and booking at 1280/390px; confirmed in ie4727db_test');
