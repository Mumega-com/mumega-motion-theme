import { createElement, Fragment } from 'react';

/**
 * Creates a JSX element through WordPress core's active React implementation.
 * Static children are passed as individual arguments so development React can
 * record their key validation without requiring a second React runtime.
 *
 * @param {*}       type     React element type.
 * @param {Object}  props    Element properties.
 * @param {*}       key      Optional React key.
 * @param {boolean} isStatic Whether children came from a static JSX list.
 * @return {*} React element.
 */
function createJSXElement( type, props = {}, key, isStatic = false ) {
	const { children, ...elementProps } = props;

	if ( key !== undefined ) {
		elementProps.key = key;
	}

	if ( children === undefined ) {
		return createElement( type, elementProps );
	}

	if ( isStatic && Array.isArray( children ) ) {
		return createElement( type, elementProps, ...children );
	}

	return createElement( type, elementProps, children );
}

export { Fragment };

export function jsx( type, props, key ) {
	return createJSXElement( type, props, key );
}

export function jsxs( type, props, key ) {
	return createJSXElement( type, props, key, true );
}

export function jsxDEV( type, props, key, isStaticChildren ) {
	return createJSXElement( type, props, key, isStaticChildren );
}
