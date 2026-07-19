<?php
/**
 * Tests for the optional Figma token style output.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/figma-tokens.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/figma-tokens.php';
}

/**
 * Exercises the bounded inline-style output contract.
 */
final class FigmaTokensTest extends TestCase {
	/**
	 * Resets token and escaping observations before each assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_options']       = array();
		$GLOBALS['mumega_motion_test_wp_kses_calls'] = array();
	}

	/**
	 * Preserves validated CSS while allowing only the intended style element.
	 */
	public function test_output_uses_an_explicit_html_allowlist_without_encoding_valid_css(): void {
		$GLOBALS['mumega_motion_test_options']['mcpwp_figma_design_tokens'] = array(
			'colors'     => array(
				'accent' => '#abcdef',
			),
			'typography' => array(
				'body' => array(
					'fontFamily' => 'Source Sans 3',
				),
			),
		);

		ob_start();
		mumega_motion_output_figma_tokens_style();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<style id="mumega-figma-tokens">', $output );
		$this->assertStringContainsString( '--figma-color-accent: #abcdef;', $output );
		$this->assertStringContainsString( '--figma-typography-body-font-family: "Source Sans 3";', $output );
		$this->assertStringNotContainsString( '&quot;', $output );
		$this->assertSame(
			array(
				'style' => array(
					'id' => array(),
				),
			),
			$GLOBALS['mumega_motion_test_wp_kses_calls'][0][1]
		);
	}
}
