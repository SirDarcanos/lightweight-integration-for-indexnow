#!/usr/bin/env node
/**
 * wp-env 11.16 uses simple-git's pre-v4 callable CommonJS export.
 * Keep the security-fixed v4 dependency and adapt that export in this process.
 * No installed dependency files are modified.
 */
const path = require('node:path');
const gitPath = require.resolve('simple-git');
const git = require(gitPath);
if (typeof git !== 'function') {
  if (typeof git.simpleGit !== 'function') {
    throw new Error('Unsupported simple-git export; review the wp-env compatibility adapter.');
  }
  require.cache[gitPath].exports = Object.assign(git.simpleGit, git);
}
const envPackage = require.resolve('@wordpress/env/package.json');
require(path.join(path.dirname(envPackage), 'bin/wp-env'));
