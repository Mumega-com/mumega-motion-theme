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

		$GLOBALS['mumega_motion_test_conditionals'] = array();

		$GLOBALS['mumega_motion_test_page_template'] = '';

		$GLOBALS['mumega_motion_test_queried_object_id'] = 0;

		$GLOBALS['mumega_motion_test_posts'] = array();

		$GLOBALS['mumega_motion_test_filters'] = array();

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
			'page-templates/editorial-home.php',
		);

		foreach ( $templates as $template ) {
			$contents = file_get_contents( dirname( __DIR__ ) . '/' . $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect local theme templates.

			$this->assertIsString( $contents, $template );
			$this->assertStringContainsString( 'get_header();', $contents, $template );
			$this->assertStringContainsString( 'get_footer();', $contents, $template );
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
				'primary' => 'Primary Navigation',
				'footer'  => 'Footer Navigation',
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

		$GLOBALS['mumega_motion_test_page_template'] = 'page-templates/editorial-home.php';
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
