# Mumega Motion

WordPress theme with React/[Motion](https://motion.dev) (formerly Framer Motion) animations, built
as progressive enhancement over real, server-rendered content — not a headless rebuild.

## Why this approach

WordPress's actual advantage over building on a custom stack is its RBAC and 60k-plugin ecosystem, not
its default frontend rendering. Going fully headless (WP-as-API + separate React app) throws that away.
This theme instead:

- Keeps normal WP templates/loops/blocks as the source of truth for content and markup.
- Shares WordPress core's **own** React instance (the one that powers the block editor) instead of
  bundling a second copy — no double-loading React.
- Uses Motion only to animate content that's already there, via a `data-motion="fade-in"` attribute on
  any element. No-JS visitors and search engines see the full content immediately; Motion adds the
  entrance transition on top.

## How the React-sharing works

`@wordpress/scripts`' build config includes `@wordpress/dependency-extraction-webpack-plugin`, which
externalizes `react`/`react-dom` (and anything a dependency like Motion imports internally) to
WordPress's own global script handles instead of bundling them. Confirmed in `build/index.asset.php`:

```php
array('dependencies' => array('react', 'react-dom', 'react-jsx-runtime'), ...)
```

`functions.php` reads that generated dependency list and passes it straight to `wp_enqueue_script()`,
so WordPress loads its own React first and our bundle attaches to it.

## Usage

Wrap any server-rendered block with the attribute and it animates in on load:

```php
<div data-motion="fade-in" data-motion-delay="0.1" data-motion-y="24" data-motion-duration="0.5">
	<?php the_content(); ?>
</div>
```

- `data-motion-delay` — seconds before the animation starts (default `0`)
- `data-motion-y` — pixels the content slides up from (default `24`)
- `data-motion-duration` — animation duration in seconds (default `0.5`)

For custom Gutenberg blocks or hand-authored components, import `FadeIn` or `StaggerList` directly from
`src/components/` instead of the attribute-driven auto-mount.

## Build

```bash
npm install
npm run build     # production build -> build/index.js + build/index.asset.php
npm run start     # dev build with watch mode
npm run package -- 0.1.123 # build and create dist/mumega-motion-theme-0.1.123.zip
```

## Edge update packages

Every successful `master` build can produce a GitHub edge prerelease. Its edge
version is `0.1.<GitHub run number>` and its tag is
`edge-v0.1.<GitHub run number>`. The release contains only the WordPress theme
runtime files, its SHA-256 checksum, and `manifest.json`; it never changes the
checked-in `style.css` version.

Release immutability is a GitHub repository setting, not something this
workflow can establish by itself. Before enabling publishing, a repository
administrator must enable GitHub **Immutable Releases** for this repository.
The workflow creates a new annotated tag without force-pushing, verifies that
the remote tag resolves to the triggering commit, and verifies after publishing
that GitHub reports the release as immutable with exactly the expected assets.
It deliberately fails on a pre-existing or concurrently-created tag instead of
reusing it.

Immutable Releases protects release assets, but it is not a substitute for tag
protection. Configure a repository ruleset for `refs/tags/edge-v*` that blocks
tag deletion and force updates. The ruleset's separate GitHub Actions bot
exception needs permission only to create a new edge tag; this workflow never
needs to move an existing one. That tag-ruleset exception is distinct from the
release job's `contents: write` workflow permission, which authorizes creating
the prerelease and uploading its assets.

To make the same package locally, run `npm run package -- 0.1.123`. The
packager stages an explicit runtime allowlist, rejects development-only
directories and files (including source maps, docs, tests, and build tooling),
sets the version only in the staged stylesheet, and normalizes archive metadata
so a repeated build from the same source produces the same ZIP bytes.
`dist/manifest.json` exactly binds the archive SHA-256, fixed GitHub download
URL, WordPress requirement, and PHP requirement.

Publishing an edge package does **not** install it on a WordPress site. The
normal WordPress Themes/Updates dashboard remains the fallback when MCPWP is
unavailable or an operator prefers a dashboard action.

For the explicit MCP workflow, wait for the edge prerelease, then call
`wp_update_mumega_motion` with an MCPWP key that has the required `admin`
scope (optionally using `force_check: true`). Review the returned package and
installation evidence, then separately verify the homepage status, desktop
and mobile rendering, and the absence of fatal errors. Use
`wp_rollback_mumega_motion` only as an explicit recovery operation; updates
are never installed merely by pushing a commit.

Before an install, the updater stores a local copy of the active theme and
retains the three newest successful backups. It automatically attempts to
restore the fresh backup when a post-backup update step fails.

The MCP bridge has a one-time installation requirement: install a theme bridge
ZIP manually through WordPress once (and ensure MCPWP includes the
raise-only admin-scope bridge support) before relying on MCP-triggered updates.
After that bootstrap, later verified edge packages can be discovered and
installed through the dashboard or the explicit MCP workflow above.

## Known tradeoff — bundle size

Current bundle: **~34KB gzipped** (Motion's full feature set — `domAnimation`: animate, exit, hover,
tap, variants). Motion's own docs advertise ~4.6KB using `LazyMotion` with features loaded as a
separate async chunk via dynamic `import()`. That dynamic import did **not** actually code-split under
`@wordpress/scripts`'s default webpack config when tested here — it stayed inlined in the single main
chunk regardless. Getting the smaller number needs a custom `webpack.config.js` overriding wp-scripts'
defaults (`output.chunkFilename`, `splitChunks` tuning). Worth doing once a real site accumulates enough
`data-motion` usage for it to matter; not done here to keep the initial setup simple and honest about
what it actually ships.

## Verified

Built and tested live against a real WordPress instance (not just claimed):
- Site title (`bloginfo`) and post loop content (`the_title`/`the_content`) animate in correctly via the
  `data-motion` attribute, wrapping real server-rendered markup.
- WordPress's own core React script (`/wp-includes/js/dist/vendor/react*`) is what actually loads — no
  duplicate React bundle.
- Zero console errors.

## StreamingText — real HTTP-streamed content, with a known gap

`src/components/StreamingText.jsx` renders text arriving from a genuine streaming HTTP response (`fetch`
+ `ReadableStream`, not a client-side typewriter simulation) — `data-motion-stream="<url>"` on a mount
point. This is the actual "AI-first, impossible in Elementor" case: content that grows as tokens
genuinely arrive over the wire, the same mechanism a real LLM response stream uses.

**Confirmed working**, verified against `stream-demo.php` (a real chunked/flushed PHP endpoint, ~3s
total, words arriving every ~90ms — proven via `curl -w '%{time_total}'`, not assumed):
- Text renders progressively as real bytes arrive (sampled string length growing across the stream, not
  jumping straight to the final value).
- Final rendered text exactly matches the source.
- Zero console errors.

**Not confirmed — a real, currently-unresolved gap**, not glossed over: the intent was for a sibling
element below the streaming text to smoothly glide down as the text grows (Motion's FLIP-based `layout`
animation), rather than snap instantly like plain CSS reflow — the actual "Elementor literally cannot do
this" claim. Extensive live testing (sub-25ms position and `transform` sampling across a growth event,
both with and without an explicit `LayoutGroup` wrapper and an explicit slow `transition`) shows the
sibling's position changes in a single frame with `transform` staying `none` throughout — i.e. the FLIP
animation is not actually activating for this cross-component case in the current implementation, despite
following Motion's documented `layout` + `LayoutGroup` pattern. Leading suspects, not yet root-caused:
the sibling never re-renders itself (only `StreamingText`'s own internal state updates), and whatever
ResizeObserver-based propagation Motion normally uses to catch "my neighbor grew" isn't registering
across that boundary here. Worth a closer look with Motion's own devtools/source before relying on this
specific effect in production.

## Figma-synced design tokens (colors + typography)

`inc/figma-tokens.php` reads a WP option — `mcpwp_figma_design_tokens` — and, if it holds any valid
tokens, prints an inline `<style id="mumega-figma-tokens">` block of CSS custom properties in `wp_head`.
This lets a *separate* plugin, [MCPWP](https://mcpwp.net) (a WordPress MCP plugin, installed independently
on the same site), keep this theme's colors/typography in sync with a Figma file — re-syncing from Figma
on the MCPWP side updates the rendered site immediately, with no theme rebuild and no theme code changes.

**This theme has zero hard dependency on MCPWP.** It only ever calls `get_option(
'mcpwp_figma_design_tokens', array() )`. If MCPWP isn't installed, hasn't synced yet, or the option is
malformed, every code path here degrades to "no valid tokens" and the `<style>` block simply isn't
printed — the theme's own hardcoded fallback values render exactly as if this feature didn't exist.

### Option schema (owned by MCPWP; this theme is a read-only consumer)

```php
array(
    'synced_at' => 1234567890, // unix timestamp
    'file_key'  => 'abc123',
    'colors'    => array(
        'brand-primary'   => '#2F7CFF', // hex, or rgba(r, g, b, a) string if alpha < 1
        'brand-secondary' => '#27C46A',
    ),
    'typography' => array(
        'heading-h1' => array(
            'fontFamily'   => 'Inter',
            'fontWeight'   => 700,
            'fontSize'     => 48,     // px, integer
            'lineHeightPx' => 56,     // px, integer
        ),
    ),
)
```

`colors` and `typography` keys are already CSS-custom-property-safe slugs (lowercase, hyphenated — Figma
style names like "Brand/Primary" become `brand-primary` on the MCPWP side). Both maps may be empty (never
synced, or a Figma file with no shared styles) — this theme treats that as the normal, unsynced state, not
an error.

### CSS custom property naming convention

| Token | CSS custom property |
|---|---|
| `colors.{slug}` | `--figma-color-{slug}` |
| `typography.{slug}.fontFamily` | `--figma-typography-{slug}-font-family` |
| `typography.{slug}.fontWeight` | `--figma-typography-{slug}-font-weight` |
| `typography.{slug}.fontSize` | `--figma-typography-{slug}-font-size` (px baked into the value, e.g. `48px`) |
| `typography.{slug}.lineHeightPx` | `--figma-typography-{slug}-line-height` (px baked into the value, e.g. `56px`) |

Every value is validated against a strict whitelist before being placed in the raw `<style>` block (colors
must match a hex or `rgb()`/`rgba()` pattern; font families are stripped to letters/digits/spaces/hyphens/
commas; numeric fields must be numeric and in a sane range) — the option is treated as untrusted input at
this boundary even though it ultimately traces back to the site owner's own Figma account, since it passes
through an external API and a separate plugin first. Values that don't pass validation are dropped, not
partially escaped and kept.

`style.css` consumes these with fallbacks, e.g.:

```css
header {
	background-color: var(--figma-color-brand-primary, #1a1a2e);
}
header h1 {
	font-family: var(--figma-typography-heading-h1-font-family, inherit);
	font-size: var(--figma-typography-heading-h1-font-size, 2rem);
}
```

So the header/hero re-colors and re-fonts itself the moment MCPWP's Figma sync populates the option —
no theme rebuild, no theme code touched — and falls back to the hardcoded defaults above whenever the
option is empty or MCPWP isn't present.
