import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('./ui/node_modules/playwright-core');
import { readdir, rename } from 'node:fs/promises';
import { join } from 'node:path';

const BASE_URL = 'http://localhost:8000/ie4727_web_application_design-/clinic-base/';
const RECORDINGS_DIR = join(process.cwd(), 'recordings');

const delay = (ms) => new Promise(res => setTimeout(res, ms));

async function runPatientStory() {
    console.log('--- Starting Patient User Story Recording ---');
    const browser = await chromium.launch({
        headless: true
    });

    const context = await browser.newContext({
        recordVideo: {
            dir: RECORDINGS_DIR,
            size: { width: 1280, height: 800 }
        },
        viewport: { width: 1280, height: 800 }
    });

    const page = await context.newPage();
    page.on('dialog', async dialog => {
        console.log(`[Dialog] ${dialog.type()}: "${dialog.message()}" -> Accepting`);
        await dialog.accept();
    });

    try {
        // Step 1: Visit Home Page
        console.log('1. Navigating to Home Page...');
        await page.goto(BASE_URL + 'index.php', { waitUntil: 'networkidle' });
        await delay(2000);

        // Scroll to explore home page sections
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await delay(1500);
        await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
        await delay(1500);
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        await delay(1000);

        // Step 2: Browse Doctors
        console.log('2. Navigating to Find a Doctor...');
        await page.click('nav a[href*="screens/doctors.php"]');
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Filter by Dental
        console.log('   Filtering by Dental...');
        const dentalLink = page.locator('a[href*="specialty=Dental"]').first();
        if (await dentalLink.count() > 0) {
            await dentalLink.click();
            await page.waitForLoadState('networkidle');
            await delay(1500);
        }

        // Filter by General Practice
        console.log('   Filtering by General Practice...');
        const gpLink = page.locator('a[href*="specialty=General"]').first();
        if (await gpLink.count() > 0) {
            await gpLink.click();
            await page.waitForLoadState('networkidle');
            await delay(1500);
        }

        // Step 3: Inspect Doctor Profile (Dr. John Smith)
        console.log('3. Viewing Dr. John Smith Profile...');
        const doctorProfileLink = page.locator('a[href*="screens/doctor.php?id=1"]').first();
        await doctorProfileLink.click();
        await page.waitForLoadState('networkidle');
        await delay(2500);

        // Step 4: Click Book Appointment
        console.log('4. Navigating to Booking Page...');
        const bookButton = page.locator('a[href*="screens/book.php"]').first();
        await bookButton.click();
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Step 5: Log in as Patient Alex Tan
        console.log('5. Logging in as Patient Alex Tan...');
        await page.goto(BASE_URL + 'index.php#login-card', { waitUntil: 'networkidle' });
        await delay(1000);
        await page.fill('input[name="username_or_email"]', 'alextan');
        await delay(500);
        await page.fill('input[name="password"]', 'Password123');
        await delay(800);
        await page.click('button:has-text("Sign in")');
        await page.waitForLoadState('networkidle');
        await delay(2000);

        // Step 6: Go to Book Appointment as Logged-in Patient
        console.log('6. Navigating to Booking form for Dr. John Smith...');
        await page.goto(BASE_URL + 'screens/book.php?doctor=1', { waitUntil: 'networkidle' });
        await delay(2000);

        // Check if there are available day tabs
        const dayTabs = page.locator('.day-tabs .day-tab');
        const dayCount = await dayTabs.count();
        console.log(`   Found ${dayCount} date tabs`);
        
        // Find a day with available slots
        let slotFound = false;
        for (let i = 0; i < Math.min(dayCount, 7); i++) {
            await dayTabs.nth(i).click();
            await page.waitForLoadState('networkidle');
            await delay(1000);

            const freeSlots = page.locator('.slot.free input[type="radio"]');
            const freeCount = await freeSlots.count();
            if (freeCount > 0) {
                console.log(`   Found ${freeCount} free slots on day ${i + 1}. Selecting first free slot...`);
                await freeSlots.first().check();
                await delay(1000);

                const reasonInput = page.locator('input[name="reason"]');
                if (await reasonInput.count() > 0) {
                    await reasonInput.fill('Regular health checkup and consultation');
                    await delay(800);
                }

                console.log('   Confirming booking...');
                await page.click('button[type="submit"]:has-text("Confirm booking")');
                await page.waitForLoadState('networkidle');
                slotFound = true;
                break;
            }
        }

        if (!slotFound) {
            console.log('   Notice: No free slots in the immediate window, proceeding to dashboard...');
            await page.goto(BASE_URL + 'screens/patient/home.php', { waitUntil: 'networkidle' });
        }
        await delay(2500);

        // Step 7: View Patient Dashboard
        console.log('7. Viewing Patient Dashboard (Upcoming Appointments)...');
        await page.evaluate(() => window.scrollBy({ top: 250, behavior: 'smooth' }));
        await delay(2000);

        // Step 8: Reschedule Workflow
        const rescheduleButton = page.locator('a.button:has-text("Reschedule")').first();
        if (await rescheduleButton.count() > 0) {
            console.log('8. Exercising Reschedule Workflow...');
            await rescheduleButton.click();
            await page.waitForLoadState('networkidle');
            await delay(2000);

            // Look for another available slot on another day
            const reschedDayTabs = page.locator('.day-tabs .day-tab');
            const reschedCount = await reschedDayTabs.count();
            for (let i = 1; i < reschedCount; i++) {
                await reschedDayTabs.nth(i).click();
                await page.waitForLoadState('networkidle');
                await delay(1000);
                const altFreeSlots = page.locator('.slot.free input[type="radio"]');
                if (await altFreeSlots.count() > 0) {
                    await altFreeSlots.first().check();
                    await delay(1000);
                    console.log('   Confirming reschedule...');
                    await page.click('button[type="submit"]:has-text("Confirm reschedule")');
                    await page.waitForLoadState('networkidle');
                    break;
                }
            }
            await delay(2500);
        }

        // Step 9: Cancellation Workflow
        const cancelButton = page.locator('button.appointment-cancel').first();
        if (await cancelButton.count() > 0) {
            console.log('9. Exercising Cancellation Workflow...');
            await cancelButton.click();
            await page.waitForLoadState('networkidle');
            await delay(2500);
        }

        // Step 10: Sign Out
        console.log('10. Signing out...');
        const signOutButton = page.locator('form[action*="logout.php"] button, a:has-text("Sign out"), button:has-text("Sign out")').first();
        if (await signOutButton.count() > 0) {
            await signOutButton.click();
            await page.waitForLoadState('networkidle');
        }
        await delay(1500);

        console.log('--- Patient User Story Completed Successfully ---');
    } catch (err) {
        console.error('Error during patient user story:', err);
    } finally {
        await page.close();
        await context.close();
        await browser.close();
    }
}

async function renameLatestRecording(targetName) {
    const files = await readdir(RECORDINGS_DIR);
    const webmFiles = files.filter(f => f.endsWith('.webm') && !f.startsWith('patient-') && !f.startsWith('doctor-'));
    if (webmFiles.length > 0) {
        const latest = webmFiles.sort().pop();
        await rename(join(RECORDINGS_DIR, latest), join(RECORDINGS_DIR, targetName));
        console.log(`Saved recording to: ${join(RECORDINGS_DIR, targetName)}`);
    }
}

(async () => {
    await runPatientStory();
    await renameLatestRecording('patient-user-story.webm');
})();
