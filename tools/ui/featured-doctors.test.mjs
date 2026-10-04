import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve('UIPROBLEMS/after', `issue134-${before ? 'before' : 'after'}-matrix`);
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const measure = page => page.evaluate(() => {
  const tiles = [...document.querySelectorAll('.doctor-tile')];
  const row = document.querySelector('.doctor-tiles');
  const lines = el => {
    const style = getComputedStyle(el);
    return Math.round((el.getBoundingClientRect().height - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)) / parseFloat(style.lineHeight));
  };
  return {
    height: row.getBoundingClientRect().height,
    width: document.documentElement.scrollWidth,
    tiles: tiles.map(el => ({ width: el.offsetWidth, heading: lines(el.querySelector('h3')),
      link: lines(el.querySelector('.doctor-tile-link')), specialty: el.querySelector('.doctor-specialty')?.getBoundingClientRect().height })),
    scroll: row.scrollLeft, max: row.scrollWidth - row.clientWidth,
    behavior: getComputedStyle(row).scrollBehavior,
    pageSize: Number(getComputedStyle(row).getPropertyValue('--doctors-per-page')),
  };
});
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of [3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        php('tools/ui/featured-doctors-fixture.php', String(count));
        for (const width of [1280, 1024, 768, 390]) {
          for (const motion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
            page.setDefaultNavigationTimeout(60000);
            try {
              const errors = [];
              page.on('pageerror', error => errors.push(error.message));
              await visit(page, 'index.php', base);
              const normal = await measure(page);
              assert.deepEqual(errors, [], 'No browser errors');
              assert.equal(normal.tiles.length, count);
              await page.locator('.doctor-tile').first().hover();
              await page.waitForTimeout(250);
              const hover = await measure(page);
              records.push({ count, width, motion, normal, hover });
              console.log(`${count} doctors / ${width} / ${motion}: min width ${Math.min(...normal.tiles.map(t => t.width))}, max heading lines ${Math.max(...normal.tiles.map(t => t.heading))}, height ${normal.height} -> ${hover.height}`);
              await page.locator('.featured-doctors').screenshot({ path: resolve(output, `${count}-${width}-${motion}.png`) });
              if (!before) {
                assert.ok(normal.tiles.every(t => t.width >= 200 && t.heading <= 2 && t.link <= 2));
                assert.deepEqual(hover.tiles.slice(1), normal.tiles.slice(1), 'Hover must not reflow neighbours');
                assert.equal(hover.height, normal.height, 'Hover must not change row height');
                assert.equal(normal.width, width, 'No document overflow');
                assert.equal(normal.pageSize, width >= 1200 ? 4 : width >= 640 ? 2 : 1);
                if (motion === 'reduce') assert.equal(normal.behavior, 'auto');
                const prev = page.getByRole('button', { name: 'Previous featured doctors' });
                const next = page.getByRole('button', { name: 'Next featured doctors' });
                assert.ok(await next.evaluate(el => el.offsetWidth >= 44 && el.offsetHeight >= 44), 'Arrow tap target');
                assert.equal(await prev.isDisabled(), true);
                if (normal.max > 1) {
                  await next.focus();
                  assert.equal(await next.evaluate(el => el.matches(':focus-visible')), true);
                  await page.keyboard.press('Enter');
                  await page.waitForTimeout(600);
                  assert.ok((await measure(page)).scroll > 0);
                  for (let i = 0; i < count && !(await next.isDisabled()); i++) {
                    await next.click();
                    await page.waitForTimeout(600);
                  }
                  assert.equal(await next.isDisabled(), true);
                  const end = await measure(page);
                  assert.ok(Math.abs(end.scroll - end.max) < 2, 'Last tile reachable');
                  await prev.focus();
                  await page.keyboard.press('Space');
                  await page.waitForTimeout(600);
                  assert.ok((await measure(page)).scroll < end.scroll);
                  await page.setViewportSize({ width: width === 390 ? 1280 : 390, height: 844 });
                  await page.waitForTimeout(100);
                  assert.ok((await measure(page)).tiles.every(t => t.width >= 200));
                  await page.setViewportSize({ width, height: width === 390 ? 844 : 800 });
                  await page.waitForTimeout(100);
                } else assert.equal(await next.isDisabled(), true);
                await page.locator('.doctor-tile').first().focus();
                await page.waitForTimeout(250);
                assert.equal((await measure(page)).height, normal.height);
              }
            } finally { await page.close(); }
          }
          if (!before) {
            const page = await browser.newPage({ javaScriptEnabled: false, viewport: { width, height: 844 } });
            page.setDefaultNavigationTimeout(60000);
            try {
              await visit(page, 'index.php', base);
              const result = await measure(page);
              assert.ok(result.tiles.every(t => t.width >= 200 && t.heading <= 2 && t.link <= 2));
              assert.equal(await page.getByRole('button', { name: 'Next featured doctors' }).count(), 0);
              await page.locator('.doctor-tile').last().focus();
              // With page scripts disabled, inspect settled geometry directly.
              await page.waitForTimeout(1000);
              const lastVisible = await page.locator('.doctor-tile').last().evaluate(el => {
                const tile = el.getBoundingClientRect();
                const row = el.parentElement.getBoundingClientRect();
                return tile.left >= row.left && tile.right <= row.right;
              });
              assert.ok(lastVisible, 'No-JS keyboard focus must reveal the entire last doctor tile');
              assert.equal(await page.locator('.doctor-tiles').evaluate(el => getComputedStyle(el).scrollSnapType), 'x mandatory');
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
console.log(before ? 'OK: before measurements captured' : 'OK: featured doctors 3/7/12, four widths, hover/focus, keyboard paging, resize, reduced motion and no-JS scroll snap');
