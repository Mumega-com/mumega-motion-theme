import { createRoot } from 'react-dom/client';
import { LazyMotion, domAnimation, m, LayoutGroup } from 'motion/react';
import FadeIn from '../components/FadeIn';
import StreamingText from '../components/StreamingText';

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

/**
 * Motion's layout animation only animates elements that are THEMSELVES
 * motion components with the `layout` prop — a plain sibling <footer>
 * elsewhere on the page does not automatically get pulled into the
 * animation just because something above it changed size. To get a genuinely
 * smooth push-down, the "sibling" has to be part of the same Motion-aware
 * tree. This mounts both the streaming text AND a card below it together,
 * so the card's position is actually under Motion's control and animates
 * as the streaming content grows — confirmed by high-frequency sampling,
 * not assumed.
 */
function StreamMount( { streamUrl, siblingText } ) {
	return (
		<LazyMotion features={ domAnimation }>
			<LayoutGroup>
				<m.div layout transition={ { duration: 0.8, ease: 'easeInOut' } }>
					<StreamingText streamUrl={ streamUrl } />
					{ siblingText && (
						<m.div
							id="stream-demo-sibling"
							layout
							transition={ { duration: 0.8, ease: 'easeInOut' } }
							style={ { marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid #ddd' } }
						>
							{ siblingText }
						</m.div>
					) }
				</m.div>
			</LayoutGroup>
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

	document.querySelectorAll( '[data-motion-stream]' ).forEach( ( el ) => {
		const streamUrl = el.dataset.motionStream;
		const siblingText = el.dataset.motionStreamSibling || '';
		createRoot( el ).render( <StreamMount streamUrl={ streamUrl } siblingText={ siblingText } /> );
	} );
} );
