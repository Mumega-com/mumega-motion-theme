<?php
/**
 * Tests for editorial content helpers.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-helpers.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-helpers.php';
}

/**
 * Exercises pure editorial display decisions without a WordPress runtime.
 */
final class EditorialHelpersTest extends TestCase {
	/**
	 * Resets configurable WordPress doubles before each helper assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']            = array();
		$GLOBALS['mumega_motion_test_posts']              = array();
		$GLOBALS['mumega_motion_test_generated_excerpts'] = array();
		$GLOBALS['mumega_motion_test_post_terms']         = array();
		$GLOBALS['mumega_motion_test_post_tags']          = array();
		$GLOBALS['mumega_motion_test_options']            = array();
		$GLOBALS['mumega_motion_test_post_queries']       = array();
		$GLOBALS['mumega_motion_test_get_posts_requests'] = array();
	}

	/**
	 * Keeps even one visible word at the minimum estimated duration.
	 */
	public function test_reading_time_is_at_least_one_minute(): void {
		$this->assertSame( 1, mumega_motion_reading_time( 'word' ) );
	}

	/**
	 * Uses 225 visible words per minute and rounds partial minutes upward.
	 */
	public function test_reading_time_rounds_visible_words_up_at_225_words_per_minute(): void {
		$this->assertSame( 1, mumega_motion_reading_time( $this->words( 225 ) ) );
		$this->assertSame( 2, mumega_motion_reading_time( $this->words( 226 ) ) );
	}

	/**
	 * Leaves a non-empty author supplied excerpt untouched.
	 */
	public function test_card_summary_prefers_a_manual_excerpt(): void {
		$post = new WP_Post(
			array(
				'post_excerpt' => 'Author-provided summary.',
				'post_content' => '<!-- wp:paragraph --><p>Ignored <strong>HTML</strong> [example] content.</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 'Author-provided summary.', mumega_motion_card_summary( $post ) );
	}

	/**
	 * Builds a visible-text summary and marks it only when trimming content.
	 */
	public function test_card_summary_uses_first_28_visible_content_words(): void {
		$post = new WP_Post(
			array(
				'post_content' => '<!-- wp:paragraph --><p>' . $this->words( 14 ) . ' <strong>' . $this->words( 14, 15 ) . '</strong> [gallery ids="1"] ' . $this->words( 1, 29 ) . '</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( $this->words( 28 ) . '…', mumega_motion_card_summary( $post ) );
	}

	/**
	 * Ignores a generated display excerpt when no manual excerpt was authored.
	 */
	public function test_card_summary_uses_content_when_wordpress_generates_an_excerpt(): void {
		$post = new WP_Post(
			array(
				'ID'           => 9,
				'post_excerpt' => '',
				'post_content' => $this->words( 29 ),
			)
		);
		$GLOBALS['mumega_motion_test_generated_excerpts'][9] = 'Generated display excerpt.';

		$this->assertSame( $this->words( 28 ) . '…', mumega_motion_card_summary( $post ) );
	}

	/**
	 * Prioritizes an assigned category according to Primary-menu category order.
	 */
	public function test_primary_category_prefers_first_assigned_category_in_menu_order(): void {
		$GLOBALS['mumega_motion_test_post_terms'][10]['category'] = array(
			new WP_Term(
				array(
					'term_id' => 2,
					'name'    => 'Guides',
					'slug'    => 'guides',
				)
			),
			new WP_Term(
				array(
					'term_id' => 3,
					'name'    => 'AI',
					'slug'    => 'ai',
				)
			),
		);

		$category = mumega_motion_primary_category( 10, array( 3, 2 ) );

		$this->assertSame( 3, $category->term_id );
	}

	/**
	 * Uses alphabetical category name order after removing the default category.
	 */
	public function test_primary_category_falls_back_to_alphabetical_non_default_category(): void {
		$GLOBALS['mumega_motion_test_options']['default_category'] = 1;
		$GLOBALS['mumega_motion_test_post_terms'][11]['category']  = array(
			new WP_Term(
				array(
					'term_id' => 5,
					'name'    => 'Zebra',
					'slug'    => 'zebra',
				)
			),
			new WP_Term(
				array(
					'term_id' => 1,
					'name'    => 'Uncategorized',
					'slug'    => 'uncategorized',
				)
			),
			new WP_Term(
				array(
					'term_id' => 6,
					'name'    => 'Alpha',
					'slug'    => 'alpha',
				)
			),
		);

		$category = mumega_motion_primary_category( 11 );

		$this->assertSame( 6, $category->term_id );
	}

	/**
	 * Has no replacement value when a post has no assigned category.
	 */
	public function test_primary_category_returns_null_without_categories(): void {
		$this->assertNull( mumega_motion_primary_category( 12 ) );
	}

	/**
	 * Shows a modified date only once it differs from publication by a full day.
	 */
	public function test_modified_date_requires_a_24_hour_difference(): void {
		$GLOBALS['mumega_motion_test_posts'][20] = new WP_Post(
			array(
				'ID'                => 20,
				'post_date_gmt'     => '2026-01-01 00:00:00',
				'post_modified_gmt' => '2026-01-01 23:59:59',
			)
		);
		$this->assertFalse( mumega_motion_has_meaningful_modified_date( 20 ) );

		$GLOBALS['mumega_motion_test_posts'][20]->post_modified_gmt = '2026-01-02 00:00:00';
		$this->assertTrue( mumega_motion_has_meaningful_modified_date( 20 ) );
	}

	/**
	 * Omits operational tags from public entity presentation.
	 */
	public function test_public_entity_tags_exclude_reserved_operational_tags(): void {
		$GLOBALS['mumega_motion_test_post_tags'][30] = array(
			new WP_Term(
				array(
					'term_id' => 1,
					'name'    => 'Affiliate',
					'slug'    => 'affiliate',
				)
			),
			new WP_Term(
				array(
					'term_id' => 2,
					'name'    => 'OpenAI',
					'slug'    => 'openai',
				)
			),
		);

		$tags = mumega_motion_public_entity_tags( 30 );

		$this->assertCount( 1, $tags );
		$this->assertSame( 'openai', $tags[0]->slug );
	}

	/**
	 * Allows sites to add operational tags while preserving normalized slugs.
	 */
	public function test_operational_tag_slug_filter_can_extend_the_reserved_list(): void {
		add_filter(
			'mumega_motion_operational_tag_slugs',
			static function ( $slugs ) {
				return array_merge( $slugs, array( ' sponsored ', 'affiliate', '' ) );
			}
		);
		$GLOBALS['mumega_motion_test_post_tags'][31] = array(
			new WP_Term(
				array(
					'term_id' => 3,
					'name'    => 'Sponsored',
					'slug'    => 'sponsored',
				)
			),
			new WP_Term(
				array(
					'term_id' => 4,
					'name'    => 'Protocol',
					'slug'    => 'protocol',
				)
			),
		);

		$this->assertSame( array( 'affiliate', 'sponsored' ), mumega_motion_operational_tag_slugs() );
		$this->assertSame(
			array( 'protocol' ),
			array_map(
				static function ( $tag ) {
					return $tag->slug;
				},
				mumega_motion_public_entity_tags( 31 )
			)
		);
	}

	/**
	 * Resolves a policy only through the constrained public page convention.
	 */
	public function test_affiliate_policy_lookup_requires_a_published_unprotected_page(): void {
		$policy                                       = new WP_Post(
			array(
				'ID'            => 40,
				'post_status'   => 'publish',
				'post_password' => '',
			)
		);
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $policy );

		$this->assertSame( $policy, mumega_motion_affiliate_policy_page() );
		$this->assertSame(
			array(
				'post_type'    => 'page',
				'name'         => 'affiliate-disclosure',
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => 1,
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);

		$this->assertNull( mumega_motion_affiliate_policy_page() );
	}

	/**
	 * Resolves the newsletter only through its constrained public page query.
	 */
	public function test_newsletter_page_lookup_requires_a_published_unprotected_page(): void {
		$newsletter                                   = new WP_Post(
			array(
				'ID'            => 41,
				'post_status'   => 'publish',
				'post_password' => '',
			)
		);
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $newsletter );

		$this->assertSame( $newsletter, mumega_motion_newsletter_page() );
		$this->assertSame(
			array(
				'post_type'    => 'page',
				'name'         => 'newsletter',
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => 1,
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Rejects convention pages if a query result is not public and unprotected.
	 */
	public function test_convention_page_lookups_reject_draft_and_password_protected_posts(): void {
		$GLOBALS['mumega_motion_test_post_queries'][] = array(
			new WP_Post(
				array(
					'post_status'   => 'draft',
					'post_password' => '',
				)
			),
		);
		$GLOBALS['mumega_motion_test_post_queries'][] = array(
			new WP_Post(
				array(
					'post_status'   => 'publish',
					'post_password' => 'secret',
				)
			),
		);

		$this->assertNull( mumega_motion_newsletter_page() );
		$this->assertNull( mumega_motion_affiliate_policy_page() );
	}

	/**
	 * Builds a deterministic sequence of visible word fixtures.
	 *
	 * @param int $count Number of words.
	 * @param int $start First numeric suffix.
	 * @return string
	 */
	private function words( $count, $start = 1 ): string {
		$words = array();

		for ( $number = $start; $number < $start + $count; ++$number ) {
			$words[] = 'word' . $number;
		}

		return implode( ' ', $words );
	}
}
