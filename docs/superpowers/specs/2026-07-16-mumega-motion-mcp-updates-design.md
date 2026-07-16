# Mumega Motion MCP-triggered updates

**Date:** 2026-07-16  
**Status:** Approved design, awaiting implementation plan  
**Repositories:** `Mumega-com/mumega-motion-theme`, `Mumega-com/mcpwp`

## Objective

Create a fast, controlled iteration loop for the active Mumega Motion theme on
`mcpwp.net`:

1. change theme code;
2. build and verify it in GitHub Actions;
3. publish an immutable update package;
4. trigger the update through an authenticated MCPWP tool;
5. verify the installed theme and rendered site;
6. roll back quickly if verification fails.

Updates are never installed merely because a commit was pushed. A successful
build makes an update available; an explicit MCP call or WordPress dashboard
action installs it.

## Repository decision

No new repository is required. `Mumega-com/mumega-motion-theme` remains the
canonical theme source and owns theme packaging, update discovery, backups, and
rollback. `Mumega-com/mcpwp` receives only the small extension-contract change
needed to let a third-party MCP tool require `admin` scope safely.

## Considered approaches

### Theme-owned MCP tool and GitHub release channel — selected

The theme registers its own REST endpoints and MCP tools through
`mcpwp_register_tools`. GitHub Actions publishes verified packages. This keeps
theme-specific behavior out of MCPWP core and needs no additional WordPress
plugin or server credential.

### WordPress dashboard updater only

This is retained as a fallback but rejected as the primary loop because it
requires a human dashboard click after every build.

### Direct GitHub-to-server deployment

Rejected for the first implementation. It would require server deployment
credentials in GitHub and could replace the live theme immediately after a bad
push. It also bypasses WordPress update handling, backups, and MCPWP's audit
trail.

## Release architecture

The theme repository adds one GitHub Actions workflow triggered by pushes to
`master` and manual dispatch.

The workflow:

1. installs dependencies with `npm ci`;
2. runs the production JavaScript build;
3. lints every shipped PHP file on PHP 7.4 and PHP 8.3;
4. validates the built JavaScript;
5. stages only runtime files under a single `mumega-motion-theme/` root;
6. assigns a monotonically increasing edge version of
   `0.1.<github.run_number>` in the staged `style.css`;
7. creates `mumega-motion-theme-<version>.zip`;
8. calculates SHA-256 and generates `manifest.json`;
9. publishes an immutable prerelease tagged `edge-v<version>`.

Each prerelease contains the ZIP, checksum file, and manifest. The workflow
does not alter the source tree solely to bump an edge build version. Stable
tagged releases can later use normal semantic versions such as `0.2.0`.

The theme discovers the most recent `edge-v*` prerelease through the public
GitHub Releases API. Discovery results are cached for fifteen minutes during
development. The MCP update tool supports a `force_check` boolean that clears
this cache before checking. The WordPress dashboard uses the cached result.

## Package and manifest contract

`manifest.json` contains:

```json
{
  "slug": "mumega-motion-theme",
  "version": "0.1.123",
  "commit": "full-git-sha",
  "package_url": "https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.123/mumega-motion-theme-0.1.123.zip",
  "sha256": "64-lowercase-hex-characters",
  "requires_wordpress": "6.5",
  "requires_php": "7.4",
  "published_at": "RFC3339 timestamp"
}
```

The update client rejects a manifest unless:

- the slug is exactly `mumega-motion-theme`;
- the version is newer according to `version_compare`;
- the package uses HTTPS and the host is exactly `github.com`;
- the package path belongs to `Mumega-com/mumega-motion-theme/releases/download/`;
- the checksum is valid lowercase SHA-256;
- runtime requirements are satisfied.

No MCP argument may override the manifest URL, repository, package URL, slug,
or checksum.

## WordPress update integration

The theme adds `Update URI: https://github.com/Mumega-com/mumega-motion-theme`
to `style.css` and provides a small update service under `inc/updates/`.

The service adds Mumega Motion update data to the normal `update_themes` site
transient. This makes the same verified GitHub build visible in WordPress's
Themes and Updates screens. The theme must continue to render normally when
GitHub is unreachable, rate-limited, or returns invalid data; update discovery
failure is never a frontend failure.

## MCPWP extension security change

MCPWP currently infers a third-party tool's scope from its name. A custom
update tool would otherwise resolve to `write`, even if the tool is marked
destructive. That is insufficient for code installation.

MCPWP adds a filter named `mcpwp_required_scope_for_tool` at the end of
`get_required_scope_for_tool_name()` with these rules:

- callbacks receive the inferred scope and tool name;
- a callback may raise `read` to `write` or `admin`, or `write` to `admin`;
- a callback can never lower the inferred scope;
- invalid values are ignored;
- built-in tool scopes therefore cannot be weakened by an extension.

Mumega Motion uses the filter to raise only `wp_update_mumega_motion` and
`wp_rollback_mumega_motion` to `admin`.

This MCPWP change receives focused tests proving elevation, rejection of
downgrades, and unchanged defaults for existing tools.

## Theme REST and MCP tools

The theme registers two REST endpoints:

- `POST /mumega-motion/v1/update`
- `POST /mumega-motion/v1/rollback`

Both permission callbacks require `current_user_can( 'update_themes' )`. They
also require MCPWP's admin scope when reached through MCP because the registered
tool names are elevated by `mcpwp_required_scope_for_tool`.

The theme registers two tools through `mcpwp_register_tools`:

### `wp_update_mumega_motion`

- category: `admin`
- destructive: `true`
- open world: `true`
- input: optional `force_check` boolean
- result: previous version, installed version, source commit, backup ID,
  package checksum, and verification state

### `wp_rollback_mumega_motion`

- category: `admin`
- destructive: `true`
- open world: `false`
- input: optional backup ID; omitted means the newest valid backup
- result: restored version, backup ID, and verification state

The REST endpoints are theme-specific. They do not accept arbitrary theme
slugs or packages and are not generic code installers.

## Update transaction

The update endpoint performs these stages:

1. verify WordPress capability and request scope;
2. fetch and validate the latest manifest;
3. return a no-update result when the installed version is current;
4. download the immutable ZIP to a WordPress temporary file;
5. verify its SHA-256 before extraction;
6. inspect the archive for one expected root and required runtime files;
7. create a backup of the currently installed theme;
8. install through WordPress's `Theme_Upgrader` using the verified local file;
9. confirm the theme remains installed and active;
10. confirm its version and required files match the manifest;
11. clear theme/update caches;
12. return structured evidence.

The updater keeps the three newest successful backups in a non-public
directory under `wp-content/uploads/mumega-motion-backups/`. Directory listing
is blocked with an `index.php`; generated backup names contain random IDs and
no credentials. Older backups are deleted only after a new backup is verified.

If installation returns an error or post-install verification fails, the same
request attempts to restore the freshly created backup and reports whether the
restore succeeded. A failed update must never be reported as successful merely
because `Theme_Upgrader` returned a truthy value.

## Render verification boundary

The update endpoint verifies package integrity and WordPress installation
state; it cannot prove that every public page renders correctly. After a
successful MCP update, the operating agent must separately verify at least:

- homepage HTTP status;
- expected theme marker/version in the response or site diagnostics;
- desktop screenshot;
- mobile screenshot;
- absence of fatal-error text and obvious layout loss.

Rendered verification remains a caller workflow and aligns with MCPWP issues
#639 and #651. A future workflow tool may combine update and render checks, but
that is outside this implementation.

## Failure behavior

- GitHub unavailable or rate-limited: retain the installed theme and return a
  retryable discovery error.
- Invalid manifest, URL, checksum, or archive: reject before touching the
  installed theme.
- No newer version: return success with `updated: false`.
- Backup failure: abort before installing.
- Install failure: attempt automatic restore and return failure evidence.
- Restore failure: preserve the backup, return a critical error, and provide
  the backup ID for manual recovery.
- MCPWP absent: dashboard update discovery remains available; MCP tools are
  simply not registered.

## Testing

### Theme tests

- manifest field and URL allowlist validation;
- semantic version comparison;
- checksum acceptance and rejection;
- archive root and required-file validation;
- backup creation, retention, and selection;
- capability denial;
- tool registration only when MCPWP's hooks are present;
- update no-op, success, installer failure, verification failure, automatic
  restore, and explicit rollback;
- malformed GitHub responses and network failures;
- PHP 7.4 and PHP 8.3 lint;
- production JavaScript build and syntax validation;
- exact ZIP content policy.

### MCPWP tests

- extension can elevate a custom tool from write to admin;
- extension cannot downgrade an admin or write tool;
- invalid extension scope is ignored;
- existing built-in scope inference remains unchanged.

### Release smoke test

Install the previous package in a disposable WordPress instance, publish a test
edge build, discover it, invoke `wp_update_mumega_motion`, verify the installed
version and active theme, then invoke rollback and verify restoration.

## Rollout

1. Land and release the MCPWP scope-elevation hook.
2. Update mcpwp.net to that MCPWP release through its existing updater.
3. Land the theme release workflow and update service.
4. Produce one edge release and manually update the currently installed 0.1.0
   package through the WordPress dashboard.
5. Confirm the MCP tools appear with the dedicated admin-scoped key.
6. Run one MCP-triggered edge update and rollback on mcpwp.net.
7. Begin normal website iteration only after the live round trip is verified.

## Non-goals

- automatic deployment on every push;
- arbitrary theme or package installation;
- server SSH/SFTP deployment;
- changing MCPWP's own updater;
- replacing the mcpwp.net content plan;
- declaring a page visually correct without browser evidence.
