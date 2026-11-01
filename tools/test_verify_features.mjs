import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('./ui/node_modules/playwright-core');

const BASE_URL_BASE = 'http://localhost:8000/ie4727_web_application_design-/clinic-base/';
const BASE_URL_PLUS = 'http://localhost:8000/ie4727_web_application_design-/clinic-plus/';

async function verifyFeatures() {
    console.log('====================================================');
    console.log('UI FEATURE VERIFICATION (CLINIC-BASE & CLINIC-PLUS)');
    console.log('====================================================');

    const browser = await chromium.launch({ headless: true });

    // ----------------------------------------------------
    // 1. Verify Clinic-Base UI Features
    // ----------------------------------------------------
    console.log('\n--- 1. VERIFYING CLINIC-BASE UI ---');
    const pageBase = await browser.newPage();

    // 1.1 Home Page UI
    await pageBase.goto(BASE_URL_BASE + 'index.php', { waitUntil: 'networkidle' });
    const baseTitle = await pageBase.title();
    const heroHeading = await pageBase.locator('h1').innerText();
    const featuredCarousel = await pageBase.locator('[data-doctor-carousel]').count();
    const specialtyCards = await pageBase.locator('.specialty-item').count();
    const loginCard = await pageBase.locator('#login-card').count();
    console.log('  [Base Home] Title:', baseTitle);
    console.log('  [Base Home] Hero H1:', heroHeading);
    console.log('  [Base Home] Featured Carousels:', featuredCarousel);
    console.log('  [Base Home] Specialty items on Home:', specialtyCards);
    console.log('  [Base Home] Login card present:', loginCard > 0);

    // 1.2 Doctors Directory UI & Filtering
    await pageBase.goto(BASE_URL_BASE + 'doctors.php', { waitUntil: 'networkidle' });
    const doctorCountTotal = await pageBase.locator('.doctor-card').count();
    const filterOptions = await pageBase.locator('.specialty-pills a, .specialty-chip, .filter-chip, a[href*="specialty="]').count();
    console.log('  [Base Doctors] Total doctor cards rendered:', doctorCountTotal);
    console.log('  [Base Doctors] Specialty filter links:', filterOptions);

    // 1.3 Doctor Detailed Profile UI
    await pageBase.goto(BASE_URL_BASE + 'doctor.php?id=1', { waitUntil: 'networkidle' });
    const doctorName = await pageBase.locator('h1').innerText();
    const doctorQuals = await pageBase.locator('.doctor-qualifications, .qualifications, p:has-text("MBBS")').count();
    const doctorBio = await pageBase.locator('.doctor-bio, .writeup, p:has-text("family physician")').count();
    console.log('  [Base Doctor Profile] Name:', doctorName);
    console.log('  [Base Doctor Profile] Qualifications displayed:', doctorQuals > 0);
    console.log('  [Base Doctor Profile] Biographical writeup displayed:', doctorBio > 0);

    // 1.4 Doctor Login & Day Board UI
    await pageBase.goto(BASE_URL_BASE + 'index.php#login-card', { waitUntil: 'networkidle' });
    await pageBase.fill('input[name="username_or_email"]', 'drsmith');
    await pageBase.fill('input[name="password"]', 'Password123');
    await pageBase.click('button:has-text("Sign in")');
    await pageBase.waitForLoadState('networkidle');
    const doctorHomeHeading = await pageBase.locator('h1').innerText();
    const summaryStrip = await pageBase.locator('.day-summary, .summary-strip').innerText();
    const dateSwitcher = await pageBase.locator('form.day-board-filter input[type="date"]').count();
    console.log('  [Base Doctor Dashboard] H1:', doctorHomeHeading);
    console.log('  [Base Doctor Dashboard] Summary workload strip:', summaryStrip.replace(/\s+/g, ' '));
    console.log('  [Base Doctor Dashboard] Date picker switcher present:', dateSwitcher > 0);

    // 1.5 Doctor Schedule Management UI
    await pageBase.goto(BASE_URL_BASE + 'doctor/schedule.php', { waitUntil: 'networkidle' });
    const scheduleSlots = await pageBase.locator('.schedule-slot, .slot, .schedule-grid button, .schedule-grid form').count();
    const blockButtons = await pageBase.locator('button:has-text("Block"), button:has-text("Reopen")').count();
    console.log('  [Base Doctor Schedule] Slot items/controls rendered:', scheduleSlots);
    console.log('  [Base Doctor Schedule] Interactive Block/Reopen buttons:', blockButtons);

    // 1.6 Patient Clinical Consultation Visit Notes UI
    await pageBase.goto(BASE_URL_BASE + 'doctor/visit.php?appt=23', { waitUntil: 'networkidle' });
    const visitPatientName = await pageBase.locator('.patient-profile h2, .patient-name').innerText();
    const allergyDisplay = await pageBase.locator('.patient-profile').first().innerText();
    const diagInput = await pageBase.locator('[name="Diagnosis"]').count();
    const rxInput = await pageBase.locator('[name="Prescription"]').count();
    const txInput = await pageBase.locator('[name="Treatment"]').count();
    const followUpInput = await pageBase.locator('[name="FollowUp"]').count();
    const remarksInput = await pageBase.locator('[name="Remarks"]').count();
    console.log('  [Base Doctor Visit] Patient on chart:', visitPatientName);
    console.log('  [Base Doctor Visit] Allergy alert visible in sidebar:', allergyDisplay.includes('Allergies'));
    console.log('  [Base Doctor Visit] Clinical chart fields present (Diag, Rx, Tx, FollowUp, Remarks):',
        diagInput > 0 && rxInput > 0 && txInput > 0 && followUpInput > 0 && remarksInput > 0);

    // 1.7 Patient Login & Dashboard UI
    const pagePatient = await browser.newPage();
    await pagePatient.goto(BASE_URL_BASE + 'index.php#login-card', { waitUntil: 'networkidle' });
    await pagePatient.fill('input[name="username_or_email"]', 'alextan');
    await pagePatient.fill('input[name="password"]', 'Password123');
    await pagePatient.click('button:has-text("Sign in")');
    await pagePatient.waitForLoadState('networkidle');
    const patientH1 = await pagePatient.locator('h1').innerText();
    const upcomingHeading = await pagePatient.locator('#upcoming-heading, h2:has-text("Upcoming")').count();
    const pastHeading = await pagePatient.locator('#past-heading, h2:has-text("Past")').count();
    const notesLinks = await pagePatient.locator('a:has-text("View visit notes")').count();
    console.log('  [Base Patient Dashboard] H1:', patientH1);
    console.log('  [Base Patient Dashboard] Upcoming & Past sections present:', upcomingHeading > 0 && pastHeading > 0);
    console.log('  [Base Patient Dashboard] "View visit notes" links for completed visits:', notesLinks);

    // 1.8 Admin Operational Console UI & Outbox UI
    const pageAdmin = await browser.newPage();
    // Use admin login
    await pageAdmin.goto(BASE_URL_BASE + 'index.php#login-card', { waitUntil: 'networkidle' });
    await pageAdmin.fill('input[name="username_or_email"]', 'admin');
    await pageAdmin.fill('input[name="password"]', 'Password123');
    await pageAdmin.click('button:has-text("Sign in")');
    await pageAdmin.waitForLoadState('networkidle');
    console.log('  [Base Admin Login] Redirected to:', pageAdmin.url());
    if (pageAdmin.url().includes('console.php')) {
        const adminStats = await pageAdmin.locator('.stat, .stats-card, .metric, table').count();
        console.log('  [Base Admin Console] Operational metrics/tables:', adminStats);
        await pageAdmin.goto(BASE_URL_BASE + 'admin/outbox.php', { waitUntil: 'networkidle' });
        const outboxRows = await pageAdmin.locator('table tbody tr').count();
        console.log('  [Base Admin Outbox] Notification log rows:', outboxRows);
    }

    // ----------------------------------------------------
    // 2. Verify Clinic-Plus (Modern Enhancement) UI Features
    // ----------------------------------------------------
    console.log('\n--- 2. VERIFYING CLINIC-PLUS UI ---');
    const pagePlus = await browser.newPage();

    // 2.1 Index of clinic-plus
    await pagePlus.goto(BASE_URL_PLUS + 'index.php', { waitUntil: 'networkidle' });
    console.log('  [Plus Home] Title:', await pagePlus.title());

    // 2.2 htmx Dynamic Partial Slots in clinic-plus/book.php
    await pagePlus.goto(BASE_URL_PLUS + 'book.php?doctor=1', { waitUntil: 'networkidle' });
    const htmxScriptTag = await pagePlus.locator('script[src*="htmx"]').count();
    const hxGetAttributes = await pagePlus.locator('[hx-get]').count();
    const schedulePanel = await pagePlus.locator('#schedule-panel').count();
    console.log('  [Plus Booking] htmx script loaded in DOM:', htmxScriptTag > 0);
    console.log('  [Plus Booking] Elements with hx-get partial requests:', hxGetAttributes);
    console.log('  [Plus Booking] Dynamic #schedule-panel present:', schedulePanel > 0);

    // Test clicking a day tab in clinic-plus book.php to observe htmx dynamic partial swap
    const plusDayTabs = pagePlus.locator('.day-tabs .day-tab');
    if (await plusDayTabs.count() > 1) {
        console.log('  [Plus Booking] Clicking day tab via htmx...');
        await plusDayTabs.nth(1).click();
        await pagePlus.waitForTimeout(1000);
        const panelContent = await pagePlus.locator('#schedule-panel').innerText();
        console.log('  [Plus Booking] #schedule-panel content updated dynamically (first 60 chars):', panelContent.slice(0, 60).replace(/\n/g, ' '));
    }

    // 2.3 Doctor Live Board with Server-Sent Events (SSE)
    await pagePlus.goto(BASE_URL_PLUS + 'index.php#login-card', { waitUntil: 'networkidle' });
    await pagePlus.fill('input[name="username_or_email"]', 'drsmith');
    await pagePlus.fill('input[name="password"]', 'Password123');
    await pagePlus.click('button:has-text("Sign in")');
    await pagePlus.waitForLoadState('networkidle');
    console.log('  [Plus Doctor Login] Landed at:', pagePlus.url());

    await pagePlus.goto(BASE_URL_PLUS + 'doctor/board.php', { waitUntil: 'networkidle' });
    const liveBoardTitle = await pagePlus.title();
    const boardStatus = await pagePlus.locator('#board-status').innerText();
    const liveTable = await pagePlus.locator('table.day-board, table').count();
    console.log('  [Plus Live Board] Title:', liveBoardTitle);
    console.log('  [Plus Live Board] SSE Status element:', boardStatus);
    console.log('  [Plus Live Board] Live table rendered:', liveTable > 0);

    // 2.4 Verify SSE endpoint response directly
    const sseResponse = await pagePlus.request.get(BASE_URL_PLUS + 'events.php?date=2026-10-27');
    console.log('  [Plus SSE Endpoint] events.php status:', sseResponse.status());
    console.log('  [Plus SSE Endpoint] Content-Type:', sseResponse.headers()['content-type']);

    await browser.close();
    console.log('\n====================================================');
    console.log('VERIFICATION COMPLETE');
    console.log('====================================================');
}

verifyFeatures().catch(console.error);
