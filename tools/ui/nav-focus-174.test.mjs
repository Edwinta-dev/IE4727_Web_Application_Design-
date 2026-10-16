import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { root, visit, withTestServer } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const evidence = resolve(root, 'UIPROBLEMS/after/iter7/nav-focus-174');
await mkdir(evidence, { recursive: true });

const focusStyle = locator => locator.evaluate(element => {
  const style = getComputedStyle(element);
  return {
    outline: style.outlineStyle,
    outlineColor: style.outlineColor,
    expectedColor: getComputedStyle(document.documentElement).getPropertyValue('--c-focus-on-dark').trim(),
    shadow: style.boxShadow,
    radius: style.borderRadius,
    display: style.display,
    visible: element.matches(':focus-visible'),
    focused: element.matches(':focus'),
    width: element.getBoundingClientRect().width,
    height: element.getBoundingClientRect().height,
  };
});

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      try {
        await visit(page, 'index.php', base);
        if (width === 390) await page.locator('.nav-toggle').click();
        const link = page.locator('.site-nav a', { hasText: 'Find a doctor' });
        const box = await link.boundingBox();
        assert.ok(box, `Nav link is visible at ${width}px`);
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await page.mouse.down();
        const mouse = await focusStyle(link);
        await page.screenshot({ path: resolve(evidence, `${before ? 'before' : 'after'}-mouse-${width}.png`) });
        await page.mouse.up();

        await page.goto(base + 'index.php');
        if (width === 390) await page.locator('.nav-toggle').click();
        await page.keyboard.press('Tab');
        for (let step = 0; step < 10 && !(await link.evaluate(el => el.matches(':focus-visible'))); step++) {
          await page.keyboard.press('Tab');
        }
        const keyboard = await focusStyle(link);
        await page.screenshot({ path: resolve(evidence, `${before ? 'before' : 'after'}-keyboard-${width}.png`) });
        console.log(`${width}px mouse=${JSON.stringify(mouse)} keyboard=${JSON.stringify(keyboard)}`);
        assert.equal(mouse.focused, true, 'Mouse press focuses the link');
        assert.equal(mouse.visible, false, 'Mouse press is not keyboard focus');
        assert.equal(keyboard.visible, true, 'Keyboard navigation reaches the link');
        if (before) {
          assert.notEqual(mouse.outline, 'none', 'Reproduce the unwanted mouse outline');
        } else {
          assert.equal(mouse.outline, 'none', 'Mouse press has no outline');
          assert.notEqual(keyboard.outline, 'none', 'Keyboard focus has a visible ring');
          assert.equal(keyboard.expectedColor, '#fffdf9', 'Keyboard ring uses the shared dark-surface token');
          assert.equal(keyboard.outlineColor, 'rgb(255, 253, 249)');
          assert.equal(mouse.shadow, 'none', 'Mouse press has no focus halo');
          assert.notEqual(keyboard.radius, '0px', 'Ring follows a rounded link');
          assert.equal(keyboard.display, width === 1280 ? 'inline-block' : 'block', 'Ring wraps the existing padded tab');
          assert.ok(keyboard.height >= 44, 'Padded tab remains a touch target');
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});
console.log(`OK: #174 ${before ? 'reproduced' : 'mouse and keyboard focus verified'} at 1280/390`);
