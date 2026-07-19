<?php
/**
 * Bounded markup boundary for future editorial React islands.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders inert, allowlisted editorial island markup.
 *
 * This future boundary is intentionally separate from the current
 * data-motion/data-motion-stream runtime selectors. It cannot implicitly mount
 * a component and accepts neither executable data nor HTML fallback markup.
 *
 * @param mixed $name     Allowlisted island name.
 * @param mixed $data     JSON-safe scalar/array data.
 * @param mixed $fallback Plain-text server-rendered fallback.
 * @return string Bounded island markup, or an empty string for invalid input.
 */
function mumega_motion_render_editorial_island( $name, $data = array(), $fallback = '' ) {
	$allowed_names = array( 'fade-in', 'streaming-text' );

	if ( ! is_string( $name ) || ! in_array( $name, $allowed_names, true ) || ! is_array( $data ) || ! is_string( $fallback ) ) {
		return '';
	}

	$json = wp_json_encode( $data );

	if ( false === $json || ! mumega_motion_is_safe_island_data( $data ) ) {
		return '';
	}

	return sprintf(
		'<div data-motion-island="%s" data-motion-data="%s">%s</div>',
		esc_attr( $name ),
		esc_attr( $json ),
		esc_html( $fallback )
	);
}

/**
 * Rejects executable and non-JSON scalar values at any nesting depth.
 *
 * @param mixed $value Candidate island data value.
 * @return bool Whether the value is inert JSON data.
 */
function mumega_motion_is_safe_island_data( $value ) {
	if ( is_callable( $value ) || is_object( $value ) || is_resource( $value ) ) {
		return false;
	}

	if ( is_float( $value ) && ! is_finite( $value ) ) {
		return false;
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $nested_value ) {
			if ( ! mumega_motion_is_safe_island_data( $nested_value ) ) {
				return false;
			}
		}
	}

	return is_array( $value ) || is_null( $value ) || is_scalar( $value );
}
