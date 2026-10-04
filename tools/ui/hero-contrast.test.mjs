import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit, login } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const directory = resolve('UIPROBLEMS/after', `hero-contrast-${before ? 'before' : 'after'}-measurements`);
await mkdir(directory, { recursive: true });
const results = [];
await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    let page;
    // 640 CSS px at device scale 2 models a 1280px window at 200% zoom.
    const cases = before ? [[390, 100, 1], [1280, 100, 1]] : [[390, 100, 1], [1280, 100, 1], [390, 200, 1], [1280, 200, 1], [640, 100, 2], [320, 200, 1]];
    for (const [width, textSize, scale] of cases) {
      if (page) await page.close();
      page = await browser.newPage({ deviceScaleFactor: scale });
      await page.setViewportSize({ width, height: width <= 390 ? 844 : 800 });
      await visit(page, 'index.php', base);
      await page.addStyleTag({ content: `html { font-size: ${textSize}% !important; }` });
      await page.locator('.hero-image').evaluate(image => image.decode());
      const geometry = await page.evaluate(() => {
        const rect = selector => {
          const { x, y, right, bottom, width, height } = document.querySelector(selector).getBoundingClientRect();
          return { x, y, right, bottom, width, height };
        };
        return { banner: rect('.photo-banner'), caption: rect('.photo-caption'), eyebrow: rect('.photo-caption .eyebrow'), heading: rect('.photo-caption h1'), login: rect('.member-login') };
      });
      const name = `${width}-text${textSize}-zoom${scale}`;
      await page.screenshot({ path: resolve(directory, `${name}.png`), fullPage: true });
      await page.screenshot({ path: resolve(directory, `${name}-viewport.png`) });
      await page.addStyleTag({ content: '.photo-caption, .photo-caption * { transition: none !important; }' });
      // Preserve layout and the real composited photograph; remove just glyphs.
      const hide = await page.addStyleTag({ content: '.photo-caption, .photo-caption * { color: transparent !important; }' });
      const background = await page.locator('.photo-banner').screenshot();
      await hide.evaluate(element => element.remove());
      // Render a glyph mask at the identical positions, without the photo/scrim.
      const maskStyle = await page.addStyleTag({ content: '.photo-banner { background: #000 !important; } .photo-banner .hero-image, .photo-banner .photo-scrim { visibility: hidden !important; } .photo-caption, .photo-caption * { color: #fff !important; }' });
      const mask = await page.locator('.photo-banner').screenshot();
      await maskStyle.evaluate(element => element.remove());
      const contrast = await page.evaluate(async ({ background, mask, scale }) => {
        async function pixels(encoded) {
          const image = new Image(); image.src = 'data:image/png;base64,' + encoded; await image.decode();
          const canvas = document.createElement('canvas'); canvas.width = image.width; canvas.height = image.height;
          const context = canvas.getContext('2d'); context.drawImage(image, 0, 0);
          // Drop fractional locator edges that can include the page surface.
          const edge = 2 * scale;
          return context.getImageData(edge, edge, canvas.width - 2 * edge, canvas.height - 2 * edge).data;
        }
        const bg = await pixels(background), glyphs = await pixels(mask);
        const linear = byte => { const value = byte / 255; return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4; };
        let minimum = Infinity, samples = 0;
        for (let offset = 0; offset < bg.length; offset += 4) {
          // Include antialiased edges too; compare specified white foreground
          // against the underlying background, not the antialiased text pixel.
          if (!glyphs[offset] && !glyphs[offset + 1] && !glyphs[offset + 2]) continue;
          const luminance = [0.2126, 0.7152, 0.0722].reduce((sum, weight, channel) => sum + weight * linear(bg[offset + channel]), 0);
          minimum = Math.min(minimum, 1.05 / (luminance + 0.05)); samples++;
        }
        return { minimum, samples };
      }, { background: background.toString('base64'), mask: mask.toString('base64'), scale });
      results.push({ width, textSize, zoomEquivalent: scale, geometry, ...contrast });
      console.log(`${name}: minimum ${contrast.minimum.toFixed(3)}:1 across ${contrast.samples} glyph-background pixels`);
      assert.ok(contrast.samples > 100, 'Caption glyph mask must contain actual text');
      if (!before) {
        assert.ok(contrast.minimum >= 4.5, `${name}: composited photo contrast`);
        assert.ok(geometry.banner.x >= 0 && geometry.banner.right <= width, `${name}: photograph stays within viewport`);
        for (const key of ['eyebrow', 'heading']) {
          const rect = geometry[key], banner = geometry.banner;
          assert.ok(rect.x >= banner.x && rect.right <= banner.right + 1 && rect.y >= banner.y && rect.bottom <= banner.bottom + 1, `${name}: ${key} fully inside photograph`);
        }
        assert.ok(geometry.login.y >= geometry.banner.bottom || geometry.login.x >= geometry.banner.right, `${name}: login does not overlap hero`);
      }
    }
    // Also render a real high-density 200% zoom equivalent, and check login.
    const zoom = await browser.newPage({ viewport: { width: 640, height: 400 }, deviceScaleFactor: 2 });
    await visit(zoom, 'index.php', base);
    await zoom.screenshot({ path: resolve(directory, 'desktop-200percent-zoom.png'), fullPage: true });
    await login(page, 'patient', base);
    assert.ok(page.url().endsWith('/patient/home.php'), 'Existing member login still works');
  } finally {
    await browser.close();
    await writeFile(resolve(directory, 'contrast.json'), JSON.stringify(results, null, 2));
  }
});
console.log(before ? 'OK: baseline photo contrast measured' : 'OK: actual photo contrast, caption wrapping/text enlargement/zoom and member login');
