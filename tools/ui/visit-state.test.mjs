import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { withTestServer, login } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const fixture = (...args) => testPhp(['tools/ui/visit-state-fixture.php', ...args.map(String)], { encoding: 'utf8' }).trim();
const check = (condition, message) => { if (!condition) throw Error(message); };
const seed = JSON.parse(fixture('seed'));
const previousDoctor = process.env.UI_DOCTOR;
process.env.UI_DOCTOR = seed.user;
const snapshot = id => JSON.parse(fixture('snapshot', id));
try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext({ javaScriptEnabled: false });
      const page = await context.newPage();
      await login(page, 'doctor', base);
      // Use the real logged-in session's token even when no notes form is shown.
      await page.goto(`${base}doctor/visit.php?appt=${seed.visits[0].id}`);
      const token = await page.locator('input[name="_csrf"]').first().inputValue();
      for (const visit of seed.visits) {
        await page.goto(`${base}doctor/visit.php?appt=${visit.id}`);
        const eligible = visit.period === 'past' && ['Future', 'Rescheduled', 'Completed'].includes(visit.status);
        const disallowed = ['Cancelled', 'No show'].includes(visit.status);
        const form = page.locator('form.visit-form');
        check(await form.count() === Number(eligible), `${visit.period} ${visit.status}: notes form eligibility`);
        check(await page.locator('.visit-form-section :disabled').count() === 0, 'Inactive fields should not be presented');
        if (!eligible) {
          const reason = await page.locator('.visit-form-section .empty-state').innerText();
          check(disallowed ? reason.includes(`This appointment is ${visit.status}.`) && !reason.includes('has not started')
            : reason.includes('This appointment has not started.') && reason.includes('Visit notes can be recorded from'), `${visit.period} ${visit.status}: wrong reason: ${reason}`);
          if (disallowed) check((await page.locator('.visit-form-section').innerText()).includes('Earlier diagnosis'), 'Read-only existing notes lost');
        } else {
          check(await form.locator('input[name="_csrf"]').count() === 1, 'Eligible form must contain CSRF token');
          await page.locator('#diagnosis').focus();
          check(await page.locator('#diagnosis').evaluate(field => getComputedStyle(field).outlineStyle) !== 'none', 'Notes field lacks keyboard focus');
        }
        if ((visit.status === 'Rescheduled' && visit.period === 'future') || (visit.status === 'Future' && eligible)) {
          await mkdir('UIPROBLEMS/after/visit-state-matrix-after', { recursive: true });
          for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Visit overflows viewport');
            await page.screenshot({ path: `UIPROBLEMS/after/visit-state-matrix-after/${eligible ? 'editable' : 'rescheduled'}-${width}.png`, fullPage: true });
          }
        }
        const before = snapshot(visit.id);
        const fields = { Diagnosis: 'Saved diagnosis', Treatment: 'Saved treatment', Prescription: 'Saved prescription', Remarks: 'Saved doctor remarks', FollowUp: '1' };
        if (eligible) {
          for (const field of ['Diagnosis', 'Treatment', 'Prescription', 'Remarks']) await form.locator(`[name="${field}"]`).fill(fields[field]);
          await form.locator('[name="FollowUp"]').check();
          await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Save visit notes' }).click()]);
          check((await page.locator('body').innerText()).includes('Visit notes saved.'), 'No-JS save confirmation missing');
        } else {
          const response = await context.request.post(`${base}doctor/visit.php?appt=${visit.id}`, { form: { _csrf: token, appointment_id: String(visit.id), ...fields } });
          check(response.ok() && (await response.text()).includes(disallowed ? `This appointment is ${visit.status}.` : 'This appointment has not started.'), 'Ineligible POST reason missing');
        }
        const after = snapshot(visit.id);
        if (!eligible) check(JSON.stringify(after) === JSON.stringify(before), `${visit.period} ${visit.status}: rejected save mutated data/notifications`);
        else {
          for (const field of ['Diagnosis', 'Treatment', 'Prescription']) check(after.appointment[field] === fields[field], `${field} not saved`);
          check(Number(after.appointment.FollowUp) === 1 && after.appointment.Status === 'Completed', 'Saved outcome/follow-up wrong');
          const remarks = after.remarks;
          check(remarks.reason === 'Patient reason' && remarks.doctor_remarks === fields.Remarks, 'Save lost patient reason or doctor remarks');
          check(JSON.stringify(after.notifications) === JSON.stringify(before.notifications), 'Notes save changed notifications');
        }
      }
      const beforeForeign = snapshot(seed.foreign);
      const get = await page.goto(`${base}doctor/visit.php?appt=${seed.foreign}`);
      check(get.status() === 404 && await page.locator('form.visit-form').count() === 0, 'Foreign visit GET not denied');
      const post = await context.request.post(`${base}doctor/visit.php`, { form: { _csrf: token, appointment_id: String(seed.foreign), Diagnosis: 'Unauthorized' } });
      check(post.status() === 404 && JSON.stringify(snapshot(seed.foreign)) === JSON.stringify(beforeForeign), 'Foreign save not denied/non-mutating');
      console.log('OK: test DB verified; JS-off future/past status matrix, foreign GET/POST, non-mutating rejected saves, all-field historical saves, focus and 1280/390 captures');
    } finally { await browser.close(); }
  });
} finally {
  fixture('cleanup', seed.doctor, seed.patient);
  if (previousDoctor === undefined) delete process.env.UI_DOCTOR;
  else process.env.UI_DOCTOR = previousDoctor;
}

