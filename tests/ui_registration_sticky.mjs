import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withServer, visit } from '../tools/ui/lib.mjs';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

const bounds = (page, selector) => page.locator(selector).boundingBox();
const assert = (condition, message) => { if (!condition) throw Error(message); };

await withServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    const shots = resolve('UIPROBLEMS/after/iter7/issue-188-registration-sticky');
    await mkdir(shots, { recursive: true });
    for (const width of [1280, 390]) {
      const baseline = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      await visit(baseline, 'register.php', base);
      // Reconstruct HEAD: this is the only registration rule added in this issue.
      await baseline.addStyleTag({ content: '.registration-support { position: static !important; max-height: none !important; overflow-y: visible !important; }' });
      await baseline.screenshot({ path: resolve(shots, `before-${width}.png`), fullPage: true });
      await baseline.evaluate(() => window.scrollTo(0, 550));
      await baseline.screenshot({ path: resolve(shots, `before-${width}-scrolled.png`) });
      await baseline.close();
    }
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    await visit(page, 'register.php', base);
    await page.screenshot({ path: resolve(shots, 'after-1280.png'), fullPage: true });
    const support = page.locator('.registration-support');
    assert(await support.evaluate((node) => getComputedStyle(node).position) === 'sticky', 'desktop support column is not sticky');
    const before = await bounds(page, '.registration-support');
    await page.evaluate(() => window.scrollTo(0, 550));
    await page.screenshot({ path: resolve(shots, 'after-1280-scrolled.png') });
    const after = await bounds(page, '.registration-support');
    assert(after.y >= 80 && after.y <= 110, `desktop support did not pin below navigation: ${after.y}`);
    assert(after.y < before.y && after.y > before.y - 550, 'desktop support moved with the form');
    assert(await support.locator('section').count() === 2, 'registration guidance was lost');

    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    const supportEnd = await bounds(page, '.registration-support');
    const footer = await bounds(page, '.site-footer');
    assert(supportEnd.y + supportEnd.height <= footer.y + 1, 'sticky support overlaps the footer');

    await page.evaluate(() => window.scrollTo(0, 0));
    await page.locator('[data-role-switch="doctor"]').click();
    await page.evaluate(() => window.scrollTo(0, 650));
    const doctorSupport = await bounds(page, '.registration-support');
    assert(doctorSupport.y >= 80 && doctorSupport.y <= 110, 'doctor form does not keep guidance pinned');
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    const doctorEnd = await bounds(page, '.registration-support');
    const doctorFooter = await bounds(page, '.site-footer');
    assert(doctorEnd.y + doctorEnd.height <= doctorFooter.y + 1, 'doctor form guidance overlaps footer');

    await page.setViewportSize({ width: 1280, height: 320 });
    const shortViewport = await support.evaluate((node) => ({
      height: node.clientHeight,
      contentHeight: node.scrollHeight,
      overflow: getComputedStyle(node).overflowY,
    }));
    assert(shortViewport.overflow === 'auto' && shortViewport.contentHeight > shortViewport.height,
      'short desktop viewport cannot scroll the guidance internally');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: resolve(shots, 'after-390.png'), fullPage: true });
    assert(await support.evaluate((node) => getComputedStyle(node).position) === 'static', 'mobile guidance is not in normal flow');
    const form = await bounds(page, '#registration-form');
    const mobileSupport = await bounds(page, '.registration-support');
    assert(mobileSupport.y >= form.y + form.height, 'mobile guidance is not stacked after the form');
    await page.evaluate(() => window.scrollTo(0, 500));
    await page.screenshot({ path: resolve(shots, 'after-390-scrolled.png') });
    const mobileAfter = await bounds(page, '.registration-support');
    assert(Math.abs(mobileAfter.y - (mobileSupport.y - 500)) <= 1, 'mobile guidance unexpectedly sticks');
    await page.close();
    console.log('OK: desktop registration guidance stays visible, clears the footer, and stacks normally on mobile');
  } finally {
    await browser.close();
  }
});
