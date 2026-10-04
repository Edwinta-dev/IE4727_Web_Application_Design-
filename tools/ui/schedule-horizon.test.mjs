import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { withServer, login, appBase } from './lib.mjs';

const root = resolve(import.meta.dirname, '../..');
process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (code, ...args) => execFileSync('php', ['-r', `require 'clinic-base/lib/db.php'; ${code}`, ...args], { cwd: root, env: process.env, encoding: 'utf8' }).trim();
const check = (condition, message) => { if (!condition) throw Error(message); };
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Singapore', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const day = offset => { const date = new Date(`${today}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + offset); return date.toISOString().slice(0, 10); };
const count = date => Number(php('echo q_val("SELECT COUNT(*) FROM `slots` WHERE `DoctorID` = (SELECT `DoctorID` FROM `doctor` WHERE `User` = :user) AND DATE(`SlotDateTime`) = :date", ["user" => "drsmith", "date" => $argv[1]]);', date));
const notifications = () => Number(php('echo q_val("SELECT COUNT(*) FROM `notifications`");'));

execFileSync('php', ['tools/db_reset.php', '--test'], { cwd: root, env: process.env, stdio: 'pipe' });
await withServer(true, async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await login(page, 'doctor', appBase);
    const open = async date => page.goto(`${appBase}doctor/schedule.php?date=${encodeURIComponent(date)}`, { waitUntil: 'networkidle' });
    const generate = async (date, days = 1) => {
      await page.locator('#start-date').fill(date);
      await page.locator('#days').fill(String(days));
      await page.locator('#start-time').fill('09:00');
      await page.locator('#end-time').fill('11:00');
      await page.getByRole('checkbox', { name: 'Sunday' }).uncheck();
      await Promise.all([page.waitForNavigation(), page.locator('.schedule-generator button').click()]);
    };
    const before40 = count(day(40));
    await open(today);
    await generate(day(40));
    check(page.url().endsWith(`date=${day(40)}`), 'generation must redirect to day +40');
    check(await page.locator('.day-view .slot').count() === before40 + 4, 'day +40 must render four generated slots');
    check(count(day(40)) === before40 + 4, 'day +40 row count must increase by four');
    check((await page.locator('#schedule-date').inputValue()) === day(40), 'date navigation must show day +40');
    const evidence = resolve(root, 'UIPROBLEMS/after/schedule-horizon-after');
    await mkdir(evidence, { recursive: true });
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      await page.screenshot({ path: resolve(evidence, `doctor-schedule-day40-${width}.png`), fullPage: true });
    }
    await Promise.all([page.waitForNavigation(), page.locator('.day-view button', { hasText: 'Block slot' }).first().click()]);
    check(page.url().endsWith(`date=${day(40)}`), 'blocking must preserve day +40');
    check(await page.locator('.day-view button', { hasText: 'Make available' }).count() === 1, 'blocked slot must render');
    await Promise.all([page.waitForNavigation(), page.locator('.day-view button', { hasText: 'Make available' }).click()]);
    check(page.url().endsWith(`date=${day(40)}`), 'reopening must preserve day +40');
    await page.reload();
    check(count(day(40)) === before40 + 4, 'PRG refresh must not add rows');

    await generate(day(364));
    check(page.url().endsWith(`date=${day(364)}`), 'last supported date must open');
    check(count(day(364)) === 4, 'last supported date must generate four rows');
    const noticesBefore = notifications();
    await open(today);
    const token = await page.locator('.schedule-generator input[name="_csrf"]').inputValue();
    const rejected = await page.context().request.post(`${appBase}doctor/schedule.php`, { form: { _csrf: token, action: 'generate', start_date: day(365), days: '1', start_time: '09:00', end_time: '11:00', slot_length: '30' } });
    check(rejected.ok(), 'out-of-range POST must redirect normally');
    check((await rejected.text()).includes('365-day management window'), 'rejected generation must explain the limit');
    check(count(day(365)) === 0 && notifications() === noticesBefore, 'day beyond limit must create no rows or notices');
    await open(day(365));
    check(await page.locator('[role="alert"]').count() > 0 && (await page.locator('#schedule-date').inputValue()) === today, 'unsupported GET must report fallback date');
    await open('2026-99-99');
    check(await page.locator('[role="alert"]').count() > 0 && (await page.locator('#schedule-date').inputValue()) === today, 'malformed GET must report fallback date');

    const later = day(366);
    php('q("INSERT INTO `slots` (`DoctorID`, `SlotDateTime`) VALUES ((SELECT `DoctorID` FROM `doctor` WHERE `User` = :user), :when)", ["user" => "drsmith", "when" => $argv[1] . " 09:00:00"]);', later);
    await open(later);
    check((await page.locator('#schedule-date').inputValue()) === later && await page.locator('.day-view .slot').count() === 1, 'owned existing date beyond limit must remain manageable');
    check(notifications() === noticesBefore, 'schedule rejection must not create notifications');
    const noJs = await browser.newPage({ javaScriptEnabled: false });
    await login(noJs, 'doctor', appBase);
    await noJs.goto(`${appBase}doctor/schedule.php?date=${day(40)}`);
    check(await noJs.locator('.day-view .slot').count() === before40 + 4, 'day +40 must render without JavaScript');
    await noJs.locator('#schedule-date').fill(day(364));
    await noJs.getByRole('button', { name: 'Show date' }).focus();
    await Promise.all([noJs.waitForNavigation(), noJs.keyboard.press('Enter')]);
    check((await noJs.locator('#schedule-date').inputValue()) === day(364), 'keyboard date navigation must work without JavaScript');
    await noJs.close();
    console.log(`OK: management 365 days, ${today} through ${day(364)}; day +40 ${before40}->${count(day(40))} rows, edge 4, beyond 0; PRG/block/reopen and invalid-date messages`);
  } finally { await browser.close(); }
});
