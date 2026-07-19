# MCPWP.net Editorial Site Launch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Configure MCPWP.net as the first production profile of the reusable Mumega Motion editorial system, migrate enough existing content to make the homepage credible, and switch the homepage with a one-setting rollback path.

**Architecture:** The theme remains generic. MCPWP-specific taxonomy, navigation, editorial copy, newsletter content, and homepage selection live in normal WordPress content and settings. All live work is draft-first and revision-preserving. The existing Elementor homepage stays published and unchanged until final acceptance.

**Tech Stack:** WordPress dashboard, Gutenberg, Mumega Motion edge update channel, MCPWP scoped WordPress operations where their discovered schemas are suitable, WPForms, Yoast, browser accessibility/performance tools.

## Global Constraints

- The read-only inspection portions of Tasks 1 and 2 may execute before theme implementation so compatibility requirements are known. Every export, live mutation, issue write, theme update, and approval-gated action waits until the theme implementation plan is complete, reviewed, merged, and available through a verified edge release.
- Never paste API keys into source, GitHub issues, screenshots, logs, or plan notes.
- Do not uninstall/deactivate plugins, delete categories, mass-change slugs, rewrite permalinks, or edit the existing Elementor homepage.
- Do not publish agent-authored content automatically. Create drafts and obtain editorial approval.
- Prefer MCPWP for scoped, inspectable WordPress operations; use the authenticated dashboard when a required MCPWP capability is unavailable or its schema is uncertain.
- Discover and read a tool schema before calling it. Do not guess MCPWP parameters.
- Preserve revisions and record before/after IDs, slugs, and URLs for every live mutation.
- Keep the old Elementor homepage published so rollback is a single Reading Settings change.
- Do not switch the homepage while Yoast is producing public-facing warnings or while accessibility, mobile, form, link, and query gates are unresolved.
- Use the site timezone and verify all scheduled/published dates in WordPress.
- This plan changes site content and settings; pause at every explicit approval gate.

## Operational State to Preserve

- Current active theme: Mumega Motion.
- Current homepage: Elementor page titled MCPWP - WordPress MCP Server for AI Site Operations.
- Existing corpus: 57 published posts plus drafts; existing URLs remain stable.
- Existing categories include Compare, Field Notes, For Agencies, Governance & Trust, Integrations, MCP Guides, Releases, Tutorials, and Use Cases.
- Existing plugins include MCPWP, Elementor/Pro, Yoast, WPForms, and analytics plugins.
- Known separate issue: Yoast admin warnings concerning wpseo_premium.
- Known separate decision: analytics consolidation is not part of homepage construction.

---

### Task 1: Freeze a Recoverable Pre-Launch Baseline

**Files / Records:**
- Create locally: docs/superpowers/evidence/mcpwp-launch/preflight.md
- Create locally: docs/superpowers/evidence/mcpwp-launch/reading-settings-before.png
- Create locally: docs/superpowers/evidence/mcpwp-launch/plugin-state-before.png
- Create locally: docs/superpowers/evidence/mcpwp-launch/homepage-before.png
- Do not commit exports containing private user/form/order data

- [ ] Inspect the live site read-only and record: active theme/version, WordPress version, PHP version when visible, active plugin versions, homepage ID/title/URL, posts-page setting, permalink structure, site title/tagline, assigned menu locations, category IDs/slugs/counts, published newsletter page if any, and available WPForms forms. For the current homepage and representative key Elementor pages, also record the assigned WordPress page-template slug, Elementor template/canvas mode when visible, and whether the active theme supplies their outer header/footer.

- [ ] Export WordPress posts, pages, categories, tags, authors, and media references through Tools → Export → All content. Store the XML outside the repository in a dated private backup location.

- [ ] Capture the three baseline screenshots and write preflight.md with exact IDs and settings. Redact API keys, email addresses not already public, tokens, user lists, and form submissions.

- [ ] Verify the existing theme rollback/update path reports the current installed version and a usable backup state. Do not trigger an update yet.

- [ ] Record the old homepage ID as the rollback target. The plan may not proceed without it.

- [ ] Commit only the redacted Markdown and safe screenshots.

~~~bash
git add docs/superpowers/evidence/mcpwp-launch/preflight.md docs/superpowers/evidence/mcpwp-launch/*.png
git commit -m "docs: record MCPWP launch baseline"
~~~

### Task 2: Resolve Public-Launch Blockers Without Broad Plugin Changes

**Records:**
- Modify: docs/superpowers/evidence/mcpwp-launch/preflight.md
- No theme code changes

- [ ] Reproduce the Yoast wpseo_premium warning in the exact admin view where it occurs. Record whether it appears in public HTML, REST responses, only the plugin screen, or PHP logs.

- [ ] If public output or REST responses contain the warning, create a separate GitHub issue with sanitized evidence and block launch until fixed. Do not patch Yoast or edit its database options blindly.

- [ ] If it is admin-only and public output is clean, record the scope and keep the operational issue open; it does not block private homepage preview.

- [ ] List analytics emitters visible in page source/network. Do not disable any. Open a separate consolidation issue if duplicate pageview events are confirmed.

- [ ] Verify the site has a working privacy/cookie approach for the existing analytics and newsletter form. Treat legal copy as owner-approved site content, not theme-generated text.

- [ ] Verify a published, non-password-protected page with slug `affiliate-disclosure` exists and contains owner-approved policy copy before any affiliate-tagged article is featured. Confirm the article fallback links to its canonical URL and emits no dead link when tested without the page.

- [ ] Approval gate: the user explicitly accepts the blocker classification before the theme update.

### Task 3: Install the Verified Editorial Theme Release Safely

**Records:**
- Modify: docs/superpowers/evidence/mcpwp-launch/preflight.md
- No direct server file edits

- [ ] Confirm the feature PR is merged to master and GitHub Actions is green on PHP 7.4 and 8.3.

- [ ] Read the immutable edge release manifest and record its release tag, version, SHA-256, requires_wp, requires_php, and triggering commit. Confirm the archive name and checksum match the release assets.

- [ ] Through MCPWP, discover the explicit Mumega Motion update and rollback tool schemas. Confirm the update operation is admin-scoped, targets only mumega-motion-theme, and reports the verified manifest before installing.

- [ ] Trigger the explicit theme update. If MCPWP discovery or authorization is unavailable, use Appearance → Themes/Updates with the same verified package; do not upload an unverified ZIP.

- [ ] Immediately verify:
  - installed version equals the manifest;
  - current homepage ID is unchanged;
  - the old Elementor homepage looks the same;
  - Elementor-authored pages still render;
  - wp-admin remains accessible;
  - the update system reports a rollback backup;
  - no front-page.php exists in the package contract.

- [ ] On any regression, trigger the tested rollback operation, verify the prior version/homepage, record evidence, and stop. Do not debug on production while the broken package remains active.

- [ ] Approval gate: user confirms the existing live homepage is unchanged after the update.

### Task 4: Create the MCPWP Editorial Taxonomy and Navigation

**WordPress objects to create:**

| Name | Slug | Purpose |
|---|---|---|
| WordPress + AI | wordpress-ai | Main WordPress/AI intersection |
| MCP | mcp | Model Context Protocol |
| AI Visibility | ai-visibility | Discovery, citations, AI search |
| Test Lab | test-lab | Reproducible tests and research |
| Tools & Reviews | tools-reviews | Comparisons and reviews |
| Guides | guides | Instructional content |

- [ ] Inspect for slug/name collisions before creating any term. Reuse a matching existing term only when both semantics and slug are correct.

- [ ] Create missing categories through normal WordPress category operations. Give each a concise human-readable description used by archive pages and section headings.

- [ ] Treat these Primary-menu categories as durable topic hubs. Their descriptions define the hub's editorial purpose, scope, and inclusion boundary.

- [ ] Audit existing tags for collisions, spelling variants, and operational markers before introducing controlled public entity tags. Do not expose `affiliate` as a knowledge entity.

- [ ] Preserve all existing categories. Do not delete General or rename old slugs during launch.

- [ ] Create a new menu named MCPWP Editorial Primary rather than rewriting the current live menu. Add category links in this exact order:

~~~text
WordPress + AI
MCP
AI Visibility
Test Lab
Tools & Reviews
Guides
~~~

- [ ] Do not assign the new menu to Primary yet. Create or update a separate Footer menu using useful existing pages only; omit empty and sales-only links that conflict with the publication position.

- [ ] Record new term IDs and menu ID in preflight.md.

- [ ] Verify category archives resolve, contain no PHP warnings, and use canonical URLs from Yoast.

### Task 5: Curate the Minimum Viable Editorial Corpus

**WordPress content:**
- Modify assignments, excerpts, featured images, sticky state, and draft content only
- Preserve post slugs and permalinks

- [ ] Produce a spreadsheet or Markdown inventory of published posts with ID, title, URL, date, modified date, existing categories, proposed primary topic, proposed public entity tags, proposed secondary format/series, contextual internal outbound links, orphan status, hub/cornerstone role, excerpt status, featured image status, currency risk, duplicate/canonical risk, affiliate status, and launch decision.

- [ ] Apply this mapping without removing old categories in the first pass:

| Existing category | Add |
|---|---|
| MCP Guides | MCP; also Guides when instructional |
| Tutorials | Guides plus the relevant topic |
| Integrations | WordPress + AI or MCP |
| Compare | Tools & Reviews |
| Use Cases | WordPress + AI |
| Governance & Trust | MCP or WordPress + AI as primary; retain existing as secondary |
| For Agencies | Relevant topic; retain existing as secondary |
| Field Notes | Relevant topic when clear; retain Field Notes |
| Releases | Keep Releases; do not add to a homepage topic merely for volume |

- [ ] Assign enough genuinely relevant, current posts for at least three primary-menu categories to have three eligible posts each. Do not miscategorize posts to fill a layout.

- [ ] Select one strong, current non-Release article as the intended lead. Make it sticky only after reviewing title, excerpt, author, dates, image, links, claims, and disclosure.

- [ ] Select three supporting stories with no topical duplication and current information.

- [ ] Review cornerstone candidates covering WordPress MCP, security/governance, MCP versus REST, plugin comparisons, and AI visibility. Update or draft them; do not publish incomplete rewrites.

- [ ] Any title containing an old year must either receive a substantive 2026 review/update or remain outside the featured launch set.

- [ ] Reviews/comparisons require visible methodology and affiliate disclosure before being featured.

- [ ] Do not merge duplicate posts during initial launch. Record canonical/redirect proposals in a separate issue because redirects require independent validation.

- [ ] Add contextual internal links only where they help a reader move to a prerequisite, source, topic hub, entity archive, or next action. Preserve canonical URLs and prohibit link stuffing.

- [ ] Approval gate: user reviews the proposed lead, supporting stories, three rail categories, and any Test Lab/Field Notes content before publication or sticky changes.

### Task 6: Create Newsletter and Draft Editorial Homepage

**WordPress objects:**
- Create/update: page slug newsletter
- Create: private or draft page MCPWP Editorial Home
- Keep old homepage unchanged

- [ ] If no published newsletter page exists, create a draft page titled The MCPWP Brief with slug newsletter using the theme's Newsletter Page pattern.

- [ ] Insert the site's existing appropriate WPForms form through its Gutenberg block. If no appropriate form exists, configure one in WPForms with at least email, clear consent language, success/error messages, and the owner-approved storage/integration. Do not create a custom theme form.

- [ ] Test invalid email, required consent if present, successful submission, duplicate behavior, confirmation, delivery/integration, keyboard use, and privacy link. Delete test subscriber records after verification when appropriate.

- [ ] Publish newsletter only after form acceptance. Confirm the theme's Subscribe link appears and targets the canonical page.

- [ ] Create a new page titled MCPWP Editorial Home. Keep it private or draft. Assign Template: Editorial Home. Do not place Elementor content on it.

- [ ] Temporarily assign MCPWP Editorial Primary to the theme's Primary menu location only after confirming the old Elementor homepage remains usable with that header. If this would disrupt the old homepage, schedule the assignment for the final switch and preview the new page with a safe temporary method.

- [ ] Preview the draft/private Editorial Home as an authorized user. Confirm every module is populated only from real WordPress content and absent optional modules collapse cleanly.

### Task 7: Run the Private Acceptance Gate

**Records:**
- Create: docs/superpowers/evidence/mcpwp-launch/acceptance.md
- Create safe screenshots for desktop/mobile/JS-off/reduced-motion

- [ ] Content checks:
  - lead and supporting posts do not repeat;
  - later modules do not repeat earlier posts;
  - Releases appear only when deliberately sticky;
  - category labels reflect Primary-menu order;
  - Test Lab contains real authored methodology;
  - Field Notes and newsletter omit cleanly if unavailable;
  - all featured claims are current and sourced.

- [ ] Template checks:
  - article anatomy is complete;
  - archive, search, page, 404, and post index work;
  - author/date/modified/reading-time rules are correct;
  - affiliate disclosure appears where required;
  - More from this topic posts exclude the current post;
  - topic hubs show their descriptions and current posts;
  - public entity-tag archives resolve and explain the entity when descriptions exist;
  - contextual internal links use canonical URLs and no featured article is orphaned;
  - print view is readable.

- [ ] Compatibility checks:
  - old Elementor homepage and key Elementor pages are unchanged;
  - WPForms validation and submission work;
  - Yoast owns canonical/schema;
  - MCPWP authenticated operations still initialize and discover tools;
  - theme update/rollback status is healthy.

- [ ] Responsive/accessibility checks at 320, 768, 1024, and 1440px; 200% zoom; keyboard only; screen-reader landmark/heading inspection; visible focus; AA contrast; no horizontal scroll.

- [ ] Progressive enhancement checks with JavaScript blocked and prefers-reduced-motion enabled. Essential text, links, search, navigation, and form remain usable.

- [ ] Performance checks on the private preview: no initial-content API fetch, no duplicate React, lead image eager, below-fold images lazy, no avoidable layout shift, no uncaught console errors, and acceptable Core Web Vitals lab results.

- [ ] Link-check all homepage and sample-article links. Verify no draft/private URL leaks into public navigation.

- [ ] Record pass/fail evidence and exact remaining defects. Any failed critical check blocks the switch.

- [ ] Approval gate: user signs off on the private preview and acceptance.md.

### Task 8: Switch with a One-Setting Rollback

**Live settings:**
- Reading Settings homepage
- Primary/Footer menu locations
- No deletions

- [ ] Choose a low-traffic launch window and confirm an administrator is available.

- [ ] Reconfirm old homepage ID, new homepage ID, latest export location, current theme version, and rollback operation.

- [ ] Publish MCPWP Editorial Home if still private/draft.

- [ ] Assign MCPWP Editorial Primary and the prepared Footer menu to their registered locations.

- [ ] Change Settings → Reading → Homepage to MCPWP Editorial Home. Do not change the Posts page setting unless acceptance testing proved a specific need.

- [ ] Purge only relevant WordPress/host/CDN caches using known safe controls. Do not change Cloudflare WAF/bot rules as part of this launch.

- [ ] Test logged-out homepage, search, newsletter, one article, one archive, one Elementor legacy page, wp-admin, and MCPWP initialization immediately.

- [ ] If any critical failure occurs, restore the old homepage ID in Reading Settings first. If the regression is theme-wide, then invoke theme rollback. Restore old menu assignments only if needed. Verify recovery and stop.

- [ ] Record exact launch time, new homepage ID, theme version, menu IDs, cache action, test results, and whether rollback was used.

### Task 9: Observe, Learn, and Prepare Agent Operations

**Records / Issues:**
- Update: docs/superpowers/evidence/mcpwp-launch/acceptance.md
- Create GitHub issues for verified follow-ups

- [ ] Monitor 404s, PHP errors, form errors, search behavior, page performance, and duplicate analytics events during the first 24 hours and first 7 days.

- [ ] Check Search Console/indexing, Yoast sitemap inclusion, and real-user engagement when data becomes available. Do not infer success from one analytics tool if duplicate emitters remain.

- [ ] Keep publishing draft-first. Initial agent roles may research, prepare briefs, audit links/claims, draft posts, and propose updates. Publishing remains a human approval action.

- [ ] Open narrowly scoped issues for verified follow-ups: Yoast warning, analytics consolidation, redirect/canonical cleanup, custom interactive Test Lab islands, intelligent search, content automation, and optional Mumega/Mupot agent orchestration.

- [ ] Keep backlink extraction, edge-aware related-content ranking, graph visualization, and graph-oriented MCP tools as later issues after the native topic/entity/link model is stable.

- [ ] Do not add broad theme-file editing or automatic publishing tools merely to accelerate operations.

- [ ] After 7 days, review: subscription conversion, homepage engagement, search queries, article freshness defects, form errors, crawl/index status, performance, and editorial workflow friction. Feed reusable findings into Mumega Motion; keep MCPWP-specific content decisions in the site profile.
