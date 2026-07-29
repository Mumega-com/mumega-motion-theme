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

	/**
	 * Keeps guide links usable as minimum-size touch targets when their label fits on one line.
	 */
	public function test_product_home_guide_links_keep_a_44px_minimum_target(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );
		$this->assertMatchesRegularExpression(
			'/\.mcpwp-product-home__guides a,\s*\.mcpwp-product-home \.mcpwp-product-guides a\s*\{[^}]*min-height:\s*2\.75rem;/s',
			$css
		);
	}

	/**
	 * Keeps the workflow's status accents when BEM cards are used without concise aliases.
	 */
	public function test_product_home_bem_workflow_cards_receive_each_status_accent(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );

		foreach ( array( 2 => '--mcpwp-cobalt', 3 => '--mcpwp-amber', 4 => '--mcpwp-teal' ) as $step => $accent ) {
			$this->assertMatchesRegularExpression(
				'/\.mcpwp-product-home__workflow \.mcpwp-product-home__card:nth-child\(' . $step . '\),\s*\.mcpwp-product-home \.mcpwp-product-workflow \.mcpwp-product-home__card:nth-child\(' . $step . '\)[^{]*\{[^}]*border-top:\s*4px solid var\(' . preg_quote( $accent, '/' ) . '\);/s',
				$css
			);
		}
	}
}
