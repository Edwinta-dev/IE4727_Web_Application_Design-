import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const directory = resolve('UIPROBLEMS/after', `hero-size-${before ? 'before' : 'after'}-geometry`);
await mkdir(directory, { recursive: true });
const measurements = [];
await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    for (const [name, route] of [
      ['home', 'index.php'],
      ['directory', 'doctors.php'],
      ['filtered', 'doctors.php?specialty=General%20Practice'],
    ]) {
      for (const width of [390, 768, 1280, 1920]) {
        await page.setViewportSize({ width, height: width === 390 ? 844 : 800 });
        await visit(page, route, base);
        assert.equal(await page.locator('.hero-image').count(), 1);
        await page.locator('.hero-image').evaluate(image => image.decode());
        if (name === 'filtered') assert.equal(await page.locator('#specialty').inputValue(), 'General Practice');
        const measure = () => page.evaluate(() => {
          const image = document.querySelector('.hero-image');
          const rect = selector => {
            const { x, y, width, height, right, bottom } = document.querySelector(selector).getBoundingClientRect();
            return { x, y, width, height, right, bottom };
          };
          return {
            banner: rect('.photo-banner'), image: rect('.hero-image'),
            caption: rect('.photo-caption'), scrim: rect('.photo-scrim'),
            naturalWidth: image.naturalWidth, naturalHeight: image.naturalHeight,
            fit: getComputedStyle(image).objectFit, position: getComputedStyle(image).objectPosition,
            clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth,
          };
        });
        const normal = await measure();
        await page.screenshot({ path: resolve(directory, `${name}-${width}.png`), fullPage: true });
        // Prove geometry works independently of the existing clipping rules.
        await page.addStyleTag({ content: 'body, .photo-banner { overflow: visible !important; }' });
        const unclipped = await measure();
        measurements.push({ route, width, normal, unclipped });
        for (const result of [normal, unclipped]) {
          const label = `${route} at ${width}px`;
          assert.ok(result.scrollWidth <= result.clientWidth + 1, `${label}: document overflow`);
          for (const key of ['x', 'y', 'width', 'height']) {
            assert.ok(Math.abs(result.image[key] - result.banner[key]) <= 1, `${label}: image ${key} differs from banner`);
          }
          assert.ok(result.image.width <= result.naturalWidth + 1, `${label}: image upscaled`);
          assert.ok(result.banner.height <= 440 + 1, `${label}: desktop height cap lost`);
          assert.equal(result.fit, 'cover');
          assert.ok(result.caption.x >= result.banner.x && result.caption.right <= result.banner.right + 1, `${label}: caption horizontal bounds`);
          assert.ok(result.caption.y >= result.banner.y && result.caption.bottom <= result.banner.bottom + 1, `${label}: caption vertical bounds`);
          assert.ok(Math.abs(result.scrim.right - result.banner.right) <= 1 && Math.abs(result.scrim.bottom - result.banner.bottom) <= 1, `${label}: scrim anchor`);
        }
        console.log(`${route} ${width}: banner/image ${normal.image.width.toFixed(2)}x${normal.image.height.toFixed(2)}; scroll/client ${normal.scrollWidth}/${normal.clientWidth}; unclipped ${unclipped.scrollWidth}/${unclipped.clientWidth}`);
      }
    }
  } finally {
    await browser.close();
    await writeFile(resolve(directory, 'rectangles.json'), JSON.stringify(measurements, null, 2));
  }
});
console.log('OK: responsive hero bounds, cropping, anchors and no upscaling; clipping disabled regression passed');
