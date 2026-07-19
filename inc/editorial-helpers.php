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
 * Renders one post's block content without leaking its global post context.
 *
 * @param WP_Post $post Post whose content should be rendered.
 * @return string
 */
function mumega_motion_render_post_content( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$had_previous_post = array_key_exists( 'post', $GLOBALS );
	$previous_post     = $had_previous_post ? $GLOBALS['post'] : null;

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The content filter requires the target as WordPress's current global post.
	$GLOBALS['post'] = $post;
	setup_postdata( $post );

	try {
		return (string) apply_filters( 'the_content', $post->post_content );
	} finally {
		wp_reset_postdata();

		if ( $previous_post instanceof WP_Post ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the exact prior post after the temporary content context.
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );
		} elseif ( $had_previous_post ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the exact non-post value that existed before rendering.
			$GLOBALS['post'] = $previous_post;
		} else {
			unset( $GLOBALS['post'] );
		}
	}
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
			static function ( $category ) use ( $default_category_id ) {
				return $category instanceof WP_Term && (int) $category->term_id !== $default_category_id;
			}
		)
	);

	if ( empty( $categories ) ) {
		return null;
	}

	usort(
		$categories,
		static function ( $first, $second ) {
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
 * Resolves a published, unprotected page by its canonical slug.
 *
 * @param string $slug Canonical page slug.
 * @return WP_Post|null
 */
function mumega_motion_public_page_by_slug( $slug ) {
	if ( ! is_string( $slug ) || sanitize_title( $slug ) !== $slug || '' === $slug ) {
		return null;
	}

	$pages = get_posts(
		array(
			'post_type'    => 'page',
			'name'         => $slug,
			'post_status'  => 'publish',
			'has_password' => false,
			'numberposts'  => 1,
		)
	);
	$page  = empty( $pages ) ? null : $pages[0];

	return $page instanceof WP_Post && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
}

/**
 * Resolves the optional newsletter page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_newsletter_page() {
	return mumega_motion_public_page_by_slug( 'newsletter' );
}

/**
 * Resolves the optional affiliate policy page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_affiliate_policy_page() {
	return mumega_motion_public_page_by_slug( 'affiliate-disclosure' );
}

/**
 * Resolves the optional editorial guide page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_editorial_guide_page() {
	return mumega_motion_public_page_by_slug( 'editorial-guide' );
}

/**
 * Resolves the optional editorial methodology page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_methodology_page() {
	return mumega_motion_public_page_by_slug( 'editorial-methodology' );
}

/**
 * Resolves the optional knowledge map page convention.
 *
 * @return WP_Post|null
 */
function mumega_motion_knowledge_map_page() {
	return mumega_motion_public_page_by_slug( 'knowledge-map' );
}

/**
 * Returns normalized audience pathways from the assigned menu.
 *
 * Values remain unescaped so each rendering context can escape them at output.
 *
 * @param int $limit Maximum number of pathways to return, capped at three.
 * @return array
 */
function mumega_motion_audience_menu_items( $limit = 3 ) {
	$limit = min( 3, max( 0, (int) $limit ) );

	if ( 0 === $limit ) {
		return array();
	}

	$locations = get_nav_menu_locations();
	$menu_id   = is_array( $locations ) && isset( $locations['audiences'] ) ? (int) $locations['audiences'] : 0;
	$menu      = $menu_id > 0 ? wp_get_nav_menu_object( $menu_id ) : false;

	if ( ! $menu instanceof WP_Term ) {
		return array();
	}

	$normalized = array();
	$items      = wp_get_nav_menu_items( (int) $menu->term_id );

	foreach ( (array) $items as $item ) {
		if ( count( $normalized ) >= $limit ) {
			break;
		}

		if (
			! is_object( $item ) ||
			! isset( $item->title, $item->description, $item->url ) ||
			! is_scalar( $item->title ) ||
			! is_scalar( $item->description ) ||
			! is_scalar( $item->url )
		) {
			continue;
		}

		$url = (string) $item->url;

		if ( '' === $url || false === wp_http_validate_url( $url ) ) {
			continue;
		}

		$normalized[] = array(
			'title'       => (string) $item->title,
			'description' => (string) $item->description,
			'url'         => $url,
		);
	}

	return $normalized;
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
			static function ( $tag ) use ( $operational_tags ) {
				return $tag instanceof WP_Term && ! in_array( strtolower( trim( $tag->slug ) ), $operational_tags, true );
			}
		)
	);
}
