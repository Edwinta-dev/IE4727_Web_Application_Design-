import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, root } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const suffix = `${Date.now()}${Math.random().toString(16).slice(2)}`;
const user = `issue207_${suffix}`;
const stored = () => testPhp(['-r',
  'require "clinic-base/lib/db.php"; $row=q_one("SELECT `Allergies` FROM `patient` WHERE `User` = :user", ["user" => $argv[1]]); echo $row["Allergies"] ?? "";', user],
  { encoding: 'utf8' }).trim();
const cleanup = () => testPhp(['-r',
  'require "clinic-base/lib/db.php"; q("DELETE FROM `patient` WHERE `User` = :user", ["user" => $argv[1]]);', user]);

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const javascriptEnabled of [true, false]) {
      const context = await browser.newContext({ javaScriptEnabled: javascriptEnabled });
      const page = await context.newPage();
      try {
        await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
        const checkbox = page.locator('[name="NoKnownAllergies"]');
        const allergies = page.locator('[name="Allergies"]');
        if (await checkbox.count() !== 1 || await checkbox.isChecked()) throw Error('Missing or prechecked checkbox');
        if (javascriptEnabled) {
          await allergies.fill('Pollen');
          await checkbox.check();
          if (await allergies.inputValue() !== 'No Known Allergies' || await allergies.getAttribute('readonly') === null) throw Error('Checkbox did not lock canonical value');
          await checkbox.uncheck();
          if (await allergies.inputValue() !== 'Pollen' || await allergies.getAttribute('readonly')) throw Error('Unchecking did not restore editable allergies');
        }
        await allergies.fill('');
        await checkbox.check();
        if (javascriptEnabled) {
          const screenshotDir = resolve(root, 'UIPROBLEMS/after/iter8/issue207-no-known-allergies/after');
          await mkdir(screenshotDir, { recursive: true });
          for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
            await page.screenshot({ path: resolve(screenshotDir, `register-checked-${width}.png`), fullPage: true });
          }
          await page.keyboard.press('Tab');
          await page.keyboard.press('Shift+Tab');
          if (await checkbox.evaluate((field) => getComputedStyle(field).outlineStyle) !== 'solid') {
            throw Error('Keyboard focus ring is missing from checkbox');
          }
        }
        const account = {
          FullName: 'Issue 207 Patient', User: user, Email: `${user}@example.local`,
          password: 'Secret123', confirm_password: 'Secret123', Gender: 'Other', Phone: '81234567',
        };
        for (const [name, value] of Object.entries(account)) {
          const field = page.locator(`[name="${name}"]`);
          if (name === 'Gender') await field.selectOption(value); else await field.fill(value);
        }
        await page.locator('[name="Email"]').fill('invalid-email');
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'networkidle' }),
          page.locator('#registration-form').evaluate((form) => form.submit()),
        ]);
        if (!page.url().endsWith('/register.php')) throw Error('Invalid email was accepted');
        if (!(await checkbox.isChecked()) || await allergies.inputValue() !== 'No Known Allergies' || await allergies.getAttribute('readonly') === null) {
          throw Error('Checkbox state was not restored after validation failed');
        }
        await page.locator('[name="Email"]').fill(account.Email);
        await page.locator('[name="password"]').fill(account.password);
        await page.locator('[name="confirm_password"]').fill(account.confirm_password);
        if (javascriptEnabled) await allergies.evaluate((field) => { field.value = 'Peanuts'; });
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/patient/home.php', { timeout: 12000 });
        if (stored() !== '["No Known Allergies"]') throw Error('Database did not store canonical JSON allergy value');
        cleanup();
      } finally {
        await context.close();
      }
    }
  } finally {
    cleanup();
    await browser.close();
  }
}, 'clinic-base');

console.log('PASS: checkbox toggle, restored validation state, JS on/off registration and canonical JSON storage');
