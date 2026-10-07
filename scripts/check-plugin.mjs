import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const result = spawnSync(process.execPath, [
  'scripts/wp-env.cjs', '--config', '.wp-env.tests.json', 'run', 'cli', 'wp', 'plugin', 'check',
  'lightweight-integration-for-indexnow', '--slug=lightweight-integration-for-indexnow',
  '--require=/var/www/html/wp-content/plugins/plugin-check/cli.php',
  '--format=strict-json', '--error-severity=1', '--warning-severity=1',
  '--categories=general,plugin_repo,security,performance,accessibility',
], { cwd: root, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
process.stdout.write(result.stdout || '');
process.stderr.write(result.stderr || '');
if (result.error) throw result.error;
if (result.status !== 0) process.exit(result.status || 1);

// Plugin Check 2.1 can exit zero even when it prints ERROR findings.
// Its strict JSON format emits findings instead of this explicit clean-run message.
// Fail closed if the command produces findings or changes its output contract.
const output = (result.stdout || '').replace(/\u001b\[[0-9;]*m/g, '');
if (!/^Success: Checks complete\. No errors found\.$/m.test(output)) {
  console.error('FAIL: Plugin Check reported findings or did not confirm a clean run.');
  process.exit(1);
}
console.log('PASS: Plugin Check reports no errors or warnings across all five categories.');
