import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const output = resolve('UIPROBLEMS/after/issue138-after-matrix');
await mkdir(output, { recursive: true });
const measurements = [];
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch();
    try {
      for (const count of [3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        const fixture = php('tools/ui/specialty-canonical-fixture.php', String(count));
        const expected = JSON.parse(fixture.slice(fixture.indexOf('{')));
        for (const width of [1280, 390]) {
          const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
          try {
            for (const path of ['index.php', 'doctors.php', 'register.php']) {
              await visit(page, path, base);
              if (path === 'register.php') {
                await page.locator('[data-role-switch="doctor"]').click();
                await page.waitForTimeout(220);
              }
              const selector = path === 'index.php' ? '.specialty-item h3' : path === 'doctors.php' ? '#specialty option:not([value=""])' : '#specialty option:not([value=""])';
              const specialties = await page.locator(selector).allTextContents();
              assert.equal(specialties.includes('Dentistry'), false);
              assert.equal(new Set(specialties).size, specialties.length);
              if (path !== 'register.php') assert.deepEqual(specialties, expected.specialties);
              else assert.deepEqual(specialties, ['Dental', 'Dermatology', 'General Practice', 'Paediatrics', 'Physiotherapy']);
              if (path === 'doctors.php') {
                assert.equal(await page.locator('tbody tr').count(), count);
                assert.ok((await page.locator('tbody').innerText()).includes('Dr Edwin'));
              }
              const documentWidth = await page.evaluate(() => document.documentElement.scrollWidth);
              assert.equal(documentWidth, width);
              measurements.push({ count, width, path, specialties, documentWidth });
              await page.screenshot({ path: resolve(output, `${count}-${path.replace('.php', '')}-${width}.png`), fullPage: true });
            }
            await visit(page, 'doctors.php?specialty=Dental', base);
            assert.ok((await page.locator('tbody').innerText()).includes('Dr Edwin'));
          } finally { await page.close(); }
        }
      }
      // A crafted POST bypasses the select and client validation entirely.
      for (const javaScriptEnabled of [true, false]) {
        const page = await browser.newPage({ javaScriptEnabled });
        try {
          await visit(page, 'register.php', base);
          const csrf = await page.locator('[name="_csrf"]').inputValue();
          const user = `issue138_${javaScriptEnabled}`;
          const form = { _csrf: csrf, role: 'doctor', FullName: 'dr edwin', User: user,
            Email: `${user}@example.test`, password: 'Password123', confirm_password: 'Password123',
            Specialty: 'Dentistry', Qualifications: 'BDS', Languages: 'English', WriteUp: 'Dental care.' };
          const rejected = await page.request.post(base + 'actions/register.php', { form });
          assert.ok(rejected.url().endsWith('/register.php'));
          assert.ok((await rejected.text()).includes('Choose a specialty from the list of clinic services.'));
          assert.equal(php('-r', 'require "clinic-base/models/accounts.php"; echo user_or_email_taken($argv[1]) ? "exists" : "absent";', user), 'absent');
          const accepted = await page.request.post(base + 'actions/register.php', { form: { ...form, Specialty: 'Dental' } });
          assert.ok(accepted.url().endsWith('/doctor/home.php'), `${accepted.url()}: ${(await accepted.text()).slice(0, 1500)}`);
          assert.equal(php('-r', 'require "clinic-base/models/accounts.php"; echo find_login($argv[1])["name"];', user), 'Dr Edwin');
        } finally { await page.close(); }
      }
    } finally { await browser.close(); }
  });
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify(measurements, null, 2));
  php('tools/db_reset.php', '--test');
}
console.log('OK: #138 crafted registration with JS on/off; legacy Dental filtering; 3/7/12 doctors and specialty source rows at 1280/390');
