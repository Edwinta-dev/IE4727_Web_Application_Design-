import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve('UIPROBLEMS/after', `issue135-${before ? 'before' : 'after'}-matrix`);
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const measure = page => page.evaluate(() => {
  const rect = el => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y + scrollY, width: r.width, height: r.height };
  };
  const lines = el => Math.round(el.offsetHeight / parseFloat(getComputedStyle(el).lineHeight));
  return {
    row: rect(document.querySelector('.specialty-strip')),
    footer: rect(document.querySelector('.site-footer')),
    scrollWidth: document.documentElement.scrollWidth,
    items: [...document.querySelectorAll('.specialty-item')].map(el => ({
      rect: rect(el), image: rect(el.querySelector('img')),
      heading: lines(el.querySelector('h3')), copy: lines(el.querySelector('p')),
      layoutWidth: el.offsetWidth, layoutHeight: el.offsetHeight,
      padding: getComputedStyle(el).padding,
    })),
  };
});
const close = (a, b, message, tolerance = 0.1) => assert.ok(Math.abs(a - b) < tolerance, `${message}: ${a} vs ${b}`);
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      // Include the real seeded labels and copy, then database-backed dynamic counts.
      for (const count of [5, 3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        if (count !== 5) php('tools/ui/specialty-hover-fixture.php', String(count));
        for (const width of [1280, 1707, 390]) {
          for (const motion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
            try {
              await visit(page, 'index.php', base);
              // Legacy arbitrary categories are retained in doctor profiles,
              // while public services are capped by the canonical clinic list.
              const specialtyCount = Math.min(count, 5);
              assert.equal((await measure(page)).items.length, specialtyCount);
              for (const index of [...new Set([0, Math.floor(specialtyCount / 2), specialtyCount - 1])]) {
                for (const state of ['hover', 'focus']) {
                  await page.mouse.move(0, 0);
                  await page.evaluate(() => document.activeElement.blur());
                  await page.waitForTimeout(220);
                  const item = page.locator('.specialty-item').nth(index);
                  // Paging scrolls to an off-screen link before its state changes.
                  // Compare hover/focus against that settled scroll position.
                  await item.scrollIntoViewIfNeeded();
                  await page.waitForTimeout(600);
                  const normal = await measure(page);
                  if (state === 'hover') await item.hover();
                  else {
                    await item.focus();
                    await page.keyboard.press('Shift+Tab');
                    await page.keyboard.press('Tab');
                    assert.equal(await item.evaluate(el => el.matches(':focus-visible')), true);
                  }
                  await page.waitForTimeout(220);
                  const active = await measure(page);
                  records.push({ count, width, motion, index, state, normal, active });
                  if (index === Math.floor(count / 2) && state === 'hover') {
                    const a = normal.items[index], b = active.items[index];
                    console.log(`${count}/${width}/${motion}: item ${a.rect.width.toFixed(1)}x${a.rect.height.toFixed(1)} -> ${b.rect.width.toFixed(1)}x${b.rect.height.toFixed(1)}; image ${a.image.width.toFixed(1)}x${a.image.height.toFixed(1)} -> ${b.image.width.toFixed(1)}x${b.image.height.toFixed(1)}; top delta ${(b.image.y-a.image.y).toFixed(1)}`);
                    await page.locator('.services').screenshot({ path: resolve(output, `${count}-${width}-${motion}-hover.png`) });
                  }
                  if (before) continue;
                  assert.deepEqual(active.row, normal.row, 'Strip geometry stays fixed');
                  assert.deepEqual(active.footer, normal.footer, 'Footer stays fixed');
                  assert.ok(active.scrollWidth <= width, 'No horizontal overflow');
                  for (let i = 0; i < count; i++) {
                    const a = normal.items[i], b = active.items[i];
                    assert.equal(b.heading, a.heading, 'Heading line count stays fixed');
                    assert.equal(b.copy, a.copy, 'Copy line count stays fixed');
                    assert.equal(b.padding, a.padding, 'No added padding');
                    assert.equal(b.layoutWidth, a.layoutWidth, 'No layout width change');
                    assert.equal(b.layoutHeight, a.layoutHeight, 'No layout height change');
                    close(b.image.width / b.image.height, 16 / 9, 'Image keeps 16:9', 0.001);
                    if (i !== index || motion === 'reduce') assert.deepEqual(b, a, 'Sibling/reduced motion geometry stays fixed');
                    else {
                      close(b.rect.width / a.rect.width, 1.025, 'Uniform magnification', 0.001);
                      close(b.rect.height / a.rect.height, 1.025, 'Uniform magnification', 0.001);
                      close(b.image.width / a.image.width, 1.025, 'Image magnifies with item', 0.001);
                      assert.ok(b.image.y < a.image.y, 'Image lifts rather than sliding down');
                      close(b.rect.x + b.rect.width / 2, a.rect.x + a.rect.width / 2, 'Centered horizontal growth');
                    }
                  }
                  for (let a = 0; a < count; a++) for (let b = a + 1; b < count; b++) {
                    const x = active.items[a].rect, y = active.items[b].rect;
                    assert.ok(x.x + x.width <= y.x || y.x + y.width <= x.x || x.y + x.height <= y.y || y.y + y.height <= x.y, 'Allowance prevents overlap');
                  }
                }
              }
            } finally { await page.close(); }
          }
        }
      }
    } finally { await browser.close(); }
  });
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify(records, null, 2));
  php('tools/db_reset.php', '--test');
}
console.log(before ? 'OK: original specialty geometry captured' : 'OK: specialty hover/focus, 16:9, unchanged text/siblings, 3/7/12 database items and reduced motion');
