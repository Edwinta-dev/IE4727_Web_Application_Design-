import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { withServer, root } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const stage = process.argv[2] ?? 'after';
if (!['before', 'after'].includes(stage)) throw Error('Use before or after');
const output = resolve(root, 'UIPROBLEMS/after/iter7/issue189-registration-toggle', stage);
await mkdir(output, { recursive: true });

await withServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      const role = page.getByLabel('Registering as');
      assert.equal(await role.inputValue(), 'patient');
      assert.equal(await page.locator('#patient-fields').isVisible(), true);
      assert.equal(await page.locator('#doctor-fields').isVisible(), false);
      assert.equal(await page.locator('[data-role-switch]').count(), 0);

      await role.focus();
      assert.equal(await role.evaluate(el => el.matches(':focus-visible')), true);
      const ring = await role.evaluate(el => ({ style: getComputedStyle(el).outlineStyle, radius: getComputedStyle(el).borderRadius }));
      assert.notEqual(ring.style, 'none');
      assert.notEqual(ring.radius, '0px');
      await role.selectOption('doctor');
      assert.equal(await page.locator('#doctor-fields').isVisible(), true);
      assert.equal(await page.locator('#patient-fields').isVisible(), false);
      await page.screenshot({ path: resolve(output, `doctor-${width}.png`), fullPage: true, animations: 'disabled' });
      await role.selectOption('patient');
      assert.equal(await page.locator('#patient-fields').isVisible(), true);
      assert.equal(await page.locator('#doctor-fields').isVisible(), false);
      await page.screenshot({ path: resolve(output, `register-${width}.png`), fullPage: true, animations: 'disabled' });
      await page.close();
    }
    const page = await browser.newPage({ javaScriptEnabled: false });
    await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
    assert.equal(await page.getByLabel('Registering as').inputValue(), 'patient');
    await page.getByLabel('Registering as').selectOption('doctor');
    assert.equal(await page.getByLabel('Registering as').inputValue(), 'doctor');
    await page.close();
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log(`OK: registration dropdown and keyboard focus at 1280px and 390px (${stage})`);
