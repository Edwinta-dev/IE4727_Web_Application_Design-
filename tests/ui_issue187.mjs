import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { testPhp } from '../tools/ui/isolation.mjs';
import { root, withTestServer } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const suffix = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
const fixture = (action) => JSON.parse(testPhp(['tools/ui/register-fixture.php', action, suffix], { encoding: 'utf8' }));

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
    const gender = page.locator('[name="Gender"]');
    const choice = "I'd rather not disclose";
    if (!(await gender.locator('option').allTextContents()).includes(choice)) throw Error('Gender choice is absent');
    await gender.selectOption(choice);
    if (await gender.inputValue() !== choice) throw Error('Gender choice could not be selected');
    const screenshots = resolve(root, 'UIPROBLEMS/after/iter7/issue187-gender/after');
    await mkdir(screenshots, { recursive: true });
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      await page.screenshot({ path: resolve(screenshots, `register-selected-${width}.png`), fullPage: true });
    }
    const user = `ui_patient_js_${suffix}`;
    for (const [name, value] of Object.entries({
      FullName: 'Gender Choice Browser Test', User: user, Email: `${user}@example.test`,
      password: 'Password123', confirm_password: 'Password123', Phone: '81234567',
    })) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/patient/home.php', { timeout: 12000 });
    const counts = fixture('counts');
    if (counts.database !== 'ie4727db_test' || counts.patient !== 1) throw Error('Patient registration was not stored in the test database');
    console.log('PASS: Chromium registered a patient selecting the non-disclosure Gender option');
  } finally {
    await browser.close();
    fixture('cleanup');
  }
}, 'clinic-base');
