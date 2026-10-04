import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const output = resolve('UIPROBLEMS/after/issue137-height');
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const measure = page => page.evaluate(() => {
  const rect = el => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y + scrollY, width: r.width, height: r.height };
  };
  return ['.doctor-tiles', '.specialty-strip'].map(selector => {
    const track = document.querySelector(selector);
    const items = [...track.children];
    return {
      selector, track: rect(track),
      allowance: parseFloat(getComputedStyle(track).paddingTop) + parseFloat(getComputedStyle(track).paddingBottom),
      items: items.map(item => {
        // Measure the same content and width with the height constraint removed.
        // Keep the clone in its real parent so inherited/carousel styles still apply.
        const clone = item.cloneNode(true);
        Object.assign(clone.style, {
          position: 'absolute', visibility: 'hidden', pointerEvents: 'none',
          width: `${item.offsetWidth}px`, height: 'auto', minHeight: '0',
          maxHeight: 'none', transform: 'none', flex: 'none',
        });
        track.append(clone);
        const intrinsic = clone.getBoundingClientRect().height;
        clone.remove();
        return { ...rect(item), layoutHeight: item.offsetHeight, intrinsic, minHeight: getComputedStyle(item).minHeight };
      }),
    };
  });
});

try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of [null, 3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        if (count !== null) php('tools/ui/specialty-strip-fixture.php', String(count));
        for (const width of [1280, 1707, 390]) {
          for (const motion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
            try {
              await visit(page, 'index.php', base);
              await page.mouse.move(0, 0);
              const normal = await measure(page);
              records.push({ count: count ?? 'seed', width, motion, normal });
              for (const group of normal) {
                if (count !== null) assert.equal(group.items.length, count, 'Real database-backed item count');
                const naturalRowHeight = Math.max(...group.items.map(item => item.intrinsic));
                for (const item of group.items) {
                  assert.ok(Math.abs(item.layoutHeight - naturalRowHeight) <= 1,
                    `${group.selector} height ${item.layoutHeight} must follow intrinsic row content ${naturalRowHeight}`);
                }
                assert.ok(Math.abs(group.track.height - naturalRowHeight - group.allowance) <= 1,
                  `${group.selector} must reserve only its existing hover padding`);
                console.log(`${count ?? 'seed'}/${width}/${motion} ${group.selector}: card ${group.items[0].layoutHeight}px; content ${naturalRowHeight.toFixed(2)}px; allowance ${group.allowance}px`);
              }
              await page.screenshot({ path: resolve(output, `${count ?? 'seed'}-${width}-${motion}.png`), fullPage: true });
              for (const selector of ['.doctor-tile', '.specialty-item']) {
                const first = page.locator(selector).first();
                for (const state of ['hover', 'focus']) {
                  await page.mouse.move(0, 0);
                  await page.evaluate(() => document.activeElement.blur());
                  await first.scrollIntoViewIfNeeded();
                  await page.waitForTimeout(220);
                  const baseline = await measure(page);
                  if (state === 'hover') await first.hover();
                  else {
                    await first.focus();
                    await page.keyboard.press('Shift+Tab');
                    await page.keyboard.press('Tab');
                    assert.equal(await first.evaluate(el => el.matches(':focus-visible')), true);
                  }
                  await page.waitForTimeout(220);
                  const active = await measure(page);
                  for (let i = 0; i < active.length; i++) {
                    assert.deepEqual(active[i].track, baseline[i].track, 'Hover/focus must preserve row geometry');
                    assert.deepEqual(active[i].items.map(item => item.layoutHeight), baseline[i].items.map(item => item.layoutHeight), 'Hover/focus must preserve layout heights');
                    const affected = selector === '.doctor-tile' ? 0 : 1;
                    if (i === affected) {
                      const item = active[i].items[0], track = active[i].track;
                      assert.ok(item.y >= track.y - 1 && item.y + item.height <= track.y + track.height + 1, 'Hover/focus fits reserved vertical allowance');
                    }
                    if (motion === 'reduce') assert.deepEqual(active[i], baseline[i], 'Reduced motion preserves geometry');
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
console.log('OK: content-sized home cards at 1280/1707/390px; seed and 3/7/12 database-backed doctors/specialties; hover, focus and reduced motion');
