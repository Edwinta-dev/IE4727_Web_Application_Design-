import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve('UIPROBLEMS/after', `issue154-${before ? 'before' : 'after'}-matrix`);
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      for (const count of [3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        php('tools/ui/featured-doctors-fixture.php', String(count), '--tiles');
        for (const width of [1280, 390]) {
          for (const reducedMotion of ['no-preference', 'reduce']) {
            const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion });
            try {
              await visit(page, 'index.php', base);
              const measurement = await page.evaluate(() => {
                const tiles = [...document.querySelectorAll('.doctor-tile')];
                return {
                  specialties: document.querySelectorAll('.specialty-item').length,
                  tiles: tiles.map(tile => {
                    const box = tile.getBoundingClientRect();
                    const image = tile.querySelector('img').getBoundingClientRect();
                    const specialty = tile.querySelector('.doctor-specialty');
                    const range = document.createRange();
                    range.selectNodeContents(specialty);
                    const text = [...range.getClientRects()];
                    return {
                      height: box.height,
                      linkBottom: box.bottom - tile.querySelector('.doctor-tile-link').getBoundingClientRect().bottom,
                      textFits: text.every(rect => rect.left >= box.left && rect.right <= box.right),
                      portraitRatio: image.width / image.height,
                      src: tile.querySelector('img').getAttribute('src'),
                      alt: tile.querySelector('img').getAttribute('alt'),
                    };
                  }),
                };
              });
              records.push({ count, width, reducedMotion, ...measurement });
              console.log(`${count}/${width}/${reducedMotion}: ${measurement.tiles.filter(t => !t.textFits).length} overflowing labels; heights ${[...new Set(measurement.tiles.map(t => t.height))]}; logo fallbacks ${measurement.tiles.filter(t => t.src.includes('clinic-logo')).length}`);
              await page.locator('.featured-doctors').screenshot({ path: resolve(output, `${count}-${width}-${reducedMotion}.png`) });
              if (!before) {
                assert.equal(measurement.tiles.length, count);
                // The clinic supports exactly five specialties; 7/12 distinct
                // services cannot be rendered without changing that contract.
                assert.equal(measurement.specialties, Math.min(count, 5));
                assert.ok(measurement.tiles.every(t => t.textFits), 'All specialty text must fit inside its card');
                assert.ok(measurement.tiles.every(t => Math.abs(t.portraitRatio - 0.8) < 0.01), 'Every portrait has a 4:5 crop');
                assert.ok(measurement.tiles.every(t => !t.src.includes('clinic-logo')), 'Clinic logo must never be a portrait');
                assert.ok(measurement.tiles.some(t => t.src.includes('doctor-placeholder.svg') && t.alt.includes('unavailable')), 'Missing portraits use an honestly labelled neutral avatar');
                assert.ok(Math.max(...measurement.tiles.map(t => t.height)) - Math.min(...measurement.tiles.map(t => t.height)) < 1, 'Varied excerpts have equal card heights');
                assert.ok(Math.max(...measurement.tiles.map(t => t.linkBottom)) - Math.min(...measurement.tiles.map(t => t.linkBottom)) < 1, 'Links align at the bottom');
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
console.log(before ? 'OK: #154 baseline measurements captured' : 'OK: #154 doctor portraits, specialty containment, equal heights and bottom links; 3/7/12 doctors, 3/5 specialties, desktop/mobile, reduced motion');
