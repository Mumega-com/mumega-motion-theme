<?php
/**
 * Figma-synced design tokens → CSS custom properties.
 *
 * Consumes the `mcpwp_figma_design_tokens` WP option — populated by the
 * separate MCPWP plugin's Figma sync feature, if MCPWP happens to be
 * installed and active on this WordPress site — and emits an inline
 * `<style id="mumega-figma-tokens">` block of `--figma-*` CSS custom
 * properties in `wp_head`.
 *
 * This theme has ZERO hard dependency on MCPWP at the PHP level: it only
 * ever reads a WP option that may or may not exist. If the option is
 * missing, empty, or malformed in any way, every function below degrades
 * to "no valid tokens" and the style block is simply not printed — theme
 * CSS then falls back entirely to its hardcoded `var(--figma-*, fallback)`
 * defaults (see style.css), so nothing breaks on a site that never had
 * MCPWP, never ran a Figma sync, or synced a Figma file with no shared
 * styles.
 *
 * Security note: the option value ultimately originates from an external
 * API (Figma, via MCPWP) written by a separate plugin. It is treated as
 * untrusted input at this boundary regardless of who owns the Figma
 * account — every color/font/number is validated against a strict
 * whitelist before it is allowed anywhere near the raw `<style>` block
 * this prints. Values that don't match are dropped, not escaped-and-kept,
 * since escaping alone doesn't make an attacker-controlled string safe to
 * splice into raw CSS.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch + defensively normalize the Figma design tokens option.
 *
 * @return array{colors: array<string,mixed>, typography: array<string,mixed>}
 */
function mumega_motion_get_figma_tokens() {
	$raw = get_option( 'mcpwp_figma_design_tokens', array() );

	if ( ! is_array( $raw ) ) {
		return array(
			'colors'     => array(),
			'typography' => array(),
		);
	}

	$colors     = isset( $raw['colors'] ) && is_array( $raw['colors'] ) ? $raw['colors'] : array();
	$typography = isset( $raw['typography'] ) && is_array( $raw['typography'] ) ? $raw['typography'] : array();

	return array(
		'colors'     => $colors,
		'typography' => $typography,
	);
}

/**
 * Validate + sanitize a color value for safe raw CSS output.
 *
 * Only hex (#rgb / #rgba / #rrggbb / #rrggbbaa) or rgb()/rgba() with purely
 * numeric channels are accepted — anything else is rejected outright. This
 * is deliberately a whitelist-and-reject strategy, not an escape-and-keep
 * one: the string is about to sit inside a raw `<style>` block, so there is
 * no safe way to "escape" arbitrary input the way esc_html()/esc_attr()
 * would for an HTML attribute.
 *
 * @param mixed $value Raw color value from the option.
 * @return string|null Sanitized CSS color, or null if invalid.
 */
function mumega_motion_sanitize_figma_color( $value ) {
	if ( ! is_string( $value ) ) {
		return null;
	}

	$value = trim( $value );

	if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
		return $value;
	}

	return null;
}

/**
 * Validate + sanitize a font-family value for safe raw CSS output.
 *
 * Strips to a whitelist of letters, digits, spaces, hyphens and commas,
 * then wraps the result in double quotes as a single CSS string. The
 * whitelist alone already excludes quote/angle-bracket/semicolon
 * characters, so there is nothing left that could break out of the
 * `<style>` block or the quoted string.
 *
 * @param mixed $value Raw font-family value from the option.
 * @return string|null Quoted, sanitized CSS string, or null if empty/invalid.
 */
function mumega_motion_sanitize_figma_font_family( $value ) {
	if ( ! is_string( $value ) ) {
		return null;
	}

	$clean = preg_replace( '/[^A-Za-z0-9 \-,]/', '', $value );
	$clean = is_string( $clean ) ? trim( $clean ) : '';

	if ( '' === $clean ) {
		return null;
	}

	return '"' . $clean . '"';
}

/**
 * Validate + sanitize a numeric token value (font-weight, font-size,
 * line-height) into an integer within an allowed range.
 *
 * @param mixed $value Raw numeric value from the option.
 * @param int   $min    Minimum accepted value (inclusive).
 * @param int   $max    Maximum accepted value (inclusive).
 * @return int|null Sanitized integer, or null if invalid/out of range.
 */
function mumega_motion_sanitize_figma_number( $value, $min, $max ) {
	if ( ! is_numeric( $value ) ) {
		return null;
	}

	$int = (int) round( (float) $value );

	if ( $int < $min || $int > $max ) {
		return null;
	}

	return $int;
}

/**
 * Build the full set of `--figma-*` CSS custom property declarations from
 * the tokens option. Every value is individually re-validated here — the
 * option's declared shape is never trusted as-is.
 *
 * Naming convention:
 * - `--figma-color-{slug}`
 * - `--figma-typography-{slug}-font-family`
 * - `--figma-typography-{slug}-font-weight`
 * - `--figma-typography-{slug}-font-size` (px baked in)
 * - `--figma-typography-{slug}-line-height` (px baked in)
 *
 * @return string CSS declarations (one `--custom-property: value;` per line), or '' if no valid tokens.
 */
function mumega_motion_build_figma_tokens_css() {
	$tokens = mumega_motion_get_figma_tokens();
	$lines  = array();

	foreach ( $tokens['colors'] as $slug => $value ) {
		$safe_slug  = sanitize_key( (string) $slug );
		$safe_value = mumega_motion_sanitize_figma_color( $value );

		if ( '' === $safe_slug || null === $safe_value ) {
			continue;
		}

		$lines[] = sprintf( '--figma-color-%s: %s;', $safe_slug, $safe_value );
	}

	foreach ( $tokens['typography'] as $slug => $props ) {
		$safe_slug = sanitize_key( (string) $slug );

		if ( '' === $safe_slug || ! is_array( $props ) ) {
			continue;
		}

		if ( isset( $props['fontFamily'] ) ) {
			$font_family = mumega_motion_sanitize_figma_font_family( $props['fontFamily'] );
			if ( null !== $font_family ) {
				$lines[] = sprintf( '--figma-typography-%s-font-family: %s;', $safe_slug, $font_family );
			}
		}

		if ( isset( $props['fontWeight'] ) ) {
			$font_weight = mumega_motion_sanitize_figma_number( $props['fontWeight'], 1, 1000 );
			if ( null !== $font_weight ) {
				$lines[] = sprintf( '--figma-typography-%s-font-weight: %d;', $safe_slug, $font_weight );
			}
		}

		if ( isset( $props['fontSize'] ) ) {
			$font_size = mumega_motion_sanitize_figma_number( $props['fontSize'], 1, 1000 );
			if ( null !== $font_size ) {
				$lines[] = sprintf( '--figma-typography-%s-font-size: %dpx;', $safe_slug, $font_size );
			}
		}

		if ( isset( $props['lineHeightPx'] ) ) {
			$line_height = mumega_motion_sanitize_figma_number( $props['lineHeightPx'], 1, 2000 );
			if ( null !== $line_height ) {
				$lines[] = sprintf( '--figma-typography-%s-line-height: %dpx;', $safe_slug, $line_height );
			}
		}
	}

	if ( empty( $lines ) ) {
		return '';
	}

	return implode( "\n\t", $lines );
}

/**
 * Emit the inline `<style id="mumega-figma-tokens">` block in wp_head.
 *
 * Prints nothing at all if there are zero valid tokens (MCPWP not
 * installed, never synced yet, or a Figma file with no shared styles) —
 * theme CSS then falls back entirely to its hardcoded
 * `var(--figma-*, fallback)` defaults, so the page renders identically to
 * how it did before this feature existed.
 */
function mumega_motion_output_figma_tokens_style() {
	$css = mumega_motion_build_figma_tokens_css();

	if ( '' === $css ) {
		return;
	}

	// Every value composing $css was individually whitelisted/rejected in
	// mumega_motion_build_figma_tokens_css() above — this is not a raw,
	// unescaped passthrough of option data.
	echo '<style id="mumega-figma-tokens">' . "\n:root {\n\t" . $css . "\n}\n" . '</style>' . "\n";
}
add_action( 'wp_head', 'mumega_motion_output_figma_tokens_style' );
