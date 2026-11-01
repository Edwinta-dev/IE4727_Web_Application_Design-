import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      const role = page.getByLabel('Registering as');
      assert.equal(await role.inputValue(), 'patient');
      assert.equal(await page.locator('#patient-fields').isVisible(), true);
      assert.equal(await page.locator('#doctor-fields').isVisible(), false);
      assert.equal(await page.locator('#doctor-fields input, #doctor-fields select, #doctor-fields textarea').evaluateAll(fields => fields.every(field => field.disabled)), true);
      assert.equal(await page.locator('[data-role-switch], input[type="radio"][name="role"]').count(), 0);

      await role.selectOption('doctor');
      assert.equal(await page.locator('#doctor-fields').isVisible(), true);
      assert.equal(await page.locator('#patient-fields').isVisible(), false);
      assert.equal(await page.locator('#patient-fields input, #patient-fields select, #patient-fields textarea').evaluateAll(fields => fields.every(field => field.disabled)), true);
      assert.equal(await page.locator('#doctor-fields input, #doctor-fields select, #doctor-fields textarea').evaluateAll(fields => fields.every(field => !field.disabled)), true);

      const csrf = await page.locator('input[name="_csrf"]').inputValue();
      const response = await page.request.post(base + 'actions/register.php', {
        form: { _csrf: csrf, role: 'doctor', FullName: '', User: '', Email: '', password: '', confirm_password: '' },
        maxRedirects: 0,
      });
      assert.equal(response.status(), 302);
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      assert.equal(await role.inputValue(), 'doctor');
      assert.equal(await page.locator('#doctor-fields').isVisible(), true);
      assert.equal(await page.locator('#patient-fields').isVisible(), false);

      await role.selectOption('patient');
      assert.equal(await page.locator('#patient-fields').isVisible(), true);
      assert.equal(await page.locator('#doctor-fields').isVisible(), false);
      assert.equal(await page.locator('#patient-fields input, #patient-fields select, #patient-fields textarea').evaluateAll(fields => fields.every(field => !field.disabled)), true);
      await page.close();
    }
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log('OK: registration role dropdown defaults to Patient, switches both ways, and preserves Doctor after validation');
