<?php
/**
 * Tests for the native product homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the product homepage template contract.
 */
final class ProductHomeTemplateTest extends TestCase {
	/**
	 * Renders the product homepage through the native WordPress page loop.
	 */
	public function test_product_home_is_a_named_native_page_template(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/page-templates/product-home.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect local theme templates.

		$this->assertIsString( $source );
		$this->assertStringContainsString( 'Template Name: Product Home', $source );
		$this->assertStringContainsString( 'mumega_motion_get_header();', $source );
		$this->assertStringContainsString( 'mumega_motion_get_footer();', $source );
		$this->assertStringContainsString( 'the_content();', $source );
	}
}
