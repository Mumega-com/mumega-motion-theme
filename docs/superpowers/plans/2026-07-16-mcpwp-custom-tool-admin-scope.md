# MCPWP Custom Tool Admin Scope Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let extensions raise a registered MCP tool's inferred authorization scope to `admin` without allowing any extension to weaken existing MCPWP scope requirements.

**Architecture:** Add one raise-only WordPress filter at MCPWP's existing tool-scope choke point. Keep the current hard-coded admin list and name-based write/read inference unchanged, expose the filter in the third-party tool contract, and prove elevation and downgrade protection in `ApiAuthTest`.

**Tech Stack:** PHP 7.4+, WordPress filters, MCPWP API-key authorization trait, PHPUnit 9.6, PHP_CodeSniffer.

**Repository:** `/Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval`

---

## Task 1: Lock the authorization contract with failing tests

**Files:**

- Modify: `mcpwp/tests/ApiAuthTest.php`
- Verify: `mcpwp/tests/bootstrap.php`

- [ ] Create an isolated branch from current `origin/main` and confirm the worktree is clean.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git fetch origin
git switch -c feat/custom-tool-admin-scope origin/main
git status --short --branch
```

Expected: branch `feat/custom-tool-admin-scope` tracks the current `origin/main`, with no modified files.

- [ ] Add a public test-only wrapper to `Mcpwp_Api_Auth_Test_Harness` so tests can exercise the private scope inference method without changing production visibility.

```php
public function required_scope_for_tool( string $tool_name ): string {
	return $this->get_required_scope_for_tool_name( $tool_name );
}
```

- [ ] Reset filter state in `ApiAuthTest::setUp()` so each test is independent.

```php
$GLOBALS['mcpwp_test_filters'] = array();
```

- [ ] Add a test proving an extension can raise its own tool to `admin`.

```php
public function test_extension_can_raise_custom_tool_scope_to_admin(): void {
	add_filter(
		'mcpwp_required_scope_for_tool',
		static function ( string $scope, string $tool_name ): string {
			return 'wp_update_mumega_motion' === $tool_name ? 'admin' : $scope;
		},
		10,
		2
	);

	$auth = new Mcpwp_Api_Auth_Test_Harness();

	$this->assertSame( 'admin', $auth->required_scope_for_tool( 'wp_update_mumega_motion' ) );
}
```

- [ ] Add a test proving a filter cannot lower an MCPWP core admin tool.

```php
public function test_extension_cannot_lower_builtin_admin_scope(): void {
	add_filter(
		'mcpwp_required_scope_for_tool',
		static function (): string {
			return 'read';
		},
		10,
		2
	);

	$auth = new Mcpwp_Api_Auth_Test_Harness();

	$this->assertSame( 'admin', $auth->required_scope_for_tool( 'wp_trigger_update' ) );
}
```

- [ ] Add a test proving invalid scope strings are ignored.

```php
public function test_invalid_filtered_scope_is_ignored(): void {
	add_filter(
		'mcpwp_required_scope_for_tool',
		static function (): string {
			return 'root';
		},
		10,
		2
	);

	$auth = new Mcpwp_Api_Auth_Test_Harness();

	$this->assertSame( 'write', $auth->required_scope_for_tool( 'wp_update_example' ) );
}
```

- [ ] Add a regression test for unchanged default inference.

```php
public function test_scope_inference_is_unchanged_without_filter(): void {
	$auth = new Mcpwp_Api_Auth_Test_Harness();

	$this->assertSame( 'read', $auth->required_scope_for_tool( 'vendor_list_records' ) );
	$this->assertSame( 'write', $auth->required_scope_for_tool( 'wp_update_example' ) );
	$this->assertSame( 'admin', $auth->required_scope_for_tool( 'wp_trigger_update' ) );
}
```

- [ ] Run only the authorization test under the project's PHP 8.3 test command and confirm the new elevation test fails because the filter hook does not yet exist.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
composer install
vendor/bin/phpunit -c tests/phpunit.xml tests/ApiAuthTest.php
```

Expected: the elevation assertion reports `write` instead of `admin`; existing authorization tests remain green.

- [ ] Commit the failing contract tests.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git add mcpwp/tests/ApiAuthTest.php
git commit -m "test: define custom tool scope elevation contract"
```

## Task 2: Implement the raise-only scope filter

**Files:**

- Modify: `mcpwp/includes/traits/trait-mcpwp-api-auth.php`
- Modify: `mcpwp/mcpwp.php`
- Test: `mcpwp/tests/ApiAuthTest.php`

- [ ] Refactor `get_required_scope_for_tool_name()` so its current hard-coded admin list and write-name regex assign to `$required_scope` instead of returning early. Do not change either list or regex.

- [ ] After default inference, apply the extension filter with the inferred scope and tool name.

```php
$filtered_scope = apply_filters(
	'mcpwp_required_scope_for_tool',
	$required_scope,
	$tool_name
);
```

- [ ] Accept only the three documented scope strings and only when the filtered scope has a greater rank than the inferred scope.

```php
$scope_ranks = array(
	'read'  => 1,
	'write' => 2,
	'admin' => 3,
);

if (
	is_string( $filtered_scope )
	&& isset( $scope_ranks[ $filtered_scope ] )
	&& $scope_ranks[ $filtered_scope ] > $scope_ranks[ $required_scope ]
) {
	return $filtered_scope;
}

return $required_scope;
```

- [ ] Add the WordPress PHPDoc immediately above the filter call. Document both parameters and state that implementations may raise, but cannot lower, the inferred requirement.

- [ ] Define a feature-detection constant next to MCPWP's existing version constants. The theme will use this to avoid registering destructive custom tools against an older MCPWP build that would infer only `write` scope.

```php
define( 'MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER', true );
```

- [ ] Run the focused test on PHP 8.3.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
vendor/bin/phpunit -c tests/phpunit.xml tests/ApiAuthTest.php
```

Expected: all `ApiAuthTest` tests pass.

- [ ] Run the same focused test with the repository's PHP 7.4 container or CI-equivalent command documented in the repository. If no wrapper exists, use the official PHP 7.4 CLI image with the repository mounted and Composer dependencies already installed.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
docker run --rm -v "$PWD:/app" -w /app php:7.4-cli php vendor/bin/phpunit -c tests/phpunit.xml tests/ApiAuthTest.php
```

Expected: all focused tests pass without syntax or type errors on PHP 7.4.

- [ ] Commit the implementation.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git add mcpwp/includes/traits/trait-mcpwp-api-auth.php mcpwp/mcpwp.php
git commit -m "feat: allow extensions to raise MCP tool scope"
```

## Task 3: Document the extension contract

**Files:**

- Modify: `mcpwp/includes/mcp/class-mcpwp-custom-tool-registry.php`
- Modify: `mcpwp/docs/skills/dev-mcp-tools.md`

- [ ] Extend the custom-tool registry documentation to explain that `annotations.destructiveHint` describes behavior but does not grant an authorization scope.

- [ ] Add a complete extension example to `dev-mcp-tools.md` showing registration plus exact-name scope elevation.

```php
add_filter(
	'mcpwp_required_scope_for_tool',
	static function ( string $scope, string $tool_name ): string {
		$admin_tools = array(
			'wp_update_mumega_motion',
			'wp_rollback_mumega_motion',
		);

		return in_array( $tool_name, $admin_tools, true ) ? 'admin' : $scope;
	},
	10,
	2
);
```

- [ ] State these invariants next to the example:

  - The filter can raise `read` to `write` or `admin`, and `write` to `admin`.
  - It cannot lower a core or inferred requirement.
  - Invalid scope values are ignored.
  - Exact tool names should be used.
  - A tool must still enforce an appropriate WordPress capability in its callback or REST permission callback.
  - Destructive extensions should feature-detect `MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER` before registering tools that require elevation.

- [ ] Run the focused tests and documentation lint, if the repository defines one.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
vendor/bin/phpunit -c tests/phpunit.xml tests/ApiAuthTest.php
vendor/bin/phpcs includes/traits/trait-mcpwp-api-auth.php includes/mcp/class-mcpwp-custom-tool-registry.php tests/ApiAuthTest.php
```

Expected: tests and coding-standard checks pass.

- [ ] Commit the documentation.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git add mcpwp/includes/mcp/class-mcpwp-custom-tool-registry.php mcpwp/docs/skills/dev-mcp-tools.md
git commit -m "docs: explain custom MCP tool authorization scopes"
```

## Task 4: Validate and publish the MCPWP change

**Files:**

- Verify all files changed by Tasks 1–3.

- [ ] Run the complete PHPUnit suite on PHP 8.3.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
vendor/bin/phpunit -c tests/phpunit.xml
```

- [ ] Run the complete suite on PHP 7.4 using the same container strategy used in Task 2.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
docker run --rm -v "$PWD:/app" -w /app php:7.4-cli php vendor/bin/phpunit -c tests/phpunit.xml
```

- [ ] Run PHP lint and coding standards across the changed PHP files.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval/mcpwp
php -l includes/traits/trait-mcpwp-api-auth.php
php -l includes/mcp/class-mcpwp-custom-tool-registry.php
php -l mcpwp.php
php -l tests/ApiAuthTest.php
vendor/bin/phpcs includes/traits/trait-mcpwp-api-auth.php includes/mcp/class-mcpwp-custom-tool-registry.php tests/ApiAuthTest.php
```

- [ ] Review the final diff and confirm the production change is limited to a generic, raise-only authorization extension point.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git diff --check origin/main...HEAD
git diff --stat origin/main...HEAD
git log --oneline origin/main..HEAD
```

- [ ] Push the branch and open a pull request. The PR must explain the threat model, downgrade protection, test matrix, and that no Mumega Motion-specific updater code is added to MCPWP core.

```bash
cd /Users/hadi/dev/mumega/mumcp/mcpwp-wporg-approval
git push -u origin feat/custom-tool-admin-scope
gh pr create --base main --head feat/custom-tool-admin-scope --title "Allow extensions to raise custom MCP tool scope" --body-file /tmp/mcpwp-custom-tool-admin-scope-pr.md
```

- [ ] Merge only after required checks and review pass. Record the merge commit and MCPWP release version required by the theme deployment plan.
