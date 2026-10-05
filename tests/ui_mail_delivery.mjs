import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';
const php = code => testPhp(['-r', code], { encoding: 'utf8' });
assert.equal(php("require 'clinic-base/lib/config.php'; echo MAIL_DELIVERY;"), 'off', 'effective delivery configuration');

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  let slotId;
  try {
    const page = await browser.newPage();
    await login(page, 'patient', base);
    // Use a real future slot within the server's seven-day booking window.
    const slot = JSON.parse(php(`require 'clinic-base/lib/db.php'; echo json_encode(q_one(
      "SELECT slotID, DoctorID, DATE(SlotDateTime) AS slotDate FROM slots
       WHERE Status = 'Available' AND SlotDateTime > NOW()
       AND DATE(SlotDateTime) <= DATE_ADD(CURDATE(), INTERVAL 6 DAY)
       ORDER BY SlotDateTime, slotID LIMIT 1"));`));
    assert.ok(slot, 'future slot exists');
    await page.goto(`${base}book.php?doctor=${slot.DoctorID}&date=${slot.slotDate}`, { waitUntil: 'domcontentloaded' });
    const form = page.locator(`form:has(input[name="slot_id"][value="${slot.slotID}"])`);
    const csrf = await form.locator('input[name="_csrf"]').inputValue();
    slotId = Number(slot.slotID);
    assert.ok(Number.isSafeInteger(slotId) && slotId > 0);
    const start = performance.now();
    const response = await page.request.post(base + 'actions/book.php', {
      form: { _csrf: csrf, slot_id: String(slotId), reason: 'Issue 141 synthetic booking' },
      maxRedirects: 0,
    });
    const elapsed = (performance.now() - start) / 1000;
    assert.equal(response.status(), 302, 'booking redirects');
    assert.match(response.headers().location, /book\.php\?doctor=/);
    const rows = JSON.parse(php(`require 'clinic-base/lib/db.php'; echo json_encode(q_all(
      'SELECT n.recipient, n.deliveryStatus FROM notifications n
       JOIN appointment a ON a.appointmentID = n.appointmentID WHERE a.slotID = :slot', ['slot' => ${slotId}]));`));
    assert.equal(rows.length, 2, 'patient and doctor rows');
    assert.equal(new Set(rows.map(row => row.recipient)).size, 2, 'distinct recipients');
    assert.ok(rows.every(row => row.deliveryStatus === 'logged'), 'both rows stay logged');
    assert.ok(elapsed < 1, `booking redirect took ${elapsed.toFixed(3)}s`);
    console.log(`OK: delivery-off booking HTTP redirect ${elapsed.toFixed(3)}s; patient/doctor rows logged; ie4727db_test`);
  } finally {
    if (slotId) php(`require 'clinic-base/lib/db.php';
      $id = q_val('SELECT appointmentID FROM appointment WHERE slotID = :slot', ['slot' => ${slotId}]);
      if ($id) {
        q('DELETE FROM notifications WHERE appointmentID = :id', ['id' => $id]);
        q('DELETE FROM appointment WHERE appointmentID = :id', ['id' => $id]);
        q("UPDATE slots SET Status = 'Available' WHERE slotID = :slot", ['slot' => ${slotId}]);
      }`);
    await browser.close();
  }
});
