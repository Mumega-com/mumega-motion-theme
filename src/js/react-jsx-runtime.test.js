import { jsx, jsxs } from './react-jsx-runtime';

describe( 'WordPress React JSX adapter', () => {
	it( 'creates static children through the active React implementation', () => {
		const first = jsx( 'span', { children: 'First' }, 'first' );
		const second = jsx( 'span', { children: 'Second' }, 'second' );
		const parent = jsxs( 'div', { children: [ first, second ] } );

		expect( parent.props.children ).toEqual( [ first, second ] );
		expect( first._store.validated ).toBe( true );
		expect( second._store.validated ).toBe( true );
	} );
} );
