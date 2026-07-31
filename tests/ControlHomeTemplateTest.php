<?php
/**
 * Tests for the native MCPWP control-plane homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-setup.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-setup.php';
}

/**
 * Exercises the control-plane homepage template boundary.
 */
final class ControlHomeTemplateTest extends TestCase {
	/**
	 * Catches a missing template registration or a template that bypasses the native shell/content loop.
	 */
	public function test_control_home_is_a_named_native_page_template(): void {
		$path = dirname( __DIR__ ) . '/page-templates/control-home.php';

		$this->assertFileExists( $path );

		if ( ! is_file( $path ) ) {
			return;
		}

		$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect a local theme template.

		$this->assertIsString( $source );
		$this->assertStringContainsString( 'Template Name: MCPWP Control Plane Home', $source );
		$this->assertStringContainsString( 'mumega_motion_get_header();', $source );
		$this->assertStringContainsString( 'mumega_motion_get_footer();', $source );
		$this->assertStringContainsString( "post_class( 'mcpwp-control-home-entry' )", $source );
		$this->assertStringContainsString( 'while ( have_posts() )', $source );
		$this->assertStringContainsString( 'the_post();', $source );
		$this->assertStringContainsString( 'mumega_motion_the_control_home_content();', $source );
		$this->assertStringNotContainsString( '<?php the_content(); ?>', $source );
	}

	/**
	 * Preserves source-controlled layout HTML while retaining every other content filter.
	 */
	public function test_control_home_content_suspends_only_wpautop_and_restores_it(): void {
		$original_filters = isset( $GLOBALS['mumega_motion_test_filters']['the_content'] )
			? $GLOBALS['mumega_motion_test_filters']['the_content']
			: null;
		$original_post      = $GLOBALS['mumega_motion_test_current_post'];
		$had_wp_filter      = array_key_exists( 'wp_filter', $GLOBALS );
		$original_wp_filter = $had_wp_filter ? $GLOBALS['wp_filter'] : null;

		try {
			$GLOBALS['mumega_motion_test_filters']['the_content'] = array();
			$GLOBALS['mumega_motion_test_current_post']           = new WP_Post(
				array(
					'ID'           => 25,
					'post_content' => '<div data-layout="control-home">Source HTML</div>',
				)
			);

			add_filter(
				'the_content',
				static function ( $content ) {
					return 'before(' . $content . ')';
				},
				10
			);
			add_filter( 'the_content', 'wpautop', 10 );
			add_filter(
				'the_content',
				static function ( $content ) {
					return $content . ':after';
				},
				10
			);
			add_filter(
				'the_content',
				static function ( $content ) {
					return $content . '<!-- retained-filter -->';
				},
				20
			);

			$test_hook            = new stdClass();
			$test_hook->callbacks =& $GLOBALS['mumega_motion_test_filters']['the_content'];
			$GLOBALS['wp_filter']  = array( 'the_content' => $test_hook );

			ob_start();
			mumega_motion_the_control_home_content();
			$output = ob_get_clean();

			$this->assertSame(
				'before(<div data-layout="control-home">Source HTML</div>):after<!-- retained-filter -->',
				$output
			);
			$this->assertSame( 10, has_filter( 'the_content', 'wpautop' ) );
			$this->assertSame(
				'<p class="test-wpautop">before(Second render)</p>:after<!-- retained-filter -->',
				apply_filters( 'the_content', 'Second render' )
			);
		} finally {
			$GLOBALS['mumega_motion_test_current_post'] = $original_post;

			if ( $had_wp_filter ) {
				$GLOBALS['wp_filter'] = $original_wp_filter;
			} else {
				unset( $GLOBALS['wp_filter'] );
			}

			if ( null === $original_filters ) {
				unset( $GLOBALS['mumega_motion_test_filters']['the_content'] );
			} else {
				$GLOBALS['mumega_motion_test_filters']['the_content'] = $original_filters;
			}
		}
	}
}
