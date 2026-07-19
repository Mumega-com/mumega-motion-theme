<?php
/**
 * Tests for the bounded editorial visual and accessibility system.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Protects the CSS and theme.json contracts verified in rendered QA.
 */
final class EditorialVisualSystemTest extends TestCase {
	/**
	 * Keeps safe editorial tokens available without styling legacy Elementor pages.
	 */
	public function test_theme_json_exposes_tokens_without_global_editorial_styles(): void {
		$theme = json_decode( $this->source( 'theme.json' ), true );

		$this->assertIsArray( $theme );
		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertSame( 'https://schemas.wp.org/wp/6.5/theme.json', $theme['$schema'] );
		$this->assertSame( 2, $theme['version'] );
		$this->assertArrayNotHasKey( 'styles', $theme, 'theme.json styles are global and bypass conditional editorial.css loading.' );
		$this->assertSame( '#f7f3ea', $this->palette_color( $theme, 'paper' ) );
		$this->assertSame( '#171717', $this->palette_color( $theme, 'ink' ) );
		$this->assertSame( '#5f5b55', $this->palette_color( $theme, 'muted-ink' ) );
		$this->assertSame( '#d8cdf7', $this->palette_color( $theme, 'lavender' ) );
		$this->assertSame( '#6545a4', $this->palette_color( $theme, 'lavender-ink' ) );
		$this->assertTrue( $theme['settings']['typography']['fluid'] );
		$display = $this->font_size( $theme, 'display' );
		$this->assertSame( '2.5rem', $display['fluid']['min'] );
		$this->assertSame( '5.5rem', $display['fluid']['max'] );
	}

	/**
	 * Leaves structural CSS QA to the PostCSS regression suite.
	 */
	public function test_editorial_css_and_print_css_are_parseable_contract_inputs(): void {
		$css       = $this->source( 'assets/css/editorial.css' );
		$print_css = $this->source( 'assets/css/print.css' );

		$this->assertNotSame( '', trim( $css ) );
		$this->assertNotSame( '', trim( $print_css ) );
		$this->assertSame( substr_count( $css, '{' ), substr_count( $css, '}' ) );
		$this->assertSame( substr_count( $print_css, '{' ), substr_count( $print_css, '}' ) );
	}

	/**
	 * Finds one palette entry by its stable WordPress slug.
	 *
	 * @param array  $theme Theme settings.
	 * @param string $slug  Palette slug.
	 * @return string
	 */
	private function palette_color( array $theme, $slug ): string {
		foreach ( $theme['settings']['color']['palette'] as $color ) {
			if ( $slug === $color['slug'] ) {
				return $color['color'];
			}
		}

		return '';
	}

	/**
	 * Finds one font-size preset by its stable WordPress slug.
	 *
	 * @param array  $theme Theme settings.
	 * @param string $slug  Font-size slug.
	 * @return array
	 */
	private function font_size( array $theme, $slug ): array {
		foreach ( $theme['settings']['typography']['fontSizes'] as $font_size ) {
			if ( $slug === $font_size['slug'] ) {
				return $font_size;
			}
		}

		return array();
	}

	/**
	 * Reads one theme source file.
	 *
	 * @param string $filename Theme-relative filename.
	 * @return string
	 */
	private function source( $filename ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local contract fixture.
		return (string) file_get_contents( dirname( __DIR__ ) . '/' . $filename );
	}
}
