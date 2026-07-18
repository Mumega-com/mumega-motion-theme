# MCPWP editorial system for Mumega Motion

**Date:** 2026-07-18  
**Status:** Approved visual direction; implementation plan pending user review  
**Repository:** `Mumega-com/mumega-motion-theme`  
**Site:** `https://mcpwp.net`  
**Implementation branch:** `feat/mcpwp-editorial-system`

## Objective

Turn Mumega Motion into a reusable, AI-first WordPress editorial system, with MCPWP.net as its first production implementation and reference site.

MCPWP must read as an independent media and research property, not as a landing page for the MCPWP or Mumega MCP plugin. The publication should help WordPress owners, agencies, developers, and content teams understand and adopt AI without abandoning their existing sites.

The primary launch conversion is subscription to **The MCPWP Brief**. Authority and audience growth come before product promotion.

## Product and strategy model

The project has three distinct layers:

| Layer | Role |
|---|---|
| Mumega Motion | Reusable presentation and interaction system for AI-first WordPress publications |
| MCPWP.net | First site profile, editorial brand, content corpus, and production proving ground |
| MCPWP / Mumega MCP plugin | Optional agent interface for controlled content operations, theme updates, and rollback |

MCPWP.net is the first sample, not the only site the theme can render. Theme components must use WordPress content, menus, taxonomies, and site identity rather than hard-coded MCPWP labels or database IDs.

The strategic loop is:

1. Humans and agents create structured content in WordPress.
2. MCPWP applies normal WordPress permissions and scoped operations.
3. Mumega Motion renders complete, machine-readable editorial pages.
4. React and Motion enhance the parts that benefit from interaction.
5. MCPWP.net supplies real production feedback that improves the reusable theme.

AI-first does **not** mean JavaScript-first or automatically generated. It means the system is structured for human and agent collaboration, machine-readable retrieval, explicit evidence, controlled operations, and interactive AI-era experiences without sacrificing normal WordPress publishing.

## Approved visual direction

The approved direction combines:

- **The Editorial Desk:** a balanced publication homepage with a lead investigation, supporting stories, topic rails, research results, field notes, and newsletter conversion.
- **The Research Journal:** restrained typography, explicit methodology, visible authorship, and fewer unsupported claims.

![Approved MCPWP editorial homepage concept](assets/2026-07-18-mcpwp-editorial-homepage-concept.png)

The image is a visual reference, not a pixel-perfect contract. The implementation must preserve its hierarchy, editorial character, and restrained palette while using real WordPress content and accessible responsive behavior.

## Existing state and constraints

The live audit on 2026-07-18 found:

- Mumega Motion is the active theme.
- The live static homepage is an Elementor page titled `MCPWP - WordPress MCP Server for AI Site Operations`.
- The site contains 64 posts: 57 published and 7 drafts.
- The site contains 49 pages: 42 published and 7 private.
- Existing categories are Compare, Field Notes, For Agencies, Governance & Trust, Integrations, MCP Guides, Releases, Tutorials, and Use Cases. The empty General category is not useful editorially.
- MCPWP 3.6.1, Elementor, Elementor Pro, Yoast SEO, WPForms, Site Kit, MonsterInsights, PostHog, WooCommerce, LearnPress, and several hosting/support plugins are active.
- The Yoast plugin screen emits PHP warnings about a missing `wpseo_premium` option. That defect requires a separate operational fix before public launch; it is not theme behavior.
- Multiple analytics plugins are active. Analytics consolidation is an operational follow-up, not part of the theme implementation.
- Mumega Motion already provides progressive-enhancement Motion components, Figma token consumption, and a verified GitHub edge-release/update channel.

The implementation must preserve existing Elementor pages and must not deactivate, uninstall, rewrite, or delete plugins or content.

## Chosen architecture

### Gutenberg content, PHP rendering, React/Motion islands

Mumega Motion remains a classic PHP theme with `theme.json` design tokens and Gutenberg as the content-authoring surface. The first release is not a full Site Editor theme.

PHP owns deterministic, semantic, server-rendered templates and WordPress queries. Gutenberg owns article bodies and reusable editorial patterns. React and Motion are isolated enhancements mounted onto server-rendered markup; they never own the canonical article, navigation, or homepage content.

Use React/Motion only for capabilities that benefit materially from client-side state or animation, including interactive comparisons, Test Lab visualizations, intelligent search interfaces, live testing tools, and streaming demonstrations. Ordinary cards, menus, article text, forms, and links do not require React.

This architecture was selected over:

- **A full block-theme conversion:** flexible but unnecessarily broad for the first editorial release and more vulnerable to accidental template edits.
- **An Elementor rebuild:** faster for one page but inconsistent with the theme's reason for existing, harder to govern through agents, and less deterministic.
- **A full React application:** duplicates WordPress rendering and makes essential content dependent on a client runtime.
- **An Astro/headless frontend:** adds a second deployment, routing, preview, authentication, and synchronization system while weakening direct WordPress plugin compatibility and making the existing theme update channel irrelevant.

### Safe homepage rollout

The first editorial release must **not** add `front-page.php`. Because Mumega Motion is already active, adding that file would replace the live homepage immediately when the theme update is installed.

Instead, the theme adds a named page template at `page-templates/editorial-home.php`.

Rollout sequence:

1. Install the theme update without changing the current homepage.
2. Create a new WordPress page named `MCPWP Editorial Home`.
3. Assign the Editorial Home page template.
4. Preview the page while it is draft or private.
5. Validate desktop, mobile, accessibility, performance, links, queries, and empty states.
6. Publish the page.
7. Change **Settings → Reading → Homepage** to the new page.

Rollback requires only restoring the previous Elementor page as the static homepage. The existing page remains published and untouched.

## Theme boundaries

### Mumega Motion owns

- Page composition and visual hierarchy.
- Header, footer, homepage, article, archive, search, page, and error templates.
- Responsive layout, typography, colors, focus states, and print styles.
- Editorial query selection and duplicate exclusion.
- Reusable Gutenberg patterns for consistent article structure.
- Stable mount points and data contracts for optional React/Motion islands.
- Progressive-enhancement motion and reduced-motion behavior.
- Graceful empty states and plugin-independent fallbacks.

### WordPress core owns

- Posts, pages, authors, excerpts, featured images, categories, tags, sticky state, menus, and media.
- Content editing through Gutenberg.
- Homepage selection through Reading Settings.

### Other plugins and services own

- **MCPWP:** authenticated agent operations and explicit theme update/rollback tools.
- **Yoast:** canonical URLs, sitemaps, and schema after its existing warning is fixed. The theme must not emit competing Article schema.
- **WPForms on MCPWP.net:** subscriber validation, storage/integration, consent, and form errors. Forms are site content, not theme architecture.
- **Analytics platform:** tracking and consent. The theme provides stable semantic hooks but no analytics vendor code.
- **Elementor:** legacy product and documentation pages during migration.

No new plugin is required for the first editorial theme release. The existing WPForms installation is sufficient for the MCPWP sample.

## MCPWP operating contract

MCPWP is the optional control layer around normal WordPress data, not a rendering dependency.

For the first editorial release, agents use MCPWP's standard scoped WordPress capabilities to:

- inspect posts, pages, categories, tags, menus, media, and site configuration;
- create and update drafts;
- assign categories, excerpts, featured media, and sticky state;
- preview content before publication;
- perform the existing explicit, admin-scoped Mumega Motion update and rollback operations.

Mumega Motion must not add a second content API or require special MCP-only post storage. A human editor using the WordPress dashboard and an authorized agent using MCPWP operate on the same posts, blocks, taxonomies, and revision history.

The first release does not add automatic publishing or broad theme-file editing tools. Agent-authored content remains draft-first; publication follows the site's existing WordPress capability and editorial approval model.

## Editorial information architecture

### MCPWP site profile

MCPWP.net configures this primary navigation through a normal WordPress menu:

1. **WordPress + AI**
2. **MCP**
3. **AI Visibility**
4. **Test Lab**
5. **Tools & Reviews**
6. **Guides**

MCPWP.net creates these categories with stable slugs:

| Section | Slug |
|---|---|
| WordPress + AI | `wordpress-ai` |
| MCP | `mcp` |
| AI Visibility | `ai-visibility` |
| Test Lab | `test-lab` |
| Tools & Reviews | `tools-reviews` |
| Guides | `guides` |

These names and slugs belong to the MCPWP site profile, not to the reusable theme engine.

### Reusable theme discovery rules

- The theme registers `primary` and `footer` menu locations.
- The Editorial Home discovers topic rails from category menu items in the assigned Primary menu, preserving menu order.
- Custom links and page links remain in navigation but are not treated as category rails.
- The first three menu categories with at least three eligible posts become the topic rails.
- A category with slug `test-lab` activates the optional research-feature module.
- A category with slug `field-notes` activates the optional dated-notes module.
- A category with slug `releases` is excluded from automatic lead/supporting selection unless one of its posts is sticky.
- A published page with slug `newsletter` activates the optional newsletter module and Subscribe link.
- Sites without any of these optional convention slugs still render a complete lead desk, topic rails, and footer.
- Site name, tagline, menus, category names, excerpts, page content, and media supply all public text. Theme PHP must not hard-code `MCPWP`, `WordPress + AI`, `MCP`, author names, article titles, benchmark values, or newsletter copy.

### Existing-category migration

The existing corpus is migrated without changing URLs:

| Existing category | Editorial treatment |
|---|---|
| MCP Guides | Add MCP; add Guides when the article is instructional |
| Tutorials | Add Guides and the relevant topic section |
| Integrations | Add WordPress + AI or MCP according to the article |
| Compare | Add Tools & Reviews |
| Use Cases | Add WordPress + AI |
| Governance & Trust | Retain as a secondary category; also assign a primary topic |
| For Agencies | Convert to an audience tag or retain as a secondary landing category |
| Field Notes | Retain as a recurring editorial series, not a top navigation section |
| Releases | Keep as a secondary product-update archive, excluded from the main homepage unless manually featured |
| General | Stop assigning; remove only after confirming no content depends on it |

Posts may belong to one topic section and one format/series. Migration must not mass-change slugs or permalinks.

### Corpus cleanup

Content work is a separate editorial operation but is required before the public switch:

- Select and revise cornerstone articles about WordPress MCP, security, governance, MCP versus REST, plugin comparisons, and AI visibility.
- Merge overlapping plugin-comparison and connection-tutorial articles only after choosing canonical URLs and preparing permanent redirects.
- Remove outdated years from titles or update the underlying research; for example, a 2026 site must not promote an unreviewed `Best WordPress MCP Plugin 2025` article as current.
- Move product announcements out of the primary editorial stream.
- Add methodology and affiliate disclosure to reviews and comparisons.
- Preserve transparent Field Notes that demonstrate real testing, failures, and corrections.

## Homepage composition and data flow

The Editorial Home template is fully server-rendered and data-driven. Editors do not rebuild its layout.

### 1. Publication header

- Text masthead rendered from the WordPress site title; MCPWP.net sets that title to `MCPWP`.
- Primary navigation menu.
- Native WordPress search form; it remains usable without JavaScript.
- Persistent Subscribe link to the published page using the `newsletter` convention, when present.
- Mobile navigation that works with keyboard and screen readers.
- If no primary menu is assigned, fall back to the six non-empty categories with the highest post counts; never emit an empty navigation landmark.

### 2. Lead desk

- The newest published sticky post is the lead story.
- If no sticky post exists, the newest published non-Release post is the fallback.
- The lead displays featured image when available, primary category, title, excerpt, author, published/updated date, and estimated reading time.
- Three supporting stories use the newest eligible posts excluding the lead and excluding each other.
- Product Releases are excluded unless a Release post is deliberately sticky.
- A sticky Release is therefore an explicit editorial override and remains eligible.

### 3. AI Visibility Lab

- The module title comes from the category named by the optional `test-lab` convention; MCPWP.net names that category Test Lab and introduces it editorially as the AI Visibility Lab.
- Uses the newest post in `test-lab` as the featured experiment.
- Displays the experiment's featured image, title, excerpt, date, and a link to its visible methodology.
- The first release does not invent or store benchmark data in theme options.
- Charts and numeric results must be authored as visible post content or media; the theme does not infer values from prose.
- If Test Lab has no published content, the entire module is omitted without leaving an empty heading.

### 4. Topic rails

- Uses the first three eligible category items from the Primary menu in menu order.
- Each rail shows one visual lead and two compact links.
- A post already used above is excluded from every later module.
- A missing or undersupplied category causes the layout to redistribute available rails rather than show placeholders.

### 5. Latest Field Notes

- When the optional `field-notes` category exists, shows its five newest posts.
- Emphasizes dates, titles, short descriptions, and reading time.
- Omits the section if no eligible posts exist.

### 6. Newsletter module (The MCPWP Brief on MCPWP.net)

- Dark, visually distinct newsletter module near the bottom of the page. MCPWP.net calls it The MCPWP Brief; other sites use their Newsletter page title.
- The theme does not store subscribers.
- MCPWP.net creates one published Gutenberg page with slug `newsletter`, containing its title, description, consent copy, and existing WPForms block.
- The template renders that page's block content inside the homepage newsletter module while preserving and restoring homepage global post state.
- If no published Newsletter page exists, the module is omitted. Forms are not a blocker for theme development, but MCPWP public launch requires the page and a working form.

### 7. Publication footer

- Short publication description drawn from the WordPress site tagline.
- Editorial Standards, Corrections Policy, Affiliate Disclosure, Privacy Policy, Terms, About, and Contact links.
- Secondary resource links and social links supplied through WordPress menus.
- No plugin sales CTA in the global footer.
- Policy/resource links are menu-managed and render only when assigned, preventing dead placeholder links.

### Query invariants

- No post appears twice on the homepage.
- Only published posts are eligible.
- Password-protected posts are excluded.
- Sticky lead selection is deterministic.
- All post IDs already rendered are passed to later queries through one explicit exclusion list.
- Category lookup failures return an empty result and never a fatal error.
- All query state is reset after each module.
- The primary category shown on cards and articles is the first assigned category that also appears as a category item in the Primary menu. If none match, use the alphabetically first assigned category other than the site's default category.

## Article experience

The `single.php` template provides:

- Primary section label.
- One H1 title.
- Excerpt as an answer-first summary when present.
- Visible author, published date, modified date when at least 24 hours later than publication, and reading time.
- Featured image with WordPress responsive image attributes.
- A readable article column with optional wide media.
- Author biography.
- Related stories chosen from the primary category and excluding the current post.
- A global affiliate-disclosure link and visible disclosure block when the post has the `affiliate` tag.
- Previous/next navigation where appropriate.

The first release does not auto-generate a table of contents. Authors and agents may insert a supplied Article Brief Gutenberg pattern containing:

- Summary.
- Key takeaways.
- Table of contents block or linked heading list.
- Methodology.
- Sources.
- Corrections/update note.

This avoids brittle server-side rewriting of arbitrary heading markup.

Reading time is the ceiling of visible article words divided by 225 words per minute, with a minimum of one minute. Card summaries use the manual excerpt when present; otherwise they use the first 28 visible words after stripping blocks, shortcodes, and HTML.

## Reusable Gutenberg patterns

The theme registers patterns, not custom content storage:

1. **Article Brief:** summary, key takeaways, and contents.
2. **Test Method:** question, environment, models/tools tested, procedure, date, limitations, and results.
3. **Evidence Table:** claim, observation, source, and confidence.
4. **Affiliate Disclosure:** standardized disclosure text and policy link.
5. **Correction Note:** dated correction with the previous claim and revised finding.
6. **Newsletter Page:** title, description, consent copy, and a standard form-block insertion area. MCPWP.net inserts its existing WPForms block.

Patterns use core blocks so content remains portable if the theme changes.

## Template and component structure

The implementation should create focused template units rather than one large `index.php`:

```text
header.php
footer.php
page.php
single.php
archive.php
search.php
404.php
home.php
page-templates/
  editorial-home.php
template-parts/
  content-card.php
  content-card-compact.php
  lead-story.php
  section-heading.php
  newsletter.php
  article-meta.php
  empty-state.php
inc/
  editorial-setup.php
  editorial-queries.php
  editorial-patterns.php
  editorial-helpers.php
  editorial-islands.php
assets/
  css/
    editorial.css
    print.css
theme.json
```

This file map is the implementation contract. Additional test fixtures and development-only files may be added without changing these production responsibilities.

## Visual system

### Character

- Serious independent technology publication.
- Warm paper background, near-black ink, muted gray, and restrained lavender accent.
- Editorial serif for major headlines; clean sans serif for interface text and body copy.
- Thin rules and spacing establish hierarchy; cards are used sparingly.
- Photography, diagrams, screenshots, and test artifacts should look reported, not generated for decoration.

### Responsive behavior

- Desktop lead desk uses an asymmetric main-and-supporting grid.
- Tablet reduces the supporting column without hiding metadata.
- Mobile becomes a single reading order: lead, supporting stories, Lab, topic rails, Field Notes, newsletter.
- Navigation collapses to an accessible disclosure/menu control.
- No horizontal scrolling at 320 CSS pixels.

### Motion

- Motion may animate section entrance, card sequencing, and navigation transitions.
- Content and controls exist and work before JavaScript.
- `prefers-reduced-motion: reduce` removes nonessential animation.
- StreamingText is not used on the editorial homepage.
- Motion must not move layout after interaction or delay access to article links.
- Each React/Motion island has one bounded mount element, a documented server-rendered fallback, and JSON-safe `data-*` input owned by its PHP template part.
- Islands share WordPress core's React instance through the theme's existing build system; the theme must not bundle another React copy.
- Failure to mount an island leaves the server-rendered fallback visible and functional.

## Accessibility, performance, and machine readability

### Accessibility

- Semantic `header`, `nav`, `main`, `article`, `section`, `aside`, and `footer` landmarks.
- One H1 per page and ordered heading levels.
- Visible skip link and focus indicators.
- Keyboard-operable menus, search, forms, and links.
- WCAG 2.2 AA color contrast.
- Meaningful alt text comes from the WordPress media field; decorative images use empty alt text.
- Form errors and labels are owned by the newsletter provider but must fit the theme visibly.

### Performance

- All critical editorial content is present in the initial HTML response.
- Below-the-fold images use native lazy loading; the lead image is not lazy-loaded.
- Responsive image sizes prevent oversized downloads.
- Editorial CSS is loaded only on relevant front-end templates.
- No new frontend framework or bundled React copy is added.
- The existing Motion bundle remains the only JavaScript enhancement dependency.
- Launch targets: no console errors, no PHP warnings from theme code, and Core Web Vitals in the “good” range under representative production testing.

### Search and AI visibility

- Server-rendered article text and navigation.
- Clear answer-first summaries and visible primary sources.
- Stable author identity, publication dates, modification dates, and correction notes.
- Semantic headings and link text.
- Yoast remains the only schema/canonical owner to prevent duplicates.
- Structured data must describe visible content.
- `llms.txt` is not part of the theme release. It may be tested later by the AI Visibility Lab as an experimental convention, not presented as a ranking standard.

## Error handling and fallbacks

- Missing featured image: render a typography-led card without a broken placeholder image.
- Missing excerpt: generate a bounded plain-text summary from post content for cards only.
- Missing sticky post: use the newest eligible post.
- Missing editorial category: omit that module and redistribute the grid.
- Missing Newsletter page: omit the homepage newsletter module without affecting other content.
- Missing Yoast, Elementor, WPForms, MCPWP, analytics, or Figma tokens: the new editorial templates continue rendering without fatal errors.
- Failed React/Motion island: retain its complete server-rendered fallback and log no uncaught browser error.
- Failed image load: preserve article title and metadata layout.
- Empty search/archive: show a useful empty state and navigation back to current sections.
- Update discovery failure: continue using the installed theme exactly as the existing update design requires.

## Testing and verification

### Automated

- PHPUnit tests for lead selection, fallback selection, category lookup, exclusion-list behavior, Release exclusion, and graceful missing-content states.
- PHPUnit tests for reading-time and summary helpers.
- PHPUnit tests for menu-derived rail selection and primary-category selection.
- PHP syntax checks on PHP 7.4 and the current supported PHP version.
- WordPress Coding Standards on new PHP files.
- Existing update-channel tests remain green.
- Production JavaScript build remains green.
- Packaging test confirms all new runtime templates, CSS, `theme.json`, and patterns are included while development artifacts remain excluded.

### Rendered verification

- Preview the draft Editorial Home without changing the live homepage.
- Verify desktop, tablet, and mobile layouts, including 320-pixel width.
- Verify homepage has one H1, no duplicated post cards, valid links, and no empty module headings.
- Verify keyboard navigation, focus order, skip link, and reduced motion.
- Disable JavaScript and verify that all editorial content, links, search, and fallbacks remain usable.
- Verify article, category, search, page, 404, and posts-index templates.
- Verify existing Elementor product pages still render unchanged.
- Verify Yoast emits one canonical and one schema graph after its separate warning is fixed.
- Verify no theme PHP warnings, browser console errors, mixed content, or failed assets.
- Run a representative performance audit on the preview and after launch.

### Launch verification

After changing the static homepage setting:

1. Confirm the new homepage URL returns HTTP 200 to a logged-out request.
2. Confirm the expected page title, canonical, H1, lead story, and Subscribe destination.
3. Confirm desktop and mobile screenshots match the approved hierarchy.
4. Confirm the old Elementor homepage remains published and selectable for rollback.
5. Confirm MCP-triggered update and rollback operations remain available and scoped to admin.

## Explicit non-goals for the first release

- Removing Elementor or converting every existing page.
- Rebuilding WPForms, subscriber storage, or email automation inside the theme.
- Storing subscribers in the theme.
- Consolidating analytics plugins.
- Fixing Yoast's existing option warning.
- Building a paywall, membership system, recommendation engine, or personalized feed.
- Automatically publishing agent-generated content.
- Creating custom post types for experiments or reviews.
- Building an AI chatbot or “Ask MCPWP” interface.
- Replacing WordPress with React, Astro, or another headless frontend.
- Publishing `llms.txt` as a claimed visibility solution.
- Rewriting or redirecting existing content without a separately approved migration map.
- Adding a new comments interface or comment-notification workflow.

## Definition of done

The editorial-system implementation is complete only when:

- The new Editorial Home can be previewed independently while the existing homepage remains live.
- Homepage, article, archive, search, page, error, and posts-index templates render correctly.
- The approved editorial hierarchy is present on desktop and mobile.
- Homepage modules are driven by real posts/categories and never duplicate stories.
- Topic rails and primary-category labels are driven by WordPress menu/category configuration, not MCPWP-specific PHP constants.
- Gutenberg patterns support consistent human and agent-assisted publishing.
- MCPWP.net's existing WPForms form works when embedded in its Gutenberg Newsletter page; the reusable theme owns only its presentation.
- Existing Elementor pages remain intact.
- Accessibility, PHP, coding-standard, build, packaging, unit, responsive, and rendered checks pass.
- The homepage switch and one-setting rollback are documented and verified.
- No required behavior in the new editorial templates depends on JavaScript, MCPWP, Yoast, Elementor, WPForms, analytics, or Figma being available. Untouched legacy pages may continue to depend on Elementor during migration.
- React/Motion enhancements share WordPress core React, have bounded mount contracts, and preserve functional server-rendered fallbacks.
- The same theme can render a second site with different site identity, menus, categories, posts, and newsletter copy without editing PHP template labels.
