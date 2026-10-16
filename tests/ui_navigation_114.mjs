import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login, visit, root } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const evidence = resolve(root, 'UIPROBLEMS/after/nav-current-matrix');
await mkdir(evidence, { recursive: true });
const publicRoutes = ['index.php', 'doctors.php', 'doctor.php?id=1', 'book.php?doctor=1', 'register.php'];
const roleRoutes = {
  visitor: publicRoutes,
  patient: [...publicRoutes, 'patient/home.php'],
  doctor: [...publicRoutes, 'doctor/home.php', 'doctor/schedule.php', 'doctor/visit.php'],
  admin: [...publicRoutes, 'admin/console.php', 'admin/outbox.php'],
};
const destinations = {
  visitor: ['index.php', 'doctors.php', 'book.php', 'register.php'],
  patient: ['patient/home.php', 'doctors.php', 'book.php'],
  doctor: ['doctor/home.php', 'doctor/schedule.php'],
  admin: ['admin/console.php', 'admin/outbox.php'],
};
const measurements = [];
const states = page => page.locator('.site-nav a').evaluateAll(links => links.map(link => {
  const style = getComputedStyle(link, '::before');
  return { href: link.getAttribute('href'), current: link.getAttribute('aria-current'),
    shadow: style.boxShadow, transform: style.transform, animation: style.animationName,
    transition: style.transitionDuration };
}));
await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const [role, routes] of Object.entries(roleRoutes)) {
      for (const route of routes) {
        const context = await browser.newContext();
        const page = await context.newPage();
        if (role !== 'visitor' && publicRoutes.includes(route)) await login(page, role, base);
        // visit() authenticates protected routes and resolves a real visit ID.
        const rendered = await visit(page, route, base);
        assert.equal(page.url(), rendered, `${role}: unexpected redirect for ${route}`);
        const expected = destinations[role].includes(route.split('?')[0])
          ? new URL(route.split('?')[0], base).pathname : null;
        for (const width of [1280, 390]) {
          await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
          await page.emulateMedia({ reducedMotion: 'no-preference' });
          if (width === 390) {
            await page.locator('.nav-toggle').focus();
            await page.keyboard.press('Enter');
            assert.equal(await page.locator('.nav-toggle').getAttribute('aria-expanded'), 'true');
          }
          const check = async stage => {
            // Wait for the existing 180ms state animation/transition to settle.
            await page.waitForTimeout(220);
            const links = await states(page);
            assert.deepEqual(links.filter(link => link.current === 'page').map(link => link.href), expected ? [expected] : []);
            assert.deepEqual(links.filter(link => link.shadow !== 'none').map(link => link.href), expected ? [expected] : []);
            for (const link of links.filter(link => link.current === 'page')) {
              assert.equal(link.transform, 'matrix(1, 0, 0, 1, 0, -4)');
            }
            measurements.push({ role, route, rendered, width, stage, links });
          };
          await page.mouse.move(0, 0);
          await check('rest');
          const preview = page.locator('.site-nav a:not([aria-current])').first();
          const previewHref = await preview.getAttribute('href');
          await preview.hover();
          await check('hover');
          assert.equal((await states(page)).find(link => link.href === previewHref).transform, 'matrix(1, 0, 0, 1, 0, -4)');
          await page.mouse.move(0, 0);
          await preview.focus();
          await page.keyboard.press('Tab');
          assert.equal(await page.locator('.site-nav a:focus-visible').count(), 1);
          await check('keyboard');
          await page.emulateMedia({ reducedMotion: 'reduce' });
          await check('reduced-motion');
          for (const link of await states(page)) {
            assert.equal(link.animation, 'none');
            assert.ok(parseFloat(link.transition) <= 0.00001);
          }
          await page.screenshot({ path: resolve(evidence, `${role}-${route.split('?')[0].replaceAll('/', '-').replace('.php', '')}-${width}.png`), fullPage: true });
          if (width === 390) {
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('.nav-toggle').getAttribute('aria-expanded'), 'false');
          }
        }
        await context.close();
      }
    }
    for (const javaScriptEnabled of [true, false]) {
      const context = await browser.newContext({ javaScriptEnabled });
      const page = await context.newPage();
      await visit(page, 'register.php', base);
      assert.equal(await page.locator('#registration-form').evaluate(form => new FormData(form).get('role')), 'patient');
      await context.close();
    }
  } finally { await browser.close(); }
});
await writeFile(resolve(evidence, 'measurements.json'), JSON.stringify(measurements, null, 2));
console.log('OK: #114 all page-map destinations by role at 1280/390, current style, hover, keyboard, reduced motion and untouched registration role');
