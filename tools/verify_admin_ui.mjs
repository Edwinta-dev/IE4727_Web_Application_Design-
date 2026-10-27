import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('./ui/node_modules/playwright-core');

const BASE_URL = 'http://localhost:8000/ie4727_web_application_design-/clinic-base/';

async function checkAdmin() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    console.log('Logging in as Admin on clinic-base...');
    await page.goto(BASE_URL + 'index.php#login-card', { waitUntil: 'networkidle' });
    await page.fill('input[name="username_or_email"]', 'admin');
    await page.fill('input[name="password"]', 'Password123');
    await page.click('button:has-text("Sign in")');
    await page.waitForLoadState('networkidle');

    console.log('Current URL after admin login:', page.url());
    const flashMessage = await page.locator('.alert, .flash, .error-summary, .status-banner').allInnerTexts();
    console.log('Flash messages on page:', flashMessage);
    console.log('Admin Console Title:', await page.title());

    // Check stats cards
    const statCards = await page.locator('.stat-card, .stats-strip h2').allInnerTexts();
    console.log('Admin Console Stats Headers:', statCards);

    // Check Outbox
    await page.goto(BASE_URL + 'admin/outbox.php', { waitUntil: 'networkidle' });
    console.log('Outbox Title:', await page.title());
    const rowCount = await page.locator('.admin-outbox table tbody tr').count();
    console.log('Outbox message rows in UI:', rowCount);

    await browser.close();
}

checkAdmin().catch(console.error);
