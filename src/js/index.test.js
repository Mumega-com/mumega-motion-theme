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
} );
