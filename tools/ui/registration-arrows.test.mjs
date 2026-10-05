import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { withTestServer, visit } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve('UIPROBLEMS/after', `issue149-${before ? 'before' : 'after'}-states`);
await mkdir(output, { recursive: true });
const records = [];
const php = (...args) => testPhp(args, { encoding: 'utf8' });
const measure = button => button.evaluate(el => {
  const style = getComputedStyle(el);
  const luminance = color => {
    const values = color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => {
      v /= 255;
      return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return values[0] * 0.2126 + values[1] * 0.7152 + values[2] * 0.0722;
  };
  const fg = luminance(style.color), bg = luminance(style.backgroundColor);
  const range = document.createRange();
  range.selectNodeContents(el);
  return { glyph: el.textContent, color: style.color, background: style.backgroundColor,
    contrast: (Math.max(fg, bg) + 0.05) / (Math.min(fg, bg) + 0.05),
    contentWidth: el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
    glyphWidth: range.getBoundingClientRect().width };
});
try {
  php('tools/db_reset.php', '--test');
  await withTestServer(true, async base => {
    const browser = await chromium.launch();
    try {
      for (const width of [1280, 390]) {
        for (const motion of ['no-preference', 'reduce']) {
          const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
          try {
            await visit(page, 'register.php', base);
            for (const role of ['patient', 'doctor']) {
              await page.locator(`[data-role-switch="${role}"]`).click();
              await page.waitForTimeout(220);
              assert.equal(await page.locator('[name="role"]:checked').inputValue(), role);
              assert.equal(await page.locator(`#${role}-fields input`).first().isEnabled(), true);
              for (const target of ['patient', 'doctor']) {
                const button = page.locator(`[data-role-switch="${target}"]`);
                for (const state of ['rest', 'hover', 'focus', 'pressed']) {
                  await page.mouse.move(0, 0);
                  await button.evaluate(el => el.blur());
                  if (state === 'hover' || state === 'pressed') await button.hover();
                  if (state === 'focus') await button.focus();
                  if (state === 'pressed') await page.mouse.down();
                  await page.waitForTimeout(200);
                  const record = { width, motion, role, target, state, ...await measure(button) };
                  records.push(record);
                  if (state === 'pressed') await page.mouse.up();
                  if (!before) {
                    assert.ok(record.contrast >= 4.5, JSON.stringify(record));
                    assert.ok(record.contentWidth >= record.glyphWidth, JSON.stringify(record));
                    assert.equal(record.glyph.trim(), target === 'patient' ? '←' : '→');
                  }
                }
              }
              await page.locator(`[data-role-switch="${role}"]`).hover();
              await page.screenshot({ path: resolve(output, `${role}-${width}-${motion}.png`), fullPage: true });
            }
            await page.locator('[data-role-switch="patient"]').focus();
            await page.keyboard.press('ArrowRight');
            assert.equal(await page.locator('[name="role"]:checked').inputValue(), 'doctor');
            await page.keyboard.press('ArrowLeft');
            assert.equal(await page.locator('[name="role"]:checked').inputValue(), 'patient');
          } finally { await page.close(); }
        }
      }
      for (const count of [3, 7, 12]) {
        php('tools/db_reset.php', '--test');
        php('tools/ui/specialty-strip-fixture.php', String(count));
        for (const width of [1280, 390]) {
          const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
          try {
            await visit(page, 'index.php', base);
            const images = await page.locator('.specialty-item img').evaluateAll(els => els.map(el => ({ src: el.getAttribute('src'), loaded: el.complete && el.naturalWidth > 0 })));
            assert.ok(images.every(image => image.loaded));
            // The directory already filters unknown specialties (#138); test the
            // fallback asset separately without changing that service list.
            if (!before) {
              const fallback = await page.evaluate(async base => {
                const image = new Image();
                image.src = base + 'assets/img/specialty-neutral.svg';
                await image.decode();
                return { width: image.naturalWidth, height: image.naturalHeight };
              }, base);
              assert.deepEqual(fallback, { width: 640, height: 360 });
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
            records.push({ count, width, images });
            await page.screenshot({ path: resolve(output, `specialties-${count}-${width}.png`), fullPage: true });
          } finally { await page.close(); }
        }
      }
    } finally { await browser.close(); }
  });
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify(records, null, 2));
  php('tools/db_reset.php', '--test');
}
console.log(before ? 'OK: #149 before measurements captured' : 'OK: #149 arrow contrast and glyph fit in all states; keyboard switching; reduced motion; 3/7/12 specialty fixtures');
