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
		$this->assertSame( '#cbc4b8', $this->palette_color( $theme, 'rule' ) );
		$this->assertSame( '#ffffff', $this->palette_color( $theme, 'white' ) );
		$this->assertTrue( $theme['settings']['typography']['fluid'] );
		$display = $this->font_size( $theme, 'display' );
		$this->assertSame( '2.5rem', $display['fluid']['min'] );
		$this->assertSame( '5.5rem', $display['fluid']['max'] );
	}

	/**
	 * Adds the four v2 semantic accents without disturbing the shipped palette.
	 */
	public function test_theme_json_exposes_the_four_new_v2_semantic_tokens(): void {
		$theme = json_decode( $this->source( 'theme.json' ), true );

		$this->assertSame( '#1b2a4a', $this->palette_color( $theme, 'navy' ) );
		$this->assertSame( '#0f6a63', $this->palette_color( $theme, 'teal' ) );
		$this->assertSame( '#2454a6', $this->palette_color( $theme, 'cobalt' ) );
		$this->assertSame( '#8a5a12', $this->palette_color( $theme, 'amber' ) );
	}

	/**
	 * Maps every new semantic token to a CSS custom property with a literal fallback,
	 * matching the established --editorial-* convention.
	 */
	public function test_editorial_css_declares_custom_properties_for_the_four_new_tokens(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$this->assertMatchesRegularExpression( '/--editorial-navy:\s*var\(--wp--preset--color--navy,\s*#1b2a4a\)/', $css );
		$this->assertMatchesRegularExpression( '/--editorial-teal:\s*var\(--wp--preset--color--teal,\s*#0f6a63\)/', $css );
		$this->assertMatchesRegularExpression( '/--editorial-cobalt:\s*var\(--wp--preset--color--cobalt,\s*#2454a6\)/', $css );
		$this->assertMatchesRegularExpression( '/--editorial-amber:\s*var\(--wp--preset--color--amber,\s*#8a5a12\)/', $css );
	}

	/**
	 * Gives the hero an exact 7/5 column split at the 800px desktop breakpoint,
	 * while every later module spans the full 12-column row.
	 */
	public function test_homepage_hero_uses_a_twelve_column_seven_five_split_at_800px(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$breakpoint = $this->media_block( $css, '(min-width: 800px)' );

		$this->assertNotSame( '', $breakpoint, 'The 800px breakpoint must exist.' );
		$this->assertMatchesRegularExpression(
			'/\.editorial-home\s*>\s*\.home-intro\s*\{[^}]*grid-column:\s*span 7/s',
			$breakpoint
		);
		$this->assertMatchesRegularExpression(
			'/\.editorial-home\s*>\s*\.home-briefing\s*\{[^}]*grid-column:\s*span 5/s',
			$breakpoint
		);
		$this->assertMatchesRegularExpression(
			'/\.editorial-home\s*>\s*:not\([^{]*\.home-intro\)[^{]*:not\([^{]*\.home-briefing\)[^{]*\{[^}]*grid-column:\s*1\s*\/\s*-1/s',
			$breakpoint
		);
	}

	/**
	 * Leaves the hero in plain source-order block flow below 800px: no explicit
	 * grid-column span for .home-intro/.home-briefing exists outside that breakpoint.
	 */
	public function test_homepage_hero_has_no_column_span_below_800px(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$outside_breakpoints = $this->strip_media_block( $css, '(min-width: 800px)' );

		$this->assertDoesNotMatchRegularExpression( '/\.home-intro\s*\{[^}]*grid-column/s', $outside_breakpoints );
		$this->assertDoesNotMatchRegularExpression( '/\.home-briefing\s*\{[^}]*grid-column/s', $outside_breakpoints );
	}

	/**
	 * Builds the audience, coverage, guide, and tool card layouts with CSS Grid,
	 * never flexbox, per the approved layout system.
	 */
	public function test_new_homepage_card_layouts_use_css_grid_not_flexbox(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		foreach ( array( '.home-audiences__list', '.home-coverage__grid', '.home-coverage__support', '.home-guides__list', '.home-tools__grid' ) as $selector ) {
			$block = $this->rule_block( $css, $selector );

			$this->assertNotSame( '', $block, $selector . ' must have a rule block.' );
			$this->assertStringContainsString( 'display: grid', $block, $selector . ' must use CSS Grid.' );
			$this->assertStringNotContainsString( 'display: flex', $block, $selector . ' must not use flexbox.' );
		}
	}

	/**
	 * Guarantees a real 44px effective hit target on every new interactive link
	 * introduced for Task 8, at minimum the audience pathway cards.
	 */
	public function test_new_interactive_controls_meet_the_44px_touch_target(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		foreach ( array( '.home-audiences__link', '.home-briefing__guide-link', '.home-briefing__related a', '.home-guides__group-category a' ) as $selector ) {
			$block = $this->rule_block( $css, $selector );

			$this->assertNotSame( '', $block, $selector . ' must have a rule block.' );
			$this->assertStringContainsString( 'min-height: 44px', $block, $selector . ' must declare a 44px minimum hit target.' );
		}
	}

	/**
	 * Keeps the reduced-motion guard covering any future [data-motion] mounts;
	 * Task 8 must not remove or weaken it even though it added no new motion CSS.
	 */
	public function test_reduced_motion_guard_still_covers_data_motion_mounts(): void {
		$css = $this->source( 'assets/css/editorial.css' );

		$this->assertMatchesRegularExpression(
			'/@media \(prefers-reduced-motion: reduce\)\s*\{.*\[data-motion\]/s',
			$css
		);
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

	/**
	 * Extracts the literal content of one top-level @media block by its prelude.
	 *
	 * @param string $css     Full stylesheet source.
	 * @param string $prelude Media query text, e.g. "(min-width: 800px)".
	 * @return string
	 */
	private function media_block( string $css, string $prelude ): string {
		$open = $this->media_open_brace( $css, $prelude );

		if ( false === $open ) {
			return '';
		}

		$close = $this->matching_brace( $css, $open );

		if ( false === $close ) {
			return '';
		}

		return substr( $css, $open + 1, $close - $open - 1 );
	}

	/**
	 * Removes one top-level @media block (prelude through matching close brace)
	 * so its selectors can be proven absent from the rest of the stylesheet.
	 *
	 * @param string $css     Full stylesheet source.
	 * @param string $prelude Media query text, e.g. "(min-width: 800px)".
	 * @return string
	 */
	private function strip_media_block( string $css, string $prelude ): string {
		$needle = '@media ' . $prelude;
		$start  = strpos( $css, $needle );
		$open   = $this->media_open_brace( $css, $prelude );

		if ( false === $start || false === $open ) {
			return $css;
		}

		$close = $this->matching_brace( $css, $open );

		if ( false === $close ) {
			return $css;
		}

		return substr( $css, 0, $start ) . substr( $css, $close + 1 );
	}

	/**
	 * Extracts one rule block's declaration body by its selector text.
	 *
	 * @param string $css      Full stylesheet source.
	 * @param string $selector Literal selector text, e.g. ".home-tools__grid".
	 * @return string
	 */
	private function rule_block( string $css, string $selector ): string {
		if ( ! preg_match( '/' . preg_quote( $selector, '/' ) . '\s*\{/', $css, $matches, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$open  = strpos( $css, '{', $matches[0][1] );
		$close = $this->matching_brace( $css, $open );

		if ( false === $close ) {
			return '';
		}

		return substr( $css, $open + 1, $close - $open - 1 );
	}

	/**
	 * Finds the opening brace index of one top-level @media block by its prelude.
	 *
	 * @param string $css     Full stylesheet source.
	 * @param string $prelude Media query text.
	 * @return int|false
	 */
	private function media_open_brace( string $css, string $prelude ) {
		$start = strpos( $css, '@media ' . $prelude );

		if ( false === $start ) {
			return false;
		}

		return strpos( $css, '{', $start );
	}

	/**
	 * Finds the index of the closing brace matching the opening brace at $open.
	 *
	 * @param string $css  Full stylesheet source.
	 * @param int    $open Index of the opening brace.
	 * @return int|false
	 */
	private function matching_brace( string $css, int $open ) {
		$depth = 0;
		$len   = strlen( $css );

		for ( $i = $open; $i < $len; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return false;
	}
}
