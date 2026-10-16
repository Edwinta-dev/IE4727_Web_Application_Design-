import { chromium } from 'playwright';
import { testPhp } from './isolation.mjs';
import { withTestServer, login, appBase } from './lib.mjs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const root = resolve(import.meta.dirname, '../..');
const php = code => testPhp(['-r', `require 'clinic-base/lib/db.php'; require 'clinic-base/models/booking.php'; ${code}`], { cwd: root, env: process.env, encoding: 'utf8' }).trim();
console.log(testPhp(['tools/db_reset.php', '--test'], { cwd: root, env: process.env, encoding: 'utf8' }).trim());
const fixture = JSON.parse(php(`$slot = q_one("SELECT s.slotID, DATE(s.SlotDateTime) AS day FROM slots s JOIN doctor d ON d.DoctorID = s.DoctorID WHERE d.User = 'drsmith' AND s.Status = 'Available' AND s.SlotDateTime > DATE_ADD(NOW(), INTERVAL 2 DAY) ORDER BY s.SlotDateTime LIMIT 1"); $patient = q_one("SELECT PatientID, FullName FROM patient WHERE User = 'alextan'"); $booking = book_appointment((int)$patient['PatientID'], (int)$slot['slotID'], 'Schedule editor test'); echo json_encode(['slot' => $slot['slotID'], 'day' => $slot['day'], 'patient' => $patient['FullName'], 'appointment' => $booking['appointment_id']]);`));
await withTestServer(true, async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await login(page, 'doctor', appBase);
        await page.goto(`${appBase}doctor/schedule.php?date=${fixture.day}`);
        assert.equal(await page.getByRole('heading', { name: 'Set working hours' }).count(), 1);
        assert.equal(await page.getByRole('heading', { name: /Block time on/ }).count(), 1);
        assert.equal(await page.locator('.schedule-legend li').count(), 4);
        for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            const layout = await page.evaluate(() => {
                const top = selector => document.querySelector(selector).getBoundingClientRect().top;
                return { calendar: top('.month-grid'), day: top('.day-view'), hours: top('.schedule-generator'), overflow: document.documentElement.scrollWidth > innerWidth };
            });
            assert.ok(layout.calendar < layout.day && layout.day < layout.hours, `${width}: date, block controls, then working hours`);
            assert.equal(layout.overflow, false, `${width}: no horizontal page overflow`);
        }
        const form = page.locator(`.schedule-booked-form:has(input[value="${fixture.slot}"])`);
        assert.match(await form.innerText(), new RegExp(fixture.patient));
        await form.locator('input[name="confirm_booking"]').check();
        page.once('dialog', dialog => { assert.match(dialog.message(), new RegExp(fixture.patient)); dialog.accept(); });
        await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Block booked slot' }).click()]);
        assert.equal(await page.locator('.day-view .slot.blocked').count() > 0, true);
        const result = JSON.parse(php(`$id = ${Number(fixture.appointment)}; $a = q_one('SELECT Status FROM appointment WHERE appointmentID = :id', ['id' => $id]); $n = q_one('SELECT COUNT(*) AS total FROM notifications WHERE appointmentID = :id', ['id' => $id]); echo json_encode(['status' => $a['Status'], 'notifications' => $n['total']]);`));
        assert.equal(result.status, 'Cancelled');
        assert.ok(result.notifications >= 2);
        const nextDate = new Date(`${fixture.day}T00:00:00Z`);
        nextDate.setUTCDate(nextDate.getUTCDate() + 1);
        const nextDay = nextDate.toISOString().slice(0, 10);
        await Promise.all([page.waitForNavigation(), page.locator('#schedule-date').fill(nextDay)]);
        assert.equal(await page.locator('#schedule-date').inputValue(), nextDay);
        assert.equal(await page.locator('#start-date').inputValue(), nextDay);
        console.log('OK: #193 1280/390 layout, booked patient confirmation, cancellation notification, date auto-submit and status legend');
    } finally { await browser.close(); }
});
