import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('./ui/node_modules/playwright-core');

const BASE_URL_PLUS = 'http://localhost:8000/ie4727_web_application_design-/clinic-plus/';

async function checkPlus() {
    console.log('Testing clinic-plus UI in browser...');
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    // 1. Check htmx partial swaps on booking
    console.log('1. Opening clinic-plus/screens/book.php?doctor=1 ...');
    await page.goto(BASE_URL_PLUS + 'screens/book.php?doctor=1', { waitUntil: 'networkidle' });
    console.log('Booking title:', await page.title());

    const hasHtmx = await page.locator('script[src*="htmx"]').count();
    console.log('htmx script present in DOM:', hasHtmx > 0);

    const dayTabs = page.locator('.day-tabs .day-tab');
    const dayCount = await dayTabs.count();
    console.log(`Day tabs count: ${dayCount}`);

    // Click on 2nd day tab and observe partial swap in #schedule-panel
    if (dayCount > 1) {
        const initialText = await page.locator('#schedule-panel').innerText();
        console.log('Initial panel text (first 50 chars):', initialText.slice(0, 50).replace(/\n/g, ' '));
        
        await dayTabs.nth(1).click();
        await page.waitForTimeout(1000); // Wait for htmx request to fulfill
        
        const updatedText = await page.locator('#schedule-panel').innerText();
        console.log('Updated panel text after htmx swap (first 50 chars):', updatedText.slice(0, 50).replace(/\n/g, ' '));
        console.log('htmx partial swap verified:', initialText !== updatedText);
    }

    // 2. Doctor Login and Live Board in clinic-plus
    console.log('\n2. Logging in as Doctor Dr. John Smith in clinic-plus...');
    await page.goto(BASE_URL_PLUS + 'index.php#login-card', { waitUntil: 'networkidle' });
    await page.fill('input[name="username_or_email"]', 'drsmith');
    await page.fill('input[name="password"]', 'Password123');
    await page.click('button:has-text("Sign in")');
    await page.waitForLoadState('networkidle');
    console.log('Doctor logged in URL:', page.url());

    console.log('Opening clinic-plus/doctor/board.php (Live Clinic Board)...');
    await page.goto(BASE_URL_PLUS + 'doctor/board.php', { waitUntil: 'domcontentloaded' });
    console.log('Live Board Title:', await page.title());

    const statusText = await page.locator('#board-status').innerText();
    console.log('Live updates status indicator in UI:', statusText);

    const hasEventSource = await page.evaluate(() => typeof window.EventSource !== 'undefined');
    console.log('Browser supports EventSource:', hasEventSource);

    await browser.close();
    console.log('\nclinic-plus UI verification finished.');
}

checkPlus().catch(console.error);
