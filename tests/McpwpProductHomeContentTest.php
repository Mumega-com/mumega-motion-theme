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
	 * Keeps the primary install path, ASTER attachment token, and supported clients intact.
	 */
	public function test_product_home_content_has_the_approved_conversion_contract(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, '<h1' ) );
		$this->assertStringContainsString( 'Install free from WordPress.org', $content );
		$this->assertStringContainsString( 'https://wordpress.org/plugins/mumega-mcp/', $content );
		$this->assertStringContainsString( '{{ASTER_ID}}', $content );
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
		$this->assertSame( 1, substr_count( $content, '[mumega_product_search]' ) );
		$this->assertStringContainsString( '<section id="workflow"', $content );
		$this->assertStringContainsString( '<section id="first-connection"', $content );
		$this->assertStringContainsString( '<section id="agency"', $content );
		$this->assertStringContainsString( '<section id="guides"', $content );
		$this->assertStringContainsString( '<svg', $content );
		$this->assertStringNotContainsString( '<script', strtolower( $content ) );
	}

	/**
	 * Keeps free-edition positioning scope- and draft-first while naming paid approval gates.
	 */
	public function test_product_home_content_keeps_honest_edition_boundaries(): void {
		$content = $this->content();

		$this->assertStringContainsString( 'scoped access, draft-first workflows, and a visible activity history', $content );
		$this->assertStringContainsString( 'Scoped access. Draft-first work. Visible activity for review and recovery.', $content );
		$this->assertStringContainsString( 'Human approval gates are part of Agency Mode’s paid governance.', $content );
		$this->assertStringContainsString( 'The free edition stays scope- and draft-first.', $content );
		$this->assertStringNotContainsString( 'scoped permissions, approvals, and a visible activity history', $content );
		$this->assertStringNotContainsString( 'Approval before risk.', $content );
	}

	/**
	 * Uses WordPress attachment rendering instead of a full-size raw ASTER image.
	 */
	public function test_product_home_content_delegates_aster_to_responsive_attachment_markup(): void {
		$content = $this->content();

		$this->assertStringContainsString( '[mumega_product_aster_image id="{{ASTER_ID}}"]', $content );
		$this->assertStringNotContainsString( '<img class="mcpwp-product-home__portrait"', $content );
		$this->assertStringNotContainsString( '{{ASTER_URL}}', $content );
	}

	/**
	 * Points both conversion-oriented Agency links to the actual Agency destination.
	 */
	public function test_product_home_agency_ctas_use_the_agency_destination(): void {
		$content = $this->content();

		$this->assertSame( 2, substr_count( $content, 'href="https://mcpwp.net/agencies/"' ) );
		$this->assertStringContainsString( '>Explore Agency Mode</a>', $content );
		$this->assertStringNotContainsString( 'href="#agency-recovery">Review the operating approach</a>', $content );
	}

	/**
	 * Keeps product navigation destinations distinct and truthful.
	 */
	public function test_product_home_navigation_uses_meaningful_destinations(): void {
		$content = $this->content();

		$this->assertStringContainsString( '<a href="#product">Product</a>', $content );
		$this->assertStringContainsString( '<a href="#workflow">How it works</a>', $content );
		$this->assertStringContainsString( '<a href="#agency">Agency</a>', $content );
		$this->assertStringContainsString( '<a href="#guides">Guides</a>', $content );
		$this->assertStringContainsString( '<a href="https://mcpwp.net/pricing/">Pricing</a>', $content );
		$this->assertStringNotContainsString( '<a href="#agency">Pricing</a>', $content );
	}

	/**
	 * Keeps all four durable guide questions attached to their canonical published resources.
	 */
	public function test_product_home_guides_cover_each_durable_topic_with_a_canonical_link(): void {
		$content = $this->content();

		$guides = array(
			'https://mcpwp.net/what-is-wordpress-mcp-server/' => 'What is WordPress MCP?',
			'https://mcpwp.net/secure-wordpress-mcp-api-keys-scopes/' => 'How do scoped permissions work?',
			'#client-choice'   => 'Which AI client should a team use?',
			'#agency-recovery' => 'How should an agency review and recover changes?',
		);

		foreach ( $guides as $url => $topic ) {
			$this->assertStringContainsString( 'href="' . $url . '"', $content );
			$this->assertStringContainsString( $topic, $content );
		}

		$this->assertStringNotContainsString( '/?p=', $content );
	}

	/**
	 * Keeps client-choice guidance current, read-first, and free of universal rankings.
	 */
	public function test_product_home_client_choice_uses_truthful_on_page_guidance(): void {
		$content = $this->content();

		$this->assertStringContainsString( '<details id="client-choice">', $content );
		$this->assertStringContainsString( 'Begin with the AI client your team has already approved.', $content );
		$this->assertStringContainsString( 'Confirm current MCPWP compatibility in the installed plugin.', $content );
		$this->assertStringContainsString( 'Start with read-only access.', $content );
		$this->assertStringContainsString( 'No one client is universally best.', $content );
		$this->assertStringNotContainsString( 'https://mcpwp.net/connect-chatgpt-to-wordpress/', $content );
	}

	/**
	 * Keeps agency recovery guidance bounded to one site and real WordPress recovery controls.
	 */
	public function test_product_home_agency_recovery_uses_truthful_on_page_guidance(): void {
		$content = $this->content();

		$this->assertStringContainsString( '<details id="agency-recovery">', $content );
		$this->assertStringContainsString( 'Isolate one site.', $content );
		$this->assertStringContainsString( 'Review the intended actions.', $content );
		$this->assertStringContainsString( 'Create a draft.', $content );
		$this->assertStringContainsString( 'Verify the activity record.', $content );
		$this->assertStringContainsString( 'Use WordPress revisions and backups for recovery.', $content );
		$this->assertStringNotContainsString( 'https://mcpwp.net/agency-wordpress-ai-operations-playbook/', $content );
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
