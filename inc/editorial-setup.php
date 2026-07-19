<?php
/**
 * Editorial theme capabilities and front-end asset boundaries.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WordPress capabilities used by editorial templates.
 *
 * @return void
 */
function mumega_motion_setup() {
	register_nav_menus(
		array(
			'primary' => __( 'Primary Navigation', 'mumega-motion' ),
			'footer'  => __( 'Footer Navigation', 'mumega-motion' ),
		)
	);

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'mumega_motion_setup' );

/**
 * Determines whether the request uses an editorial theme template.
 *
 * Legacy Elementor pages are deliberately excluded, so the editorial CSS and
 * optional Motion bundle do not alter their presentation or dependencies.
 *
 * @return bool
 */
function mumega_motion_is_editorial_view() {
	return is_page_template( 'page-templates/editorial-home.php' ) ||
		is_singular( 'post' ) ||
		is_home() ||
		is_archive() ||
		is_search() ||
		is_404();
}

/**
 * Checks the current content for an explicit progressive-enhancement mount.
 *
 * @return bool
 */
function mumega_motion_page_has_motion_mounts() {
	$post_id = get_queried_object_id();

	if ( $post_id <= 0 ) {
		return false;
	}

	$content = get_post_field( 'post_content', $post_id );

	return is_string( $content ) && 1 === preg_match( '/\\bdata-motion(?:-stream)?\\s*=/', $content );
}

/**
 * Enqueues styles owned by editorial request types.
 *
 * @return void
 */
function mumega_motion_enqueue_editorial_styles() {
	if ( ! mumega_motion_is_editorial_view() ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );
	$uri     = get_template_directory_uri() . '/assets/css/';

	wp_enqueue_style( 'mumega-motion-editorial', $uri . 'editorial.css', array( 'mumega-motion-style' ), $version );
	wp_enqueue_style( 'mumega-motion-print', $uri . 'print.css', array( 'mumega-motion-editorial' ), $version, 'print' );
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_editorial_styles' );

/**
 * Enqueues the optional Motion bundle when a page declares a mount.
 *
 * The generated dependency list preserves WordPress core's React runtime.
 * WordPress's build tooling externalizes React rather than bundling a second copy.
 *
 * @return void
 */
function mumega_motion_enqueue_motion_assets() {
	$enqueue = apply_filters( 'mumega_motion_enqueue_motion', mumega_motion_page_has_motion_mounts() );

	if ( ! $enqueue ) {
		return;
	}

	$asset_file = get_template_directory() . '/build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'mumega-motion',
		get_template_directory_uri() . '/build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_motion_assets' );
