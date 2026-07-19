<?php
/**
 * Tests for native article, archive, and search templates.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/editorial-helpers.php';
require_once dirname( __DIR__ ) . '/inc/editorial-queries.php';

/**
 * Exercises the public knowledge-publication template contract.
 */
final class EditorialContentTemplatesTest extends TestCase {
	/**
	 * Resets query and post fixtures before each assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']                  = array();
		$GLOBALS['mumega_motion_test_options']                  = array();
		$GLOBALS['mumega_motion_test_posts']                    = array();
		$GLOBALS['mumega_motion_test_post_terms']               = array();
		$GLOBALS['mumega_motion_test_post_tags']                = array();
		$GLOBALS['mumega_motion_test_post_queries']             = array();
		$GLOBALS['mumega_motion_test_get_posts_requests']       = array();
		$GLOBALS['mumega_motion_test_loop_posts']               = array();
		$GLOBALS['mumega_motion_test_loop_index']               = 0;
		$GLOBALS['mumega_motion_test_current_post']             = null;
		$GLOBALS['mumega_motion_test_categories']               = array();
		$GLOBALS['mumega_motion_test_nav_menu_locations']       = array();
		$GLOBALS['mumega_motion_test_nav_menu_objects']         = array();
		$GLOBALS['mumega_motion_test_nav_menu_items']           = array();
		$GLOBALS['mumega_motion_test_queried_object']           = null;
		$GLOBALS['mumega_motion_test_search_query']             = '';
		$GLOBALS['mumega_motion_test_author_meta']              = array();
		$GLOBALS['mumega_motion_test_adjacent_post_navigation'] = '';
		$GLOBALS['post']                                        = null;
	}

	/**
	 * Gives the article title sole ownership of the document H1.
	 */
	public function test_single_has_one_content_h1(): void {
		$output = $this->render_single( $this->post( 10, 'One clear article title' ) );

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>\s*One clear article title\s*<\/h1>/s', $output );
	}

	/**
	 * Presents the chosen category as the topic and native public tags as entities.
	 */
	public function test_single_renders_primary_topic_and_public_entity_tags(): void {
		$post     = $this->post( 11, 'Entity reporting' );
		$topic    = new WP_Term(
			array(
				'term_id' => 5,
				'name'    => 'Infrastructure',
				'slug'    => 'infrastructure',
			)
		);
		$entity   = new WP_Term(
			array(
				'term_id' => 8,
				'name'    => 'OpenAI',
				'slug'    => 'openai',
			)
		);
		$operator = new WP_Term(
			array(
				'term_id' => 9,
				'name'    => 'Affiliate',
				'slug'    => 'affiliate',
			)
		);

		$GLOBALS['mumega_motion_test_post_terms'][11]['category'] = array( $topic );
		$GLOBALS['mumega_motion_test_post_tags'][11]              = array( $entity, $operator );

		$output = $this->render_single( $post );

		$this->assertMatchesRegularExpression( '/class="article-header__topic"[^>]*href="https:\/\/example\.test\/category\/5\/"[^>]*>\s*Infrastructure\s*<\/a>/s', $output );
		$this->assertMatchesRegularExpression( '/class="article-entities__link"[^>]*href="https:\/\/example\.test\/tag\/8\/"[^>]*>\s*OpenAI\s*<\/a>/s', $output );
	}

	/**
	 * Keeps the operational affiliate tag out of the public entity list.
	 */
	public function test_operational_affiliate_tag_is_not_presented_as_an_entity(): void {
		$post = $this->post( 12, 'Affiliate-tagged article' );

		$GLOBALS['mumega_motion_test_post_tags'][12] = array(
			new WP_Term(
				array(
					'term_id' => 8,
					'name'    => 'OpenAI',
					'slug'    => 'openai',
				)
			),
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);

		$output = $this->render_single( $post );

		$this->assertStringContainsString( 'https://example.test/tag/8/', $output );
		$this->assertStringNotContainsString( 'https://example.test/tag/9/', $output );
		$this->assertSame( 1, substr_count( $output, 'class="article-entities"' ) );
	}

	/**
	 * Makes native category and tag archives useful topic and entity hubs.
	 */
	public function test_archive_renders_category_and_tag_descriptions(): void {
		$category = new WP_Term(
			array(
				'term_id'     => 5,
				'name'        => 'Infrastructure',
				'slug'        => 'infrastructure',
				'taxonomy'    => 'category',
				'description' => 'Reporting on the systems beneath modern software.',
			)
		);
		$tag      = new WP_Term(
			array(
				'term_id'     => 8,
				'name'        => 'OpenAI',
				'slug'        => 'openai',
				'taxonomy'    => 'post_tag',
				'description' => 'Independent reporting about OpenAI.',
			)
		);

		$category_output = $this->render_archive( $category );
		$tag_output      = $this->render_archive( $tag );

		$this->assertStringContainsString( 'Reporting on the systems beneath modern software.', $category_output );
		$this->assertStringContainsString( 'Independent reporting about OpenAI.', $tag_output );
	}

	/**
	 * Keeps every listing bounded with native WordPress pagination.
	 */
	public function test_listing_templates_paginate(): void {
		foreach ( array( 'home.php', 'archive.php', 'search.php' ) as $template ) {
			$GLOBALS['mumega_motion_test_loop_posts']   = array( $this->post( 20, 'A listed report' ) );
			$GLOBALS['mumega_motion_test_loop_index']   = 0;
			$GLOBALS['mumega_motion_test_current_post'] = null;

			$output = $this->render_theme_file( $template );

			$this->assertStringContainsString( '<nav class="pagination"', $output, $template );
		}
	}

	/**
	 * Associates the posts-index card grid with an H2 before shared H3 cards.
	 */
	public function test_home_non_empty_output_has_h1_h2_h3_hierarchy(): void {
		$output = $this->render_non_empty_listing( 'home.php' );

		$this->assert_listing_heading_hierarchy( $output );
	}

	/**
	 * Associates the archive card grid with an H2 before shared H3 cards.
	 */
	public function test_archive_non_empty_output_has_h1_h2_h3_hierarchy(): void {
		$GLOBALS['mumega_motion_test_queried_object'] = new WP_Term(
			array(
				'term_id'  => 5,
				'name'     => 'Infrastructure',
				'slug'     => 'infrastructure',
				'taxonomy' => 'category',
			)
		);

		$output = $this->render_non_empty_listing( 'archive.php' );

		$this->assert_listing_heading_hierarchy( $output );
	}

	/**
	 * Associates the search card grid with an H2 before shared H3 cards.
	 */
	public function test_search_non_empty_output_has_h1_h2_h3_hierarchy(): void {
		$GLOBALS['mumega_motion_test_search_query'] = 'infrastructure';

		$output = $this->render_non_empty_listing( 'search.php' );

		$this->assert_listing_heading_hierarchy( $output );
	}

	/**
	 * Names category-derived recommendations with the exact editorial label.
	 */
	public function test_single_labels_category_recommendations_more_from_this_topic(): void {
		$post  = $this->post( 30, 'Current article' );
		$topic = new WP_Term(
			array(
				'term_id' => 5,
				'name'    => 'Infrastructure',
				'slug'    => 'infrastructure',
			)
		);

		$GLOBALS['mumega_motion_test_post_terms'][30]['category'] = array( $topic );
		$GLOBALS['mumega_motion_test_post_queries'][]             = array( $this->post( 31, 'Related article' ) );

		$output = $this->render_single( $post );

		$this->assertMatchesRegularExpression( '/<h2\b[^>]*>\s*More from this topic\s*<\/h2>/s', $output );
		$this->assertStringContainsString( 'Related article', $output );
		$this->assertSame( array( 30 ), $GLOBALS['mumega_motion_test_get_posts_requests'][0]['post__not_in'] );
		$this->assertSame( array( 5 ), $GLOBALS['mumega_motion_test_get_posts_requests'][0]['category__in'] );
	}

	/**
	 * Uses the exact fallback disclosure and links only a resolved policy page.
	 */
	public function test_affiliate_fallback_uses_only_the_resolved_policy_page(): void {
		$post   = $this->post( 40, 'Affiliate report' );
		$policy = $this->post( 90, 'Affiliate disclosure' );

		$GLOBALS['mumega_motion_test_post_tags'][40]  = array(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $policy );
		$GLOBALS['mumega_motion_test_post_queries'][] = array();

		$output = $this->render_single( $post );

		$this->assertStringContainsString( $this->affiliate_disclosure(), $output );
		$this->assertStringContainsString( 'href="https://example.test/?p=90"', $output );
		$this->assertStringContainsString( 'Read our affiliate disclosure', $output );

		$GLOBALS['mumega_motion_test_loop_posts']   = array();
		$GLOBALS['mumega_motion_test_loop_index']   = 0;
		$GLOBALS['mumega_motion_test_post_queries'] = array();
		$output                                     = $this->render_single( $post );

		$this->assertStringContainsString( $this->affiliate_disclosure(), $output );
		$this->assertStringNotContainsString( 'Read our affiliate disclosure', $output );
		$this->assertStringNotContainsString( 'href=', $output );
	}

	/**
	 * Avoids duplicating disclosure supplied in authored Gutenberg content.
	 */
	public function test_authored_affiliate_disclosure_suppresses_the_fallback(): void {
		$post               = $this->post( 41, 'Disclosed affiliate report' );
		$post->post_content = '<div class="wp-block-group"><p>' . $this->affiliate_disclosure() . '</p></div>';

		$GLOBALS['mumega_motion_test_post_tags'][41] = array(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);

		$output = $this->render_single( $post );

		$this->assertSame( 1, substr_count( $output, $this->affiliate_disclosure() ) );
		$this->assertStringNotContainsString( 'class="affiliate-disclosure"', $output );
	}

	/**
	 * Treats the stable pattern class as disclosure even when editors change its copy.
	 */
	public function test_affiliate_disclosure_marker_suppresses_fallback_after_copy_edits(): void {
		$post               = $this->post( 42, 'Edited disclosure report' );
		$post->post_content = '<div class="wp-block-group mumega-motion-affiliate-disclosure"><p>Partner note with locally edited copy.</p></div>';

		$GLOBALS['mumega_motion_test_post_tags'][42] = array(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);

		$output = $this->render_single( $post );

		$this->assertSame( 1, substr_count( $output, 'mumega-motion-affiliate-disclosure' ) );
		$this->assertStringNotContainsString( $this->affiliate_disclosure(), $output );
		$this->assertStringNotContainsString( 'Read our affiliate disclosure', $output );
	}

	/**
	 * Detects authored fallback copy regardless of case or whitespace changes.
	 */
	public function test_normalized_affiliate_disclosure_copy_suppresses_fallback(): void {
		$post               = $this->post( 43, 'Normalized disclosure report' );
		$post->post_content = "<p>  THIS ARTICLE MAY CONTAIN AFFILIATE LINKS.\n\tOur editorial conclusions are independent, and we may earn a commission when you purchase through a link.  </p>";

		$GLOBALS['mumega_motion_test_post_tags'][43] = array(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);

		$output = $this->render_single( $post );

		$this->assertStringNotContainsString( 'class="affiliate-disclosure', $output );
		$this->assertStringNotContainsString( 'Read our affiliate disclosure', $output );
	}

	/**
	 * Localizes fallback disclosure copy and carries the stable pattern marker.
	 */
	public function test_affiliate_fallback_is_localizable_and_uses_pattern_marker(): void {
		$source = $this->theme_source( 'single.php' );
		$post   = $this->post( 44, 'Fallback disclosure report' );

		$GLOBALS['mumega_motion_test_post_tags'][44] = array(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
		);

		$output = $this->render_single( $post );

		$this->assertStringContainsString( "__( 'This article may contain affiliate links.", $source );
		$this->assertStringContainsString( 'class="affiliate-disclosure mumega-motion-affiliate-disclosure"', $output );
	}

	/**
	 * Leaves canonical, schema, and authored navigation ownership outside the theme.
	 */
	public function test_single_adds_no_theme_schema_canonical_or_automatic_toc(): void {
		$source = strtolower( $this->theme_source( 'single.php' ) );

		$this->assertFileExists( dirname( __DIR__ ) . '/single.php' );
		$this->assertStringNotContainsString( 'application/ld+json', $source );
		$this->assertStringNotContainsString( 'rel="canonical"', $source );
		$this->assertStringNotContainsString( 'table-of-contents', $source );
	}

	/**
	 * Captures the exact print readability and external-URL contract.
	 */
	public function test_print_styles_hide_chrome_and_show_external_article_urls(): void {
		$source = $this->theme_source( 'assets/css/print.css' );

		$this->assertStringContainsString( '.primary-navigation', $source );
		$this->assertStringContainsString( '.site-header__search', $source );
		$this->assertStringContainsString( '.home-newsletter', $source );
		$this->assertStringContainsString( '.more-from-topic', $source );
		$this->assertStringContainsString( '.footer-navigation', $source );
		$this->assertStringContainsString( '.post-navigation', $source );
		$this->assertMatchesRegularExpression( '/\.article-body\s+a\[href\^="https?:\/\/"\][^{]*::after\s*\{[^}]*content:\s*"\s*\("\s*attr\(href\)\s*"\)"/s', $source );
		$this->assertMatchesRegularExpression( '/\.single-article[^}]*background:\s*#fff[^}]*color:\s*#000/s', $source );
	}

	/**
	 * Renders one populated listing template.
	 *
	 * @param string $filename Theme-relative template filename.
	 * @return string
	 */
	private function render_non_empty_listing( $filename ): string {
		$GLOBALS['mumega_motion_test_loop_posts']   = array( $this->post( 50, 'A listed report' ) );
		$GLOBALS['mumega_motion_test_loop_index']   = 0;
		$GLOBALS['mumega_motion_test_current_post'] = null;

		return $this->render_theme_file( $filename );
	}

	/**
	 * Asserts a semantic H1-H2-H3 listing outline and grid association.
	 *
	 * @param string $output Rendered listing output.
	 * @return void
	 */
	private function assert_listing_heading_hierarchy( $output ): void {
		$this->assertMatchesRegularExpression(
			'/<h1\b[^>]*>.*<\/h1>.*<h2\b[^>]*id="listing-stories-heading"[^>]*>\s*Stories\s*<\/h2>.*<div\b[^>]*class="editorial-listing__grid"[^>]*aria-labelledby="listing-stories-heading"[^>]*>.*<h3\b[^>]*>.*A listed report.*<\/h3>/s',
			$output
		);
	}

	/**
	 * Renders one article fixture through single.php.
	 *
	 * @param WP_Post $post Article fixture.
	 * @return string
	 */
	private function render_single( WP_Post $post ): string {
		$GLOBALS['post']                                  = $post;
		$GLOBALS['mumega_motion_test_posts'][ $post->ID ] = $post;
		$GLOBALS['mumega_motion_test_loop_posts']         = array( $post );
		$GLOBALS['mumega_motion_test_loop_index']         = 0;
		$GLOBALS['mumega_motion_test_current_post']       = null;

		return $this->render_theme_file( 'single.php' );
	}

	/**
	 * Renders archive.php for one native taxonomy term.
	 *
	 * @param WP_Term $term Queried term fixture.
	 * @return string
	 */
	private function render_archive( WP_Term $term ): string {
		$GLOBALS['mumega_motion_test_queried_object'] = $term;
		$GLOBALS['mumega_motion_test_loop_posts']     = array();
		$GLOBALS['mumega_motion_test_loop_index']     = 0;
		$GLOBALS['mumega_motion_test_current_post']   = null;

		return $this->render_theme_file( 'archive.php' );
	}

	/**
	 * Captures a theme template if the contract file exists.
	 *
	 * @param string $filename Theme-relative filename.
	 * @return string
	 */
	private function render_theme_file( $filename ): string {
		$path = dirname( __DIR__ ) . '/' . $filename;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		require $path;

		return (string) ob_get_clean();
	}

	/**
	 * Reads one theme source file without warning when it is absent.
	 *
	 * @param string $filename Theme-relative filename.
	 * @return string
	 */
	private function theme_source( $filename ): string {
		$path = dirname( __DIR__ ) . '/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local contract fixture.
		return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Creates a complete post fixture for rendered template output.
	 *
	 * @param int    $id    Post identifier.
	 * @param string $title Post title.
	 * @return WP_Post
	 */
	private function post( $id, $title ): WP_Post {
		return new WP_Post(
			array(
				'ID'                => $id,
				'post_title'        => $title,
				'post_author'       => 1,
				'post_content'      => '<p>Useful Gutenberg article content.</p>',
				'post_excerpt'      => 'A concise manual excerpt.',
				'post_date'         => 'July 18, 2026',
				'post_date_gmt'     => '2026-07-18 12:00:00',
				'post_modified_gmt' => '2026-07-18 12:00:00',
			)
		);
	}

	/**
	 * Returns the required authored/fallback disclosure copy.
	 *
	 * @return string
	 */
	private function affiliate_disclosure(): string {
		return 'This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.';
	}
}
