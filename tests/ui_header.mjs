import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, visit } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const measureOnly = process.argv.includes('--measure');
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const paths = ['index.php', 'register.php', 'doctors.php', 'doctor.php?id=1',
  'book.php?doctor=1', 'patient/home.php', 'doctor/home.php',
  'doctor/schedule.php', 'admin/console.php', 'admin/outbox.php'];

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const path of paths) {
      const page = await browser.newPage();
      await visit(page, path, base);
      for (const width of [375, 390, 768, 1280]) {
        await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
        const dimensions = await page.locator('.site-header').evaluate(header => ({
          height: header.getBoundingClientRect().height,
          // Inspect geometry as well as scroll width: existing body overflow
          // rules must not conceal a header element that exceeds the viewport.
          overflow: header.scrollWidth > header.clientWidth || [...header.querySelectorAll('*')]
            .filter(element => element.getClientRects().length)
            .some(element => {
              const rect = element.getBoundingClientRect();
              return rect.left < -1 || rect.right > innerWidth + 1;
            }),
        }));
        console.log(`${path} ${width}px: header ${dimensions.height.toFixed(1)}px, overflow ${dimensions.overflow}`);
        if (measureOnly) continue;
        assert.equal(dimensions.overflow, false, `${path}: header overflow at ${width}`);
        const toggle = page.locator('.nav-toggle');
        const links = page.locator('.site-nav a');
        if (width <= 390) {
          assert.ok(dimensions.height <= 100, `${path}: phone header exceeds 100px`);
          assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
          assert.equal(await links.first().isVisible(), false);
          await toggle.focus();
          await page.keyboard.press('Enter');
          assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
          for (const link of await links.all()) assert.equal(await link.isVisible(), true);
          assert.equal(await page.locator('.site-header').evaluate(header => header.scrollWidth > header.clientWidth ||
            [...header.querySelectorAll('*')].filter(element => element.getClientRects().length).some(element => {
              const rect = element.getBoundingClientRect();
              return rect.left < -1 || rect.right > innerWidth + 1;
            })), false, `${path}: open menu overflow`);
          await page.keyboard.press('Tab');
          assert.equal(await links.first().evaluate(link => link === document.activeElement), true);
          await page.keyboard.press('Escape');
          assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
          assert.equal(await toggle.evaluate(button => button === document.activeElement), true);
          await page.keyboard.press('Space');
          await toggle.click();
          assert.equal(await links.first().isVisible(), false);
        } else {
          assert.equal(await toggle.isVisible(), false);
          for (const link of await links.all()) assert.equal(await link.isVisible(), true);
        }
      }
      if (!measureOnly) {
        await page.setViewportSize({ width: 375, height: 844 });
        assert.equal(await page.locator('.nav-toggle').getAttribute('aria-expanded'), 'false');
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.locator('.nav-toggle').click();
        const motions = await page.locator('.site-nav a').evaluateAll(links => links.map(link => ({
          animation: getComputedStyle(link, '::before').animationName,
          transition: getComputedStyle(link, '::before').transitionDuration,
        })));
        for (const motion of motions) {
          assert.equal(motion.animation, 'none');
          // The shared reduced-motion reset uses 0.01ms !important.
          assert.ok(parseFloat(motion.transition) <= 0.00001);
        }
      }
      await page.close();
    }
    if (!measureOnly) {
      const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 844 } });
      const page = await context.newPage();
      await visit(page, 'index.php', base);
      assert.equal(await page.locator('.nav-toggle').isVisible(), false);
      for (const link of await page.locator('.site-nav a').all()) assert.equal(await link.isVisible(), true);
      await context.close();
      // Exercise the shared header alongside real database lists at the
      // milestone's three sizes, using the existing isolated fixtures.
      try {
        for (const count of [3, 7, 12]) {
          php('tools/db_reset.php', '--test');
          php('tools/ui/specialty-strip-fixture.php', String(count));
          const fixture = JSON.parse(php('-r', `require 'clinic-base/models/slots.php';
            for ($offset = 1; $offset < 7; $offset++) {
              $date = date('Y-m-d', strtotime("+$offset days"));
              $slots = slots_for_day(1, $date);
              if (count($slots) >= 12) { echo json_encode(['date' => $date, 'slots' => $slots]); break; }
            }`));
          for (const width of [375, 1280]) {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            await visit(page, 'index.php', base);
            assert.equal(await page.locator('.doctor-tile').count(), count);
            // Public specialties retain their existing canonical service filter.
            assert.equal(await page.locator('.specialty-item').count(), Math.min(count - 1, 5));
            await visit(page, `book.php?doctor=1&date=${fixture.date}&from=${fixture.slots[0].SlotTime.slice(0, 5)}&to=${fixture.slots[count - 1].SlotTime.slice(0, 5)}`, base);
            assert.equal(await page.locator('#doctor option[value]:not([value=""])').count(), count);
            assert.equal(await page.locator('.schedule-grid > .slot').count(), count);
            if (width === 375) assert.ok(await page.locator('.site-header').evaluate(header => header.offsetHeight <= 100 && header.scrollWidth <= header.clientWidth));
            console.log(`OK: ${count} database doctors/specialties/slots at ${width}px`);
            await page.close();
          }
        }
      } finally { php('tools/db_reset.php', '--test'); }
    }
  } finally { await browser.close(); }
});
console.log(measureOnly ? 'OK: current header measurements' : 'OK: #145 compact phone header, keyboard menu, resize, reduced motion and no-JS links');
