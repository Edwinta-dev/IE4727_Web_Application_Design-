import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { testPhp } from './isolation.mjs';
import { withTestServer, login, appBase } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const check = (ok, message) => { if (!ok) throw Error(message); };
const fixture = mode => JSON.parse(testPhp(['tools/ui/reschedule-day-fixture.php', mode], { encoding: 'utf8' }));
console.log(testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' }).trim());
const seed = fixture('setup');
const day = offset => { const date = new Date(`${seed.today}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + offset); return date.toISOString().slice(0, 10); };
const original = seed.appointments[0];
const beforeOnly = process.argv.includes('--before');
const label = `reschedule-day-${beforeOnly ? 'before' : 'after'}-flow`;
const capture = async (page, name) => {
  await mkdir(`UIPROBLEMS/after/${label}`, { recursive: true });
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
    await page.screenshot({ path: `UIPROBLEMS/after/${label}/${name}-${width}.png`, fullPage: true });
    const measurements = await page.evaluate(() => {
      const feedback = document.querySelector('.booking-slots > .empty-state')?.getBoundingClientRect();
      const grid = document.querySelector('.schedule-grid')?.getBoundingClientRect();
      return { overflow: document.documentElement.scrollWidth - innerWidth,
        feedbackBottom: feedback?.bottom ?? null, gridTop: grid?.top ?? null,
        feedbackGridCells: document.querySelectorAll('.schedule-grid .empty-state').length };
    });
    await writeFile(`UIPROBLEMS/after/${label}/${name}-${width}.json`, JSON.stringify(measurements, null, 2));
    if (!beforeOnly) {
      check(measurements.overflow <= 1 && measurements.feedbackGridCells === 0, 'capture has overflow or day-level grid cell');
      check(measurements.feedbackBottom === null || measurements.gridTop === null || measurements.feedbackBottom <= measurements.gridTop,
        'day feedback overlaps the slot grid');
    }
  }
};
await withTestServer(true, async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ javaScriptEnabled: false });
    await login(page, 'patient');
    const route = `${appBase}book.php?reschedule=${original.appointmentID}`;
    await page.goto(route);
    await capture(page, 'landing');
    await page.goto(`${route}&date=${day(0)}`);
    await capture(page, 'elapsed');
    if (beforeOnly) { console.log('OK: authenticated before captures; resolved ie4727db_test'); return; }
    const audit = paths => console.log(execFileSync(process.execPath, ['tools/ui/audit.mjs', '--within', 'main',
      'layout,tap-targets,palette,contrast,focus,slop', ...paths], {
      encoding: 'utf8', env: { ...process.env, UI_BASE_URL: appBase }, windowsHide: true,
    }).trim());
    // The supported relative-route workflow retains patient login in prepare().
    audit([`patient/../book.php?reschedule=${original.appointmentID}`,
      `patient/../book.php?reschedule=${original.appointmentID}&date=${day(0)}`]);
    await page.goto(route);
    check(await page.locator('#date').inputValue() === day(4), 'entry must prefer selectable original day');
    await page.goto(`${appBase}book.php?reschedule=${seed.appointments[1].appointmentID}&doctor=1`);
    check(await page.locator('#date').inputValue() === day(3), 'outside-window original must land on earliest free permitted day with owned doctor');
    check(await page.locator('#doctor').inputValue() === String(seed.doctor), 'reschedule doctor changed');
    await page.goto(`${route}&from=09:00&to=10:30`);
    check(await page.locator('#date').inputValue() === day(3), 'landing must respect time filters');
    for (const [offset, copy] of [[0, 'All appointment times on this day have passed.'], [1, 'The remaining times on this day are booked or unavailable.'], [2, 'No appointment times are scheduled for this day.']]) {
      await page.goto(`${route}&date=${day(offset)}`);
      check(await page.locator('#date').inputValue() === day(offset), 'valid explicit day must be preserved');
      check((await page.locator('.booking-slots').innerText()).includes(copy), `day ${offset} has dishonest empty message`);
      check(await page.locator('.schedule-grid .empty-state').count() === 0, 'day feedback occupies a grid cell');
      check(!(await page.locator('main').innerText()).includes('fully booked'), 'elapsed/blocked copy says fully booked');
    }
    await page.goto(`${route}&date=${day(3)}&from=15:00&to=16:00`);
    check((await page.locator('.booking-slots').innerText()).includes('No appointment times match these time filters.'), 'filtered empty day mistaken for ungenerated day');
    await page.goto(`${route}&date=${day(3)}`);
    const details = page.locator('.slot-booking').first();
    await details.locator('.slot-choice').first().focus();
    await page.keyboard.press('Space');
    check(await details.locator('.slot-choice').first().isChecked(), 'keyboard cannot select time');
    await details.locator('input[name="reason"]').fill('Reschedule conflict regression');
    fixture('stale');
    await Promise.all([page.waitForNavigation(), details.locator('button').click()]);
    check((await page.locator('main').innerText()).includes('just been taken'), 'stale selection conflict missing');
    const afterConflict = fixture('state');
    check(JSON.stringify(afterConflict.appointments) === JSON.stringify(seed.appointments), 'conflict changed original booking');
    check(afterConflict.notifications === seed.notifications, 'conflict emitted notifications');
    await page.goto(`${route}&date=${day(1)}`);
    await page.locator('#date').fill(day(4));
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Show schedule' }).click()]);
    check(await page.locator('#date').inputValue() === day(4) && await page.locator('.reschedule-context').count() === 1, 'JS-off date form lost reschedule');
    const replacement = page.locator('.slot-booking').first();
    await replacement.locator('.slot-choice').first().check();
    await replacement.locator('input[name="reason"]').fill('Confirmed replacement');
    await Promise.all([page.waitForNavigation(), replacement.locator('button').click()]);
    const success = fixture('state');
    check(success.appointments[0].Status === 'Rescheduled' && success.appointments[0].slotID !== original.slotID, 'replacement did not succeed');
    check(success.slots.find(slot => slot.slotID === original.slotID).Status === 'Available', 'successful replacement did not release original');
    fixture('unavailable');
    await page.goto(`${appBase}book.php?reschedule=${seed.appointments[1].appointmentID}`);
    check((await page.locator('main').innerText()).includes('No available appointment times within the next 7 days'), 'no-window availability state missing');
    await capture(page, 'no-availability');
    audit([`patient/../book.php?reschedule=${seed.appointments[1].appointmentID}`]);
    await page.goto(`${appBase}book.php?reschedule=1`);
    check(!page.url().includes('book.php'), 'foreign booking exposed');
    console.log('OK: authorized original/next day, explicit/elapsed/booked/blocked/empty/filtered days, no-window state, keyboard, JS-off, stale conflict and atomic replacement');
  } finally { await browser.close(); }
});
