<?php
/**
 * Tests for the source-controlled MCPWP control-plane homepage content.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the importable MCPWP homepage meaning and claim boundaries.
 */
final class McpwpControlHomeContentTest extends TestCase {
	/**
	 * Catches a homepage that competes with itself instead of asking one question and exposing three paths.
	 */
	public function test_homepage_has_one_question_and_exactly_three_stable_intents(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, '<h1' ) );
		$this->assertStringContainsString( 'What do you want AI to do with WordPress?', $content );
		$this->assertSame( 3, substr_count( $content, 'data-mcpwp-intent=' ) );

		foreach ( array( 'operate', 'scale', 'understand' ) as $intent ) {
			$this->assertStringContainsString( 'href="#' . $intent . '"', $content );
			$this->assertStringContainsString( 'data-mcpwp-intent="' . $intent . '"', $content );
			$this->assertStringContainsString( 'id="' . $intent . '"', $content );
		}

		$this->assertStringContainsString( 'I run a WordPress business.', $content );
		$this->assertStringContainsString( 'I manage or build WordPress sites.', $content );
		$this->assertStringContainsString( 'I am evaluating WordPress and AI.', $content );
	}

	/**
	 * Catches a decorative graph whose route data cannot drive the progressive enhancement.
	 */
	public function test_control_plane_exposes_server_owned_route_data_and_one_explicit_mount(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, 'data-mcpwp-control-plane' ) );
		$this->assertSame( 1, substr_count( $content, 'data-motion="control-plane"' ) );
		$this->assertSame( 3, substr_count( $content, 'data-mcpwp-summary=' ) );
		$this->assertSame( 3, substr_count( $content, 'data-mcpwp-action-label=' ) );
		$this->assertSame( 3, substr_count( $content, 'data-mcpwp-action-href=' ) );
		$this->assertSame( 3, substr_count( $content, 'data-mcpwp-event="homepage_intent_selected"' ) );
		$this->assertStringContainsString( 'data-mcpwp-control-summary', $content );
		$this->assertStringContainsString( 'aria-live="polite"', $content );
	}

	/**
	 * Catches fabricated activity or a workflow that omits its human and audit boundaries.
	 */
	public function test_evidence_trace_is_explicitly_an_example_and_keeps_all_review_boundaries(): void {
		$content = $this->content();

		$this->assertStringContainsString( 'EXAMPLE EVIDENCE TRACE', $content );

		foreach ( array( 'Request', 'Scope', 'Human gate', 'WordPress result', 'Activity record' ) as $step ) {
			$this->assertStringContainsString( '>' . $step . '<', $content );
		}

		$this->assertStringContainsString( 'This is an illustrative workflow, not a live activity feed.', $content );
		$this->assertStringContainsString( 'WordPress revisions and backups remain part of recovery.', $content );
	}

	/**
	 * Catches a category homepage that obscures its commercial product relationship or sends users to fake destinations.
	 */
	public function test_flagship_product_and_commercial_paths_are_disclosed_with_real_destinations(): void {
		$content = $this->content();

		$this->assertStringContainsString( 'Mumega MCP is the WordPress AI connector we build and test.', $content );
		$this->assertStringContainsString( 'https://wordpress.org/plugins/mumega-mcp/', $content );
		$this->assertStringContainsString( 'https://mcpwp.net/pricing/', $content );
		$this->assertStringContainsString( 'https://mcpwp.net/agencies/', $content );
		$this->assertStringContainsString( '>Free<', $content );
		$this->assertStringContainsString( '>Pro<', $content );
		$this->assertStringContainsString( '>Agency<', $content );

		foreach ( array( 'Claude', 'ChatGPT', 'Gemini', 'Codex', 'Hermes', 'OpenClaw' ) as $client ) {
			$this->assertStringContainsString( $client, $content );
		}
	}

	/**
	 * Catches a product-only homepage that removes the durable evidence and native discovery path.
	 */
	public function test_knowledge_mesh_uses_native_search_and_published_foundations(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, '[mumega_product_search]' ) );
		$this->assertStringContainsString( 'https://mcpwp.net/what-is-wordpress-mcp-server/', $content );
		$this->assertStringContainsString( 'https://mcpwp.net/secure-wordpress-mcp-api-keys-scopes/', $content );
		$this->assertStringContainsString( 'Follow the evidence, not just the feed.', $content );
		$this->assertStringContainsString( 'ASTER’S SIGNAL', $content );
	}

	/**
	 * Catches nested document shells, unlabeled sections, and script-dependent meaning.
	 */
	public function test_content_is_semantic_script_free_and_owned_by_one_page_root(): void {
		$content = $this->content();

		$this->assertSame( 1, substr_count( $content, 'class="mcpwp-control-home"' ) );
		$this->assertStringContainsString( '<header class="mcpwp-control-home__header">', $content );
		$this->assertStringContainsString( '<footer class="mcpwp-control-home__footer"', $content );
		$this->assertGreaterThanOrEqual( 6, substr_count( $content, 'aria-labelledby=' ) );
		$this->assertStringNotContainsString( '<main', strtolower( $content ) );
		$this->assertStringNotContainsString( '<script', strtolower( $content ) );
		$this->assertStringNotContainsString( '<img', strtolower( $content ) );
	}

	/**
	 * Catches marketing claims for systems, proof, or versions that have not been verified.
	 */
	public function test_homepage_rejects_unverified_and_legacy_claims(): void {
		$content = strtolower( $this->content() );

		foreach (
			array(
				'semantic search',
				'live knowledge graph',
				'testimonial',
				'★★★★★',
				'5-star',
				'customer count',
				'3.10.2',
				'site-pilot-ai',
				'spai_',
			) as $forbidden_claim
		) {
			$this->assertStringNotContainsString( $forbidden_claim, $content );
		}
	}

	/**
	 * Reads the source-controlled homepage artifact.
	 */
	private function content(): string {
		$path = dirname( __DIR__ ) . '/site-content/mcpwp-control-home.html';

		$this->assertFileExists( $path );

		if ( ! is_file( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect a local content artifact.
		$this->assertIsString( $content );

		return (string) $content;
	}
}
