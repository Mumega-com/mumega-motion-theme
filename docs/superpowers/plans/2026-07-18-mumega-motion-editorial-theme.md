# Mumega Motion Editorial Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Build the reusable, server-rendered editorial system in the approved MCPWP design without changing the live MCPWP.net homepage or hard-coding MCPWP-specific content into the theme.

**Architecture:** Keep Mumega Motion as a classic PHP theme with Gutenberg-authored content, theme.json tokens, deterministic WordPress queries, and bounded React/Motion progressive-enhancement islands. Add a named Editorial Home page template instead of front-page.php, so installing a theme package cannot replace the active Elementor homepage.

**Tech Stack:** WordPress 6.5+, PHP 7.4+, Gutenberg, theme.json, PHPUnit 9, WordPress Coding Standards, React supplied by WordPress, Motion 12, @wordpress/scripts, Bash package checks, GitHub Actions.

## Global Constraints

- Work only on feat/mcpwp-editorial-system until review and merge approval.
- Treat docs/superpowers/specs/2026-07-18-mcpwp-editorial-system-design.md as the source of truth.
- Do not add front-page.php, change Reading Settings, update the live theme, or modify live content in this plan.
- Do not delete or rewrite inc/updates, inc/figma-tokens.php, the release workflow, or existing updater tests.
- Do not hard-code MCPWP, MCPWP taxonomy labels, author names, post/page IDs, benchmark values, or newsletter copy in runtime PHP.
- Essential content and navigation must exist in server-rendered HTML before JavaScript runs.
- Yoast remains the sole schema and canonical owner.
- Reuse WordPress core React; never bundle another React runtime.
- Preserve PHP 7.4 compatibility.
- Escape on output, translate public fallbacks, reset post data after secondary loops, and preserve globals when rendering a page outside the main loop.
- Each new or changed PHP/JavaScript behavior follows red, green, refactor, verification, then a focused commit. CSS, `theme.json`, documentation, and manual rendered QA use their explicit build, lint, accessibility, and visual gates.

## File and Interface Map

| Area | Files | Contract |
|---|---|---|
| Bootstrap | functions.php, inc/editorial-setup.php | Load modules, theme supports, primary/footer menus, conditional assets |
| Helpers | inc/editorial-helpers.php | Visible text, reading time, summaries, primary category, modified date, newsletter lookup |
| Queries | inc/editorial-queries.php | Eligibility, Release exclusion, sticky override, menu rails, shared used-ID list |
| Gutenberg | inc/editorial-patterns.php | Six core-block editorial patterns |
| Motion | inc/editorial-islands.php, src/js/index.js, FadeIn.jsx | Bounded mounts, reduced motion, intact fallback |
| Visual system | theme.json, style.css, assets/css/editorial.css, assets/css/print.css | Paper/ink/lavender tokens, responsive and print rules |
| Shared templates | header.php, footer.php, page.php, index.php, 404.php | Semantic shell, search, accessible menu |
| Homepage | page-templates/editorial-home.php and template-parts | Lead, Test Lab, rails, Field Notes, newsletter |
| Content | single.php, home.php, archive.php, search.php | Article anatomy and listings |
| Verification | tests, package scripts, workflow | Automated contracts and complete runtime ZIP |

---

### Task 1: Establish the Baseline

**Files:**
- Read: functions.php
- Read: inc/updates/**
- Read: tests/**
- Read: scripts/package-theme.sh
- Read: .github/workflows/edge-release.yml

- [ ] Confirm the feature branch and a clean starting state.

~~~bash
git branch --show-current
git status --short
~~~

Expected: feat/mcpwp-editorial-system and no unexpected changes.

- [ ] Run the current regression gates.

~~~bash
composer install --no-interaction --prefer-dist
vendor/bin/phpunit -c phpunit.xml.dist
./scripts/test-package-release.sh
npm ci
npm run build
~~~

Expected: all existing tests pass, package checks end with Package and release workflow checks passed., and the bundle builds.

- [ ] Before Task 5, execute the read-only portions of the site-launch baseline: record the current static homepage ID, assigned page template, Elementor template metadata when visible, and the same facts for representative key Elementor pages. Capture logged-out screenshots without changing WordPress. Add each observed page-template mode to the Task 5 disposable fixtures so the new global shell can prove that legacy content still reaches `the_content()` and receives no editorial-only assets.

- [ ] Record the command output in the PR notes. Do not commit dist output.

### Task 2: Add Pure Editorial Helpers

**Files:**
- Create: inc/editorial-helpers.php
- Create: tests/EditorialHelpersTest.php
- Modify: tests/bootstrap.php
- Modify: functions.php

- [ ] Add minimal WP_Post and WP_Term test values plus controllable doubles for wp_strip_all_tags, strip_shortcodes, get_the_excerpt, get_post_field, get_the_terms, get_the_tags, apply_filters, and get_option.

- [ ] Write failing tests with these exact methods:

~~~php
public function test_reading_time_is_at_least_one_minute(): void;
public function test_reading_time_rounds_visible_words_up_at_225_words_per_minute(): void;
public function test_card_summary_prefers_a_manual_excerpt(): void;
public function test_card_summary_uses_first_28_visible_content_words(): void;
public function test_primary_category_prefers_first_assigned_category_in_menu_order(): void;
public function test_primary_category_falls_back_to_alphabetical_non_default_category(): void;
public function test_primary_category_returns_null_without_categories(): void;
public function test_modified_date_requires_a_24_hour_difference(): void;
public function test_public_entity_tags_exclude_reserved_operational_tags(): void;
public function test_operational_tag_slug_filter_can_extend_the_reserved_list(): void;
public function test_affiliate_policy_lookup_requires_a_published_unprotected_page(): void;
~~~

Use fixed 1, 225, and 226-word fixtures. Include block comments, HTML, and a shortcode in summary fixtures.

- [ ] Confirm the focused test is red.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHelpersTest.php
~~~

- [ ] Implement these signatures and exact rules:

~~~php
function mumega_motion_visible_text( $content );
function mumega_motion_reading_time( $content );
function mumega_motion_trim_words( $text, $limit );
function mumega_motion_card_summary( $post, $limit = 28 );
function mumega_motion_primary_category( $post_id, $menu_category_ids = array() );
function mumega_motion_has_meaningful_modified_date( $post_id );
function mumega_motion_newsletter_page();
function mumega_motion_affiliate_policy_page();
function mumega_motion_operational_tag_slugs();
function mumega_motion_public_entity_tags( $post_id );
~~~

Implementation contract:

1. Remove Gutenberg comments, shortcodes, HTML, and repeated whitespace from visible text.
2. Reading time is max(1, ceil(visible words / 225)).
3. Card summary uses a non-empty manual excerpt first; otherwise the first 28 visible words followed by an ellipsis only when truncated.
4. Primary category is the first assigned category appearing in Primary-menu order. Fallback is the alphabetically first assigned non-default category. Return null if none remains.
5. Show modified date only when post_modified_gmt is at least DAY_IN_SECONDS after post_date_gmt.
6. Newsletter lookup requests one published, non-password-protected page with slug newsletter and returns null otherwise.
7. Operational tag slugs default to `array( 'affiliate' )`, pass through the `mumega_motion_operational_tag_slugs` filter, and are normalized to unique non-empty slugs.
8. Public entity tags are the post's assigned native tags excluding every slug in the operational list; return an empty array when no public tags remain.
9. Affiliate policy lookup requests one published, non-password-protected page with slug `affiliate-disclosure` and returns null otherwise.

- [ ] Require the helper file from functions.php, run focused and full suites, then commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialHelpersTest.php
vendor/bin/phpunit -c phpunit.xml.dist
git add functions.php inc/editorial-helpers.php tests/bootstrap.php tests/EditorialHelpersTest.php
git commit -m "feat: add editorial content helpers"
~~~

### Task 3: Centralize Eligibility and Rail Discovery

**Files:**
- Create: inc/editorial-queries.php
- Create: tests/EditorialQueriesTest.php
- Modify: tests/bootstrap.php
- Modify: functions.php

- [ ] Add recorded, controllable doubles for get_posts, get_category_by_slug, get_categories, wp_get_nav_menu_items, get_nav_menu_locations, and wp_get_nav_menu_object.

- [ ] Write failing tests with these exact methods:

~~~php
public function test_base_query_is_published_posts_only_and_rejects_passwords(): void;
public function test_fallback_lead_excludes_release_posts(): void;
public function test_newest_sticky_post_can_override_release_exclusion(): void;
public function test_supporting_posts_reuse_the_shared_exclusion_list(): void;
public function test_menu_categories_preserve_order_and_ignore_non_categories(): void;
public function test_rails_require_three_eligible_posts_and_stop_at_three(): void;
public function test_no_menu_fallback_uses_six_largest_non_empty_categories(): void;
public function test_missing_special_category_returns_no_posts_without_querying(): void;
public function test_more_from_topic_posts_share_primary_category_and_exclude_current_post(): void;
~~~

- [ ] Implement these public functions:

~~~php
function mumega_motion_base_post_query_args( $overrides = array(), $used_ids = array() );
function mumega_motion_menu_category_ids();
function mumega_motion_select_lead_post( &$used_ids );
function mumega_motion_select_supporting_posts( &$used_ids, $limit = 3 );
function mumega_motion_select_rail_categories( $used_ids, $limit = 3 );
function mumega_motion_select_category_posts( $term_id, &$used_ids, $limit = 3 );
function mumega_motion_select_special_posts( $slug, &$used_ids, $limit );
function mumega_motion_select_more_from_topic_posts( $post_id, $term_id, $limit = 3 );
~~~

The base query must contain:

~~~php
array(
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'has_password'        => false,
    'ignore_sticky_posts' => true,
    'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
    'no_found_rows'       => true,
    'post__not_in'        => array_values( array_unique( array_map( 'intval', $used_ids ) ) ),
);
~~~

Selection policy:

1. Query the newest published sticky post first, without excluding Releases.
2. If no sticky post exists, query one post and exclude the releases term when that term exists.
3. Supporting posts use the same Release exclusion.
4. Append every chosen ID immediately to the one passed-by-reference used-ID list.
5. Accept menu items only when type is taxonomy, object is category, and object_id is positive.
6. Without a Primary menu, use get_categories with hide_empty true, number 6, orderby count, order DESC.
7. A rail qualifies only if a dry query returns three eligible posts after exclusions. Stop after three rails.
8. A missing convention term returns an empty array without a post query.
9. More-from-topic posts use the same eligibility policy, the chosen category, and exclude the current ID. They are category-derived and make no semantic-similarity claim.

- [ ] Require the module after helpers. Run tests and commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialQueriesTest.php
vendor/bin/phpunit -c phpunit.xml.dist
git add functions.php inc/editorial-queries.php tests/bootstrap.php tests/EditorialQueriesTest.php
git commit -m "feat: add deterministic editorial queries"
~~~

### Task 4: Register Theme Capabilities and Tokens

**Files:**
- Create: inc/editorial-setup.php
- Create: tests/EditorialSetupTest.php
- Create: theme.json
- Create: assets/css/editorial.css
- Create: assets/css/print.css
- Modify: functions.php
- Modify: tests/bootstrap.php

- [ ] Write failing tests that require primary and footer menu locations; title-tag, post-thumbnails, HTML5, responsive-embeds, align-wide, editor-styles, and wp-block-styles supports; print media for print.css; conditional editorial CSS; and conditional Motion. Create the two CSS entry files in this task so every enqueued runtime asset exists when the tests turn green.

- [ ] Move setup registration into inc/editorial-setup.php and keep its after_setup_theme hook.

- [ ] Add theme.json schema version 3 with contentSize 760px and wideSize 1240px. Define this palette: paper #f7f3ea, ink #171717, muted-ink #5f5b55, lavender #d8cdf7, rule #cbc4b8, white #ffffff. Define Editorial Serif as Iowan Old Style, Baskerville, Times New Roman, serif and Editorial Sans as Inter, ui-sans-serif, system-ui, sans-serif.

- [ ] Implement these asset helpers:

~~~php
function mumega_motion_is_editorial_view();
function mumega_motion_page_has_motion_mounts();
function mumega_motion_enqueue_editorial_styles();
function mumega_motion_enqueue_motion_assets();
~~~

Editorial view is true for the Editorial Home page template, singular posts, posts index, archives, search, and 404. Normal Elementor pages receive only style.css. Enqueue print.css with print media. Enqueue Motion only when the view declares mounts or the mumega_motion_enqueue_motion filter returns true.

- [ ] Run tests, lint, and commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialSetupTest.php
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpcs --standard=WordPress inc functions.php tests
git add functions.php inc/editorial-setup.php tests/bootstrap.php tests/EditorialSetupTest.php theme.json assets/css/editorial.css assets/css/print.css
git commit -m "feat: register editorial theme system"
~~~

### Task 5: Build the Semantic Site Shell

**Files:**
- Create: header.php
- Create: footer.php
- Create: page.php
- Create: 404.php
- Create: template-parts/empty-state.php
- Create: tests/EditorialShellTest.php
- Modify: assets/css/editorial.css
- Modify: index.php
- Modify: style.css
- Modify: tests/bootstrap.php

- [ ] Replace the demo document shell in index.php with get_header and get_footer. Remove the streaming demo from the production loop.

- [ ] First write and verify failing template-contract tests named `test_header_site_title_is_not_an_h1`, `test_header_starts_with_a_skip_link_and_calls_body_hooks`, `test_header_omits_empty_navigation_landmarks`, `test_page_templates_use_one_main_primary`, `test_legacy_page_modes_render_the_content_without_editorial_assets`, and `test_footer_calls_wp_footer`. Populate the legacy-page data provider with every exact page-template/Elementor mode recorded in Task 1. Build header.php in this order: wp_head/body hooks, first-focusable Skip to content link, linked non-heading site-title masthead, optional tagline, Primary menu, non-empty category fallback when no menu is assigned, native search, optional Subscribe link only when a published newsletter page exists, and a native details/summary mobile menu.

- [ ] Never emit an empty navigation landmark. Site title and tagline come only from WordPress identity settings.

- [ ] Build footer.php from site name, tagline, optional Footer menu, current year, and wp_footer. Include no plugin-sales CTA.

- [ ] Build page.php, index.php, and 404.php with one main#primary, normal WordPress loops, escaped headings, pagination, shared empty state, and search/category recovery links on 404.

- [ ] Keep only theme header plus universal base/fallback rules in style.css. Put editorial components in assets/css/editorial.css.

- [ ] Verify in a disposable WordPress environment with JavaScript disabled; do not use MCPWP.net.

- [ ] Run syntax, lint, tests, build, and commit.

~~~bash
find functions.php index.php header.php footer.php page.php 404.php inc template-parts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpcs --standard=WordPress functions.php index.php header.php footer.php page.php 404.php inc template-parts
vendor/bin/phpunit -c phpunit.xml.dist
npm run build
git add style.css assets/css/editorial.css header.php footer.php page.php index.php 404.php template-parts/empty-state.php tests/bootstrap.php tests/EditorialShellTest.php
git commit -m "feat: add semantic editorial shell"
~~~

### Task 6: Implement the Safe Editorial Home

**Files:**
- Create: page-templates/editorial-home.php
- Create: template-parts/lead-story.php
- Create: template-parts/content-card.php
- Create: template-parts/content-card-compact.php
- Create: template-parts/section-heading.php
- Create: template-parts/newsletter.php
- Create: tests/EditorialHomeTemplateTest.php
- Modify: assets/css/editorial.css
- Modify: tests/bootstrap.php

- [ ] Add this exact template header and no front-page.php:

~~~php
<?php
/**
 * Template Name: Editorial Home
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */
~~~

- [ ] First write and verify failing tests named `test_editorial_home_is_a_named_page_template`, `test_front_page_php_is_absent`, `test_home_selection_uses_one_shared_used_id_list`, `test_lead_heading_is_the_only_home_h1`, and `test_newsletter_render_restores_the_previous_global_post`.

- [ ] Initialize one shared list and select modules in this order:

~~~php
$used_ids    = array();
$lead        = mumega_motion_select_lead_post( $used_ids );
$supporting  = mumega_motion_select_supporting_posts( $used_ids, 3 );
$test_lab    = mumega_motion_select_special_posts( 'test-lab', $used_ids, 1 );
$rails       = mumega_motion_select_rail_categories( $used_ids, 3 );
$field_notes = mumega_motion_select_special_posts( 'field-notes', $used_ids, 5 );
~~~

For each rail, select its three posts with the same used-ID list before selecting the next rail.

- [ ] Use template-part arguments named post and menu_category_ids. Cards render an article, permalink, responsive image when present, primary category when present, title, summary, author, date, and reading time.

- [ ] Lead image is eager and high priority. All below-fold card images are lazy.

- [ ] Test Lab uses only the real term name, latest post image/title/excerpt/date, and post link. Do not extract numbers from prose or invent charts. Omit when empty.

- [ ] Each topic rail renders one visual card and two compact cards. Underfilled or unavailable rails are omitted without placeholders.

- [ ] Field Notes renders five dated items only when eligible posts exist.

- [ ] Render the newsletter page through the_content so an existing WPForms block works. Save the previous global post, setup the newsletter post, apply the_content, call wp_reset_postdata, then restore and setup the previous post. Omit the module if the page is missing.

- [ ] Apply data-motion=fade-in only to below-fold sections. Do not animate the lead H1 or lead image.

- [ ] Verify 320, 768, 1024, and 1440px layouts and commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpcs --standard=WordPress functions.php inc page-templates template-parts
find page-templates template-parts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
npm run build
git add page-templates template-parts assets/css/editorial.css tests/bootstrap.php tests/EditorialHomeTemplateTest.php
git commit -m "feat: add data-driven editorial homepage"
~~~

### Task 7: Implement Article and Listing Templates

**Files:**
- Create: single.php
- Create: home.php
- Create: archive.php
- Create: search.php
- Create: template-parts/article-meta.php
- Create: tests/EditorialContentTemplatesTest.php
- Modify: assets/css/print.css
- Modify: assets/css/editorial.css
- Modify: tests/bootstrap.php

- [ ] First write and verify failing template-contract tests named `test_single_has_one_content_h1`, `test_single_renders_primary_topic_and_public_entity_tags`, `test_operational_affiliate_tag_is_not_presented_as_an_entity`, `test_archive_renders_category_and_tag_descriptions`, `test_listing_templates_paginate`, and `test_single_labels_category_recommendations_more_from_this_topic`.

- [ ] Build single.php in this order: primary category, H1, manual excerpt, author, published date, modified date only after 24 hours, reading time, featured image/caption, Gutenberg content, author bio, public entity tags excluding operational-only presentation, affiliate disclosure, three **More from this topic** posts, previous/next navigation.

- [ ] Show this fallback disclosure only for posts tagged affiliate and only when the content does not already contain the Affiliate Disclosure pattern:

~~~text
This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.
~~~

Append a `Read our affiliate disclosure` link only when `mumega_motion_affiliate_policy_page()` returns a published page. Never create a guessed or dead URL. MCPWP launch must supply and verify that policy page before featuring an affiliate post.

- [ ] Do not add an automatic table of contents or theme schema.

- [ ] Build home.php, archive.php, and search.php with the shared cards, descriptive H1, pagination, and empty states. Category and tag archives render their term descriptions when non-empty; tag archives therefore work as public entity pages. Use native WordPress search.

- [ ] Print CSS hides navigation, search, newsletter, more-from-topic cards, motion decoration, and footer menus; prints article text black on white and displays external article-body link URLs.

- [ ] Verify with Yoast active in a disposable/preview environment that only Yoast emits canonical/schema data.

- [ ] Run all checks and commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpcs --standard=WordPress single.php home.php archive.php search.php inc template-parts
find single.php home.php archive.php search.php inc template-parts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
git add single.php home.php archive.php search.php template-parts/article-meta.php assets/css/editorial.css assets/css/print.css tests/bootstrap.php tests/EditorialContentTemplatesTest.php
git commit -m "feat: add editorial content templates"
~~~

### Task 8: Register Six Gutenberg Patterns

**Files:**
- Create: inc/editorial-patterns.php
- Create: tests/EditorialPatternsTest.php
- Modify: tests/bootstrap.php
- Modify: functions.php

- [ ] Add recording doubles for pattern category and pattern registration.

- [ ] Write failing tests for one mumega-motion/editorial category and exactly these slugs:

~~~text
mumega-motion/article-brief
mumega-motion/test-method
mumega-motion/evidence-table
mumega-motion/affiliate-disclosure
mumega-motion/correction-note
mumega-motion/newsletter-page
~~~

- [ ] Register only core-block markup on init when the pattern API exists.

Required contents:

| Pattern | Required structure |
|---|---|
| Article Brief | Summary; Key takeaways; table of contents block or linked heading list; Methodology; Sources; Corrections/update note |
| Test Method | Question; Environment; Models/tools tested; Procedure; Date; Limitations; Results |
| Evidence Table | Claim; Observation; Source; Confidence |
| Affiliate Disclosure | Same independent-editorial disclosure as single.php plus an editable link to the site's affiliate policy |
| Correction Note | Correction; visible date; previous claim; revised finding; explanation |
| Newsletter Page | Title; description; consent copy; standard form-block insertion area for the site's existing provider |

- [ ] Add no custom block and no automatic TOC.

- [ ] Run tests and commit.

~~~bash
vendor/bin/phpunit -c phpunit.xml.dist tests/EditorialPatternsTest.php
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpcs --standard=WordPress inc/editorial-patterns.php tests/EditorialPatternsTest.php
git add functions.php inc/editorial-patterns.php tests/bootstrap.php tests/EditorialPatternsTest.php
git commit -m "feat: add Gutenberg editorial patterns"
~~~

### Task 9: Harden Motion as Progressive Enhancement

**Files:**
- Create: inc/editorial-islands.php
- Create: src/js/index.test.js
- Modify: src/js/index.js
- Modify: src/components/FadeIn.jsx
- Modify: package.json
- Modify: assets/css/editorial.css
- Modify: functions.php

- [ ] Add scripts test:js as wp-scripts test-unit-js --runInBand and test as npm run test:js followed by npm run build.

- [ ] Write DOM tests proving: only explicit fade-in nodes mount; invalid numeric attributes use defaults; reduced-motion leaves original markup unchanged; one mount exception does not stop later nodes and restores failed markup; loading the bundle alone does not mount StreamingText.

- [ ] Export parseMotionNumber, shouldReduceMotion, mountFadeInNode, and mountMotionIslands from index.js. Cache each element's original innerHTML before mounting. On exception, restore it and set data-motion-failed=true.

- [ ] Keep StreamingText only behind explicit data-motion-stream. Do not use it on Editorial Home.

- [ ] Never hide motion targets in CSS before JS mounts. In reduced motion, collapse animation/transition duration and keep normal document flow.

- [ ] inc/editorial-islands.php may emit only allowlisted island names and JSON-encoded data. It accepts no executable code or arbitrary component name.

- [ ] Run tests/build and prove build/index.asset.php declares only the React and ReactDOM handles available in WordPress 6.5. Motion's small JSX adapter is bundled without bundling a second React implementation.

~~~bash
npm run test:js
npm run build
php -r '$a=require "build/index.asset.php"; if (array("react","react-dom") !== $a["dependencies"]) { exit(1); }'
vendor/bin/phpunit -c phpunit.xml.dist
git add package.json package-lock.json functions.php inc/editorial-islands.php src/js/index.js src/js/index.test.js src/components/FadeIn.jsx assets/css/editorial.css build/index.js build/index.asset.php
git commit -m "feat: harden editorial motion islands"
~~~

### Task 10: Complete Visual and Accessibility QA

**Files:**
- Modify: theme.json
- Modify: assets/css/editorial.css
- Modify: assets/css/print.css
- Modify: templates only as verified findings require

- [ ] Implement warm paper, ink, muted gray, lavender, serif headlines, sans body, thin rules, restrained cards, and bounded fluid type. Add no UI framework.

- [ ] Desktop lead desk uses a 7/5 grid and collapses below 800px without changing DOM order.

- [ ] Check one H1 per template, logical heading levels, semantic landmarks, card link names, alt text, and hidden decorative media.

- [ ] Keyboard-check skip link, menus, details/summary, search, cards, pagination, and forms. Focus indicators meet 3:1 contrast; text meets WCAG AA.

- [ ] Verify 200% zoom, 320px width, long titles/category names, missing images/excerpts/bios, empty results, reduced motion, and JavaScript blocked.

- [ ] Commit verified changes only.

~~~bash
git add theme.json assets/css/editorial.css assets/css/print.css header.php footer.php page.php index.php 404.php single.php home.php archive.php search.php page-templates template-parts
git commit -m "style: complete accessible editorial system"
~~~

### Task 11: Expand Packaging and CI

**Files:**
- Modify: scripts/package-theme.sh
- Modify: scripts/test-package-release.sh
- Modify: .github/workflows/edge-release.yml
- Modify: README.md

- [ ] First make package regression tests require all new root templates, theme.json, assets, page-templates, template-parts, and editorial inc files.

- [ ] Expand both package validation and copy allowlists to exactly:

~~~text
style.css
theme.json
functions.php
index.php
header.php
footer.php
page.php
single.php
home.php
archive.php
search.php
404.php
build
inc
assets
page-templates
template-parts
~~~

Keep all current dev-file rejection, symlink rejection, normalized timestamps, checksum, manifest, immutable release, and tag verification protections.

- [ ] Extend CI syntax scans and required-file assertions for new runtime paths. Do not loosen action pinning or release/tag checks.

- [ ] Document the reusable Editorial Home conventions, safe template selection, optional slugs, patterns, JavaScript-off behavior, and local verification. State that MCPWP and WPForms are optional.

- [ ] Run the package test plus two identical local packages. Remove dist and commit.

~~~bash
./scripts/test-package-release.sh
npm run package -- 0.1.988
shasum -a 256 dist/mumega-motion-theme-0.1.988.zip
npm run package -- 0.1.988
shasum -a 256 dist/mumega-motion-theme-0.1.988.zip
rm -rf dist
git add scripts/package-theme.sh scripts/test-package-release.sh .github/workflows/edge-release.yml README.md
git commit -m "build: package editorial theme runtime"
~~~

Expected: both printed SHA-256 values are identical.

### Task 12: Full Verification and Merge Gate

**Files:**
- Verify: all changed files
- Do not modify MCPWP.net

- [ ] Run the clean local gate:

~~~bash
verify_parent="$(mktemp -d "${TMPDIR:-/tmp}/mumega-motion-theme-verify.XXXXXX")"
verify_worktree="$verify_parent/worktree"
cleanup_verify_worktree() {
    git worktree remove --force "$verify_worktree" 2>/dev/null || true
    rmdir "$verify_parent" 2>/dev/null || true
}
trap cleanup_verify_worktree EXIT INT TERM
git worktree add "$verify_worktree" --detach HEAD
(
    cd "$verify_worktree"
    npm ci
    composer install --no-interaction --prefer-dist
    npm run test:js
    npm run build
    vendor/bin/phpunit -c phpunit.xml.dist
    vendor/bin/phpcs --standard=WordPress functions.php index.php header.php footer.php page.php single.php home.php archive.php search.php 404.php inc page-templates template-parts tests
    find functions.php index.php header.php footer.php page.php single.php home.php archive.php search.php 404.php inc page-templates template-parts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
    ./scripts/test-package-release.sh
    git diff --check
    git status --short
)
cleanup_verify_worktree
trap - EXIT INT TERM
~~~

- [ ] Install a local package in a disposable WordPress 6.5+ environment on PHP 7.4 and PHP 8.3. Test one site with convention categories and a second without them.

- [ ] Confirm Elementor pages remain intact, no front-page.php exists, and installing the theme does not change the selected static homepage.

- [ ] Verify 320/768/1024/1440px, JS on/off, reduced motion on/off, keyboard only, print preview, missing optional content, and WPForms inside a newsletter page.

- [ ] Inspect network/performance: no duplicate React, no client fetch for initial editorial content, responsive images, eager lead image, lazy below-fold images, and no unnecessary editorial assets on legacy pages.

- [ ] Request code review against the approved spec. Resolve evidence-backed findings and rerun the full gate.

- [ ] Open a PR from feat/mcpwp-editorial-system to master. Merge only after green CI and user approval. The merge publishes the existing edge channel; do not install it on MCPWP.net until the separate site-launch plan reaches its update gate.
