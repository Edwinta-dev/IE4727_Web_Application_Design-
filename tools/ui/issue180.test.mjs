import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const fixture = JSON.parse(testPhp(['-r', `
    require 'clinic-base/lib/db.php';
    q('INSERT INTO doctor (FullName, User, HashPass, Email, Specialty) VALUES (?, ?, ?, ?, ?)',
      ['Elapsed fixture', 'elapsed_fixture_180', 'unused', 'elapsed180@example.test', 'General Practice']);
    $doctor = (int) db()->lastInsertId();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    q('INSERT INTO slots (DoctorID, SlotDateTime) VALUES (?, ?)', [$doctor, $today . ' 00:00:00']);
    q('INSERT INTO slots (DoctorID, SlotDateTime) VALUES (?, ?)',
      [$doctor, $tomorrow . ' 09:00:00']);
    echo json_encode(['doctor' => $doctor, 'today' => $today, 'tomorrow' => $tomorrow]);
  `], { encoding: 'utf8' }));

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    try {
      for (const doctor of ['1', '']) {
        const params = new URLSearchParams({ date: fixture.today });
        if (doctor) params.set('doctor', doctor);
        await visit(page, `book.php?${params}`, base);
        assert.equal(await page.locator('.slot.past, .elapsed-slots').count(), 0);
        assert.equal(await page.getByText('Past', { exact: true }).count(), 0);
        const visibleTimes = await page.locator('.slot-time').allTextContents();
        const now = new Date();
        const currentMinutes = Number(new Intl.DateTimeFormat('en-GB', {
          timeZone: 'Asia/Singapore', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).format(now).replace(':', ''));
        for (const label of visibleTimes) {
          const match = label.trim().match(/^(\d{1,2}):(\d{2}) ([AP])M$/);
          assert.ok(match, `unexpected slot time: ${label}`);
          const hour = Number(match[1]) % 12 + (match[3] === 'P' ? 12 : 0);
          assert.ok(hour * 100 + Number(match[2]) > currentMinutes, `elapsed slot shown: ${label}`);
        }
      }
      await visit(page, `book.php?doctor=${fixture.doctor}&date=${fixture.today}`, base);
      assert.equal(await page.locator('.slot').count(), 0);
      assert.equal(await page.locator('.empty-state').count(), 1);
      const tomorrowLink = page.getByRole('link', { name: "See tomorrow's times" });
      assert.equal(await tomorrowLink.count(), 1);
      assert.equal(new URL(await tomorrowLink.getAttribute('href'), base).searchParams.get('date'), fixture.tomorrow);
      assert.equal(await page.locator('#booking-post').count(), 0);
      console.log('OK: issue #180 hides elapsed slots and suggests tomorrow');
    } finally { await page.close(); }
  } finally { await browser.close(); }
});
