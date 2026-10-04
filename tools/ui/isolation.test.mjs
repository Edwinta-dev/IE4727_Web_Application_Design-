import assert from 'node:assert/strict';
import { test } from 'node:test';
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { testEnvironment, verifyTestConfig, testGuard } from './isolation.mjs';
import { withTestServer } from './lib.mjs';

for (const database of [undefined, '', 'ie4727db', 'unexpected_test']) {
  test(`refuses ${database ?? 'missing'} before PHP launch`, () => {
    let launches = 0;
    assert.throws(() => verifyTestConfig(database === undefined ? {} : { CLINIC_DB_NAME: database },
      () => { launches++; return 'ie4727db_test'; }), /explicit ie4727db_test/);
    assert.equal(launches, 0);
    const env = { ...process.env, CLINIC_DB_NAME: database ?? '' };
    const result = execFileSync('php', ['-r',
      'try { require $argv[1]; echo "UNSAFE"; } catch (Throwable $e) { echo "REFUSED"; }', testGuard], { env, encoding: 'utf8' });
    assert.equal(result, 'REFUSED');
  });
}
test('effective config refuses production/unexpected constants without DB connection', () => {
  for (const db of ['ie4727db', 'unexpected_test']) {
    const result = execFileSync('php', ['-r',
      'define("DB_NAME", $argv[2]); try { require $argv[1]; echo "UNSAFE"; } catch (Throwable $e) { echo "REFUSED"; }', testGuard, db],
      { env: { ...process.env, CLINIC_DB_NAME: 'ie4727db_test' }, encoding: 'utf8' });
    assert.equal(result, 'REFUSED');
  }
});
test('allowed config and child environment verified without DB connection', () => {
  assert.equal(verifyTestConfig({ ...process.env, CLINIC_DB_NAME: 'ie4727db_test' }).CLINIC_DB_NAME, 'ie4727db_test');
  assert.throws(() => testEnvironment({ CLINIC_DB_NAME: 'ie4727db_test', clinic_db_name: 'ie4727db' }), /Conflicting/);
  assert.throws(() => verifyTestConfig({ CLINIC_DB_NAME: 'ie4727db_test' }, () => 'ie4727db'), /not verified/);
});
test('mutation server refuses external/reused server', async () => {
  process.env.CLINIC_DB_NAME = 'ie4727db_test';
  let callbacks = 0;
  await assert.rejects(withTestServer(false, () => callbacks++), /own clinic-base server/);
  process.env.UI_BASE_URL = 'http://127.0.0.1:8123/clinic-base/';
  await assert.rejects(withTestServer(true, () => callbacks++), /UI_BASE_URL/);
  delete process.env.UI_BASE_URL;
  const stale = createServer((req, res) => { res.writeHead(200); res.end('Stub stale server'); });
  await new Promise((resolve, reject) => { stale.once('error', reject); stale.listen(8123, '127.0.0.1', resolve); });
  try {
    await assert.rejects(withTestServer(true, () => callbacks++), /did not serve/);
    assert.equal(callbacks, 0);
  } finally { await new Promise(resolve => stale.close(resolve)); }
});
