import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit, login } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const height = width === 390 ? 844 : 800;
      const page = await browser.newPage({ viewport: { width, height } });
      try {
        for (const path of ['index.php', 'screens/doctors.php', 'screens/book.php', 'screens/register.php']) {
          await visit(page, path, base);
          const links = page.locator('#site-navigation a');
          const labels = await links.allTextContents();
          const register = labels.indexOf('Register');
          assert.equal(labels[register + 1], 'Log in', `${path} at ${width}px: Log in follows Register`);
          const logIn = links.filter({ hasText: /^Log in$/ });
          assert.equal(await logIn.getAttribute('href'), '/clinic-base/index.php#login-card');
          if (path !== 'screens/doctors.php') continue;
          if (width === 390) await page.getByRole('button', { name: 'Menu' }).click();
          await logIn.click();
          await page.waitForURL('**/index.php#login-card');
          const card = page.locator('#login-card');
          await card.waitFor({ state: 'visible' });
          const box = await card.boundingBox();
          assert.ok(box, 'Login card has a box');
          assert.ok(box.y >= 60, `${path} at ${width}px: card top clears the header (${box.y})`);
          assert.ok(box.y + box.height <= height - 20, `${path} at ${width}px: card fits in viewport`);
          assert.ok(Math.abs(box.y + box.height / 2 - height / 2) <= 75,
            `${path} at ${width}px: card is centred in viewport`);
          const directory = resolve('UIPROBLEMS/after/iter8/issue-208-login-nav/after');
          await mkdir(directory, { recursive: true });
          await page.screenshot({ path: resolve(directory, `login-landing-${width}.png`) });
        }
        await login(page, 'patient', base);
        await visit(page, 'index.php', base);
        assert.equal(await page.getByRole('link', { name: 'Log in', exact: true }).count(), 0,
          `Authenticated nav at ${width}px hides Log in`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #208 guest Log in nav and centred home card at 1280/390px');
