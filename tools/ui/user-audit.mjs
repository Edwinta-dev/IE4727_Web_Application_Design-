import { spawnSync } from 'node:child_process';

if (process.env.CLINIC_DB_NAME !== 'ie4727db_test') {
  throw Error('Set CLINIC_DB_NAME=ie4727db_test before running the user audit');
}

// Each finding has an existing regression from its repair issue. Run them in
// order because several browser fixtures reset the same isolated database.
const findings = [
  ['1 · active slot reuse', 'php', ['tests/run.php', 'test_slot_reuse']],
  ['2 · appointment locking', 'php', ['tests/run.php', 'test_concurrency_reschedule']],
  ['3 · mail after commit', 'php', ['tests/run.php', 'test_mail_delivery']],
  ['4 · reschedule reason', 'php', ['tests/run.php', 'test_issue161']],
  ['5 · remember-me control', 'php', ['tests/run.php', 'test_index']],
  ['6 · login throttling', 'php', ['tests/run.php', 'test_auth']],
  ['7 · mobile actions', 'node', ['tests/ui_mobile_actions_164.mjs']],
  ['8 · admin filters', 'node', ['tests/ui_admin_console_165.mjs']],
  ['9 · expired slots', 'node', ['tools/ui/expired-slots.test.mjs']],
  ['10 · booking confirmation', 'node', ['tests/ui_booking_success_167.mjs']],
];

for (const [finding, command, args] of findings) {
  console.log(`Finding ${finding}: ${command} ${args.join(' ')}`);
  const result = spawnSync(command, args, {
    cwd: process.cwd(),
    env: { ...process.env, CLINIC_DB_NAME: 'ie4727db_test' },
    stdio: 'inherit',
    shell: process.platform === 'win32',
  });
  if (result.error) throw result.error;
  if (result.status !== 0) throw Error(`${finding} failed with exit ${result.status}`);
}

console.log('OK: all ten user-audit finding regressions passed against ie4727db_test');
