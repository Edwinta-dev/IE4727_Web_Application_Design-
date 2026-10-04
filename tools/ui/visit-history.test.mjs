import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { withTestServer, login } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const fixture = (...args) => testPhp(['tools/ui/visit-history-fixture.php', ...args], { encoding: 'utf8' }).trim();
const seed = JSON.parse(fixture('seed'));
const check = (condition, message) => { if (!condition) throw Error(message); };
const baseline = process.argv.includes('--baseline');
const label = baseline ? 'visit-history-before' : 'visit-history-flow-after';
const priorDoctor = process.env.UI_DOCTOR;
const priorPatient = process.env.UI_PATIENT;
process.env.UI_DOCTOR = seed.accounts.doctor.own.user;
process.env.UI_PATIENT = seed.accounts.patient.own.user;
try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext({ javaScriptEnabled: false });
      const page = await context.newPage();
      await login(page, 'doctor', base);
      const route = `${base}doctor/visit.php?appt=${seed.visits.current}`;
      const history = page.locator('.patient-history');
      const verifyHistory = async saved => {
        const records = history.locator('.visit-history-record');
        check(await records.count() === (baseline ? (saved ? 5 : 4) : 2), 'Wrong history record count');
        for (const name of ['earlier', 'earliest']) {
          check(await records.filter({ hasText: `Diagnosis: History ${name}` }).count() === 1, `${name} must appear once`);
        }
        const text = await history.innerText();
        for (const name of ['foreign_doctor', 'foreign_patient', 'cancelled', 'no_show', 'future_status', 'rescheduled']) {
          check(!text.includes(`History ${name}`), `${name} leaked into history`);
        }
        check(text.includes('History later') === baseline, 'Later encounter history boundary');
        check(text.includes('Saved diagnosis') === (baseline && saved), 'Current notes history exclusion');
        if (!baseline) check((await records.first().innerText()).includes('History earlier'), 'History newest first');
      };
      const measurements = [];
      const capture = async state => {
        await mkdir(`UIPROBLEMS/after/${label}`, { recursive: true });
        for (const width of [1280, 390]) {
          await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
          const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
          check(overflow <= 0, 'Viewport overflow');
          measurements.push({ state, width, overflow, records: await history.locator('.visit-history-record').count() });
          await page.screenshot({ path: `UIPROBLEMS/after/${label}/${state}-${width}.png`, fullPage: true });
        }
      };
      await page.goto(route);
      await verifyHistory(false);
      await capture('before-save');
      for (const name of ['Diagnosis', 'Treatment', 'Prescription', 'Remarks']) {
        await page.locator(`.visit-form [name="${name}"]`).fill(`Saved ${name === 'Remarks' ? 'doctor remarks' : name.toLowerCase()}`);
      }
      await page.locator('[name="FollowUp"]').check();
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save visit notes' }).click()]);
      check((await page.locator('main').innerText()).includes('Visit notes saved.'), 'Save confirmation missing');
      for (const refresh of [false, true]) {
        if (refresh) await page.reload();
        await verifyHistory(true);
        for (const name of ['Diagnosis', 'Treatment', 'Prescription', 'Remarks']) {
          check(await page.locator(`.visit-form [name="${name}"]`).inputValue() === `Saved ${name === 'Remarks' ? 'doctor remarks' : name.toLowerCase()}`, `${name} lost after save/refresh`);
        }
        check(await page.locator('[name="FollowUp"]').isChecked(), 'Follow-up lost');
        check((await page.locator('.visit-form-section').innerText()).includes('Patient reason: Reason current'), 'Patient reason lost');
      }
      await capture('saved-refresh');
      if (!baseline) {
        await page.goto(`${base}doctor/visit.php?appt=${seed.visits.earliest}`);
        check(await history.locator('.visit-history-record').count() === 0 && (await history.innerText()).includes('No previous visits recorded'), 'Honest empty history missing');
        await capture('empty');
        const token = await page.locator('input[name="_csrf"]').first().inputValue();
        const foreignGet = await page.goto(`${base}doctor/visit.php?appt=${seed.visits.foreign_doctor}`);
        check(foreignGet.status() === 404 && !(await page.locator('body').innerText()).includes('History foreign_doctor'), 'Foreign doctor GET leaks data');
        const foreignPost = await context.request.post(`${base}doctor/visit.php`, { form: { _csrf: token, appointment_id: String(seed.visits.foreign_doctor), Diagnosis: 'Unauthorized' } });
        check(foreignPost.status() === 404, 'Foreign doctor POST permitted');
        const patientContext = await browser.newContext({ javaScriptEnabled: false });
        const patientPage = await patientContext.newPage();
        await login(patientPage, 'patient', base);
        await patientPage.goto(`${base}patient/home.php?view=${seed.visits.current}`);
        const notes = await patientPage.locator('.visit-notes').innerText();
        for (const value of ['Saved diagnosis', 'Saved treatment', 'Saved prescription', 'Follow-up: Yes', 'Reason current', 'Saved doctor remarks']) check(notes.includes(value), `Patient notes missing ${value}`);
        await patientPage.goto(`${base}patient/home.php?view=${seed.visits.foreign_patient}`);
        check(await patientPage.locator('.visit-notes').count() === 0, 'Foreign patient notes visible');
        const patientDenied = await patientContext.request.get(route, { maxRedirects: 0 });
        check(patientDenied.status() === 302 && patientDenied.headers().location.includes('/index.php?next='), 'Patient doctor-visit request was not redirected by the guard');
        check(!(await patientDenied.text()).includes('History current'), 'Denied request leaked visit data');
      }
      await writeFile(`UIPROBLEMS/after/${label}/measurements.json`, JSON.stringify({ now: seed.now, database: 'ie4727db_test', measurements }, null, 2));
      console.log(baseline ? 'OK: reproduced later/same-time history and saved current visit duplication' : 'OK: no-JS save/refresh, earlier-only history, empty state, all notes, patient/doctor access; zero overflow at 1280/390');
    } finally { await browser.close(); }
  });
} finally {
  fixture('cleanup', JSON.stringify(seed));
  for (const [key, value] of [['UI_DOCTOR', priorDoctor], ['UI_PATIENT', priorPatient]]) {
    if (value === undefined) delete process.env[key]; else process.env[key] = value;
  }
}
