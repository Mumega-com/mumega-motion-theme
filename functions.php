<?php
/**
 * Mumega Motion theme bootstrap.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the theme's own stylesheet (style.css). This is what the
 * `var(--figma-color-*, fallback)` / `var(--figma-typography-*, fallback)`
 * declarations in style.css resolve against — see inc/figma-tokens.php for
 * the custom properties themselves.
 */
function mumega_motion_enqueue_styles() {
	wp_enqueue_style( 'mumega-motion-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_styles' );

/**
 * Figma-synced design tokens (colors + typography) → CSS custom properties.
 * Reads the `mcpwp_figma_design_tokens` option that a separate plugin
 * (MCPWP) populates on Figma sync, if installed. Zero hard dependency on
 * MCPWP — degrades gracefully if the option/plugin is absent.
 */
require get_template_directory() . '/inc/figma-tokens.php';

/**
 * Pure editorial content and taxonomy helpers.
 */
require get_template_directory() . '/inc/editorial-helpers.php';

/**
 * Deterministic editorial eligibility and category discovery.
 */
require get_template_directory() . '/inc/editorial-queries.php';

/**
 * Editorial theme capabilities and conditional front-end assets.
 */
require get_template_directory() . '/inc/editorial-setup.php';

/**
 * Reusable Gutenberg patterns for editorial content.
 */
require get_template_directory() . '/inc/editorial-patterns.php';

/**
 * Fixed-repository verified update system, with dashboard fallback independent
 * of the optional MCPWP extension hook.
 */
require get_template_directory() . '/inc/updates/bootstrap.php';
