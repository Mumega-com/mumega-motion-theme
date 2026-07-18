<?php
/**
 * Deterministic editorial post selection.
 *
 * @package Mumega_Motion
 */

/**
 * Builds the eligibility constraints shared by editorial post queries.
 *
 * @param array $overrides Additional query arguments.
 * @param array $used_ids  Post identifiers already rendered.
 * @return array
 */
function mumega_motion_base_post_query_args( $overrides = array(), $used_ids = array() ) {
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'has_password'        => false,
		'ignore_sticky_posts' => true,
		'orderby'             => array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		),
		'no_found_rows'       => true,
		'post__not_in'        => array_values( array_unique( array_map( 'intval', $used_ids ) ) ),
	);

	return array_merge( $args, (array) $overrides );
}

/**
 * Gets the configured Releases category identifier when it exists.
 *
 * @return int
 */
function mumega_motion_releases_category_id() {
	$category = get_category_by_slug( 'releases' );

	return $category instanceof WP_Term ? (int) $category->term_id : 0;
}

/**
 * Appends queried post identifiers to the shared exclusion list.
 *
 * @param array $posts    Selected post values.
 * @param array $used_ids Post identifiers already rendered.
 * @return array
 */
function mumega_motion_add_used_post_ids( $posts, &$used_ids ) {
	foreach ( (array) $posts as $post ) {
		if ( $post instanceof WP_Post && (int) $post->ID > 0 ) {
			$used_ids[] = (int) $post->ID;
		}
	}

	return (array) $posts;
}

/**
 * Returns category IDs from the assigned Primary menu or the category fallback.
 *
 * @return array
 */
function mumega_motion_menu_category_ids() {
	$locations = get_nav_menu_locations();
	$menu_id   = is_array( $locations ) && isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
	$menu      = $menu_id > 0 ? wp_get_nav_menu_object( $menu_id ) : false;

	if ( ! $menu instanceof WP_Term ) {
		$categories = get_categories(
			array(
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $category ) {
							return $category instanceof WP_Term ? (int) $category->term_id : 0;
						},
						(array) $categories
					),
					static function ( $term_id ) {
						return $term_id > 0;
					}
				)
			)
		);
	}

	$category_ids = array();
	$items        = wp_get_nav_menu_items( (int) $menu->term_id );

	foreach ( (array) $items as $item ) {
		if ( ! is_object( $item ) || 'taxonomy' !== $item->type || 'category' !== $item->object || (int) $item->object_id <= 0 ) {
			continue;
		}

		$term_id = (int) $item->object_id;

		if ( ! in_array( $term_id, $category_ids, true ) ) {
			$category_ids[] = $term_id;
		}
	}

	return $category_ids;
}

/**
 * Selects the editorial lead, favouring the newest published sticky post.
 *
 * @param array $used_ids Post identifiers already rendered.
 * @return WP_Post|null
 */
function mumega_motion_select_lead_post( &$used_ids ) {
	$sticky_posts = array_values( array_unique( array_filter( array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ) ) ) );

	if ( ! empty( $sticky_posts ) ) {
		$posts = get_posts(
			mumega_motion_base_post_query_args(
				array(
					'numberposts' => 1,
					'post__in'    => $sticky_posts,
				),
				$used_ids
			)
		);

		if ( ! empty( $posts ) && $posts[0] instanceof WP_Post ) {
			mumega_motion_add_used_post_ids( array( $posts[0] ), $used_ids );

			return $posts[0];
		}
	}

	$overrides  = array( 'numberposts' => 1 );
	$release_id = mumega_motion_releases_category_id();

	if ( $release_id > 0 ) {
		$overrides['category__not_in'] = array( $release_id );
	}

	$posts = get_posts( mumega_motion_base_post_query_args( $overrides, $used_ids ) );

	if ( empty( $posts ) || ! $posts[0] instanceof WP_Post ) {
		return null;
	}

	mumega_motion_add_used_post_ids( array( $posts[0] ), $used_ids );

	return $posts[0];
}

/**
 * Selects newest supporting stories while preserving lead exclusions.
 *
 * @param array $used_ids Post identifiers already rendered.
 * @param int   $limit    Maximum number of supporting posts.
 * @return array
 */
function mumega_motion_select_supporting_posts( &$used_ids, $limit = 3 ) {
	$limit = max( 0, (int) $limit );

	if ( 0 === $limit ) {
		return array();
	}

	$overrides  = array( 'numberposts' => $limit );
	$release_id = mumega_motion_releases_category_id();

	if ( $release_id > 0 ) {
		$overrides['category__not_in'] = array( $release_id );
	}

	return mumega_motion_add_used_post_ids( get_posts( mumega_motion_base_post_query_args( $overrides, $used_ids ) ), $used_ids );
}

/**
 * Selects categories able to fill a complete three-post topic rail.
 *
 * @param array $used_ids Post identifiers already rendered.
 * @param int   $limit    Maximum number of rails.
 * @return array
 */
function mumega_motion_select_rail_categories( $used_ids, $limit = 3 ) {
	$limit = max( 0, (int) $limit );

	if ( 0 === $limit ) {
		return array();
	}

	$rail_categories = array();

	foreach ( mumega_motion_menu_category_ids() as $term_id ) {
		$dry_used_ids = $used_ids;
		$posts        = mumega_motion_select_category_posts( $term_id, $dry_used_ids, 3 );

		if ( 3 !== count( $posts ) ) {
			continue;
		}

		$rail_categories[] = (int) $term_id;

		if ( count( $rail_categories ) >= $limit ) {
			break;
		}
	}

	return $rail_categories;
}

/**
 * Selects eligible posts assigned to one category.
 *
 * @param int   $term_id  Category identifier.
 * @param array $used_ids Post identifiers already rendered.
 * @param int   $limit    Maximum number of posts.
 * @return array
 */
function mumega_motion_select_category_posts( $term_id, &$used_ids, $limit = 3 ) {
	$term_id = (int) $term_id;
	$limit   = max( 0, (int) $limit );

	if ( $term_id <= 0 || 0 === $limit ) {
		return array();
	}

	return mumega_motion_add_used_post_ids(
		get_posts(
			mumega_motion_base_post_query_args(
				array(
					'numberposts'  => $limit,
					'category__in' => array( $term_id ),
				),
				$used_ids
			)
		),
		$used_ids
	);
}

/**
 * Selects posts from an optional category convention.
 *
 * @param string $slug     Category slug.
 * @param array  $used_ids Post identifiers already rendered.
 * @param int    $limit    Maximum number of posts.
 * @return array
 */
function mumega_motion_select_special_posts( $slug, &$used_ids, $limit ) {
	$category = get_category_by_slug( $slug );

	if ( ! $category instanceof WP_Term ) {
		return array();
	}

	return mumega_motion_select_category_posts( (int) $category->term_id, $used_ids, $limit );
}

/**
 * Selects more posts from the current article's chosen category.
 *
 * @param int $post_id Current post identifier.
 * @param int $term_id Chosen category identifier.
 * @param int $limit   Maximum number of posts.
 * @return array
 */
function mumega_motion_select_more_from_topic_posts( $post_id, $term_id, $limit = 3 ) {
	$post_id = (int) $post_id;
	$term_id = (int) $term_id;
	$limit   = max( 0, (int) $limit );

	if ( $post_id <= 0 || $term_id <= 0 || 0 === $limit ) {
		return array();
	}

	return get_posts(
		mumega_motion_base_post_query_args(
			array(
				'numberposts'  => $limit,
				'category__in' => array( $term_id ),
			),
			array( $post_id )
		)
	);
}
