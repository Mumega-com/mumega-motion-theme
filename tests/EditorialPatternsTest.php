<?php
/**
 * Tests for reusable editorial block patterns.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

$editorial_patterns_file = dirname( __DIR__ ) . '/inc/editorial-patterns.php';

if ( file_exists( $editorial_patterns_file ) ) {
	require_once $editorial_patterns_file;
}

/**
 * Exercises the editorial pattern registration contract.
 */
final class EditorialPatternsTest extends TestCase {
	/**
	 * Resets recorded registrations before each assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_translations']       = array();
		$GLOBALS['mumega_motion_test_pattern_categories'] = array();
		$GLOBALS['mumega_motion_test_patterns']           = array();
	}

	/**
	 * Registers the single editorial category and six stable pattern slugs.
	 */
	public function test_registers_one_editorial_category_and_exactly_six_patterns(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/inc/editorial-patterns.php' );
		$this->assertStringContainsString(
			'/inc/editorial-patterns.php',
			(string) file_get_contents( dirname( __DIR__ ) . '/functions.php' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests inspect the local theme bootstrap.
		);

		do_action( 'init' );

		$this->assertSame( array( 'mumega-motion' ), array_keys( $GLOBALS['mumega_motion_test_pattern_categories'] ) );
		$this->assertSame(
			array(
				'mumega-motion/article-brief',
				'mumega-motion/test-method',
				'mumega-motion/evidence-table',
				'mumega-motion/affiliate-disclosure',
				'mumega-motion/correction-note',
				'mumega-motion/newsletter-page',
			),
			array_keys( $GLOBALS['mumega_motion_test_patterns'] )
		);
	}

	/**
	 * Gives an article brief every required reporting section without automatic TOC generation.
	 */
	public function test_article_brief_has_reporting_sections_and_a_linked_contents_list(): void {
		$content = $this->pattern_content( 'mumega-motion/article-brief' );

		foreach ( array( 'Summary', 'Key takeaways', 'Contents', 'Methodology', 'Sources', 'Corrections / updates' ) as $heading ) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringContainsString( 'href="#methodology"', $content );
		$this->assertStringNotContainsString( 'wp:table-of-contents', $content );
	}

	/**
	 * Gives a test-method pattern every reproducibility field.
	 */
	public function test_test_method_has_reproducibility_sections(): void {
		$content = $this->pattern_content( 'mumega-motion/test-method' );

		foreach ( array( 'Question', 'Environment', 'Models/tools tested', 'Procedure', 'Date', 'Limitations', 'Results' ) as $heading ) {
			$this->assertStringContainsString( $heading, $content );
		}
	}

	/**
	 * Uses a core table for the required evidence fields.
	 */
	public function test_evidence_table_has_required_columns(): void {
		$content = $this->pattern_content( 'mumega-motion/evidence-table' );

		$this->assertStringContainsString( '<!-- wp:table', $content );

		foreach ( array( 'Claim', 'Observation', 'Source', 'Confidence' ) as $column ) {
			$this->assertStringContainsString( '<th>' . $column . '</th>', $content );
		}
	}

	/**
	 * Makes the authored disclosure recognizable to the single-template fallback guard.
	 */
	public function test_affiliate_disclosure_has_independent_editorial_copy_and_editable_policy_link(): void {
		$content = $this->pattern_content( 'mumega-motion/affiliate-disclosure' );

		$this->assertStringContainsString( 'mumega-motion-affiliate-disclosure', $content );
		$this->assertStringContainsString( 'This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.', $content );
		$this->assertStringContainsString( '<a href="/affiliate-disclosure/">Read our affiliate policy</a>', $content );
		$this->assertStringNotContainsString( 'href="#"', $content );
		$this->assertStringNotContainsString( 'mcpwp', strtolower( $content ) );
	}

	/**
	 * Provides visible correction fields for an amended report.
	 */
	public function test_correction_note_has_visible_date_and_revision_fields(): void {
		$content = $this->pattern_content( 'mumega-motion/correction-note' );

		foreach ( array( 'Correction', 'Date: [Insert correction date]', 'Previous claim', 'Revised finding', 'Explanation' ) as $field ) {
			$this->assertStringContainsString( $field, $content );
		}
	}

	/**
	 * Provides newsletter context and an insertion area without implementing a form.
	 */
	public function test_newsletter_page_has_consent_copy_and_a_provider_form_insertion_area(): void {
		$content = $this->pattern_content( 'mumega-motion/newsletter-page' );

		foreach ( array( 'Newsletter', 'Stay informed with reporting delivered to your inbox.', 'By subscribing, you agree to receive email updates.', 'Insert your site&#039;s existing newsletter form block here.' ) as $field ) {
			$this->assertStringContainsString( $field, $content );
		}
	}

	/**
	 * Keeps every registered pattern limited to WordPress core blocks.
	 */
	public function test_patterns_use_only_core_blocks(): void {
		do_action( 'init' );

		foreach ( $GLOBALS['mumega_motion_test_patterns'] as $pattern ) {
			preg_match_all( '/<!--\\s*\\/?([^\\s]+).*?-->/', $pattern['content'], $matches );
			$this->assertTrue( $this->serialized_block_comments_are_valid( $pattern['content'] ) );

			foreach ( $matches[1] as $block_name ) {
				$this->assertStringStartsWith( 'wp:', $block_name );
			}
		}
	}

	/**
	 * Rejects block comments that close a different nested block than they opened.
	 */
	public function test_serialized_block_validator_rejects_mismatched_nesting(): void {
		$content = '<!-- wp:group -->\n<div class="wp-block-group"><!-- wp:paragraph -->\n<p>Text.</p>\n<!-- /wp:group --></div>\n<!-- /wp:paragraph -->';

		$this->assertFalse( $this->serialized_block_comments_are_valid( $content ) );
	}

	/**
	 * Accepts self-closing core block comments within a nested block structure.
	 */
	public function test_serialized_block_validator_accepts_self_closing_blocks(): void {
		$content = '<!-- wp:group -->\n<div class="wp-block-group"><!-- wp:spacer {"height":"1px"} /--></div>\n<!-- /wp:group -->';

		$this->assertTrue( $this->serialized_block_comments_are_valid( $content ) );
	}

	/**
	 * Localizes every editor-visible pattern string before serializing block markup.
	 */
	public function test_visible_pattern_content_uses_the_translation_mapping(): void {
		$source_strings = array(
			'Summary',
			'Write a concise summary of the reporting and its significance.',
			'Key takeaways',
			'Add the first key finding.',
			'Add the second key finding.',
			'Contents',
			'Methodology',
			'Sources',
			'Corrections / updates',
			'Describe how the reporting, testing, or analysis was conducted.',
			'Add sources, documents, or interviews.',
			'Record material corrections or updates here.',
			'Question',
			'State the question this test is designed to answer.',
			'Environment',
			'Record the hardware, software, settings, and relevant conditions.',
			'Models/tools tested',
			'Add each model or tool and version.',
			'Procedure',
			'Describe the repeatable steps followed during the test.',
			'Date',
			'Record the date of testing.',
			'Limitations',
			'Note constraints, unknowns, and conditions that may affect the results.',
			'Results',
			'Report the observed results and link to supporting evidence.',
			'Claim',
			'Observation',
			'Source',
			'Confidence',
			'Add a claim.',
			'Add the observed evidence.',
			'Add a source.',
			'Add a confidence assessment.',
			'This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.',
			'Read our affiliate policy',
			'Correction',
			'Date: [Insert correction date]',
			'Previous claim',
			'State the original claim.',
			'Revised finding',
			'State the corrected finding.',
			'Explanation',
			'Explain what changed and why.',
			'Newsletter',
			'Stay informed with reporting delivered to your inbox.',
			'By subscribing, you agree to receive email updates.',
			'Insert your site\'s existing newsletter form block here.',
		);

		$translations = array();

		foreach ( $source_strings as $index => $source_string ) {
			$translations[ $source_string ] = 'Localized pattern text ' . (string) $index;
		}

		$GLOBALS['mumega_motion_test_translations']['mumega-motion'] = $translations;
		do_action( 'init' );

		$all_content = implode(
			"\n",
			array_column( $GLOBALS['mumega_motion_test_patterns'], 'content' )
		);

		foreach ( $translations as $source_string => $translated_string ) {
			$this->assertStringContainsString( $translated_string, $all_content );
			$this->assertStringNotContainsString( $source_string, $all_content );
		}
	}

	/**
	 * Returns one registered pattern's block markup.
	 *
	 * @param string $slug Pattern slug.
	 * @return string
	 */
	private function pattern_content( string $slug ): string {
		do_action( 'init' );

		$this->assertArrayHasKey( $slug, $GLOBALS['mumega_motion_test_patterns'] );

		return $GLOBALS['mumega_motion_test_patterns'][ $slug ]['content'];
	}

	/**
	 * Validates nesting and names in serialized Gutenberg block comments.
	 *
	 * @param string $content Serialized block content.
	 * @return bool
	 */
	private function serialized_block_comments_are_valid( string $content ): bool {
		$stack = array();

		preg_match_all( '/<!--.*?-->/s', $content, $comments );

		foreach ( $comments[0] as $comment ) {
			if ( false === strpos( $comment, 'wp:' ) ) {
				continue;
			}

			if ( 1 !== preg_match( '/^<!--\\s*(\\/?)wp:([a-z0-9-]+)\\s+(.*?)-->$/si', $comment, $block_comment ) ) {
				return false;
			}

			$is_closing = '/' === $block_comment[1];
			$block_name = $block_comment[2];
			$payload    = trim( $block_comment[3] );

			if ( $is_closing ) {
				if ( '' !== $payload || empty( $stack ) || end( $stack ) !== $block_name ) {
					return false;
				}

				array_pop( $stack );
				continue;
			}

			if ( '/' !== substr( $payload, -1 ) ) {
				$stack[] = $block_name;
			}
		}

		return empty( $stack );
	}
}
