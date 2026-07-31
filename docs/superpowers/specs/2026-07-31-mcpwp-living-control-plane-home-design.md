# MCPWP Living Control Plane Homepage Design

**Status:** Approved direction
**Date:** 2026-07-31
**Owner:** MCPWP
**Implementation lane:** `feat/mcpwp-conversion-home-preview` / draft PR #38

## Purpose

The MCPWP homepage must do one job: help a human or an AI agent identify why they are here and enter the right part of the WordPress-and-AI system. It is not a long product landing page and it is not a generic editorial feed.

The homepage establishes MCPWP as the operating guide for WordPress in the AI era. Mumega MCP remains the disclosed flagship product and keeps its own commercial product journey. The homepage earns that product transition by first helping visitors orient themselves.

## Core promise

The opening question is:

> What do you want AI to do with WordPress?

The supporting promise is:

> Choose a path. MCPWP will show you the workflows, evidence, and tools that fit.

The three stable paths are:

1. **Operate** — I run a WordPress business.
2. **Scale** — I manage or build WordPress sites.
3. **Understand** — I am evaluating WordPress and AI.

The page has exactly one `h1`. Each path is a real link with useful fallback behavior, not a JavaScript-only control.

## Recommended visual direction

The homepage is a living control plane rather than a stack of interchangeable cards.

- The canvas is warm editorial paper with deep ink typography.
- WordPress is the central operating node.
- Operate, Scale, and Understand are three signal paths with stable colors: violet, teal, and cobalt.
- Human approval and governance use amber as a distinct safety signal.
- Fine rules, route lines, trace labels, and restrained glow create a technical system without turning the site into a dark SaaS dashboard.
- ASTER appears as a small editorial signal or annotation. ASTER is never a full-body hero or a decorative stock character.
- Large editorial typography supplies authority; system traces and graph motion supply product energy.
- Repeated generic card grids are avoided. Major sections use a route stage, a trace, one product reveal, and a knowledge mesh.

## Page structure

### 1. Minimal header

The homepage owns a compact header containing the MCPWP wordmark, a short category descriptor, essential navigation, native search access, and one commercial action. The primary commercial action leads to the verified Mumega MCP installation path.

The header remains understandable without JavaScript and usable at 200 percent zoom. On small screens it wraps into a simple, non-overlay layout rather than relying on a fragile custom menu.

### 2. Living intent stage

The first viewport contains the eyebrow, single `h1`, short orientation copy, and the control-plane graph.

The server-rendered graph contains:

- a central WordPress node;
- three linked path anchors for Operate, Scale, and Understand;
- a concise outcome statement and descriptive destination for each path;
- a visible statement that MCPWP is independent, AI-assisted, and human-reviewed.

With progressive enhancement, choosing a path:

- marks the choice with `aria-pressed` or the equivalent accessible state;
- moves the route highlight through the graph;
- updates a polite live summary with the path's next best action;
- preserves a stable URL fragment (`#operate`, `#scale`, or `#understand`);
- never removes the other pathways from the document;
- emits a vendor-neutral DOM event and stable data attributes for later PostHog integration.

The first render must not require JavaScript. If the Motion island fails, the original links and all page content remain intact. Reduced-motion users receive the complete static graph with no React mount.

### 3. Evidence trace

Immediately after orientation, the page shows one labelled example trace:

`Request -> Scope -> Human gate -> WordPress result -> Activity record`

This is explicitly an example workflow, not fabricated live activity or social proof. The trace explains what can be inspected at every step and distinguishes the free product boundary from paid Agency governance where necessary.

### 4. Three route narratives

All three route sections remain present in semantic HTML so people, search engines, and AI systems can understand the complete category.

- **Operate** connects a business owner to a safe first WordPress connection, practical workflows, and the free Mumega MCP installation path.
- **Scale** connects agencies and builders to governed multi-site operations, approvals, recovery, and the Agency destination.
- **Understand** connects evaluators to durable MCP explanations, security guidance, tested workflows, and the knowledge graph.

The selected route may receive stronger visual emphasis, but unselected content is not hidden from the underlying document. Each section has descriptive links and no generic “learn more” CTA.

### 5. Flagship product reveal

A single deep-ink section explains the commercial relationship plainly:

> Mumega MCP is the WordPress AI connector we build and test.

The section shows the bounded workflow rather than feature-card overload. It names the verified client ecosystem without claiming universal compatibility or an unverified version. Its primary action installs the free WordPress.org edition. Secondary actions lead to pricing and Agency information.

This section may reuse the proven product-home visual tokens and workflow language from PR #38, but the existing Product Home template remains available as the deeper conversion page.

### 6. Knowledge mesh

The final editorial section demonstrates that MCPWP is more than a plugin page. It links permissions, workflows, commerce, governance, agencies, and AI clients around WordPress, then surfaces a small number of real published resources.

Native WordPress search remains available. The homepage must not claim semantic search, a live knowledge graph, benchmarks, or automated recommendations until those systems exist and have verified evidence.

### 7. Footer

The footer groups destinations by Evidence, Product, Agency, Guides, and About. Policy links and the independent/vendor-neutral disclosure remain easy to find.

## Interaction architecture

The page content lives in `site-content/mcpwp-control-home.html` and is rendered by a native page template. A page-scoped stylesheet owns the visual system. A bounded React/Motion island enhances only the intent stage.

The island reads configuration from existing semantic markup rather than duplicating the homepage copy in JavaScript. It may clone or map only the short path labels and summaries needed for the live panel. WordPress remains the content source of truth.

The enhancement contract is:

1. Detect an explicit `[data-mcpwp-control-plane]` mount.
2. Capture the original HTML before creating a React root.
3. Skip mounting for `prefers-reduced-motion: reduce`.
4. Restore the exact original HTML on synchronous or asynchronous failure.
5. Keep ordinary editorial pages and the Product Home template unchanged.

Stable hooks include:

- `data-mcpwp-control-plane` for the bounded island;
- `data-mcpwp-intent="operate|scale|understand"` for path choices;
- `data-mcpwp-event="homepage_intent_selected"` for vendor-neutral analytics wiring;
- route section IDs matching the stable fragments.

No tracking vendor is bundled in the theme.

## Motion behavior

Motion communicates state; it is not continuous decoration.

- The selected signal line draws from WordPress to the chosen path.
- The selected node advances slightly and its label gains contrast.
- The route summary crossfades and shifts by a small distance.
- The evidence trace reveals once as it enters the viewport.
- No autoplaying carousel, cursor-chasing effect, scroll hijack, heavy parallax, or perpetual particle field is allowed.
- Reduced-motion mode removes transforms, line drawing, and animated transitions.

The page remains suitable for Lumen to record as a desktop or vertical mobile explainer because each state has a stable resting frame.

## Mobile behavior

Below 768 CSS pixels, the radial control plane becomes a vertical signal path:

1. WordPress node;
2. Operate;
3. Scale;
4. Understand;
5. selected-path summary.

Touch targets are at least 44 CSS pixels. No essential label is encoded only by color. The evidence trace becomes an ordered vertical sequence. Editorial line lengths stay within a readable range and no horizontal scrolling is introduced at 320 CSS pixels.

## AI-first and search contract

- The complete meaning is present in initial server-rendered HTML.
- There is one descriptive `h1` and a logical heading hierarchy.
- Every major section has a stable ID and an explicit `aria-labelledby` relationship.
- Link labels name their destinations and tasks.
- Claims are bounded to verified product behavior.
- There are no fabricated testimonials, customer counts, rankings, live events, or version claims.
- Preview pages are `noindex` and excluded from sitemaps until promoted.
- Once promoted through WordPress Reading Settings, the homepage is indexable and canonical at `https://mcpwp.net/`.
- Existing Yoast or site-level schema remains authoritative; the theme does not emit duplicate organization or website schema.

## Page ownership and release safety

The current live homepage is not overwritten during construction.

1. Package and install the candidate theme through the existing theme release boundary.
2. Create or restore an unlinked WordPress preview page using the Control Plane Home template.
3. Import the source-controlled content and replace any approved media token with a WordPress attachment ID.
4. Verify the preview while it remains `noindex` and absent from sitemaps.
5. Record the current static front-page ID and theme version for rollback.
6. Promote only after desktop, mobile, keyboard, reduced-motion, SEO, console, and performance checks pass.
7. Recheck the public root URL, canonical, robots directive, sitemap behavior, and primary conversion links after promotion.

## Verification and acceptance criteria

The implementation is acceptable only when all of the following are true:

- The first viewport asks one question and offers exactly three primary paths.
- The page contains exactly one `h1`.
- Operate, Scale, and Understand have real destinations without JavaScript.
- The intent enhancement works with mouse and keyboard, maintains stable fragments, and exposes state accessibly.
- Turning off JavaScript leaves a complete and useful homepage.
- Reduced-motion mode leaves the server-rendered page intact.
- An island failure restores the original markup.
- The example evidence trace is clearly labelled and makes no live-activity claim.
- Mumega MCP is disclosed as MCPWP's flagship product without making MCPWP merely a product brochure.
- The free, Pro, and Agency commercial paths are truthful and distinct.
- No unverified metric, testimonial, compatibility, version, or semantic-search claim appears.
- The page is usable without horizontal scrolling at 320, 768, 1024, and 1440 CSS pixels.
- Core navigation, route controls, and CTAs have visible focus and at least 44-pixel targets.
- The staged preview is noindex and out of sitemaps; the promoted homepage is canonical and indexable.
- JavaScript tests, PHP 7.4/8.3 tests, build, package inspection, and browser checks pass before promotion.

## Explicit non-goals

- Replacing the existing Product Home conversion page.
- Building semantic search or a live recommendation engine.
- Shipping a PostHog SDK or changing consent behavior.
- Creating fake activity, social proof, benchmarks, partner logos, or compatibility badges.
- Rebuilding the whole editorial archive or every MCPWP page in this release.
- Depending on Elementor for the new homepage.

