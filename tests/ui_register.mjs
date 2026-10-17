import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { mkdir, rm } from 'node:fs/promises';
import { resolve } from 'node:path';
import { withTestServer, root } from '../tools/ui/lib.mjs';

import { testPhp } from '../tools/ui/isolation.mjs';
process.env.CLINIC_DB_NAME = 'ie4727db_test';
const fixture = (action) => JSON.parse(testPhp(['tools/ui/register-fixture.php', action, suffix], { encoding: 'utf8' }));

const suffix = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
const capture = async (page, stage, role) => {
  const dir = resolve(root, `UIPROBLEMS/after/issue120-${stage}`);
  await mkdir(dir, { recursive: true });
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
    await page.screenshot({ path: resolve(dir, `${role}-${width}.png`), fullPage: true, animations: 'disabled' });
  }
};
const credentials = (role, variant = '') => ({
  FullName: 'Synthetic Registration',
  User: `ui_${role}_${variant}_${suffix}`,
  Email: `ui_${role}_${variant}_${suffix}@example.local`,
  password: 'Synthetic123',
  confirm_password: 'Synthetic123',
});

await withTestServer(true, async (base) => {
  console.log('Registration fixture before:', fixture('counts'));
  const browser = await chromium.launch({ headless: true });
  try {
    for (const javascriptEnabled of [true, false]) {
      const context = await browser.newContext({ javaScriptEnabled: javascriptEnabled });
      const page = await context.newPage();
      const patternErrors = [];
      page.on('console', (message) => { if (/pattern attribute.*invalid|invalid.*pattern attribute/i.test(message.text())) patternErrors.push(message.text()); });
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      await capture(page, 'before', javascriptEnabled ? 'patient-js' : 'patient-nojs');
      console.log(`loaded untouched form with JS ${javascriptEnabled}`);
      const patientRole = page.locator('input[name="role"][value="patient"]');
      if (!(await patientRole.isChecked())) throw Error(`patient role not checked with JS ${javascriptEnabled}`);
      const submittedRole = await page.locator('#registration-form').evaluate((form) => new FormData(form).get('role'));
      if (submittedRole !== 'patient') throw Error(`untouched form omitted patient role with JS ${javascriptEnabled}`);
      const phone = page.locator('[name="Phone"]');
      const browserPattern = await phone.getAttribute('pattern');
      if (browserPattern !== '[689][0-9]{7}') throw Error(`unexpected rendered phone pattern: ${browserPattern}`);
      if (await page.locator('.phone-prefix').innerText() !== '+65') throw Error('fixed Singapore prefix is missing');
      if (await page.locator('.phone-prefix').getAttribute('contenteditable')) throw Error('Singapore prefix became editable');
      if (await phone.getAttribute('placeholder') !== '81234567') throw Error('phone placeholder suggests a value that fails validation');
      await phone.click();
      const mouseFocusVisible = await phone.evaluate((field) => field.matches(':focus-visible'));
      const mouseRing = await page.locator('.phone-input').evaluate((group) => getComputedStyle(group).outlineStyle);
      if (mouseRing !== (mouseFocusVisible ? 'solid' : 'none')) throw Error('phone focus ring did not follow :focus-visible');
      await page.keyboard.press('Tab');
      await page.keyboard.press('Shift+Tab');
      if (await page.locator('.phone-input').evaluate((group) => getComputedStyle(group).outlineStyle) !== 'solid') throw Error('keyboard focus lost its phone focus ring');
      for (const value of ['61234567', '81234567', '91234567']) {
        await phone.fill(value);
        if (!(await phone.evaluate((field) => field.validity.valid))) throw Error(`browser rejected supported phone: ${value}`);
      }
      for (const value of ['6123abc8', '6123/4567', '71234567', '+65 81234567', '8123456']) {
        await phone.fill(value);
        if (!(await phone.evaluate((field) => field.validity.patternMismatch))) throw Error(`browser missed invalid phone: ${value}`);
      }
      await phone.fill('');
      if (!(await phone.evaluate((field) => field.validity.valueMissing))) throw Error('empty phone did not trigger required validation');
      for (const [name, value] of Object.entries(credentials('patient', javascriptEnabled ? 'js' : 'nojs'))) await page.locator(`[name="${name}"]`).fill(value);
      await page.locator('[name="Gender"]').selectOption('Other');
      await phone.fill('6123abc8');
      await page.locator('button[type="submit"]').click();
      if (!page.url().endsWith('/register.php') || !(await phone.evaluate((field) => field.validity.patternMismatch))) throw Error('invalid phone was submitted');
      const csrf = await page.locator('input[name="_csrf"]').inputValue();
      const crafted = credentials('patient', javascriptEnabled ? 'crafted-js' : 'crafted-nojs');
      const rejected = await page.request.post(base + 'actions/register.php', {
        form: { _csrf: csrf, role: 'patient', ...crafted, Gender: 'Other', Phone: '6123abc8', Allergies: '' },
      });
      if (!rejected.url().endsWith('/register.php') || !(await rejected.text()).includes('Enter 8 digits after +65, starting with 6, 8 or 9.')) {
        throw Error('crafted invalid phone bypassed PHP registration validation');
      }
      await page.locator('[name="Phone"]').fill('81234567');
      if (!javascriptEnabled) {
        await page.locator('[name="password"]').fill('Synthetic123');
        await page.locator('[name="confirm_password"]').fill('Synthetic123');
      }
      if (patternErrors.length) throw Error(`Chromium rejected phone pattern: ${patternErrors.join('; ')}`);
      await page.locator('button[type="submit"]').click();
      try { await page.waitForURL('**/patient/home.php', { timeout: 12000 }); }
      catch (error) { console.log(`JS ${javascriptEnabled} stayed at ${page.url()}: ${(await page.locator('body').innerText()).slice(0, 1000)}`); throw error; }
      await capture(page, 'after', javascriptEnabled ? 'patient-js' : 'patient-nojs');
      console.log(`registered untouched patient with JS ${javascriptEnabled}`);
      await context.close();
    }

    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
    await page.locator('[data-role-switch="doctor"]').click();
    await capture(page, 'before', 'doctor');
    if (!(await page.locator('[name="role"][value="doctor"]').isChecked())) throw Error('doctor switch did not select doctor role');
    if (await page.locator('#patient-fields input, #patient-fields select, #patient-fields textarea').evaluateAll((fields) => fields.some((field) => !field.disabled))) throw Error('inactive patient fields remained enabled');
    for (const [name, value] of Object.entries(credentials('doctor'))) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[name="Specialty"]').selectOption('General Practice');
    for (const [name, value] of Object.entries({ Qualifications: 'MBBS', Languages: 'English', WriteUp: 'Synthetic doctor profile with experience in general practice and patient care.' })) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[name="ProfilePhoto"]').setInputFiles(resolve(root, 'clinic-base/assets/img/Doctor_Consulting.jpg'));
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/doctor/home.php', { timeout: 12000 });
    await capture(page, 'after', 'doctor');
    await context.close();
    const counts = fixture('counts');
    if (counts.database !== 'ie4727db_test' || counts.patient !== 2 || counts.doctor !== 1) throw Error('Registration rows not isolated: ' + JSON.stringify(counts));
    console.log('Registration fixture after:', counts);
  } finally {
    try { await browser.close(); } finally {
      const photo = testPhp(['-r', 'require "clinic-base/lib/db.php"; $row=q_one("SELECT `ImageURL` FROM `doctor` WHERE `User` = :user", ["user" => $argv[1]]); echo $row["ImageURL"] ?? "";', credentials('doctor').User], { encoding: 'utf8' }).trim();
      fixture('cleanup');
      if (/^assets\/uploads\/doctors\/doctor-[a-f0-9]{32}\.(jpg|png|webp)$/.test(photo)) await rm(resolve(root, 'clinic-base', photo), { force: true });
    }
    const counts = fixture('counts');
    if (counts.patient !== 0 || counts.doctor !== 0) throw Error('Own registration fixtures remain');
    console.log('Registration fixture cleaned:', counts);
  }
}, 'clinic-base');

console.log('PASS: untouched patient role and registration with JS on/off; doctor switch registration');
