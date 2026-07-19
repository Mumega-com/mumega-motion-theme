jest.mock( '../components/FadeIn', () => {
	const React = require( 'react' );
	const actual = jest.requireActual( '../components/FadeIn' );

	return {
		__esModule: true,
		...actual,
		default: ( props ) => {
			if (
				props.dangerouslySetInnerHTML.__html.includes(
					'data-test-react-render-error'
				)
			) {
				throw new Error( 'descendant render failed' );
			}

			return React.createElement( actual.default, props );
		},
	};
} );

import { act } from 'react';
import { createRoot as createReactRoot } from 'react-dom/client';
import {
	mountFadeInNode,
	mountMotionIslands,
	parseMotionNumber,
	shouldReduceMotion,
} from './index';

const originalFetch = global.fetch;
const originalTextDecoder = global.TextDecoder;

function createRootRecorder( onRender = () => {} ) {
	const mountedNodes = [];
	const renderedElements = [];
	const unmountedNodes = [];
	const createRoot = jest.fn( ( node ) => {
		mountedNodes.push( node );

		return {
			render: ( element ) => {
				renderedElements.push( element );
				onRender( node, element );
			},
			unmount: () => unmountedNodes.push( node ),
		};
	} );

	return { createRoot, mountedNodes, renderedElements, unmountedNodes };
}

describe( 'editorial Motion islands', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		window.matchMedia = jest.fn( () => ( { matches: false } ) );
		global.TextDecoder = class {
			decode( value ) {
				return value ? String( value ) : '';
			}
		};
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		global.TextDecoder = originalTextDecoder;
	} );

	test( 'parses only finite numeric motion values', () => {
		expect( parseMotionNumber( '1.25', 0 ) ).toBe( 1.25 );
		expect( parseMotionNumber( '12px', 24 ) ).toBe( 24 );
		expect( parseMotionNumber( 'Infinity', 0.5 ) ).toBe( 0.5 );
		expect( parseMotionNumber( '', 24 ) ).toBe( 24 );
	} );

	test( 'reports the reduced-motion preference when the browser exposes it', () => {
		const matchMedia = jest.fn( () => ( { matches: true } ) );

		expect( shouldReduceMotion( matchMedia ) ).toBe( true );
		expect( matchMedia ).toHaveBeenCalledWith(
			'(prefers-reduced-motion: reduce)'
		);
		expect( shouldReduceMotion( undefined ) ).toBe( false );
	} );

	test( 'mounts only nodes with an explicit fade-in marker', () => {
		document.body.innerHTML = `
			<section id="fade" data-motion="fade-in">Fade</section>
			<section id="other" data-motion="slide">Other</section>
			<section id="plain">Plain</section>
		`;
		const recorder = createRootRecorder();

		mountMotionIslands( document, recorder.createRoot );

		expect( recorder.mountedNodes ).toEqual( [
			document.getElementById( 'fade' ),
		] );
	} );

	test( 'uses safe defaults for invalid numeric attributes', () => {
		document.body.innerHTML = `
			<div
				id="fade"
				data-motion="fade-in"
				data-motion-delay="later"
				data-motion-y="12px"
				data-motion-duration="Infinity"
			>Fallback</div>
		`;
		const recorder = createRootRecorder();

		mountFadeInNode(
			document.getElementById( 'fade' ),
			recorder.createRoot
		);

		expect(
			recorder.renderedElements[ 0 ].props.children.props
		).toMatchObject( {
			delay: 0,
			y: 24,
			duration: 0.5,
		} );
	} );

	test( 'leaves all original markup untouched in reduced-motion mode', () => {
		document.body.innerHTML = `
			<section id="fade" data-motion="fade-in"><a href="/story">Story</a></section>
			<section id="stream" data-motion-stream="/stream">Stream fallback</section>
		`;
		const originalMarkup = document.body.innerHTML;
		const recorder = createRootRecorder();
		window.matchMedia = jest.fn( () => ( { matches: true } ) );

		mountMotionIslands( document, recorder.createRoot );

		expect( recorder.createRoot ).not.toHaveBeenCalled();
		expect( document.body.innerHTML ).toBe( originalMarkup );
	} );

	test( 'restores a failed node and continues mounting later nodes', () => {
		document.body.innerHTML = `
			<section id="first" data-motion="fade-in"><strong>First fallback</strong></section>
			<section id="second" data-motion="fade-in"><em>Second fallback</em></section>
		`;
		const first = document.getElementById( 'first' );
		const second = document.getElementById( 'second' );
		const recorder = createRootRecorder( ( node ) => {
			if ( node === first ) {
				node.innerHTML = '<span>Broken render</span>';
				throw new Error( 'render failed' );
			}
		} );

		mountMotionIslands( document, recorder.createRoot );

		expect( first.innerHTML ).toBe( '<strong>First fallback</strong>' );
		expect( first.dataset.motionFailed ).toBe( 'true' );
		expect( recorder.mountedNodes ).toEqual( [ first, second ] );
		expect( recorder.unmountedNodes ).toEqual( [ first ] );
		expect( recorder.renderedElements ).toHaveLength( 2 );
	} );

	test( 'recovers an asynchronous React render failure without blocking a later real root', async () => {
		document.body.innerHTML = `
			<section id="first" data-motion="fade-in"><strong data-test-react-render-error>First fallback</strong></section>
			<section id="second" data-motion="fade-in"><em>Second fallback</em></section>
		`;
		const first = document.getElementById( 'first' );
		const second = document.getElementById( 'second' );
		const originalFirstHTML = first.innerHTML;
		const originalSecondHTML = second.innerHTML;
		const previousActEnvironment = globalThis.IS_REACT_ACT_ENVIRONMENT;
		globalThis.IS_REACT_ACT_ENVIRONMENT = true;
		const roots = [];
		const createTrackedReactRoot = ( node ) => {
			const root = createReactRoot( node );
			const unmount = root.unmount.bind( root );

			root.unmount = jest.fn( unmount );
			roots.push( { node, root } );

			return root;
		};
		const consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );

		try {
			await act( async () => {
				mountMotionIslands( document, createTrackedReactRoot );
			} );

			const reactDiagnostics = consoleError.mock.calls
				.flat()
				.map( String )
				.join( '\n' );

			expect( reactDiagnostics ).not.toContain(
				'Attempted to synchronously unmount a root while React was already rendering'
			);
			expect( reactDiagnostics ).not.toContain( 'not wrapped in act' );
			expect( reactDiagnostics ).not.toContain(
				'The current testing environment is not configured to support act'
			);
			expect( first.innerHTML ).toBe( originalFirstHTML );
			expect( first.dataset.motionFailed ).toBe( 'true' );
			expect(
				roots.find( ( { node } ) => node === first ).root.unmount
			).toHaveBeenCalledTimes( 1 );
			expect(
				roots.find( ( { node } ) => node === second ).root.unmount
			).not.toHaveBeenCalled();
			expect( second.dataset.motionFailed ).toBeUndefined();
			expect( second.innerHTML ).not.toBe( originalSecondHTML );
			expect( second.textContent ).toBe( 'Second fallback' );
		} finally {
			consoleError.mockRestore();

			if ( previousActEnvironment === undefined ) {
				delete globalThis.IS_REACT_ACT_ENVIRONMENT;
			} else {
				globalThis.IS_REACT_ACT_ENVIRONMENT = previousActEnvironment;
			}
		}
	} );

	test( 'does not mount StreamingText without its explicit stream marker', () => {
		document.body.innerHTML = `
			<div data-motion-island="streaming-text" data-motion-data="{}">Future fallback</div>
			<div data-component="StreamingText">Unrelated content</div>
		`;
		const recorder = createRootRecorder();

		mountMotionIslands( document, recorder.createRoot );

		expect( recorder.createRoot ).not.toHaveBeenCalled();
	} );

	test( 'mounts StreamingText only for an explicit stream marker', () => {
		document.body.innerHTML = `
			<div id="stream" data-motion-stream="/stream" data-motion-stream-sibling="Evidence">Fallback</div>
		`;
		const recorder = createRootRecorder();

		mountMotionIslands( document, recorder.createRoot );

		expect( recorder.mountedNodes ).toEqual( [
			document.getElementById( 'stream' ),
		] );
		expect(
			recorder.renderedElements[ 0 ].props.children.props
		).toMatchObject( {
			streamUrl: '/stream',
			siblingText: 'Evidence',
		} );
	} );

	test.each( [
		[ 'a rejected fetch', () => Promise.reject( new Error( 'network failed' ) ) ],
		[ 'a non-success response', () => Promise.resolve( { ok: false, status: 503, body: {} } ) ],
		[ 'a response without a body', () => Promise.resolve( { ok: true, body: null } ) ],
		[
			'a reader failure',
			() => Promise.resolve( {
				ok: true,
				body: {
					getReader: () => ( {
						read: () => Promise.reject( new Error( 'reader failed' ) ),
					} ),
				},
			} ),
		],
	] )( 'restores the exact streaming fallback after %s', async ( label, fetchImpl ) => {
		document.body.innerHTML = '<div id="stream" data-motion-stream="/stream"><strong>Cached fallback</strong></div>';
		const stream = document.getElementById( 'stream' );
		const originalHTML = stream.innerHTML;
		const roots = [];
		global.fetch = jest.fn( fetchImpl );
		const previousActEnvironment = globalThis.IS_REACT_ACT_ENVIRONMENT;
		globalThis.IS_REACT_ACT_ENVIRONMENT = true;
		const consoleError = jest.spyOn( console, 'error' ).mockImplementation( () => {} );

		try {
			await act( async () => {
				mountMotionIslands( document, ( node ) => {
					const root = createReactRoot( node );
					roots.push( root );
					return root;
				} );
				await Promise.resolve();
				await Promise.resolve();
			} );

			expect( stream.innerHTML ).toBe( originalHTML );
			expect( stream.dataset.motionFailed ).toBe( 'true' );
			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} finally {
			consoleError.mockRestore();

			if ( previousActEnvironment === undefined ) {
				delete globalThis.IS_REACT_ACT_ENVIRONMENT;
			} else {
				globalThis.IS_REACT_ACT_ENVIRONMENT = previousActEnvironment;
			}
		}
	} );

	test( 'aborts an in-flight stream when its React island is cleaned up', async () => {
		document.body.innerHTML = '<div id="stream" data-motion-stream="/stream">Cached fallback</div>';
		let requestSignal;
		global.fetch = jest.fn( ( url, options ) => {
			requestSignal = options.signal;
			return new Promise( () => {} );
		} );
		const previousActEnvironment = globalThis.IS_REACT_ACT_ENVIRONMENT;
		globalThis.IS_REACT_ACT_ENVIRONMENT = true;
		const root = createReactRoot( document.getElementById( 'stream' ) );

		try {
			await act( async () => {
				root.render( <StreamingTextForCleanup /> );
				await Promise.resolve();
			} );

			expect( requestSignal ).toBeInstanceOf( AbortSignal );
			expect( requestSignal.aborted ).toBe( false );

			await act( async () => {
				root.unmount();
			} );

			expect( requestSignal.aborted ).toBe( true );
		} finally {
			if ( previousActEnvironment === undefined ) {
				delete globalThis.IS_REACT_ACT_ENVIRONMENT;
			} else {
				globalThis.IS_REACT_ACT_ENVIRONMENT = previousActEnvironment;
			}
		}
	} );
} );

function StreamingTextForCleanup() {
	const StreamingText = require( '../components/StreamingText' ).default;

	return <StreamingText streamUrl="/stream" />;
}
