import { Component } from 'react';
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
 * Catches descendant render failures inside one React island.
 *
 * Recovery is deferred until React has completed the current commit stack. A
 * synchronous unmount from componentDidCatch would otherwise trigger React's
 * update-during-render warning and could leave the root in an undefined state.
 */
class MotionIslandErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch() {
		const { onError } = this.props;

		if ( typeof queueMicrotask === 'function' ) {
			queueMicrotask( onError );
			return;
		}

		Promise.resolve().then( onError );
	}

	render() {
		return this.state.failed ? null : this.props.children;
	}
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
	let reactRoot;
	const recover = createIslandRecovery(
		element,
		originalHTML,
		() => reactRoot
	);

	try {
		reactRoot = createRootImpl( element );
		reactRoot.render(
			<MotionIslandErrorBoundary onError={ recover }>
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
			</MotionIslandErrorBoundary>
		);
		delete element.dataset.motionFailed;
		return true;
	} catch {
		recover();
		return false;
	}
}

/**
 * Creates an idempotent fallback restoration for a single island root.
 *
 * @param {Element}  element Server-rendered mount node.
 * @param {string}   html    Original inner HTML.
 * @param {Function} getRoot Returns the node's React root, when created.
 * @return {Function} Recovery callback.
 */
function createIslandRecovery( element, html, getRoot ) {
	let recovered = false;

	return () => {
		if ( recovered ) {
			return;
		}

		recovered = true;

		try {
			const root = getRoot();

			if ( root && typeof root.unmount === 'function' ) {
				root.unmount();
			}
		} catch {
			// Restoration below remains mandatory even if root cleanup fails.
		} finally {
			element.innerHTML = html;
			element.dataset.motionFailed = 'true';
		}
	};
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
function StreamMount( { streamUrl, siblingText, onError } ) {
	return (
		<LazyMotion features={ domAnimation }>
			<LayoutGroup>
				<m.div
					layout
					transition={ { duration: 0.8, ease: 'easeInOut' } }
				>
					<StreamingText streamUrl={ streamUrl } onError={ onError } />
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
	let reactRoot;
	const recover = createIslandRecovery(
		element,
		originalHTML,
		() => reactRoot
	);

	try {
		reactRoot = createRootImpl( element );
		reactRoot.render(
			<MotionIslandErrorBoundary onError={ recover }>
				<StreamMount
					streamUrl={ element.dataset.motionStream }
					siblingText={ element.dataset.motionStreamSibling || '' }
					onError={ recover }
				/>
			</MotionIslandErrorBoundary>
		);
		delete element.dataset.motionFailed;
		return true;
	} catch {
		recover();
		return false;
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	mountMotionIslands();
} );
