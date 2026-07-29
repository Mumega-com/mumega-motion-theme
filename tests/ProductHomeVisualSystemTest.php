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
				'@media (max-width: 47.9375rem)',
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
	 * Hides the generic shell header without hiding headers on ordinary pages.
	 */
	public function test_generic_header_hide_rule_is_scoped_to_the_product_home_template(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );
		$this->assertMatchesRegularExpression(
			'/\.page-template-product-home\s+\.site-header\s*\{[^}]*display:\s*none;/s',
			$css
		);
		$this->assertDoesNotMatchRegularExpression(
			'/(?:^|})\s*\.site-header\s*\{[^}]*display:\s*none;/s',
			$css
		);
	}

	/**
	 * Keeps the paid Agency link readable against the navy edition card.
	 */
	public function test_agency_secondary_button_uses_high_contrast_text_on_navy(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );
		$this->assertMatchesRegularExpression(
			'/\.mcpwp-product-home__comparison\s*>\s*:last-child\s+\.mcpwp-product-button--secondary[^{]*\{[^}]*color:\s*var\(--mcpwp-white\);/s',
			$css
		);
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#ffffff', '#17233d' ) );
	}

	/**
	 * Keeps tablet supporting grids at two columns at exactly 768 CSS pixels.
	 */
	public function test_exact_768_layout_keeps_supporting_grids_at_two_columns(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );
		$this->assertStringContainsString( '@media (max-width: 47.9375rem)', $css );
		$this->assertStringNotContainsString( '@media (max-width: 48rem)', $css );
		$this->assertMatchesRegularExpression(
			'/@media \(max-width:\s*64rem\)[^{]*\{.*?\.mcpwp-product-home__guides,[^{]*\{[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\);/s',
			$css
		);
	}

	/**
	 * Keeps the workflow's status accents when BEM cards are used without concise aliases.
	 */
	public function test_product_home_bem_workflow_cards_receive_each_status_accent(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-home.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme asset.

		$this->assertIsString( $css );

		foreach (
			array(
				2 => '--mcpwp-cobalt',
				3 => '--mcpwp-amber',
				4 => '--mcpwp-teal',
			) as $step => $accent
		) {
			$this->assertMatchesRegularExpression(
				'/\.mcpwp-product-home__workflow \.mcpwp-product-home__card:nth-child\(' . $step . '\),\s*\.mcpwp-product-home \.mcpwp-product-workflow \.mcpwp-product-home__card:nth-child\(' . $step . '\)[^{]*\{[^}]*border-top:\s*4px solid var\(' . preg_quote( $accent, '/' ) . '\);/s',
				$css
			);
		}
	}

	/**
	 * Calculates WCAG contrast for two six-digit hexadecimal colors.
	 *
	 * @param string $foreground Foreground color.
	 * @param string $background Background color.
	 * @return float
	 */
	private function contrast_ratio( string $foreground, string $background ): float {
		$luminance = static function ( string $color ): float {
			$channels = array_map(
				static function ( string $pair ): float {
					$value = hexdec( $pair ) / 255;

					return $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
				},
				str_split( ltrim( $color, '#' ), 2 )
			);

			return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
		};

		$lighter = max( $luminance( $foreground ), $luminance( $background ) );
		$darker  = min( $luminance( $foreground ), $luminance( $background ) );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}
}
