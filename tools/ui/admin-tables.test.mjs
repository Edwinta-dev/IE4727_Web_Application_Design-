import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { withTestServer, login, root } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (...args) => testPhp(args, { cwd: root, encoding: 'utf8' }).trim();
console.log(php('tools/db_reset.php', '--test'));
const fixture = JSON.parse(php('tools/ui/admin-flash-fixture.php', 'seed'));
const ids = ['doctor', 'patient', 'slot', 'doctorAppointment', 'patientAppointment'].map(key => String(fixture[key]));
const counts = () => JSON.parse(php('tools/ui/admin-flash-fixture.php', 'counts', ...ids));
const evidence = `${root}/UIPROBLEMS/after/admin-phone-after`;
await mkdir(evidence, { recursive: true });
const measurements = [];

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ hasTouch: true, isMobile: true });
    await login(page, 'admin', base);
    const cdp = await page.context().newCDPSession(page);
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      for (const name of ['Patients', 'Doctors', 'Appointments']) {
        const region = page.getByRole('region', { name, exact: true });
        assert.equal(await region.count(), 1);
        assert.equal(await region.getAttribute('tabindex'), '0');
        const hint = page.locator(`#${await region.getAttribute('aria-describedby')}`);
        assert.ok(await hint.isVisible(), `${name}: visible scroll hint`);
        await region.scrollIntoViewIfNeeded();
        await region.evaluate(el => { el.scrollLeft = 0; });
        const initial = await region.evaluate(el => {
          const rect = node => { const r = node.getBoundingClientRect(); return { x: r.x, right: r.right, width: r.width }; };
          return { viewport: innerWidth, document: document.documentElement.scrollWidth, region: rect(el), clientWidth: el.clientWidth, scrollWidth: el.scrollWidth, table: rect(el.querySelector('table')), fontSize: getComputedStyle(el.querySelector('td')).fontSize };
        });
        assert.equal(initial.document, width, 'document must not scroll horizontally');
        assert.ok(initial.region.right <= width && initial.region.x >= 0);
        assert.ok(parseFloat(initial.fontSize) >= 16, 'readable table text');
        await region.focus();
        assert.ok(await region.evaluate(el => getComputedStyle(el).outlineStyle !== 'none'), 'region focus ring');
        if (width === 390) {
          assert.ok(initial.scrollWidth > initial.clientWidth, `${name}: table scrolls within region`);
          // Native keyboard scrolling, without assigning scrollLeft to reach columns.
          for (let i = 0; i < 40; i++) await page.keyboard.press('ArrowRight');
          await page.waitForFunction(el => el.scrollLeft >= el.scrollWidth - el.clientWidth - 1, await region.elementHandle());
        }
        const lastCell = region.locator('tbody tr').first().locator('td').last();
        const assertReachable = async () => {
          const bounds = await lastCell.evaluate(td => {
            const r = td.getBoundingClientRect(), s = td.closest('[role=region]').getBoundingClientRect();
            return { x: r.x, right: r.right, regionX: s.x, regionRight: s.right };
          });
          assert.ok(bounds.x >= bounds.regionX - 1 && bounds.right <= bounds.regionRight + 1, `${name}: rightmost cell reachable ${JSON.stringify(bounds)}`);
          return bounds;
        };
        const keyboard = await assertReachable();
        let touch = null;
        if (width === 390) {
          await region.evaluate(el => { el.scrollLeft = 0; });
          const box = await region.boundingBox();
          const y = Math.max(0, box.y) + 25;
          for (let swipe = 0; swipe < 5; swipe++) {
            const start = box.x + box.width - 30;
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: start, y }] });
            for (let step = 1; step <= 12; step++) {
              await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: start - (box.width - 60) * step / 12, y }] });
            }
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
          }
          await page.waitForFunction(el => el.scrollLeft >= el.scrollWidth - el.clientWidth - 1, await region.elementHandle());
          touch = await assertReachable();
          await region.screenshot({ path: `${evidence}/${name.toLowerCase()}-rightmost-390.png` });
        }
        const button = region.getByRole('button', { name: 'Delete account' }).first();
        if (await button.count()) {
          await region.evaluate(el => { el.scrollLeft = 0; });
          await region.focus();
          await page.keyboard.press('Tab');
          assert.ok(await button.evaluate(el => el === document.activeElement), 'Tab reaches Delete');
          const box = await button.boundingBox();
          assert.ok(box.width >= 44 && box.height >= 44);
          await assertReachable();
        }
        measurements.push({ name, ...initial, keyboard, touch });
      }
      await page.goto(base + 'admin/console.php?status=Future');
      assert.ok((await page.locator('.appointment-table tbody .status-label').allTextContents()).every(status => status === 'Future'));
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, 'filtered page fits viewport');
      await page.screenshot({ path: `${evidence}/admin-filtered-${width}.png`, fullPage: true });
      await page.goto(base + 'admin/console.php?doctor=99999999');
      for (const name of ['Patients', 'Doctors', 'Appointments']) {
        const region = page.getByRole('region', { name, exact: true });
        const empty = await region.evaluate(el => ({ width: el.clientWidth, scroll: el.scrollWidth, columns: el.querySelectorAll('th').length, span: el.querySelector('td').colSpan, text: el.querySelector('.empty-state').getBoundingClientRect().width }));
        assert.equal(empty.scroll, empty.width, 'empty region fits');
        assert.equal(empty.span, empty.columns, 'empty state spans all columns');
        assert.ok(empty.text <= empty.width);
        assert.equal(await region.getAttribute('tabindex'), null);
        measurements.push({ name, viewport: width, empty });
      }
      await page.screenshot({ path: `${evidence}/admin-empty-${width}.png`, fullPage: true });
      await page.goto(base + 'admin/console.php');
    }
    const before = counts();
    const rejected = await page.context().request.post(base + 'admin/console.php', { form: { action: 'delete_doctor', id: String(fixture.doctor), _csrf: 'invalid' } });
    assert.equal(rejected.status(), 419);
    assert.deepEqual(counts(), before, 'CSRF rejection preserves fixture');
    for (const role of ['Doctor', 'Patient']) {
      const button = page.locator('.console-section tr', { hasText: `Flash Test ${role}` }).getByRole('button', { name: 'Delete account' });
      const unchanged = counts();
      page.once('dialog', dialog => dialog.dismiss());
      await button.click();
      assert.deepEqual(counts(), unchanged, 'cancel preserves row and dependents');
      page.once('dialog', dialog => dialog.accept());
      await Promise.all([page.waitForNavigation(), button.click()]);
      assert.ok(page.url().includes('/admin/console.php?'), 'PRG redirect');
      assert.ok(await page.getByRole('status').filter({ hasText: `${role} account and its` }).isVisible());
      const current = counts();
      if (role === 'Doctor') assert.ok(current.doctor === 0 && current.slot === 0 && current.doctorAppointment === 0 && current.patient === 1);
      else assert.ok(current.patient === 0 && current.patientAppointment === 0);
      assert.equal(current.notifications, before.notifications, 'cascade retains notifications');
      await page.reload();
      assert.equal(await page.locator('.flash').count(), 0, 'flash consumed once');
    }
    await writeFile(`${evidence}/measurements.json`, JSON.stringify(measurements, null, 2));
    console.log(JSON.stringify(measurements));
    console.log('OK: all admin columns reachable by keyboard and touch; desktop, filtered/empty, CSRF, cancel/confirm, cascades and PRG');
  } finally { await browser.close(); }
});
