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
	 * Keeps the approved publication palette and fluid type available to blocks.
	 */
	public function test_theme_json_defines_editorial_defaults_and_bounded_fluid_type(): void {
		$theme = json_decode( $this->source( 'theme.json' ), true );

		$this->assertSame( '#f7f3ea', $this->palette_color( $theme, 'paper' ) );
		$this->assertSame( '#171717', $this->palette_color( $theme, 'ink' ) );
		$this->assertSame( '#5f5b55', $this->palette_color( $theme, 'muted-ink' ) );
		$this->assertSame( '#d8cdf7', $this->palette_color( $theme, 'lavender' ) );
		$this->assertSame( '#6545a4', $this->palette_color( $theme, 'lavender-ink' ) );
		$this->assertTrue( $theme['settings']['typography']['fluid'] );
		$this->assertSame( 'var:preset|color|paper', $theme['styles']['color']['background'] );
		$this->assertSame( 'var:preset|color|ink', $theme['styles']['color']['text'] );
		$this->assertSame( 'var:preset|font-family|editorial-sans', $theme['styles']['typography']['fontFamily'] );

		$display = $this->font_size( $theme, 'display' );
		$this->assertSame( '2.5rem', $display['fluid']['min'] );
		$this->assertSame( '5.5rem', $display['fluid']['max'] );
	}

	/**
	 * Holds the desktop lead desk to the approved 7/5 composition at 800px.
	 */
	public function test_editorial_css_uses_a_seven_five_lead_desk_from_800_pixels(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$this->assertMatchesRegularExpression(
			'/@media\s*\(min-width:\s*50rem\).*?\.editorial-home\s*\{[^}]*grid-template-columns:\s*repeat\(12,\s*minmax\(0,\s*1fr\)\)[^}]*\}.*?\.editorial-home\s*>\s*\.lead-story\s*\{[^}]*grid-column:\s*span\s+7[^}]*\}.*?\.editorial-home\s*>\s*\.home-supporting\s*\{[^}]*grid-column:\s*span\s+5/s',
			$css
		);
	}

	/**
	 * Retains a high-contrast two-layer focus indicator across light and dark areas.
	 */
	public function test_editorial_css_has_a_two_layer_focus_indicator_and_accessible_controls(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$this->assertMatchesRegularExpression( '/:focus-visible\s*\{[^}]*outline:\s*3px\s+solid\s+var\(--editorial-paper\)[^}]*box-shadow:\s*0\s+0\s+0\s+6px\s+var\(--editorial-accent-ink\)/s', $css );
		$this->assertMatchesRegularExpression( '/button,.*?input\[type="submit"\].*?summary\s*\{[^}]*min-height:\s*2\.75rem/s', $css );
		$this->assertMatchesRegularExpression( '/\.content-card__category,.*?\.article-entities__link\s*\{[^}]*min-height:\s*1\.5rem/s', $css );
	}

	/**
	 * Prevents long editorial strings and animations from defeating narrow layouts.
	 */
	public function test_editorial_css_handles_narrow_content_and_reduced_motion(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$this->assertMatchesRegularExpression( '/\.lead-story__title,.*?\.archive-title\s*\{[^}]*overflow-wrap:\s*anywhere/s', $css );
		$this->assertMatchesRegularExpression( '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{[^}]*html\s*\{[^}]*scroll-behavior:\s*auto/s', $css );
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
