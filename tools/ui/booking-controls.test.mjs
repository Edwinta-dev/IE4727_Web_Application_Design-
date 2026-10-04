import assert from 'node:assert/strict';
import { writeFile, mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';
import { withTestServer, visit, login } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = `UIPROBLEMS/after/issue147-${before ? 'before' : 'after'}-matrix`;
await mkdir(output, { recursive: true });
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const records = [];
php('tools/db_reset.php', '--test');
const fixture = JSON.parse(php('-r', `require 'clinic-base/models/slots.php';
    for ($offset = 1; $offset < 7; $offset++) {
        $date = date('Y-m-d', strtotime("+$offset days"));
        $slots = slots_for_day(1, $date);
        if (count($slots) >= 12) { echo json_encode(['date' => $date, 'slots' => $slots]); break; }
    }`));
try {
    await withTestServer(true, async base => {
        const browser = await chromium.launch({ headless: true });
        try {
            for (const count of before ? [5] : [3, 7, 12]) {
                if (!before) {
                    php('tools/db_reset.php', '--test');
                    php('tools/ui/featured-doctors-fixture.php', String(count));
                }
                for (const width of [1280, 390]) {
                    for (const js of before ? [true] : [true, false]) {
                        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, javaScriptEnabled: js, reducedMotion: 'reduce' });
                        try {
                            const path = `book.php?doctor=1&date=${fixture.date}`;
                            await visit(page, path, base);
                            const measured = await page.evaluate(() => {
                                const tabs = document.querySelector('.day-tabs');
                                return { tabsWidth: tabs.clientWidth, tabsScrollWidth: tabs.scrollWidth,
                                    filterHeight: document.querySelector('.booking-filters').getBoundingClientRect().height,
                                    dateType: document.querySelector('[name="date"]').type,
                                    timeVisible: !!document.querySelector('#from').getClientRects().length };
                            });
                            records.push({ count, width, js, ...measured });
                            console.log(JSON.stringify(records.at(-1)));
                            await page.screenshot({ path: `${output}/${count}-${width}-${js}-default.png`, fullPage: true });
                            if (before) continue;
                            assert.equal(measured.dateType, 'hidden', 'Day tabs are the sole date control');
                            assert.equal(measured.timeVisible, false, 'Time filters are optional and initially closed');
                            assert.equal(await page.locator('#doctor option:not([value=""])').count(), count);
                            assert.equal(await page.locator('.day-tab').count(), 7, 'Fixed seven-day booking window');
                            assert.equal(await page.locator('.day-tab.selected').count(), 1);
                            assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);
                            await page.locator('.booking-time-filter summary').click();
                            assert.ok(await page.locator('#from').isVisible());
                            const from = fixture.slots[0].SlotTime.slice(0, 5);
                            const to = fixture.slots[count - 1].SlotTime.slice(0, 5);
                            await page.locator('#from').fill(from);
                            await page.locator('#to').fill(to);
                            await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Show schedule' }).click()]);
                            assert.equal(await page.locator('.schedule-grid > .slot').count(), count);
                            assert.ok(await page.locator('#from').isVisible(), 'Active filters stay visible');
                            if (width === 390) {
                                assert.ok(await page.locator('.day-scroll-hint').isVisible());
                                if (js) {
                                    assert.ok(await page.getByRole('button', { name: 'Show later days' }).isVisible());
                                    await page.getByRole('button', { name: 'Show later days' }).click();
                                    assert.ok(await page.locator('.day-tabs').evaluate(el => el.scrollLeft > 0));
                                    await page.getByRole('button', { name: 'Show earlier days' }).click();
                                    assert.equal(await page.locator('.day-tabs').evaluate(el => el.scrollLeft), 0);
                                }
                            }
                            const last = page.locator('.day-tab').last();
                            const href = await last.getAttribute('href');
                            await Promise.all([page.waitForNavigation(), last.click()]);
                            assert.equal(await page.locator('.booking-filters [name="date"]').inputValue(), new URL(href, base).searchParams.get('date'));
                            assert.equal(await page.locator('#from').inputValue(), from);
                            await page.screenshot({ path: `${output}/${count}-${width}-${js}-filtered-last-day.png`, fullPage: true });
                            if (js && width === 390 && count === 3) {
                                await login(page, 'patient', base);
                                await page.getByRole('button', { name: 'Menu' }).click();
                                assert.ok(await page.getByRole('link', { name: 'Book an appointment', exact: true }).isVisible());
                            }
                        } finally { await page.close(); }
                    }
                }
            }
        } finally { await browser.close(); }
    });
} finally {
    await writeFile(`${output}/measurements.json`, JSON.stringify(records, null, 2));
    php('tools/db_reset.php', '--test');
}
console.log(before ? 'OK: before measurements captured' : 'OK: booking controls, patient Book link, 3/7/12 doctors and slots, 1280/390px, JS on/off, reduced motion');
