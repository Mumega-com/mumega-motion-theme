<?php
/**
 * Tests for the bounded editorial island markup emitter.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-islands.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-islands.php';
}

/**
 * Exercises the allowlist and inert serialization boundary.
 */
final class EditorialIslandsTest extends TestCase {
	/**
	 * Emits only allowlisted names with escaped JSON data and text fallback.
	 */
	public function test_emits_allowlisted_island_with_json_data_and_text_fallback(): void {
		$output = mumega_motion_render_editorial_island(
			'fade-in',
			array(
				'delay' => 0.2,
				'label' => 'A "quoted" value',
			),
			'<strong>Visible fallback</strong>'
		);

		$this->assertStringContainsString( 'data-motion-island="fade-in"', $output );
		$this->assertStringContainsString( 'data-motion-data="{&quot;delay&quot;:0.2,&quot;label&quot;:&quot;A \\&quot;quoted\\&quot; value&quot;}"', $output );
		$this->assertStringContainsString( '&lt;strong&gt;Visible fallback&lt;/strong&gt;', $output );
		$this->assertStringNotContainsString( '<strong>', $output );
	}

	/**
	 * Supports the explicit future StreamingText name without executable input.
	 */
	public function test_emits_allowlisted_streaming_text_name(): void {
		$output = mumega_motion_render_editorial_island(
			'streaming-text',
			array( 'streamUrl' => '/stream' ),
			'Loading stream…'
		);

		$this->assertStringContainsString( 'data-motion-island="streaming-text"', $output );
		$this->assertStringContainsString( 'data-motion-data="{&quot;streamUrl&quot;:&quot;\/stream&quot;}"', $output );
	}

	/**
	 * Rejects unknown names and non-array data.
	 */
	public function test_rejects_unknown_names_and_non_array_data(): void {
		$this->assertSame( '', mumega_motion_render_editorial_island( 'arbitrary-component', array(), 'Fallback' ) );
		$this->assertSame( '', mumega_motion_render_editorial_island( 'fade-in', 'alert(1)', 'Fallback' ) );
		$this->assertSame( '', mumega_motion_render_editorial_island( 'fade-in', array(), static function () {} ) );
	}

	/**
	 * Rejects nested values that are executable or cannot be safely serialized.
	 *
	 * @dataProvider unsafe_data_provider
	 *
	 * @param mixed $value Unsafe nested value.
	 */
	public function test_rejects_non_json_safe_nested_values( $value ): void {
		$this->assertSame(
			'',
			mumega_motion_render_editorial_island( 'fade-in', array( 'unsafe' => $value ), 'Fallback' )
		);
	}

	/**
	 * Provides unsafe values without executing them.
	 *
	 * @return array
	 */
	public function unsafe_data_provider(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Test-only resource fixture for rejection behavior.
		$resource = fopen( 'php://memory', 'r' );

		return array(
			'object'       => array( new stdClass() ),
			'closure'      => array( static function () {} ),
			'resource'     => array( $resource ),
			'callable'     => array( 'strlen' ),
			'not a number' => array( NAN ),
			'infinity'     => array( INF ),
		);
	}
}
