import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, login, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = code => testPhp(['-r', code], { encoding: 'utf8' }).trim();
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const fixture = JSON.parse(php("$f = require 'tests/fixtures/booking_179.php'; echo json_encode($f);"));
const [ada, ben] = fixture.doctors;
const adaEarly = fixture.slots[`${ada}:06:30:00`];
const route = `book.php?specialty=Dental&date=${fixture.date}&from=06%3A00&to=08%3A00`;

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      await visit(page, route, base);
      assert.equal(await page.locator('#doctor').inputValue(), '', 'doctor filter starts blank');
      assert.equal(await page.locator('.booking-doctor-option').count(), 2);
      assert.equal(await page.locator('.schedule-grid:first-of-type .slot.free').count(), 3, 'union of distinct start times');
      const shared = page.locator('.slot.free').filter({ hasText: '7:00 AM' });
      assert.equal(await shared.locator('.slot-doctors span').count(), 2, 'shared time names both doctors');
      assert.equal(await page.locator('.slot.free').filter({ hasText: '7:30 AM' }).count(), 0, 'blocked time omitted');
      await shared.locator('.slot-choice').check();
      assert.equal(await page.locator('.booking-doctor-option:visible').count(), 2);
      assert.equal(await page.locator('.booking-confirmation button').isDisabled(), true);
      await page.locator(`.booking-doctor-option[data-doctor-id="${ben}"] input`).check();
      assert.equal(await shared.locator('.slot-choice').inputValue(), String(fixture.slots[`${ben}:07:00:00`]));
      assert.equal(await page.locator('.booking-confirmation button').isEnabled(), true);
      await page.locator('.slot.free').filter({ hasText: '6:30 AM' }).locator('.slot-choice').check();
      assert.equal(await page.locator(`.booking-doctor-option[data-doctor-id="${ada}"] input`).isChecked(), true, 'single covering doctor preselected');
      assert.equal(await page.locator('.booking-doctor-option:visible').count(), 1);
      assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);
      await page.close();

      const doctorFirst = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      await visit(doctorFirst, route, base);
      await doctorFirst.locator(`.booking-doctor-option[data-doctor-id="${ben}"] input`).check();
      assert.equal(await doctorFirst.locator('.schedule-grid:first-of-type .slot.free:visible').count(), 2,
        'choosing a doctor first filters to that doctor’s times');
      await doctorFirst.close();

      const noJs = await browser.newPage({ javaScriptEnabled: false });
      await visit(noJs, `${route}&doctor=${ben}`, base);
      const noJsForm = noJs.locator('#booking-post');
      await noJsForm.locator('.slot-choice').first().check();
      const data = await noJsForm.evaluate(form => [...new FormData(form).entries()]);
      assert.equal(data.find(([name]) => name === 'doctor')[1], String(ben), 'no-JS doctor filter keeps a valid booking choice');
      await noJs.close();
    }

    const page = await browser.newPage();
    await login(page, 'patient', base);
    await visit(page, route, base);
    const token = await page.locator('#booking-post input[name="_csrf"]').inputValue();
    const before = php(`require 'clinic-base/lib/db.php'; echo q_val('SELECT COUNT(*) FROM appointment WHERE slotID = :slot', ['slot' => ${adaEarly}]);`);
    const mismatch = await page.request.post(base + 'actions/book.php', { form: {
      _csrf: token, slot_id: String(adaEarly), doctor: String(ben), date: fixture.date,
      specialty: 'Dental', reason: 'Mismatch regression'
    } });
    assert.match(await mismatch.text(), /Choose a doctor who is available at that time/);
    php(`require 'clinic-base/models/slots.php'; set_slot_status(${adaEarly}, 'Blocked');`);
    const stale = await page.request.post(base + 'actions/book.php', { form: {
      _csrf: token, slot_id: String(adaEarly), doctor: String(ada), date: fixture.date,
      specialty: 'Dental', reason: 'Stale regression'
    } });
    assert.match(await stale.text(), /no longer available at the selected time/);
    assert.equal(php(`require 'clinic-base/lib/db.php'; echo q_val('SELECT COUNT(*) FROM appointment WHERE slotID = :slot', ['slot' => ${adaEarly}]);`), before);
    await page.close();
  } finally { await browser.close(); }
});
console.log('OK: #179 union grid, doctor selection, blocked period, stale and mismatched submit at 1280/390');
