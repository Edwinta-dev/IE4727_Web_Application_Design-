// Adaptation of UIPROBLEMS/Open/iter5/shoot_iter5.cjs for the owned test server.
// Acceptance: node tools/ui/iter5-gate.test.mjs
import assert from 'node:assert/strict';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit, login, prepareStaticAssets, root } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.MAIL_DELIVERY = 'off';
const beforeDir = resolve(root, 'UIPROBLEMS/Open/iter5');
const output = resolve(root, 'UIPROBLEMS/after/iter5');
const php = (...args) => testPhp(args, { cwd: root, encoding: 'utf8' });
const reset = () => php('tools/db_reset.php', '--test');
const records = [];
const captures = [];
await mkdir(resolve(output, 'comparisons'), { recursive: true });
await mkdir(resolve(output, 'matrix'), { recursive: true });

const geometry = (page, selector) => page.locator(selector).evaluateAll(nodes => {
  const rect = el => {
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y + scrollY, width: r.width, height: r.height };
  };
  return nodes.map(el => {
    const img = el.querySelector('img');
    return { box: rect(el), width: el.offsetWidth, height: el.offsetHeight,
      image: rect(img), fit: getComputedStyle(img).objectFit,
      position: getComputedStyle(img).objectPosition, src: img.currentSrc,
      text: [...el.querySelectorAll('h3,p')].map(text => ({ width: text.offsetWidth, height: text.offsetHeight, content: text.textContent })) };
  });
});

async function hoverCheck(page, selector, index, motion) {
  const target = page.locator(selector).nth(index);
  await page.mouse.move(0, 0);
  await target.scrollIntoViewIfNeeded();
  await page.waitForTimeout(600);
  const normal = await geometry(page, selector);
  await target.hover();
  await page.waitForTimeout(250);
  const hover = await geometry(page, selector);
  const a = normal[index], b = hover[index];
  assert.ok(b.image.y <= a.image.y + 0.1, 'No downward image jump');
  assert.ok(Math.abs(b.image.width / b.image.height - a.image.width / a.image.height) < 0.001, 'No image re-crop');
  assert.equal(b.fit, a.fit);
  assert.equal(b.position, a.position);
  assert.equal(b.src, a.src);
  for (let i = 0; i < normal.length; i++) {
    assert.equal(hover[i].width, normal[i].width, 'No layout width change');
    assert.equal(hover[i].height, normal[i].height, 'No layout height change');
    assert.deepEqual(hover[i].text, normal[i].text, 'No text re-wrap');
    if (i !== index || motion === 'reduce') assert.deepEqual(hover[i], normal[i], 'Neighbour/reduced-motion geometry unchanged');
  }
  records.push({ selector, index, motion, viewport: page.viewportSize(), normal, hover });
}

async function shot(page, name, { el, full = false } = {}) {
  await page.waitForTimeout(350);
  await page.evaluate(async () => {
    for (const img of document.images) img.loading = 'eager';
    await Promise.all([...document.images].map(img => img.decode()));
  });
  const path = resolve(output, name);
  if (el) await page.locator(el).first().screenshot({ path });
  else await page.screenshot({ path, fullPage: full });
  captures.push({ name, url: page.url(), viewport: page.viewportSize() });
  console.log('saved', name);
}

async function capture(browser, base) {
  for (const width of [1280, 1024]) {
    const page = await browser.newPage({ viewport: { width, height: 800 } });
    try {
      await visit(page, 'index.php', base);
      await shot(page, `01-featured-doctors-${width}.png`, { el: '.featured-doctors' });
      if (width === 1280) {
        await hoverCheck(page, '.doctor-tile', 3, 'no-preference');
        await shot(page, '01-featured-doctors-hover-1280.png', { el: '.featured-doctors' });
        await page.mouse.move(0, 0);
        await shot(page, '02-specialty-strip-1280.png', { el: '.services' });
        await hoverCheck(page, '.specialty-item', 1, 'no-preference');
        await shot(page, '02-specialty-strip-hover-1280.png', { el: '.services' });
      }
    } finally { await page.close(); }
  }
  php('tools/ui/featured-doctors-fixture.php', '12');
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  try {
    await visit(page, 'index.php', base);
    assert.equal(await page.locator('.doctor-tile').count(), 12);
    await shot(page, '01-featured-doctors-12-items-1280.png', { el: '.featured-doctors' });
    reset();
    php('tools/ui/specialty-strip-fixture.php', '10');
    await visit(page, 'index.php', base);
    await shot(page, '03-specialty-strip-10-items-1280.png', { el: '.services' });
    reset();
    await visit(page, 'doctors.php', base);
    await page.locator('select[name=specialty]').evaluate(select => select.size = 8);
    await shot(page, '05-doctors-specialty-filter-1280.png', { full: true });
    await visit(page, 'register.php', base);
    await page.locator('.registration-role-arrow').last().click();
    await shot(page, '05-register-doctor-specialty-text-1280.png', { full: true });
    await login(page, 'patient', base);
    await shot(page, '10-patient-dashboard-1280.png', { full: true });
    const fixture = JSON.parse(php('-r', `require 'clinic-base/models/slots.php';
      for ($offset = 1; $offset < 7; $offset++) {
        $date = date('Y-m-d', strtotime("+$offset days"));
        if (count(free_slots(1, $date)) >= 8) { echo json_encode(['date'=>$date]); break; }
      }`));
    const booking = `book.php?doctor=1&date=${fixture.date}&from=00%3A00&to=23%3A59`;
    await visit(page, booking, base);
    await shot(page, '06-book-slots-closed-1280.png', { full: true });
    // #139 replaced expanding per-slot details with one shared radio form.
    // Preserve the old filename; select both old positions in succession.
    await page.locator('.slot-choice').nth(1).check();
    await page.locator('.slot-choice').nth(7).check();
    assert.equal(await page.locator('.slot-choice:checked').count(), 1);
    await shot(page, '06-book-slots-two-open-1280.png', { full: true });
    await visit(page, 'doctors.php', base);
    await shot(page, '11-doctors-book-unavailable-1280.png', { full: true });
    const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
    try {
      await mobile.context().addCookies(await page.context().cookies());
      await visit(mobile, booking, base);
      await shot(mobile, '06-book-slots-390.png', { full: true });
      await visit(mobile, 'index.php', base);
      // Original mobile evidence is public, without a member session.
      await mobile.context().clearCookies();
      await visit(mobile, 'index.php', base);
      await shot(mobile, '12-phone-header-home-390.png');
      await visit(mobile, 'register.php', base);
      await shot(mobile, '12-phone-register-390.png', { full: true });
    } finally { await mobile.close(); }
    await page.context().clearCookies();
    await login(page, 'doctor', base);
    await shot(page, '10-doctor-dayboard-1280.png', { full: true });
    await page.goto(base + `doctor/schedule.php?date=${fixture.date}`, { waitUntil: 'domcontentloaded' });
    assert.ok(page.url().includes('/doctor/schedule.php?'));
    await shot(page, '13-doctor-schedule-1280.png', { full: true });
    await page.context().clearCookies();
    await prepareStaticAssets(page, base);
    const guard = await page.goto(base + 'admin/outbox.php');
    assert.equal(guard.status(), 200);
    assert.ok(new URL(page.url()).pathname.endsWith('/index.php'));
    assert.equal(await page.locator('.member-login .flash[role="alert"]').innerText(), 'Please sign in as an admin to continue.');
    await shot(page, '09-outbox-guard-redirect-no-message-1280.png');
    await login(page, 'admin', base);
    await shot(page, '09-admin-landing-1280.png', { full: true });
    await page.goto(base + 'admin/outbox.php', { waitUntil: 'domcontentloaded' });
    assert.ok(page.url().endsWith('/admin/outbox.php'));
    await shot(page, '07-outbox-list-1280.png', { full: true });
  } finally { await page.close(); }
  // Use installed Google Chrome, rather than relabelling bundled Chromium.
  const chrome = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await chrome.newPage({ viewport: { width: 1280, height: 800 } });
    await visit(page, 'index.php', base);
    // Dentistry was removed from the public strip by #138, so Paediatrics
    // is now the fourth item rather than the fifth in the owner's JPG.
    await hoverCheck(page, '.specialty-item', 3, 'no-preference');
    await shot(page, '02-specialty-strip-hover-realchrome.jpg', { el: '.services' });
    records.push({ browser: 'installed Google Chrome', version: chrome.version(), headless: true });
  } finally { await chrome.close(); }
}

async function matrix(browser, base) {
  for (const count of [3, 7, 12]) {
    reset();
    const fixture = JSON.parse(php('tools/ui/booking-urls-fixture.php', String(count)));
    for (const width of [1280, 390]) {
      for (const motion of ['no-preference', 'reduce']) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 800 }, reducedMotion: motion });
        try {
          await visit(page, 'index.php', base);
          assert.equal(await page.locator('.doctor-tile').count(), count);
          // Canonical service list has five entries; raw legacy rows never
          // manufacture extra clinic services just to reach a requested count.
          const specialties = await page.locator('.specialty-item').count();
          assert.equal(specialties, Math.min(count, 5));
          await hoverCheck(page, '.doctor-tile', 0, motion);
          await hoverCheck(page, '.specialty-item', 0, motion);
          await page.screenshot({ path: resolve(output, 'matrix', `${count}-${width}-${motion}-home.png`), fullPage: true });
          await visit(page, 'doctors.php', base);
          assert.equal(await page.locator('tbody tr').count(), count);
          await visit(page, `book.php?doctor=${fixture.doctor}&date=${fixture.date}`, base);
          assert.equal(await page.locator('.schedule-grid > .slot').count(), count);
          assert.equal(await page.locator('#doctor option:not([value=""])').count(), count);
          const heights = () => page.locator('.schedule-grid > .slot').evaluateAll(nodes => nodes.map(el => el.offsetHeight));
          const closed = await heights();
          await page.locator('.slot-choice').first().check();
          await page.locator('.slot-choice').last().check();
          assert.equal(await page.locator('.slot-choice:checked').count(), 1);
          assert.deepEqual(await heights(), closed);
          assert.equal(await page.locator('.booking-confirmation').count(), 1);
          assert.equal(await page.locator('html').evaluate(el => el.scrollWidth), width);
          await page.screenshot({ path: resolve(output, 'matrix', `${count}-${width}-${motion}-slots.png`), fullPage: true });
          records.push({ count, width, motion, doctors: count, rawSpecialties: count, renderedSpecialties: specialties, slots: count, heights: closed });
        } finally { await page.close(); }
      }
    }
  }
}

async function comparisons(browser, names) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
  const pairs = [];
  try {
    for (const name of names) {
      const mime = name.endsWith('.jpg') ? 'image/jpeg' : 'image/png';
      const before = (await readFile(resolve(beforeDir, name))).toString('base64');
      const after = (await readFile(resolve(output, name))).toString('base64');
      const pair = `<article><h2>${name}</h2><div><figure><figcaption>BEFORE</figcaption><img src="data:${mime};base64,${before}"></figure><figure><figcaption>AFTER</figcaption><img src="data:${mime};base64,${after}"></figure></div></article>`;
      pairs.push(pair);
      await page.setContent(`<style>body{margin:16px;font:16px Arial;background:#fff}h2{font-size:18px}div{display:flex;gap:16px}figure{margin:0;width:608px}img{width:100%;height:auto}</style>${pair}`);
      await page.evaluate(() => Promise.all([...document.images].map(img => img.decode())));
      await page.screenshot({ path: resolve(output, 'comparisons', name.replace(/\.(png|jpg)$/, '.png')), fullPage: true });
    }
    for (let start = 0; start < pairs.length; start += 4) {
      await page.setContent(`<style>body{margin:12px;font:14px Arial;background:white}h2{font-size:15px;margin:4px 0}article{height:235px;border-bottom:1px solid #aaa}div{display:flex;gap:12px}figure{width:620px;margin:0}img{width:100%;height:200px;object-fit:contain;object-position:top}</style>${pairs.slice(start, start + 4).join('')}`);
      await page.evaluate(() => Promise.all([...document.images].map(img => img.decode())));
      await page.screenshot({ path: resolve(output, 'comparisons', `sheet-${start / 4 + 1}.png`), fullPage: true });
    }
  } finally { await page.close(); }
}

try {
  reset();
  const names = (await readdir(beforeDir)).filter(name => /\.(png|jpg)$/.test(name)).sort();
  assert.equal(names.filter(name => name.endsWith('.png')).length, 21, 'Complete original PNG evidence set');
  assert.equal(names.filter(name => name.endsWith('.jpg')).length, 1, 'Original real-Chrome evidence');
  await withTestServer(true, async base => {
    const browser = await chromium.launch({ headless: true });
    try {
      await capture(browser, base);
      assert.deepEqual(captures.map(item => item.name).sort(), names, 'Every original name re-shot, no missing jobs');
      await matrix(browser, base);
      await comparisons(browser, names);
    } finally { await browser.close(); }
  });
  console.log('OK: #155 all 22 original captures, side-by-side comparisons, hover geometry, 3/7/12 DB rows, desktop/mobile and reduced motion; ie4727db_test');
} finally {
  await writeFile(resolve(output, 'measurements.json'), JSON.stringify({ captures, records }, null, 2));
  reset();
}
