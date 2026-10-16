import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { withServer, visit } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

await withServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      try {
        await visit(page, 'index.php', base);
        const measure = () => page.evaluate(() => {
          const track = document.querySelector('.specialty-strip');
          const cards = [...track.querySelectorAll('.specialty-item')];
          const rect = element => {
            const box = element.getBoundingClientRect();
            return { left: box.left, right: box.right, top: box.top, bottom: box.bottom,
              width: box.width, height: box.height };
          };
          return {
            track: rect(track), overflow: track.scrollWidth - track.clientWidth,
            scroll: track.scrollLeft,
            cards: cards.map(card => ({ ...rect(card), textSize: parseFloat(getComputedStyle(card.querySelector('h3')).fontSize) })),
            arrowsHidden: [...document.querySelectorAll('.services .doctor-carousel-arrow')].every(arrow => arrow.hidden),
          };
        });
        const normal = await measure();
        assert.equal(normal.cards.length, 5);
        assert.ok(normal.cards.every(card => card.textSize >= 14 && card.width >= 44 && card.height >= 44));
        if (width === 1280) {
          assert.ok(normal.overflow <= 1, 'All specialties fit without scrolling');
          assert.ok(normal.arrowsHidden, 'No arrows when the row fits');
          assert.ok(normal.cards.every(card => card.left >= normal.track.left && card.right <= normal.track.right));
          assert.equal(new Set(normal.cards.map(card => card.top)).size, 1);
        } else {
          assert.ok(normal.overflow > 1, 'Narrow row remains scrollable');
          assert.equal(normal.arrowsHidden, false);
          await page.getByRole('button', { name: 'Next specialties' }).click();
          await page.waitForTimeout(500);
          assert.ok((await measure()).scroll > 0, 'Narrow carousel arrow moves the row');
        }
        for (const index of width === 1280 ? [0, 1, 2, 3, 4] : [0]) {
          await page.locator('.specialty-item').nth(index).hover();
          await page.waitForTimeout(220);
          const hover = await measure();
          const card = hover.cards[index];
          assert.ok(card.left >= hover.track.left && card.right <= hover.track.right,
            'Hovered card stays inside the horizontal track edges');
          assert.ok(card.top >= hover.track.top && card.bottom <= hover.track.bottom,
            'Hovered card stays inside the vertical track edges');
          if (index > 0) assert.ok(card.left >= hover.cards[index - 1].right, 'No overlap with previous card');
          if (index < 4) assert.ok(card.right <= hover.cards[index + 1].left, 'No overlap with next card');
        }
        if (width === 1280) {
          await page.setViewportSize({ width: 390, height: 844 });
          const resized = await measure();
          assert.ok(resized.overflow > 1 && !resized.arrowsHidden, 'Resize restores mobile carousel arrows');
        }
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
});
console.log('OK: #172 five desktop specialties fit with hidden arrows and hover room; mobile paging remains usable');
