import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { withTestServer, login } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.UI_DOCTOR = 'drsmith';
const check = (ok, message) => { if (!ok) throw Error(message); };
const snapshot = () => JSON.parse(testPhp(['tools/ui/outcome-seed-fixture.php'], { encoding: 'utf8' }));
let previous;
for (let reset = 0; reset < 2; reset++) {
  testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
  const seed = snapshot();
  check(seed.pending.length === 2 && seed.counts.appointment === 27, 'Fresh seed lacks two actionable appointments');
  if (previous) check(JSON.stringify(seed.counts) === JSON.stringify(previous), 'Repeated reset counts differ');
  previous = seed.counts;
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext({ javaScriptEnabled: false });
      const page = await context.newPage();
      await login(page, 'doctor', base);
      const route = `${base}doctor/home.php?date=${seed.pending[0].appointmentDateTime.slice(0, 10)}`;
      await page.goto(route);
      check(await page.getByRole('button', { name: 'Mark completed', exact: true }).count() === 2, 'Both completed controls must be visible');
      check(await page.getByRole('button', { name: 'Mark no-show', exact: true }).count() === 2, 'Both no-show controls must be visible');
      if (reset === 0) return; // Both fresh resets prove immediate authenticated visibility.
      const capture = async label => {
        await mkdir(`UIPROBLEMS/after/${label}`, { recursive: true });
        for (const width of [1280, 390]) {
          await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
          check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Board overflows viewport');
          await page.screenshot({ path: `UIPROBLEMS/after/${label}/doctor-home-${width}.png`, fullPage: true });
        }
      };
      await capture('outcome-seed-actionable');
      const token = await page.locator('input[name="_csrf"]').first().inputValue();
      for (const appointment of [seed.future[0], seed.foreign[0]]) {
        for (const status of ['Completed', 'No show']) {
          const before = snapshot();
          const response = await context.request.post(`${base}actions/appointment.php`, { form: {
            _csrf: token, appointment_id: String(appointment.appointmentID), status,
          }, maxRedirects: 0 });
          check(response.status() === 302, 'Outcome POST must redirect');
          const after = snapshot();
          for (const key of ['appointments', 'slots', 'notifications']) check(JSON.stringify(after[key]) === JSON.stringify(before[key]), `Rejected ${status} POST changed ${key}`);
        }
      }
      for (const [index, status] of ['Completed', 'No show'].entries()) {
        await page.goto(route);
        const row = page.locator('tr').filter({ has: page.locator(`input[name="appointment_id"][value="${seed.pending[index].appointmentID}"]`) });
        await Promise.all([page.waitForNavigation(), row.getByRole('button', { name: index === 0 ? 'Mark completed' : 'Mark no-show', exact: true }).click()]);
        check((await page.locator('main').innerText()).includes(`Appointment marked as ${status}.`), 'Outcome confirmation missing');
        const after = snapshot();
        const saved = after.appointments.find(a => a.appointmentID === seed.pending[index].appointmentID);
        const expected = { ...seed.pending[index], Status: status, updatedAt: saved.updatedAt };
        check(JSON.stringify(saved) === JSON.stringify(expected), 'Outcome changed unrelated appointment data');
        check(JSON.stringify(after.slots) === JSON.stringify(seed.slots), 'Outcome changed booked slots');
        check(JSON.stringify(after.notifications) === JSON.stringify(seed.notifications), 'Outcome sent/logged mail');
      }
      await capture('outcome-seed-outcomes');
      console.log('OK: two verified test resets; owned actionable rows; no-JS Completed/No show; future/foreign POSTs rejected; relations and outbox preserved; 1280/390 overflow 0px');
    } finally { await browser.close(); }
  });
}
