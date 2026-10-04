import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright';
import { withTestServer, login } from './lib.mjs';
import { testPhp } from './isolation.mjs';

// Supplement the all-page manifest with explicit synthetic role/visit routes.
// Run serially with the other UI tests: they share the test database and port.
process.env.CLINIC_DB_NAME = 'ie4727db_test';
const fixture = (...args) => testPhp(['tools/ui/visit-history-fixture.php', ...args], { encoding: 'utf8' }).trim();
const seed = JSON.parse(fixture('seed'));
const previous = { UI_DOCTOR: process.env.UI_DOCTOR, UI_PATIENT: process.env.UI_PATIENT };
process.env.UI_DOCTOR = seed.accounts.doctor.own.user;
process.env.UI_PATIENT = seed.accounts.patient.own.user;
const output = 'UIPROBLEMS/after/iter4-roles-final';
const measurements = [];
const seen = new Set();
const titles = new Map();
const pageErrors = [];
let supplementaryAudit;
try {
  await mkdir(output, { recursive: true });
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const [role, routes] of [
        ['patient', [`patient/home.php?view=${seed.visits.earlier}`]],
        ['doctor', ['doctor/home.php', 'doctor/schedule.php', `doctor/visit.php?appt=${seed.visits.current}`]],
        ['admin', ['admin/console.php', 'admin/outbox.php']],
      ]) {
        const context = await browser.newContext({ javaScriptEnabled: false });
        try {
          const page = await context.newPage();
          page.on('pageerror', error => pageErrors.push(error.message));
          await login(page, role, base);
          for (const route of routes) {
            const response = await page.goto(base + route, { waitUntil: 'networkidle' });
            assert.equal(response.status(), 200, route);
            assert.equal(response.headers()['x-ui-database'], 'ie4727db_test');
            assert.equal(page.url(), base + route, 'A login redirect cannot represent role coverage');
            assert.doesNotMatch(await page.locator('body').innerText(), /Fatal error|Warning:|Parse error/);
            seen.add(`${role}:${route}`);
            const title = await page.title();
            assert.ok(title.trim(), `${route}: a title is required`);
            assert.ok(!titles.has(title), `${route}: title duplicates ${titles.get(title)}`);
            titles.set(title, route);
            assert.equal(await page.locator('main h1').count(), 1);
            if (role === 'patient') assert.equal(await page.locator('.visit-notes').count(), 1);
            if (route.startsWith('doctor/visit.php')) assert.equal(await page.locator('.patient-history .visit-history-record').count(), 2);
            const slug = route.split('?')[0].replace('.php', '').replaceAll('/', '-');
            for (const width of [1280, 390]) {
              await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
              await page.evaluate(async () => Promise.all([...document.images].map(image => image.decode())));
              const geometry = await page.evaluate(() => ({
                overflow: document.documentElement.scrollWidth - innerWidth,
                title: document.title,
                heading: document.querySelector('main h1').textContent,
                images: [...document.images].map(image => ({ loaded: image.complete && image.naturalWidth > 0, width: image.getBoundingClientRect().width })),
                navigation: [...document.querySelectorAll('.site-nav a')].map(link => ({ text: link.textContent, href: link.getAttribute('href'), current: link.getAttribute('aria-current') })),
              }));
              assert.equal(geometry.overflow, 0, `${route} at ${width}`);
              assert.ok(geometry.images.some(image => image.loaded && image.width > 0));
              assert.ok(geometry.images.every(image => image.loaded), `${route}: every required image loads`);
              const screenshot = `${output}/${slug}-${width}.png`;
              await page.screenshot({ path: screenshot, fullPage: true });
              measurements.push({ role, route, base, width, database: response.headers()['x-ui-database'], screenshot, ...geometry });
            }
          }
        } finally { await context.close(); }
      }
    } finally { await browser.close(); }
    // Audit the exact visits on the still-owned, guarded test server. The
    // existing CLI supports UI_BASE_URL without --serve; this is our child,
    // never an arbitrary pre-existing server or a login-page substitute.
    const routes = [...seen].map(route => route.slice(route.indexOf(':') + 1));
    const result = spawnSync(process.execPath, ['tools/ui/audit.mjs',
      'layout,tap-targets,palette,contrast,focus,slop', ...routes], {
      env: { ...process.env, UI_BASE_URL: base }, encoding: 'utf8', windowsHide: true,
    });
    supplementaryAudit = { status: result.status, error: result.error?.message ?? null };
    await writeFile(`${output}/audit.log`, (result.stdout ?? '') + (result.stderr ?? ''));
  });
  assert.equal(seen.size, 6, 'Every supplementary authenticated route must be visited');
  assert.equal(measurements.length, 12, 'Every supplementary route needs both viewports');
  assert.deepEqual(pageErrors, [], 'Authenticated pages must have no browser runtime errors');
  assert.equal(supplementaryAudit.status, 0, `Supplementary audit failed: ${output}/audit.log`);
  console.log('OK: six authenticated role routes, 12 desktop/mobile captures, resolved test DB, no-JS notes/history, loaded images and zero viewport overflow');
} finally {
  try {
      await writeFile(`${output}/measurements.json`, JSON.stringify({ now: seed.now, measurements, pageErrors, supplementaryAudit }, null, 2));
  } finally {
    fixture('cleanup', JSON.stringify(seed));
    for (const [key, value] of Object.entries(previous)) {
      if (value === undefined) delete process.env[key]; else process.env[key] = value;
    }
  }
}
