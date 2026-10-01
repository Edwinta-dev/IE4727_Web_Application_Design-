import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withServer } from '../tools/ui/lib.mjs';

const suffix = `${Date.now()}_${Math.random().toString(16).slice(2)}`;
const credentials = (role, variant = '') => ({
  FullName: 'Synthetic Registration',
  User: `ui_${role}_${variant}_${suffix}`,
  Email: `ui_${role}_${variant}_${suffix}@example.local`,
  password: 'Synthetic123',
  confirm_password: 'Synthetic123',
});

await withServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const javascriptEnabled of [true, false]) {
      const context = await browser.newContext({ javaScriptEnabled: javascriptEnabled });
      const page = await context.newPage();
      await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
      console.log(`loaded untouched form with JS ${javascriptEnabled}`);
      const patientRole = page.locator('input[name="role"][value="patient"]');
      if (!(await patientRole.isChecked())) throw Error(`patient role not checked with JS ${javascriptEnabled}`);
      const submittedRole = await page.locator('#registration-form').evaluate((form) => new FormData(form).get('role'));
      if (submittedRole !== 'patient') throw Error(`untouched form omitted patient role with JS ${javascriptEnabled}`);
      for (const [name, value] of Object.entries(credentials('patient', javascriptEnabled ? 'js' : 'nojs'))) await page.locator(`[name="${name}"]`).fill(value);
      await page.locator('[name="Gender"]').selectOption('Other');
      await page.locator('[name="Phone"]').fill('+65 61234567');
      await page.locator('button[type="submit"]').click();
      try { await page.waitForURL('**/patient/home.php', { timeout: 12000 }); }
      catch (error) { console.log(`JS ${javascriptEnabled} stayed at ${page.url()}: ${(await page.locator('body').innerText()).slice(0, 1000)}`); throw error; }
      console.log(`registered untouched patient with JS ${javascriptEnabled}`);
      await context.close();
    }

    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(base + 'register.php', { waitUntil: 'networkidle' });
    await page.locator('[data-role-switch="doctor"]').click();
    if (!(await page.locator('[name="role"][value="doctor"]').isChecked())) throw Error('doctor switch did not select doctor role');
    if (await page.locator('#patient-fields input, #patient-fields select, #patient-fields textarea').evaluateAll((fields) => fields.some((field) => !field.disabled))) throw Error('inactive patient fields remained enabled');
    for (const [name, value] of Object.entries(credentials('doctor'))) await page.locator(`[name="${name}"]`).fill(value);
    for (const [name, value] of Object.entries({ Specialty: 'General Practice', Qualifications: 'MBBS', Languages: 'English', WriteUp: 'Synthetic doctor profile.' })) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/doctor/home.php', { timeout: 12000 });
    await context.close();
  } finally {
    await browser.close();
  }
}, 'clinic-base');

console.log('PASS: untouched patient role and registration with JS on/off; doctor switch registration');
