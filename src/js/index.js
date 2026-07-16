import { createRoot } from 'react-dom/client';
import { LazyMotion, domAnimation } from 'motion/react';
import FadeIn from '../components/FadeIn';

/**
 * Progressive-enhancement auto-mount: any element already rendered by
 * WordPress (theme template, block, widget — doesn't matter) marked with
 * `data-motion="fade-in"` gets its existing content wrapped in an entrance
 * animation. The HTML underneath is untouched real content — no-JS visitors
 * and search engines see it immediately; Motion only adds the transition.
 *
 * NOTE on bundle size: domAnimation is imported statically here, so the
 * built bundle is Motion's full ~30-37kb (gzipped), not the ~4.6kb LazyMotion
 * advertises for a true async split. A dynamic `import('motion/react')` for
 * domAnimation does NOT actually code-split under @wordpress/scripts'
 * default webpack config — confirmed by inspecting the build output, it
 * stayed a single chunk regardless. Getting the smaller number needs a
 * custom webpack.config.js overriding wp-scripts' defaults (chunkFilename,
 * splitChunks tuning) — worth doing if a real theme accumulates enough
 * data-motion usage to matter, not done here to keep the setup simple.
 */
function AutoMount( { html, delay, y, duration } ) {
	return (
		<LazyMotion features={ domAnimation }>
			{ /*
			 * dangerouslySetInnerHTML is safe here specifically: `html` is
			 * el.innerHTML captured from a node WordPress already rendered
			 * into the live DOM (theme template / block / widget output,
			 * already through WP's own server-side escaping) before this
			 * script ever runs. Nothing untrusted or runtime-supplied is
			 * being injected — we're re-parenting content the browser
			 * already rendered once, under React, so Motion can animate it.
			 */ }
			<FadeIn
				delay={ delay }
				y={ y }
				duration={ duration }
				dangerouslySetInnerHTML={ { __html: html } }
			/>
		</LazyMotion>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '[data-motion="fade-in"]' ).forEach( ( el ) => {
		const html = el.innerHTML;
		const delay = parseFloat( el.dataset.motionDelay || '0' );
		const y = parseFloat( el.dataset.motionY || '24' );
		const duration = parseFloat( el.dataset.motionDuration || '0.5' );

		createRoot( el ).render(
			<AutoMount html={ html } delay={ delay } y={ y } duration={ duration } />
		);
	} );
} );
