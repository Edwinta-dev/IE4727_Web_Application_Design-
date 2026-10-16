import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const output = resolve('UIPROBLEMS/after/iter7/booking-focus');
await mkdir(output, { recursive: true });

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 } });
      try {
        await visit(page, 'book.php', base);
        for (const [name, selector] of [['date', '.day-tab.selected'], ['reason', '#reason']]) {
          const control = page.locator(selector);
          assert.equal(await control.count(), 1, `${name} control exists`);
          await page.keyboard.press('Tab');
          await control.focus();
          const style = await control.evaluate(element => {
            const computed = getComputedStyle(element);
            return {
              focusVisible: element.matches(':focus-visible'),
              color: computed.outlineColor,
              width: computed.outlineWidth,
              radius: computed.borderRadius,
              shadow: computed.boxShadow,
            };
          });
          console.log(`${width} ${name}: ${JSON.stringify(style)}`);
          await page.screenshot({ path: resolve(output, `${before ? 'before' : 'after'}-${name}-${width}.png`), fullPage: true });
          if (!before) {
            assert.equal(style.focusVisible, true, `${name} keyboard focus is visible`);
            assert.equal(style.color, 'rgb(15, 118, 110)', `${name} uses primary focus color`);
            assert.notEqual(style.width, '0px', `${name} keeps a visible ring`);
            assert.notEqual(style.radius, '0px', `${name} ring follows rounded control`);
            assert.equal(style.shadow, 'none', `${name} has no extra glow`);
            await page.locator('h1').click();
            assert.equal(await control.evaluate(element => element.matches(':focus-visible')), false);
          }
        }
        if (!before) {
          // Probe the shared rule on all control types, including a native date
          // input and a textarea that use the same stylesheet on other pages.
          await page.evaluate(() => {
            for (const [tag, type] of [['input', 'date'], ['textarea', null]]) {
              const element = document.createElement(tag);
              if (type) element.type = type;
              element.style.position = 'absolute';
              element.style.left = '-9999px';
              document.body.append(element);
            }
          });
          for (const selector of ['#specialty', 'input[type="date"]', 'textarea']) {
            const control = page.locator(selector).first();
            await page.keyboard.press('Tab');
            await control.focus();
            const style = await control.evaluate(element => {
              const computed = getComputedStyle(element);
              return [element.matches(':focus-visible'), computed.outlineColor, computed.boxShadow];
            });
            assert.deepEqual(style, [true, 'rgb(15, 118, 110)', 'none'], `${selector} uses the shared ring`);
          }
        }
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
});
console.log(`OK: #181 ${before ? 'before evidence' : 'date and reason focus'} at 1280/390`);
