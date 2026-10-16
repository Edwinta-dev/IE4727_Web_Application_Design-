import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { withTestServer, prepareStaticAssets } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';

const pages = await readFile('docs/PAGES.md', 'utf8');
assert.equal((pages.match(/\| `[^`]+\.php` \| BUILT \|/g) ?? []).length, 11, 'page budget remains eleven');

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
      try {
        await prepareStaticAssets(page, base);
        await page.goto(base + 'index.php');
        const layout = await page.evaluate(async () => {
          const about = document.querySelector('#about');
          const services = document.querySelector('.services');
          const copy = about?.querySelector('.about-clinic-copy');
          const image = about?.querySelector('img');
          if (image) await image.decode();
          const aboutRect = about?.getBoundingClientRect();
          const copyRect = copy?.getBoundingClientRect();
          const imageRect = image?.getBoundingClientRect();
          return {
            followsServices: services?.nextElementSibling === about,
            aboutId: about?.id,
            imageAlt: image?.alt,
            imageLoaded: image?.naturalWidth > 0,
            overflow: document.documentElement.scrollWidth - innerWidth,
            aboutRight: aboutRect?.right,
            copyRight: copyRect?.right,
            copyBottom: copyRect?.bottom,
            imageLeft: imageRect?.left,
            imageTop: imageRect?.top,
          };
        });
        assert.equal(layout.followsServices, true, 'About Us immediately follows Specialties');
        assert.equal(layout.aboutId, 'about');
        assert.match(layout.imageAlt, /clinician.*patient.*consultation room/i);
        assert.equal(layout.imageLoaded, true);
        assert.ok(layout.overflow <= 0, `${width}px home has no horizontal overflow`);
        assert.ok(layout.aboutRight <= width + 1, `${width}px about section fits viewport`);
        if (width === 1280) assert.ok(layout.copyRight < layout.imageLeft, 'desktop image sits beside text');
        else assert.ok(layout.copyBottom < layout.imageTop, 'mobile image sits below text');

        await page.goto(base + 'doctors.php');
        if (width === 390) await page.locator('.nav-toggle').click();
        const link = page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'About Us' });
        assert.equal(await link.getAttribute('href'), '/clinic-base/index.php#about');
        await link.click();
        await page.waitForURL('**/index.php#about');
        assert.equal(await page.locator('#about').isVisible(), true, 'nav reaches About Us from another page');
        await page.waitForLoadState('networkidle');
      } finally {
        await page.close();
      }
    }
    console.log('OK: #170 About Us order, layout, image, page count and cross-page navigation at 1280/390');
  } finally {
    await browser.close();
  }
});
