import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const dir = resolve('UIPROBLEMS/after/iter8/create-account-password', before ? 'before' : 'after');
await mkdir(dir, { recursive: true });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        await visit(page, 'screens/register.php', base);
        const password = page.locator('#password');
        const confirm = page.locator('#confirm-password');
        const error = page.locator('[data-error-for="confirm_password"]');
        await password.fill('Password123');
        await confirm.fill('Password124');
        await confirm.blur();
        await page.screenshot({ path: resolve(dir, `register-${width}.png`), fullPage: true });
        if (before) {
          assert.equal(await error.innerText(), '', 'Baseline should reproduce missing live warning');
        } else {
          assert.equal(await error.innerText(), 'Passwords must match.');
          assert.equal(await confirm.getAttribute('aria-invalid'), 'true');
          assert.equal(await confirm.getAttribute('aria-describedby'), 'confirm-password-error');
          assert.equal(await confirm.evaluate(el => getComputedStyle(el).borderColor), 'rgb(185, 28, 28)');
          await confirm.fill('Password123');
          assert.equal(await error.innerText(), '', 'Correcting confirmation should clear warning immediately');
          assert.equal(await confirm.getAttribute('aria-invalid'), null);
          await confirm.fill('');
          assert.equal(await error.innerText(), 'Passwords must match.', 'Clearing a previously typed confirmation should show mismatch');
          await confirm.fill('Password123');
          assert.equal(await error.innerText(), '');
          await password.fill('Password125');
          assert.equal(await error.innerText(), 'Passwords must match.', 'Changing password should recheck confirmation');
          await password.fill('Password123');
          assert.equal(await error.innerText(), '');
          await password.fill('short');
          await confirm.fill('short');
          await page.locator('#registration-form button[type="submit"]').click();
          assert.match(await error.innerText(), /8 characters/, 'Matching short passwords should retain native length feedback');
          await password.fill('Password123');
          await confirm.fill('Password124');
          await page.locator('#registration-form button[type="submit"]').click();
          assert.equal(await error.innerText(), 'Passwords must match.', 'Submit should keep mismatch feedback');
          assert.ok(page.url().endsWith('/screens/register.php'));
        }
      } finally { await page.close(); }
    }
    if (!before) {
      const page = await browser.newPage({ javaScriptEnabled: false });
      try {
        await visit(page, 'screens/register.php', base);
        assert.equal(await page.locator('[data-error-for="confirm_password"]').innerText(), '');
        assert.ok(await page.locator('#registration-form').evaluate(form => form.noValidate));
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
});
console.log(before ? 'OK: #205 mismatch reproduces before submit at 1280/390' : 'OK: #205 live mismatch and correction at 1280/390');
