import { createRoot } from 'react-dom/client';
import { LazyMotion, domAnimation, m, LayoutGroup } from 'motion/react';
import FadeIn, { FADE_IN_DEFAULTS } from '../components/FadeIn';
import StreamingText from '../components/StreamingText';

/**
 * Parses a finite motion value without accepting partial numeric strings.
 *
 * @param {string|number|undefined} value    Candidate DOM value.
 * @param {number}                  fallback Safe fallback.
 * @return {number} Parsed value or the fallback.
 */
export function parseMotionNumber( value, fallback ) {
	if (
		value === undefined ||
		value === null ||
		String( value ).trim() === ''
	) {
		return fallback;
	}

	const parsed = Number( value );

	return Number.isFinite( parsed ) ? parsed : fallback;
}

/**
 * Checks the user's nonessential-motion preference.
 *
 * @param {Function|undefined} matchMedia Browser matchMedia implementation.
 * @return {boolean} Whether motion should be skipped.
 */
export function shouldReduceMotion(
	matchMedia = typeof window !== 'undefined' ? window.matchMedia : undefined
) {
	return (
		typeof matchMedia === 'function' &&
		matchMedia( '(prefers-reduced-motion: reduce)' ).matches === true
	);
}

/**
 * Wraps server-rendered fallback HTML in the bounded FadeIn component.
 *
 * @param {Element}  element        Explicit fade-in mount.
 * @param {Function} createRootImpl React root factory.
 * @return {boolean} Whether rendering was requested successfully.
 */
export function mountFadeInNode( element, createRootImpl = createRoot ) {
	if ( ! element || ! element.matches( '[data-motion="fade-in"]' ) ) {
		return false;
	}

	const originalHTML = element.innerHTML;

	try {
		createRootImpl( element ).render(
			<AutoMount
				html={ originalHTML }
				delay={ parseMotionNumber(
					element.dataset.motionDelay,
					FADE_IN_DEFAULTS.delay
				) }
				y={ parseMotionNumber(
					element.dataset.motionY,
					FADE_IN_DEFAULTS.y
				) }
				duration={ parseMotionNumber(
					element.dataset.motionDuration,
					FADE_IN_DEFAULTS.duration
				) }
			/>
		);
		delete element.dataset.motionFailed;
		return true;
	} catch {
		element.innerHTML = originalHTML;
		element.dataset.motionFailed = 'true';
		return false;
	}
}

/**
 * Mounts the explicit Motion selectors within a DOM root.
 *
 * Reduced-motion mode deliberately does not create React roots, preserving the
 * exact server-rendered markup and its normal document flow.
 *
 * @param {Document|Element} root           DOM query root.
 * @param {Function}         createRootImpl React root factory.
 * @return {number} Number of successful mount requests.
 */
export function mountMotionIslands(
	root = document,
	createRootImpl = createRoot
) {
	if ( ! root || shouldReduceMotion() ) {
		return 0;
	}

	let mounted = 0;

	root.querySelectorAll( '[data-motion="fade-in"]' ).forEach( ( element ) => {
		if ( mountFadeInNode( element, createRootImpl ) ) {
			mounted += 1;
		}
	} );

	root.querySelectorAll( '[data-motion-stream]' ).forEach( ( element ) => {
		if ( mountStreamingTextNode( element, createRootImpl ) ) {
			mounted += 1;
		}
	} );

	return mounted;
}

/**
 * Motion wrapper for a fade-in mount. The HTML is the fallback already
 * rendered and escaped by WordPress; it is captured before React owns the node.
 */
function AutoMount( { html, delay, y, duration } ) {
	return (
		<LazyMotion features={ domAnimation }>
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
 * StreamingText remains available only through data-motion-stream. Merely
 * loading this bundle or emitting a future island boundary never invokes it.
 */
function StreamMount( { streamUrl, siblingText } ) {
	return (
		<LazyMotion features={ domAnimation }>
			<LayoutGroup>
				<m.div
					layout
					transition={ { duration: 0.8, ease: 'easeInOut' } }
				>
					<StreamingText streamUrl={ streamUrl } />
					{ siblingText && (
						<m.div
							id="stream-demo-sibling"
							layout
							transition={ { duration: 0.8, ease: 'easeInOut' } }
							style={ {
								marginTop: '1rem',
								paddingTop: '1rem',
								borderTop: '1px solid #ddd',
							} }
						>
							{ siblingText }
						</m.div>
					) }
				</m.div>
			</LayoutGroup>
		</LazyMotion>
	);
}

/**
 * Replaces an explicit streaming fallback while isolating mount failures.
 *
 * @param {Element}  element        Explicit stream mount.
 * @param {Function} createRootImpl React root factory.
 * @return {boolean} Whether rendering was requested successfully.
 */
function mountStreamingTextNode( element, createRootImpl ) {
	const originalHTML = element.innerHTML;

	try {
		createRootImpl( element ).render(
			<StreamMount
				streamUrl={ element.dataset.motionStream }
				siblingText={ element.dataset.motionStreamSibling || '' }
			/>
		);
		delete element.dataset.motionFailed;
		return true;
	} catch {
		element.innerHTML = originalHTML;
		element.dataset.motionFailed = 'true';
		return false;
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	mountMotionIslands();
} );
