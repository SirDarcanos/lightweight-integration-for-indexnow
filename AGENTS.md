# Agent instructions

## Project and layout

Lightweight Integration for IndexNow is a single-file WordPress plugin. It sends content-change notifications for posts, pages, and WooCommerce products through the WordPress HTTP API. Runtime supports WordPress 6.0+ and PHP 7.4+; Node tooling is development-only.

- `lightweight-integration-for-indexnow.php`: bootstrap, activation, save/trash hooks, URL collection, HTTP submission, and Settings API fields. Public functions, hooks, and options use the `nm_indexnow_` prefix.
- `readme.txt`: WordPress.org metadata, user instructions, hook reference, and changelog. `README.md` covers local development.
- `tests/integration.php`: real-WordPress functional checks, run through WP-CLI; no PHPUnit setup.
- `scripts/`: wp-env compatibility adapter, release packaging assertions, and Plugin Check gate.
- `.wordpress-org/`: directory assets, deployed separately from the plugin ZIP.

## Setup and development

Requires running Docker, Node.js 20.19+ (prefer current LTS), npm, Git, and `zip` / `unzip`. From the repository root:

```sh
npm ci
npm run env:start
npm run env:status
```

Development runs on `http://localhost:8888`; isolated tests on `http://localhost:8889`. Local admin credentials are `admin` / `password`; keep these environments local. wp-env provisions the databases and activates the plugin automatically.

The development site mounts source directly; no watcher or asset build is needed. The test site mounts staged release files, so rebuild through the test scripts after edits. Use `npm run env:cli -- <wp-command>` for development and `npm run env:test:cli -- <wp-command>` for tests; for example, `npm run env:test:cli -- plugin list`.

Use npm scripts or `node scripts/wp-env.cjs` for wp-env commands. The adapter preserves security-fixed simple-git v4 while satisfying wp-env's older export API; direct `npx wp-env` bypasses it. When changing dependency overrides, regenerate `package-lock.json` and verify startup, tests, and `npm audit`.

Personal configuration belongs in `.wp-env.override.json`, `.wp-env.tests.override.json`, or `.wp-env.minimum.override.json`. These are Git-ignored; plugin arrays replace rather than merge. Preserve each environment's intended plugin and test mappings.

## Testing and completion gate

```sh
npm test                     # Package assertions, functional tests, all Plugin Check categories
npm run test:minimum         # WordPress 6.0 / PHP 7.4 functional tests; starts port 8890
npm audit
```

For focused work, run `npm run test:package`, `npm run test:integration`, or `npm run test:plugin-check`. There is no separate lint, coverage, or automated browser-test script. For PHP changes also run `php -l lightweight-integration-for-indexnow.php` and `php -l tests/integration.php`; check whitespace with `git diff --check`.

- For runtime changes, add regression checks to `tests/integration.php`, then require both the main and minimum-version suites to pass. Keep fixtures disposable and cleanup in `finally` blocks.
- Tests intercept outbound HTTP. Preserve that interception and the `NM_INDEXNOW_TEST_ENV` guard; use only isolated test databases and synthetic content/keys.
- Plugin Check scans staged release contents, enables runtime checks, and fails closed on findings. Keep `scripts/check-plugin.mjs` as the gate: the underlying CLI can exit zero while reporting errors.
- For docs or packaging changes, require package assertions and valid local links; run the full suite when shipped files or test tooling change.
- Report commands, results, and blocked checks. A clean local suite does not establish live IndexNow acceptance, full WooCommerce behavior, multisite support, or WordPress.org approval.

For settings changes, follow the browser nonce/capability and persistence checks in [docs/testing.md](docs/testing.md). For submission or release reviews, read [docs/plugin-review.md](docs/plugin-review.md) for the recorded guideline review and scope limits; rerun relevant checks rather than treating that record as current proof.

## Code style and security

Follow the surrounding file's conventions: PHP uses tabs, WordPress spacing, `array()` syntax, PHPDoc, and `nm_indexnow_` names; Node scripts use built-in modules, two spaces, single quotes, and semicolons (`.mjs` except the CommonJS adapter). Keep runtime PHP compatible with 7.4 and APIs compatible with WordPress 6.0. Preserve public hook names and callback arguments unless an explicitly approved API change requires otherwise.

Use WordPress APIs for settings, URLs, HTTP, translation, and storage. Sanitize input, escape output for its context, and pair nonce protection with capability checks. Existing settings use the core `general` Settings API group; core `options.php` enforces the save nonce and `manage_options` capability.

Keep the plugin minimal: no front-end output or JS/CSS, and non-blocking requests by default. A dispatched request is not proof of API acceptance. The generated API key does not create or serve its public verification file. Document any change to external-service data sharing in `readme.txt`.

Keep secrets and real API keys out of tracked files, logs, fixtures, and packages. Production mutation, live service testing, WordPress.org submission, and SVN deployment require explicit user authorization.

## Packaging and releases

`npm run test:package` builds `dist/lightweight-integration-for-indexnow.zip` and refreshes its staging directory. The package allowlist is exactly four files: main plugin PHP, `index.php`, `readme.txt`, and `license.txt`. For a deliberate runtime-file addition, update the assertion and review exclusions together.

Keep `.distignore` and `.gitattributes` aligned: development files, including this `AGENTS.md`, must be excluded from plugin ZIPs and Git exports. Preserve the staging directory inode when rebuilding because Docker bind-mounts refer to it.

Both workflows in `.github/workflows/` are manual `workflow_dispatch`; there is no automatic PR test workflow. The build workflow packages the plugin. SVN deployment defaults to dry run and requires an approved repository, an unused version tag on `main`, matching plugin `Version` / readme `Stable tag`, and the configured `wordpress-org` environment. For release work, read `.github/workflows/deploy-svn.yml` before executing anything; live credentials come from the configured 1Password Environment, not local files.

Record pending user-facing changes under `Unreleased` in `readme.txt`. For a subsequent published functional release, bump plugin `Version` and `Stable tag` together and use a new tag. Set `Tested up to` only after verifying that WordPress release. `Contributors: nicolamustone` is the WordPress.org username field; the human author name may remain Nicola Mustone.

## Git workflow and troubleshooting

Work on a short-lived branch from current `main`, then open a PR into `main`. Active rules require PRs with zero approvals and no bypasses; direct pushes to `main` are blocked. Existing agent commits use Conventional Commit prefixes such as `docs:`, `fix:`, and `chore:`. Commit, push, merge, or deploy only when authorized for the current task.

Use `npm run env:stop` for development/tests and `npm run env:minimum:stop` for the extra minimum-version environment. Inspect logs with `node scripts/wp-env.cjs logs --no-watch` or `node scripts/wp-env.cjs --config .wp-env.tests.json logs --no-watch`. If a branch switch leaves `wp-content/indexnow-tests/integration.php` missing inside Docker while `tests/integration.php` exists on the host, refresh the test containers with `node scripts/wp-env.cjs --config .wp-env.tests.json start --update`; stale bind mounts can retain a deleted directory inode. This refresh may download newer stable sources but does not intentionally reset the database. Resolve port conflicts through local overrides rather than stopping unrelated containers. Database resets and environment destruction require confirmation because they discard data.
