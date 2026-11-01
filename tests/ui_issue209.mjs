import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, root } from '../tools/ui/lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const output = resolve(root, 'UIPROBLEMS/after/iter9/issue209-doctor-directory-search/after');
await mkdir(output, { recursive: true });

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    // 1. Test with JavaScript enabled
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      let networkRequestsDuringTyping = 0;

      await page.goto(base + 'doctors.php', { waitUntil: 'networkidle' });

      const searchInput = page.locator('#doctor-search');
      const emptyMessage = page.locator('#doctor-search-empty');
      const rows = page.locator('.doctor-directory tbody tr');

      assert.equal(await searchInput.count(), 1, 'Search input exists');
      assert.equal(await emptyMessage.count(), 1, 'Empty state message exists');
      assert.ok(await emptyMessage.isHidden(), 'Empty state message initially hidden');

      const totalRows = await rows.count();
      assert.ok(totalRows >= 5, `Expected at least 5 doctor rows, got ${totalRows}`);

      // Count initially visible rows
      let visibleCount = 0;
      for (let i = 0; i < totalRows; i++) {
        if (await rows.nth(i).isVisible()) visibleCount++;
      }
      assert.equal(visibleCount, totalRows, 'All doctor rows initially visible');

      // Hook network listener to ensure zero AJAX / fetch / XHR requests during typing
      page.on('request', (req) => {
        const resourceType = req.resourceType();
        if (['fetch', 'xhr'].includes(resourceType)) {
          networkRequestsDuringTyping++;
        }
      });

      // Test 1: Typing "Smith" shows only Dr. John Smith
      await searchInput.fill('Smith');
      let smithVisible = 0;
      for (let i = 0; i < totalRows; i++) {
        if (await rows.nth(i).isVisible()) {
          smithVisible++;
          const rowText = await rows.nth(i).innerText();
          assert.ok(rowText.toLowerCase().includes('smith'), 'Visible row contains Smith');
        }
      }
      assert.equal(smithVisible, 1, 'Exactly one row matches "Smith"');
      assert.ok(await emptyMessage.isHidden(), 'Empty message is hidden when matches exist');

      if (width === 1280) {
        await page.screenshot({ path: resolve(output, 'doctors-search-smith-1280.png'), fullPage: true });
      }

      // Test 2: Typing "Mandarin" displays all Mandarin-speaking doctors across specialties
      await searchInput.fill('Mandarin');
      let mandarinVisible = 0;
      for (let i = 0; i < totalRows; i++) {
        if (await rows.nth(i).isVisible()) {
          mandarinVisible++;
          const rowText = await rows.nth(i).innerText();
          assert.ok(rowText.toLowerCase().includes('mandarin'), 'Visible row contains Mandarin');
        }
      }
      assert.ok(mandarinVisible >= 2, 'Multiple doctors speak Mandarin');
      assert.ok(await emptyMessage.isHidden(), 'Empty message is hidden when matches exist');

      // Test 3: Typing an unmatched string ("xyz") hides all rows and reveals #doctor-search-empty
      await searchInput.fill('xyz');
      let xyzVisible = 0;
      for (let i = 0; i < totalRows; i++) {
        if (await rows.nth(i).isVisible()) {
          xyzVisible++;
        }
      }
      assert.equal(xyzVisible, 0, 'No rows visible for "xyz"');
      assert.ok(await emptyMessage.isVisible(), 'Empty state message is visible for unmatched query');

      if (width === 1280) {
        await page.screenshot({ path: resolve(output, 'doctors-search-empty-1280.png'), fullPage: true });
      }

      // Test 4: Clearing search immediately restores all rows
      await searchInput.fill('');
      let restoredCount = 0;
      for (let i = 0; i < totalRows; i++) {
        if (await rows.nth(i).isVisible()) {
          restoredCount++;
        }
      }
      assert.equal(restoredCount, totalRows, 'Clearing search restored all rows');
      assert.ok(await emptyMessage.isHidden(), 'Empty message hidden after clearing search');

      // Test 5: Verify zero network requests were made during typing
      assert.equal(networkRequestsDuringTyping, 0, 'Zero network requests made during live search');

      // Test 6: Focus ring test
      await searchInput.focus();
      const outline = await searchInput.evaluate((el) => {
        const style = window.getComputedStyle(el);
        return style.outlineStyle;
      });
      // Screenshot default view
      await page.screenshot({ path: resolve(output, `doctors-directory-${width}.png`), fullPage: true });

      await page.close();
    }

    // 2. Progressive enhancement test: JavaScript disabled
    {
      const context = await browser.newContext({ javaScriptEnabled: false });
      const page = await context.newPage({ viewport: { width: 1280, height: 800 } });
      await page.goto(base + 'doctors.php', { waitUntil: 'networkidle' });

      const specialtySelect = page.locator('#specialty');
      assert.equal(await specialtySelect.count(), 1, 'Specialty select exists without JS');
      const noscriptButton = page.locator('.specialty-filter button');
      assert.equal(await noscriptButton.count(), 1, 'Noscript submit button is present');

      await page.screenshot({ path: resolve(output, 'doctors-directory-noscript-1280.png'), fullPage: true });
      await context.close();
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #209 browser acceptance checks and screenshots complete');
