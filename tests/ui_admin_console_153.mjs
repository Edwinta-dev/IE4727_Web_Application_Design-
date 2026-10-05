import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login } from '../tools/ui/lib.mjs';
import { testPhp, testEnvironment } from '../tools/ui/isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const before = process.argv.includes('--before');
const php = code => testPhp(['-r', code], { encoding: 'utf8' }).trim();
const seed = count => php(`require 'clinic-base/models/accounts.php';
    q('DELETE FROM doctor'); q('DELETE FROM patient');
    for ($i=1; $i<=${count}; $i++) {
        $fields = ['FullName'=>'Doctor with a long name '.$i, 'User'=>str_repeat('u',38).sprintf('%02d',$i),
            'Email'=>str_repeat('e',40).$i.'@example.local','Specialty'=>'Dermatology and family medicine '.$i,'password'=>'Password123'];
        $doctor = create_doctor_account($fields);
        $fields['FullName'] = 'Patient with a long name '.$i;
        $fields['Phone'] = '81234567'; $fields['Allergies'] = [];
        $patient = create_patient_account($fields);
        q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,:patient,NOW(),:status)',
            ['doctor'=>$doctor,'patient'=>$patient,'status'=>$i%2 ? 'Future' : 'Completed']);
    }`);

testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' });
try {
    seed(3);
    execFileSync('node', ['tools/ui/shoot.mjs','--serve','--label',`issue153-${before ? 'before' : 'after'}`,'admin/console.php'],
        { env: testEnvironment(), stdio: 'inherit', windowsHide: true });
    await withTestServer(true, async base => {
        const browser = await chromium.launch({ headless: true });
        try {
            const page = await browser.newPage({ javaScriptEnabled: false, reducedMotion: 'reduce' });
            await login(page, 'admin', base);
            for (const total of before ? [3] : [3,7,12]) {
                seed(total);
                for (const width of [1280,390]) {
                    await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
                    await page.goto(base + 'admin/console.php');
                    const measure = await page.evaluate(() => ({
                        overflow: document.documentElement.scrollWidth - innerWidth,
                        tables: [...document.querySelectorAll('.console-table-scroll')].map(el => ({
                            name: el.getAttribute('aria-labelledby'), width: el.clientWidth, scroll: el.scrollWidth,
                            rows: el.querySelectorAll('tbody tr').length,
                            clipped: [...el.querySelectorAll('th,td')].some(td => td.scrollWidth > td.clientWidth + 1),
                        })),
                        options: document.querySelectorAll('[name=doctor] option').length - 1,
                        weeklyDoctors: document.querySelectorAll('.stat-card:first-of-type > p').length,
                        noShowDoctors: document.querySelectorAll('.stat-card:nth-of-type(2) > p').length - 1,
                        filters: document.querySelector('#filters-heading').textContent,
                        week: document.querySelector('.stat-card h3').textContent,
                    }));
                    console.log(JSON.stringify({ total, width, ...measure }));
                    if (before) continue;
                    assert.equal(measure.overflow, 0);
                    assert.equal(measure.options, total);
                    assert.equal(measure.weeklyDoctors, total);
                    assert.equal(measure.noShowDoctors, total);
                    for (const table of measure.tables) {
                        assert.equal(table.rows, total);
                        assert.equal(table.clipped, false, 'full text wraps without clipping');
                        if (width === 1280) assert.ok(table.scroll <= table.width + 1, 'all desktop columns fit');
                        else {
                            const region = page.locator(`[aria-labelledby=${table.name}][role=region]`);
                            await region.evaluate(el => { el.scrollLeft = el.scrollWidth; });
                            assert.ok(await region.evaluate(el => {
                                const cell = el.querySelector('tbody tr td:last-child').getBoundingClientRect();
                                const bounds = el.getBoundingClientRect();
                                return cell.right <= bounds.right + 1 && cell.left >= bounds.left - 1;
                            }), 'mobile last column reachable');
                        }
                    }
                    assert.equal(measure.filters, 'Patient, doctor and appointment filters');
                    assert.match(measure.week, /Appointments per doctor this week \(.+ \u2013 .+\)/);
                    assert.equal(await page.locator('.console-filters + .console-section #patients-heading').count(), 1);
                    await page.screenshot({path:`UIPROBLEMS/after/issue153-after/rows-${total}-${width}.png`,fullPage:true});
                    await page.locator('[name=status]').selectOption('Future');
                    await page.getByRole('button', {name:'Apply filters'}).click();
                    for (const name of ['Patients','Doctors','Appointments']) {
                        assert.equal(await page.getByRole('region', {name,exact:true}).locator('tbody tr').count(), Math.ceil(total/2));
                    }
                    assert.ok((await page.locator('.appointment-table .status-label').allTextContents()).every(s=>s==='Future'));
                    await page.goto(base + 'admin/console.php?doctor=99999999');
                    assert.equal(await page.locator('.console-table-scroll .empty-state').count(), 3);
                    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth), 0);
                }
            }
        } finally { await browser.close(); }
    });
} finally { testPhp(['tools/db_reset.php', '--test'], { encoding: 'utf8' }); }
console.log(before ? 'OK: #153 before measurements' : 'OK: #153 admin console acceptance; 3/7/12 items; 1280/390; no JavaScript; isolated test database');
