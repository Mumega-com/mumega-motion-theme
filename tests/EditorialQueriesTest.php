<?php
/**
 * Tests for deterministic editorial post selection.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

if ( file_exists( dirname( __DIR__ ) . '/inc/editorial-queries.php' ) ) {
	require_once dirname( __DIR__ ) . '/inc/editorial-queries.php';
}

/**
 * Exercises query construction and bounded editorial discovery.
 */
final class EditorialQueriesTest extends TestCase {
	/**
	 * Resets every configurable query and navigation double.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_options']                    = array();
		$GLOBALS['mumega_motion_test_post_queries']               = array();
		$GLOBALS['mumega_motion_test_get_posts_requests']         = array();
		$GLOBALS['mumega_motion_test_categories_by_slug']         = array();
		$GLOBALS['mumega_motion_test_category_by_slug_requests']  = array();
		$GLOBALS['mumega_motion_test_categories']                 = array();
		$GLOBALS['mumega_motion_test_get_categories_requests']    = array();
		$GLOBALS['mumega_motion_test_nav_menu_items']             = array();
		$GLOBALS['mumega_motion_test_nav_menu_item_requests']     = array();
		$GLOBALS['mumega_motion_test_nav_menu_locations']         = array();
		$GLOBALS['mumega_motion_test_nav_menu_location_requests'] = array();
		$GLOBALS['mumega_motion_test_nav_menu_objects']           = array();
		$GLOBALS['mumega_motion_test_nav_menu_object_requests']   = array();
	}

	/**
	 * Keeps every shared eligibility invariant in the base post query.
	 */
	public function test_base_query_is_published_posts_only_and_rejects_passwords(): void {
		$this->assertTrue( function_exists( 'mumega_motion_base_post_query_args' ) );

		$this->assertSame(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'no_found_rows'       => true,
				'post__not_in'        => array( 4, 9 ),
				'numberposts'         => 3,
			),
			mumega_motion_base_post_query_args( array( 'numberposts' => 3 ), array( '4', 9, 4 ) )
		);
	}

	/**
	 * Keeps ordinary Releases out of a non-sticky lead fallback.
	 */
	public function test_fallback_lead_excludes_release_posts(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_lead_post' ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['releases'] = new WP_Term( array( 'term_id' => 8 ) );
		$lead = $this->post( 11 );
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $lead );
		$used_ids                                     = array();

		$this->assertSame( $lead, mumega_motion_select_lead_post( $used_ids ) );
		$this->assertSame( array( 11 ), $used_ids );
		$this->assertSame(
			array_merge(
				$this->base_args(),
				array(
					'numberposts'      => 1,
					'category__not_in' => array( 8 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Lets an explicitly sticky Release win lead placement.
	 */
	public function test_newest_sticky_post_can_override_release_exclusion(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_lead_post' ) );
		$GLOBALS['mumega_motion_test_options']['sticky_posts']        = array( 18, 17 );
		$GLOBALS['mumega_motion_test_categories_by_slug']['releases'] = new WP_Term( array( 'term_id' => 8 ) );
		$sticky                                       = $this->post( 18 );
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $sticky );
		$used_ids                                     = array( 5 );

		$this->assertSame( $sticky, mumega_motion_select_lead_post( $used_ids ) );
		$this->assertSame( array( 5, 18 ), $used_ids );
		$this->assertSame(
			array_merge(
				$this->base_args( array( 5 ) ),
				array(
					'numberposts' => 1,
					'post__in'    => array( 18, 17 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
		$this->assertArrayNotHasKey( 'category__not_in', $GLOBALS['mumega_motion_test_get_posts_requests'][0] );
	}

	/**
	 * Gives supporting stories the accumulated exclusion list and Release rule.
	 */
	public function test_supporting_posts_reuse_the_shared_exclusion_list(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_supporting_posts' ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['releases'] = new WP_Term( array( 'term_id' => 8 ) );
		$posts                                        = array( $this->post( 12 ), $this->post( 13 ) );
		$GLOBALS['mumega_motion_test_post_queries'][] = $posts;
		$used_ids                                     = array( 11 );

		$this->assertSame( $posts, mumega_motion_select_supporting_posts( $used_ids, 3 ) );
		$this->assertSame( array( 11, 12, 13 ), $used_ids );
		$this->assertSame(
			array_merge(
				$this->base_args( array( 11 ) ),
				array(
					'numberposts'      => 3,
					'category__not_in' => array( 8 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Commits a complete pair of eligible related posts as one transaction.
	 */
	public function test_related_posts_commit_exactly_two_non_release_posts(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_related_posts' ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['releases'] = new WP_Term( array( 'term_id' => 8 ) );
		$related                                      = array( $this->post( 12 ), $this->post( 13 ) );
		$GLOBALS['mumega_motion_test_post_queries'][] = $related;
		$used_ids                                     = array( 11 );

		$this->assertSame( $related, mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( array( 11, 12, 13 ), $used_ids );
		$this->assertSame(
			array_merge(
				$this->base_args( array( 11 ) ),
				array(
					'numberposts'      => 2,
					'category__not_in' => array( 8 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Lets a sparse related candidate fall through to the coverage feature.
	 */
	public function test_underfilled_related_posts_remain_available_for_coverage(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_related_posts' ) );
		$candidate                                  = $this->post( 12 );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			array( $candidate ),
			array( $candidate ),
		);
		$used_ids                                   = array( 11 );

		$this->assertSame( array(), mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( array( 11 ), $used_ids );
		$this->assertSame( $candidate, mumega_motion_select_coverage_feature( $used_ids ) );
		$this->assertSame( array( 11, 12 ), $used_ids );
		$this->assertSame( array( 11 ), $GLOBALS['mumega_motion_test_get_posts_requests'][1]['post__not_in'] );
	}

	/**
	 * Rejects a nominal pair unless both related values are valid posts.
	 */
	public function test_related_posts_reject_invalid_pairs_without_committing_ids(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_related_posts' ) );
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $this->post( 12 ), (object) array( 'ID' => 13 ) );
		$used_ids                                     = array( 11 );

		$this->assertSame( array(), mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( array( 11 ), $used_ids );
	}

	/**
	 * Treats numeric-string exclusions as already used during pair validation.
	 */
	public function test_related_posts_reject_candidates_already_present_as_numeric_strings(): void {
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $this->post( 11 ), $this->post( 12 ) );
		$used_ids                                     = array( '11' );

		$this->assertSame( array(), mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( array( '11' ), $used_ids );
	}

	/**
	 * Selects the newest unused non-Release post as the coverage feature.
	 */
	public function test_coverage_feature_excludes_releases_and_commits_its_id(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_coverage_feature' ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['releases'] = new WP_Term( array( 'term_id' => 8 ) );
		$feature                                      = $this->post( 14 );
		$GLOBALS['mumega_motion_test_post_queries'][] = array( $feature );
		$used_ids                                     = array( 11, 12, 13 );

		$this->assertSame( $feature, mumega_motion_select_coverage_feature( $used_ids ) );
		$this->assertSame( array( 11, 12, 13, 14 ), $used_ids );
		$this->assertSame(
			array_merge(
				$this->base_args( array( 11, 12, 13 ) ),
				array(
					'numberposts'      => 1,
					'category__not_in' => array( 8 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Leaves the shared transaction untouched when no coverage feature exists.
	 */
	public function test_missing_coverage_feature_does_not_mutate_used_ids(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_coverage_feature' ) );
		$GLOBALS['mumega_motion_test_post_queries'][] = array();
		$used_ids                                     = array( 11, 12, 13 );

		$this->assertNull( mumega_motion_select_coverage_feature( $used_ids ) );
		$this->assertSame( array( 11, 12, 13 ), $used_ids );
	}

	/**
	 * Reserves seven unique posts across related, feature and coverage modules.
	 */
	public function test_related_feature_and_support_posts_share_one_unique_transaction(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_related_posts' ) );
		$this->assertTrue( function_exists( 'mumega_motion_select_coverage_feature' ) );
		$related                                    = array( $this->post( 12 ), $this->post( 13 ) );
		$feature                                    = $this->post( 14 );
		$support                                    = array( $this->post( 15 ), $this->post( 16 ), $this->post( 17 ), $this->post( 18 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			$related,
			array( $feature ),
			$support,
		);
		$used_ids                                   = array( 11 );

		$this->assertSame( $related, mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( $feature, mumega_motion_select_coverage_feature( $used_ids ) );
		$this->assertSame( $support, mumega_motion_select_supporting_posts( $used_ids, 4 ) );
		$this->assertSame( array( 11, 12, 13, 14, 15, 16, 17, 18 ), $used_ids );
		$module_ids = array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			array_merge( $related, array( $feature ), $support )
		);
		$this->assertCount( 7, $module_ids );
		$this->assertCount( 7, array_unique( $module_ids ) );
		$this->assertSame( array( 11, 12, 13 ), $GLOBALS['mumega_motion_test_get_posts_requests'][1]['post__not_in'] );
		$this->assertSame( array( 11, 12, 13, 14 ), $GLOBALS['mumega_motion_test_get_posts_requests'][2]['post__not_in'] );
	}

	/**
	 * Treats only positive category taxonomy menu items as rail declarations.
	 */
	public function test_menu_categories_preserve_order_and_ignore_non_categories(): void {
		$this->assertTrue( function_exists( 'mumega_motion_menu_category_ids' ) );
		$GLOBALS['mumega_motion_test_nav_menu_locations']['primary'] = 21;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][21]          = new WP_Term( array( 'term_id' => 21 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][21]            = array(
			(object) array(
				'type'      => 'custom',
				'object'    => '',
				'object_id' => 99,
			),
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'post_tag',
				'object_id' => 4,
			),
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 7,
			),
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 0,
			),
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 3,
			),
		);

		$this->assertSame( array( 7, 3 ), mumega_motion_menu_category_ids() );
		$this->assertSame(
			array( true ),
			$GLOBALS['mumega_motion_test_nav_menu_location_requests']
		);
		$this->assertSame( array( 21 ), $GLOBALS['mumega_motion_test_nav_menu_object_requests'] );
		$this->assertSame( array( 21 ), $GLOBALS['mumega_motion_test_nav_menu_item_requests'] );
	}

	/**
	 * Returns only the first three menu rails that can fill all three slots.
	 */
	public function test_rails_require_three_eligible_posts_and_stop_at_three(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_rail_categories' ) );
		$this->configure_primary_menu( array( 2, 3, 4, 5, 6 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			array( $this->post( 20 ), $this->post( 21 ) ),
			array( $this->post( 30 ), $this->post( 31 ), $this->post( 32 ) ),
			array( $this->post( 40 ), $this->post( 41 ), $this->post( 42 ) ),
			array( $this->post( 50 ), $this->post( 51 ), $this->post( 52 ) ),
		);

		$this->assertSame( array( 3, 4, 5 ), mumega_motion_select_rail_categories( array( 10 ) ) );
		$this->assertCount( 4, $GLOBALS['mumega_motion_test_get_posts_requests'] );
		$this->assertSame( array( 5 ), $GLOBALS['mumega_motion_test_get_posts_requests'][3]['category__in'] );

		$this->assertSame( array( 10 ), $GLOBALS['mumega_motion_test_get_posts_requests'][0]['post__not_in'] );
		$this->assertSame( array( 10 ), $GLOBALS['mumega_motion_test_get_posts_requests'][1]['post__not_in'] );
		$this->assertSame( array( 10, 30, 31, 32 ), $GLOBALS['mumega_motion_test_get_posts_requests'][2]['post__not_in'] );
		$this->assertSame( array( 10, 30, 31, 32, 40, 41, 42 ), $GLOBALS['mumega_motion_test_get_posts_requests'][3]['post__not_in'] );
	}

	/**
	 * Commits only complete rails and keeps looking after overlap underfills a category.
	 */
	public function test_rail_groups_commit_only_complete_groups_and_continue_after_overlap(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_rail_groups' ) );
		$this->configure_primary_menu( array( 2, 3, 4, 5 ) );
		$first_group                                = array( $this->post( 20 ), $this->post( 21 ), $this->post( 22 ) );
		$last_group                                 = array( $this->post( 40 ), $this->post( 41 ), $this->post( 42 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			$first_group,
			array( $this->post( 30 ), $this->post( 31 ) ),
			$last_group,
		);
		$used_ids                                   = array( 10 );

		$this->assertSame(
			array(
				array(
					'term_id' => 2,
					'posts'   => $first_group,
				),
				array(
					'term_id' => 4,
					'posts'   => $last_group,
				),
			),
			mumega_motion_select_rail_groups( $used_ids, 2 )
		);
		$this->assertSame( array( 10, 20, 21, 22, 40, 41, 42 ), $used_ids );
		$this->assertSame( array( 10 ), $GLOBALS['mumega_motion_test_get_posts_requests'][0]['post__not_in'] );
		$this->assertSame( array( 10, 20, 21, 22 ), $GLOBALS['mumega_motion_test_get_posts_requests'][1]['post__not_in'] );
		$this->assertSame(
			array( 10, 20, 21, 22 ),
			$GLOBALS['mumega_motion_test_get_posts_requests'][2]['post__not_in'],
			'IDs from an incomplete rail must not be reserved.'
		);
	}

	/**
	 * Selects four complete two-post guide groups without reserving underfills.
	 */
	public function test_rail_groups_support_four_complete_two_post_groups(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_rail_groups' ) );
		$this->configure_primary_menu( array( 2, 3, 4, 5, 6 ) );
		$first_group                                = array( $this->post( 20 ), $this->post( 21 ) );
		$second_group                               = array( $this->post( 30 ), $this->post( 31 ) );
		$third_group                                = array( $this->post( 40 ), $this->post( 41 ) );
		$fourth_group                               = array( $this->post( 50 ), $this->post( 51 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			$first_group,
			array( $this->post( 29 ) ),
			$second_group,
			$third_group,
			$fourth_group,
		);
		$used_ids                                   = array( 10 );

		$this->assertSame(
			array(
				array(
					'term_id' => 2,
					'posts'   => $first_group,
				),
				array(
					'term_id' => 4,
					'posts'   => $second_group,
				),
				array(
					'term_id' => 5,
					'posts'   => $third_group,
				),
				array(
					'term_id' => 6,
					'posts'   => $fourth_group,
				),
			),
			mumega_motion_select_rail_groups( $used_ids, 4, 2 )
		);
		$this->assertSame( array( 10, 20, 21, 30, 31, 40, 41, 50, 51 ), $used_ids );

		foreach ( $GLOBALS['mumega_motion_test_get_posts_requests'] as $request ) {
			$this->assertSame( 2, $request['numberposts'] );
		}

		$this->assertSame(
			array( 10, 20, 21 ),
			$GLOBALS['mumega_motion_test_get_posts_requests'][2]['post__not_in'],
			'IDs from an incomplete two-post group must not be reserved.'
		);
	}

	/**
	 * Keeps the legacy two-argument rail call at three groups of three posts.
	 */
	public function test_rail_groups_two_argument_call_defaults_to_three_posts_per_group(): void {
		$this->configure_primary_menu( array( 2, 3, 4, 5 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			array( $this->post( 20 ), $this->post( 21 ), $this->post( 22 ) ),
			array( $this->post( 30 ), $this->post( 31 ), $this->post( 32 ) ),
			array( $this->post( 40 ), $this->post( 41 ), $this->post( 42 ) ),
		);
		$used_ids                                   = array( 10 );

		$groups = mumega_motion_select_rail_groups( $used_ids, 3 );

		$this->assertCount( 3, $groups );
		$this->assertSame( array( 3, 3, 3 ), array_column( $GLOBALS['mumega_motion_test_get_posts_requests'], 'numberposts' ) );
		$this->assertSame( array( 10, 20, 21, 22, 30, 31, 32, 40, 41, 42 ), $used_ids );
	}

	/**
	 * Treats zero rail limits and zero group sizes as no-op selections.
	 */
	public function test_rail_groups_return_early_for_zero_dimensions(): void {
		$this->configure_primary_menu( array( 2 ) );
		$used_ids = array( 10 );

		$this->assertSame( array(), mumega_motion_select_rail_groups( $used_ids, 0, 2 ) );
		$this->assertSame( array(), mumega_motion_select_rail_groups( $used_ids, 4, 0 ) );
		$this->assertSame( array( 10 ), $used_ids );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_get_posts_requests'] );
	}

	/**
	 * Gives Field Notes the IDs committed by earlier complete topic rails.
	 */
	public function test_field_notes_exclude_posts_committed_to_topic_rails(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_rail_groups' ) );
		$this->configure_primary_menu( array( 2 ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['field-notes'] = new WP_Term( array( 'term_id' => 9 ) );
		$GLOBALS['mumega_motion_test_post_queries']                      = array(
			array( $this->post( 20 ), $this->post( 21 ), $this->post( 22 ) ),
			array( $this->post( 30 ) ),
		);
		$used_ids = array( 10 );

		mumega_motion_select_rail_groups( $used_ids, 3 );
		mumega_motion_select_special_posts( 'field-notes', $used_ids, 5 );

		$this->assertSame( array( 10, 20, 21, 22 ), $GLOBALS['mumega_motion_test_get_posts_requests'][1]['post__not_in'] );
		$this->assertSame( array( 10, 20, 21, 22, 30 ), $used_ids );
	}

	/**
	 * Keeps tools and reviews distinct from every earlier homepage module.
	 */
	public function test_tool_posts_exclude_every_post_reserved_by_earlier_modules(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_related_posts' ) );
		$this->assertTrue( function_exists( 'mumega_motion_select_coverage_feature' ) );
		$this->configure_primary_menu( array( 2 ) );
		$GLOBALS['mumega_motion_test_categories_by_slug']['tools-reviews'] = new WP_Term( array( 'term_id' => 9 ) );
		$lead                                       = $this->post( 11 );
		$related                                    = array( $this->post( 12 ), $this->post( 13 ) );
		$feature                                    = $this->post( 14 );
		$support                                    = array( $this->post( 15 ), $this->post( 16 ), $this->post( 17 ), $this->post( 18 ) );
		$guides                                     = array( $this->post( 20 ), $this->post( 21 ) );
		$tools                                      = array( $this->post( 30 ), $this->post( 31 ), $this->post( 32 ) );
		$GLOBALS['mumega_motion_test_post_queries'] = array(
			array( $lead ),
			$related,
			array( $feature ),
			$support,
			$guides,
			$tools,
		);
		$used_ids                                   = array();

		$this->assertSame( $lead, mumega_motion_select_lead_post( $used_ids ) );
		$this->assertSame( $related, mumega_motion_select_related_posts( $used_ids ) );
		$this->assertSame( $feature, mumega_motion_select_coverage_feature( $used_ids ) );
		$this->assertSame( $support, mumega_motion_select_supporting_posts( $used_ids, 4 ) );
		$this->assertCount( 1, mumega_motion_select_rail_groups( $used_ids, 4, 2 ) );
		$this->assertSame( $tools, mumega_motion_select_special_posts( 'tools-reviews', $used_ids, 3 ) );

		$earlier_ids = array( 11, 12, 13, 14, 15, 16, 17, 18, 20, 21 );
		$this->assertSame( $earlier_ids, $GLOBALS['mumega_motion_test_get_posts_requests'][5]['post__not_in'] );
		$this->assertSame( array_merge( $earlier_ids, array( 30, 31, 32 ) ), $used_ids );
		$this->assertCount( count( $used_ids ), array_unique( $used_ids ) );
	}

	/**
	 * Uses the bounded non-empty category fallback without a Primary menu.
	 */
	public function test_no_menu_fallback_uses_six_largest_non_empty_categories(): void {
		$this->assertTrue( function_exists( 'mumega_motion_menu_category_ids' ) );
		$GLOBALS['mumega_motion_test_categories'] = array(
			new WP_Term( array( 'term_id' => 9 ) ),
			new WP_Term( array( 'term_id' => 4 ) ),
		);

		$this->assertSame( array( 9, 4 ), mumega_motion_menu_category_ids() );
		$this->assertSame(
			array(
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
			),
			$GLOBALS['mumega_motion_test_get_categories_requests'][0]
		);
	}

	/**
	 * Makes absent convention categories an explicit empty state.
	 */
	public function test_missing_special_category_returns_no_posts_without_querying(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_special_posts' ) );
		$used_ids = array( 5 );

		$this->assertSame( array(), mumega_motion_select_special_posts( 'test-lab', $used_ids, 1 ) );
		$this->assertSame( array( 5 ), $used_ids );
		$this->assertSame( array(), $GLOBALS['mumega_motion_test_get_posts_requests'] );
	}

	/**
	 * Finds category peers while always excluding the article being viewed.
	 */
	public function test_more_from_topic_posts_share_primary_category_and_exclude_current_post(): void {
		$this->assertTrue( function_exists( 'mumega_motion_select_more_from_topic_posts' ) );
		$posts                                        = array( $this->post( 30 ), $this->post( 31 ) );
		$GLOBALS['mumega_motion_test_post_queries'][] = $posts;

		$this->assertSame( $posts, mumega_motion_select_more_from_topic_posts( 29, 7, 3 ) );
		$this->assertSame(
			array_merge(
				$this->base_args( array( 29 ) ),
				array(
					'numberposts'  => 3,
					'category__in' => array( 7 ),
				)
			),
			$GLOBALS['mumega_motion_test_get_posts_requests'][0]
		);
	}

	/**
	 * Creates a minimal post fixture.
	 *
	 * @param int $id Post identifier.
	 * @return WP_Post
	 */
	private function post( $id ): WP_Post {
		return new WP_Post( array( 'ID' => $id ) );
	}

	/**
	 * Returns the exact shared base query with optional exclusions.
	 *
	 * @param array $used_ids Used post identifiers.
	 * @return array
	 */
	private function base_args( $used_ids = array() ): array {
		return array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'no_found_rows'       => true,
			'post__not_in'        => $used_ids,
		);
	}

	/**
	 * Configures an assigned Primary menu with category items in supplied order.
	 *
	 * @param array $term_ids Category identifiers.
	 * @return void
	 */
	private function configure_primary_menu( $term_ids ): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['primary'] = 21;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][21]          = new WP_Term( array( 'term_id' => 21 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][21]            = array_map(
			static function ( $term_id ) {
				return (object) array(
					'type'      => 'taxonomy',
					'object'    => 'category',
					'object_id' => $term_id,
				);
			},
			$term_ids
		);
	}
}
