import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.UI_PATIENT = 'issue143-ui';
process.env.UI_DOCTOR = 'drsmith';
const php = code => testPhp(['-r', code], { encoding: 'utf8' }).trim();
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const patient = Number(php(`require 'clinic-base/models/notifications.php';
    q('INSERT INTO patient (FullName, User, HashPass, Email) VALUES (:name, :user, :hash, :email)',
      ['name'=>'Dashboard test', 'user'=>'issue143-ui', 'hash'=>password_hash('Password123', PASSWORD_DEFAULT), 'email'=>'issue143-ui@example.local']);
    echo db()->lastInsertId();`));
const snapshot = () => php(`require 'clinic-base/models/notifications.php';
    echo json_encode(q_all('SELECT appointmentID, Status, updatedAt FROM appointment WHERE PatientID = :id ORDER BY appointmentID', ['id'=>${patient}]));`);
const captures = 'UIPROBLEMS/after/issue143-functional';
await mkdir(captures, { recursive: true });
const capture = async (page, label) => {
    const openMessages = await page.locator('.patient-notification').evaluateAll(nodes => nodes.flatMap((node, index) => node.open ? [index] : []));
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
        // Reload at each size so the JS-disabled browser resolves responsive
        // custom properties for that viewport rather than retaining desktop values.
        await page.reload();
        for (const index of openMessages) await page.locator('.patient-notification').nth(index).locator('summary').click();
        const png = await page.screenshot({ path: `${captures}/${label}-${width}.png`, fullPage: true });
        assert.equal(png.readUInt32BE(16), width, 'capture matches requested viewport width');
        const geometry = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
            outside: [...document.querySelectorAll('body *')].filter(node => node.getBoundingClientRect().right > innerWidth).map(node => node.className || node.tagName).slice(0, 12) }));
        assert.ok(geometry.scroll <= geometry.width, `${label}: no page overflow ${JSON.stringify(geometry)}`);
    }
};
try {
    await withTestServer(true, async base => {
        const browser = await chromium.launch({ headless: true });
        try {
            const context = await browser.newContext({ javaScriptEnabled: false, reducedMotion: 'reduce' });
            const page = await context.newPage();
            await login(page, 'patient', base);
            assert.equal(await page.locator('.patient-notifications .empty-state').innerText(), 'You have no notifications.');
            await capture(page, 'empty');
            const links = await page.locator('.site-nav a').evaluateAll(nodes => nodes.map(n => [n.textContent.trim(), n.getAttribute('href')]));
            assert.equal(new Set(links.map(([, href]) => href.split('#')[0])).size, links.length, 'distinct patient destinations');
            assert.ok(links.some(([label, href]) => label === 'Book an appointment' && href.endsWith('/book.php')));
            assert.equal(await page.locator('.site-nav [aria-current="page"]').innerText(), 'My appointments');
            for (const [route, label] of [['doctors.php', 'Find a doctor'], ['book.php', 'Book an appointment']]) {
                await page.goto(base + route);
                assert.equal(await page.locator('.site-nav [aria-current="page"]').innerText(), label);
            }
            let added = 0;
            const statuses = ['Future', 'Rescheduled', 'Cancelled', 'Completed', 'No show'];
            // Past pending rows remain stored in their original enum states.
            php(`require 'clinic-base/models/notifications.php';
                foreach (['Future','Rescheduled'] as $status) q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (1,:patient,DATE_SUB(NOW(), INTERVAL 1 DAY),:status)',
                ['patient'=>${patient}, 'status'=>$status]);`);
            for (const total of [3, 7, 12]) {
                while (added < total) {
                    const index = added++;
                    php(`require 'clinic-base/models/notifications.php';
                        $id=log_notification('issue143-ui@example.local', '${index === 0 ? '<script>Subject</script>' : 'Booking update ' + index}', "First line\\n<script>unsafe</script>\\n${'Longmessage'.repeat(45)}");
                        q('UPDATE notifications SET SentAt = :time, deliveryStatus = :status WHERE notificationID = :id',
                          ['time'=>'2044-01-01 09:00:00','status'=>'${['logged', 'sent', 'failed'][index % 3]}','id'=>$id]);
                        q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (1,:patient,:time,:status)',
                          ['patient'=>${patient},'time'=>'2044-01-02 ${String(9 + Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '30' : '00'}:00','status'=>'${statuses[index % 5]}']);`);
                }
                php(`require 'clinic-base/models/notifications.php'; log_notification('private143@example.local', 'Private subject', 'Private body');`);
                const before = snapshot();
                await page.goto(base + 'patient/home.php?patient=2&recipient=private143@example.local');
                assert.equal(await page.locator('.patient-notification').count(), total);
                const expected = JSON.parse(php(`require 'clinic-base/models/notifications.php'; echo json_encode(notifications_for_patient(${patient}));`));
                assert.deepEqual(await page.locator('.patient-notification summary span').allTextContents(), expected.map(row => row.Subject));
                assert.deepEqual(await page.locator('.patient-notification time').evaluateAll(nodes => nodes.map(n => n.getAttribute('datetime'))), expected.map(row => row.SentAt.replace(' ', 'T')));
                assert.equal(await page.locator('.past-appointments tbody tr').filter({ hasText: 'Awaiting outcome' }).count(), 2);
                assert.ok(!(await page.locator('.past-appointments tbody td:nth-child(5)').allTextContents()).includes('Future'));
                assert.deepEqual(await page.locator('.upcoming-appointments tbody td:nth-child(5)').allTextContents(),
                    Array.from({ length: total }, (_, i) => statuses[i % 5]).filter(status => ['Future', 'Rescheduled'].includes(status)), 'future booking states preserved');
                assert.ok(!(await page.locator('main').innerText()).includes('Private subject'));
                assert.equal(await page.locator('.patient-notifications script').count(), 0, 'subject escaped');
                assert.ok(!(await page.locator('.patient-notifications').innerText()).includes('failed'), 'delivery failure not shown');
                const detail = page.locator('.patient-notification').last();
                await detail.locator('summary').focus();
                await page.keyboard.press('Enter');
                assert.equal(await detail.getAttribute('open'), '', 'keyboard body reveal works without JS');
                assert.ok((await detail.locator('.notification-body').innerText()).startsWith('First line\n<script>unsafe</script>'));
                assert.equal(await detail.locator('script').count(), 0, 'body escaped');
                await capture(page, `patient-${total}`);
                const doctor = await browser.newPage({ javaScriptEnabled: false, reducedMotion: 'reduce' });
                await login(doctor, 'doctor', base);
                await doctor.goto(base + 'doctor/home.php?date=2044-01-02');
                assert.equal(await doctor.locator('.day-board tbody tr').count(), total);
                const summary = await doctor.locator('.day-summary').innerText();
                for (const status of statuses) {
                    const count = Array.from({ length: total }, (_, i) => statuses[i % 5]).filter(s => s === status).length;
                    assert.ok(summary.includes(`${status === 'No show' ? 'No-show' : status}: ${count}`), `${status} counted separately: ${summary}`);
                }
                await capture(doctor, `doctor-${total}`);
                await doctor.close();
                assert.equal(snapshot(), before, 'dashboard reads preserve stored states');
                console.log(`OK: #143 ${total} messages/appointments; recipient isolation; escaped no-JS keyboard reveal; awaiting outcomes; exact counters; 1280/390 overflow 0px`);
            }
        } finally { await browser.close(); }
    });
} finally {
    php(`require 'clinic-base/lib/db.php';
        q('DELETE FROM notifications WHERE recipient IN (:patient,:other)', ['patient'=>'issue143-ui@example.local','other'=>'private143@example.local']);
        q('DELETE FROM patient WHERE PatientID = :id', ['id'=>${patient}]);`);
}
console.log('OK: #143 patient dashboard acceptance; isolated ie4727db_test');
