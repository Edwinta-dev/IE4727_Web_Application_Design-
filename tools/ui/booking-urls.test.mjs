import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { testPhp } from './isolation.mjs';
import { withTestServer, visit, login, appBase } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const check = (condition, message) => { if (!condition) throw Error(message); };
const php = args => testPhp(args, { encoding: 'utf8' }).trim();
const reset = () => php(['tools/db_reset.php', '--test']);
const out = `UIPROBLEMS/after/issue144-${before ? 'before' : 'after'}-matrix`;
await mkdir(out, { recursive: true });
const measurements = [];
reset();
try {
  await withTestServer(true, async () => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of [3, 7, 12]) {
        reset();
        const fixture = JSON.parse(php(['tools/ui/booking-urls-fixture.php', String(count)]));
        const page = await browser.newPage({ javaScriptEnabled: false });
        try {
          for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            await visit(page, 'doctors.php');
            check(await page.locator('.doctor-directory tbody tr').count() === count, 'doctor list count');
            // The clinic exposes five canonical specialties; unknown fixture values are filtered.
            check(await page.locator('#specialty option').count() === Math.min(count, 5) + 1, 'specialty list count');
            const links = await page.locator('.doctor-directory-book').evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
            const unavailableLinks = await page.locator('.doctor-directory tbody tr').evaluateAll((rows, ids) => rows.filter(row =>
              ids.some(id => row.querySelector(`a[href$="doctor.php?id=${id}"]`))).map(row => row.querySelectorAll('.doctor-directory-book').length), fixture.unavailable);
            measurements.push({ count, width, links, unavailableLinks });
            if (!before) {
              check(unavailableLinks.every(n => n === 0), 'unavailable doctor has a Book link');
              for (const id of fixture.unavailable) {
                const row = page.locator(`tr:has(a[href$="doctor.php?id=${id}"])`);
                check((await row.innerText()).includes('No times in the next seven days'), 'missing availability explanation');
              }
              check(links.every(href => new URL(href, appBase).searchParams.has('doctor') && !href.includes('doctor_id')), 'noncanonical directory URL');
              const link = page.locator(`a.doctor-directory-book[href*="doctor=${fixture.doctor}&"]`);
              await link.click();
              check(await page.locator('#date').inputValue() === fixture.date, 'directory link did not land on next available date');
            }
            await visit(page, `book.php?doctor_id=${fixture.doctor}&date=${fixture.date}`);
            check(await page.locator('#doctor').inputValue() === String(fixture.doctor), 'legacy deep link doctor');
            check(await page.locator('#date').inputValue() === fixture.date, 'legacy deep link date ignored');
            check(await page.locator('.day-tab.selected').count() === 1, 'selected day missing');
            check(await page.locator('.slot').count() === count, 'slot list count');
            await page.screenshot({ path: `${out}/book-${count}-${width}.png`, fullPage: true });
            await page.locator('#date').fill(fixture.date);
            await page.getByRole('button', { name: 'Show schedule' }).click();
            check(new URL(page.url()).searchParams.get('doctor') === String(fixture.doctor), 'filter lost canonical doctor');
            check(await page.locator('#date').inputValue() === fixture.date, 'filter lost date');
            await visit(page, 'doctors.php');
            // Reveal the action column on narrow screens for visual evidence.
            await page.locator('.table-scroll').evaluate(n => { n.scrollLeft = n.scrollWidth; });
            await page.screenshot({ path: `${out}/doctors-${count}-${width}.png`, fullPage: true });
          }
          if (!before && count === 3) {
            for (const [role, forged, slot] of [['patient', 'doctor', fixture.slots[0]], ['doctor', 'patient', fixture.originalSlot]]) {
              const actorPage = await browser.newPage({ javaScriptEnabled: false });
              try {
                await login(actorPage, role);
                await visit(actorPage, `book.php?reschedule=${fixture.appointment}&doctor=${fixture.doctor}&date=${fixture.date}`);
                check(await actorPage.locator('.slot-booking input[name="actor"]').count() === 0, 'untrusted actor field remains');
                const token = await actorPage.locator('.slot-booking input[name="_csrf"]').first().inputValue();
                const response = await actorPage.context().request.post(`${appBase}actions/appointment.php`, { form: {
                  _csrf: token, appointment_id: String(fixture.appointment), slot_id: String(slot), reschedule: '1', actor: forged,
                }});
                check((await response.text()).includes('Your appointment has been rescheduled.'), `${role} reschedule failed`);
                const messages = JSON.parse(php(['tools/ui/booking-urls-fixture.php', 'state', String(fixture.appointment)]));
                check(messages.length === (role === 'patient' ? 2 : 4), 'reschedule notification count');
                check(messages.slice(-2).every(row => row.Body.includes(`rescheduled by the ${role}`)), 'POST actor overrode session');
              } finally { await actorPage.close(); }
            }
          }
        } finally { await page.close(); }
      }
    } finally { await browser.close(); }
  });
  await writeFile(`${out}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log(`${before ? 'BEFORE' : 'OK'}: #144 booking URLs and dates; ${before ? 'recorded availability' : 'availability and session actor verified'}; 3/7/12 doctor, slot and raw specialty fixtures (canonical specialties capped at five); 1280/390px; no JS`);
} finally { reset(); }
