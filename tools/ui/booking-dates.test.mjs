import { chromium } from 'playwright';
import { testPhp } from './isolation.mjs';
import { withTestServer, login, appBase } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (code) => testPhp(['-r', `require 'clinic-base/lib/db.php'; ${code}`], { encoding: 'utf8', env: process.env }).trim();
const check = (condition, message) => { if (!condition) throw Error(message); };
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Singapore', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const day = offset => { const date = new Date(`${today}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + offset); return date.toISOString().slice(0, 10); };
const counts = () => php('echo q_val("SELECT COUNT(*) FROM `appointment`") . ":" . q_val("SELECT COUNT(*) FROM `notifications`");');

console.log(testPhp(['tools/db_reset.php', '--test'], { env: process.env, stdio: 'pipe', encoding: 'utf8' }).trim());
await withTestServer(true, async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const open = date => page.goto(`${appBase}book.php?doctor=1&date=${encodeURIComponent(date)}`, { waitUntil: 'networkidle' });
    await open(today);
    const todayLabel = await page.locator('.booking-doctor p').last().innerText();
    for (const [requested, reason] of [[day(-1), 'Past dates cannot be booked.'], [day(40), 'within the next 7 days'], ['2026-99-99', 'valid date']]) {
      await open(requested);
      check((await page.locator('[role="alert"]').innerText()).includes(reason), `${requested}: rejection reason missing`);
      check((await page.locator('[role="alert"]').innerText()).includes('Showing'), `${requested}: fallback not announced`);
      check(await page.locator('#date').inputValue() === today, `${requested}: displayed day differs from fallback`);
      check((await page.locator('.booking-doctor').innerText()).includes(todayLabel), `${requested}: doctor grid must show effective day`);
    }
    for (const offset of [0, 6]) {
      await open(day(offset));
      check(await page.locator('[role="alert"]').count() === 0, `day ${offset}: valid boundary rejected`);
      check(await page.locator('#date').inputValue() === day(offset), `day ${offset}: valid boundary changed`);
    }
    await page.goto(`${appBase}doctor.php?id=1`);
    for (const href of await page.locator('.available-slots a').evaluateAll(nodes => nodes.map(node => node.getAttribute('href')))) {
      const date = new URL(href, appBase).searchParams.get('date');
      check(date >= today && date <= day(6), 'doctor profile linked outside booking window');
    }

    await login(page, 'patient', appBase);
    const available = JSON.parse(php('echo json_encode(q_one("SELECT `slotID`, `DoctorID`, `SlotDateTime` FROM `slots` WHERE `DoctorID` = 1 AND `Status` = \'Available\' AND `SlotDateTime` > DATE_ADD(NOW(), INTERVAL 1 DAY) AND DATE(`SlotDateTime`) <= DATE_ADD(CURDATE(), INTERVAL 6 DAY) ORDER BY `SlotDateTime` LIMIT 1"));'));
    check(available, 'seed has no future slot inside booking window');
    await page.goto(`${appBase}book.php?doctor=1&date=${available.SlotDateTime.slice(0, 10)}`);
    const token = await page.locator('.slot-booking input[name="_csrf"]').first().inputValue();
    const far = day(40);
    php(`q("INSERT INTO \`slots\` (\`DoctorID\`, \`SlotDateTime\`, \`Status\`) VALUES (1, '${far} 09:00:00', 'Available')");`);
    const farId = Number(php('echo q_val("SELECT `slotID` FROM `slots` WHERE `DoctorID` = 1 AND DATE(`SlotDateTime`) = DATE_ADD(CURDATE(), INTERVAL 40 DAY) ORDER BY `slotID` DESC LIMIT 1");'));
    const before = counts();
    const rejected = await page.context().request.post(`${appBase}actions/book.php`, { form: { _csrf: token, slot_id: String(farId), doctor: '1', date: far, reason: 'Tampered date' } });
    check(rejected.ok() && (await rejected.text()).includes('outside the current booking window'), 'crafted far-slot POST must be rejected');
    check(counts() === before && php(`echo q_val("SELECT \`Status\` FROM \`slots\` WHERE \`slotID\` = ${farId}");`) === 'Available', 'rejection changed rows or notifications');

    php(`q("INSERT INTO \`appointment\` (\`DoctorID\`, \`PatientID\`, \`appointmentDateTime\`, \`Status\`) VALUES (1, (SELECT \`PatientID\` FROM \`patient\` WHERE \`User\` = 'alextan'), '${day(2)} 15:00:00', 'Future')");`);
    const appointment = { appointmentID: Number(php('echo q_val("SELECT `appointmentID` FROM `appointment` ORDER BY `appointmentID` DESC LIMIT 1");')), DoctorID: 1 };
    await page.goto(`${appBase}book.php?reschedule=${appointment.appointmentID}&doctor=${appointment.DoctorID}&date=${encodeURIComponent(day(-1))}`);
    check(await page.locator('[role="alert"]').count() === 1 && await page.locator('.reschedule-context').count() === 1, 'invalid reschedule date lost context');
    check(await page.locator('input[name="reschedule"]').first().inputValue() === String(appointment.appointmentID), 'reschedule filter lost id');
    const rescheduleToken = await page.locator('.slot-booking input[name="_csrf"]').first().inputValue().catch(() => token);
    const rescheduleBefore = counts();
    await page.context().request.post(`${appBase}actions/appointment.php`, { form: { _csrf: rescheduleToken, appointment_id: String(appointment.appointmentID), slot_id: String(farId), reschedule: '1' } });
    check(counts() === rescheduleBefore && php(`echo q_val("SELECT \`Status\` FROM \`slots\` WHERE \`slotID\` = ${farId}");`) === 'Available', 'crafted reschedule changed rows or notifications');

    const noJs = await browser.newPage({ javaScriptEnabled: false });
    await noJs.goto(`${appBase}book.php?doctor=1&date=${encodeURIComponent(day(40))}`);
    check(await noJs.locator('[role="alert"]').count() === 1, 'no-JS invalid date has no message');
    await noJs.locator('#date').fill(day(6));
    await Promise.all([noJs.waitForNavigation(), noJs.getByRole('button', { name: 'Show schedule' }).click()]);
    check(await noJs.locator('#date').inputValue() === day(6) && await noJs.locator('[role="alert"]').count() === 0, 'no-JS valid filter failed');
    await noJs.close();

    await page.goto(`${appBase}book.php?doctor=1&date=${available.SlotDateTime.slice(0, 10)}`);
    const form = page.locator(`.slot-booking form:has(input[name="slot_id"][value="${available.slotID}"])`);
    await form.locator('..').locator('summary').click();
    await form.locator('input[name="reason"]').fill('Date regression visit');
    await Promise.all([page.waitForNavigation(), form.locator('button').click()]);
    check(Number(php(`echo q_val("SELECT COUNT(*) FROM \`appointment\` WHERE \`slotID\` = ${available.slotID}");`)) === 1, 'valid future-slot booking failed');
    console.log(`OK: booking window ${today} through ${day(6)}; rejected dates and POSTs unchanged, valid booking, reschedule context, JS off`);
  } finally { await browser.close(); }
});
