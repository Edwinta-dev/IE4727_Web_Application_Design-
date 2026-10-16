import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { root, visit, withTestServer } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const evidence = resolve(root, 'UIPROBLEMS/after/iter7/header-alignment-175');
await mkdir(evidence, { recursive: true });

const paths = {
  guest: 'index.php',
  patient: 'patient/home.php',
  doctor: 'doctor/home.php',
  admin: 'admin/console.php',
};

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    let reproduced = false;
    for (const [role, path] of Object.entries(paths)) {
      for (const width of [1280, 390]) {
        const page = await browser.newPage({ viewport: { width, height: width === 1280 ? 800 : 844 } });
        try {
          await visit(page, path, base);
          const geometry = await page.locator('.site-header').evaluate(header => {
            const center = element => {
              const rect = element.getBoundingClientRect();
              return rect.top + rect.height / 2;
            };
            const brand = header.querySelector('.site-brand');
            const logo = header.querySelector('.clinic-logo');
            const navigation = header.querySelector('.site-nav');
            const links = [...navigation.querySelectorAll('a')];
            const shownLinks = links.filter(link => link.getClientRects().length && getComputedStyle(link).visibility !== 'hidden');
            return {
              brand: center(brand),
              logo: center(logo),
              links: shownLinks.map(center),
              navVisible: getComputedStyle(navigation).display !== 'none',
              overflow: header.scrollWidth > header.clientWidth,
            };
          });
          await page.screenshot({ path: resolve(evidence, `${before ? 'before' : 'after'}-${role}-${width}.png`), fullPage: true });
          assert.equal(geometry.overflow, false, `${role} ${width}px header overflow`);
          assert.ok(Math.abs(geometry.brand - geometry.logo) <= 2, `${role} ${width}px brand and logo centres`);
          if (width === 1280) {
            assert.equal(geometry.navVisible, true, `${role} desktop navigation visible`);
            assert.ok(geometry.links.length >= 4, `${role} desktop links visible`);
            const offsets = geometry.links.map(linkCenter => Math.abs(linkCenter - geometry.brand));
            console.log(`${role} ${width}px nav offsets: ${offsets.map(offset => offset.toFixed(1)).join(', ')}px`);
            if (before) reproduced ||= offsets.some(offset => offset > 3);
            else assert.ok(offsets.every(offset => offset <= 2), `${role} desktop brand and nav centres align`);
          } else {
            assert.equal(geometry.navVisible, false, `${role} mobile menu remains collapsed`);
          }
        } finally {
          await page.close();
        }
      }
    }
    if (before) assert.equal(reproduced, true, 'Current desktop nav offset reproduces');
  } finally {
    await browser.close();
  }
});
console.log(`OK: #175 ${before ? 'offset reproduced' : 'header alignment'} at 1280/390 for all roles`);
