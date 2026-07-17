# Mumega Motion Update Channel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build verified GitHub edge packages and let an admin-scoped MCPWP tool explicitly update or roll back the active Mumega Motion theme.

**Architecture:** The existing public theme repository owns builds, immutable prereleases, update discovery, package validation, backups, installation, rollback, and verification. WordPress's upgrader and Filesystem APIs perform installation; MCPWP exposes only fixed-purpose admin tools with no caller-controlled URL, slug, package, or checksum.

**Tech Stack:** WordPress 6.5+, PHP 7.4+, WordPress HTTP/Filesystem/Upgrader APIs, GitHub Actions and Releases, PHPUnit 9.6, Node.js, `@wordpress/scripts`.

**Repository:** `/Users/hadi/dev/mumega/mumcp/mumega-motion-theme`

**Dependency:** MCPWP must include the raise-only `mcpwp_required_scope_for_tool` filter from `2026-07-16-mcpwp-custom-tool-admin-scope.md` before the live MCP update and rollback tools are enabled.

---

## File map

**Create:**

- `composer.json`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/ReleaseClientTest.php`
- `tests/PackageValidatorTest.php`
- `tests/BackupStoreTest.php`
- `tests/UpdaterTest.php`
- `tests/UpdateApiTest.php`
- `inc/updates/class-mumega-motion-release-client.php`
- `inc/updates/class-mumega-motion-package-validator.php`
- `inc/updates/class-mumega-motion-backup-store.php`
- `inc/updates/class-mumega-motion-updater.php`
- `inc/updates/class-mumega-motion-update-api.php`
- `inc/updates/bootstrap.php`
- `scripts/package-theme.sh`
- `.github/workflows/edge-release.yml`

**Modify:**

- `.gitignore`
- `style.css`
- `functions.php`
- `README.md`

## Task 1: Establish the PHP test harness

**Files:**

- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Modify: `.gitignore`

- [ ] Confirm the approved design branch and clean starting state.

```bash
cd /Users/hadi/dev/mumega/mumcp/mumega-motion-theme
git switch feat/mcp-triggered-updates
git status --short --branch
```

- [ ] Add Composer development tooling compatible with PHP 7.4.

```json
{
  "name": "mumega/mumega-motion-theme",
  "description": "Mumega Motion WordPress theme",
  "type": "wordpress-theme",
  "require-dev": {
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
    "phpunit/phpunit": "^9.6",
    "squizlabs/php_codesniffer": "^3.10",
    "wp-coding-standards/wpcs": "^3.0"
  },
  "scripts": {
    "test": "phpunit -c phpunit.xml.dist",
    "lint:php": "phpcs --standard=WordPress inc functions.php tests"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  }
}
```

- [ ] Configure PHPUnit to load `tests/bootstrap.php`, use colors, fail on warnings and risky tests, and discover files ending in `Test.php` under `tests/`.

- [ ] In `tests/bootstrap.php`, define `ABSPATH`, load lightweight WordPress stubs needed by the update classes, capture registered filters/actions/routes/tools in globals, and require update classes only after their direct dependencies exist. Each stub must use the same argument order and return shape as the corresponding WordPress function.

- [ ] Add `vendor/`, `.phpunit.result.cache`, `dist/`, `*.zip`, and generated package manifests to `.gitignore` without removing existing entries.

- [ ] Install dependencies and run the empty test suite.

```bash
cd /Users/hadi/dev/mumega/mumcp/mumega-motion-theme
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```

Expected: PHPUnit starts successfully and reports no tests, with no bootstrap fatal error.

- [ ] Commit the harness.

```bash
git add composer.json composer.lock phpunit.xml.dist tests/bootstrap.php .gitignore
git commit -m "test: add theme update test harness"
```

## Task 2: Discover and validate the latest immutable release

**Files:**

- Create: `tests/ReleaseClientTest.php`
- Create: `inc/updates/class-mumega-motion-release-client.php`

- [ ] Write tests for `Mumega_Motion_Release_Client::latest( $force = false )`. Cover:

  - selection of the highest semantic version among non-draft prereleases whose tag begins `edge-v`;
  - rejection of release assets whose download URL is not on `github.com/Mumega-com/mumega-motion-theme/releases/download/`;
  - rejection when `manifest.json` is absent;
  - rejection of a manifest with a different slug, invalid semantic version, malformed 64-character SHA-256, incompatible PHP requirement, or incompatible WordPress requirement;
  - use of the cached normalized manifest for 15 minutes;
  - `$force = true` deleting the site transient and making fresh HTTP requests;
  - conversion of transport errors, non-200 responses, and invalid JSON to stable `WP_Error` codes.

- [ ] Use fixture arrays in the test file with these fixed repository constants:

```php
private const REPOSITORY = 'Mumega-com/mumega-motion-theme';
private const SLUG       = 'mumega-motion-theme';
private const API_URL    = 'https://api.github.com/repos/Mumega-com/mumega-motion-theme/releases?per_page=10';
```

- [ ] Run the test and confirm it fails because the class does not exist.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/ReleaseClientTest.php
```

- [ ] Implement the release client with constants for the repository, theme slug, API URL, transient key, and 900-second TTL. Use `wp_safe_remote_get()` with a bounded timeout and a GitHub-compatible User-Agent. Do not accept repository, slug, or URL as constructor or method arguments.

- [ ] Normalize the successful return value to this contract:

```php
array(
	'slug'             => 'mumega-motion-theme',
	'version'          => '0.1.123',
	'package_url'      => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.123/mumega-motion-theme-0.1.123.zip',
	'sha256'           => '64-lowercase-hex-characters',
	'requires_wp'      => '6.5',
	'requires_php'     => '7.4',
	'release_tag'      => 'edge-v0.1.123',
	'published_at'     => '2026-07-16T12:00:00Z',
	'manifest_url'     => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.123/manifest.json',
)
```

- [ ] Run the release-client tests until green, then lint the class under PHP 7.4 and the local PHP version.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/ReleaseClientTest.php
php -l inc/updates/class-mumega-motion-release-client.php
docker run --rm -v "$PWD:/app" -w /app php:7.4-cli php -l inc/updates/class-mumega-motion-release-client.php
```

- [ ] Commit.

```bash
git add tests/ReleaseClientTest.php inc/updates/class-mumega-motion-release-client.php tests/bootstrap.php
git commit -m "feat: discover verified theme edge releases"
```

## Task 3: Validate downloaded theme packages before installation

**Files:**

- Create: `tests/PackageValidatorTest.php`
- Create: `inc/updates/class-mumega-motion-package-validator.php`

- [ ] Write table-driven tests for `Mumega_Motion_Package_Validator::validate( $zip_path, array $manifest )`. The valid fixture must contain exactly one root directory named `mumega-motion-theme/` and these required non-empty files:

```text
mumega-motion-theme/style.css
mumega-motion-theme/functions.php
mumega-motion-theme/index.php
mumega-motion-theme/build/index.js
mumega-motion-theme/build/index.asset.php
```

- [ ] Add rejection cases for:

  - a SHA-256 mismatch;
  - an archive larger than 20 MiB;
  - multiple root directories;
  - a root directory with the wrong slug;
  - an absent or empty required file;
  - `../` traversal, absolute paths, Windows backslash paths, and null bytes;
  - symlink entries based on ZIP external attributes;
  - unreadable, corrupt, or empty archives.

- [ ] Create ZIP fixtures inside each test's temporary directory and remove them in `tearDown()` so no binary fixtures enter Git.

- [ ] Run the test and confirm it fails because the validator class does not exist.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/PackageValidatorTest.php
```

- [ ] Implement the validator with `ZipArchive`, `hash_file( 'sha256', $zip_path )`, `filesize()`, and entry-by-entry inspection. Return `true` on success and a `WP_Error` with a stable code on every rejection path. Never extract the archive during validation.

- [ ] Run focused tests and lint on PHP 7.4 and the local PHP version.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/PackageValidatorTest.php
php -l inc/updates/class-mumega-motion-package-validator.php
docker run --rm -v "$PWD:/app" -w /app php:7.4-cli php -l inc/updates/class-mumega-motion-package-validator.php
```

- [ ] Commit.

```bash
git add tests/PackageValidatorTest.php inc/updates/class-mumega-motion-package-validator.php tests/bootstrap.php
git commit -m "feat: validate theme update packages"
```

## Task 4: Create protected local backups and bounded retention

**Files:**

- Create: `tests/BackupStoreTest.php`
- Create: `inc/updates/class-mumega-motion-backup-store.php`

- [ ] Write tests for these methods:

```php
public function create( string $theme_directory, string $version );
public function restore( string $backup_id, string $theme_directory );
public function latest();
public function prune( int $keep = 3 );
```

- [ ] Cover successful copy and restore, random non-guessable backup IDs, metadata round-trip, rejection of unknown IDs, rejection of malformed metadata, cleanup after a partial copy failure, and pruning oldest backups while retaining the newest three.

- [ ] Assert that the store resolves beneath `wp_upload_dir()['basedir'] . '/mumega-motion-backups'`, creates an empty `index.php`, and writes a deny-all `.htaccess`. Do not expose backup paths or IDs through unauthenticated output.

- [ ] Run the test and confirm it fails because the backup store does not exist.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/BackupStoreTest.php
```

- [ ] Implement backup IDs with `bin2hex( random_bytes( 16 ) )`. Use `wp_mkdir_p()`, WordPress `copy_dir()` for directory copies, atomic metadata writes through a temporary file plus `rename()`, and recursive cleanup through WordPress filesystem helpers.

- [ ] Make `restore()` copy the backup into a sibling temporary directory, validate required theme files there, rename the current theme aside, rename the restored directory into place, and remove the displaced directory only after success. Restore the displaced directory if either rename fails.

- [ ] Run focused tests and lint.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/BackupStoreTest.php
php -l inc/updates/class-mumega-motion-backup-store.php
```

- [ ] Commit.

```bash
git add tests/BackupStoreTest.php inc/updates/class-mumega-motion-backup-store.php tests/bootstrap.php
git commit -m "feat: add bounded theme update backups"
```

## Task 5: Implement the update and automatic recovery transaction

**Files:**

- Create: `tests/UpdaterTest.php`
- Create: `inc/updates/class-mumega-motion-updater.php`

- [ ] Write updater tests with injected collaborators for release discovery, download, validation, backup, install, and post-install inspection. Cover:

  - no-op when the latest release is not newer than the installed version;
  - successful download, validation, backup, overwrite install, and verification;
  - download, validation, backup, and installer failures;
  - installed-version mismatch after an apparently successful install;
  - inactive theme or missing required files after install;
  - automatic restore after every failure that occurs after backup creation;
  - a distinct error when both update and automatic restore fail;
  - deletion of temporary downloads on every exit path;
  - pruning only after a verified successful update.

- [ ] Define the successful update result contract in the test:

```php
array(
	'status'          => 'updated',
	'previous_version'=> '0.1.100',
	'current_version' => '0.1.101',
	'release_tag'     => 'edge-v0.1.101',
	'backup_id'       => '32-lowercase-hex-characters',
	'checksum'        => '64-lowercase-hex-characters',
	'verified'        => true,
)
```

- [ ] Run the test and confirm it fails because the updater does not exist.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/UpdaterTest.php
```

- [ ] Implement `update( $force_check = true )` as a transaction:

  1. Read the active theme slug and installed version; reject any slug other than `mumega-motion-theme`.
  2. Discover the fixed repository release and reject a non-newer version.
  3. Download through `download_url()` to a temporary local file.
  4. Validate the archive and checksum before changing WordPress files.
  5. Create the bounded local backup.
  6. Load the WordPress upgrader includes and install with `Theme_Upgrader` in overwrite mode.
  7. Flush theme caches and inspect the installed theme again.
  8. Verify exact version, active stylesheet, and required files.
  9. On failure after step 5, restore the backup and return evidence for both operations.
  10. On success, prune old backups and return the structured result.

- [ ] Implement `rollback()` using only `Backup_Store::latest()`. It must create a safety backup of the current version, restore the newest prior backup, verify the active theme and restored version, and restore the safety backup if verification fails. The caller cannot choose a path or arbitrary backup ID.

- [ ] Run focused tests and lint.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/UpdaterTest.php
php -l inc/updates/class-mumega-motion-updater.php
```

- [ ] Commit.

```bash
git add tests/UpdaterTest.php inc/updates/class-mumega-motion-updater.php tests/bootstrap.php
git commit -m "feat: add verified theme update transaction"
```

## Task 6: Expose fixed-purpose REST and MCPWP admin operations

**Files:**

- Create: `tests/UpdateApiTest.php`
- Create: `inc/updates/class-mumega-motion-update-api.php`
- Create: `inc/updates/bootstrap.php`
- Modify: `functions.php`
- Modify: `style.css`

- [ ] Write API tests asserting registration of exactly these REST routes:

```text
POST /mumega-motion/v1/update
POST /mumega-motion/v1/rollback
```

- [ ] Assert both REST routes use a permission callback that returns `current_user_can( 'update_themes' )`, accept no package URL, slug, checksum, or backup path, and delegate only to the updater's fixed `update()` and `rollback()` operations.

- [ ] Write tool-registration tests asserting exactly these MCPWP tool names and properties:

```text
wp_update_mumega_motion
wp_rollback_mumega_motion
```

Both tools must use category `admin`, set `destructiveHint` to `true`, and advertise no arbitrary update source. Update sets `openWorldHint` to `true`; rollback sets it to `false`.

- [ ] Assert the theme adds `mcpwp_required_scope_for_tool` and raises only those two exact names to `admin`, leaving every other tool's inferred scope unchanged.

- [ ] Assert tool registration is skipped when MCPWP's registration hook is unavailable or `MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER` is not defined and `true`. Pass that feature-detection result into the API class from the production bootstrap so unit tests can cover both states. The WordPress dashboard updater must remain available as a fallback.

- [ ] Run the API test and confirm it fails before implementation.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/UpdateApiTest.php
```

- [ ] Implement the REST controller, MCPWP registration, admin-scope filter, and bootstrap wiring. Sanitize booleans only; do not add input fields for source selection.

- [ ] Add native WordPress theme update discovery through `pre_set_site_transient_update_themes` and package metadata through `themes_api`. Use the same release client, 15-minute cache, exact slug, fixed repository URL, and verified manifest package URL.

- [ ] Add the update-system bootstrap to `functions.php` and this header to `style.css`:

```css
Update URI: https://github.com/Mumega-com/mumega-motion-theme
```

- [ ] Run focused tests and PHP lint on all update files.

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/UpdateApiTest.php
find inc/updates -name '*.php' -print0 | xargs -0 -n1 php -l
php -l functions.php
```

- [ ] Commit.

```bash
git add tests/UpdateApiTest.php inc/updates functions.php style.css tests/bootstrap.php
git commit -m "feat: expose admin-scoped theme update tools"
```

## Task 7: Build deterministic runtime packages and immutable edge releases

**Files:**

- Create: `scripts/package-theme.sh`
- Create: `.github/workflows/edge-release.yml`
- Modify: `package.json`
- Modify: `README.md`

- [ ] Write `scripts/package-theme.sh VERSION` with `set -euo pipefail`. Validate `VERSION` against `^[0-9]+\.[0-9]+\.[0-9]+$`, create a clean staging directory, and copy only the runtime allowlist:

```text
style.css
functions.php
index.php
stream-demo.php
build/
inc/
```

- [ ] Patch only the staged `Version:` header, never the checked-in `style.css`. Remove test files, source maps, documentation, Composer tooling, Node tooling, Git metadata, workflow files, and local artifacts from the staged tree.

- [ ] Create `dist/mumega-motion-theme-VERSION.zip` with one `mumega-motion-theme/` root. Generate the SHA-256 and `dist/manifest.json` with exactly these keys:

```json
{
  "slug": "mumega-motion-theme",
  "version": "0.1.123",
  "package_url": "https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.123/mumega-motion-theme-0.1.123.zip",
  "sha256": "64-lowercase-hex-characters",
  "requires_wp": "6.5",
  "requires_php": "7.4"
}
```

- [ ] Add an npm script that runs the packager after the production JavaScript build.

- [ ] Test packaging twice with the same version and source tree. Compare sorted archive listings, extracted files, staged version, manifest fields, and checksums. If ZIP timestamps prevent byte-for-byte identity, normalize timestamps in the staging tree before archive creation.

```bash
npm ci
npm run build
./scripts/package-theme.sh 0.1.999
cp dist/mumega-motion-theme-0.1.999.zip /tmp/mumega-motion-first.zip
./scripts/package-theme.sh 0.1.999
cmp /tmp/mumega-motion-first.zip dist/mumega-motion-theme-0.1.999.zip
unzip -l dist/mumega-motion-theme-0.1.999.zip
unzip -p dist/mumega-motion-theme-0.1.999.zip mumega-motion-theme/style.css | grep '^Version: 0.1.999$'
```

- [ ] Create `.github/workflows/edge-release.yml` triggered by pushes to `master` and manual dispatch. It must:

  1. check out the exact commit;
  2. derive `VERSION=0.1.${GITHUB_RUN_NUMBER}` and `TAG=edge-v${VERSION}`;
  3. run `npm ci` and `npm run build`;
  4. run JavaScript syntax checks;
  5. install Composer dependencies;
  6. install the ZIP extension and run PHPUnit plus PHP lint on PHP 7.4 and PHP 8.3 matrix jobs;
  7. run the packaging script once tests pass;
  8. verify the archive root and required runtime files;
  9. create an immutable GitHub prerelease for the exact commit;
  10. upload the ZIP, `manifest.json`, and SHA-256 file;
  11. fail if the tag already exists rather than replacing release assets.

- [ ] Document the edge version convention, dashboard fallback, explicit MCP workflow, backup retention, and one-time bridge-install requirement in `README.md`.

- [ ] Run local build, PHP tests, package validation, and workflow syntax review.

```bash
npm ci
npm run build
composer install
vendor/bin/phpunit -c phpunit.xml.dist
./scripts/package-theme.sh 0.1.999
git diff --check
```

- [ ] Commit.

```bash
git add scripts/package-theme.sh .github/workflows/edge-release.yml package.json package-lock.json README.md
git commit -m "ci: publish immutable theme edge releases"
```

## Task 8: Validate, review, and publish the theme branch

**Files:**

- Verify every file changed in Tasks 1–7.

- [ ] Run the complete local verification suite.

```bash
cd /Users/hadi/dev/mumega/mumcp/mumega-motion-theme
npm ci
npm run build
node --check build/index.js
composer install
vendor/bin/phpunit -c phpunit.xml.dist
find . -path './vendor' -prune -o -path './node_modules' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/package-theme.sh 0.1.999
git diff --check master...HEAD
```

- [ ] Inspect the generated ZIP and manifest. Confirm one root directory, no development-only files, exact version agreement, and matching SHA-256.

```bash
unzip -l dist/mumega-motion-theme-0.1.999.zip
shasum -a 256 dist/mumega-motion-theme-0.1.999.zip
cat dist/manifest.json
```

- [ ] Request code review with special attention to path validation, authorization, rollback atomicity, and failed-update recovery. Resolve findings and rerun the full suite.

- [ ] Push the existing feature branch and open a pull request against `master`.

```bash
git push -u origin feat/mcp-triggered-updates
gh pr create --base master --head feat/mcp-triggered-updates --title "Add verified MCP-triggered theme updates" --body-file /tmp/mumega-motion-update-channel-pr.md
```

- [ ] Merge only after both PHP matrix jobs, JavaScript build, package checks, and review pass. Confirm the merge produces a new immutable `edge-v0.1.RUN_NUMBER` prerelease with all three assets.

## Task 9: Perform the one-time bridge install and prove the live loop

**Systems:**

- GitHub release assets from `Mumega-com/mumega-motion-theme`
- WordPress site `mcpwp.net`
- MCPWP admin-scoped API key
- Desktop and mobile browser checks

- [ ] Confirm the live MCPWP version includes the raise-only scope filter. Use tool discovery with an admin-scoped key and verify the response does not expose either theme tool before the bridge theme is installed.

- [ ] Download the first green edge ZIP from its immutable GitHub prerelease and independently verify its SHA-256 against both `manifest.json` and the `.sha256` release asset.

- [ ] Make one manual WordPress theme upload over installed version `0.1.0`. Keep Mumega Motion active and verify the installed version exactly matches the edge manifest. This manual bridge is required because version `0.1.0` cannot update itself.

- [ ] Discover MCP tools again. Confirm both `wp_update_mumega_motion` and `wp_rollback_mumega_motion` are present for an admin-scoped key.

- [ ] Prove authorization boundaries:

  - a write-only API key is rejected for both tools;
  - an admin-scoped key can invoke them;
  - unauthenticated REST requests are rejected;
  - a logged-in user without `update_themes` is rejected.

- [ ] Merge a harmless visible theme change to produce a second edge release. Invoke `wp_update_mumega_motion` with the admin-scoped key and record the returned previous version, current version, tag, checksum, backup ID, and verified flag.

- [ ] Verify the homepage separately after the MCP operation:

  - HTTP status is successful;
  - the Mumega Motion theme marker and new version are present;
  - desktop and mobile screenshots show no fatal error, missing layout, or broken primary navigation;
  - browser console has no new theme error.

- [ ] Invoke `wp_rollback_mumega_motion`, verify its structured result, and repeat the desktop/mobile render checks. Then update forward again so production finishes on the newest verified release.

- [ ] Add the live evidence—release tags, versions, checksums, MCP result summaries, and verification timestamp—to the operational section of `README.md`. Do not commit API keys, cookies, backup IDs from production, or private URLs. This documentation push will intentionally create one final edge release.

- [ ] Commit and push only the sanitized evidence update.

```bash
git add README.md
git commit -m "docs: record verified live theme update loop"
git push
```

- [ ] Wait for the documentation commit's edge prerelease to pass all checks. Invoke `wp_update_mumega_motion` once more, verify the exact final version and checksum, and repeat the desktop/mobile render checks. Do not push another commit after this final production verification.
