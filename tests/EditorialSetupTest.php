<?php
/**
 * Tests for editorial theme setup and front-end asset boundaries.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-setup.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-setup.php';
}

$GLOBALS['mumega_motion_test_setup_filters']    = $GLOBALS['mumega_motion_test_filters'];
$GLOBALS['mumega_motion_test_setup_shortcodes'] = $GLOBALS['mumega_motion_test_shortcodes'];

/**
 * Exercises theme capabilities and opt-in editorial assets.
 */
final class EditorialSetupTest extends TestCase {
	/**
	 * Resets request state and registered assets between assertions.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_theme_supports'] = array();

		$GLOBALS['mumega_motion_test_menu_locations'] = array();

		$GLOBALS['mumega_motion_test_enqueued_styles'] = array();

		$GLOBALS['mumega_motion_test_enqueued_scripts'] = array();

		$GLOBALS['mumega_motion_test_dequeued_styles'] = array();

		$GLOBALS['mumega_motion_test_dequeued_scripts'] = array();

		$GLOBALS['wp_styles'] = null;

		$GLOBALS['wp_scripts'] = null;

		$GLOBALS['mumega_motion_test_conditionals'] = array();

		$GLOBALS['mumega_motion_test_page_template'] = '';

		$GLOBALS['mumega_motion_test_page_templates'] = array();

		$GLOBALS['mumega_motion_test_queried_object_id'] = 0;

		$GLOBALS['mumega_motion_test_posts'] = array();

		$GLOBALS['mumega_motion_test_post_queries'] = array();

		$GLOBALS['mumega_motion_test_get_posts_requests'] = array();

		$GLOBALS['mumega_motion_test_options'] = array();

		$GLOBALS['mumega_motion_test_filters'] = $GLOBALS['mumega_motion_test_setup_filters'];

		$GLOBALS['mumega_motion_test_shortcodes'] = $GLOBALS['mumega_motion_test_setup_shortcodes'];

		$GLOBALS['mumega_motion_test_attachment_image_requests'] = array();

		$GLOBALS['mumega_motion_test_search_form_calls'] = 0;

		$GLOBALS['mumega_motion_test_elementor_locations'] = array();

		$GLOBALS['mumega_motion_test_elementor_shell_calls'] = array();
	}

	/**
	 * Registers only the Elementor locations the theme explicitly renders.
	 */
	public function test_registers_header_and_footer_elementor_locations(): void {
		$manager = new class() {
			/**
			 * Registered location names.
			 *
			 * @var array
			 */
			public $locations = array();

			/**
			 * Records a supported Theme Builder location.
			 *
			 * @param string $location Theme Builder location.
			 */
			public function register_location( $location ): void {
				$this->locations[] = $location;
			}
		};

		mumega_motion_register_elementor_locations( $manager );

		$this->assertSame( array( 'header', 'footer' ), $manager->locations );
	}

	/**
	 * Keeps every public template on WordPress's document-shell contract.
	 */
	public function test_public_templates_keep_wordpress_header_and_footer_calls(): void {
		$templates = array(
			'404.php',
			'archive.php',
			'home.php',
			'index.php',
			'page.php',
			'search.php',
			'single.php',
			'page-templates/editorial-page.php',
			'page-templates/editorial-home.php',
		);

		foreach ( $templates as $template ) {
			$contents = file_get_contents( dirname( __DIR__ ) . '/' . $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect local theme templates.

			$this->assertIsString( $contents, $template );
			$this->assertStringContainsString( 'mumega_motion_get_header();', $contents, $template );
			$this->assertStringContainsString( 'mumega_motion_get_footer();', $contents, $template );
		}
	}

	/**
	 * Registers the editorial menu locations and core block-editor features.
	 */
	public function test_setup_registers_navigation_and_editorial_theme_supports(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-setup.php' );

		mumega_motion_setup();

		$this->assertSame(
			array(
				'primary'   => 'Primary Navigation',
				'footer'    => 'Footer Navigation',
				'audiences' => 'Audience Pathways',
			),
			$GLOBALS['mumega_motion_test_menu_locations']
		);
		$this->assertArrayHasKey( 'title-tag', $GLOBALS['mumega_motion_test_theme_supports'] );
		$this->assertArrayHasKey( 'post-thumbnails', $GLOBALS['mumega_motion_test_theme_supports'] );
		$this->assertSame(
			array( array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) ),
			$GLOBALS['mumega_motion_test_theme_supports']['html5']
		);

		foreach ( array( 'responsive-embeds', 'align-wide', 'editor-styles', 'wp-block-styles' ) as $support ) {
			$this->assertArrayHasKey( $support, $GLOBALS['mumega_motion_test_theme_supports'] );
		}
	}

	/**
	 * Recognizes only templates and request types owned by the editorial system.
	 */
	public function test_editorial_view_excludes_an_ordinary_elementor_page(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-setup.php' );
		$this->assertFalse( mumega_motion_is_editorial_view() );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/editorial-page.php';
		$this->assertTrue( mumega_motion_is_editorial_view() );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/editorial-home.php';
		$this->assertTrue( mumega_motion_is_editorial_view() );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/product-home.php';
		$this->assertTrue( mumega_motion_is_editorial_view() );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/control-home.php';
		$this->assertTrue( mumega_motion_is_editorial_view() );

		foreach ( array( 'is_singular', 'is_home', 'is_archive', 'is_search', 'is_404' ) as $conditional ) {
			$GLOBALS['mumega_motion_test_page_template'] = '';
			$GLOBALS['mumega_motion_test_conditionals']  = array( $conditional => true );
			$this->assertTrue( mumega_motion_is_editorial_view(), $conditional );
		}
	}

	/**
	 * Loads editorial and print CSS only for the views that the theme owns.
	 */
	public function test_editorial_styles_are_conditional_and_print_css_uses_print_media(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-setup.php' );
		$this->assertFileExists( dirname( __DIR__ ) . '/assets/css/editorial.css' );
		$this->assertFileExists( dirname( __DIR__ ) . '/assets/css/print.css' );
		mumega_motion_enqueue_editorial_styles();
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_enqueued_styles'] );

		$GLOBALS['mumega_motion_test_conditionals'] = array( 'is_archive' => true );
		mumega_motion_enqueue_editorial_styles();

		$this->assertArrayHasKey( 'mumega-motion-editorial', $GLOBALS['mumega_motion_test_enqueued_styles'] );
		$this->assertSame( 'all', $GLOBALS['mumega_motion_test_enqueued_styles']['mumega-motion-editorial']['media'] );
		$this->assertArrayHasKey( 'mumega-motion-print', $GLOBALS['mumega_motion_test_enqueued_styles'] );
		$this->assertSame( 'print', $GLOBALS['mumega_motion_test_enqueued_styles']['mumega-motion-print']['media'] );
	}

	/**
	 * Loads the product-home presentation layer only for its assigned template.
	 */
	public function test_product_home_styles_are_scoped_to_the_product_home_template(): void {
		mumega_motion_enqueue_product_home_styles();
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_enqueued_styles'] );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/product-home.php';
		mumega_motion_enqueue_product_home_styles();

		$this->assertSame(
			array(
				'mumega-motion-product-home' => array(
					'src'   => 'https://example.test/wp-content/themes/mumega-motion/assets/css/product-home.css',
					'deps'  => array( 'mumega-motion-editorial' ),
					'ver'   => '0.1.0',
					'media' => 'all',
				),
			),
			$GLOBALS['mumega_motion_test_enqueued_styles']
		);
	}

	/**
	 * Catches control-home styles leaking onto ordinary or product-owned pages.
	 */
	public function test_control_home_styles_are_scoped_to_the_control_home_template(): void {
		$this->assertTrue( function_exists( 'mumega_motion_enqueue_control_home_styles' ) );

		if ( ! function_exists( 'mumega_motion_enqueue_control_home_styles' ) ) {
			return;
		}

		mumega_motion_enqueue_control_home_styles();
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_enqueued_styles'] );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/product-home.php';
		mumega_motion_enqueue_control_home_styles();
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_enqueued_styles'] );

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/control-home.php';
		mumega_motion_enqueue_control_home_styles();

		$this->assertSame(
			array(
				'mumega-motion-control-home' => array(
					'src'   => 'https://example.test/wp-content/themes/mumega-motion/assets/css/control-home.css',
					'deps'  => array( 'mumega-motion-editorial' ),
					'ver'   => '0.1.0',
					'media' => 'all',
				),
			),
			$GLOBALS['mumega_motion_test_enqueued_styles']
		);
	}

	/**
	 * Renders the product-header search through WordPress's native form function.
	 */
	public function test_product_search_shortcode_returns_one_native_search_form(): void {
		$this->assertArrayHasKey( 'mumega_product_search', $GLOBALS['mumega_motion_test_shortcodes'] );

		if ( ! isset( $GLOBALS['mumega_motion_test_shortcodes']['mumega_product_search'] ) ) {
			return;
		}

		$markup = call_user_func( $GLOBALS['mumega_motion_test_shortcodes']['mumega_product_search'] );

		$this->assertSame( 1, substr_count( $markup, 'role="search"' ) );
		$this->assertStringContainsString( 'class="search-form"', $markup );
		$this->assertSame( 1, $GLOBALS['mumega_motion_test_search_form_calls'] );
	}

	/**
	 * Renders ASTER through WordPress's responsive attachment boundary.
	 */
	public function test_product_aster_shortcode_requests_economic_responsive_markup(): void {
		$this->assertArrayHasKey( 'mumega_product_aster_image', $GLOBALS['mumega_motion_test_shortcodes'] );

		if ( ! isset( $GLOBALS['mumega_motion_test_shortcodes']['mumega_product_aster_image'] ) ) {
			return;
		}

		$markup = call_user_func(
			$GLOBALS['mumega_motion_test_shortcodes']['mumega_product_aster_image'],
			array( 'id' => '28' )
		);

		$this->assertStringContainsString( 'srcset="', $markup );
		$this->assertStringContainsString( 'sizes="(max-width: 47.9375rem) calc(100vw - 2.5rem), 20rem"', $markup );
		$this->assertSame(
			array(
				'attachment_id' => 28,
				'size'          => 'large',
				'icon'          => false,
				'attr'          => array(
					'class'   => 'mcpwp-product-home__portrait',
					'alt'     => 'ASTER, MCPWP’s AI Research Editor',
					'loading' => 'eager',
					'sizes'   => '(max-width: 47.9375rem) calc(100vw - 2.5rem), 20rem',
				),
			),
			$GLOBALS['mumega_motion_test_attachment_image_requests'][0]
		);
	}

	/**
	 * Keeps owned homepage previews out of search results until promotion.
	 */
	public function test_home_preview_robots_filter_is_scoped_to_owned_home_templates(): void {
		$robots = array( 'max-image-preview' => 'large' );

		$this->assertSame( $robots, apply_filters( 'wp_robots', $robots ) );

		foreach ( array( 'page-templates/product-home.php', 'page-templates/control-home.php' ) as $template ) {
			$GLOBALS['mumega_motion_test_page_template'] = $template;

			$this->assertSame(
				array(
					'max-image-preview' => 'large',
					'noindex'           => true,
					'nofollow'          => true,
				),
				apply_filters( 'wp_robots', $robots ),
				$template
			);
		}

		$GLOBALS['mumega_motion_test_options']           = array(
			'show_on_front' => 'page',
			'page_on_front' => 42,
		);
		$GLOBALS['mumega_motion_test_queried_object_id'] = 42;

		$this->assertSame( $robots, apply_filters( 'wp_robots', $robots ) );
	}

	/**
	 * Excludes owned homepage previews from page sitemaps without altering other post types.
	 */
	public function test_owned_home_pages_are_excluded_only_from_page_sitemaps(): void {
		$article_args = array( 'post__not_in' => array( 8 ) );

		$this->assertSame(
			$article_args,
			apply_filters( 'wp_sitemaps_posts_query_args', $article_args, 'post' )
		);
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_get_posts_requests'] );

		$GLOBALS['mumega_motion_test_post_queries'][] = array( 29, 31 );

		$this->assertSame(
			array( 'post__not_in' => array( 8, 29, 31 ) ),
			apply_filters( 'wp_sitemaps_posts_query_args', $article_args, 'page' )
		);
		$this->assertSame(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'numberposts'            => -1,
				'meta_key'               => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The template assignment is the exclusion contract.
				'meta_value'             => array( 'page-templates/product-home.php', 'page-templates/control-home.php' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The owned template assignments are the exclusion contract.
				'meta_compare'           => 'IN',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);

		$GLOBALS['mumega_motion_test_post_queries'][] = array( 29, 31 );
		$GLOBALS['mumega_motion_test_options']        = array(
			'show_on_front' => 'page',
			'page_on_front' => 29,
		);

		$this->assertSame(
			array( 'post__not_in' => array( 8, 31 ) ),
			apply_filters( 'wp_sitemaps_posts_query_args', $article_args, 'page' )
		);
	}

	/**
	 * Applies the same preview-only exclusion to Yoast and Rank Math sitemaps.
	 */
	public function test_seo_plugin_sitemap_filters_exclude_only_unpromoted_owned_home_pages(): void {
		$GLOBALS['mumega_motion_test_post_queries'][] = array( 29, 31 );

		$this->assertSame(
			array( 7, 29, 31 ),
			apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array( 7 ) )
		);

		$GLOBALS['mumega_motion_test_page_templates'][29] = 'page-templates/product-home.php';
		$post = new WP_Post( array( 'ID' => 29 ) );
		$url  = array( 'loc' => 'https://example.test/mcpwp-home-preview/' );

		$rank_math_entry = apply_filters( 'rank_math/sitemap/entry', $url, 'post', $post ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Rank Math's documented hook uses slashes.
		$this->assertFalse( $rank_math_entry );

		$GLOBALS['mumega_motion_test_page_templates'][31] = 'page-templates/control-home.php';
		$post = new WP_Post( array( 'ID' => 31 ) );
		$this->assertFalse(
			apply_filters( 'rank_math/sitemap/entry', $url, 'post', $post ) // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Rank Math's documented hook uses slashes.
		);

		$GLOBALS['mumega_motion_test_options'] = array(
			'show_on_front' => 'page',
			'page_on_front' => 29,
		);

		$GLOBALS['mumega_motion_test_post_queries'][] = array( 29, 31 );
		$this->assertSame(
			array( 7, 31 ),
			apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array( 7 ) )
		);
		$post = new WP_Post( array( 'ID' => 29 ) );
		$rank_math_entry = apply_filters( 'rank_math/sitemap/entry', $url, 'post', $post ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Rank Math's documented hook uses slashes.
		$this->assertSame( $url, $rank_math_entry );
	}

	/**
	 * Removes Elementor shell assets only from routes owned by the editorial system.
	 */
	public function test_editorial_views_remove_elementor_shell_assets_without_touching_other_assets(): void {
		$GLOBALS['wp_styles']  = (object) array(
			'queue'      => array( 'mumega-motion-style', 'elementor-frontend', 'elementor-post-315', 'elementor-gf-inter' ),
			'registered' => array(
				'mumega-motion-style' => (object) array( 'src' => 'https://example.test/wp-content/themes/mumega-motion/style.css' ),
				'elementor-frontend'  => (object) array( 'src' => 'https://example.test/wp-content/plugins/elementor/assets/css/frontend.min.css' ),
				'elementor-post-315'  => (object) array( 'src' => 'https://example.test/wp-content/uploads/elementor/css/post-315.css' ),
				'elementor-gf-inter'  => (object) array( 'src' => 'https://fonts.googleapis.com/css?family=Inter' ),
			),
		);
		$GLOBALS['wp_scripts'] = (object) array(
			'queue'      => array( 'jquery', 'elementor-webpack-runtime', 'elementor-frontend', 'smartmenus' ),
			'registered' => array(
				'jquery'                    => (object) array( 'src' => 'https://example.test/wp-includes/js/jquery/jquery.min.js' ),
				'elementor-webpack-runtime' => (object) array( 'src' => 'https://example.test/wp-content/plugins/elementor/assets/js/webpack.runtime.min.js' ),
				'elementor-frontend'        => (object) array( 'src' => 'https://example.test/wp-content/plugins/elementor/assets/js/frontend.min.js' ),
				'smartmenus'                => (object) array( 'src' => 'https://example.test/wp-content/plugins/elementor-pro/assets/lib/smartmenus/jquery.smartmenus.min.js' ),
			),
		);

		mumega_motion_remove_editorial_elementor_assets();
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_dequeued_styles'] );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_dequeued_scripts'] );

		$GLOBALS['mumega_motion_test_conditionals'] = array( 'is_archive' => true );
		mumega_motion_remove_editorial_elementor_assets();

		$this->assertSame(
			array( 'elementor-frontend', 'elementor-post-315', 'elementor-gf-inter' ),
			$GLOBALS['mumega_motion_test_dequeued_styles']
		);
		$this->assertSame(
			array( 'elementor-webpack-runtime', 'elementor-frontend', 'smartmenus' ),
			$GLOBALS['mumega_motion_test_dequeued_scripts']
		);
		$this->assertNotContains( 'mumega-motion-style', $GLOBALS['mumega_motion_test_dequeued_styles'] );
		$this->assertNotContains( 'jquery', $GLOBALS['mumega_motion_test_dequeued_scripts'] );
	}

	/**
	 * Detects an explicit progressive-enhancement mount in the queried content.
	 */
	public function test_page_motion_mount_detection_requires_a_declared_mount(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-setup.php' );
		$GLOBALS['mumega_motion_test_queried_object_id'] = 42;
		$GLOBALS['mumega_motion_test_posts'][42]         = new WP_Post(
			array(
				'ID'           => 42,
				'post_content' => '<p>Static content.</p>',
			)
		);
		$this->assertFalse( mumega_motion_page_has_motion_mounts() );

		$GLOBALS['mumega_motion_test_posts'][42]->post_content = '<div data-motion="fade-in">Visible fallback</div>';
		$this->assertTrue( mumega_motion_page_has_motion_mounts() );
	}

	/**
	 * Enqueues Motion only for a declared mount or an explicit filter opt-in.
	 */
	public function test_motion_assets_require_a_mount_or_filter_opt_in(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-setup.php' );
		mumega_motion_enqueue_motion_assets();
		$this->assertArrayNotHasKey( 'mumega-motion', $GLOBALS['mumega_motion_test_enqueued_scripts'] );

		add_filter(
			'mumega_motion_enqueue_motion',
			static function () {
				return true;
			}
		);
		mumega_motion_enqueue_motion_assets();
		$this->assertArrayHasKey( 'mumega-motion', $GLOBALS['mumega_motion_test_enqueued_scripts'] );
	}

	/**
	 * Leaves a mount-free posts index outside the optional Motion bundle.
	 */
	public function test_posts_index_without_explicit_mount_does_not_load_motion(): void {
		$GLOBALS['mumega_motion_test_conditionals'] = array( 'is_home' => true );
		$this->assertSame( 0, get_queried_object_id() );
		mumega_motion_enqueue_motion_assets();
		$this->assertArrayNotHasKey( 'mumega-motion', $GLOBALS['mumega_motion_test_enqueued_scripts'] );
		$this->assertFalse( function_exists( 'mumega_motion_declare_legacy_demo_mounts' ) );
	}

	/**
	 * Keeps the generated bundle compatible with WordPress 6.5's React handles.
	 */
	public function test_motion_bundle_uses_react_dependencies_available_in_wordpress_65(): void {
		$asset = require dirname( __DIR__ ) . '/build/index.asset.php';

		$this->assertSame( array( 'react', 'react-dom' ), $asset['dependencies'] );
		$this->assertNotContains( 'react-jsx-runtime', $asset['dependencies'] );
	}
}
