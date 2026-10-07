# Plugin verification record

This records local verification against the [Plugin Handbook](https://developer.wordpress.org/plugins/), [submission requirements](https://wordpress.org/plugins/developers/add/), and [18 Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). It is not WordPress.org approval or a certification of legal compliance.

## Executed checks

| Check | Environment / result |
| --- | --- |
| Functional tests | WordPress 7.1.3 / PHP 8.3.35: **20 passed, 0 failed** against staged release files. |
| Minimum-version tests | WordPress 6.0 / PHP 7.4.33: **20 passed, 0 failed**. Extra environment stopped afterward. |
| Official Plugin Check 2.1.0 | **No errors or warnings** across general, plugin repository, security, performance, and accessibility categories, including runtime checks, at severity 1. |
| Release ZIP | Exactly four files: main plugin PHP, `index.php`, `readme.txt`, and `license.txt`. No dependencies, tests, configs, repository metadata, logs, or secrets. |
| Dependency audit | `npm audit`: **0 vulnerabilities** with the pinned lockfile and documented overrides. |
| PHP syntax | Main plugin, `index.php`, and functional test file passed `php -l`. |
| Administrator settings | Real browser login, settings save, reload/persistence, and restoration passed. |
| CSRF protection | Missing and invalid settings nonces returned **403**; rejected requests left the key unchanged. |
| Authorization | Temporary subscriber received **403** for settings access and update, with an explicit capability denial. Subscriber removed afterward. |
| Output safety | Functional checks verified escaping of a raw HTML-containing key and error notice, and administrator-only notice consumption. |
| Ignores and docs | Git ignore checks passed for dependencies, generated artifacts, secrets, task logs, and development/test/minimum personal overrides. Local documentation links and `git diff --check` passed. |

Plugin Check can exit zero while reporting errors. The test command now uses a fail-closed wrapper requiring the explicit clean-run message. It was verified to fail on the old `Tested up to: 6.9` finding and pass after actual testing supported updating it to `7.1`.

## Issues corrected

- Defined the site's hostname before constructing an IndexNow payload; previously requests referenced an undefined `$host`.
- Normalized IPv6 brackets so `http://[::1]` is treated as localhost.
- Added the declared WordPress 6.0 / PHP 7.4 minimums to the main plugin header and used the project URL as `Plugin URI`.
- Documented the external service, transmitted fields, request timing, disabling submissions, and service terms in `readme.txt`.
- Corrected instructions that implied a generated API key alone was sufficient: the plugin does not create or serve the verification file.
- Added wp-env, isolated functional tests, release exclusions, package checks, and a clean Plugin Check gate.

## Directory guideline review

| # | Review | Evidence / remaining responsibility |
| --- | --- | --- |
| 1 | GPL compatibility | Main header declares `GPL-2.0+`, `readme.txt` declares GPLv2 or later, and `license.txt` contains GPLv2. No third-party runtime libraries are bundled. Maintainer must retain rights to any directory assets. |
| 2 | Developer responsibility | No circumvention or hidden payload found in the reviewed source. Legal ownership and service-term compliance remain the maintainer's responsibility. |
| 3 | Stable directory version | Release workflow exists; no WordPress.org publication or comparison with an existing SVN release was performed. |
| 4 | Readable source | Runtime is readable PHP, with no obfuscation, minified assets, or build-only executable code. |
| 5 | No trialware | No payment, license-subscription, quota, or expiry gates. The IndexNow key is for service verification, not paid access. |
| 6 | Genuine service integration | IndexNow provides content-change notifications to search engines. Endpoint, behavior, transmitted data, and service terms are disclosed in `readme.txt`. |
| 7 | Consent / tracking | No analytics, visitor tracking, or telemetry code found. Requests implement the named service integration; activation itself makes no request. The service exception is documented in the official guideline. Reviewer interpretation of consent remains authoritative. |
| 8 | Remote executable code | No remote executable loads, external self-updater, or remote installation path in plugin runtime. Development tool downloads are not shipped. |
| 9 | Honest behavior | No review manipulation, hidden SEO output, resource abuse, or fabricated legal-compliance claim found. |
| 10 | Forced links | No front-end output or forced visitor-facing backlinks. |
| 11 | Admin experience | Native Settings API fields; error notice is escaped, dismissible, administrator-only, and consumed once. No ads, upsells, or external admin iframe. |
| 12 | Readme quality | Five relevant tags, matching plugin name, service disclosure, and no affiliate links or competitor-tag stuffing found. |
| 13 | Core libraries | Uses WordPress APIs; no bundled duplicate core libraries. |
| 14 | SVN release discipline | Existing deployment is manual, version-tag based, and defaults to dry run. No SVN deployment was performed. |
| 15 | Versioning | Plugin version and `Stable tag` both remain `1.0.0`; fixes are documented under `Unreleased`. Before a subsequent published functional release, increment both together and use a new tag. |
| 16 | Complete plugin | Local activation, submission hooks, settings, and errors pass functional checks. Live ownership verification and search-engine acceptance are not established by mocked HTTP tests. |
| 17 | Naming / trademarks | Name, slug, and text domain agree; “IndexNow” follows “for.” Plugin explicitly disclaims affiliation. Final trademark/name approval belongs to WordPress.org. |
| 18 | Directory authority | WordPress.org retains review and removal discretion; local checks do not override it. |

## Scope limits

No plugin was submitted or deployed. No content or key was sent to IndexNow during functional testing. Those tests intercept all HTTP, and the browser tests touched only the local settings screens.

Product-type hooks were tested with a registered `product` post type, not a complete WooCommerce installation. Multisite, real search-engine indexing/acceptance, and a live public verification file were not tested. Public-site credentials, directory asset provenance, and release history require maintainer review before submission.

Development and isolated test sites remain running on ports 8888 and 8889. The minimum-version site on port 8890 was stopped after testing. Use [testing.md](testing.md) to repeat the checks after changes; WordPress and Plugin Check stable downloads may advance over time.
