import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { withServer, login, appBase, root } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (...args) => execFileSync('php', ['tools/ui/admin-flash-fixture.php', ...args], { cwd: root, env: process.env, encoding: 'utf8' }).trim();
const check = (value, message) => { if (!value) throw Error(message); };

execFileSync('php', ['tools/db_reset.php', '--test'], { cwd: root, env: process.env, stdio: 'pipe' });
const fixture = JSON.parse(php('seed'));
const ids = ['doctor', 'patient', 'slot', 'doctorAppointment', 'patientAppointment'].map(key => String(fixture[key]));
const counts = () => JSON.parse(php('counts', ...ids));
check(Object.values(counts()).slice(0, 5).every(value => value === 1), 'synthetic accounts and dependent rows must exist');

await withServer(true, async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const confirmationPage = await browser.newPage();
    await login(confirmationPage, 'admin', appBase);
    for (const width of [1280, 390]) {
      await confirmationPage.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      const styles = await confirmationPage.evaluate(() => {
        const destructive = document.querySelector('.destructive-action');
        const ordinary = document.querySelector('.console-filters button');
        const rect = destructive.getBoundingClientRect();
        const danger = getComputedStyle(destructive);
        const primary = getComputedStyle(ordinary);
        const luminance = color => color.match(/\d+/g).slice(0, 3).map(Number).map(value => value / 255).map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
        const a = luminance(danger.backgroundColor), b = luminance(danger.color);
        return { width: rect.width, height: rect.height, danger: danger.backgroundColor, foreground: danger.color, primary: primary.backgroundColor, contrast: (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) };
      });
      check(styles.width >= 44 && styles.height >= 44, `${width}px destructive target must be at least 44px square`);
      check(styles.foreground === 'rgb(255, 255, 255)' && styles.danger !== styles.primary && styles.contrast >= 4.5, `${width}px delete action must be readable and distinct from apply filters: ${JSON.stringify(styles)}`);
      await confirmationPage.locator('.destructive-action').first().focus();
      check(await confirmationPage.locator('.destructive-action').first().evaluate(button => getComputedStyle(button).outlineStyle !== 'none'), `${width}px delete action must show keyboard focus`);
    }
    const appointmentTimes = await confirmationPage.locator('.appointment-table td:first-child time').evaluateAll(times => times.map(time => ({ visible: time.textContent.trim(), machine: time.dateTime })));
    check(appointmentTimes.length > 0 && appointmentTimes.every(time => /^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$/.test(time.machine) && !/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/.test(time.visible)), 'appointment cells must have readable text and machine-readable local times');
    const beforeCancel = counts();
    confirmationPage.once('dialog', dialog => dialog.dismiss());
    await confirmationPage.locator('section.console-section tr', { hasText: 'Flash Test Doctor' }).getByRole('button', { name: 'Delete account' }).click();
    check(JSON.stringify(counts()) === JSON.stringify(beforeCancel), 'cancelling confirmation must leave accounts, appointments, and notifications unchanged');
    check(await confirmationPage.locator('.flash').count() === 0, 'cancelled confirmation must not show a deletion flash');
    await confirmationPage.close();

    const page = await browser.newPage({ javaScriptEnabled: false });
    await login(page, 'admin', appBase);
    check(page.url().includes('/admin/console.php'), 'admin console must be authenticated');
    const before = counts();
    const token = await page.locator('form[method="post"] input[name="_csrf"]').first().inputValue();
    const rejection = await page.context().request.post(appBase + 'admin/console.php', { form: { _csrf: token, action: 'delete_doctor', id: '99999999' } });
    check(rejection.ok() && (await rejection.text()).includes('That account could not be found. No account was removed.'), 'missing account must give visible rejection after redirect');
    check(JSON.stringify(counts()) === JSON.stringify(before), 'rejected deletion must change no rows or notifications');
    await page.goto(appBase + 'admin/console.php');
    check(await page.locator('.flash').count() === 0, 'rejected flash must not recur on navigation');

    const doctorRow = page.locator('section.console-section tr', { hasText: 'Flash Test Doctor' });
    await doctorRow.getByRole('button', { name: 'Delete account' }).focus();
    await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
    check(page.url().includes('/admin/console.php?'), 'doctor deletion must redirect');
    check(await page.getByRole('status').filter({ hasText: 'Doctor account and its slots and appointments were removed.' }).count() === 1, 'doctor success must be visible immediately');
    let current = counts();
    check(current.doctor === 0 && current.slot === 0 && current.doctorAppointment === 0 && current.patient === 1 && current.patientAppointment === 1, 'doctor deletion must cascade only its slot and appointment');
    check(current.orphanNotifications === 1 && current.notifications === before.notifications, 'doctor deletion must retain its notification as orphan');
    await page.reload();
    check(await page.locator('.flash').count() === 0, 'doctor success must be consumed on refresh');
    await page.goto(appBase + 'admin/outbox.php');
    check(await page.locator('.flash').count() === 0, 'doctor success must not appear on outbox');

    await page.goto(appBase + 'admin/console.php');
    await Promise.all([page.waitForNavigation(), page.locator('section.console-section tr', { hasText: 'Flash Test Patient' }).getByRole('button', { name: 'Delete account' }).click()]);
    check(await page.getByRole('status').filter({ hasText: 'Patient account and its appointments were removed.' }).count() === 1, 'patient success must be visible immediately');
    current = counts();
    check(current.patient === 0 && current.patientAppointment === 0 && current.orphanNotifications === 2 && current.notifications === before.notifications, 'patient deletion must cascade its appointment and retain notifications');
    await page.reload();
    check(await page.locator('.flash').count() === 0, 'patient success must be consumed on refresh');

    const cookie = (await page.context().cookies()).find(item => item.name === 'PHPSESSID');
    const sessionDir = cookie && readdirSync(root).find(name => name.startsWith('.ui-sessions-') && existsSync(resolve(root, name, `sess_${cookie.value}`)));
    check(cookie && sessionDir, 'authenticated session fixture must be available');
    php('flash', resolve(root, sessionDir), cookie.value);
    await page.goto(appBase + 'admin/outbox.php');
    check(await page.getByRole('status').filter({ hasText: 'Outbox fixture <once>' }).count() === 1, 'outbox fixture flash must render without JavaScript');
    check((await page.locator('.flash').innerHTML()).includes('&lt;once&gt;'), 'outbox flash must escape markup');
    await page.reload();
    check(await page.locator('.flash').count() === 0, 'outbox flash must be consumed on refresh');
    await page.goto(appBase + 'admin/console.php');
    check(await page.locator('.flash').count() === 0, 'outbox flash must not leak to console');
    await page.close();
    console.log('OK: authenticated admin, synthetic doctor/patient cascades, PRG, rejected action, one-time outbox flash, keyboard and JS off');
  } finally { await browser.close(); }
});
