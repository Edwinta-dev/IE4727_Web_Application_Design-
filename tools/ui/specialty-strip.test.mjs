import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const requestedCount = Number(process.argv.find(arg => arg.startsWith('--count='))?.split('=')[1]);
const requestedWidth = Number(process.argv.find(arg => arg.startsWith('--width='))?.split('=')[1]);
const counts = requestedCount ? [requestedCount] : [3, 6, 10, 7, 12];
const widths = requestedWidth ? [requestedWidth] : [1280, 1024, 768, 390];
assert.ok(counts.every(count => [3, 6, 10, 7, 12].includes(count)));
assert.ok(widths.every(width => [1280, 1024, 768, 390].includes(width)));
const output = resolve('UIPROBLEMS/after', `issue136-${before ? 'before' : 'after'}-matrix`);
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const measure = page => page.evaluate(() => {
  const track = document.querySelector('.specialty-strip');
  const rect = el => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y, width: r.width, height: r.height };
  };
  return {
    track: rect(track), scroll: track.scrollLeft, max: track.scrollWidth - track.clientWidth,
    behavior: getComputedStyle(track).scrollBehavior,
    documentWidth: document.documentElement.scrollWidth,
    items: [...track.children].map(el => ({ ...rect(el), layoutWidth: el.offsetWidth,
      image: rect(el.querySelector('img')), textHeight: el.querySelector('p').offsetHeight })),
  };
});
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of counts) {
        php('tools/db_reset.php', '--test');
        php('tools/ui/specialty-strip-fixture.php', String(count));
        for (const width of widths) {
          for (const motion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
            try {
              const errors = [];
              page.on('pageerror', error => errors.push(error.message));
              await visit(page, 'index.php', base);
              await page.locator('.services').scrollIntoViewIfNeeded();
              const normal = await measure(page);
              records.push({ count, width, motion, normal });
              console.log(`${count}/${width}/${motion}: min width ${Math.min(...normal.items.map(t => t.layoutWidth))}; rows ${new Set(normal.items.map(t => t.y)).size}`);
              if (width === 1280 || width === 390) await page.screenshot({ path: resolve(output, `${count}-${width}-${motion}.png`), fullPage: true });
              if (before) continue;
              assert.deepEqual(errors, []);
              assert.equal(normal.items.length, count);
              assert.equal(new Set(normal.items.map(t => t.y)).size, 1, 'One scrollable row, no orphan rows');
              assert.ok(normal.items.every(t => t.layoutWidth >= 180), 'Readable specialty width');
              assert.equal(normal.documentWidth, width, 'No document overflow');
              await page.locator('.specialty-item').first().hover();
              await page.waitForTimeout(220);
              const hover = await measure(page);
              assert.deepEqual(hover.items.slice(1), normal.items.slice(1), 'No sibling reflow');
              assert.deepEqual(hover.track, normal.track, 'No row reflow');
              assert.equal(hover.items[0].textHeight, normal.items[0].textHeight);
              const first = hover.items[0];
              assert.ok(first.x >= normal.track.x && first.y >= normal.track.y, 'Hover allowance inside track');
              assert.ok(first.y + first.height <= normal.track.y + normal.track.height, 'Bottom allowance');
              assert.ok(first.x + first.width <= hover.items[1].x, 'No neighbour overlap');
              if (motion === 'reduce') {
                assert.deepEqual(hover.items, normal.items);
                assert.equal(normal.behavior, 'auto');
              } else assert.ok(first.width > normal.items[0].width, 'Hover magnifies');
              await page.mouse.move(0, 0);
              const next = page.getByRole('button', { name: 'Next specialties', exact: true });
              const previous = page.getByRole('button', { name: 'Previous specialties', exact: true });
              assert.ok(await next.evaluate(el => el.offsetWidth >= 44 && el.offsetHeight >= 44));
              assert.equal(await previous.isDisabled(), true);
              if (normal.max > 1) {
                await next.focus();
                await page.keyboard.press('Enter');
                await page.waitForTimeout(600);
                assert.ok((await measure(page)).scroll > 0, 'Keyboard paging');
                for (let i = 0; i < count && !(await next.isDisabled()); i++) {
                  await next.click();
                  await page.waitForTimeout(600);
                }
                assert.equal(await next.isDisabled(), true);
                const end = await measure(page);
                assert.ok(Math.abs(end.scroll - end.max) < 2, 'Last specialty reachable');
                await previous.click();
                await page.waitForTimeout(600);
                assert.ok((await measure(page)).scroll < end.scroll);
              } else assert.equal(await next.isDisabled(), true);
              await page.setViewportSize({ width: width === 390 ? 1280 : 390, height: 844 });
              await page.waitForTimeout(220);
              assert.ok((await measure(page)).items.every(t => t.layoutWidth >= 180), 'Resize preserves widths');
            } finally { await page.close(); }
          }
          if (!before) {
            const page = await browser.newPage({ javaScriptEnabled: false, viewport: { width, height: 844 } });
            try {
              await visit(page, 'index.php', base);
              await page.locator('.specialty-item').last().focus();
              await page.waitForTimeout(1000);
              const end = await measure(page), last = end.items.at(-1);
              records.push({ count, width, javaScript: false, end });
              assert.ok(last.x >= end.track.x && last.x + last.width <= end.track.x + end.track.width, `No-JS keyboard access: ${JSON.stringify({ last, track: end.track, scroll: end.scroll, max: end.max })}`);
              assert.ok(end.items.every(t => t.layoutWidth >= 180));
              assert.equal(await page.getByRole('button', { name: 'Next specialties' }).count(), 0);
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
console.log(before ? 'OK: original specialty widths and rows captured' : `OK: specialties ${counts.join('/')} at ${widths.join('/')}; hover allowance, arrows, keyboard, resize, reduced motion and no-JS access`);
