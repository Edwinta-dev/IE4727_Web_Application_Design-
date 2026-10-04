import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const testGuard = fileURLToPath(new URL('./test-database.php', import.meta.url));
export function testEnvironment(env = process.env) {
  const child = {};
  for (const [key, value] of Object.entries(env)) {
    const normalized = key.toUpperCase();
    if (Object.hasOwn(child, normalized) && child[normalized] !== value)
      throw Error(`Conflicting environment key ${normalized}`);
    child[normalized] = value;
  }
  if (child.CLINIC_DB_NAME !== 'ie4727db_test')
    throw Error('UI mutations require explicit ie4727db_test before PHP launch.');
  return child;
}
export function verifyTestConfig(env = process.env, run = execFileSync) {
  const child = testEnvironment(env);
  const db = run('php', ['-r', 'require $argv[1]; echo DB_NAME;', testGuard],
    { env: child, encoding: 'utf8', windowsHide: true }).trim();
  if (db !== 'ie4727db_test') throw Error('Effective PHP test database was not verified.');
  return child;
}
export function testPhp(args, options = {}) {
  const env = verifyTestConfig(options.env);
  // -r does not execute auto_prepend_file; explicitly prepend the same guard.
  const guarded = [...args];
  if (guarded[0] === '-r') guarded[1] = `require '${testGuard.replaceAll('\\', '/')}'; ${guarded[1]}`;
  return execFileSync('php', ['-d', 'disable_functions=mail', '-d',
    `auto_prepend_file=${testGuard.replaceAll('\\', '/')}`, ...guarded],
    { ...options, env, windowsHide: true });
}
