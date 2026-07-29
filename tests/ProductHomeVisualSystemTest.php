<?php
/**
 * Tests for the product-home visual system asset.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Checks the public visual-system guarantees for the product homepage.
 */
final class ProductHomeVisualSystemTest extends TestCase {
	/**
	 * Provides the responsive, accessible product-home presentation hooks.
	 */
	public function test_product_home_stylesheet_provides_responsive_accessible_hooks(): void {
		$stylesheet = dirname( __DIR__ ) . '/assets/css/product-home.css';

		$this->assertFileExists( $stylesheet );

		$css = file_get_contents( $stylesheet ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );

		foreach (
			array(
				'.mcpwp-product-home',
				'.mcpwp-product-hero',
				'@media (max-width: 48rem)',
				'@media (prefers-reduced-motion: reduce)',
				':focus-visible',
			) as $required_hook
		) {
			$this->assertStringContainsString( $required_hook, $css );
		}
	}
}
