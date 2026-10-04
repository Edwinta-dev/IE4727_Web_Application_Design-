import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { withTestServer, login, root } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = process.argv.includes('--delivery-on') ? 'on' : 'off';
const before = process.argv.includes('--before');
const php = code => testPhp(['-r', code], { encoding: 'utf8' });
const ids = [];
const evidence = `${root}/UIPROBLEMS/after/issue150-${before ? 'before' : 'after'}`;
await mkdir(evidence, { recursive: true });
const measurements = [];
try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      const page = await browser.newPage({ javaScriptEnabled: false });
      await login(page, 'admin', base);
      const baseline = JSON.parse(php("require 'clinic-base/models/notifications.php'; echo json_encode(outbox_notifications());"));
      assert.equal(baseline.length, 2, 'reset test database before acceptance');
      for (const total of [3, 7, 12]) {
        while (baseline.length + ids.length < total) {
          ids.push(Number(php(`require 'clinic-base/models/notifications.php';
            $id = log_notification('long-recipient-address-for-layout@example.local',
              'Appointment rescheduled with a longer subject for wrapping - Clinic Appointment Portal',
              "First line\\n<script>unsafe</script>\\n" . str_repeat('LongBody', 40));
            q('UPDATE notifications SET deliveryStatus = :status WHERE notificationID = :id',
              ['status' => '${['failed', 'sent', 'logged'][ids.length % 3]}', 'id' => $id]); echo $id;`)));
        }
        await page.goto(base + 'admin/outbox.php');
        assert.equal(await page.locator('tbody tr').count(), total);
        for (const width of [1280, 1024, 390]) {
          await page.setViewportSize({ width, height: width === 390 ? 844 : 800 });
          const measure = () => page.evaluate(() => {
            const table = document.querySelector('.outbox-list table');
            const section = document.querySelector('.outbox-list');
            const filter = document.querySelector('.outbox-filters form');
            const rect = el => { const r = el.getBoundingClientRect(); return { x: r.x, right: r.right, width: r.width, y: r.y }; };
            return { viewport: innerWidth, document: document.documentElement.scrollWidth,
              section: rect(section), table: rect(table), scroll: section.scrollWidth,
              select: rect(filter.querySelector('select')), button: rect(filter.querySelector('button')),
              lastHeader: rect(table.querySelector('th:last-child')) };
          });
          const closed = await measure();
          const detail = page.locator('details').first();
          await detail.locator('summary').click();
          assert.equal(await detail.getAttribute('open'), '');
          const expanded = await measure();
          measurements.push({ total, closed, expanded });
          if (!before) {
            assert.equal(closed.document, width, 'no document overflow');
            assert.equal(expanded.document, width, 'expanded body does not widen document');
            if (width >= 1024) {
              assert.ok(closed.table.right <= closed.section.right + 1, 'all columns fit desktop');
              assert.ok(expanded.table.right <= expanded.section.right + 1, 'expanded row fits desktop');
              assert.ok(closed.select.width <= 240, 'compact select');
              assert.ok(Math.abs(closed.select.y - closed.button.y) < 5, 'inline filter');
            } else {
              const region = page.getByRole('region', { name: 'Message log table', exact: true });
              await region.focus();
              for (let step = 0; step < 30; step++) await page.keyboard.press('ArrowRight', { delay: 25 });
              await page.waitForFunction(() => {
                const el = document.querySelector('.outbox-table-scroll');
                return el.scrollLeft >= el.scrollWidth - el.clientWidth - 1;
              }, null, { polling: 100, timeout: 3000 });
              assert.ok(await region.evaluate(el => {
                const cell = el.querySelector('th:last-child').getBoundingClientRect();
                const bounds = el.getBoundingClientRect();
                return cell.x >= bounds.x - 1 && cell.right <= bounds.right + 1;
              }), 'keyboard scrolling reaches the status column');
              await region.evaluate(el => { el.scrollLeft = 0; });
            }
            assert.equal(await detail.locator('script').count(), 0, 'body is escaped');
            assert.match(await detail.locator('.notification-body').innerText(), /First line\n<script>unsafe<\/script>/);
            assert.ok(await detail.locator('.notification-body').evaluate(el => el.scrollWidth <= el.clientWidth), 'full body wraps within its column');
            assert.ok(!(await detail.locator('summary').innerText()).includes(' - Clinic Appointment Portal'), 'display omits clinic suffix');
          }
          if (width !== 1024) await page.screenshot({ path: `${evidence}/outbox-${total}-${width}.png`, fullPage: true });
          await detail.locator('summary').click();
        }
        if (!before) {
          assert.equal(await page.getByText('Logged - local mail delivery not configured', { exact: true }).count(), process.env.MAIL_DELIVERY === 'off' ? 1 : 0);
          const expected = JSON.parse(php("require 'clinic-base/models/notifications.php'; echo json_encode(outbox_notifications());"));
          for (const status of ['logged', 'sent', 'failed']) {
            await page.selectOption('#deliveryStatus', status);
            await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Apply filter' }).click()]);
            assert.equal(await page.locator('tbody tr').count(), expected.filter(row => row.deliveryStatus === status).length);
            assert.ok((await page.locator('.status-label').allTextContents()).every(text => text === status), 'recorded status remains truthful');
          }
          const failed = page.locator('.status-failed').first();
          assert.ok(await failed.evaluate(el => {
            const probe = document.createElement('span');
            probe.style.color = 'var(--c-muted)';
            el.append(probe);
            const neutral = getComputedStyle(el).color === getComputedStyle(probe).color;
            probe.remove();
            return neutral;
          }), 'delivery status is neutral');
          await page.getByRole('link', { name: 'Clear', exact: true }).click();
          assert.equal(await page.locator('tbody tr').count(), total);
        }
      }
    } finally { await browser.close(); }
  });
} finally {
  if (ids.length) php(`require 'clinic-base/lib/db.php'; foreach (${JSON.stringify(ids)} as $id) q('DELETE FROM notifications WHERE notificationID = :id', ['id' => $id]);`);
  await writeFile(`${evidence}/measurements.json`, JSON.stringify(measurements, null, 2));
}
console.log(before ? JSON.stringify(measurements[0]) : 'OK: #150 desktop fit, expanded escaped bodies, compact filter, neutral statuses, delivery legend, no-JS filters; 3/7/12 rows at 1280/1024/390; ie4727db_test');
