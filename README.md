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
```

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
