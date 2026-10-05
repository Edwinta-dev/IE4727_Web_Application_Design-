import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { withTestServer, login, root } from './lib.mjs';
import { testPhp } from './isolation.mjs';

process.env.CLINIC_DB_NAME = 'ie4727db_test';
const php = (...args) => testPhp(args, { encoding: 'utf8' }).trim();
const snapshot = () => JSON.parse(php('tools/ui/leadtime-fixture.php'));
console.log(php('tools/db_reset.php', '--test'));
const seed = snapshot();
assert.equal(seed.database, 'ie4727db_test');
assert.deepEqual([seed.lead.valid, seed.lead.invalid], [27, 0]);
assert.ok(Math.abs(seed.lead.mean_days - 193 / 27) < 0.000001);
php('tools/ui/leadtime-fixture.php', 'samples');
const before = snapshot();
assert.deepEqual(before.rows.slice(0, 27), seed.rows, 'Adding regression samples preserves seed timestamps');
const output = `${root}/UIPROBLEMS/after/leadtime-regression-after`;
await mkdir(output, { recursive: true });
const measurements = [];
await withTestServer(true, async base => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ javaScriptEnabled: false });
    await login(page, 'admin', base);
    const range = 'date_from=2040-01-01&date_to=2040-01-31';
    const states = [
      ['seed', 'date_to=2039-12-31', '7.1 days', '27 of 27 appointments included; 0 excluded.'],
      ['mixed', range, '1.3 days', '3 of 5 appointments included; 2 excluded.'],
      ['invalid', `${range}&status=Cancelled`, 'Unavailable: no usable booking times.', '0 of 1 appointments included; 1 excluded.'],
      ['empty', 'date_from=2041-01-01&date_to=2041-01-31', 'Unavailable: no usable booking times.', '0 of 0 appointments included; 0 excluded.'],
    ];
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 800 : 844 });
      for (const [state, query, result, coverage] of states) {
        await page.goto(`${base}admin/console.php?${query}`);
        const metric = page.locator('.stat-card').filter({ has: page.getByRole('heading', { name: 'Mean booking lead time' }) });
        assert.equal(await metric.locator('.metric-note').getAttribute('open'), null, 'Formula starts collapsed');
        await metric.locator('.metric-note summary').click();
        const text = await metric.innerText();
        assert.ok(text.includes(result), `${state}: ${text}`);
        assert.ok(text.includes(coverage), `${state}: ${text}`);
        assert.equal(text.includes('Excluded: booking time missing or after appointment.'), ['mixed', 'invalid'].includes(state));
        const bounds = await metric.evaluate(el => ({ width: innerWidth, documentWidth: document.documentElement.scrollWidth, metricHeight: el.getBoundingClientRect().height, fontSize: getComputedStyle(el.querySelector('p')).fontSize }));
        assert.equal(bounds.documentWidth, width, 'No document overflow');
        assert.ok(parseFloat(bounds.fontSize) >= 16);
        measurements.push({ state, ...bounds, result, coverage });
        await metric.screenshot({ path: `${output}/${state}-${width}.png` });
      }
    }
  } finally { await browser.close(); }
});
assert.deepEqual(snapshot(), before, 'Reading admin metrics never overwrites booking timestamps');
await writeFile(`${output}/measurements.json`, JSON.stringify(measurements, null, 2));
console.log('OK: lead time 27/27 = 193/27 days; mixed 3/5 = 4/3 days; all-invalid 0/1; empty 0/0; both widths; timestamps preserved');
