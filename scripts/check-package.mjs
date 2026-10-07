import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const slug = 'lightweight-integration-for-indexnow';
const rules = readFileSync(join(root, '.distignore'), 'utf8').split(/\r?\n/)
  .map((line) => line.trim().replace(/^\//, '')).filter((line) => line && !line.startsWith('#'));
const matchers = rules.map((rule) => new RegExp(`^${rule.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')}(?:/|$)`));
const excluded = (path) => matchers.some((pattern) => pattern.test(path));
const attributes = readFileSync(join(root, '.gitattributes'), 'utf8');

// Guard the distribution and Git archive paths against accidental dev-file leaks.
for (const path of ['.git', '.github', '.pi', '.wordpress-org', 'dist', 'tests', 'scripts', 'docs',
  'node_modules', 'vendor', '.wp-env.json', '.wp-env.tests.json', '.wp-env.minimum.json', '.wp-env.override.json', '.wp-env.tests.override.json', '.wp-env.minimum.override.json', '.env', '.env.local',
  'package.json', 'package-lock.json', 'README.md', 'coverage', 'test-results']) {
  assert(excluded(path), `${path} must be excluded by .distignore`);
  if (path !== '.git') {
    const attrRules = attributes.split(/\r?\n/).filter((line) => line.endsWith(' export-ignore'))
      .map((line) => line.split(' ')[0].replace(/^\//, ''));
    assert(attrRules.some((rule) => new RegExp(`^${rule.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')}$`).test(path)),
      `${path} must be excluded by .gitattributes`);
  }
}
const temp = mkdtempSync(join(tmpdir(), 'indexnow-package-'));
const files = [];
function copyDirectory(relative = '') {
  for (const entry of readdirSync(join(root, relative), { withFileTypes: true })) {
    const path = relative ? `${relative}/${entry.name}` : entry.name;
    if (excluded(path)) continue;
    assert(!entry.isSymbolicLink(), `Refusing to package symlink: ${path}`);
    if (entry.isDirectory()) copyDirectory(path);
    else {
      const destination = join(temp, slug, path);
      mkdirSync(dirname(destination), { recursive: true });
      cpSync(join(root, path), destination);
      files.push(path);
    }
  }
}
try {
  copyDirectory();
  assert.deepEqual(files.sort(), ['index.php', 'license.txt', `${slug}.php`, 'readme.txt'].sort(),
    'Unexpected release contents: review exclusions before shipping');
  const plugin = readFileSync(join(root, `${slug}.php`), 'utf8');
  const readme = readFileSync(join(root, 'readme.txt'), 'utf8');
  assert.equal(plugin.match(/\* Version:\s*(\S+)/)?.[1], readme.match(/^Stable tag:\s*(\S+)/m)?.[1]);
  mkdirSync(join(root, 'dist'), { recursive: true });
  // Mount only shipped files in the test environment, not the source checkout.
  const stagedPlugin = join(root, 'dist', slug);
  // Preserve the directory inode: Docker bind-mounts may already refer to it.
  mkdirSync(stagedPlugin, { recursive: true });
  for (const entry of readdirSync(stagedPlugin)) {
    rmSync(join(stagedPlugin, entry), { recursive: true, force: true });
  }
  cpSync(join(temp, slug), stagedPlugin, { recursive: true });
  const zip = join(root, 'dist', `${slug}.zip`);
  // Remove a previous ZIP so deleted files cannot survive as stale archive entries.
  rmSync(zip, { force: true });
  execFileSync('zip', ['-qr', zip, slug], { cwd: temp });
  const entries = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' }).trim().split('\n')
    .filter((path) => !path.endsWith('/')).sort();
  assert.deepEqual(entries, files.map((path) => `${slug}/${path}`).sort());
  console.log(`PASS: release ZIP contains only ${files.length} runtime files; versions and exclusions match.`);
  console.log(`Built ${zip}`);
} finally {
  rmSync(temp, { recursive: true, force: true });
}
