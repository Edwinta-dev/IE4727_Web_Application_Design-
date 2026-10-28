import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { chromium } from 'playwright';
import { withTestServer, visit, login, root } from './lib.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';

const output = resolve(root, 'UIPROBLEMS/after/iter9/standout-features');
await mkdir(output, { recursive: true });

await withTestServer(true, async (base) => {
  const browser = await chromium.launch({ headless: true });
  try {
    // =========================================================================
    // 1. Feature 1: Doctor directory client-side live search filter (doctors.php)
    // =========================================================================
    for (const width of [1280, 390]) {
      const height = width === 1280 ? 800 : 844;
      const page = await browser.newPage({ viewport: { width, height } });
      try {
        let networkRequestsDuringTyping = 0;
        page.on('request', (req) => {
          const resourceType = req.resourceType();
          if (['fetch', 'xhr'].includes(resourceType)) {
            networkRequestsDuringTyping++;
          }
        });

        await page.goto(`${base}doctors.php`, { waitUntil: 'networkidle' });

        const searchInput = page.locator('#doctor-search');
        const emptyMessage = page.locator('#doctor-search-empty');
        const rows = page.locator('.doctor-directory tbody tr');

        assert.equal(await searchInput.count(), 1, `[${width}px] Search input exists`);
        assert.ok(await searchInput.isVisible(), `[${width}px] Search is revealed when JavaScript runs`);
        assert.equal(await emptyMessage.count(), 1, `[${width}px] Empty state message exists`);
        assert.ok(await emptyMessage.isHidden(), `[${width}px] Empty state message initially hidden`);

        const totalRows = await rows.count();
        assert.ok(totalRows >= 5, `[${width}px] Expected at least 5 doctor rows, got ${totalRows}`);

        // All rows initially visible
        for (let i = 0; i < totalRows; i++) {
          assert.ok(await rows.nth(i).isVisible(), `[${width}px] Row ${i} is initially visible`);
        }

        // Test 1: Search by doctor name "Smith"
        await searchInput.fill('Smith');
        let visibleCount = 0;
        for (let i = 0; i < totalRows; i++) {
          if (await rows.nth(i).isVisible()) {
            visibleCount++;
            const text = (await rows.nth(i).getAttribute('data-search')).toLowerCase();
            assert.ok(text.includes('smith'), `[${width}px] Visible row contains Smith`);
          } else if (width === 390) {
            // Confirm tr[hidden] rule prevents mobile CSS grid override
            const display = await rows.nth(i).evaluate((el) => window.getComputedStyle(el).display);
            assert.equal(display, 'none', `[${width}px] Unmatched row is display: none`);
          }
        }
        assert.equal(visibleCount, 1, `[${width}px] Exactly 1 row matches "Smith"`);
        assert.ok(await emptyMessage.isHidden(), `[${width}px] Empty message hidden when match found`);
        await page.screenshot({ path: resolve(output, `doctors-search-smith-${width}.png`), fullPage: true });

        // Test 2: Search by language "Mandarin"
        await searchInput.fill('Mandarin');
        let mandarinCount = 0;
        for (let i = 0; i < totalRows; i++) {
          if (await rows.nth(i).isVisible()) {
            mandarinCount++;
            const text = (await rows.nth(i).getAttribute('data-search')).toLowerCase();
            assert.ok(text.includes('mandarin'), `[${width}px] Visible row contains Mandarin`);
          }
        }
        assert.ok(mandarinCount >= 2, `[${width}px] Multiple doctors speak Mandarin (got ${mandarinCount})`);
        assert.ok(await emptyMessage.isHidden(), `[${width}px] Empty message hidden for Mandarin matches`);
        assert.match(await emptyMessage.innerText(), new RegExp(`${mandarinCount} doctors? match`));

        // Search is limited to name, specialty, and languages, not action labels.
        await searchInput.fill('book');
        assert.equal(await rows.locator(':visible').count(), 0, `[${width}px] Book button label is not searchable`);
        assert.ok(await emptyMessage.isVisible(), `[${width}px] No-match message is shown for book`);
        assert.match(await emptyMessage.innerText(), /No doctors match/);

        // Test 3: Search unmatched query "xyznonexistent"
        await searchInput.fill('xyznonexistent');
        let unmatchedVisible = 0;
        for (let i = 0; i < totalRows; i++) {
          if (await rows.nth(i).isVisible()) unmatchedVisible++;
        }
        assert.equal(unmatchedVisible, 0, `[${width}px] 0 rows visible for unmatched query`);
        assert.ok(await emptyMessage.isVisible(), `[${width}px] Empty state visible for unmatched query`);
        await page.screenshot({ path: resolve(output, `doctors-search-empty-${width}.png`), fullPage: true });

        // Test 4: Clear search restoring all rows
        await searchInput.fill('');
        let restoredCount = 0;
        for (let i = 0; i < totalRows; i++) {
          if (await rows.nth(i).isVisible()) restoredCount++;
        }
        assert.equal(restoredCount, totalRows, `[${width}px] All rows restored after clearing search`);
        assert.ok(await emptyMessage.isHidden(), `[${width}px] Empty message hidden after clearing search`);

        // Test 5: Enter key does not reload or navigate away
        await searchInput.fill('Smith');
        await searchInput.press('Enter');
        assert.ok(!page.url().includes('?q='), `[${width}px] Enter key did not submit form as GET query`);
        assert.equal(await searchInput.inputValue(), 'Smith', `[${width}px] Search input retains value after Enter`);

        // Test 6: Zero AJAX / fetch / XHR requests
        assert.equal(networkRequestsDuringTyping, 0, `[${width}px] Zero network transport during typing`);
      } finally {
        await page.close();
      }
    }

    // A specialty with no results retains only the table's server-rendered empty state.
    {
      const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
      try {
        await page.goto(`${base}doctors.php?specialty=No+such+specialty`, { waitUntil: 'networkidle' });
        assert.equal(await page.locator('.doctor-directory tbody tr.empty-state').count(), 1);
        assert.ok(await page.locator('.doctor-directory tbody tr.empty-state').isVisible());
        assert.ok(await page.locator('#doctor-search-empty').isHidden());
        assert.ok(await page.locator('.doctor-search-box').isHidden(), 'Search is skipped when the specialty has no doctor rows');
        assert.equal(await page.locator('.empty-state:visible').count(), 1, 'Only the table empty state is shown');
      } finally {
        await page.close();
      }
    }

    // Progressive enhancement: JavaScript disabled
    {
      const context = await browser.newContext({ javaScriptEnabled: false });
      const page = await context.newPage({ viewport: { width: 1280, height: 800 } });
      try {
        await page.goto(`${base}doctors.php`, { waitUntil: 'networkidle' });
        const rows = page.locator('.doctor-directory tbody tr');
        assert.ok((await rows.count()) >= 5, 'Server-rendered doctor rows present without JS');
        assert.equal(await page.locator('#specialty').count(), 1, 'Specialty select exists without JS');
        assert.equal(await page.locator('.specialty-filter button').count(), 1, 'Noscript filter button exists');
        assert.ok(await page.locator('.doctor-search-box').isHidden(), 'Search control stays hidden without JS');
      } finally {
        await context.close();
      }
    }

    // =========================================================================
    // 2. Feature 2: Patient consultation slip & @media print (patient/home.php)
    // =========================================================================
    for (const width of [1280, 390]) {
      const height = width === 1280 ? 800 : 844;
      const page = await browser.newPage({ viewport: { width, height } });
      try {
        await login(page, 'patient', base);
        await page.goto(`${base}patient/home.php`, { waitUntil: 'networkidle' });
        assert.equal(await page.locator('.medical-slip').count(), 0, `[${width}px] Normal appointments page has no summary`);
        await page.emulateMedia({ media: 'print' });
        for (const selector of ['.upcoming-appointments', '.past-appointments']) {
          const section = page.locator(selector);
          assert.ok(await section.isVisible(), `[${width}px] ${selector} remains visible when printing the appointments list`);
          assert.notEqual(await section.evaluate((el) => window.getComputedStyle(el).display), 'none');
        }
        await page.emulateMedia({ media: 'screen' });

        await page.goto(`${base}patient/home.php?view=2`, { waitUntil: 'networkidle' });

        // Section & Print button verification
        const visitNotes = page.locator('.visit-notes');
        assert.equal(await visitNotes.count(), 1, `[${width}px] Visit notes section rendered`);

        const printButton = page.locator('.print-slip-button');
        assert.equal(await printButton.count(), 1, `[${width}px] Print consultation slip button exists`);
        assert.equal(await printButton.innerText(), 'Print consultation summary', `[${width}px] Button label correct`);
        assert.equal(await printButton.getAttribute('onclick'), 'window.print()', `[${width}px] Button triggers window.print()`);
        assert.ok(await printButton.isVisible(), `[${width}px] Print button visible on screen`);

        // Medical slip container & content verification
        const slip = page.locator('.medical-slip');
        assert.equal(await slip.count(), 1, `[${width}px] Medical slip container exists`);
        assert.ok(await slip.isVisible(), `[${width}px] Medical slip is visible on screen`);

        // Consultation summary title & header details
        assert.equal(await page.locator('.medical-slip .slip-title').innerText(), 'Consultation summary');
        assert.equal(await page.locator('.medical-slip .slip-clinic-name').innerText(), 'Clinic Appointment Portal');

        // Metadata fields
        const metaText = await page.locator('.medical-slip .slip-metadata').innerText();
        assert.ok(metaText.includes('Alex Tan'), `[${width}px] Slip includes patient name Alex Tan`);
        assert.ok(metaText.includes('Dr. Priya Nair'), `[${width}px] Slip includes attending doctor Dr. Priya Nair`);
        assert.ok(metaText.includes('Dental'), `[${width}px] Slip includes specialty Dental`);
        assert.ok(metaText.includes('Consultation date:'), `[${width}px] Slip includes Consultation date`);
        assert.ok(metaText.includes('Printed on:'), `[${width}px] Slip includes Printed on date`);

        // Clinical findings
        const findingsText = await page.locator('.medical-slip .slip-findings').innerText();
        assert.ok(findingsText.includes('Diagnosis:'), `[${width}px] Slip includes Diagnosis`);
        assert.ok(findingsText.includes('Prescribed Medications:'), `[${width}px] Slip includes Prescribed Medications`);
        assert.ok(findingsText.includes('Treatment Plan:'), `[${width}px] Slip includes Treatment Plan`);
        assert.ok(findingsText.includes('Follow-up instructions:'), `[${width}px] Slip includes Follow-up instructions`);

        // Validation footer
        assert.equal(await page.locator('.medical-slip .slip-signature-label').innerText(), 'Attending Physician Signature');
        assert.equal(await page.locator('.medical-slip .slip-doctor-name').innerText(), 'Dr. Priya Nair');
        assert.equal((await page.locator('.medical-slip .stamp-text').textContent()).trim(), 'Clinic stamp');

        // Layout check on screen: no horizontal scrollbar overflow
        const screenOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        assert.equal(screenOverflow, false, `[${width}px] No horizontal layout overflow on screen`);
        await page.screenshot({ path: resolve(output, `patient-slip-screen-${width}.png`), fullPage: true });

        // Emulate print media & verify @media print styling
        await page.emulateMedia({ media: 'print' });

        const hiddenInPrint = [
          '.site-header',
          '.site-nav',
          '.site-footer',
          '.page-intro',
          '.upcoming-appointments',
          '.past-appointments',
          '.cancelled-appointments',
          '.patient-notifications',
          '.flash',
          '.skip-link',
          '.print-slip-button',
          '.table-scroll-hint',
          '.visit-notes-header',
        ];

        for (const selector of hiddenInPrint) {
          const locator = page.locator(selector);
          if ((await locator.count()) > 0) {
            const display = await locator.first().evaluate((el) => window.getComputedStyle(el).display);
            assert.equal(display, 'none', `[${width}px] ${selector} must be display: none in print media`);
          }
        }
        for (const selector of ['.upcoming-appointments', '.past-appointments']) {
          assert.ok(await page.locator(selector).isHidden(), `[${width}px] ${selector} is hidden while printing an open summary`);
        }

        const slipDisplay = await slip.evaluate((el) => window.getComputedStyle(el).display);
        assert.equal(slipDisplay, 'block', `[${width}px] .medical-slip must be display: block in print media`);

        const logoFilter = await page.locator('.medical-slip .slip-logo').evaluate((el) => window.getComputedStyle(el).filter);
        assert.ok(logoFilter.includes('grayscale'), `[${width}px] Slip logo is grayscale in print media`);

        const printOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        assert.equal(printOverflow, false, `[${width}px] No horizontal overflow in print media`);
        await page.screenshot({ path: resolve(output, `patient-slip-print-${width}.png`), fullPage: true });

        // Reset to screen media
        await page.emulateMedia({ media: 'screen' });

        // Patient isolation check: appointment belonging to another patient
        await page.goto(`${base}patient/home.php?view=3`, { waitUntil: 'networkidle' });
        assert.equal(await page.locator('.medical-slip').count(), 0, `[${width}px] Cross-patient appointment renders no slip`);
        assert.equal(await page.locator('.print-slip-button').count(), 0, `[${width}px] Cross-patient appointment renders no print button`);

        // Non-existent appointment view check
        await page.goto(`${base}patient/home.php?view=999999`, { waitUntil: 'networkidle' });
        assert.equal(await page.locator('.medical-slip').count(), 0, `[${width}px] Non-existent appointment renders no slip`);
        assert.equal(await page.locator('.print-slip-button').count(), 0, `[${width}px] Non-existent appointment renders no print button`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
});

console.log('OK: #212 doctor directory search, #210 consultation slip and #214 scoped print styles verified');
