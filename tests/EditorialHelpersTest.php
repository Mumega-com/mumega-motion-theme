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
	/** Canonical globals changed by WP_Query::setup_postdata(). */
	private const POSTDATA_GLOBAL_NAMES = array(
		'post',
		'id',
		'authordata',
		'currentday',
		'currentmonth',
		'page',
		'pages',
		'multipage',
		'more',
		'numpages',
	);

	/**
	 * Resets configurable WordPress doubles before each helper assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']                    = array();
		$GLOBALS['mumega_motion_test_posts']                      = array();
		$GLOBALS['mumega_motion_test_generated_excerpts']         = array();
		$GLOBALS['mumega_motion_test_post_terms']                 = array();
		$GLOBALS['mumega_motion_test_post_tags']                  = array();
		$GLOBALS['mumega_motion_test_options']                    = array();
		$GLOBALS['mumega_motion_test_post_queries']               = array();
		$GLOBALS['mumega_motion_test_get_posts_requests']         = array();
		$GLOBALS['mumega_motion_test_nav_menu_items']             = array();
		$GLOBALS['mumega_motion_test_nav_menu_item_requests']     = array();
		$GLOBALS['mumega_motion_test_nav_menu_locations']         = array();
		$GLOBALS['mumega_motion_test_nav_menu_location_requests'] = array();
		$GLOBALS['mumega_motion_test_nav_menu_objects']           = array();
		$GLOBALS['mumega_motion_test_nav_menu_object_requests']   = array();
		$GLOBALS['mumega_motion_test_current_post']               = null;
		$GLOBALS['mumega_motion_test_postdata_events']            = array();
		$GLOBALS['mumega_motion_test_main_query_post']            = null;
		$GLOBALS['mumega_motion_test_setup_postdata_exceptions']  = array();
		$GLOBALS['mumega_motion_test_reset_postdata_exception']   = null;
		$this->clear_postdata_globals();
	}

	/**
	 * Filters a target post without echoing or leaking its global context.
	 */
	public function test_render_post_content_filters_target_and_restores_previous_post(): void {
		$previous = new WP_Post(
			array(
				'ID'           => 10,
				'post_content' => 'Previous content.',
			)
		);
		$target   = new WP_Post(
			array(
				'ID'           => 20,
				'post_content' => '<!-- wp:paragraph --><p>Target content.</p><!-- /wp:paragraph -->',
			)
		);

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post']    = $previous;
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post( array( 'ID' => 99 ) );
		add_filter(
			'the_content',
			static function ( $content ) use ( $target ) {
				$GLOBALS['mumega_motion_test_postdata_events'][] = array( 'filter', (int) $GLOBALS['post']->ID );
				TestCase::assertSame( $target, $GLOBALS['post'] );
				TestCase::assertSame( $target, $GLOBALS['mumega_motion_test_current_post'] );

				return '<div class="rendered-blocks">' . $content . '</div>';
			}
		);

		ob_start();
		$rendered = mumega_motion_render_post_content( $target );
		$echoed   = ob_get_clean();

		$this->assertSame( '', $echoed );
		$this->assertSame(
			'<div class="rendered-blocks"><!-- wp:paragraph --><p>Target content.</p><!-- /wp:paragraph --></div>',
			$rendered
		);
		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 20 ),
				array( 'filter', 20 ),
				array( 'reset' ),
				array( 'setup', 10 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Restores the prior post even when filtering produces no markup.
	 */
	public function test_render_post_content_restores_previous_post_after_empty_filter_result(): void {
		$previous = new WP_Post( array( 'ID' => 11 ) );
		$target   = new WP_Post(
			array(
				'ID'           => 21,
				'post_content' => 'Filtered away.',
			)
		);

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post']    = $previous;
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post( array( 'ID' => 98 ) );
		add_filter(
			'the_content',
			static function () {
				return '';
			}
		);

		$this->assertSame( '', mumega_motion_render_post_content( $target ) );
		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 21 ),
				array( 'reset' ),
				array( 'setup', 11 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Rejects invalid inputs without touching global post state.
	 */
	public function test_render_post_content_rejects_non_post_without_side_effects(): void {
		$previous = new WP_Post( array( 'ID' => 12 ) );

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                   = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post'] = $previous;
		add_filter(
			'the_content',
			static function () {
				$GLOBALS['mumega_motion_test_postdata_events'][] = array( 'filter' );

				return 'Unexpected content.';
			}
		);

		$this->assertSame( '', mumega_motion_render_post_content( (object) array( 'post_content' => 'Invalid.' ) ) );
		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_postdata_events'] );
	}

	/**
	 * Preserves an absent global post instead of inventing one during cleanup.
	 */
	public function test_render_post_content_restores_absent_global_post(): void {
		$target                                        = new WP_Post(
			array(
				'ID'           => 22,
				'post_content' => 'Target content.',
			)
		);
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post(
			array(
				'ID'           => 97,
				'post_content' => 'Main query content.',
			)
		);
		$snapshot                                      = $this->snapshot_postdata_globals();

		$this->assertArrayNotHasKey( 'post', $GLOBALS );
		$this->assertSame( 'Target content.', mumega_motion_render_post_content( $target ) );
		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame(
			array(
				array( 'setup', 22 ),
				array( 'reset' ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Preserves an exact non-post global value after rendering.
	 */
	public function test_render_post_content_restores_non_post_global_value(): void {
		$target = new WP_Post(
			array(
				'ID'           => 23,
				'post_content' => 'Target content.',
			)
		);

		$this->seed_unusual_postdata_globals( false );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post(
			array(
				'ID'           => 96,
				'post_content' => 'Main query content.',
			)
		);

		$this->assertSame( 'Target content.', mumega_motion_render_post_content( $target ) );
		$this->assert_postdata_globals_same( $snapshot );
	}

	/**
	 * Restores global context before propagating content-filter failures.
	 */
	public function test_render_post_content_restores_previous_post_when_filter_throws(): void {
		$previous = new WP_Post( array( 'ID' => 13 ) );
		$target   = new WP_Post(
			array(
				'ID'           => 24,
				'post_content' => 'Target content.',
			)
		);

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post']    = $previous;
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post( array( 'ID' => 95 ) );
		add_filter(
			'the_content',
			static function () {
				throw new RuntimeException( 'Filter failed.' );
			}
		);

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the content filter exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Filter failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 24 ),
				array( 'reset' ),
				array( 'setup', 13 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Restores absent globals after a content filter fails.
	 */
	public function test_render_post_content_restores_absent_globals_when_filter_throws(): void {
		$target          = new WP_Post(
			array(
				'ID'           => 25,
				'post_content' => 'Target content.',
			)
		);
		$main_query_post = new WP_Post( array( 'ID' => 94 ) );
		$snapshot        = $this->snapshot_postdata_globals();

		$GLOBALS['mumega_motion_test_main_query_post'] = $main_query_post;
		add_filter(
			'the_content',
			static function () {
				throw new RuntimeException( 'Absent-state filter failed.' );
			}
		);

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the content filter exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Absent-state filter failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $main_query_post, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertNotSame( $target, $GLOBALS['mumega_motion_test_current_post'] );
	}

	/**
	 * Restores non-post globals when target setup itself fails.
	 */
	public function test_render_post_content_restores_non_post_globals_when_target_setup_throws(): void {
		$target          = new WP_Post( array( 'ID' => 26 ) );
		$main_query_post = new WP_Post( array( 'ID' => 93 ) );

		$this->seed_unusual_postdata_globals( 'prior-non-post' );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_main_query_post'] = $main_query_post;
		$GLOBALS['mumega_motion_test_setup_postdata_exceptions'][26] = new RuntimeException( 'Target setup failed.' );

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the target setup exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Target setup failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $main_query_post, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 26 ),
				array( 'reset' ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Restores every snapshot before propagating a reset failure.
	 */
	public function test_render_post_content_restores_globals_when_reset_throws(): void {
		$target          = new WP_Post( array( 'ID' => 27 ) );
		$main_query_post = new WP_Post( array( 'ID' => 92 ) );

		$this->seed_unusual_postdata_globals( false );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_main_query_post'] = $main_query_post;
		$GLOBALS['mumega_motion_test_reset_postdata_exception'] = new RuntimeException( 'Reset failed.' );

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the reset exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Reset failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $main_query_post, $GLOBALS['mumega_motion_test_current_post'] );
	}

	/**
	 * Preserves the original filter failure when cleanup also fails.
	 */
	public function test_render_post_content_prefers_original_exception_over_cleanup_failure(): void {
		$previous = new WP_Post( array( 'ID' => 14 ) );
		$target   = new WP_Post( array( 'ID' => 28 ) );

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post']    = $previous;
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post( array( 'ID' => 91 ) );
		$GLOBALS['mumega_motion_test_reset_postdata_exception'] = new RuntimeException( 'Cleanup reset failed.' );
		add_filter(
			'the_content',
			static function () {
				throw new RuntimeException( 'Original filter failed.' );
			}
		);

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the original filter exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Original filter failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 28 ),
				array( 'reset' ),
				array( 'setup', 14 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Restores every snapshot when re-establishing the previous post fails.
	 */
	public function test_render_post_content_restores_globals_when_previous_setup_throws(): void {
		$previous = new WP_Post( array( 'ID' => 15 ) );
		$target   = new WP_Post( array( 'ID' => 29 ) );

		$this->seed_unusual_postdata_globals( $previous );
		$snapshot                                      = $this->snapshot_postdata_globals();
		$GLOBALS['mumega_motion_test_current_post']    = $previous;
		$GLOBALS['mumega_motion_test_main_query_post'] = new WP_Post( array( 'ID' => 90 ) );
		$GLOBALS['mumega_motion_test_setup_postdata_exceptions'][15] = new RuntimeException( 'Previous setup failed.' );

		try {
			mumega_motion_render_post_content( $target );
			$this->fail( 'Expected the previous setup exception to propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Previous setup failed.', $exception->getMessage() );
		}

		$this->assert_postdata_globals_same( $snapshot );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 29 ),
				array( 'reset' ),
				array( 'setup', 15 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
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
	 * Resolves a generic convention only through a constrained public page query.
	 */
	public function test_public_page_lookup_requires_a_published_unprotected_page(): void {
		$page = new WP_Post(
			array(
				'ID'            => 42,
				'post_status'   => 'publish',
				'post_password' => '',
			)
		);
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $page );
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

		$this->assertSame( $page, mumega_motion_public_page_by_slug( 'editorial-guide' ) );
		$this->assertNull( mumega_motion_public_page_by_slug( 'editorial-guide' ) );
		$this->assertNull( mumega_motion_public_page_by_slug( 'editorial-guide' ) );
		$this->assertSame(
			array(
				'post_type'    => 'page',
				'name'         => 'editorial-guide',
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => 1,
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Rejects malformed convention slugs without querying WordPress.
	 */
	public function test_public_page_lookup_rejects_invalid_slugs_before_querying(): void {
		foreach ( array( '', null, 12, array( 'editorial-guide' ), 'Editorial Guide', '../editorial-guide' ) as $slug ) {
			$this->assertNull( mumega_motion_public_page_by_slug( $slug ) );
		}

		$this->assertSame( array(), $GLOBALS['mumega_motion_test_get_posts_requests'] );
	}

	/**
	 * Maps the three reusable editorial page conventions to their fixed slugs.
	 */
	public function test_editorial_page_wrappers_resolve_their_convention_slugs(): void {
		$guide       = new WP_Post( array( 'ID' => 43 ) );
		$methodology = new WP_Post( array( 'ID' => 44 ) );
		$knowledge   = new WP_Post( array( 'ID' => 45 ) );

		$GLOBALS['mumega_motion_test_post_queries'] = array(
			array( $guide ),
			array( $methodology ),
			array( $knowledge ),
		);

		$this->assertSame( $guide, mumega_motion_editorial_guide_page() );
		$this->assertSame( $methodology, mumega_motion_methodology_page() );
		$this->assertSame( $knowledge, mumega_motion_knowledge_map_page() );
		$this->assertSame(
			array( 'editorial-guide', 'editorial-methodology', 'knowledge-map' ),
			array_column( $GLOBALS['mumega_motion_test_get_posts_requests'], 'name' )
		);
	}

	/**
	 * Preserves assigned audience order while exposing only raw display values.
	 */
	public function test_audience_menu_items_preserve_order_normalize_values_and_limit_to_three(): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['audiences'] = 31;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][31]            = new WP_Term( array( 'term_id' => 31 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][31]              = array(
			(object) array(
				'ID'          => 101,
				'title'       => 'Site owners & operators',
				'description' => 'Plan & govern',
				'url'         => 'https://example.test/site-owners/?from=home&kind=audience',
				'classes'     => array( 'untrusted-class' ),
			),
			(object) array(
				'title'       => 'Agencies',
				'description' => 'Deliver client systems',
				'url'         => 'https://example.test/agencies/',
			),
			(object) array(
				'title'       => 'Builders',
				'description' => 'Ship integrations',
				'url'         => 'https://example.test/builders/',
			),
			(object) array(
				'title'       => 'Content teams',
				'description' => 'Publish reliably',
				'url'         => 'https://example.test/content-teams/',
			),
		);

		$this->assertSame(
			array(
				array(
					'title'       => 'Site owners & operators',
					'description' => 'Plan & govern',
					'url'         => 'https://example.test/site-owners/?from=home&kind=audience',
				),
				array(
					'title'       => 'Agencies',
					'description' => 'Deliver client systems',
					'url'         => 'https://example.test/agencies/',
				),
				array(
					'title'       => 'Builders',
					'description' => 'Ship integrations',
					'url'         => 'https://example.test/builders/',
				),
			),
			mumega_motion_audience_menu_items( 3 )
		);
		$this->assertSame( array( true ), $GLOBALS['mumega_motion_test_nav_menu_location_requests'] );
		$this->assertSame( array( 31 ), $GLOBALS['mumega_motion_test_nav_menu_object_requests'] );
		$this->assertSame( array( 31 ), $GLOBALS['mumega_motion_test_nav_menu_item_requests'] );
	}

	/**
	 * Ignores malformed audience entries and unsafe destinations.
	 */
	public function test_audience_menu_items_reject_malformed_entries_and_urls(): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['audiences'] = 32;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][32]            = new WP_Term( array( 'term_id' => 32 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][32]              = array(
			'not-an-object',
			(object) array(
				'title'       => array( 'Malformed' ),
				'description' => 'Not scalar',
				'url'         => 'https://example.test/malformed/',
			),
			(object) array(
				'title'       => 'Empty URL',
				'description' => 'Missing destination',
				'url'         => '',
			),
			(object) array(
				'title'       => 'Unsafe URL',
				'description' => 'Invalid destination',
				'url'         => 'javascript:alert(1)',
			),
			(object) array(
				'title'       => 'Valid item',
				'description' => 'A valid destination',
				'url'         => 'https://example.test/valid/',
			),
		);

		$this->assertSame(
			array(
				array(
					'title'       => 'Valid item',
					'description' => 'A valid destination',
					'url'         => 'https://example.test/valid/',
				),
			),
			mumega_motion_audience_menu_items( 3 )
		);
	}

	/**
	 * Omits audience pathways when the menu location is not assigned.
	 */
	public function test_audience_menu_items_return_an_empty_array_when_unassigned(): void {
		$this->assertSame( array(), mumega_motion_audience_menu_items( 3 ) );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_nav_menu_object_requests'] );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_nav_menu_item_requests'] );
	}

	/**
	 * Removes every global that WordPress postdata setup may mutate.
	 */
	private function clear_postdata_globals(): void {
		foreach ( self::POSTDATA_GLOBAL_NAMES as $global_name ) {
			unset( $GLOBALS[ $global_name ] );
		}
	}

	/**
	 * Captures exact presence and values for every canonical postdata global.
	 *
	 * @return array
	 */
	private function snapshot_postdata_globals(): array {
		$snapshot = array();

		foreach ( self::POSTDATA_GLOBAL_NAMES as $global_name ) {
			$present                  = array_key_exists( $global_name, $GLOBALS );
			$snapshot[ $global_name ] = array(
				'present' => $present,
				'value'   => $present ? $GLOBALS[ $global_name ] : null,
			);
		}

		return $snapshot;
	}

	/**
	 * Seeds deliberately unusual values so Core-style cleanup cannot mask leaks.
	 *
	 * @param mixed $post_value Exact prior global post value.
	 */
	private function seed_unusual_postdata_globals( $post_value ): void {
		$GLOBALS['post']         = $post_value;
		$GLOBALS['id']           = 'prior-id';
		$GLOBALS['authordata']   = (object) array( 'marker' => 'prior-author' );
		$GLOBALS['currentday']   = array( 'prior-day' );
		$GLOBALS['currentmonth'] = false;
		$GLOBALS['page']         = -7;
		$GLOBALS['pages']        = array( 'prior', 'pages' );
		$GLOBALS['multipage']    = 'prior-multipage';
		$GLOBALS['more']         = null;
		$GLOBALS['numpages']     = 3.5;
	}

	/**
	 * Asserts exact global presence and identity/value after temporary rendering.
	 *
	 * @param array $snapshot Prior canonical postdata snapshot.
	 */
	private function assert_postdata_globals_same( $snapshot ): void {
		foreach ( self::POSTDATA_GLOBAL_NAMES as $global_name ) {
			if ( $snapshot[ $global_name ]['present'] ) {
				$this->assertArrayHasKey( $global_name, $GLOBALS );
				$this->assertSame( $snapshot[ $global_name ]['value'], $GLOBALS[ $global_name ] );
			} else {
				$this->assertArrayNotHasKey( $global_name, $GLOBALS );
			}
		}
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
