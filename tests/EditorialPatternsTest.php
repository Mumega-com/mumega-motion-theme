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
		$GLOBALS['mumega_motion_test_loop_posts']         = array();
		$GLOBALS['mumega_motion_test_loop_index']         = 0;
		$GLOBALS['mumega_motion_test_current_post']       = null;
	}

	/**
	 * Registers the single editorial category and twelve stable pattern slugs.
	 */
	public function test_registers_one_editorial_category_and_exactly_twelve_patterns(): void {
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
				'mumega-motion/explainer',
				'mumega-motion/practical-guide',
				'mumega-motion/test-report',
				'mumega-motion/comparison-review',
				'mumega-motion/news-briefing',
				'mumega-motion/analysis-opinion',
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

		foreach ( array( 'Stay informed with reporting delivered to your inbox.', 'By subscribing, you agree to receive email updates.', 'Insert your site&#039;s existing newsletter form block here.' ) as $field ) {
			$this->assertStringContainsString( $field, $content );
		}
	}

	/**
	 * Defers the page-level H1 to whichever template owns the newsletter content.
	 */
	public function test_newsletter_page_pattern_contains_no_page_level_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/newsletter-page' );

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives an explainer every required section from the approved design and no page-level H1.
	 */
	public function test_explainer_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/explainer' );

		foreach (
			array(
				'Direct answer',
				'Why it matters to the primary audience',
				'How it works',
				'Concrete WordPress example',
				'Boundaries and common misconceptions',
				'What to do next',
				'Sources and updates',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives a practical guide every required section from the approved design and no page-level H1.
	 */
	public function test_practical_guide_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/practical-guide' );

		foreach (
			array(
				'Outcome and suitability',
				'Prerequisites and permissions',
				'Tested versions and date',
				'Ordered procedure',
				'Verification steps',
				'Failure modes',
				'Rollback or recovery',
				'Security and data considerations',
				'Sources and updates',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives a test report every required section from the approved design and no page-level H1.
	 */
	public function test_test_report_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/test-report' );

		foreach (
			array(
				'Question and hypothesis',
				'Environment and versions',
				'Procedure',
				'Observations and artifacts',
				'Results',
				'Failures and anomalies',
				'Limitations and reproducibility',
				'Conclusion bounded to the evidence',
				'Sources, corrections and retest trigger',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives a comparison review every required section from the approved design and no page-level H1.
	 */
	public function test_comparison_review_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/comparison-review' );

		foreach (
			array(
				'Decision and audience',
				'Inclusion and exclusion criteria',
				'Tested versions, access and commercial relationships',
				'Comparable evidence table',
				'Findings by decision criterion',
				'Best fit and poor fit for each option',
				'Limitations',
				'Independent conclusion',
				'Affiliate disclosure when applicable',
				'Sources and update trigger',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringContainsString( '<!-- wp:table', $content );

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives a news briefing every required section from the approved design and no page-level H1.
	 */
	public function test_news_briefing_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/news-briefing' );

		foreach (
			array(
				'What changed',
				'Primary announcement or artifact',
				'Who is affected',
				'Why it matters now',
				'What readers should do',
				'What remains unknown',
				'Connection to a durable topic page',
				'Sources and dated update note',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Gives an analysis or opinion piece every required section from the approved design and no page-level H1.
	 */
	public function test_analysis_opinion_has_required_sections_and_no_h1(): void {
		$content = $this->pattern_content( 'mumega-motion/analysis-opinion' );

		foreach (
			array(
				'Thesis',
				'Evidence',
				'Strongest counterargument',
				'Analysis separating facts from inference',
				'Implications for the primary audience',
				'Conditions that would change the conclusion',
				'Sources and author disclosure',
			) as $heading
		) {
			$this->assertStringContainsString( $heading, $content );
		}

		$this->assertStringNotContainsString( '<h1', $content );
		$this->assertStringNotContainsString( '"level":1', $content );
	}

	/**
	 * Keeps a standalone Newsletter page to one template-owned H1.
	 */
	public function test_standalone_newsletter_page_renders_one_h1(): void {
		$page                                     = new WP_Post(
			array(
				'ID'           => 41,
				'post_title'   => 'The Weekly Brief',
				'post_content' => $this->pattern_content( 'mumega-motion/newsletter-page' ),
			)
		);
		$GLOBALS['mumega_motion_test_loop_posts'] = array( $page );

		ob_start();
		require dirname( __DIR__ ) . '/page.php';
		$output = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>\s*The Weekly Brief\s*<\/h1>/s', $output );
		$this->assertStringContainsString( 'Stay informed with reporting delivered to your inbox.', $output );
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
			'Stay informed with reporting delivered to your inbox.',
			'By subscribing, you agree to receive email updates.',
			'Insert your site\'s existing newsletter form block here.',
			'Direct answer',
			'State the direct answer to the reader\'s question in the first sentence.',
			'Why it matters to the primary audience',
			'Explain why this matters to the primary audience for this topic.',
			'How it works',
			'Describe the underlying mechanism or process in plain language.',
			'Concrete WordPress example',
			'Provide a concrete WordPress example that illustrates the explanation.',
			'Boundaries and common misconceptions',
			'Add a boundary or edge case where this explanation no longer applies.',
			'Add a common misconception and correct it.',
			'What to do next',
			'Add the next recommended action for the reader.',
			'Sources and updates',
			'Add sources, documentation, or references.',
			'Record material updates to this explanation here.',
			'Outcome and suitability',
			'State the outcome this guide produces and who it is suitable for.',
			'Prerequisites and permissions',
			'Add a required prerequisite.',
			'Add the WordPress role or capability needed.',
			'Tested versions and date',
			'Record the WordPress, plugin, and theme versions tested, and the test date.',
			'Ordered procedure',
			'Add the first step.',
			'Add the next step.',
			'Verification steps',
			'Add a step that confirms the procedure succeeded.',
			'Failure modes',
			'Add a way this procedure can fail.',
			'Rollback or recovery',
			'Describe how to undo this procedure or recover from a failed attempt.',
			'Security and data considerations',
			'Note security implications and any data handled by this procedure.',
			'Add sources or documentation referenced.',
			'Question and hypothesis',
			'State the question this test answers and the hypothesis being evaluated.',
			'Environment and versions',
			'Record the hardware, software, and versions used during testing.',
			'Observations and artifacts',
			'Add an observation recorded during testing.',
			'Link to a supporting artifact such as a screenshot or log.',
			'Report the measured results.',
			'Failures and anomalies',
			'Add a failure or anomaly observed during testing.',
			'Limitations and reproducibility',
			'Note constraints on reproducing this test and its results.',
			'Conclusion bounded to the evidence',
			'State a conclusion that does not exceed what the evidence supports.',
			'Sources, corrections and retest trigger',
			'Add sources or documents referenced.',
			'Add the condition that would trigger a retest.',
			'Decision and audience',
			'State the decision this review helps the reader make and who it is for.',
			'Inclusion and exclusion criteria',
			'Add a criterion an option must meet for inclusion.',
			'Add a criterion that excludes an option.',
			'Tested versions, access and commercial relationships',
			'Record the versions tested, how access was obtained, and any commercial relationship with each vendor. Link official documentation, pricing, terms, and changelogs for each compared product.',
			'Do not label a product "tested" unless it was directly tested.',
			'Comparable evidence table',
			'Option',
			'Criterion',
			'Evidence',
			'Directly tested',
			'Add an option.',
			'Add the criterion assessed.',
			'Add supporting evidence.',
			'State whether this option was directly tested.',
			'Findings by decision criterion',
			'Add a finding for one decision criterion.',
			'Best fit and poor fit for each option',
			'Add the best-fit use case for an option.',
			'Add the poor-fit use case for an option.',
			'Independent conclusion',
			'State a conclusion independent of any commercial relationship disclosed above.',
			'Affiliate disclosure when applicable',
			'If this review contains affiliate links, disclose that relationship here; otherwise state that none exists.',
			'Sources and update trigger',
			'Add the condition that would trigger an update to this comparison.',
			'What changed',
			'State what changed in one or two sentences.',
			'Primary announcement or artifact',
			'Link to or describe the primary announcement or artifact this briefing covers.',
			'Who is affected',
			'Add a group of users or sites affected.',
			'Why it matters now',
			'Explain why this change matters at this moment.',
			'What readers should do',
			'Add a recommended action for readers.',
			'What remains unknown',
			'Add a question that remains unanswered.',
			'Connection to a durable topic page',
			'Link to the durable topic page this briefing relates to.',
			'Sources and dated update note',
			'Add sources for this briefing.',
			'Record the date and nature of any update to this briefing.',
			'Thesis',
			'State the thesis in one or two sentences.',
			'Add a piece of supporting evidence.',
			'Strongest counterargument',
			'State the strongest argument against this thesis.',
			'Analysis separating facts from inference',
			'Separate what is established fact from what is the author\'s inference.',
			'Implications for the primary audience',
			'Explain what this means for the primary audience.',
			'Conditions that would change the conclusion',
			'Add a condition that would change this conclusion.',
			'Sources and author disclosure',
			'Add sources referenced.',
			'Disclose the author\'s relevant affiliations or interests.',
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
