<?php
/**
 * Tests for the MCPWP control-home visual system.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Protects the page-scoped, responsive, and accessible presentation contract.
 */
final class ControlHomeVisualSystemTest extends TestCase {
	/**
	 * Catches a missing page namespace, route state, mobile transform, or motion preference boundary.
	 */
	public function test_stylesheet_exposes_the_control_plane_and_accessibility_states(): void {
		$css = $this->stylesheet();

		foreach (
			array(
				'.mcpwp-control-home',
				'.mcpwp-control-plane',
				'.mcpwp-control-home__trace',
				'.mcpwp-control-home__flagship',
				'.mcpwp-control-home__mesh',
				':focus-visible',
				'@media (max-width: 47.9375rem)',
				'@media (prefers-reduced-motion: reduce)',
			) as $required_hook
		) {
			$this->assertStringContainsString( $required_hook, $css );
		}
	}

	/**
	 * Catches a selected route that fails to move its matching server-rendered signal into the active state.
	 */
	public function test_each_intent_has_a_data_driven_selected_signal(): void {
		$css = $this->stylesheet();

		foreach ( array( 'operate', 'scale', 'understand' ) as $intent ) {
			$this->assertStringContainsString(
				'.mcpwp-control-plane[data-selected-intent="' . $intent . '"] .mcpwp-control-plane__signal--' . $intent,
				$css
			);
			$this->assertStringContainsString(
				'.mcpwp-control-plane[data-selected-intent="' . $intent . '"] .mcpwp-control-plane__path--' . $intent,
				$css
			);
		}
	}

	/**
	 * Catches a generic theme-header rule that would hide navigation across unrelated templates.
	 */
	public function test_native_header_suppression_is_scoped_to_the_control_home_template(): void {
		$css = $this->stylesheet();

		$this->assertMatchesRegularExpression(
			'/\.page-template-control-home\s+\.site-header\s*\{[^}]*display:\s*none;/s',
			$css
		);
		$this->assertDoesNotMatchRegularExpression(
			'/(?:^|})\s*\.site-header\s*\{[^}]*display:\s*none;/s',
			$css
		);
	}

	/**
	 * Catches controls that become smaller than the WCAG mobile target baseline.
	 */
	public function test_route_and_action_controls_keep_44px_minimum_targets(): void {
		$css = $this->stylesheet();

		foreach (
			array(
				'.mcpwp-control-plane__path',
				'.mcpwp-control-home__header-action',
				'.mcpwp-control-home__button',
			) as $selector
		) {
			$this->assertMatchesRegularExpression(
				'/' . preg_quote( $selector, '/' ) . '[^{]*\{[^}]*min-height:\s*2\.75rem;/s',
				$css,
				$selector
			);
		}
	}

	/**
	 * Catches a narrow layout that retains the radial desktop graph or horizontal trace.
	 */
	public function test_mobile_breakpoint_converts_the_graph_and_trace_to_vertical_flow(): void {
		$css = $this->stylesheet();

		$this->assertMatchesRegularExpression(
			'/@media \(max-width:\s*47\.9375rem\)[^{]*\{.*?\.mcpwp-control-plane__signals\s*\{[^}]*display:\s*none;.*?\.mcpwp-control-plane__paths\s*\{[^}]*grid-template-columns:\s*1fr;.*?\.mcpwp-control-home__trace\s*\{[^}]*grid-template-columns:\s*1fr;/s',
			$css
		);
		$this->assertStringContainsString( 'min-width: 0;', $css );
		$this->assertStringContainsString( 'overflow-wrap: anywhere;', $css );
	}

	/**
	 * Catches React enhancement wrappers becoming layout boxes that break the server-designed graph.
	 */
	public function test_progressive_enhancement_wrappers_preserve_server_layout(): void {
		$css = $this->stylesheet();

		$this->assertMatchesRegularExpression(
			'/\.mcpwp-control-plane__enhancement,\s*\.mcpwp-control-plane__static\s*\{[^}]*display:\s*contents;/s',
			$css
		);
	}

	/**
	 * Catches a palette change that makes the core reading surfaces fail WCAG AA.
	 */
	public function test_primary_palette_pairs_meet_wcag_aa(): void {
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#16223b', '#f4f0e7' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#ffffff', '#16223b' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#4e348f', '#ffffff' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#075f5b', '#ffffff' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->contrast_ratio( '#174d96', '#ffffff' ) );
	}

	/**
	 * Catches generic link colors outranking the contrasting header and button colors.
	 */
	public function test_generic_link_colors_use_zero_specificity_inner_selectors(): void {
		$css = $this->stylesheet();

		$this->assertStringContainsString( '.mcpwp-control-home :where(a) {', $css );
		$this->assertStringContainsString( '.mcpwp-control-home :where(a:hover) {', $css );
		$this->assertStringNotContainsString( '.mcpwp-control-home a {', $css );
		$this->assertStringNotContainsString( '.mcpwp-control-home a:hover {', $css );
	}

	/**
	 * Reads the scoped stylesheet.
	 */
	private function stylesheet(): string {
		$path = dirname( __DIR__ ) . '/assets/css/control-home.css';

		$this->assertFileExists( $path );

		if ( ! is_file( $path ) ) {
			return '';
		}

		$css = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect a local theme asset.
		$this->assertIsString( $css );

		return (string) $css;
	}

	/**
	 * Calculates WCAG contrast for two six-digit hexadecimal colors.
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
