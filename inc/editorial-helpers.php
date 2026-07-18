<?php
/**
 * Pure editorial content helpers.
 *
 * @package Mumega_Motion
 */

/**
 * Reduces post content to the words readers can see.
 *
 * @param string $content Post content.
 * @return string
 */
function mumega_motion_visible_text( $content ) {
	$content = preg_replace( '/<!--.*?-->/s', '', (string) $content );
	$content = strip_shortcodes( $content );
	$content = wp_strip_all_tags( $content, true );

	return trim( preg_replace( '/\s+/', ' ', $content ) );
}

/**
 * Estimates a reader's time to complete visible content.
 *
 * @param string $content Post content.
 * @return int
 */
function mumega_motion_reading_time( $content ) {
	$visible_text = mumega_motion_visible_text( $content );
	$word_count   = '' === $visible_text ? 0 : count( preg_split( '/\s+/', $visible_text ) );

	return max( 1, (int) ceil( $word_count / 225 ) );
}

/**
 * Trims text to the requested number of words.
 *
 * @param string $text  Text to trim.
 * @param int    $limit Maximum word count.
 * @return string
 */
function mumega_motion_trim_words( $text, $limit ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	$limit = max( 0, (int) $limit );

	if ( '' === trim( (string) $text ) || 0 === $limit ) {
		return '';
	}

	return implode( ' ', array_slice( $words, 0, $limit ) );
}

/**
 * Returns the concise summary used by editorial cards.
 *
 * @param WP_Post $post  Post to summarize.
 * @param int     $limit Maximum content words.
 * @return string
 */
function mumega_motion_card_summary( $post, $limit = 28 ) {
	$excerpt = is_object( $post ) && isset( $post->post_excerpt ) ? $post->post_excerpt : '';

	if ( '' !== trim( (string) $excerpt ) ) {
		return $excerpt;
	}

	$visible_text = mumega_motion_visible_text( isset( $post->post_content ) ? $post->post_content : '' );
	$words        = '' === $visible_text ? array() : preg_split( '/\s+/', $visible_text );
	$limit        = max( 0, (int) $limit );
	$summary      = mumega_motion_trim_words( $visible_text, $limit );

	return count( $words ) > $limit ? $summary . '…' : $summary;
}

/**
 * Selects a post category from navigation order or a deterministic fallback.
 *
 * @param int   $post_id           Post ID.
 * @param array $menu_category_ids Category IDs in Primary-menu order.
 * @return WP_Term|null
 */
function mumega_motion_primary_category( $post_id, $menu_category_ids = array() ) {
	$categories = get_the_terms( $post_id, 'category' );

	if ( empty( $categories ) || ! is_array( $categories ) ) {
		return null;
	}

	foreach ( $menu_category_ids as $menu_category_id ) {
		foreach ( $categories as $category ) {
			if ( $category instanceof WP_Term && (int) $category->term_id === (int) $menu_category_id ) {
				return $category;
			}
		}
	}

	$default_category_id = (int) get_option( 'default_category', 0 );
	$categories          = array_values(
		array_filter(
			$categories,
			static function( $category ) use ( $default_category_id ) {
				return $category instanceof WP_Term && (int) $category->term_id !== $default_category_id;
			}
		)
	);

	if ( empty( $categories ) ) {
		return null;
	}

	usort(
		$categories,
		static function( $first, $second ) {
			$name_comparison = strcasecmp( $first->name, $second->name );

			return 0 !== $name_comparison ? $name_comparison : (int) $first->term_id - (int) $second->term_id;
		}
	);

	return $categories[0];
}

/**
 * Determines whether a post was changed a meaningful time after publication.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function mumega_motion_has_meaningful_modified_date( $post_id ) {
	$published = strtotime( get_post_field( 'post_date_gmt', $post_id ) );
	$modified  = strtotime( get_post_field( 'post_modified_gmt', $post_id ) );

	return false !== $published && false !== $modified && $modified - $published >= DAY_IN_SECONDS;
}

/**
 * Resolves the optional newsletter page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_newsletter_page() {
	$pages = get_posts(
		array(
			'post_type'        => 'page',
			'name'             => 'newsletter',
			'post_status'      => 'publish',
			'has_password'     => false,
			'numberposts'      => 1,
		)
	);
	$page  = empty( $pages ) ? null : $pages[0];

	return $page instanceof WP_Post && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
}

/**
 * Resolves the optional affiliate policy page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_affiliate_policy_page() {
	$pages = get_posts(
		array(
			'post_type'        => 'page',
			'name'             => 'affiliate-disclosure',
			'post_status'      => 'publish',
			'has_password'     => false,
			'numberposts'      => 1,
		)
	);
	$page  = empty( $pages ) ? null : $pages[0];

	return $page instanceof WP_Post && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
}

/**
 * Lists tag slugs reserved for operational use rather than entity display.
 *
 * @return array
 */
function mumega_motion_operational_tag_slugs() {
	$slugs      = apply_filters( 'mumega_motion_operational_tag_slugs', array( 'affiliate' ) );
	$normalized = array();

	foreach ( (array) $slugs as $slug ) {
		if ( ! is_scalar( $slug ) ) {
			continue;
		}

		$slug = strtolower( trim( (string) $slug ) );

		if ( '' !== $slug && ! in_array( $slug, $normalized, true ) ) {
			$normalized[] = $slug;
		}
	}

	return $normalized;
}

/**
 * Returns native tags that may be presented as public entities.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function mumega_motion_public_entity_tags( $post_id ) {
	$tags             = get_the_tags( $post_id );
	$operational_tags = mumega_motion_operational_tag_slugs();

	if ( empty( $tags ) || ! is_array( $tags ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$tags,
			static function( $tag ) use ( $operational_tags ) {
				return $tag instanceof WP_Term && ! in_array( strtolower( trim( $tag->slug ) ), $operational_tags, true );
			}
		)
	);
}
