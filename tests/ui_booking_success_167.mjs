import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';
const php = code => testPhp(['-r', code], { encoding: 'utf8' });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  let slotId = 0;
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await login(page, 'patient', base);
    const slot = JSON.parse(php(`require 'clinic-base/lib/db.php'; echo json_encode(q_one(
      "SELECT s.slotID, s.DoctorID, d.FullName, DATE(s.SlotDateTime) AS slotDate,
       DATE_FORMAT(s.SlotDateTime,'%d %b %Y') AS displayDate,
       DATE_FORMAT(s.SlotDateTime,'%l:%i %p') AS displayTime FROM slots s JOIN doctor d ON d.DoctorID=s.DoctorID
       WHERE s.Status='Available' AND s.SlotDateTime>NOW()
       AND DATE(s.SlotDateTime)<=DATE_ADD(CURDATE(), INTERVAL 6 DAY)
       ORDER BY s.SlotDateTime LIMIT 1"));`));
    assert.ok(slot, 'available future slot exists in ie4727db_test');
    slotId = Number(slot.slotID);
    await page.goto(`${base}book.php?doctor=${slot.DoctorID}&date=${slot.slotDate}`);
    const form = page.locator(`form:has(input[name="slot_id"][value="${slotId}"])`);
    await form.locator(`input[name="slot_id"][value="${slotId}"]`).check();
    await form.locator('input[name="reason"]').fill('Issue 167 confirmation check');
    await form.getByRole('button', { name: 'Confirm booking' }).click();
    await page.getByRole('status').getByRole('link', { name: 'View my appointment' }).waitFor();
    const confirmation = await page.getByRole('status').innerText();
    for (const expected of [slot.FullName, slot.displayDate, slot.displayTime]) {
      assert.ok(confirmation.includes(expected), `confirmation includes ${expected}`);
    }
    assert.equal(await page.locator('.slot-booking form').count(), 0, 'booking form hidden after success');
    await page.setViewportSize({ width: 1280, height: 800 });
    const link = await page.getByRole('link', { name: 'View my appointment' }).getAttribute('href');
    const response = await page.request.get(new URL(link, page.url()).toString());
    assert.equal(response.status(), 200, 'appointment dashboard link opens');
    await page.getByRole('link', { name: 'View my appointment' }).click();
    await page.getByRole('heading', { name: 'My appointments' }).waitFor();
    assert.match(page.url(), /patient\/home\.php#appointment-\d+$/);
    const rowSelector = `#${page.url().split('#')[1]}`;
    assert.equal(await page.locator(rowSelector).count(), 1, 'dashboard contains linked appointment row');
    assert.ok((await page.locator(rowSelector).innerText()).includes(slot.FullName));
    const dashboardUrl = page.url();
    await page.reload();
    assert.equal(page.url(), dashboardUrl, 'refresh stays on dashboard without resubmitting booking');
    console.log('OK: #167 confirmation and dashboard appointment link at 390x844 and 1280x800; refresh does not resubmit; ie4727db_test');
  } finally {
    if (slotId) php(`require 'clinic-base/lib/db.php';
      $id = q_val('SELECT appointmentID FROM appointment WHERE slotID=:slot', ['slot'=>${slotId}]);
      if ($id) { q('DELETE FROM notifications WHERE appointmentID=:id', ['id'=>$id]); q('DELETE FROM appointment WHERE appointmentID=:id', ['id'=>$id]); }
      q("UPDATE slots SET Status='Available' WHERE slotID=:slot", ['slot'=>${slotId}]);`);
    await browser.close();
  }
});
