<?php
/**
 * Tests for the source-controlled MCPWP product-home page content.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the importable MCPWP product-home content contract.
 */
final class McpwpProductHomeContentTest extends TestCase {
	/**
	 * Keeps the primary install path, ASTER media token, and supported clients intact.
	 */
	public function test_product_home_content_has_the_approved_conversion_contract(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, '<h1' ) );
		$this->assertStringContainsString( 'Install free from WordPress.org', $content );
		$this->assertStringContainsString( 'https://wordpress.org/plugins/mumega-mcp/', $content );
		$this->assertStringContainsString( '{{ASTER_URL}}', $content );
		$this->assertStringContainsString( 'Claude', $content );
		$this->assertStringContainsString( 'ChatGPT', $content );
		$this->assertStringContainsString( 'Gemini', $content );
		$this->assertStringContainsString( 'Codex', $content );
		$this->assertStringContainsString( 'Hermes', $content );
		$this->assertStringContainsString( 'OpenClaw', $content );
	}

	/**
	 * Keeps the document semantic, scoped, script-free, and ready for media replacement.
	 */
	public function test_product_home_content_uses_the_importable_semantic_structure(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, 'class="mcpwp-product-home"' ) );
		$this->assertStringContainsString( '<header class="mcpwp-product-home__header">', $content );
		$this->assertStringContainsString( '<section id="workflow"', $content );
		$this->assertStringContainsString( '<section id="first-connection"', $content );
		$this->assertStringContainsString( '<section id="agency"', $content );
		$this->assertStringContainsString( '<section id="guides"', $content );
		$this->assertStringContainsString( '<svg', $content );
		$this->assertStringNotContainsString( '<script', strtolower( $content ) );
	}

	/**
	 * Prevents legacy endpoints, fabricated social proof, and an unverified directory version claim.
	 */
	public function test_product_home_content_rejects_legacy_and_unverified_claims(): void {
		$content = strtolower( $this->content() );

		$this->assertStringNotContainsString( 'site-pilot-ai', $content );
		$this->assertStringNotContainsString( 'spai_', $content );
		$this->assertStringNotContainsString( '3.10.2', $content );
		$this->assertStringNotContainsString( 'testimonial', $content );
		$this->assertStringNotContainsString( '★★★★★', $content );
		$this->assertStringNotContainsString( '5-star', $content );
		$this->assertStringNotContainsString( 'customer quote', $content );
	}

	/**
	 * Reads the source-controlled page-content artifact.
	 */
	private function content(): string {
		$path = dirname( __DIR__ ) . '/site-content/mcpwp-product-home.html';

		$this->assertFileExists( $path );

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local content artifact.
		$this->assertIsString( $content );

		return $content;
	}
}
