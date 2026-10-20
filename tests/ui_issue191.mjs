import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login, root } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const stage = process.argv[2] ?? 'after';
if (!['before', 'after'].includes(stage)) throw Error('Use before or after');
const output = resolve(root, 'UIPROBLEMS/after/iter7/issue191-notification-sender', stage);
await mkdir(output, { recursive: true });

const php = code => testPhp(['-r', code], { cwd: root, encoding: 'utf8' }).trim();
const subject = 'Issue 191 sender display';
const id = Number(php(`require 'clinic-base/lib/db.php';
  q('INSERT INTO notifications (sender, recipient, Subject, Body, deliveryStatus) VALUES (:sender, :recipient, :subject, :body, :status)',
    ['sender' => 'clinic@example.local', 'recipient' => 'alex.tan@example.com', 'subject' => '${subject}', 'body' => "Dear Alex,\\n\\nAppointment update.\\nClinic: Clinic Appointment Portal", 'status' => 'logged']);
  echo db()->lastInsertId();`));
try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const width of [1280, 390]) {
        const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 }, javaScriptEnabled: false });
        await login(page, 'patient', base);
        const notification = page.locator('.patient-notification', { hasText: subject });
        assert.equal(await notification.count(), 1);
        await notification.locator('summary').click();
        const text = await notification.innerText();
        if (stage === 'before') {
          assert.ok(text.includes('Clinic: Clinic'), 'stored sign-off shows the doubled prefix before the fix');
        } else {
          assert.ok(text.includes('Clinic Appointment Service'), 'service sign-off is displayed');
          assert.ok(!text.includes('Clinic:'), 'no "Clinic:" prefix is displayed');
        }
        await page.screenshot({ path: resolve(output, `patient-home-${width}.png`), fullPage: true, animations: 'disabled' });
        await page.close();
      }
    } finally { await browser.close(); }
  });
  assert.equal(php(`require 'clinic-base/lib/db.php'; echo q_val('SELECT sender FROM notifications WHERE notificationID = :id', ['id' => ${id}]);`), 'clinic@example.local', 'stored sender remains unchanged');
} finally {
  php(`require 'clinic-base/lib/db.php'; q('DELETE FROM notifications WHERE notificationID = :id', ['id' => ${id}]);`);
}
console.log(`OK: #191 ${stage} sender at 1280px and 390px; stored row unchanged`);
