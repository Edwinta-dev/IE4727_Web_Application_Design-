import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const output = resolve('UIPROBLEMS/after/issue146-copy');
await mkdir(output, { recursive: true });
const records = [];
try {
  php('tools/db_reset.php', '--test');
  // Exercise an actual suffixed subject; only its displayed value should change.
  const original = 'Booking confirmed - Clinic Appointment Portal';
  php('-r', `require 'clinic-base/models/notifications.php';
    q('UPDATE notifications SET Subject = :subject', ['subject' => '${original}']);`);
  const visitId = php('-r', "require 'clinic-base/models/appointments.php'; echo q_val(\"SELECT appointmentID FROM appointment WHERE DoctorID = 1 AND Status = 'Future' AND appointmentDateTime > NOW() ORDER BY appointmentDateTime LIMIT 1\");").trim();
  assert.ok(Number(visitId) > 0, 'Seed has an owned future visit');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const path of ['doctor/schedule.php', 'admin/outbox.php', 'admin/console.php', 'patient/home.php', 'doctor/home.php', 'doctor/visit.php']) {
        const page = await browser.newPage({ javaScriptEnabled: false });
        try {
          await visit(page, path === 'doctor/visit.php' ? `${path}?appt=${visitId}` : path, base);
          for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            assert.equal(await page.locator('h1 img').count(), 0, `${path}: no heading logo`);
            assert.ok(await page.locator('.site-header img').count() > 0, `${path}: header image retained`);
            assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width, `${path}: document fits`);
            const record = { path, width };
            if (path === 'doctor/schedule.php') {
              const counts = await page.locator('.schedule-day .calendar-slot-total').allTextContents();
              assert.equal(counts.length, 30);
              assert.ok(counts.every(text => /^\s*([1-9]\d* slots|No slots)\s*$/.test(text)), 'Calendar totals never show zero counts');
              record.counts = counts;
            }
            if (path === 'admin/outbox.php') {
              const subjects = await page.locator('tbody td:nth-child(3)').allTextContents();
              assert.ok(subjects.length > 0 && subjects.every(subject => subject === 'Booking confirmed'));
              assert.equal(await page.locator('table').getAttribute('aria-labelledby'), 'outbox-list-heading');
              assert.equal(await page.locator('caption, .outbox-scroll-hint').count(), 0);
              assert.equal(await page.locator('.notification-details').count(), subjects.length, 'Full messages retained');
              record.subjects = subjects;
            }
            if (path === 'admin/console.php') {
              const note = page.locator('.metric-note');
              assert.equal(await note.getAttribute('open'), null, 'Formula collapsed by default');
              assert.ok(!(await page.locator('.stat-card').last().innerText()).includes('appointments included'));
              await note.locator('summary').click();
              assert.ok((await note.innerText()).includes('27 of 27 appointments included; 0 excluded.'));
              await note.locator('summary').click();
            }
            if (path === 'patient/home.php') {
              const cells = await page.locator('#past-heading').locator('..').locator('tbody td:last-child').allTextContents();
              assert.ok(cells.some(text => text.trim() === '\u2014'));
              assert.ok(cells.every(text => !text.includes('Not available')));
            }
            records.push(record);
            await page.screenshot({ path: resolve(output, `${path.replaceAll('/', '-').replace('.php', '')}-${width}.png`), fullPage: true });
          }
          console.log(`Checked ${path} at 1280/390px`);
        } finally { await page.close(); }
      }
      for (const count of [3, 7, 12]) {
        php('tools/ui/featured-doctors-fixture.php', String(count));
        const page = await browser.newPage();
        try {
          await visit(page, 'admin/console.php', base);
          for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            assert.equal(await page.locator('.console-filters select[name="doctor"] option:not([value=""])').count(), count);
            assert.equal(await page.locator('[aria-labelledby="doctors-heading"] tbody tr').count(), count);
            assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);
            records.push({ path: 'admin/console.php', width, doctors: count });
          }
        } finally { await page.close(); }
        const date = php('-r', `require 'clinic-base/models/slots.php';
          $date = date('Y-m-d', strtotime('+60 days'));
          regenerate_schedule(1, $date, 1, ['start' => '09:00', 'end' => '${String(9 + Math.floor(count / 2)).padStart(2, '0')}:${count % 2 ? '30' : '00'}', 'minutes' => 30, 'skip_weekdays' => [], 'breaks' => []]);
          q("UPDATE slots SET Status = 'Blocked' WHERE DoctorID = 1 AND SlotDateTime = :time", ['time' => $date . ' 09:00:00']);
          echo $date;`).trim();
        const schedule = await browser.newPage();
        try {
          await visit(schedule, `doctor/schedule.php?date=${date}`, base);
          for (const width of [1280, 390]) {
            await schedule.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            assert.equal(await schedule.locator('.day-view .slot').count(), count, 'Database-backed schedule slots');
            const counts = await schedule.locator('.schedule-day-counts span').allTextContents();
            assert.deepEqual(counts.map(text => text.trim()), [`Available: ${count - 1}`, 'Booked: 0', 'Blocked: 1']);
            assert.equal((await schedule.locator('.schedule-day.selected .calendar-slot-total').textContent()).trim(), `${count} slots`);
            assert.ok((await schedule.locator('.schedule-day.selected a').getAttribute('aria-label')).endsWith(`${count - 1} Available, 0 Booked, 1 Blocked`));
            assert.equal(await schedule.locator('html').evaluate(el => el.scrollWidth), width);
            records.push({ path: 'doctor/schedule.php', width, slots: count, counts });
          }
        } finally { await schedule.close(); }
      }
    } finally { await browser.close(); }
  });
  assert.equal(php('-r', "require 'clinic-base/models/notifications.php'; echo q_val('SELECT Subject FROM notifications LIMIT 1');").trim(), original, 'Display leaves stored subjects unchanged');
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify(records, null, 2));
  php('tools/db_reset.php', '--test');
}
console.log('OK: nonzero day counts; shortened display subjects; accessible message log; collapsed formula; heading logos removed; absent notes; 3/7/12 doctors and schedule slots; 1280/390px');
