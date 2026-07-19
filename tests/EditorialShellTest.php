<?php
/**
 * Tests for the semantic editorial site shell.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-helpers.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-helpers.php';
}

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-setup.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-setup.php';
}

/**
 * Exercises template output and legacy-page asset boundaries.
 */
final class EditorialShellTest extends TestCase {
	/**
	 * Resets deterministic request and template output fixtures.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_bloginfo']                  = array(
			'name'        => 'Mumega',
			'description' => 'Independent technology reporting.',
			'charset'     => 'UTF-8',
		);
		$GLOBALS['mumega_motion_test_has_nav_menu']              = array();
		$GLOBALS['mumega_motion_test_nav_menu_markup']           = '<ul class="menu"><li><a href="https://example.test/topic/">Topic</a></li></ul>';
		$GLOBALS['mumega_motion_test_categories']                = array();
		$GLOBALS['mumega_motion_test_loop_posts']                = array();
		$GLOBALS['mumega_motion_test_loop_index']                = 0;
		$GLOBALS['mumega_motion_test_current_post']              = null;
		$GLOBALS['mumega_motion_test_post_queries']              = array();
		$GLOBALS['mumega_motion_test_get_posts_requests']        = array();
		$GLOBALS['mumega_motion_test_enqueued_styles']           = array();
		$GLOBALS['mumega_motion_test_enqueued_scripts']          = array();
		$GLOBALS['mumega_motion_test_filters']                   = array();
		$GLOBALS['mumega_motion_test_conditionals']              = array();
		$GLOBALS['mumega_motion_test_page_template']             = '';
		$GLOBALS['mumega_motion_test_elementor_edit_mode']       = '';
		$GLOBALS['mumega_motion_test_queried_object_id']         = 0;
		$GLOBALS['mumega_motion_test_posts']                     = array();
		$GLOBALS['mumega_motion_test_elementor_locations']       = array();
		$GLOBALS['mumega_motion_test_elementor_location_output'] = array();
		$GLOBALS['mumega_motion_test_elementor_shell_calls']     = array();
	}

	/**
	 * Keeps the document shell while replacing only native header markup.
	 */
	public function test_matching_elementor_header_preserves_document_shell(): void {
		$GLOBALS['mumega_motion_test_elementor_locations']['header'] = true;

		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringContainsString( '<!doctype html>', $output );
		$this->assertStringContainsString( '<!-- wp_head -->', $output );
		$this->assertStringContainsString( '<body', $output );
		$this->assertStringContainsString( '<!-- wp_body_open -->', $output );
		$this->assertStringContainsString( '<!-- elementor_header -->', $output );
		$this->assertStringNotContainsString( '<header class="site-header">', $output );
	}

	/**
	 * Renders the native header when Elementor has no matching condition.
	 */
	public function test_unmatched_elementor_header_uses_native_markup(): void {
		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringNotContainsString( '<!-- elementor_header -->', $output );
		$this->assertStringContainsString( '<header class="site-header">', $output );
	}

	/**
	 * Falls back when Elementor reports a match but an exclusion emits no header.
	 */
	public function test_empty_elementor_header_output_uses_native_markup(): void {
		$GLOBALS['mumega_motion_test_elementor_locations']['header']       = true;
		$GLOBALS['mumega_motion_test_elementor_location_output']['header'] = '';

		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringContainsString( '<header class="site-header">', $output );
	}

	/**
	 * Keeps the configurable site identity outside the document heading outline.
	 */
	public function test_header_site_title_is_not_an_h1(): void {
		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringContainsString( '>Mumega</a>', $output );
		$this->assertSame( 0, preg_match( '/<h1\b[^>]*>.*Mumega.*<\/h1>/is', $output ) );
	}

	/**
	 * Places the accessibility entry point first inside body and preserves hooks.
	 */
	public function test_header_starts_with_a_skip_link_and_calls_body_hooks(): void {
		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringContainsString( '<!-- wp_head -->', $output );
		$this->assertMatchesRegularExpression(
			'/<body[^>]*>\s*<!-- wp_body_open -->\s*<a[^>]+href="#primary"[^>]*>Skip to content<\/a>/s',
			$output
		);
	}

	/**
	 * Avoids announcing blank navigation regions when menus and terms are absent.
	 */
	public function test_header_omits_empty_navigation_landmarks(): void {
		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringNotContainsString( '<nav', $output );

		$GLOBALS['mumega_motion_test_has_nav_menu']['primary'] = true;
		$GLOBALS['mumega_motion_test_nav_menu_markup']         = '';
		$output = $this->render_theme_file( 'header.php' );

		$this->assertStringNotContainsString( '<nav', $output );
	}

	/**
	 * Gives every generic request template one consistent content landmark.
	 *
	 * @dataProvider primary_template_provider
	 *
	 * @param string $template Template filename.
	 */
	public function test_page_templates_use_one_main_primary( $template ): void {
		$output = $this->render_theme_file( $template );

		$this->assertSame( 1, substr_count( $output, '<main id="primary"' ), $template );
		$this->assertSame( 1, substr_count( $output, '</main>' ), $template );
	}

	/**
	 * Preserves Elementor-authored full-width pages and excludes editorial assets.
	 *
	 * @dataProvider legacy_page_mode_provider
	 *
	 * @param string $page_template       Exact WordPress page-template slug.
	 * @param string $elementor_edit_mode Exact Elementor authoring mode.
	 */
	public function test_legacy_page_modes_render_the_content_without_editorial_assets( $page_template, $elementor_edit_mode ): void {
		$post                                        = new WP_Post(
			array(
				'ID'           => 71,
				'post_content' => '<section id="legacy-elementor-content" data-motion="fade-in">Legacy builder content</section>',
				'post_title'   => 'Legacy page',
			)
		);
		$GLOBALS['mumega_motion_test_page_template'] = $page_template;
		$GLOBALS['mumega_motion_test_elementor_edit_mode'] = $elementor_edit_mode;
		$GLOBALS['mumega_motion_test_loop_posts']          = array( $post );
		$GLOBALS['mumega_motion_test_queried_object_id']   = 71;
		$GLOBALS['mumega_motion_test_posts'][71]           = $post;
		$motion_filter_called                              = false;
		add_filter(
			'mumega_motion_enqueue_motion',
			static function () use ( &$motion_filter_called ) {
				$motion_filter_called = true;

				return true;
			}
		);

		$output = $this->render_theme_file( 'page.php' );
		mumega_motion_enqueue_editorial_styles();
		mumega_motion_enqueue_motion_assets();

		$this->assertSame( 'builder', get_post_meta( 71, '_elementor_edit_mode', true ) );
		$this->assertStringContainsString( 'legacy-elementor-content', $output );
		$this->assertStringContainsString( 'data-motion="fade-in"', $output );
		$this->assertArrayNotHasKey( 'mumega-motion-editorial', $GLOBALS['mumega_motion_test_enqueued_styles'] );
		$this->assertArrayNotHasKey( 'mumega-motion', $GLOBALS['mumega_motion_test_enqueued_scripts'] );
		$this->assertFalse( $motion_filter_called );
	}

	/**
	 * Preserves WordPress's final front-end hook.
	 */
	public function test_footer_calls_wp_footer(): void {
		$output = $this->render_theme_file( 'footer.php' );

		$this->assertStringContainsString( '<!-- wp_footer -->', $output );
		$this->assertLessThan( strpos( $output, '</body>' ), strpos( $output, '<!-- wp_footer -->' ) );
	}

	/**
	 * Keeps final document hooks while replacing only native footer markup.
	 */
	public function test_matching_elementor_footer_preserves_document_close(): void {
		$GLOBALS['mumega_motion_test_elementor_locations']['footer'] = true;

		$output = $this->render_theme_file( 'footer.php' );

		$this->assertStringContainsString( '<!-- elementor_footer -->', $output );
		$this->assertStringNotContainsString( '<footer class="site-footer">', $output );
		$this->assertStringContainsString( '<!-- wp_footer -->', $output );
		$this->assertStringContainsString( '</body>', $output );
		$this->assertStringContainsString( '</html>', $output );
	}

	/**
	 * Renders the native footer when Elementor has no matching condition.
	 */
	public function test_unmatched_elementor_footer_uses_native_markup(): void {
		$output = $this->render_theme_file( 'footer.php' );

		$this->assertStringNotContainsString( '<!-- elementor_footer -->', $output );
		$this->assertStringContainsString( '<footer class="site-footer">', $output );
	}

	/**
	 * Falls back when Elementor reports a match but an exclusion emits no footer.
	 */
	public function test_empty_elementor_footer_output_uses_native_markup(): void {
		$GLOBALS['mumega_motion_test_elementor_locations']['footer']       = true;
		$GLOBALS['mumega_motion_test_elementor_location_output']['footer'] = '';

		$output = $this->render_theme_file( 'footer.php' );

		$this->assertStringContainsString( '<footer class="site-footer">', $output );
	}

	/**
	 * Provides every generic template expected to own the primary landmark.
	 *
	 * @return array
	 */
	public function primary_template_provider(): array {
		return array(
			'page'        => array( 'page.php' ),
			'posts index' => array( 'index.php' ),
			'not found'   => array( '404.php' ),
		);
	}

	/**
	 * Provides the exact live legacy authoring pair recorded during inspection.
	 *
	 * @return array
	 */
	public function legacy_page_mode_provider(): array {
		return array(
			'Elementor Full Width / Elementor-authored' => array( 'elementor_header_footer', 'builder' ),
		);
	}

	/**
	 * Captures a theme file without turning missing production files into fatals.
	 *
	 * @param string $filename Theme-relative filename.
	 * @return string
	 */
	private function render_theme_file( $filename ): string {
		$path = dirname( __DIR__ ) . '/' . $filename;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		require $path;

		return (string) ob_get_clean();
	}
}
