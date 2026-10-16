import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { withServer, root } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const stage = process.argv[2] ?? 'after';
if (!['before', 'after'].includes(stage)) throw Error('Use before or after');
const baseline = 'c0e2bd5';
const output = resolve(root, 'UIPROBLEMS/after/iter7/issue189-registration-toggle', stage);
await mkdir(output, { recursive: true });

await withServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      if (stage === 'before') {
        for (const asset of ['app.js', 'css/style.css']) {
          const source = execFileSync('git', ['show', `${baseline}:clinic-base/assets/${asset}`], { cwd: root });
          await page.route(`**/clinic-base/assets/${asset}`, route => route.fulfill({
            status: 200, body: source,
            contentType: asset.endsWith('.css') ? 'text/css' : 'text/javascript',
          }));
        }
      }
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      const patient = page.locator('[name="role"][value="patient"]');
      const doctor = page.locator('[name="role"][value="doctor"]');
      const toPatient = page.locator('[data-role-switch="patient"]');
      const toDoctor = page.locator('[data-role-switch="doctor"]');
      assert.equal(await patient.isChecked(), true);
      if (stage === 'before') {
        assert.equal(await toPatient.isVisible(), true);
        assert.equal(await toDoctor.isVisible(), true);
        await toPatient.click();
        assert.equal(await patient.isChecked(), true, 'the displayed left arrow is a no-op');
        console.log(`${width}px: both arrows visible on Patient; left arrow leaves Patient selected`);
      } else {
        assert.equal(await toPatient.isVisible(), false);
        assert.equal(await toDoctor.isVisible(), true);
        assert.equal(await page.getByText('Patient', { exact: true }).isVisible(), true);
        assert.equal(await page.getByText('Doctor', { exact: true }).isVisible(), true);
        await toDoctor.focus();
        assert.equal(await toDoctor.evaluate(el => el.matches(':focus-visible')), true);
        const ring = await toDoctor.evaluate(el => ({ style: getComputedStyle(el).outlineStyle, radius: getComputedStyle(el).borderRadius }));
        assert.notEqual(ring.style, 'none');
        assert.notEqual(ring.radius, '0px');
        await page.keyboard.press('Enter');
        assert.equal(await doctor.isChecked(), true);
        assert.equal(await toDoctor.isVisible(), false);
        assert.equal(await toPatient.isVisible(), true);
        await page.screenshot({ path: resolve(output, `doctor-${width}.png`), fullPage: true, animations: 'disabled' });
        await page.locator('.registration-role-options').getByText('Patient').click();
        assert.equal(await patient.isChecked(), true);
        await doctor.focus();
        await page.keyboard.press('ArrowLeft');
        assert.equal(await patient.isChecked(), true);
        assert.equal(await patient.evaluate(el => el === document.activeElement), true, 'native radio navigation moves focus with selection');
        assert.equal(await patient.evaluate(el => el.matches(':focus-visible')), true);
        assert.notEqual(await patient.evaluate(el => getComputedStyle(el.closest('label')).outlineStyle), 'none');
        await toDoctor.click();
        await toPatient.click();
        assert.equal(await patient.isChecked(), true);
        assert.equal(await toDoctor.isVisible(), true);
      }
      await page.screenshot({ path: resolve(output, `register-${width}.png`), fullPage: true, animations: 'disabled' });
      await page.close();
    }
    if (stage === 'after') {
      const page = await browser.newPage({ javaScriptEnabled: false });
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      assert.equal(await page.locator('.registration-role-options').getByText('Patient').isVisible(), true);
      await page.locator('.registration-role-options').getByText('Doctor').click();
      assert.equal(await page.locator('[name="role"][value="doctor"]').isChecked(), true);
      await page.close();
    }
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log(`OK: #189 ${stage} registration role controls at 1280px and 390px`);
