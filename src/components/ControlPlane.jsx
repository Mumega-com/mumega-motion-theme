import { useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, LazyMotion, domAnimation, m } from 'motion/react';

/**
 * Reads the minimum server-owned content needed to enhance a control plane.
 *
 * @param {Element} element Explicit control-plane mount.
 * @return {Object|null} Parsed configuration or null for malformed fallback markup.
 */
export function readControlPlaneConfig(
	element,
	locationHash = typeof window !== 'undefined' ? window.location.hash : ''
) {
	if ( ! element || ! element.matches( '[data-mcpwp-control-plane]' ) ) {
		return null;
	}

	const summary = element.querySelector( '[data-mcpwp-control-summary]' );

	if ( ! summary ) {
		return null;
	}

	const seen = new Set();
	const routes = Array.from(
		element.querySelectorAll( '[data-mcpwp-intent]' )
	).reduce( ( parsed, anchor ) => {
		const id = ( anchor.dataset.mcpwpIntent || '' ).trim();
		const label = ( anchor.querySelector( 'strong' )?.textContent || '' ).trim();
		const route = {
			id,
			label,
			summary: ( anchor.dataset.mcpwpSummary || '' ).trim(),
			actionLabel: ( anchor.dataset.mcpwpActionLabel || '' ).trim(),
			actionHref: ( anchor.dataset.mcpwpActionHref || '' ).trim(),
			accent: ( anchor.dataset.mcpwpAccent || '' ).trim(),
			eventName: ( anchor.dataset.mcpwpEvent || '' ).trim(),
		};

		if (
			! /^[a-z][a-z0-9-]*$/.test( id ) ||
			seen.has( id ) ||
			anchor.getAttribute( 'href' ) !== `#${ id }` ||
			Object.values( route ).some( ( value ) => value === '' )
		) {
			return parsed;
		}

		seen.add( id );
		parsed.push( route );
		return parsed;
	}, [] );

	if ( routes.length === 0 ) {
		return null;
	}

	const summaryParagraphs = Array.from( summary.querySelectorAll( 'p' ) );
	const defaultSummary = {
		eyebrow: (
			summary.querySelector( '.mcpwp-control-home__eyebrow' )
				?.textContent || 'Current route'
		).trim(),
		title: ( summary.querySelector( 'strong' )?.textContent || '' ).trim(),
		description: (
			summaryParagraphs[ summaryParagraphs.length - 1 ]?.textContent || ''
		).trim(),
	};
	const staticClone = element.cloneNode( true );
	staticClone.querySelector( '[data-mcpwp-control-summary]' )?.remove();

	let fragment = String( locationHash || '' ).replace( /^#/, '' );

	try {
		fragment = decodeURIComponent( fragment );
	} catch {
		fragment = '';
	}

	const initialIntent = routes.some( ( route ) => route.id === fragment )
		? fragment
		: '';

	return {
		routes,
		defaultSummary,
		initialIntent,
		staticHTML: staticClone.innerHTML,
	};
}

/**
 * Progressively enhances server-owned route links and its live summary.
 */
export default function ControlPlane( {
	staticHTML,
	routes,
	defaultSummary,
	initialIntent = '',
	onIntent,
} ) {
	const [ selectedIntent, setSelectedIntent ] = useState( initialIntent );
	const enhancementRef = useRef( null );
	const selectedRoute = useMemo(
		() => routes.find( ( route ) => route.id === selectedIntent ) || null,
		[ routes, selectedIntent ]
	);

	useEffect( () => {
		const enhancement = enhancementRef.current;

		if ( ! enhancement ) {
			return;
		}

		enhancement.querySelectorAll( '[data-mcpwp-intent]' ).forEach( ( link ) => {
			if ( link.dataset.mcpwpIntent === selectedIntent ) {
				link.setAttribute( 'aria-current', 'location' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	}, [ selectedIntent ] );

	const selectIntent = ( event ) => {
		const link = event.target.closest( '[data-mcpwp-intent]' );

		if ( ! link || ! enhancementRef.current?.contains( link ) ) {
			return;
		}

		const route = routes.find(
			( candidate ) => candidate.id === link.dataset.mcpwpIntent
		);

		if ( ! route ) {
			return;
		}

		event.preventDefault();
		setSelectedIntent( route.id );
		onIntent( route );
	};

	return (
		<LazyMotion features={ domAnimation }>
			<div
				className="mcpwp-control-plane__enhancement"
				onClick={ selectIntent }
				ref={ enhancementRef }
			>
				<div
					className="mcpwp-control-plane__static"
					dangerouslySetInnerHTML={ { __html: staticHTML } }
				/>
				<AnimatePresence initial={ false } mode="sync">
					<m.aside
						animate={ { opacity: 1, y: 0 } }
						aria-atomic="true"
						aria-live="polite"
						className="mcpwp-control-plane__summary"
						exit={ { opacity: 0, y: -8 } }
						initial={ { opacity: 0, y: 8 } }
						key={ selectedRoute?.id || 'default' }
						transition={ { duration: 0.24, ease: 'easeOut' } }
					>
						<p className="mcpwp-control-home__eyebrow">
							{ selectedRoute
								? `${ selectedRoute.label } route`
								: defaultSummary.eyebrow }
						</p>
						<p>
							<strong>
								{ selectedRoute?.summary || defaultSummary.title }
							</strong>
						</p>
						{ selectedRoute ? (
							<p>
								<a href={ selectedRoute.actionHref }>
									{ selectedRoute.actionLabel }
									<span aria-hidden="true"> ↗</span>
								</a>
							</p>
						) : (
							<p>{ defaultSummary.description }</p>
						) }
					</m.aside>
				</AnimatePresence>
			</div>
		</LazyMotion>
	);
}
