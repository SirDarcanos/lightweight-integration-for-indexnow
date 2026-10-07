# Local verification and submission checks

See [plugin-review.md](plugin-review.md) for the executed verification results, guideline review, and remaining scope limits.

## Start the environment

Requires Docker running, Node.js 20.19+ (use a current LTS release), npm, Git, and the `zip` / `unzip` commands.

```sh
npm ci
npm run env:start
npm run env:status
```

`.wp-env.json` serves development WordPress on port 8888. `.wp-env.tests.json` serves an isolated WordPress database on port 8889 and installs the official Plugin Check plugin. The development site mounts this checkout; the test site mounts the four release files staged under `dist/lightweight-integration-for-indexnow/`, with test fixtures mapped separately outside the plugin. Both use the latest stable WordPress and PHP 8.3 and enable debug logging without displaying errors to visitors.

The default local administrator is `admin` / `password`. These environments are for local use only. Do not expose them publicly or use production databases, credentials, or API keys.

Use `.wp-env.override.json` for personal development settings, `.wp-env.tests.override.json` for test settings, or `.wp-env.minimum.override.json` for minimum-version settings. All are ignored by Git and excluded from both ZIPs and Git exports. wp-env overrides replace plugin arrays, so preserve the local plugin when customizing them. Environment data normally lives under `~/.wp-env`, outside this checkout.

## Run checks

```sh
npm test
npm run test:minimum
npm audit
```

| Command | Scope |
| --- | --- |
| `npm run test:package` | Builds `dist/lightweight-integration-for-indexnow.zip`; asserts it contains only the plugin PHP file, `index.php`, `license.txt`, and `readme.txt`, with matching release versions. Verifies development exclusions for ZIP and Git archive paths. |
| `npm run test:integration` | Runs functional PHP checks against the isolated WordPress database. Covers activation/key preservation, settings sanitizers, escaped output, administrator-only notices, published posts/pages/product-type records, drafts, revisions/autosaves, taxonomy URLs, trash, filters/actions, payloads, and transport errors. |
| `npm run test:plugin-check` | Rebuilds the ZIP and scans its staged contents as an installed plugin using official Plugin Check, including its runtime checks through `--require=.../plugin-check/cli.php`. All five categories run at severity 1 with no deliberately suppressed checks, errors, or warnings. `scripts/check-plugin.mjs` fails unless Plugin Check explicitly reports no findings, because Plugin Check can exit zero while printing errors. Plugin Check's CLI cannot initialize runtime checks directly from a ZIP path, so the installed staging directory is used instead. |
| `npm run test:minimum` | Starts `.wp-env.minimum.json` on port 8890, then runs functional tests on the declared minimum WordPress 6.0 / PHP 7.4 combination. Plugin Check is not installed in this older environment. |
| `npm audit` | Checks the development dependency lockfile for known vulnerabilities. These packages are not shipped in the plugin. |

The functional suite intercepts all HTTP requests, changes the site hostname only through a process-local filter, creates disposable posts/terms, and restores the original plugin options and user afterward. It refuses to run unless `NM_INDEXNOW_TEST_ENV` is enabled in the environment configuration. Tests do not verify acceptance by a real IndexNow endpoint.

The product test registers a `product` post type; it tests the plugin's integration hooks, not the entire WooCommerce checkout/catalog lifecycle. WordPress.org approval, live IndexNow verification, real WooCommerce integration, and multisite need separate verification when relevant.

## Admin and security checks

On the isolated test site:

1. Log in as administrator and open **Settings → General → IndexNow**.
2. Save a different API key, reload, and verify persistence; restore the original afterward.
3. Verify an authenticated settings POST with a missing/invalid nonce is rejected, and an account without `manage_options` cannot access or update the settings.
4. Confirm an HTML-containing key or error message is escaped on output.
5. Leave the key-file checkbox unchecked unless a matching public verification file exists. The plugin does not create that file.

The plugin registers its options in the core `general` Settings API group. WordPress's `options.php` supplies the nonce and capability enforcement; the plugin does not implement a separate settings POST handler.

## Submission review

Use these primary sources:

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Submission page](https://wordpress.org/plugins/developers/add/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Header requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Official Plugin Check](https://wordpress.org/plugins/plugin-check/)

Before submitting the ZIP:

- Verify GPL-compatible licensing for code and directory assets, with matching license headers and license text.
- Keep plugin name, slug, text domain, minimum versions, plugin version, and `Stable tag` consistent.
- Document the IndexNow service, the transmitted data, when requests happen, how to disable them, and the service terms in `readme.txt`.
- Check nonce/capability enforcement, input sanitization, and context-appropriate output escaping.
- Exclude dependencies, test fixtures, local configuration, secrets, logs, and repository metadata from the package.
- Confirm there is no obfuscated code, remote executable code, telemetry, forced front-end links, trialware, or bundled copy of a core library.
- Keep releases in SVN; do development in Git. Publish a new version for subsequent functional releases.
- Run checks again after changes, and resolve or explicitly record every remaining finding. Automated tools cannot prove all 18 guidelines or guarantee approval.

## Stop and troubleshoot

```sh
npm run env:stop
npm run env:minimum:stop
node scripts/wp-env.cjs logs --no-watch
node scripts/wp-env.cjs --config .wp-env.tests.json logs --no-watch
```

If Docker is unavailable, start Docker Desktop before retrying. If ports are occupied, adjust the configurations or use local overrides; do not stop unrelated containers. Use `node scripts/wp-env.cjs start --update` (and the equivalent test-config command) to refresh downloaded sources intentionally. Database resets and `destroy` are destructive; use them only when you intend to discard the local environment's data.

The lockfile pins wp-env. `package.json` also overrides vulnerable transitive versions of `simple-git`, `js-yaml`, and `qs`. The npm commands use `scripts/wp-env.cjs` to adapt simple-git v4's named export to the callable export expected by wp-env 11.16; the adapter changes only the current process's module cache, not dependency files. Use the npm commands or this wrapper instead of invoking `npx wp-env` directly. Re-run environment startup, the tests, and `npm audit` when changing these overrides. WordPress and Plugin Check downloads intentionally track their stable releases, so record the versions used when preparing a release.
