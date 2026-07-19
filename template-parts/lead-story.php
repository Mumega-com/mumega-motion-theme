<?php
/**
 * Editorial homepage lead story.
 *
 * @package Mumega_Motion
 */

$lead_post         = isset( $args['post'] ) && $args['post'] instanceof WP_Post ? $args['post'] : null;
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

if ( ! $lead_post ) {
	return;
}

$primary_category = mumega_motion_primary_category( $lead_post->ID, $menu_category_ids );
$author_name      = get_the_author_meta( 'display_name', (int) $lead_post->post_author );
$summary          = mumega_motion_card_summary( $lead_post );
$reading_time     = mumega_motion_reading_time( $lead_post->post_content );
/* translators: %s: Estimated reading time in minutes. */
$reading_label = sprintf( _n( '%s min read', '%s min read', $reading_time, 'mumega-motion' ), number_format_i18n( $reading_time ) );
?>

<article class="lead-story">
	<?php if ( has_post_thumbnail( $lead_post ) ) : ?>
		<a class="lead-story__media" href="<?php echo esc_url( get_permalink( $lead_post ) ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core responsive image markup.
				$lead_post,
				'full',
				array(
					'class'         => 'lead-story__image',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'decoding'      => 'async',
				)
			);
			?>
		</a>
	<?php endif; ?>
	<div class="lead-story__content">
		<?php if ( $primary_category instanceof WP_Term ) : ?>
			<a class="content-card__category" href="<?php echo esc_url( get_category_link( $primary_category->term_id ) ); ?>">
				<?php echo esc_html( $primary_category->name ); ?>
			</a>
		<?php endif; ?>
		<h1 class="lead-story__title">
			<a href="<?php echo esc_url( get_permalink( $lead_post ) ); ?>">
				<?php echo esc_html( get_the_title( $lead_post ) ); ?>
			</a>
		</h1>
		<?php if ( '' !== $summary ) : ?>
			<p class="lead-story__summary"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
		<div class="content-card__meta">
			<?php if ( '' !== $author_name ) : ?>
				<span class="content-card__author"><?php echo esc_html( $author_name ); ?></span>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_date( 'c', $lead_post ) ); ?>">
				<?php echo esc_html( get_the_date( '', $lead_post ) ); ?>
			</time>
			<span><?php echo esc_html( $reading_label ); ?></span>
		</div>
	</div>
</article>
