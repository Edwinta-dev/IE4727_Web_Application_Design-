import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp, testEnvironment } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
process.env.UI_DOCTOR = 'issue152-ui';
const before = process.argv.includes('--before');
const php = code => testPhp(['-r', code], { encoding: 'utf8' }).trim();
testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
const doctor = Number(php(`require 'clinic-base/models/appointments.php';
    q('INSERT INTO doctor (FullName, User, HashPass, Email) VALUES (:name,:user,:hash,:email)',
      ['name'=>'Day board test','user'=>'issue152-ui','hash'=>password_hash('Password123', PASSWORD_DEFAULT),'email'=>'issue152-ui@example.local']);
    echo db()->lastInsertId();`));
const dates = JSON.parse(php(`echo json_encode(array_map(fn($days)=>(new DateTimeImmutable('today'))->modify($days.' days')->format('Y-m-d'), [0,1,2,4]));`));
const seed = (count, today = false) => php(`require 'clinic-base/models/appointments.php';
    q('DELETE FROM appointment WHERE DoctorID = :id', ['id'=>${doctor}]);
    for ($i=0; $i<${count}; $i++) {
        q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,1,:time,:status)',
          ['doctor'=>${doctor},'time'=>'${dates[today ? 0 : 2]} '.sprintf('%02d:%02d:00', 9+intdiv($i,2), ($i%2)*30),'status'=>$i%2 ? 'Rescheduled' : 'Future']);
    }
    q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,1,:time,:status)',
      ['doctor'=>${doctor},'time'=>'${dates[1]} 09:00:00','status'=>'Cancelled']);
    q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,1,:time,:status)',
      ['doctor'=>${doctor},'time'=>'${dates[3]} 09:00:00','status'=>'Future']);`);
try {
    seed(3);
    execFileSync('node', ['tools/ui/shoot.mjs','--serve','--label',`issue152-${before ? 'before' : 'after'}`,'doctor/home.php'],
        { env: testEnvironment(), stdio: 'inherit', windowsHide: true });
    await withTestServer(true, async base => {
        const browser = await chromium.launch({ headless: true });
        try {
            const page = await browser.newPage({ javaScriptEnabled: false, reducedMotion: 'reduce' });
            await login(page, 'doctor', base);
            for (const total of before ? [3] : [3,7,12]) {
                seed(total);
                for (const width of [1280,390]) {
                    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
                    await page.goto(base + 'doctor/home.php');
                    const measure = await page.evaluate(() => ({
                        date: document.querySelector('#day').value,
                        overflow: document.documentElement.scrollWidth - innerWidth,
                        rows: document.querySelectorAll('.day-board tbody tr').length,
                        navigation: [...document.querySelectorAll('.site-nav a')].map(a=>[a.textContent,a.getAttribute('href')]),
                        steps: document.querySelectorAll('.day-board-navigation a').length,
                    }));
                    console.log(JSON.stringify({total,width,...measure}));
                    if (before) continue;
                    assert.equal(measure.date, dates[2], 'empty today defaults to next active appointment day');
                    assert.equal(measure.rows, total);
                    assert.ok(measure.overflow <= 0);
                    assert.equal(measure.navigation.filter(([,href])=>href.includes('/doctor/home.php')).length, 1);
                    await page.getByRole('link', { name: 'Previous day', exact: true }).click();
                    assert.equal(await page.locator('#day').inputValue(), dates[1]);
                    await page.getByRole('link', { name: 'Next day', exact: true }).click();
                    assert.equal(await page.locator('#day').inputValue(), dates[2]);
                    await page.getByRole('link', { name: 'Jump to next appointment', exact: true }).click();
                    assert.equal(await page.locator('#day').inputValue(), dates[3]);
                    assert.equal(await page.getByRole('link', { name: 'Jump to next appointment', exact: true }).count(), 0);
                    await page.goto(base + `doctor/home.php?date=${dates[0]}`);
                    assert.equal(await page.locator('#day').inputValue(), dates[0], 'explicit empty day preserved');
                    await page.screenshot({path:`UIPROBLEMS/after/issue152-after/empty-${width}.png`,fullPage:true});
                    await page.getByRole('link', { name: 'Jump to next appointment', exact: true }).click();
                    assert.equal(await page.locator('#day').inputValue(), dates[2], 'cancelled-only day skipped');
                    await page.screenshot({path:`UIPROBLEMS/after/issue152-after/rows-${total}-${width}.png`,fullPage:true});
                }
            }
            if (!before) {
                seed(3, true);
                await page.goto(base + 'doctor/home.php');
                assert.equal(await page.locator('#day').inputValue(), dates[0], 'populated today retained');
                php(`require 'clinic-base/models/appointments.php'; q('DELETE FROM appointment WHERE DoctorID=:id',['id'=>${doctor}]);`);
                await page.goto(base + 'doctor/home.php');
                assert.equal(await page.locator('#day').inputValue(), dates[0], 'no future appointments falls back to today');
                assert.equal(await page.getByRole('link', { name: 'Jump to next appointment', exact: true }).count(), 0);
                for (const query of ['date=invalid','date[]=2026-01-01','date=2026-02-30']) {
                    await page.goto(base + 'doctor/home.php?' + query);
                    assert.equal(await page.locator('#day').inputValue(), dates[0]);
                    assert.doesNotMatch(await page.locator('body').innerText(), /Fatal error|Warning:/);
                }
                await page.goto(base + 'doctor/home.php?date=2026-12-31');
                await page.getByRole('link', { name: 'Next day', exact: true }).click();
                assert.equal(await page.locator('#day').inputValue(), '2027-01-01');
                await page.getByRole('link', { name: 'Previous day', exact: true }).click();
                assert.equal(await page.locator('#day').inputValue(), '2026-12-31');
            }
        } finally { await browser.close(); }
    });
} finally {
    php(`require 'clinic-base/models/appointments.php'; q('DELETE FROM doctor WHERE DoctorID=:id',['id'=>${doctor}]);`);
}
console.log(before ? 'OK: #152 before measurements' : 'OK: #152 day navigation acceptance; 3/7/12 rows; 1280/390; no JavaScript; isolated test database');
