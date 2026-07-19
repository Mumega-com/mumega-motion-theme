const fs = require( 'fs' );
const path = require( 'path' );
const postcss = require( 'postcss' );

const themeRoot = path.resolve( __dirname, '../..' );
const editorialRoot = postcss.parse(
	fs.readFileSync(
		path.join( themeRoot, 'assets/css/editorial.css' ),
		'utf8'
	)
);
const printRoot = postcss.parse(
	fs.readFileSync( path.join( themeRoot, 'assets/css/print.css' ), 'utf8' )
);

function ruleFor( root, selector, media = null ) {
	let match = null;
	root.walkRules( ( rule ) => {
		const parentMedia =
			rule.parent.type === 'atrule' && rule.parent.name === 'media'
				? rule.parent.params
				: null;
		const selectors = rule.selectors || [];
		if ( selectors.includes( selector ) && parentMedia === media ) {
			match = rule;
		}
	} );
	return match;
}

function declaration( rule, property ) {
	if ( ! rule ) {
		return null;
	}
	const match = rule.nodes.find(
		( node ) => node.type === 'decl' && node.prop === property
	);
	return match ? match.value : null;
}

function computedDeclaration( root, selector, property, media = null ) {
	let value = null;
	root.walkRules( ( rule ) => {
		const parentMedia =
			rule.parent.type === 'atrule' && rule.parent.name === 'media'
				? rule.parent.params
				: null;
		if (
			( rule.selectors || [] ).includes( selector ) &&
			parentMedia === media
		) {
			const candidate = declaration( rule, property );
			if ( candidate !== null ) {
				value = candidate;
			}
		}
	} );
	return value;
}

function mediaAppliesAt( params, width ) {
	const minimum = params.match( /min-width:\s*(\d+)px/ );
	const maximum = params.match( /max-width:\s*(\d+(?:\.\d+)?)px/ );
	return (
		( ! minimum || width >= Number( minimum[ 1 ] ) ) &&
		( ! maximum || width <= Number( maximum[ 1 ] ) )
	);
}

function hexToRgb( hex ) {
	return hex
		.match( /[a-f\d]{2}/gi )
		.map( ( channel ) => parseInt( channel, 16 ) / 255 );
}

// Assign stable luminance weights without coupling the assertions to a browser.
function relativeLuminance( hex ) {
	const weights = [ 0.2126, 0.7152, 0.0722 ];
	return hexToRgb( hex )
		.map( ( channel ) =>
			channel <= 0.04045
				? channel / 12.92
				: ( ( channel + 0.055 ) / 1.055 ) ** 2.4
		)
		.reduce(
			( total, channel, index ) => total + channel * weights[ index ],
			0
		);
}

function contrastRatio( first, second ) {
	const values = [
		relativeLuminance( first ),
		relativeLuminance( second ),
	].sort( ( a, b ) => b - a );
	return ( values[ 0 ] + 0.05 ) / ( values[ 1 ] + 0.05 );
}

describe( 'editorial CSS contracts', () => {
	test( 'activates the source-ordered 7/5 desk at exactly 800px', () => {
		const media = '(min-width: 800px)';
		expect(
			computedDeclaration(
				editorialRoot,
				'.editorial-home',
				'grid-template-columns',
				media
			)
		).toBe( 'repeat(12, minmax(0, 1fr))' );
		expect(
			computedDeclaration(
				editorialRoot,
				'.editorial-home > .lead-story',
				'grid-column',
				media
			)
		).toBe( 'span 7' );
		expect(
			computedDeclaration(
				editorialRoot,
				'.editorial-home > .home-supporting',
				'grid-column',
				media
			)
		).toBe( 'span 5' );
		expect( mediaAppliesAt( media, 799 ) ).toBe( false );
		expect( mediaAppliesAt( media, 800 ) ).toBe( true );

		for ( const selector of [
			'.editorial-home > .lead-story',
			'.editorial-home > .home-supporting',
		] ) {
			expect(
				computedDeclaration( editorialRoot, selector, 'order', media )
			).toBeNull();
			expect(
				computedDeclaration(
					editorialRoot,
					selector,
					'grid-row',
					media
				)
			).toBeNull();
		}
	} );

	test( 'keeps the desk collapsed at 320px and at a 200%-zoom CSS viewport', () => {
		const desktopMedia = [];
		editorialRoot.walkAtRules( 'media', ( atRule ) => {
			let containsEditorialGrid = false;
			atRule.walkRules( ( rule ) => {
				if ( ( rule.selectors || [] ).includes( '.editorial-home' ) ) {
					containsEditorialGrid = true;
				}
			} );
			if ( containsEditorialGrid ) {
				desktopMedia.push( atRule.params );
			}
		} );
		expect( desktopMedia ).toContain( '(min-width: 800px)' );
		expect(
			desktopMedia.some( ( media ) => mediaAppliesAt( media, 320 ) )
		).toBe( false );
		expect(
			desktopMedia.some( ( media ) => mediaAppliesAt( media, 640 ) )
		).toBe( false );
	} );

	test( 'contains long tokens in every standalone page and listing title', () => {
		for ( const selector of [
			'.page-entry__title',
			'.post-summary__title',
		] ) {
			expect(
				computedDeclaration( editorialRoot, selector, 'overflow-wrap' )
			).toBe( 'anywhere' );
		}
		expect(
			computedDeclaration(
				editorialRoot,
				'.editorial-home > *',
				'min-width'
			)
		).toBe( '0' );
		expect(
			computedDeclaration(
				editorialRoot,
				'.article-body pre',
				'overflow-wrap'
			)
		).toBe( 'anywhere' );
	} );

	test( 'locks a three-pixel two-layer focus indicator to 3:1 contrast', () => {
		const focus = ruleFor( editorialRoot, ':focus-visible' );
		expect( declaration( focus, 'outline' ) ).toBe(
			'3px solid var(--editorial-paper)'
		);
		expect( declaration( focus, 'box-shadow' ) ).toBe(
			'0 0 0 6px var(--editorial-accent-ink)'
		);
		expect( contrastRatio( '#6545a4', '#f7f3ea' ) ).toBeGreaterThanOrEqual(
			3
		);
		expect( contrastRatio( '#f7f3ea', '#121824' ) ).toBeGreaterThanOrEqual(
			3
		);
	} );

	test( 'lets articles paginate while bounding print-sensitive elements', () => {
		const printMedia = 'print';
		expect(
			computedDeclaration(
				printRoot,
				'.article-body',
				'break-inside',
				printMedia
			)
		).toBeNull();
		for ( const selector of [
			'.article-header',
			'.article-featured-image',
			'.affiliate-disclosure',
			'.article-body blockquote',
			'.article-body figure',
			'.article-body table',
			'.article-body pre',
		] ) {
			expect(
				computedDeclaration(
					printRoot,
					selector,
					'break-inside',
					printMedia
				)
			).toBe( 'avoid' );
		}
		expect(
			computedDeclaration(
				printRoot,
				'.article-body h2',
				'break-after',
				printMedia
			)
		).toBe( 'avoid' );
		expect(
			computedDeclaration(
				printRoot,
				'.article-body p',
				'orphans',
				printMedia
			)
		).toBe( '3' );
		expect(
			computedDeclaration(
				printRoot,
				'.article-body p',
				'widows',
				printMedia
			)
		).toBe( '3' );
	} );
} );
