<?php
/**
 * Reusable core-block patterns for editorial publishing.
 *
 * @package Mumega_Motion
 */

/**
 * Translates and escapes editor-visible pattern text.
 *
 * @param string $text Source text.
 * @return string
 */
function mumega_motion_editorial_pattern_text( $text ) {
	return esc_html__( $text, 'mumega-motion' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- This helper receives only literal pattern source strings.
}

/**
 * Builds a serialized core Heading block.
 *
 * @param string $text Heading text.
 * @param int    $level Heading level.
 * @param string $anchor Optional heading anchor.
 * @return string
 */
function mumega_motion_editorial_pattern_heading( $text, $level = 2, $anchor = '' ) {
	$level            = (int) $level;
	$block_attributes = ' {"level":' . $level;
	$html_attributes  = '';

	if ( '' !== $anchor ) {
		$anchor            = esc_attr( $anchor );
		$block_attributes .= ',"anchor":"' . $anchor . '"';
		$html_attributes   = sprintf( ' class="wp-block-heading" id="%s"', $anchor );
	}

	$block_attributes .= '}';

	return sprintf(
		"<!-- wp:heading%s -->\n<h%d%s>%s</h%d>\n<!-- /wp:heading -->",
		$block_attributes,
		$level,
		$html_attributes,
		mumega_motion_editorial_pattern_text( $text ),
		$level
	);
}

/**
 * Builds a serialized core Paragraph block.
 *
 * @param string $text Paragraph text.
 * @return string
 */
function mumega_motion_editorial_pattern_paragraph( $text ) {
	return sprintf(
		"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
		mumega_motion_editorial_pattern_text( $text )
	);
}

/**
 * Builds a serialized core List block from text items.
 *
 * @param array $items List item text.
 * @return string
 */
function mumega_motion_editorial_pattern_list( $items ) {
	$list_items = '';

	foreach ( $items as $item ) {
		$list_items .= sprintf( '<li>%s</li>', mumega_motion_editorial_pattern_text( $item ) );
	}

	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $list_items . "</ul>\n<!-- /wp:list -->";
}

/**
 * Builds a serialized core List block with links to static heading anchors.
 *
 * @param array $items Heading anchors keyed to visible labels.
 * @return string
 */
function mumega_motion_editorial_pattern_linked_list( $items ) {
	$list_items = '';

	foreach ( $items as $anchor => $text ) {
		$list_items .= sprintf(
			'<li><a href="#%1$s">%2$s</a></li>',
			esc_attr( $anchor ),
			mumega_motion_editorial_pattern_text( $text )
		);
	}

	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $list_items . "</ul>\n<!-- /wp:list -->";
}

/**
 * Builds a serialized core Table block.
 *
 * @param array $headers Table heading text.
 * @param array $cells Table cell text.
 * @return string
 */
function mumega_motion_editorial_pattern_table( $headers, $cells ) {
	$header_cells = '';
	$body_cells   = '';

	foreach ( $headers as $header ) {
		$header_cells .= sprintf( '<th>%s</th>', mumega_motion_editorial_pattern_text( $header ) );
	}

	foreach ( $cells as $cell ) {
		$body_cells .= sprintf( '<td>%s</td>', mumega_motion_editorial_pattern_text( $cell ) );
	}

	return sprintf(
		"<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr>%1\$s</tr></thead><tbody><tr>%2\$s</tr></tbody></table></figure>\n<!-- /wp:table -->",
		$header_cells,
		$body_cells
	);
}

/**
 * Wraps serialized core blocks in a serialized core Group block.
 *
 * @param string $content Serialized nested blocks.
 * @param string $class_name Optional static class name.
 * @return string
 */
function mumega_motion_editorial_pattern_group( $content, $class_name = '' ) {
	$block_attributes = '';
	$html_classes     = 'wp-block-group';

	if ( '' !== $class_name ) {
		$class_name       = esc_attr( $class_name );
		$block_attributes = ' {"className":"' . $class_name . '"}';
		$html_classes    .= ' ' . $class_name;
	}

	return sprintf(
		"<!-- wp:group%1\$s -->\n<div class=\"%2\$s\">%3\$s</div>\n<!-- /wp:group -->",
		$block_attributes,
		$html_classes,
		$content
	);
}

/**
 * Builds Article Brief core-block markup.
 *
 * @return string
 */
function mumega_motion_article_brief_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Summary' ),
			mumega_motion_editorial_pattern_paragraph( 'Write a concise summary of the reporting and its significance.' ),
			mumega_motion_editorial_pattern_heading( 'Key takeaways' ),
			mumega_motion_editorial_pattern_list( array( 'Add the first key finding.', 'Add the second key finding.' ) ),
			mumega_motion_editorial_pattern_heading( 'Contents' ),
			mumega_motion_editorial_pattern_linked_list(
				array(
					'methodology'         => 'Methodology',
					'sources'             => 'Sources',
					'corrections-updates' => 'Corrections / updates',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Methodology', 2, 'methodology' ),
			mumega_motion_editorial_pattern_paragraph( 'Describe how the reporting, testing, or analysis was conducted.' ),
			mumega_motion_editorial_pattern_heading( 'Sources', 2, 'sources' ),
			mumega_motion_editorial_pattern_list( array( 'Add sources, documents, or interviews.' ) ),
			mumega_motion_editorial_pattern_heading( 'Corrections / updates', 2, 'corrections-updates' ),
			mumega_motion_editorial_pattern_paragraph( 'Record material corrections or updates here.' ),
		)
	);
}

/**
 * Builds Test Method core-block markup.
 *
 * @return string
 */
function mumega_motion_test_method_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Question' ),
			mumega_motion_editorial_pattern_paragraph( 'State the question this test is designed to answer.' ),
			mumega_motion_editorial_pattern_heading( 'Environment' ),
			mumega_motion_editorial_pattern_paragraph( 'Record the hardware, software, settings, and relevant conditions.' ),
			mumega_motion_editorial_pattern_heading( 'Models/tools tested' ),
			mumega_motion_editorial_pattern_list( array( 'Add each model or tool and version.' ) ),
			mumega_motion_editorial_pattern_heading( 'Procedure' ),
			mumega_motion_editorial_pattern_paragraph( 'Describe the repeatable steps followed during the test.' ),
			mumega_motion_editorial_pattern_heading( 'Date' ),
			mumega_motion_editorial_pattern_paragraph( 'Record the date of testing.' ),
			mumega_motion_editorial_pattern_heading( 'Limitations' ),
			mumega_motion_editorial_pattern_paragraph( 'Note constraints, unknowns, and conditions that may affect the results.' ),
			mumega_motion_editorial_pattern_heading( 'Results' ),
			mumega_motion_editorial_pattern_paragraph( 'Report the observed results and link to supporting evidence.' ),
		)
	);
}

/**
 * Builds Affiliate Disclosure core-block markup.
 *
 * @return string
 */
function mumega_motion_affiliate_disclosure_pattern_content() {
	$disclosure = mumega_motion_editorial_pattern_text( 'This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.' );
	$policy     = mumega_motion_editorial_pattern_text( 'Read our affiliate policy' );
	$paragraph  = sprintf(
		"<!-- wp:paragraph -->\n<p>%1\$s <a href=\"%2\$s\">%3\$s</a></p>\n<!-- /wp:paragraph -->",
		$disclosure,
		esc_url( '/affiliate-disclosure/' ),
		$policy
	);

	return mumega_motion_editorial_pattern_group( $paragraph, 'mumega-motion-affiliate-disclosure' );
}

/**
 * Builds Correction Note core-block markup.
 *
 * @return string
 */
function mumega_motion_correction_note_pattern_content() {
	return mumega_motion_editorial_pattern_group(
		implode(
			"\n\n",
			array(
				mumega_motion_editorial_pattern_heading( 'Correction' ),
				mumega_motion_editorial_pattern_paragraph( 'Date: [Insert correction date]' ),
				mumega_motion_editorial_pattern_heading( 'Previous claim', 3 ),
				mumega_motion_editorial_pattern_paragraph( 'State the original claim.' ),
				mumega_motion_editorial_pattern_heading( 'Revised finding', 3 ),
				mumega_motion_editorial_pattern_paragraph( 'State the corrected finding.' ),
				mumega_motion_editorial_pattern_heading( 'Explanation', 3 ),
				mumega_motion_editorial_pattern_paragraph( 'Explain what changed and why.' ),
			)
		)
	);
}

/**
 * Builds Newsletter Page core-block markup.
 *
 * @return string
 */
function mumega_motion_newsletter_page_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_paragraph( 'Stay informed with reporting delivered to your inbox.' ),
			mumega_motion_editorial_pattern_paragraph( 'By subscribing, you agree to receive email updates.' ),
			mumega_motion_editorial_pattern_group(
				mumega_motion_editorial_pattern_paragraph( 'Insert your site\'s existing newsletter form block here.' ),
				'newsletter-form-insertion-area'
			),
		)
	);
}

/**
 * Builds Explainer core-block markup.
 *
 * @return string
 */
function mumega_motion_explainer_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Direct answer' ),
			mumega_motion_editorial_pattern_paragraph( 'State the direct answer to the reader\'s question in the first sentence.' ),
			mumega_motion_editorial_pattern_heading( 'Why it matters to the primary audience' ),
			mumega_motion_editorial_pattern_paragraph( 'Explain why this matters to the primary audience for this topic.' ),
			mumega_motion_editorial_pattern_heading( 'How it works' ),
			mumega_motion_editorial_pattern_paragraph( 'Describe the underlying mechanism or process in plain language.' ),
			mumega_motion_editorial_pattern_heading( 'Concrete WordPress example' ),
			mumega_motion_editorial_pattern_paragraph( 'Provide a concrete WordPress example that illustrates the explanation.' ),
			mumega_motion_editorial_pattern_heading( 'Boundaries and common misconceptions' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add a boundary or edge case where this explanation no longer applies.',
					'Add a common misconception and correct it.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'What to do next' ),
			mumega_motion_editorial_pattern_list( array( 'Add the next recommended action for the reader.' ) ),
			mumega_motion_editorial_pattern_heading( 'Sources and updates' ),
			mumega_motion_editorial_pattern_list( array( 'Add sources, documentation, or references.' ) ),
			mumega_motion_editorial_pattern_paragraph( 'Record material updates to this explanation here.' ),
		)
	);
}

/**
 * Builds Practical Guide core-block markup.
 *
 * @return string
 */
function mumega_motion_practical_guide_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Outcome and suitability' ),
			mumega_motion_editorial_pattern_paragraph( 'State the outcome this guide produces and who it is suitable for.' ),
			mumega_motion_editorial_pattern_heading( 'Prerequisites and permissions' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add a required prerequisite.',
					'Add the WordPress role or capability needed.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Tested versions and date' ),
			mumega_motion_editorial_pattern_paragraph( 'Record the WordPress, plugin, and theme versions tested, and the test date.' ),
			mumega_motion_editorial_pattern_heading( 'Ordered procedure' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add the first step.',
					'Add the next step.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Verification steps' ),
			mumega_motion_editorial_pattern_list( array( 'Add a step that confirms the procedure succeeded.' ) ),
			mumega_motion_editorial_pattern_heading( 'Failure modes' ),
			mumega_motion_editorial_pattern_list( array( 'Add a way this procedure can fail.' ) ),
			mumega_motion_editorial_pattern_heading( 'Rollback or recovery' ),
			mumega_motion_editorial_pattern_paragraph( 'Describe how to undo this procedure or recover from a failed attempt.' ),
			mumega_motion_editorial_pattern_heading( 'Security and data considerations' ),
			mumega_motion_editorial_pattern_paragraph( 'Note security implications and any data handled by this procedure.' ),
			mumega_motion_editorial_pattern_heading( 'Sources and updates' ),
			mumega_motion_editorial_pattern_list( array( 'Add sources or documentation referenced.' ) ),
		)
	);
}

/**
 * Builds Test Report core-block markup.
 *
 * @return string
 */
function mumega_motion_test_report_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Question and hypothesis' ),
			mumega_motion_editorial_pattern_paragraph( 'State the question this test answers and the hypothesis being evaluated.' ),
			mumega_motion_editorial_pattern_heading( 'Environment and versions' ),
			mumega_motion_editorial_pattern_paragraph( 'Record the hardware, software, and versions used during testing.' ),
			mumega_motion_editorial_pattern_heading( 'Procedure' ),
			mumega_motion_editorial_pattern_paragraph( 'Describe the repeatable steps followed during the test.' ),
			mumega_motion_editorial_pattern_heading( 'Observations and artifacts' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add an observation recorded during testing.',
					'Link to a supporting artifact such as a screenshot or log.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Results' ),
			mumega_motion_editorial_pattern_paragraph( 'Report the measured results.' ),
			mumega_motion_editorial_pattern_heading( 'Failures and anomalies' ),
			mumega_motion_editorial_pattern_list( array( 'Add a failure or anomaly observed during testing.' ) ),
			mumega_motion_editorial_pattern_heading( 'Limitations and reproducibility' ),
			mumega_motion_editorial_pattern_paragraph( 'Note constraints on reproducing this test and its results.' ),
			mumega_motion_editorial_pattern_heading( 'Conclusion bounded to the evidence' ),
			mumega_motion_editorial_pattern_paragraph( 'State a conclusion that does not exceed what the evidence supports.' ),
			mumega_motion_editorial_pattern_heading( 'Sources, corrections and retest trigger' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add sources or documents referenced.',
					'Add the condition that would trigger a retest.',
				)
			),
		)
	);
}

/**
 * Builds Comparison Review core-block markup.
 *
 * @return string
 */
function mumega_motion_comparison_review_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Decision and audience' ),
			mumega_motion_editorial_pattern_paragraph( 'State the decision this review helps the reader make and who it is for.' ),
			mumega_motion_editorial_pattern_heading( 'Inclusion and exclusion criteria' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add a criterion an option must meet for inclusion.',
					'Add a criterion that excludes an option.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Tested versions, access and commercial relationships' ),
			mumega_motion_editorial_pattern_paragraph( 'Record the versions tested, how access was obtained, and any commercial relationship with each vendor. Link official documentation, pricing, terms, and changelogs for each compared product.' ),
			mumega_motion_editorial_pattern_paragraph( 'Do not label a product "tested" unless it was directly tested.' ),
			mumega_motion_editorial_pattern_heading( 'Comparable evidence table' ),
			mumega_motion_editorial_pattern_table(
				array( 'Option', 'Criterion', 'Evidence', 'Directly tested' ),
				array( 'Add an option.', 'Add the criterion assessed.', 'Add supporting evidence.', 'State whether this option was directly tested.' )
			),
			mumega_motion_editorial_pattern_heading( 'Findings by decision criterion' ),
			mumega_motion_editorial_pattern_list( array( 'Add a finding for one decision criterion.' ) ),
			mumega_motion_editorial_pattern_heading( 'Best fit and poor fit for each option' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add the best-fit use case for an option.',
					'Add the poor-fit use case for an option.',
				)
			),
			mumega_motion_editorial_pattern_heading( 'Limitations' ),
			mumega_motion_editorial_pattern_paragraph( 'Note constraints on this comparison and its scope.' ),
			mumega_motion_editorial_pattern_heading( 'Independent conclusion' ),
			mumega_motion_editorial_pattern_paragraph( 'State a conclusion independent of any commercial relationship disclosed above.' ),
			mumega_motion_editorial_pattern_heading( 'Affiliate disclosure when applicable' ),
			mumega_motion_editorial_pattern_paragraph( 'If this review contains affiliate links, disclose that relationship here; otherwise state that none exists.' ),
			mumega_motion_editorial_pattern_heading( 'Sources and update trigger' ),
			mumega_motion_editorial_pattern_list(
				array(
					'Add sources or documents referenced.',
					'Add the condition that would trigger an update to this comparison.',
				)
			),
		)
	);
}

/**
 * Builds News Briefing core-block markup.
 *
 * @return string
 */
function mumega_motion_news_briefing_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'What changed' ),
			mumega_motion_editorial_pattern_paragraph( 'State what changed in one or two sentences.' ),
			mumega_motion_editorial_pattern_heading( 'Primary announcement or artifact' ),
			mumega_motion_editorial_pattern_paragraph( 'Link to or describe the primary announcement or artifact this briefing covers.' ),
			mumega_motion_editorial_pattern_heading( 'Who is affected' ),
			mumega_motion_editorial_pattern_list( array( 'Add a group of users or sites affected.' ) ),
			mumega_motion_editorial_pattern_heading( 'Why it matters now' ),
			mumega_motion_editorial_pattern_paragraph( 'Explain why this change matters at this moment.' ),
			mumega_motion_editorial_pattern_heading( 'What readers should do' ),
			mumega_motion_editorial_pattern_list( array( 'Add a recommended action for readers.' ) ),
			mumega_motion_editorial_pattern_heading( 'What remains unknown' ),
			mumega_motion_editorial_pattern_list( array( 'Add a question that remains unanswered.' ) ),
			mumega_motion_editorial_pattern_heading( 'Connection to a durable topic page' ),
			mumega_motion_editorial_pattern_paragraph( 'Link to the durable topic page this briefing relates to.' ),
			mumega_motion_editorial_pattern_heading( 'Sources and dated update note' ),
			mumega_motion_editorial_pattern_list( array( 'Add sources for this briefing.' ) ),
			mumega_motion_editorial_pattern_paragraph( 'Record the date and nature of any update to this briefing.' ),
		)
	);
}

/**
 * Builds Analysis or Opinion core-block markup.
 *
 * @return string
 */
function mumega_motion_analysis_opinion_pattern_content() {
	return implode(
		"\n\n",
		array(
			mumega_motion_editorial_pattern_heading( 'Thesis' ),
			mumega_motion_editorial_pattern_paragraph( 'State the thesis in one or two sentences.' ),
			mumega_motion_editorial_pattern_heading( 'Evidence' ),
			mumega_motion_editorial_pattern_list( array( 'Add a piece of supporting evidence.' ) ),
			mumega_motion_editorial_pattern_heading( 'Strongest counterargument' ),
			mumega_motion_editorial_pattern_paragraph( 'State the strongest argument against this thesis.' ),
			mumega_motion_editorial_pattern_heading( 'Analysis separating facts from inference' ),
			mumega_motion_editorial_pattern_paragraph( 'Separate what is established fact from what is the author\'s inference.' ),
			mumega_motion_editorial_pattern_heading( 'Implications for the primary audience' ),
			mumega_motion_editorial_pattern_paragraph( 'Explain what this means for the primary audience.' ),
			mumega_motion_editorial_pattern_heading( 'Conditions that would change the conclusion' ),
			mumega_motion_editorial_pattern_list( array( 'Add a condition that would change this conclusion.' ) ),
			mumega_motion_editorial_pattern_heading( 'Sources and author disclosure' ),
			mumega_motion_editorial_pattern_list( array( 'Add sources referenced.' ) ),
			mumega_motion_editorial_pattern_paragraph( 'Disclose the author\'s relevant affiliations or interests.' ),
		)
	);
}

/**
 * Registers the theme's reusable editorial block patterns.
 *
 * @return void
 */
function mumega_motion_register_editorial_patterns() {
	if ( ! function_exists( 'register_block_pattern_category' ) || ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'mumega-motion',
		array(
			'label' => __( 'Mumega Motion Editorial', 'mumega-motion' ),
		)
	);

	$patterns = array(
		'mumega-motion/article-brief'        => array(
			'title'       => __( 'Article Brief', 'mumega-motion' ),
			'description' => __( 'A structured outline for a reported article.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_article_brief_pattern_content(),
		),
		'mumega-motion/test-method'          => array(
			'title'       => __( 'Test Method', 'mumega-motion' ),
			'description' => __( 'A reproducible structure for product or model testing.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_test_method_pattern_content(),
		),
		'mumega-motion/evidence-table'       => array(
			'title'       => __( 'Evidence Table', 'mumega-motion' ),
			'description' => __( 'A table for assessing claims against supporting evidence.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_editorial_pattern_table(
				array( 'Claim', 'Observation', 'Source', 'Confidence' ),
				array( 'Add a claim.', 'Add the observed evidence.', 'Add a source.', 'Add a confidence assessment.' )
			),
		),
		'mumega-motion/affiliate-disclosure' => array(
			'title'       => __( 'Affiliate Disclosure', 'mumega-motion' ),
			'description' => __( 'An editable independent-editorial affiliate disclosure.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_affiliate_disclosure_pattern_content(),
		),
		'mumega-motion/correction-note'      => array(
			'title'       => __( 'Correction Note', 'mumega-motion' ),
			'description' => __( 'A clear record of a corrected report.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_correction_note_pattern_content(),
		),
		'mumega-motion/newsletter-page'      => array(
			'title'       => __( 'Newsletter Page', 'mumega-motion' ),
			'description' => __( 'A newsletter landing-page outline with a provider form insertion area.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_newsletter_page_pattern_content(),
		),
		'mumega-motion/explainer'            => array(
			'title'       => __( 'Explainer', 'mumega-motion' ),
			'description' => __( 'A direct-answer explainer structure for a durable concept or ecosystem question.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_explainer_pattern_content(),
		),
		'mumega-motion/practical-guide'      => array(
			'title'       => __( 'Practical Guide', 'mumega-motion' ),
			'description' => __( 'A repeatable procedure with verification, failure modes, and rollback.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_practical_guide_pattern_content(),
		),
		'mumega-motion/test-report'          => array(
			'title'       => __( 'Test Report', 'mumega-motion' ),
			'description' => __( 'A conclusion-bounded report for original test evidence.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_test_report_pattern_content(),
		),
		'mumega-motion/comparison-review'    => array(
			'title'       => __( 'Comparison Review', 'mumega-motion' ),
			'description' => __( 'An evidence-based comparison or review structure for a reader decision.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_comparison_review_pattern_content(),
		),
		'mumega-motion/news-briefing'        => array(
			'title'       => __( 'News Briefing', 'mumega-motion' ),
			'description' => __( 'A dated briefing that connects a time-sensitive development to a durable topic page.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_news_briefing_pattern_content(),
		),
		'mumega-motion/analysis-opinion'     => array(
			'title'       => __( 'Analysis or Opinion', 'mumega-motion' ),
			'description' => __( 'A clearly labeled interpretation that separates fact from inference.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => mumega_motion_analysis_opinion_pattern_content(),
		),
	);

	foreach ( $patterns as $pattern_name => $pattern_properties ) {
		register_block_pattern( $pattern_name, $pattern_properties );
	}
}
add_action( 'init', 'mumega_motion_register_editorial_patterns' );
