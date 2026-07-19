# Mumega Motion 0.2.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved MCPWP Homepage V2 and six visible editorial formats as a reusable, server-rendered Mumega Motion 0.2.0 release without changing the public homepage.

**Architecture:** Extend the existing classic PHP theme and its shared used-ID query transaction. WordPress page content, menus, pages, categories, tags and featured media supply site-specific data; template parts own semantic presentation; CSS owns responsive layout; Motion remains optional progressive enhancement. Publish an exact immutable `edge-v0.2.0` package only through an explicit verified release dispatch.

**Tech Stack:** WordPress 6.5+, PHP 7.4+, Gutenberg core blocks, `theme.json`, PHPUnit 9, WordPress Coding Standards, existing React/Motion bundle, Bash packaging, GitHub Actions.

## Global Constraints

- Treat both 2026-07-19 design specifications as binding.
- Do not add `front-page.php` or change Reading Settings.
- Do not hard-code MCPWP, ASTER, article titles, post/page IDs, category IDs, benchmark values, audience labels or public positioning copy.
- Keep essential content server-rendered and complete without JavaScript.
- Preserve Elementor isolation, current updater security, immutable releases and rollback.
- Reuse WordPress core React; never bundle another runtime.
- Preserve PHP 7.4 and WordPress 6.5 minimums.
- Optional modules must omit themselves without empty headings or dead links.
- The public homepage remains the legacy Elementor page until explicit owner approval.
- Use TDD for PHP behavior and focused commits per task.

---

## File and interface map

| Area | Files | Responsibility |
|---|---|---|
| Conventions | `inc/editorial-helpers.php` | Public page lookup, safe block rendering, guide/methodology/knowledge conventions |
| Menus | `inc/editorial-setup.php`, `inc/editorial-helpers.php` | Register and resolve up to three audience pathways |
| Queries | `inc/editorial-queries.php` | Briefing, related, coverage, guide and tool selection with one used-ID transaction |
| Patterns | `inc/editorial-patterns.php` | Six new format patterns plus existing utility patterns |
| Homepage | `page-templates/editorial-home.php` | Data orchestration only |
| Parts | `template-parts/home-*.php` | Hero, briefing, pathways, coverage, guides, trust, tools and knowledge |
| Styles | `assets/css/editorial.css`, `assets/css/print.css`, `theme.json` | Approved visual system, responsive and print behavior |
| Tests | `tests/Editorial*Test.php`, `tests/bootstrap.php` | Query, semantic, fallback, pattern, package and asset contracts |
| Release | `.github/workflows/edge-release.yml`, `scripts/test-package-release.sh` | Explicit exact-version immutable package |

## Public PHP interfaces

```php
mumega_motion_public_page_by_slug( $slug ): ?WP_Post
mumega_motion_editorial_guide_page(): ?WP_Post
mumega_motion_methodology_page(): ?WP_Post
mumega_motion_knowledge_map_page(): ?WP_Post
mumega_motion_render_post_content( $post ): string
mumega_motion_audience_menu_items( $limit = 3 ): array
mumega_motion_select_coverage_feature( &$used_ids ): ?WP_Post
mumega_motion_select_rail_groups( &$used_ids, $limit = 3, $posts_per_group = 3 ): array
```

### Task 1: Add reusable page and audience conventions

**Files:**
- Modify: `inc/editorial-setup.php`
- Modify: `inc/editorial-helpers.php`
- Modify: `tests/EditorialSetupTest.php`
- Modify: `tests/EditorialHelpersTest.php`
- Modify: `tests/bootstrap.php`

**Produces:** generic pages and ordered audience menu data without site-specific labels.

- [ ] **Step 1: Write failing tests**

Add tests that assert:

```php
$this->assertSame(
    array(
        'primary'   => 'Primary Navigation',
        'footer'    => 'Footer Navigation',
        'audiences' => 'Audience Pathways',
    ),
    $GLOBALS['mumega_motion_test_menu_locations']
);
```

Test `mumega_motion_public_page_by_slug()` requires a published, unprotected page and rejects invalid slugs before querying. Test the three wrappers resolve `editorial-guide`, `editorial-methodology`, and `knowledge-map`. Test `mumega_motion_audience_menu_items(3)` preserves menu order, returns only URL/title/description, limits to three, and returns `[]` when unassigned.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter 'Editorial(Setup|Helpers)Test'`
Expected: FAIL for missing location and functions.

- [ ] **Step 3: Register the menu and implement page lookup**

Add `audiences` to `register_nav_menus()`. Implement a single constrained page query:

```php
function mumega_motion_public_page_by_slug( $slug ) {
    if ( ! is_string( $slug ) || $slug !== sanitize_title( $slug ) || '' === $slug ) {
        return null;
    }

    $pages = get_posts(
        array(
            'post_type' => 'page', 'name' => $slug, 'post_status' => 'publish',
            'has_password' => false, 'numberposts' => 1,
        )
    );
    $page = empty( $pages ) ? null : $pages[0];
    return $page instanceof WP_Post && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
}
```

Make the newsletter and affiliate helpers delegate to this function without changing their public result.

- [ ] **Step 4: Implement audience menu normalization**

Resolve the assigned `audiences` menu, return at most three objects as arrays containing escaped-later raw scalar values: `title`, `description`, `url`. Reject non-object items and empty/invalid URLs. Do not return object IDs, CSS classes or arbitrary HTML.

- [ ] **Step 5: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter 'Editorial(Setup|Helpers)Test'`
Expected: PASS.

```bash
git add inc/editorial-setup.php inc/editorial-helpers.php tests/EditorialSetupTest.php tests/EditorialHelpersTest.php tests/bootstrap.php
git commit -m "feat: add editorial profile conventions"
```

### Task 2: Generalize deterministic homepage queries

**Files:**
- Modify: `inc/editorial-queries.php`
- Modify: `tests/EditorialQueriesTest.php`

**Produces:** one unique briefing, two related links, one coverage feature, four coverage cards, four two-post guide groups, and up to three tool posts.

- [ ] **Step 1: Write failing query tests**

Add tests proving:

- the sticky briefing is selected first and commits its ID;
- `mumega_motion_select_coverage_feature()` selects the newest eligible non-Release unused post and commits it;
- two related, one feature and four support posts contain seven unique IDs;
- `mumega_motion_select_rail_groups( $used, 4, 2 )` commits only complete two-post groups;
- the existing two-argument call still defaults to three groups of three;
- `tools-reviews` posts remain distinct from every earlier module.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialQueriesTest.php`
Expected: FAIL for missing coverage function and third rail parameter.

- [ ] **Step 3: Add `mumega_motion_select_coverage_feature()`**

Use the same non-Release fallback constraints as the non-sticky lead path, with `numberposts => 1` and the supplied used IDs. Return `null` without mutating IDs when empty.

- [ ] **Step 4: Generalize rail groups**

Change the signature to:

```php
function mumega_motion_select_rail_groups( &$used_ids, $limit = 3, $posts_per_group = 3 )
```

Clamp both numbers to zero or greater, return early for zero, query exactly `$posts_per_group`, and commit a candidate only when the count matches. Update `mumega_motion_select_rail_categories()` to call the default three-post behavior.

- [ ] **Step 5: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialQueriesTest.php`
Expected: PASS.

```bash
git add inc/editorial-queries.php tests/EditorialQueriesTest.php
git commit -m "feat: select homepage v2 content deterministically"
```

### Task 3: Add safe reusable block-content rendering

**Files:**
- Modify: `inc/editorial-helpers.php`
- Modify: `template-parts/newsletter.php`
- Modify: `tests/EditorialHelpersTest.php`
- Modify: `tests/EditorialHomeTemplateTest.php`

**Produces:** `mumega_motion_render_post_content()` with global restoration shared by hero and newsletter.

- [ ] **Step 1: Write failing global-state tests**

Given a previous global post and a target page, assert the helper applies `the_content`, returns rendered blocks, calls setup/reset/setup in that order, and restores both `$GLOBALS['post']` and the test current post. Assert invalid input returns an empty string without events.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter render_post_content`
Expected: FAIL because the helper is absent.

- [ ] **Step 3: Implement and reuse the helper**

Capture the previous global, call `setup_postdata( $post )`, filter the target's `post_content`, call `wp_reset_postdata()`, and restore the previous post data if it was a `WP_Post`. Return the rendered string; never echo from the helper. Replace duplicate newsletter state management with this helper.

- [ ] **Step 4: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter 'Editorial(Helpers|HomeTemplate)Test'`
Expected: PASS including existing newsletter restoration.

```bash
git add inc/editorial-helpers.php template-parts/newsletter.php tests/EditorialHelpersTest.php tests/EditorialHomeTemplateTest.php
git commit -m "refactor: centralize safe page content rendering"
```

### Task 4: Implement the two-column hero and ASTER-compatible briefing

**Files:**
- Create: `template-parts/home-intro.php`
- Create: `template-parts/home-briefing.php`
- Modify: `page-templates/editorial-home.php`
- Modify: `tests/EditorialHomeTemplateTest.php`

**Produces:** page-owned H1/promise plus a generic guide/briefing card with no hard-coded ASTER.

- [ ] **Step 1: Write failing semantic tests**

Render a page with title, excerpt and core-button content; a briefing post; and an `editorial-guide` page with title, excerpt and thumbnail. Assert:

- exactly one H1 contains the page title, not the briefing title;
- `.home-intro` precedes `.home-briefing` in source order;
- page excerpt and rendered block content appear;
- guide title, role/disclosure excerpt, profile URL and responsive image appear;
- briefing title is H2/H3, never H1;
- no guide page yields `Editor's briefing`, no image and no dead profile link;
- no briefing yields the page promise plus the useful empty state.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: FAIL because current lead owns H1 and parts are absent.

- [ ] **Step 3: Implement `home-intro.php`**

Accept `page` as a `WP_Post`. Render `get_the_title( $page )` as H1, manual excerpt when nonempty, and `mumega_motion_render_post_content( $page )`. Escape title/excerpt and allow only WordPress-filtered block output at the documented boundary.

- [ ] **Step 4: Implement `home-briefing.php`**

Accept `post`, optional `guide`, and up to two `related` posts. Use the guide title to form a possessive briefing label; use the generic translated label when absent. Render the guide featured image with explicit responsive core markup, role/disclosure excerpt, profile link, briefing category/title/summary/meta, and related text links. Escape every scalar at output.

- [ ] **Step 5: Update the page template query order**

Use:

```php
$used_ids          = array();
$briefing           = mumega_motion_select_lead_post( $used_ids );
$briefing_related   = mumega_motion_select_supporting_posts( $used_ids, 2 );
$coverage_feature   = mumega_motion_select_coverage_feature( $used_ids );
$coverage_support   = mumega_motion_select_supporting_posts( $used_ids, 4 );
$guide_groups       = mumega_motion_select_rail_groups( $used_ids, 4, 2 );
$tool_posts         = mumega_motion_select_special_posts( 'tools-reviews', $used_ids, 3 );
```

Resolve the current page, guide, methodology, knowledge map, newsletter and audience items before rendering. Do not change Reading Settings.

- [ ] **Step 6: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: PASS with one page-owned H1 and correct fallbacks.

```bash
git add page-templates/editorial-home.php template-parts/home-intro.php template-parts/home-briefing.php tests/EditorialHomeTemplateTest.php
git commit -m "feat: build audience-first editorial hero"
```

### Task 5: Implement pathways, coverage and field guides

**Files:**
- Create: `template-parts/home-audiences.php`
- Create: `template-parts/home-coverage.php`
- Create: `template-parts/home-guides.php`
- Modify: `page-templates/editorial-home.php`
- Modify: `tests/EditorialHomeTemplateTest.php`

**Produces:** three audience paths, one-plus-four coverage grid, and four complete two-post guide groups.

- [ ] **Step 1: Write failing template-part tests**

Assert each nonempty part has one labelled H2 section, correct H3 card hierarchy, configured order and descriptive links. Assert empty inputs emit an empty string. Assert coverage and guides never repeat briefing IDs.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: FAIL for missing parts.

- [ ] **Step 3: Implement the three parts**

- Audience cards use menu title, optional description and URL only.
- Coverage uses a translatable `Latest coverage` fallback, one feature card and four standard/compact cards.
- Guides use a translatable `Field guides` heading, category title/description/link and two posts per group.

All cards reuse existing content-card parts; no nested links or clickable whole-card JavaScript.

- [ ] **Step 4: Insert parts in approved source order**

Hero, audiences, coverage, guides. Keep all content in normal document order before optional lower modules.

- [ ] **Step 5: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: PASS.

```bash
git add page-templates/editorial-home.php template-parts/home-audiences.php template-parts/home-coverage.php template-parts/home-guides.php tests/EditorialHomeTemplateTest.php
git commit -m "feat: add homepage discovery paths"
```

### Task 6: Implement trust, tools, knowledge and newsletter modules

**Files:**
- Create: `template-parts/home-methodology.php`
- Create: `template-parts/home-tools.php`
- Create: `template-parts/home-knowledge.php`
- Modify: `page-templates/editorial-home.php`
- Modify: `tests/EditorialHomeTemplateTest.php`

**Produces:** optional page/category-backed lower homepage with honest omissions.

- [ ] **Step 1: Write failing fallback tests**

Assert:

- methodology renders only for a public page, links to it, shows its title/excerpt, and contains Install/Connect/Verify/Recover plus Tested on WordPress;
- tools renders only with at least two unused `tools-reviews` posts;
- knowledge renders only for a public page and uses its title, excerpt, featured media and URL;
- newsletter behavior remains block-aware and outside Motion mounts;
- absent inputs leave no empty section heading.

- [ ] **Step 2: Run and verify failure**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: FAIL for missing parts.

- [ ] **Step 3: Implement parts and source order**

Render methodology, tools, knowledge and newsletter after guides. The methodology sequence is theme vocabulary but no article receives a tested label automatically. Knowledge media is an authored illustration, not a computed graph. Tool labels come only from real categories.

- [ ] **Step 4: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHomeTemplateTest.php`
Expected: PASS.

```bash
git add page-templates/editorial-home.php template-parts/home-methodology.php template-parts/home-tools.php template-parts/home-knowledge.php tests/EditorialHomeTemplateTest.php
git commit -m "feat: add homepage trust and knowledge modules"
```

### Task 7: Register six public-format Gutenberg patterns

**Files:**
- Modify: `inc/editorial-patterns.php`
- Modify: `tests/EditorialPatternsTest.php`

**Produces:** six new stable format patterns alongside six existing utility patterns.

- [ ] **Step 1: Replace the exact-six assertion with an exact-twelve contract**

Keep the existing six slugs and append:

```text
mumega-motion/explainer
mumega-motion/practical-guide
mumega-motion/test-report
mumega-motion/comparison-review
mumega-motion/news-briefing
mumega-motion/analysis-opinion
```

Add one test per format asserting every required heading from the approved design and no H1 block.

- [ ] **Step 2: Run pattern tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialPatternsTest.php`
Expected: FAIL at exact count and missing slugs.

- [ ] **Step 3: Implement format content builders**

Use the existing heading, paragraph, list, table and group builders. Each function returns serialized core blocks in the exact approved section order. Shared final sections are `Sources`, `Limitations`, and `Corrections / updates`; format-specific headings precede them. Instructional copy names the evidence required and never inserts claims.

- [ ] **Step 4: Register patterns**

Add all six to the existing `mumega-motion` pattern category with descriptive titles and `inserter => true`. Do not remove utility patterns.

- [ ] **Step 5: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialPatternsTest.php`
Expected: PASS with 12 exact stable slugs.

```bash
git add inc/editorial-patterns.php tests/EditorialPatternsTest.php
git commit -m "feat: add agentic editorial format patterns"
```

### Task 8: Implement the approved visual and responsive system

**Files:**
- Modify: `theme.json`
- Modify: `assets/css/editorial.css`
- Modify: `assets/css/print.css`
- Modify: `tests/EditorialVisualSystemTest.php`
- Modify: `tests/EditorialContentTemplatesTest.php`

**Produces:** ivory/navy publication layout with violet, teal, cobalt and restrained amber semantic accents.

- [ ] **Step 1: Write failing token and layout assertions**

Tests require semantic palette slugs `paper`, `ink`, `muted-ink`, `lavender`, `lavender-ink`, `navy`, `teal`, `cobalt`, `amber`, `rule`, `white`; two-column hero selectors; single-column mobile media query; 44px minimum interactive controls; reduced-motion guard; print visibility for every homepage section; and no `display:none` on essential content.

- [ ] **Step 2: Run focused tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter 'Editorial(VisualSystem|ContentTemplates)Test'`
Expected: FAIL for missing tokens/selectors.

- [ ] **Step 3: Add tokens and CSS**

Use a 12-column homepage grid at 800px+, with `.home-intro` spanning 7 and `.home-briefing` spanning 5; all later modules span full width. Stack hero in source order below 800px. Use CSS Grid for audience, coverage, guides and tools; use no layout JavaScript. Preserve current focus, overflow and reduced-motion rules.

- [ ] **Step 4: Add print rules**

Print removes decorative backgrounds and nonessential motion marks while keeping headings, article links, methodology, disclosures, sources and newsletter destination readable.

- [ ] **Step 5: Verify and commit**

Run: `vendor/bin/phpunit -c phpunit.xml.dist --filter 'Editorial(VisualSystem|ContentTemplates)Test'`
Expected: PASS.

```bash
git add theme.json assets/css/editorial.css assets/css/print.css tests/EditorialVisualSystemTest.php tests/EditorialContentTemplatesTest.php
git commit -m "style: implement MCPWP editorial homepage v2"
```

### Task 9: Update package allowlist and exact 0.2.0 release control

**Files:**
- Modify: `.github/workflows/edge-release.yml`
- Modify: `scripts/test-package-release.sh`
- Modify: `tests/PackageManifestIntegrationTest.php`

**Produces:** verified runtime files plus an explicit immutable `edge-v0.2.0` release without repeated tag creation on every master push.

- [ ] **Step 1: Write failing release tests**

Require all new template parts in the package. Replace assertions expecting `0.1.${GITHUB_RUN_NUMBER}` with assertions that:

- verify still runs on PR and push;
- release runs only for `workflow_dispatch` on `refs/heads/master`;
- dispatch requires a `version` input matching `MAJOR.MINOR.PATCH`;
- `VERSION` equals the validated input;
- `TAG=edge-v${VERSION}`;
- release remains prerelease and immutable;
- existing tags are never replaced.

- [ ] **Step 2: Run and verify failure**

Run: `./scripts/test-package-release.sh`
Expected: FAIL on old run-number derivation and missing runtime parts.

- [ ] **Step 3: Change release trigger and version validation**

Add:

```yaml
workflow_dispatch:
  inputs:
    version:
      description: Exact immutable semantic version
      required: true
      type: string
```

Guard release with `github.event_name == 'workflow_dispatch' && github.ref == 'refs/heads/master'`. Validate the input with Bash `^[0-9]+\.[0-9]+\.[0-9]+$`, export `VERSION` and `TAG`, and retain the exact SHA/timestamp/tag/package/immutable verification chain.

- [ ] **Step 4: Add new parts to runtime verification**

List every new `template-parts/home-*.php` file in `required_runtime_files`. Preserve the exact top-level allowlist; `editorial/` remains excluded.

- [ ] **Step 5: Verify package 0.2.0**

Run:

```bash
npm run build
MUMEGA_MOTION_MANIFEST_PUBLISHED_AT="$(git show -s --format=%cI HEAD)" ./scripts/package-theme.sh 0.2.0
./scripts/test-package-release.sh
unzip -p dist/mumega-motion-theme-0.2.0.zip mumega-motion-theme/style.css | grep -Fx 'Version: 0.2.0'
```

Expected: all commands pass; manifest version/package/tag URLs bind exactly to 0.2.0.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/edge-release.yml scripts/test-package-release.sh tests/PackageManifestIntegrationTest.php
git commit -m "ci: publish explicit immutable theme versions"
```

### Task 10: Full verification and private release candidate

**Files:**
- Modify only files required by discovered verification defects.

- [ ] **Step 1: Run all automated gates**

```bash
npm ci
npm run validate:editorial-contract
npm run test:editorial-contract
npm run test:js
npm run build
vendor/bin/phpunit -c phpunit.xml.dist
find functions.php index.php header.php footer.php page.php single.php home.php archive.php search.php 404.php page-templates template-parts inc -type f -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/test-package-release.sh
```

Expected: every command exits 0 with no warnings or risky tests.

- [ ] **Step 2: Build the installable candidate**

Run: `MUMEGA_MOTION_MANIFEST_PUBLISHED_AT="$(git show -s --format=%cI HEAD)" ./scripts/package-theme.sh 0.2.0`
Expected: ZIP, SHA256 and manifest created in `dist/` and checksum validates.

- [ ] **Step 3: Inspect the archive**

Confirm no tests, source, docs, editorial contract, credentials, Markdown, maps, vendor or node modules. Confirm all required PHP/CSS/build files exist and version headers/manifest agree.

- [ ] **Step 4: Commit verification-only fixes separately**

Use a focused `fix:` commit for each defect; do not combine unrelated failures.

## Completion evidence

- PHP and contract test suites pass.
- The homepage has one page-owned H1 and exact two-column desktop hierarchy.
- All content is unique across briefing, related, coverage, guides and tools.
- Six format patterns plus six utility patterns register.
- Optional modules omit cleanly.
- No essential content depends on JavaScript.
- Package contains the complete runtime and no private editorial source.
- Candidate package and manifest bind exactly to version 0.2.0.
- No front-page setting or production content changed.
