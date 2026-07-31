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
			'primary'   => __( 'Primary Navigation', 'mumega-motion' ),
			'footer'    => __( 'Footer Navigation', 'mumega-motion' ),
			'audiences' => __( 'Audience Pathways', 'mumega-motion' ),
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
 * Registers the supported Theme Builder locations when Elementor Pro is active.
 *
 * Explicit location registration keeps Elementor from replacing get_header()
 * and get_footer() through its compatibility layer. Templates can then ask
 * Elementor for a matching location and retain the native theme fallback.
 *
 * @param object $manager Elementor's theme location manager.
 * @return void
 */
function mumega_motion_register_elementor_locations( $manager ) {
	if ( ! is_object( $manager ) || ! is_callable( array( $manager, 'register_location' ) ) ) {
		return;
	}

	$manager->register_location( 'header' );
	$manager->register_location( 'footer' );
}
add_action( 'elementor/theme/register_locations', 'mumega_motion_register_elementor_locations' );

/**
 * Renders an Elementor Theme Builder location only when it emits real markup.
 *
 * Elementor may report a registered location as handled even when display
 * conditions exclude every template. Buffering the call prevents that empty
 * success response from suppressing the native theme fallback.
 *
 * @param string $location Theme Builder location name.
 * @return bool Whether non-empty Elementor markup was rendered.
 */
function mumega_motion_render_elementor_location( $location ) {
	if ( ! function_exists( 'elementor_theme_do_location' ) ) {
		return false;
	}

	ob_start();
	$handled = elementor_theme_do_location( $location );
	$markup  = (string) ob_get_clean();

	$double_quoted_marker = 'data-elementor-type="' . $location . '"';
	$single_quoted_marker = "data-elementor-type='" . $location . "'";
	$has_location_markup  = false !== strpos( $markup, $double_quoted_marker ) || false !== strpos( $markup, $single_quoted_marker );

	if ( ! $handled || ! $has_location_markup ) {
		return false;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor owns and escapes its rendered template markup.

	return true;
}

/**
 * Determines whether the request uses an editorial theme template.
 *
 * Legacy Elementor pages are deliberately excluded, so the editorial CSS and
 * optional Motion bundle do not alter their presentation or dependencies.
 *
 * @return bool
 */
function mumega_motion_is_editorial_view() {
	return is_page_template( 'page-templates/editorial-page.php' ) ||
		is_page_template( 'page-templates/editorial-home.php' ) ||
		is_page_template( 'page-templates/product-home.php' ) ||
		is_page_template( 'page-templates/control-home.php' ) ||
		is_singular( 'post' ) ||
		is_home() ||
		is_archive() ||
		is_search() ||
		is_404();
}

/**
 * Loads the site header without allowing Elementor's unsupported-theme
 * compatibility layer to intercept editorial-owned requests.
 *
 * @return void
 */
function mumega_motion_get_header() {
	if ( mumega_motion_is_editorial_view() ) {
		get_template_part( 'header' );

		return;
	}

	get_header();
}

/**
 * Loads the site footer without allowing Elementor's unsupported-theme
 * compatibility layer to intercept editorial-owned requests.
 *
 * @return void
 */
function mumega_motion_get_footer() {
	if ( mumega_motion_is_editorial_view() ) {
		get_template_part( 'footer' );

		return;
	}

	get_footer();
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
 * Enqueues the visual system owned by the product-home page template.
 *
 * @return void
 */
function mumega_motion_enqueue_product_home_styles() {
	if ( ! is_page_template( 'page-templates/product-home.php' ) ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );
	$uri     = get_template_directory_uri() . '/assets/css/';

	wp_enqueue_style(
		'mumega-motion-product-home',
		$uri . 'product-home.css',
		array( 'mumega-motion-editorial' ),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_product_home_styles' );

/**
 * Enqueues the visual system owned by the MCPWP control-plane homepage.
 *
 * @return void
 */
function mumega_motion_enqueue_control_home_styles() {
	if ( ! is_page_template( 'page-templates/control-home.php' ) ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );
	$uri     = get_template_directory_uri() . '/assets/css/';

	wp_enqueue_style(
		'mumega-motion-control-home',
		$uri . 'control-home.css',
		array( 'mumega-motion-editorial' ),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_enqueue_control_home_styles' );

/**
 * Renders the native WordPress search form inside product-owned page content.
 *
 * @return string Native search form markup.
 */
function mumega_motion_product_search_shortcode() {
	ob_start();
	get_search_form();

	return (string) ob_get_clean();
}
add_shortcode( 'mumega_product_search', 'mumega_motion_product_search_shortcode' );

/**
 * Renders ASTER through WordPress's responsive attachment image boundary.
 *
 * @param array $atts Shortcode attributes.
 * @return string Responsive image markup, or an empty string without an ID.
 */
function mumega_motion_product_aster_image_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id' => 0,
		),
		$atts,
		'mumega_product_aster_image'
	);

	$attachment_id = (int) $atts['id'];

	if ( $attachment_id <= 0 ) {
		return '';
	}

	return wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'class'   => 'mcpwp-product-home__portrait',
			'alt'     => __( 'ASTER, MCPWP’s AI Research Editor', 'mumega-motion' ),
			'loading' => 'eager',
			'sizes'   => '(max-width: 47.9375rem) calc(100vw - 2.5rem), 20rem',
		)
	);
}
add_shortcode( 'mumega_product_aster_image', 'mumega_motion_product_aster_image_shortcode' );

/**
 * Returns the explicitly owned homepage template paths.
 *
 * @return array<int,string> Owned page template paths.
 */
function mumega_motion_owned_home_templates() {
	return array(
		'page-templates/product-home.php',
		'page-templates/control-home.php',
	);
}

/**
 * Reports whether the current or supplied page uses an owned home template.
 *
 * @param int $post_id Optional page ID. Defaults to the current request.
 * @return bool Whether an owned home template is assigned.
 */
function mumega_motion_is_owned_home_template( $post_id = 0 ) {
	if ( (int) $post_id > 0 ) {
		return in_array( get_page_template_slug( (int) $post_id ), mumega_motion_owned_home_templates(), true );
	}

	foreach ( mumega_motion_owned_home_templates() as $template ) {
		if ( is_page_template( $template ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Reports whether an owned homepage has been promoted through Reading Settings.
 *
 * @param int $post_id Optional page ID. Defaults to the queried object.
 * @return bool Whether the page is the configured static front page.
 */
function mumega_motion_is_promoted_product_home( $post_id = 0 ) {
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return false;
	}

	$front_page_id = (int) get_option( 'page_on_front' );
	$post_id       = (int) $post_id > 0 ? (int) $post_id : get_queried_object_id();

	return $front_page_id > 0 && $front_page_id === $post_id;
}

/**
 * Prevents owned homepage previews from being indexed before promotion.
 *
 * @param array $robots Robots directives generated by WordPress.
 * @return array Filtered robots directives.
 */
function mumega_motion_filter_product_home_robots( $robots ) {
	if ( ! mumega_motion_is_owned_home_template() || mumega_motion_is_promoted_product_home() ) {
		return $robots;
	}

	$robots['noindex']  = true;
	$robots['nofollow'] = true;

	return $robots;
}
add_filter( 'wp_robots', 'mumega_motion_filter_product_home_robots' );

/**
 * Returns published owned-home page IDs that are still previews.
 *
 * @return array Preview page IDs.
 */
function mumega_motion_product_home_preview_ids() {
	$preview_ids = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'numberposts'            => -1,
			'meta_key'               => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The template assignment is the exclusion contract.
			'meta_value'             => mumega_motion_owned_home_templates(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The owned template assignments are the exclusion contract.
			'meta_compare'           => 'IN',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$preview_ids = array_values( array_unique( array_map( 'intval', $preview_ids ) ) );

	if ( 'page' === get_option( 'show_on_front' ) ) {
		$front_page_id = (int) get_option( 'page_on_front' );
		$preview_ids   = array_values(
			array_filter(
				$preview_ids,
				static function ( $post_id ) use ( $front_page_id ) {
					return $front_page_id <= 0 || $post_id !== $front_page_id;
				}
			)
		);
	}

	return $preview_ids;
}

/**
 * Keeps preview owned-home pages out of WordPress core sitemaps.
 *
 * @param array  $args      Query arguments for the sitemap provider.
 * @param string $post_type Post type handled by the sitemap provider.
 * @return array Filtered sitemap query arguments.
 */
function mumega_motion_exclude_product_home_sitemap_pages( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}

	$preview_ids = mumega_motion_product_home_preview_ids();

	if ( empty( $preview_ids ) ) {
		return $args;
	}

	$excluded_ids         = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
	$args['post__not_in'] = array_values( array_unique( array_merge( $excluded_ids, array_map( 'intval', $preview_ids ) ) ) );

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'mumega_motion_exclude_product_home_sitemap_pages', 10, 2 );

/**
 * Adds unpromoted owned-home pages to Yoast's sitemap exclusion IDs.
 *
 * @param array $excluded_ids Existing excluded post IDs.
 * @return array Filtered excluded post IDs.
 */
function mumega_motion_exclude_product_home_from_yoast_sitemaps( $excluded_ids ) {
	return array_values(
		array_unique(
			array_merge( (array) $excluded_ids, mumega_motion_product_home_preview_ids() )
		)
	);
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'mumega_motion_exclude_product_home_from_yoast_sitemaps' );

/**
 * Removes an unpromoted owned-home page entry from Rank Math sitemaps.
 *
 * @param array|false $url            Sitemap URL entry.
 * @param string      $type           Sitemap object type.
 * @param object      $sitemap_object Sitemap object.
 * @return array|false Filtered sitemap URL entry.
 */
function mumega_motion_filter_rank_math_sitemap_entry( $url, $type, $sitemap_object ) {
	if ( 'post' !== $type || ! is_object( $sitemap_object ) || empty( $sitemap_object->ID ) ) {
		return $url;
	}

	$post_id = (int) $sitemap_object->ID;

	if ( ! mumega_motion_is_owned_home_template( $post_id ) || mumega_motion_is_promoted_product_home( $post_id ) ) {
		return $url;
	}

	return false;
}
add_filter( 'rank_math/sitemap/entry', 'mumega_motion_filter_rank_math_sitemap_entry', 10, 3 );

/**
 * Identifies an asset registered by Elementor or Elementor Pro.
 *
 * @param string $handle     Registered asset handle.
 * @param mixed  $dependency Registered WordPress dependency object.
 * @return bool Whether the asset belongs to Elementor's front end.
 */
function mumega_motion_is_elementor_asset( $handle, $dependency ) {
	if ( 0 === strpos( $handle, 'elementor-gf-' ) ) {
		return true;
	}

	if ( ! is_object( $dependency ) || ! isset( $dependency->src ) || ! is_string( $dependency->src ) ) {
		return false;
	}

	foreach ( array( '/plugins/elementor/', '/plugins/elementor-pro/', '/uploads/elementor/' ) as $marker ) {
		if ( false !== strpos( $dependency->src, $marker ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Removes Theme Builder shell assets from theme-owned editorial routes.
 *
 * Elementor Pro can enqueue a globally conditioned header or footer before the
 * theme deliberately chooses its native editorial shell. Those assets then run
 * without rendered Elementor configuration and can throw on otherwise native
 * pages. Legacy pages keep the complete Elementor queue.
 *
 * @return void
 */
function mumega_motion_remove_editorial_elementor_assets() {
	if ( ! mumega_motion_is_editorial_view() ) {
		return;
	}

	global $wp_scripts, $wp_styles;

	$registries = array(
		'script' => $wp_scripts,
		'style'  => $wp_styles,
	);

	foreach ( $registries as $type => $registry ) {
		if ( ! is_object( $registry ) || ! isset( $registry->queue, $registry->registered ) || ! is_array( $registry->queue ) || ! is_array( $registry->registered ) ) {
			continue;
		}

		foreach ( $registry->queue as $handle ) {
			if ( ! isset( $registry->registered[ $handle ] ) || ! mumega_motion_is_elementor_asset( $handle, $registry->registered[ $handle ] ) ) {
				continue;
			}

			if ( 'script' === $type ) {
				wp_dequeue_script( $handle );
			} else {
				wp_dequeue_style( $handle );
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'mumega_motion_remove_editorial_elementor_assets', PHP_INT_MAX );

/**
 * Enqueues the optional Motion bundle when a page declares a mount.
 *
 * The generated dependency list preserves WordPress core's React runtime.
 * WordPress's build tooling externalizes React rather than bundling a second copy.
 *
 * @return void
 */
function mumega_motion_enqueue_motion_assets() {
	if ( is_page_template( 'elementor_header_footer' ) ) {
		return;
	}

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
