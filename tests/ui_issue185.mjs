import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { withServer, root } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const stage = process.argv[2] ?? 'after';
if (!['before', 'after'].includes(stage)) throw Error('Use before or after');
const output = resolve(root, 'UIPROBLEMS/after/iter7/issue-185-create-account-spacing', stage);
await mkdir(output, { recursive: true });

await withServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      const gaps = {};
      for (const role of ['patient', 'doctor']) {
        if (role === 'doctor') await page.locator('[data-role-switch="doctor"]').click();
        await page.waitForTimeout(250);
        gaps[role] = await page.evaluate((selectedRole) => {
          const field = document.querySelector(selectedRole === 'patient' ? '#allergies' : '#write-up');
          const button = document.querySelector('#registration-form > button[type="submit"]');
          return Math.round((button.getBoundingClientRect().top - field.getBoundingClientRect().bottom) * 10) / 10;
        }, role);
        await page.screenshot({ path: resolve(output, `${role}-${width}.png`), fullPage: true, animations: 'disabled' });
      }
      console.log(`${width}px last field to submit: patient ${gaps.patient}px; doctor ${gaps.doctor}px`);
      if (stage === 'after' && (Math.abs(gaps.patient - gaps.doctor) > 2 || gaps.patient < 25 || gaps.patient > 55)) {
        throw Error(`Registration submit spacing is outside the shared 25–55px range at ${width}px: ${JSON.stringify(gaps)}`);
      }
      await page.close();
    }
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log(`OK: registration spacing ${stage} captured at 1280px and 390px`);
