<?php
/**
 * Tests for the native MCPWP control-plane homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

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
		$this->assertStringContainsString( 'the_content();', $source );
	}
}
