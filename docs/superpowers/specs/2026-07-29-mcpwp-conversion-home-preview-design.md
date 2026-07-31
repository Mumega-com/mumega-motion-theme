# MCPWP conversion homepage preview

**Date:** 2026-07-29
**Status:** Approved direction; pending owner review of this written contract
**Repository:** `Mumega-com/mumega-motion-theme`
**Target:** A separate, unlinked WordPress preview page
**Production homepage:** Unchanged until explicit owner approval

## Objective

Create a homepage candidate that makes MCPWP understandable and installable within one screen while preserving the editorial authority, warmth, and visual distinction of the approved MCPWP V2 concept.

The page's primary conversion is a visitor choosing to install MCPWP. Agency Mode and ASTER-backed editorial guidance provide secondary paths and trust, but do not compete with the primary action.

Success means a new visitor can answer these questions without opening another page:

1. What does MCPWP let an AI assistant do with WordPress?
2. How is access controlled?
3. Which AI clients can connect?
4. How does someone install and achieve a first successful connection?
5. When does Agency Mode become relevant?

## Safety and release boundary

- Build and review the experience on a separate, unlinked page.
- Do not change WordPress Reading Settings.
- Do not alter, delete, or overwrite the current Elementor homepage.
- Do not add the preview to public navigation, the XML sitemap, or search indexing.
- Do not present the preview as production until desktop, mobile, accessibility, installation, and connection checks pass.
- Preserve the current homepage as the immediate rollback target if the preview is later promoted.

## Positioning

### Primary promise

> **Your WordPress site can work with AI—safely.**
>
> Install MCPWP, connect the AI assistant you already use, and let it work through scoped permissions, approvals, and a visible activity history.

The copy must explain the outcome before describing MCP, APIs, agents, or tool counts.

### Primary action

**Install free from WordPress.org**

The button links directly to the official WordPress.org plugin listing. It does not route through an email gate or a legacy package page.

### Secondary action

**Watch a real WordPress workflow**

This moves to an on-page, evidence-based workflow demonstration. It must not imitate a terminal, invent activity, or imply that an animation is a live site operation.

### Product boundaries

- The WordPress.org edition and Agency Mode are visibly distinct.
- Claims on the page must match the capabilities available in the edition being described.
- The installed MCPWP 3.10.2 runtime may be cited only as verified site evidence, not as the public directory version unless WordPress.org independently confirms that version.
- Avoid fixed tool-count marketing. Explain outcomes, permissions, and workflows.

## Page architecture

### 1. Publication and product header

The header uses the MCPWP wordmark, a short descriptor, visible desktop navigation, accessible mobile navigation, native search, and one persistent **Install free** action.

Primary navigation:

- Product
- How it works
- Agency
- Guides
- Pricing

The header does not collapse into a hamburger at desktop widths.

### 2. Conversion hero

The desktop hero uses an approximately 58/42 split.

Left:

- editorial eyebrow: `WORDPRESS + AI, WITH CONTROL`
- one H1 using the primary promise
- a concise explanatory deck
- primary and secondary actions
- a trust line: scoped access, approval before risk, and visible activity

Right:

- ASTER as the AI Research Editor, using the approved portrait
- a compact “What happens next” briefing
- the four-step first-success path: Install, Connect, Approve, Publish
- a visible AI-assisted and human-reviewed disclosure

On mobile, the promise and install action appear before ASTER.

### 3. Compatibility and immediate proof

A compact compatibility rail names the supported clients:

- Claude
- ChatGPT
- Gemini
- Codex
- Hermes
- OpenClaw

Names are rendered as text rather than fabricated or unofficial logos.

The proof statement may say that the team uses MCPWP across approximately ten websites only after the exact count and examples are verified. It must not imply ten independent paying customers.

### 4. Real workflow demonstration

The demonstration tells one bounded story:

> “Create a draft landing page for the weekend offer, using the existing brand style.”

The sequence shows:

1. **Request** — the user describes the desired outcome.
2. **Plan** — MCPWP exposes the intended WordPress actions.
3. **Approval** — a risky or publishing action waits for confirmation.
4. **Result** — WordPress contains a reviewable draft and the activity is recorded.

Use semantic cards or native disclosure controls. The demonstration is labelled as an example unless backed by a captured real run.

### 5. First connection

A short operational sequence replaces the obsolete onboarding copy:

1. Install and activate MCPWP.
2. Create a scoped API key in WordPress.
3. Connect the chosen AI client to the current MCPWP endpoint.
4. Verify the connection with a read-only site-information request.
5. Create a draft before attempting publication.

Endpoint and credential examples must be sourced from the installed 3.10.2 runtime or its packaged documentation during implementation. No endpoint is copied from the legacy getting-started page.

### 6. Free and Agency Mode

Two clearly labelled columns explain who each edition is for.

**WordPress.org edition**

- one WordPress site
- scoped key access
- read-first content workflows
- drafts and media operations supported by the directory build
- transparent security and privacy boundaries

**Agency Mode**

- multiple client websites
- reusable governed workflows
- team and client approval boundaries
- operational oversight and activity history
- supported AI clients and advanced capabilities verified against 3.10.2

The section links to installation and Agency Mode details without forcing a pricing decision in the hero.

### 7. Tested before recommended

Preserve the approved editorial methodology:

- Install
- Connect
- Verify
- Recover

This section explains how MCPWP recommendations are evaluated. “Tested on WordPress” appears only beside evidence that records the WordPress version, plugin edition, workflow, outcome, and recovery path.

### 8. Guides and editorial authority

Use real published content to answer durable questions:

- What is WordPress MCP?
- How do scoped permissions work?
- Which AI client should a team use?
- How should an agency review and recover changes?

ASTER remains a guide to the reporting, not the product being sold.

### 9. Final conversion

Repeat one primary action:

**Install MCPWP free**

Offer **Explore Agency Mode** as a visually subordinate secondary path.

## Visual system

Use the approved MCPWP V2 concept as the visual source of truth:

![Approved production ASTER hero portrait](assets/2026-07-29-aster-product-hero.png)

- warm ivory paper background
- ink navy structure and footer
- editorial serif headlines
- highly readable sans-serif body copy
- restrained violet research accent
- teal governance accent
- cobalt technical accent
- amber only for warnings and growth
- thin editorial rules and low-elevation cards

ASTER uses the approved porcelain and smoked-glass lens head, luminous eyes, violet temple nodes, and navy-and-ivory tailoring.

The approved production hero source is
`assets/2026-07-29-aster-product-hero.png`. The implementation may create
optimized WebP or AVIF derivatives and responsive crops from this source, but
must not replace ASTER's identity or alter the three-node temple mark.

The page avoids generic SaaS gradients, glassmorphism, excessive rounded cards, fake dashboards, stock photography, emoji icons, and decorative motion.

## Implementation architecture

The preview remains independent of Elementor.

- Add a reusable native WordPress page template for a product homepage.
- Keep MCPWP-specific copy and links in WordPress page content, not hard-coded into the reusable template.
- Use semantic Gutenberg-compatible markup and scoped theme classes.
- Add one scoped stylesheet for the product-home template.
- Use native disclosure elements for optional workflow detail; JavaScript is not required for comprehension.
- Reuse the theme's native header, footer, tokens, focus treatment, and reduced-motion behavior.
- Store ASTER as optimized WordPress media with intrinsic dimensions and responsive image markup.

The template renders page-owned content through the theme's existing safe content-rendering boundary. It does not add `front-page.php` or intercept other pages.

## Data and content flow

1. WordPress owns the page title, excerpt, blocks, links, and media.
2. The product-home template owns semantic page structure and the rendering boundary.
3. The scoped stylesheet owns responsive composition and presentation.
4. Runtime capability claims are checked against MCPWP 3.10.2 before page content is created.
5. WordPress.org version claims are checked independently against the public listing.
6. The preview URL is reviewed before any front-page setting changes.

If optional proof, guide, or Agency Mode content is unavailable, omit that module rather than displaying invented or empty claims.

## Responsive behavior

- **1440px:** full two-column hero, visible navigation, three- or four-column supporting layouts.
- **1024px:** two-column hero remains if both actions and ASTER fit without compression.
- **768px:** hero stacks and supporting grids reduce to two columns.
- **375px and 320px:** single-column reading order, no horizontal scrolling, no clipped headlines, and touch targets of at least 44 CSS pixels.

The install action remains visible without obscuring content. Text zoom at 200% must not hide actions or reorder meaning.

## Accessibility and performance

- exactly one H1
- sequential section headings
- skip link and semantic landmarks
- visible keyboard focus
- minimum 4.5:1 text contrast
- meaningful ASTER alternative text where the portrait adds information
- empty alternative text where adjacent content already names ASTER
- no information conveyed by color alone
- no hover-only interaction
- reduced-motion support
- declared image dimensions to prevent layout shift
- lazy loading for below-fold media
- no required third-party JavaScript

## Verification

### Structural

- Template renders only on the assigned preview page.
- Current homepage and ordinary Elementor pages remain unchanged.
- Header, footer, menus, search, and consent controls remain valid.
- The page has one H1 and a logical heading hierarchy.

### Content

- WordPress.org install link resolves to the approved plugin listing.
- Installed and public edition versions are not conflated.
- Endpoint and API-key guidance matches MCPWP 3.10.2 evidence.
- Every capability claim is assigned to the correct edition.
- No unsupported model, customer, install, review, or performance claim appears.

### Workflow

Run one clean first-success journey:

1. install the directory edition on a clean WordPress site;
2. activate it;
3. create a scoped read key;
4. connect one supported AI client;
5. request site information;
6. create a draft;
7. verify the result and activity record;
8. exercise the documented recovery boundary.

### Visual

- desktop at 1440×900
- tablet at 768px
- mobile at 375px and 320px
- browser zoom at 200%
- reduced motion
- JavaScript disabled
- keyboard-only navigation
- no horizontal overflow

## Promotion gate

The preview may become the homepage only after:

1. the owner approves desktop and mobile renders;
2. the clean first-connection workflow passes;
3. version and edition claims are verified;
4. the current homepage is recorded as the rollback target;
5. the owner explicitly authorizes the WordPress Reading Settings change.
