import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir, readFile, rm } from 'node:fs/promises';
import { resolve } from 'node:path';
import { randomBytes } from 'node:crypto';
import { withTestServer, prepareStaticAssets, root } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const suffix = randomBytes(5).toString('hex');
const username = `issue190_${suffix}`;
const output = resolve(root, 'UIPROBLEMS/after/iter7/issue190-doctor-registration/after');
const photoPath = resolve(root, 'clinic-base/assets/img/Doctor_Consulting.jpg');
const photo = await readFile(photoPath);
await mkdir(output, { recursive: true });

function doctorData(csrf, extra = {}) {
  return {
    _csrf: csrf, role: 'doctor', FullName: 'Test Doctor', User: username,
    Email: `${username}@example.local`, password: 'Password123', confirm_password: 'Password123',
    Specialty: 'General Practice', Qualifications: 'MBBS', Languages: 'English',
    WriteUp: 'General practitioner with experience in community care and preventive medicine.',
    ...extra,
  };
}

try {
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const width of [1280, 390]) {
        const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
        await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
        await page.locator('[data-role-switch="doctor"]').click();
        const background = page.locator('[name="WriteUp"]');
        const upload = page.locator('[name="ProfilePhoto"]');
        assert.equal(await upload.getAttribute('required'), '');
        assert.equal(await background.getAttribute('minlength'), '50');
        assert.match(await page.locator('label[for="profile-photo"]').innerText(), /\*/);
        assert.match(await page.locator('label[for="write-up"]').innerText(), /\*/);
        await background.fill('A short background');
        assert.match(await page.locator('#write-up-count').innerText(), /^18 \/ 50/);
        await page.waitForTimeout(250);
        assert.equal(await page.locator('.registration-role-viewport').evaluate(el => el.scrollLeft), 0);
        await page.screenshot({ path: resolve(output, `register-doctor-${width}.png`), fullPage: true, animations: 'disabled' });
        await page.close();
      }

      const page = await browser.newPage();
      await page.goto(base + 'register.php');
      const csrf = await page.locator('[name="_csrf"]').inputValue();
      const submit = async (data, file) => {
        const multipart = doctorData(csrf, data);
        if (file) multipart.ProfilePhoto = file;
        const response = await page.request.post(base + 'actions/register.php', { multipart });
        assert.ok(response.url().endsWith('/register.php'), 'invalid doctor registration redirected back');
        return response.text();
      };
      assert.match(await submit({}), /Choose a profile photo/);
      assert.match(await submit({}, { name: 'wrong.txt', mimeType: 'text/plain', buffer: Buffer.from('wrong type') }), /JPEG, PNG or WebP/);
      assert.match(await submit({}, { name: 'forged.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('not an image') }), /JPEG, PNG or WebP/);
      assert.match(await submit({}, { name: 'large.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(2 * 1024 * 1024 + 1) }), /no larger than 2 MB/);
      assert.match(await submit({ WriteUp: 'Too short' }, { name: 'doctor.jpg', mimeType: 'image/jpeg', buffer: photo }), /at least 50 characters/);
      await page.close();

      const valid = await browser.newPage();
      await prepareStaticAssets(valid, base);
      await valid.goto(base + 'register.php');
      await valid.locator('[data-role-switch="doctor"]').click();
      for (const [name, value] of Object.entries(doctorData('', {}))) {
        if (['_csrf', 'role', 'Specialty'].includes(name)) continue;
        await valid.locator(`[name="${name}"]`).fill(value);
      }
      await valid.locator('[name="Specialty"]').selectOption('General Practice');
      await valid.locator('[name="ProfilePhoto"]').setInputFiles(photoPath);
      await valid.locator('button[type="submit"]').click();
      await valid.waitForURL('**/doctor/home.php');
      await valid.goto(base + 'doctors.php', { waitUntil: 'networkidle' });
      const row = valid.locator('.doctor-directory tr', { hasText: 'Test Doctor' });
      assert.equal(await row.count(), 1);
      const src = await row.locator('img.doctor-photo').getAttribute('src');
      assert.match(src, /assets\/img\/doctor-[a-f0-9]{32}\.jpg$/);
      await row.locator('img.doctor-photo').evaluate(img => img.decode());
      assert.ok(await row.locator('img.doctor-photo').evaluate(img => img.complete && img.naturalWidth > 0), 'directory photo loaded');
      await valid.screenshot({ path: resolve(output, 'doctors-valid-1280.png'), fullPage: true, animations: 'disabled' });
      await valid.setViewportSize({ width: 390, height: 844 });
      await valid.screenshot({ path: resolve(output, 'doctors-valid-390.png'), fullPage: true, animations: 'disabled' });
      await valid.close();
    } finally {
      await browser.close();
    }
  }, 'clinic-base');
  console.log('OK: #190 doctor photo and background registration');
} finally {
  const result = testPhp(['-r', `require 'clinic-base/lib/db.php'; $row=q_one('SELECT ImageURL FROM doctor WHERE User = :user', ['user' => '${username}']); if ($row) { q('DELETE FROM doctor WHERE User = :user', ['user' => '${username}']); echo $row['ImageURL']; }`], { encoding: 'utf8', cwd: root }).trim();
  if (result && /^assets\/img\/doctor-[a-f0-9]{32}\.(jpg|png|webp)$/.test(result)) {
    await rm(resolve(root, 'clinic-base', result), { force: true });
  }
}
