import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('./ui/node_modules/playwright-core');
import { readdir, rename } from 'node:fs/promises';
import { join } from 'node:path';

const BASE_URL = 'http://localhost:8000/ie4727_web_application_design-/clinic-base/';
const RECORDINGS_DIR = join(process.cwd(), 'recordings');

const delay = (ms) => new Promise(res => setTimeout(res, ms));

async function runDoctorStory() {
    console.log('--- Starting Doctor User Story Recording ---');
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
        // Step 1: Sign in as Doctor Dr. John Smith
        console.log('1. Navigating to Login Page as Doctor Dr. John Smith...');
        await page.goto(BASE_URL + 'index.php#login-card', { waitUntil: 'networkidle' });
        await delay(1500);

        await page.fill('input[name="username_or_email"]', 'drsmith');
        await delay(600);
        await page.fill('input[name="password"]', 'Password123');
        await delay(800);
        await page.click('button:has-text("Sign in")');
        await page.waitForLoadState('networkidle');
        await delay(2500);

        // Step 2: Doctor Dashboard (Day Board)
        console.log('2. Reviewing Doctor Dashboard (Day Board)...');
        await page.evaluate(() => window.scrollBy({ top: 300, behavior: 'smooth' }));
        await delay(2000);

        // Browse Next Day
        console.log('   Navigating to next schedule day...');
        const nextDayLink = page.locator('a[aria-label="Next day"]').first();
        if (await nextDayLink.count() > 0) {
            await nextDayLink.click();
            await page.waitForLoadState('networkidle');
            await delay(2000);
        }

        // Jump to next appointment
        const jumpLink = page.locator('a:has-text("Jump to next appointment")').first();
        if (await jumpLink.count() > 0) {
            await jumpLink.click();
            await page.waitForLoadState('networkidle');
            await delay(2000);
        }

        // Step 3: Manage Schedule Availability
        console.log('3. Navigating to Schedule Management...');
        await page.goto(BASE_URL + 'screens/doctor/schedule.php', { waitUntil: 'networkidle' });
        await delay(2500);

        // Locate an available slot and block it
        console.log('   Inspecting slot calendar...');
        await page.evaluate(() => window.scrollBy({ top: 250, behavior: 'smooth' }));
        await delay(1500);

        const blockButton = page.locator('form[action*="schedule.php"] button:has-text("Block")').first();
        if (await blockButton.count() > 0) {
            console.log('   Blocking an available slot...');
            await blockButton.click();
            await page.waitForLoadState('networkidle');
            await delay(2000);

            // Reopen the blocked slot
            const reopenButton = page.locator('form[action*="schedule.php"] button:has-text("Reopen")').first();
            if (await reopenButton.count() > 0) {
                console.log('   Reopening blocked slot back to available...');
                await reopenButton.click();
                await page.waitForLoadState('networkidle');
                await delay(2000);
            }
        }

        // Step 4: Clinical Visit Consultation
        console.log('4. Navigating to Patient Visit Record...');
        // Use an editable appointment for Dr. Smith (appt=23)
        await page.goto(BASE_URL + 'screens/doctor/visit.php?appt=23', { waitUntil: 'networkidle' });
        await delay(2500);

        // Inspect Patient Profile & Allergies
        console.log('   Reviewing patient profile and medical alerts...');
        await page.evaluate(() => window.scrollBy({ top: 250, behavior: 'smooth' }));
        await delay(1500);

        // Enter Consultation Notes
        console.log('   Entering clinical consultation details...');
        const diagInput = page.locator('input[name="Diagnosis"], textarea[name="Diagnosis"]').first();
        if (await diagInput.count() > 0) {
            await diagInput.fill('Acute viral upper respiratory tract infection (rhinopharyngitis)');
            await delay(800);
        }

        const rxInput = page.locator('input[name="Prescription"], textarea[name="Prescription"]').first();
        if (await rxInput.count() > 0) {
            await rxInput.fill('Paracetamol 500mg TDS PRN, Cetirizine 10mg ON, Vitamin C 500mg daily');
            await delay(800);
        }

        const txInput = page.locator('input[name="Treatment"], textarea[name="Treatment"]').first();
        if (await txInput.count() > 0) {
            await txInput.fill('Rest, generous oral fluids, warm saline gargles');
            await delay(800);
        }

        const followUpCheck = page.locator('input[name="FollowUp"]').first();
        if (await followUpCheck.count() > 0) {
            await followUpCheck.check();
            await delay(600);
        }

        const remarksInput = page.locator('textarea[name="Remarks"]').first();
        if (await remarksInput.count() > 0) {
            await remarksInput.fill('Patient instructed on danger signs. Advised to return if fever persists > 72h or dyspnoea develops.');
            await delay(800);
        }

        // Save visit details
        console.log('   Saving clinical consultation notes...');
        await page.click('button[type="submit"]:has-text("Save")');
        await page.waitForLoadState('networkidle');
        await delay(2500);

        // Step 5: Sign out
        console.log('5. Signing out...');
        const signOutButton = page.locator('form[action*="logout.php"] button, a:has-text("Sign out"), button:has-text("Sign out")').first();
        if (await signOutButton.count() > 0) {
            await signOutButton.click();
            await page.waitForLoadState('networkidle');
        }
        await delay(1500);

        console.log('--- Doctor User Story Completed Successfully ---');
    } catch (err) {
        console.error('Error during doctor user story:', err);
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
    await runDoctorStory();
    await renameLatestRecording('doctor-user-story.webm');
})();
