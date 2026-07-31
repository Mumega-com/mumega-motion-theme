# MCPWP Living Control Plane Homepage Implementation Plan

> **For Codex:** Execute this plan in order with `superpowers:test-driven-development`. Keep the current live front page unchanged until Task 8 passes.

**Goal:** Add a production-ready, AI-readable MCPWP homepage that routes visitors through Operate, Scale, or Understand, progressively enhances the route graph with Motion, and safely replaces the current homepage only after staged browser verification.

**Architecture:** A native WordPress page template renders source-controlled semantic HTML and a scoped stylesheet. A bounded React/Motion island enhances only the first-view intent graph; it derives its route data from the server markup, restores the exact fallback on failure, and does not mount for reduced-motion users. Existing Product Home remains the deeper Mumega MCP conversion page. Preview pages stay noindex and out of sitemaps until promoted through Reading Settings.

**Tech stack:** WordPress/PHP 7.4+, semantic HTML, scoped CSS, React from WordPress, Motion 12, Jest/jsdom, PHPUnit 9, Docker PHP verification, WordPress MCP/admin staging, browser DevTools.

---

## Task 1: Native template and asset boundary

**Files:**

- Create: `tests/ControlHomeTemplateTest.php`
- Modify: `tests/EditorialSetupTest.php`
- Create: `page-templates/control-home.php`
- Modify: `inc/editorial-setup.php`

### Step 1: Write the failing template contract

Add `ControlHomeTemplateTest` assertions that require:

- `Template Name: MCPWP Control Plane Home`;
- native `mumega_motion_get_header()` and `mumega_motion_get_footer()` boundaries;
- the WordPress loop and `the_content()`;
- a page-scoped shell class.

Add `EditorialSetupTest` assertions requiring:

- the template to be an editorial view;
- `assets/css/control-home.css` to enqueue only for this template;
- the Motion bundle to remain conditional on an explicit mount in page content;
- Product Home behavior to remain unchanged.

### Step 2: Verify RED

Run the template/setup tests on PHP 8.3 and confirm they fail because the new template and asset boundary do not exist.

```bash
docker run --rm \
  -v "$PWD:/app" \
  -v /Users/hadi/dev/mumega/mumcp/mumega-motion-theme/vendor:/app/vendor:ro \
  -w /app php:8.3-cli \
  php vendor/bin/phpunit -c phpunit.xml.dist --filter '/ControlHomeTemplateTest|EditorialSetupTest/'
```

### Step 3: Implement the native boundary

Create the minimal page template, add it to `mumega_motion_is_editorial_view()`, and add a dedicated enqueue function for `control-home.css` depending on `mumega-motion-editorial`.

Do not add page copy or styling yet.

### Step 4: Verify GREEN

Run the focused PHP tests on PHP 7.4 and 8.3.

### Step 5: Commit

```bash
git add tests/ControlHomeTemplateTest.php tests/EditorialSetupTest.php page-templates/control-home.php inc/editorial-setup.php
git commit -m "feat: add MCPWP control home boundary"
```

## Task 2: Server-rendered homepage content contract

**Files:**

- Create: `tests/McpwpControlHomeContentTest.php`
- Create: `site-content/mcpwp-control-home.html`

### Step 1: Write failing semantic and truth tests

Require the content artifact to have:

- exactly one `h1` with “What do you want AI to do with WordPress?”;
- exactly three primary intent links with `data-mcpwp-intent` values `operate`, `scale`, and `understand`;
- stable matching section IDs;
- real no-JavaScript destinations;
- an explicit example evidence trace with Request, Scope, Human gate, WordPress result, and Activity record;
- a disclosed Mumega MCP flagship section and verified WordPress.org install URL;
- verified links for Agency, pricing, MCP explanation, and permission guidance;
- native search shortcode and no semantic-search or live-graph claim;
- no script tag, raw image URL, fake testimonial, star rating, customer count, version claim, or legacy Site Pilot identifier;
- exactly one explicit Motion mount marker;
- descriptive landmarks and labelled sections.

### Step 2: Verify RED

Run `McpwpControlHomeContentTest` and confirm it fails because the source artifact is absent.

### Step 3: Write the minimum complete HTML

Create the semantic homepage artifact in this order:

1. compact owned header;
2. hero question and control-plane mount;
3. example evidence trace;
4. Operate, Scale, and Understand route narratives;
5. single flagship Mumega MCP reveal;
6. knowledge mesh and real resources;
7. owned footer.

Reuse truthful product language from `site-content/mcpwp-product-home.html` without turning the homepage into a duplicate product page.

### Step 4: Verify GREEN

Run the focused content test on PHP 7.4 and 8.3.

### Step 5: Commit

```bash
git add tests/McpwpControlHomeContentTest.php site-content/mcpwp-control-home.html
git commit -m "content: add MCPWP control plane home"
```

## Task 3: Scoped visual system and responsive control plane

**Files:**

- Create: `tests/ControlHomeVisualSystemTest.php`
- Create: `assets/css/control-home.css`

### Step 1: Write failing visual-system tests

Require the stylesheet to include:

- one page root namespace and a template-scoped generic-header rule;
- warm paper, ink, violet, teal, cobalt, amber, and rule tokens;
- desktop control-plane graph hooks for all three intent states;
- CSS selectors driven by `data-selected-intent`;
- an ordered evidence trace and a single dark flagship band;
- a `47.9375rem` mobile breakpoint that converts the graph and trace to vertical flow;
- 44-pixel minimum touch targets;
- `:focus-visible` treatment;
- 320-pixel overflow protection;
- `prefers-reduced-motion: reduce` rules;
- no unscoped `.site-header { display: none; }` selector.

Include contrast calculations for the primary ink/paper, white/ink, and route label combinations.

### Step 2: Verify RED

Run `ControlHomeVisualSystemTest` and confirm the missing stylesheet causes the expected failure.

### Step 3: Implement the visual system

Build the page around four non-interchangeable compositions:

- the intent stage;
- the evidence trace;
- the route narratives;
- the flagship and knowledge mesh.

Use CSS grid, SVG signal paths from the HTML artifact, editorial type, thin rules, and limited glow. Avoid a generic card wall. Keep ASTER to a small circular annotation if an approved attachment token is used.

### Step 4: Verify GREEN

Run the visual test on PHP 7.4 and 8.3.

### Step 5: Commit

```bash
git add tests/ControlHomeVisualSystemTest.php assets/css/control-home.css
git commit -m "style: build MCPWP living control plane"
```

## Task 4: Bounded intent Motion island

**Files:**

- Read completely: `superpowers:test-driven-development/writing-good-tests.md`
- Create: `src/components/ControlPlane.jsx`
- Create: `src/components/ControlPlane.test.js`
- Modify: `src/js/index.js`
- Modify: `src/js/index.test.js`

### Step 1: Name the production failures before writing tests

The new tests must fail if:

- ordinary markup is mounted accidentally;
- route data is hard-coded rather than read from server markup;
- clicking a path does not update selected state, stable fragment, accessible current state, or live summary;
- the vendor-neutral intent event is absent or contains the wrong intent;
- reduced-motion mode changes server HTML;
- synchronous or asynchronous render failure does not restore the exact original HTML;
- one failed control-plane island blocks a later island.

### Step 2: Write the failing Jest tests

Test the exported parser/mount functions with real DOM markup and a tracked React root. Test the real component with `act()` for keyboard/click state changes and failure recovery.

### Step 3: Verify RED

```bash
npm run test:js -- --runInBand src/components/ControlPlane.test.js src/js/index.test.js
```

Confirm failures are caused by the missing component and mount behavior.

### Step 4: Implement the minimum island

Implement an explicit `[data-mcpwp-control-plane]` mount that:

- captures original HTML;
- reads route label, summary, destination, and accent from semantic anchors;
- selects a valid initial fragment when present;
- renders the static graph content plus a Motion-managed live summary;
- updates `data-selected-intent` and `aria-current`;
- uses `history.replaceState()` without forced scrolling;
- dispatches `mcpwp:intent-selected` with `{ intent }`;
- restores fallback using the existing island recovery boundary;
- does not mount in reduced-motion mode.

Use `LazyMotion` and `domAnimation`; do not add a second React or Motion dependency.

### Step 5: Verify GREEN and refactor

Run the focused tests, then the full JavaScript suite. Remove duplication from recovery setup only while green.

### Step 6: Commit

```bash
git add src/components/ControlPlane.jsx src/components/ControlPlane.test.js src/js/index.js src/js/index.test.js
git commit -m "feat: animate MCPWP intent control plane"
```

## Task 5: Preview indexing and package contracts

**Files:**

- Modify: `inc/editorial-setup.php`
- Modify: `tests/EditorialSetupTest.php`
- Modify: `tests/PackageManifestIntegrationTest.php` or the existing package-path contract test that owns page assets

### Step 1: Write failing release-boundary tests

Require:

- an unpromoted Control Plane Home page to emit `noindex, nofollow`;
- the preview template to be excluded from sitemap page IDs;
- a promoted Control Plane Home to remain indexable;
- Product Home preview behavior to remain intact;
- `page-templates/control-home.php` and `assets/css/control-home.css` to exist in the packaged theme;
- source-only `site-content/` and tests to remain excluded from the public theme archive if that is the current package contract.

### Step 2: Verify RED

Run the focused setup/package contract tests and confirm the new template is not yet covered.

### Step 3: Generalize preview safety narrowly

Extend the existing Product Home preview helpers to recognize only the two explicitly owned commercial/home templates. Do not alter robots or sitemap behavior for ordinary pages.

### Step 4: Verify GREEN

Run the focused tests on PHP 7.4 and 8.3.

### Step 5: Commit

```bash
git add inc/editorial-setup.php tests/EditorialSetupTest.php tests/PackageManifestIntegrationTest.php
git commit -m "fix: protect MCPWP control home previews"
```

## Task 6: Full local verification and package inspection

**Files:**

- Verify only; modify production files only if a failing check identifies a homepage defect.

### Step 1: Run JavaScript and production build

```bash
npm test
```

### Step 2: Run PHP 7.4 and 8.3 suites

Use release-capable PHP images or the repository's CI-equivalent environment with ZIP and Git available. Run the full PHPUnit suite. If the stock image lacks ZIP/Git, record that environmental failure and run the 277-test non-packaging suite plus the release-capable package gate separately.

### Step 3: Run editorial contract validation

```bash
npm run test:editorial-contract
npm run validate:editorial-contract
```

### Step 4: Package and inspect

```bash
npm run package
unzip -l dist/mumega-motion-theme-*.zip
```

Confirm the new runtime template/CSS/build files are shipped and source-only docs/tests/content are not.

### Step 5: Inspect the diff

```bash
git diff --check origin/master...HEAD
git status --short
```

## Task 7: Install candidate and create a safe WordPress preview

**Files/state:**

- Candidate theme package from Task 6
- Existing `mcpwp.net` WordPress installation
- New or restored unlinked preview page

### Step 1: Capture rollback state

Record without exposing credentials:

- active theme slug and version;
- current front-page ID and URL;
- current homepage template;
- current robots/canonical behavior;
- candidate commit SHA and package checksum.

### Step 2: Install through the existing theme update boundary

Use the current Mumega Motion release/update mechanism. Do not overwrite files through the browser editor and do not change the front-page setting.

### Step 3: Create the preview page

Create or restore one unlinked page using `MCPWP Control Plane Home`, import `site-content/mcpwp-control-home.html`, and replace only documented media tokens with real WordPress attachment IDs.

Keep the preview out of navigation. Confirm `noindex, nofollow`, sitemap exclusion, and a self-canonical preview URL.

## Task 8: Browser, accessibility, SEO, and performance verification

**Files/state:** Staged preview only.

### Step 1: Desktop and mobile visual checks

Inspect at 1440, 1024, 768, 390, and 320 CSS pixels. Capture screenshots at representative desktop and mobile sizes. Confirm no horizontal overflow, clipped text, or competing primary action.

### Step 2: Interaction and resilience checks

Verify all three routes with mouse and keyboard, fragments, live-summary announcement, visible focus, 44-pixel targets, refresh persistence, JavaScript disabled, simulated island failure, and reduced-motion mode.

### Step 3: Content and SEO checks

Verify exactly one `h1`, heading order, descriptive links, canonical, preview robots, sitemap exclusion, no duplicate schema, no fabricated claims, and real CTA destinations.

### Step 4: Technical checks

Verify no console errors, failed theme assets, mixed content, PHP warnings before doctype, layout shift from missing media dimensions, or accidental Elementor interception.

Run Core Web Vitals/Lighthouse diagnostics and fix homepage-owned regressions before promotion.

### Step 5: Approval evidence

Add preview URL, screenshots, test output, package checksum, and rollback state to draft PR #38.

## Task 9: Promote, verify, and publish the GitHub handoff

**Files/state:** WordPress Reading Settings, draft PR #38.

### Step 1: Promote atomically

After Task 8 is green, set the verified preview page as the static front page. Preserve the previous page ID as the immediate rollback target.

### Step 2: Verify the public root

Re-open `https://mcpwp.net/` in a fresh context and confirm:

- the new page renders at the root;
- canonical is the root URL;
- the homepage is indexable;
- the former preview URL has the intended canonical/redirect behavior;
- all three intent routes and commercial CTAs work;
- no new 404, console, PHP, or asset errors appear.

### Step 3: Run a rollback rehearsal without disrupting production

Confirm the previous front-page ID and exact Reading Settings action needed to restore it. Do not perform a visible rollback unless the promoted page fails a gate.

### Step 4: Finish the branch

Invoke `superpowers:verification-before-completion`, `superpowers:requesting-code-review`, and `superpowers:finishing-a-development-branch`. Then use `github:yeet` to push the verified commits and update draft PR #38 with the final scope and evidence.

### Step 5: Final report

Report the live URL, previous page rollback ID, commit SHA, package checksum, tests, browser evidence, known follow-ups, and PR URL. Do not call the homepage complete if any launch gate remains unverified.
