import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp, testEnvironment } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.UI_PATIENT = 'issue151-ui';
const before = process.argv.includes('--before');
const php = code => testPhp(['-r', code], { encoding: 'utf8' }).trim();
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const patient = Number(php(`require 'clinic-base/models/appointments.php';
    q('INSERT INTO patient (FullName, User, HashPass, Email) VALUES (:name,:user,:hash,:email)',
      ['name'=>'Dashboard chronology test','user'=>'issue151-ui','hash'=>password_hash('Password123', PASSWORD_DEFAULT),'email'=>'issue151-ui@example.local']);
    echo db()->lastInsertId();`));
const seed = (total, upcoming) => php(`require 'clinic-base/models/appointments.php';
    q('DELETE FROM appointment WHERE PatientID = :id', ['id'=>${patient}]);
    for ($i=0; $i<${total}; $i++) {
        foreach (['Cancelled', $i % 2 ? 'No show' : 'Completed'${upcoming ? ", $i % 2 ? 'Rescheduled' : 'Future'" : ''}] as $status) {
            $days = in_array($status, ['Completed','No show'], true) || ($status === 'Cancelled' && $i % 2) ? -($i+1) : $i+1;
            q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (1,:patient,TIMESTAMPADD(DAY,:days,NOW()),:status)',
              ['patient'=>${patient},'days'=>$days,'status'=>$status]);
        }
    }`);
try {
    seed(3, false);
    execFileSync('node', ['tools/ui/shoot.mjs','--serve','--label',`issue151-${before ? 'before' : 'after'}`,'patient/home.php'],
        { env: testEnvironment(), stdio: 'inherit', windowsHide: true });
    await withTestServer(true, async base => {
        const browser = await chromium.launch({ headless: true });
        try {
            const page = await browser.newPage({ javaScriptEnabled: false, reducedMotion: 'reduce' });
            await login(page, 'patient', base);
            if (!before) {
                seed(0, false);
                for (const width of [1280,390]) {
                    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
                    await page.goto(base + 'patient/home.php');
                    assert.equal(await page.locator('.cancelled-appointments').count(), 0, 'cancelled section omitted when empty');
                    assert.equal(await page.locator('.past-appointments .empty-state').innerText(), 'You have no past appointments.');
                    assert.equal(await page.locator('.upcoming-appointments .empty-state a').innerText(), 'Book an appointment');
                }
            }
            for (const total of before ? [3] : [3,7,12]) {
                for (const upcoming of before ? [false] : [false,true]) {
                    seed(total, upcoming);
                    const snapshot = php(`require 'clinic-base/models/appointments.php'; echo json_encode(appointments_for_patient(${patient}));`);
                    for (const width of [1280,390]) {
                        await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
                        await page.goto(base + 'patient/home.php');
                        const measure = await page.evaluate(() => ({
                            overflow: document.documentElement.scrollWidth - innerWidth,
                            pastStatuses: [...document.querySelectorAll('.past-appointments tbody td:nth-child(5)')].map(n=>n.textContent.trim()),
                            rowHeights: [...document.querySelectorAll('.appointments .appointment-table-scroll:not(.is-empty) tbody tr')].map(n=>n.getBoundingClientRect().height),
                            emptyBorders: [...document.querySelectorAll('.upcoming-appointments .is-empty :is(table,td,.empty-state)')].map(n=>[n.tagName,getComputedStyle(n).borderTopWidth,getComputedStyle(n).borderBottomWidth]),
                            emptyCta: !!document.querySelector('.upcoming-appointments .empty-state a[href$="/book.php"]')
                        }));
                        console.log(JSON.stringify({total,upcoming,width,...measure}));
                        if (before) continue;
                        assert.ok(measure.overflow <= 0, 'no page overflow');
                        assert.ok(!measure.pastStatuses.includes('Cancelled'), 'cancelled visits excluded from Past');
                        assert.equal(await page.locator('.cancelled-appointments tbody tr').count(), total);
                        assert.deepEqual(await page.locator('.cancelled-appointments tbody td:nth-child(5)').allTextContents(), Array(total).fill('Cancelled'));
                        assert.equal(await page.locator('.past-appointments tbody tr').count(), total);
                        assert.ok(Math.max(...measure.rowHeights) - Math.min(...measure.rowHeights) < 1, 'same row height with and without actions');
                        if (upcoming) {
                            assert.equal(await page.locator('.upcoming-appointments tbody tr').count(), total);
                            assert.equal(await page.locator('.upcoming-appointments form').count(), total);
                        } else {
                            assert.ok(measure.emptyCta, 'empty upcoming provides primary booking action');
                            assert.deepEqual(measure.emptyBorders.slice(0,2).map(n=>n.slice(1)), [['0px','0px'],['0px','0px']], 'only empty state has a border');
                            await page.locator('.upcoming-appointments .empty-state a').click();
                            assert.ok(page.url().endsWith('/book.php'), 'booking CTA reaches existing booking page');
                        }
                        await page.goto(base + 'patient/home.php');
                        await page.screenshot({ path: `UIPROBLEMS/after/issue151-after/rows-${total}-${upcoming ? 'upcoming' : 'empty'}-${width}.png`, fullPage: true });
                    }
                    assert.equal(php(`require 'clinic-base/models/appointments.php'; echo json_encode(appointments_for_patient(${patient}));`), snapshot, 'dashboard preserves stored records');
                }
            }
        } finally { await browser.close(); }
    });
} finally {
    php(`require 'clinic-base/models/appointments.php'; q('DELETE FROM patient WHERE PatientID = :id', ['id'=>${patient}]);`);
}
console.log(before ? 'OK: #151 before measurements' : 'OK: #151 dashboard acceptance; 3/7/12 items; 1280/390; isolated ie4727db_test');
