<?php
/**
 * Compact editorial content card.
 *
 * @package Mumega_Motion
 */

$card_post         = isset( $args['post'] ) && $args['post'] instanceof WP_Post ? $args['post'] : null;
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

if ( ! $card_post ) {
	return;
}

$primary_category = mumega_motion_primary_category( $card_post->ID, $menu_category_ids );
$author_name      = get_the_author_meta( 'display_name', (int) $card_post->post_author );
$summary          = mumega_motion_card_summary( $card_post, 18 );
$reading_time     = mumega_motion_reading_time( $card_post->post_content );
/* translators: %s: Estimated reading time in minutes. */
$reading_label = sprintf( _n( '%s min read', '%s min read', $reading_time, 'mumega-motion' ), number_format_i18n( $reading_time ) );
?>

<article class="content-card content-card--compact">
	<div class="content-card__content">
		<?php if ( $primary_category instanceof WP_Term ) : ?>
			<a class="content-card__category" href="<?php echo esc_url( get_category_link( $primary_category->term_id ) ); ?>">
				<?php echo esc_html( $primary_category->name ); ?>
			</a>
		<?php endif; ?>
		<h3 class="content-card__title">
			<a href="<?php echo esc_url( get_permalink( $card_post ) ); ?>">
				<?php echo esc_html( get_the_title( $card_post ) ); ?>
			</a>
		</h3>
		<?php if ( '' !== $summary ) : ?>
			<p class="content-card__summary"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
		<div class="content-card__meta">
			<?php if ( '' !== $author_name ) : ?>
				<span class="content-card__author"><?php echo esc_html( $author_name ); ?></span>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_date( 'c', $card_post ) ); ?>">
				<?php echo esc_html( get_the_date( '', $card_post ) ); ?>
			</time>
			<span><?php echo esc_html( $reading_label ); ?></span>
		</div>
	</div>
</article>
