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
 * Enqueue the Motion bundle using the dependency list @wordpress/scripts
 * generated at build time (build/index.asset.php). That list already
 * includes 'react'/'react-dom'/whatever WP-core handles Motion's bundled
 * React imports resolved to — @wordpress/dependency-extraction-webpack-plugin
 * externalizes those automatically, so this script shares WordPress's own
 * React instance instead of loading a second copy.
 */
function mumega_motion_enqueue_assets() {
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
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_assets' );

/**
 * Theme supports.
 */
function mumega_motion_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
}
add_action( 'after_setup_theme', 'mumega_motion_setup' );
