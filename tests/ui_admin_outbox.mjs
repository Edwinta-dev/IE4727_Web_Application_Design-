import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { mkdir, unlink } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login, prepareStaticAssets } from '../tools/ui/lib.mjs';
import { testEnvironment, testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';
const config = resolve('clinic-base/config.local.php');
const created = !existsSync(config);
let configCreated = false;
const php = code => testPhp(['-r', code], { encoding: 'utf8' });
const ids = [];
const captures = resolve('UIPROBLEMS/after/issue142-functional');
await mkdir(captures, { recursive: true });
const capture = async (page, name) => {
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
    await page.evaluate(async () => {
      for (const image of document.images) image.loading = 'eager';
      await Promise.all(Array.from(document.images, image => image.decode()));
    });
    await page.screenshot({ path: resolve(captures, `${name}-${width}.png`), fullPage: true });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no page overflow');
  }
};

try {
  if (created) {
    process.env.UI_ADMIN = 'admin';
    process.env.UI_PASSWORD = 'LocalOutbox142';
    const output = execFileSync('php', ['tools/setup_admin.php', process.env.UI_ADMIN], {
      input: process.env.UI_PASSWORD + '\n', encoding: 'utf8', windowsHide: true,
      env: testEnvironment(),
    });
    configCreated = true;
    assert.match(output, /OK:/);
  }
  // The normal screenshot bootstrap sees the generated config and does not
  // inject admin constants: this login exercises the documented setup itself.
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      const page = await browser.newPage();
      await prepareStaticAssets(page, base);
      await page.goto(base + 'admin/outbox.php?deliveryStatus=failed');
      assert.match(page.url(), /index\.php\?next=/);
      assert.equal(await page.locator('input[name="next"]').inputValue(), '/admin/outbox.php?deliveryStatus=failed');
      assert.equal(await page.locator('.member-login .flash[role="alert"]').innerText(), 'Please sign in as an admin to continue.');
      await capture(page, 'guard-sign-in');
      await page.reload();
      assert.equal(await page.locator('.flash').count(), 0, 'flash is consumed once');
      await page.locator('input[name="username_or_email"]').fill(process.env.UI_ADMIN || 'admin');
      await page.locator('input[name="password"]').fill(process.env.UI_PASSWORD || 'Password123');
      await Promise.all([page.waitForNavigation(), page.locator('form button[type="submit"]').click()]);
      assert.match(page.url(), /admin\/outbox\.php\?deliveryStatus=failed$/, 'login returns to requested outbox');
      assert.equal(await page.locator('.empty-state').innerText(), 'No messages match this filter.');
      await capture(page, 'empty-filter');

      const baseline = JSON.parse(php("require 'clinic-base/models/notifications.php'; echo json_encode(outbox_notifications());"));
      assert.equal(baseline.length, 2, 'run tools/db_reset.php first: two synthetic seed rows');
      for (const total of [3, 7, 12]) {
        while (baseline.length + ids.length < total) {
          const number = ids.length + 1;
          const id = Number(php(`require 'clinic-base/models/notifications.php';
            $id = log_notification('issue142@example.local', 'Issue 142 message ${number}', "First line\\n<script>unsafe</script>");
            q('UPDATE notifications SET deliveryStatus = :status, SentAt = :sent WHERE notificationID = :id',
              ['status' => '${number % 2 ? 'failed' : 'sent'}', 'sent' => date('Y-m-d', strtotime('${number === 1 ? 'yesterday' : 'today'}')) . ' 12:00:${String(number).padStart(2, '0')}', 'id' => $id]);
            echo $id;`));
          ids.push(id);
        }
        await page.goto(base + 'admin/outbox.php');
        assert.equal(await page.locator('tbody tr').count(), total);
        assert.equal(await page.locator('.page-intro strong').innerText(), String(total - 1), 'today excludes yesterday');
        const expected = JSON.parse(php("require 'clinic-base/models/notifications.php'; echo json_encode(outbox_notifications());"));
        const ordered = [...expected].sort((a, b) => b.SentAt.localeCompare(a.SentAt) || Number(b.notificationID) - Number(a.notificationID));
        assert.deepEqual(expected.map(row => row.notificationID), ordered.map(row => row.notificationID), 'timestamp/id descending');
        assert.deepEqual(await page.locator('tbody tr td:nth-child(1)').allTextContents(), expected.map(row => row.SentAt), 'newest first');
        assert.deepEqual(await page.locator('tbody tr td:nth-child(3)').allTextContents(), ordered.map(row => row.Subject), 'subject ordering');
        const detail = page.locator('tr').filter({ has: page.getByRole('cell', { name: 'Issue 142 message 1', exact: true }) }).locator('details');
        await detail.locator('summary').click();
        assert.equal(await detail.getAttribute('open'), '');
        assert.equal(await detail.locator('.notification-body').innerText(), 'First line\n<script>unsafe</script>');
        assert.equal(await detail.locator('script').count(), 0, 'body is escaped');
        await capture(page, `outbox-${total}`);
        for (const status of ['logged', 'sent', 'failed']) {
          await page.selectOption('#deliveryStatus', status);
          await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Apply filter' }).click()]);
          assert.equal(await page.locator('tbody tr').count(), expected.filter(row => row.deliveryStatus === status).length);
          assert.ok((await page.locator('tbody tr td:nth-child(4)').allTextContents()).every(text => text.trim() === status));
          assert.equal(await page.locator('.page-intro strong').innerText(), String(total - 1), 'count independent of filter');
        }
        console.log(`OK: outbox ${total} rows; newest first; escaped body reveal; statuses; today count; desktop/mobile`);
      }
      // Role mismatch also gives feedback without revealing the outbox.
      const patient = await browser.newPage();
      const adminPassword = process.env.UI_PASSWORD;
      process.env.UI_PASSWORD = 'Password123';
      try { await login(patient, 'patient', base); }
      finally {
        if (adminPassword === undefined) delete process.env.UI_PASSWORD;
        else process.env.UI_PASSWORD = adminPassword;
      }
      await patient.goto(base + 'admin/outbox.php');
      assert.match(patient.url(), /index\.php\?next=/);
      assert.equal(await patient.locator('.flash').innerText(), 'Please sign in as an admin to continue.');
      assert.equal(await patient.locator('.admin-outbox').count(), 0);
      console.log('OK: #142 generated-config admin login, return URL, one-time guard flash, wrong-role guard, empty state; ie4727db_test');
    } finally { await browser.close(); }
  });
} finally {
  try {
    if (ids.length) php(`require 'clinic-base/lib/db.php'; foreach (${JSON.stringify(ids)} as $id) q('DELETE FROM notifications WHERE notificationID = :id', ['id' => $id]);`);
  } finally {
    if (configCreated) await unlink(config);
  }
}
