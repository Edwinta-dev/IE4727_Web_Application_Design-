import { chromium } from 'playwright';
import { testPhp } from './isolation.mjs';
import { withTestServer, login, appBase } from './lib.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const root = resolve(import.meta.dirname, '../..');
const php = (code, ...args) => testPhp(['-r', `require 'clinic-base/lib/db.php'; ${code}`, ...args], { cwd: root, env: process.env, encoding: 'utf8' }).trim();
const check = (condition, message) => { if (!condition) throw Error(message); };
console.log(testPhp(['tools/db_reset.php', '--test'], {cwd: root, env: process.env, encoding: 'utf8'}).trim());
const today = php('echo date("Y-m-d");');
const day = offset => { const date = new Date(`${today}T00:00:00Z`); date.setUTCDate(date.getUTCDate() + offset); return date.toISOString().slice(0, 10); };
// Beyond seeded dates: real database lists of three sizes and all slot states.
for (const [offset, count] of [[32, 3], [33, 7], [34, 12]]) {
 php('for ($i = 0; $i < (int) $argv[2]; $i++) { q("INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES ((SELECT `DoctorID` FROM `doctor` WHERE `User` = :user), :when, :status)", ["user" => "drsmith", "when" => $argv[1] . sprintf(" %02d:%02d:00", 9 + intdiv($i, 2), ($i % 2) * 30), "status" => ["Available", "Booked", "Blocked"][$i % 3]]); }', day(offset), String(count));
}
const evidence = resolve(root, 'UIPROBLEMS/after/issue148-regression');
await mkdir(evidence, { recursive: true });
await withTestServer(true, async () => {
 const browser = await chromium.launch({headless: true});
 try {
  const page = await browser.newPage({javaScriptEnabled: false});
  await login(page, 'doctor', appBase);
  const open = date => page.goto(`${appBase}doctor/schedule.php?date=${date}`, {waitUntil: 'load'});
  const measurements = [];
  for (const width of [1280, 390]) {
   await page.setViewportSize({width, height: width === 1280 ? 800 : 844});
   for (const [offset, count] of [[32, 3], [33, 7], [34, 12]]) {
    await open(day(offset));
    check(await page.locator('.day-view .slot').count() === count, `${width}: expected ${count} database slots`);
    check(await page.locator('input[type="date"]').count() === 1, 'one date picker must serve navigation and generation');
    check(await page.locator('#start-date').inputValue() === day(offset), 'generator starts on opened day');
    check((await page.locator('.schedule-weekday').allTextContents()).join(',') === 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'weekday headers must be Monday first');
    const days = await page.locator('.schedule-day').evaluateAll(elements => elements.map(el => ({date:el.querySelector('time').dateTime, x:el.getBoundingClientRect().left})));
    const headers = await page.locator('.schedule-weekday').evaluateAll(elements => elements.map(el => el.getBoundingClientRect().left));
    for (const item of days) check(Math.abs(item.x - headers[(new Date(`${item.date}T00:00:00Z`).getUTCDay() + 6) % 7]) < 1, `date ${item.date} must align to its weekday`);
    check(await page.locator('.schedule-day.selected [aria-current="date"]').count() === 1, 'selected day must be announced');
    check(await page.locator('.schedule-day.no-slots').count() > 0, 'dates without slots must be muted');
    check((await page.locator('.day-view .slot.blocked .slot-status').first().textContent()).trim() === 'Blocked', 'slot label must say Blocked');
    check((await page.locator('.schedule-day-counts').innerText()).includes('Blocked:'), 'counter must use the same term');
    const metrics = await page.evaluate(() => {
     const rect = s => {const r=document.querySelector(s).getBoundingClientRect();return {top:r.top,width:r.width,height:r.height};};
     return {width:innerWidth, calendar:rect('.schedule-month-grid'), day:rect('.day-view'), generator:rect('.schedule-generator'), input:rect('#days'), columns:getComputedStyle(document.querySelector('.schedule-month-grid')).gridTemplateColumns.split(' ').length, generatorColumns:getComputedStyle(document.querySelector('.schedule-generator form')).gridTemplateColumns.split(' ').length, checkboxRows:new Set([...document.querySelectorAll('.days-to-skip label')].map(el=>el.getBoundingClientRect().top)).size, padding:parseFloat(getComputedStyle(document.querySelector('.slot-list .slot')).paddingLeft), overflow:document.documentElement.scrollWidth>innerWidth};
    });
    check(metrics.columns === 7 && metrics.generatorColumns >= 2, 'calendar/generator column counts');
    check(metrics.calendar.top < metrics.day.top && metrics.day.top < metrics.generator.top, 'calendar and working day precede generator');
    check(!metrics.overflow && metrics.padding >= 12, 'no page overflow and padded slots');
    if (width === 1280) check(metrics.checkboxRows === 1 && metrics.calendar.top < 800 && metrics.generator.height < 500, 'desktop schedule visible and generator compact');
    measurements.push({count, ...metrics});
    await page.screenshot({path:resolve(evidence, `slots-${count}-${width}.png`), fullPage:true});
   }
  }
  await open(day(32));
  await Promise.all([page.waitForNavigation(), page.locator('.day-view button', {hasText:'Block slot'}).first().click()]);
  check(await page.locator('.day-view .slot.blocked').count() === 2, 'blocking available slot must persist');
  await Promise.all([page.waitForNavigation(), page.locator('.day-view button', {hasText:'Make available'}).first().click()]);
  check(await page.locator('.day-view .slot.blocked').count() === 1, 'reopening slot must persist');
  await page.locator('#schedule-date').fill(day(45));
  await page.getByRole('button', {name:'Show date'}).focus();
  await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
  check(await page.locator('#start-date').inputValue() === day(45), 'keyboard navigation sets generator date without JavaScript');
  await page.locator('#days').fill('1');
  await page.locator('#start-time').fill('09:00');
  await page.locator('#end-time').fill('11:00');
  await page.getByRole('checkbox', {name:'Sunday'}).uncheck();
  await Promise.all([page.waitForNavigation(), page.getByRole('button', {name:'Generate slots'}).click()]);
  check(page.url().endsWith(`date=${day(45)}`) && await page.locator('.day-view .slot').count() === 4, 'generation must redirect to selected date with four slots');
  await page.reload();
  check(await page.locator('.day-view .slot').count() === 4, 'refresh must not duplicate generated slots');
  await writeFile(resolve(evidence,'measurements.json'),JSON.stringify(measurements,null,2));
  console.log(JSON.stringify(measurements));
  console.log('OK: #148 weekday alignment; 3/7/12 slots at 1280/390; selected/empty states; padding; single picker; keyboard generation and block/reopen without JavaScript; PRG');
 } finally {await browser.close();}
});
