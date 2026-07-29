# MCPWP Conversion Homepage Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and deploy a separate MCPWP conversion-homepage preview that can be reviewed without changing the production homepage.

**Architecture:** Add one reusable native page template that owns its shell and scoped stylesheet while rendering page-owned WordPress content. Keep MCPWP copy in a source-controlled site-profile HTML document, upload ASTER as WordPress media, and create the preview page on `demo.mcpwp.net`; do not add `front-page.php` or change Reading Settings.

**Tech Stack:** PHP 7.4-compatible WordPress theme code, PHPUnit 9, semantic HTML, CSS, WP-CLI, Docker staging.

## Global Constraints

- Production `mcpwp.net` remains unchanged.
- The preview is unlinked and excluded from indexing.
- The current homepage remains the rollback target.
- Elementor pages retain their current shell and assets.
- The page contains exactly one H1 and works without JavaScript.
- The primary action links to the official WordPress.org listing.
- Public directory and MCPWP 3.10.2 Agency Mode claims remain distinct.
- ASTER uses `docs/superpowers/specs/assets/2026-07-29-aster-product-hero.png`.

---

### Task 1: Product-home template contract

**Files:**
- Create: `tests/ProductHomeTemplateTest.php`
- Modify: `tests/EditorialSetupTest.php`
- Create: `page-templates/product-home.php`
- Modify: `inc/editorial-setup.php`

**Interfaces:**
- Consumes: `mumega_motion_get_header()`, `mumega_motion_get_footer()`, and the WordPress page loop.
- Produces: named template `page-templates/product-home.php` and editorial-shell ownership for that template.

- [ ] **Step 1: Write failing tests**

Add tests that require:

```php
$this->assertStringContainsString( 'Template Name: Product Home', $source );
$this->assertStringContainsString( 'mumega_motion_get_header();', $source );
$this->assertStringContainsString( 'mumega_motion_get_footer();', $source );
$this->assertStringContainsString( 'the_content();', $source );
```

Also require `mumega_motion_is_editorial_view()` to return true for
`page-templates/product-home.php`.

- [ ] **Step 2: Run tests and verify the expected failure**

Run:

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/ProductHomeTemplateTest.php tests/EditorialSetupTest.php
```

Expected: failure because `page-templates/product-home.php` and its ownership condition do not exist.

- [ ] **Step 3: Implement the minimal template contract**

Create a named page template that renders:

```php
mumega_motion_get_header();
?>
<main id="primary" class="site-main product-home-shell">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'product-home-entry' ); ?>>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
mumega_motion_get_footer();
```

Add `page-templates/product-home.php` to `mumega_motion_is_editorial_view()`.

- [ ] **Step 4: Run focused tests**

Run the focused PHPUnit command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/ProductHomeTemplateTest.php tests/EditorialSetupTest.php page-templates/product-home.php inc/editorial-setup.php
git commit -m "feat: add native product homepage template"
```

---

### Task 2: Scoped product-home visual system

**Files:**
- Modify: `tests/EditorialSetupTest.php`
- Create: `tests/ProductHomeVisualSystemTest.php`
- Create: `assets/css/product-home.css`
- Modify: `inc/editorial-setup.php`

**Interfaces:**
- Consumes: `is_page_template( 'page-templates/product-home.php' )`.
- Produces: style handle `mumega-motion-product-home`, dependent on `mumega-motion-editorial`.

- [ ] **Step 1: Write failing asset and CSS contract tests**

Require the product template to enqueue only:

```php
wp_enqueue_style(
	'mumega-motion-product-home',
	$uri . 'product-home.css',
	array( 'mumega-motion-editorial' ),
	$version
);
```

Require the CSS to include:

```css
.mcpwp-product-home
.mcpwp-product-hero
@media (max-width: 48rem)
@media (prefers-reduced-motion: reduce)
:focus-visible
```

- [ ] **Step 2: Run tests and verify the expected failure**

Run:

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialSetupTest.php tests/ProductHomeVisualSystemTest.php
```

Expected: failure because the scoped stylesheet and enqueue do not exist.

- [ ] **Step 3: Implement the stylesheet and conditional enqueue**

Build the approved paper, ink-navy, violet, teal, cobalt, and amber system with:

- a visible desktop navigation;
- 58/42 hero composition;
- install-first CTA hierarchy;
- compatibility rail;
- four-step workflow;
- free/agency comparison;
- methodology, guides, and final CTA;
- 44px minimum controls;
- responsive single-column layouts at 768px and below;
- reduced-motion and visible focus treatment.

- [ ] **Step 4: Run focused tests**

Run the focused PHPUnit command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/EditorialSetupTest.php tests/ProductHomeVisualSystemTest.php assets/css/product-home.css inc/editorial-setup.php
git commit -m "style: add MCPWP product homepage system"
```

---

### Task 3: Source-controlled MCPWP page content

**Files:**
- Create: `site-content/mcpwp-product-home.html`
- Create: `tests/McpwpProductHomeContentTest.php`

**Interfaces:**
- Consumes: WordPress page content and ASTER media URL replacement token.
- Produces: one importable content document using `{{ASTER_URL}}`.

- [ ] **Step 1: Write failing content-contract tests**

Require:

```php
$this->assertSame( 1, substr_count( $content, '<h1' ) );
$this->assertStringContainsString( 'Install free from WordPress.org', $content );
$this->assertStringContainsString( 'https://wordpress.org/plugins/mumega-mcp/', $content );
$this->assertStringContainsString( '{{ASTER_URL}}', $content );
$this->assertStringContainsString( 'Claude', $content );
$this->assertStringContainsString( 'ChatGPT', $content );
$this->assertStringContainsString( 'Gemini', $content );
$this->assertStringContainsString( 'Codex', $content );
$this->assertStringContainsString( 'Hermes', $content );
$this->assertStringContainsString( 'OpenClaw', $content );
```

Also reject `site-pilot-ai`, `spai_`, fake testimonials, and a public version claim for 3.10.2.

- [ ] **Step 2: Run the test and verify the expected failure**

Run:

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/McpwpProductHomeContentTest.php
```

Expected: failure because the content document does not exist.

- [ ] **Step 3: Write semantic page content**

Create one `.mcpwp-product-home` root containing the approved hero, compatibility, real-workflow example, first-connection sequence, free/agency comparison, testing methodology, guide links, and final install action. Use inline SVG icons and no scripts.

- [ ] **Step 4: Run the content test**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add site-content/mcpwp-product-home.html tests/McpwpProductHomeContentTest.php
git commit -m "content: add MCPWP homepage preview profile"
```

---

### Task 4: Package and staging preview

**Files:**
- Modify: `scripts/test-package-release.sh` only if the new runtime path is missing from its assertions.
- Generated locally: `dist/mumega-motion-theme-0.2.2.zip`
- Staging WordPress: `https://demo.mcpwp.net`

**Interfaces:**
- Consumes: theme package, ASTER source asset, site-content HTML.
- Produces: one unlinked, noindex staging page using the Product Home template.

- [ ] **Step 1: Run the complete local verification suite**

Run:

```bash
composer test
npm run test:js
npm run build
npm run test:editorial-contract
npm run validate:editorial-contract
./scripts/test-package-release.sh
```

Expected: all commands exit 0.

- [ ] **Step 2: Build a deterministic preview package**

Run:

```bash
./scripts/package-theme.sh 0.2.2
```

Expected: ZIP, SHA-256 file, and manifest are created in `dist/`.

- [ ] **Step 3: Record staging rollback state**

Over SSH, record the current active theme, page count, and existing page IDs. Do not read secrets.

- [ ] **Step 4: Install the theme package and ASTER media on staging**

Copy the package and image to the existing VPS staging host, install/activate the theme, import ASTER into Media Library, and record its attachment URL.

- [ ] **Step 5: Create the preview page**

Replace `{{ASTER_URL}}` in a temporary copy of the content, then create or update exactly one page:

- title: `MCPWP Home Preview`
- slug: `mcpwp-home-preview`
- status: `publish`
- template: `page-templates/product-home.php`
- robots: `noindex, nofollow`

Do not add it to navigation or Reading Settings.

- [ ] **Step 6: Verify staging state**

Confirm:

- active theme is the expected preview package;
- preview page resolves with HTTP 200;
- homepage setting is unchanged;
- no menu contains the preview URL;
- HTML contains one H1 and the WordPress.org CTA.

---

### Task 5: Browser quality gate and handoff

**Files:**
- Optional evidence images: temporary screenshots only; do not commit browser chrome or authenticated UI.

**Interfaces:**
- Consumes: staging preview URL.
- Produces: desktop and mobile evidence plus a user-facing preview URL.

- [ ] **Step 1: Inspect the rendered desktop page**

At 1440×900, verify the hero, ASTER image, navigation, CTA hierarchy, workflow, comparison, and footer. Check the browser console for page-owned errors.

- [ ] **Step 2: Inspect mobile**

At 375px and 320px, verify content order, 44px controls, no horizontal overflow, readable headings, and non-obscuring navigation.

- [ ] **Step 3: Inspect accessibility**

Verify keyboard order, visible focus, one H1, sequential headings, image alternatives, reduced motion, and 200% zoom behavior.

- [ ] **Step 4: Re-run the final code and package checks**

Run the complete suite from Task 4 Step 1 and confirm the Git working tree contains only intended changes.

- [ ] **Step 5: Commit implementation and update the existing draft PR**

Push the implementation commits to `feat/mcpwp-conversion-home-preview`. Keep PR #38 in draft until the owner approves the rendered page.
