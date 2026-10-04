import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const directory = resolve('UIPROBLEMS/after', `home-gap-${before ? 'before' : 'after'}-geometry`);
await mkdir(directory, { recursive: true });
const measurements = [];
await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      for (const motion of ['no-preference', 'reduce']) {
        await page.emulateMedia({ reducedMotion: motion });
        await visit(page, 'index.php', base);
        await page.evaluate(async () => Promise.all(Array.from(document.images, image => {
          image.loading = 'eager';
          return image.decode().catch(() => {});
        })));
        const measure = () => page.evaluate(() => {
          const rect = element => {
            const r = element.getBoundingClientRect();
            return { x: r.x, y: r.y + scrollY, right: r.right, bottom: r.bottom + scrollY, width: r.width, height: r.height };
          };
          const items = Array.from(document.querySelectorAll('.specialty-item'));
          const contentBottom = Math.max(...items.map(item => rect(item.lastElementChild).bottom));
          const strip = rect(document.querySelector('.specialty-strip'));
          const footer = rect(document.querySelector('.site-footer'));
          return {
            strip, footer, contentBottom, gap: footer.y - strip.bottom,
            visibleGap: footer.y - contentBottom,
            items: items.map(rect), main: rect(document.querySelector('main')),
            minHeight: getComputedStyle(items[0]).minHeight,
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
          };
        });
        const normal = await measure();
        measurements.push({ width, motion, state: 'normal', ...normal });
        console.log(`${width} ${motion}: strip bottom ${normal.strip.bottom.toFixed(3)}, content bottom ${normal.contentBottom.toFixed(3)}, footer top ${normal.footer.y.toFixed(3)}, gap ${normal.gap.toFixed(3)}, visible gap ${normal.visibleGap.toFixed(3)}, min-height ${normal.minHeight}`);
        await page.screenshot({ path: resolve(directory, `home-${width}-${motion}.png`), fullPage: true });
        if (!before) assert.ok(normal.visibleGap <= 100, 'Unnecessary space below specialty content');
        for (const index of [0, 2, 4]) {
          const item = page.locator('.specialty-item').nth(index);
          for (const state of ['hover', 'focus']) {
            await page.mouse.move(0, 0);
            await page.evaluate(() => document.activeElement.blur());
            // A specialty may be on another carousel page. Measure after
            // revealing it, so scrolling is not mistaken for hover reflow.
            await item.scrollIntoViewIfNeeded();
            await page.waitForTimeout(600);
            const normal = await measure();
            if (state === 'hover') await item.hover();
            else {
              await item.focus();
              await page.keyboard.press('Shift+Tab');
              await page.keyboard.press('Tab');
              assert.equal(await item.evaluate(element => element.matches(':focus-visible')), true);
            }
            await page.waitForTimeout(250);
            const result = await measure();
            measurements.push({ width, motion, state, index, ...result });
            if (width === 1280 && motion === 'no-preference') {
              if (!before) assert.ok(result.items[index].width > normal.items[index].width && result.items[index].y < normal.items[index].y, 'Specialty magnifies and lifts');
            }
            if (!before) {
              assert.deepEqual(result.strip, normal.strip, 'Magnification must preserve strip geometry');
              assert.deepEqual(result.footer, normal.footer, 'Magnification must preserve footer geometry');
              for (let sibling = 0; sibling < normal.items.length; sibling++) {
                if (sibling !== index || motion === 'reduce') assert.deepEqual(result.items[sibling], normal.items[sibling], 'Sibling/reduced motion geometry stays fixed');
              }
            }
            assert.ok(result.footer.y >= result.strip.bottom, 'Specialties overlap footer');
            assert.ok(result.contentBottom <= result.strip.bottom + 1, 'Content escapes strip');
            assert.ok(result.scrollWidth <= result.clientWidth, 'Horizontal overflow');
            for (let a = 0; a < result.items.length; a++) {
              for (let b = a + 1; b < result.items.length; b++) {
                const first = result.items[a], second = result.items[b];
                assert.ok(first.right <= second.x + 1 || second.right <= first.x + 1 || first.bottom <= second.y + 1 || second.bottom <= first.y + 1, 'Specialty items overlap');
              }
            }
            assert.ok(Math.abs(result.main.x - result.footer.x) < 1 && Math.abs(result.main.width - result.footer.width) < 1, 'Shared footer alignment');
            if (state === 'focus' && width === 1280) {
              await page.screenshot({ path: resolve(directory, `focus-${index}-${motion}.png`), fullPage: true });
            }
          }
        }
      }
      for (const route of ['doctors.php', 'doctors.php?specialty=NoSuchSpecialty']) {
        await visit(page, route, base);
        const result = await page.evaluate(() => {
          const main = document.querySelector('main').getBoundingClientRect();
          const footer = document.querySelector('.site-footer').getBoundingClientRect();
          return { mainX: main.x, mainWidth: main.width, mainBottom: main.bottom + scrollY, footerX: footer.x, footerWidth: footer.width, footerTop: footer.y + scrollY };
        });
        measurements.push({ width, route, ...result });
        assert.equal(result.mainX, result.footerX);
        assert.equal(result.mainWidth, result.footerWidth);
        assert.ok(result.footerTop >= result.mainBottom, 'Directory/short page footer overlaps main');
        await page.screenshot({ path: resolve(directory, `${route.includes('?') ? 'short' : 'directory'}-${width}.png`), fullPage: true });
      }
    }
  } finally {
    await browser.close();
    await writeFile(resolve(directory, 'measurements.json'), JSON.stringify(measurements, null, 2));
  }
});
console.log('OK: home gap measurements; first/middle/last hover and focus, reduced motion, directory and short-page alignment');
