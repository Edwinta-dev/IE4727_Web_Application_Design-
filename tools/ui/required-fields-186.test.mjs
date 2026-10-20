import { chromium } from 'playwright';
import { withTestServer, visit } from './lib.mjs';

function expect(condition, message) {
  if (!condition) throw Error(message);
}

async function checkField(page, selector) {
  const result = await page.locator(selector).evaluate(field => {
    const label = field.id ? document.querySelector(`label[for="${field.id}"]`) : null;
    return {
      required: field.required,
      ariaRequired: field.getAttribute('aria-required'),
      marker: label?.querySelector('.required-mark')?.textContent,
      hidden: label?.querySelector('.required-mark')?.getAttribute('aria-hidden'),
      markerDisplay: label?.querySelector('.required-mark') && getComputedStyle(label.querySelector('.required-mark')).display,
    };
  });
  expect(result.required && result.ariaRequired === 'true', `${selector} needs required and aria-required`);
  expect(result.marker === '*' && result.hidden === 'true', `${selector} needs an aria-hidden marker in its label`);
  expect(result.markerDisplay === 'inline', `${selector} marker must stay on the label line`);
}

await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await visit(page, 'register.php', base);
    expect(await page.locator('#registration-form > .required-note').innerText() === '* required', 'registration note missing');
    for (const name of ['FullName', 'User', 'Email', 'password', 'confirm_password', 'Gender', 'Phone', 'Specialty', 'Qualifications', 'Languages', 'WriteUp']) {
      await checkField(page, `#registration-form [name="${name}"]`);
    }
    expect(!(await page.locator('[name="Allergies"]').getAttribute('required')), 'optional allergies marked required');
    expect(await page.locator('#doctor-fields [name="Specialty"]').isDisabled(), 'inactive doctor fields must be disabled');
    await page.locator('[data-role-switch="doctor"]').click();
    expect(await page.locator('#patient-fields [name="Gender"]').isDisabled(), 'inactive patient fields must be disabled');
    expect(!(await page.locator('#doctor-fields [name="Specialty"]').isDisabled()), 'doctor specialty must be enabled');
    expect(!(await page.locator('#doctor-fields [name="Specialty"]').evaluate(field => field.checkValidity())), 'empty doctor specialty must be invalid');

    await visit(page, 'index.php', base);
    expect(await page.locator('.member-login .required-note').innerText() === '* required', 'login note missing');
    for (const name of ['username_or_email', 'password']) await checkField(page, `.member-login [name="${name}"]`);

    await visit(page, 'book.php', base);
    for (let day = 1; day < await page.locator('.day-tab').count() && !(await page.locator('#booking-post').count()); day++) {
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('.day-tab').nth(day).click()]);
    }
    expect(await page.locator('#booking-post').count() === 1, 'seeded booking window should show bookable slots');
    expect(await page.locator('#booking-post > .required-note').innerText() === '* required', 'booking note missing');
    await checkField(page, '#reason');
    for (const name of ['doctor', 'slot_id']) {
      const radio = page.locator(`[name="${name}"][required]`).first();
      expect(await radio.getAttribute('aria-required') === 'true', `${name} radio needs aria-required`);
    }
    expect(await page.locator('#booking-doctor-label .required-mark[aria-hidden="true"]').count() === 1, 'doctor group marker missing');
    expect(await page.locator('#appointment-time-label .required-mark[aria-hidden="true"]').count() === 1, 'appointment time group marker missing');
    expect(await page.locator('.booking-time-fields input[required]').count() === 0, 'optional time filters should not be required');

    await visit(page, 'doctor/schedule.php', base);
    expect(await page.locator('.schedule-generator .required-note').innerText() === '* required', 'schedule note missing');
    for (const name of ['days', 'start_time', 'end_time', 'slot_length']) await checkField(page, `.schedule-generator [name="${name}"]`);
    expect(await page.locator('.days-to-skip [required]').count() === 0, 'skip days should remain optional');
    await checkField(page, '#schedule-date');
    await page.close();

    const noScript = await browser.newPage({ javaScriptEnabled: false });
    await visit(noScript, 'register.php', base);
    expect(await noScript.locator('#registration-form').evaluate(form => form.noValidate), 'server validation must remain reachable with both role fieldsets required');
    await noScript.locator('[name="role"][value="doctor"]').check();
    await Promise.all([noScript.waitForNavigation({ waitUntil: 'domcontentloaded' }), noScript.locator('#registration-form button[type="submit"]').click()]);
    expect(await noScript.locator('.error-summary').count() === 1, 'server must reject missing doctor fields without JavaScript');
    expect(await noScript.locator('[name="role"][value="doctor"]').isChecked(), 'doctor selection must survive server validation');
    await noScript.close();
  } finally {
    await browser.close();
  }
});
console.log('OK: required markers, accessibility attributes, and role-specific controls');
