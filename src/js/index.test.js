import {
	mountFadeInNode,
	mountMotionIslands,
	parseMotionNumber,
	shouldReduceMotion,
} from './index';

function createRootRecorder( onRender = () => {} ) {
	const mountedNodes = [];
	const renderedElements = [];
	const createRoot = jest.fn( ( node ) => {
		mountedNodes.push( node );

		return {
			render: ( element ) => {
				renderedElements.push( element );
				onRender( node, element );
			},
		};
	} );

	return { createRoot, mountedNodes, renderedElements };
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

		expect( recorder.renderedElements[ 0 ].props ).toMatchObject( {
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
		expect( recorder.renderedElements ).toHaveLength( 2 );
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
		expect( recorder.renderedElements[ 0 ].props ).toMatchObject( {
			streamUrl: '/stream',
			siblingText: 'Evidence',
		} );
	} );
} );
