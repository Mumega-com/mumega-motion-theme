<?php
/**
 * Reusable core-block patterns for editorial publishing.
 *
 * @package Mumega_Motion
 */

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
			'content'     => '<!-- wp:heading {"level":2} -->
<h2>Summary</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Write a concise summary of the reporting and its significance.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Key takeaways</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>Add the first key finding.</li><li>Add the second key finding.</li></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Contents</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li><a href="#methodology">Methodology</a></li><li><a href="#sources">Sources</a></li><li><a href="#corrections-updates">Corrections / updates</a></li></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2,"anchor":"methodology"} -->
<h2 class="wp-block-heading" id="methodology">Methodology</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Describe how the reporting, testing, or analysis was conducted.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2,"anchor":"sources"} -->
<h2 class="wp-block-heading" id="sources">Sources</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>Add sources, documents, or interviews.</li></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2,"anchor":"corrections-updates"} -->
<h2 class="wp-block-heading" id="corrections-updates">Corrections / updates</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Record material corrections or updates here.</p>
<!-- /wp:paragraph -->',
		),
		'mumega-motion/test-method'          => array(
			'title'       => __( 'Test Method', 'mumega-motion' ),
			'description' => __( 'A reproducible structure for product or model testing.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => '<!-- wp:heading {"level":2} -->
<h2>Question</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>State the question this test is designed to answer.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Environment</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Record the hardware, software, settings, and relevant conditions.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Models/tools tested</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>Add each model or tool and version.</li></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Procedure</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Describe the repeatable steps followed during the test.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Date</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Record the date of testing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Limitations</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Note constraints, unknowns, and conditions that may affect the results.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Results</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Report the observed results and link to supporting evidence.</p>
<!-- /wp:paragraph -->',
		),
		'mumega-motion/evidence-table'       => array(
			'title'       => __( 'Evidence Table', 'mumega-motion' ),
			'description' => __( 'A table for assessing claims against supporting evidence.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => '<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Claim</th><th>Observation</th><th>Source</th><th>Confidence</th></tr></thead><tbody><tr><td>Add a claim.</td><td>Add the observed evidence.</td><td>Add a source.</td><td>Add a confidence assessment.</td></tr></tbody></table></figure>
<!-- /wp:table -->',
		),
		'mumega-motion/affiliate-disclosure' => array(
			'title'       => __( 'Affiliate Disclosure', 'mumega-motion' ),
			'description' => __( 'An editable independent-editorial affiliate disclosure.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => '<!-- wp:group {"className":"mumega-motion-affiliate-disclosure"} -->
<div class="wp-block-group mumega-motion-affiliate-disclosure"><!-- wp:paragraph -->
<p>This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link. <a href="/affiliate-disclosure/">Read our affiliate policy</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		),
		'mumega-motion/correction-note'      => array(
			'title'       => __( 'Correction Note', 'mumega-motion' ),
			'description' => __( 'A clear record of a corrected report.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => '<!-- wp:group -->
<div class="wp-block-group"><!-- wp:heading {"level":2} -->
<h2>Correction</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Date: [Insert correction date]</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Previous claim</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>State the original claim.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Revised finding</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>State the corrected finding.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Explanation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Explain what changed and why.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		),
		'mumega-motion/newsletter-page'      => array(
			'title'       => __( 'Newsletter Page', 'mumega-motion' ),
			'description' => __( 'A newsletter landing-page outline with a provider form insertion area.', 'mumega-motion' ),
			'categories'  => array( 'mumega-motion' ),
			'content'     => '<!-- wp:heading {"level":1} -->
<h1>Newsletter</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Stay informed with reporting delivered to your inbox.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>By subscribing, you agree to receive email updates.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"newsletter-form-insertion-area"} -->
<div class="wp-block-group newsletter-form-insertion-area"><!-- wp:paragraph -->
<p>Insert your site\'s existing newsletter form block here.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		),
	);

	foreach ( $patterns as $pattern_name => $pattern_properties ) {
		register_block_pattern( $pattern_name, $pattern_properties );
	}
}
add_action( 'init', 'mumega_motion_register_editorial_patterns' );
