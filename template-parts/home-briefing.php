<?php
/**
 * Editorial homepage briefing — a generic, ASTER-agnostic guide-and-post module.
 *
 * @package Mumega_Motion
 */

$briefing_post     = isset( $args['post'] ) && $args['post'] instanceof WP_Post ? $args['post'] : null;
$guide             = isset( $args['guide'] ) && $args['guide'] instanceof WP_Post ? $args['guide'] : null;
$related           = isset( $args['related'] ) ? array_slice( array_values( (array) $args['related'] ), 0, 2 ) : array();
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

$guide_name = $guide instanceof WP_Post ? trim( (string) get_the_title( $guide ) ) : '';

if ( '' !== $guide_name ) {
	/* translators: %s: Editorial guide's display name. */
	$briefing_label = sprintf( esc_html__( '%s\'s briefing', 'mumega-motion' ), esc_html( $guide_name ) );
} else {
	$briefing_label = esc_html__( 'Editor\'s briefing', 'mumega-motion' );
}
?>

<section class="home-briefing" aria-labelledby="home-briefing-heading">
	<h2 id="home-briefing-heading" class="home-briefing__label">
		<?php echo $briefing_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed from already-escaped fragments above. ?>
	</h2>

	<?php if ( $guide instanceof WP_Post ) : ?>
		<div class="home-briefing__guide">
			<?php if ( has_post_thumbnail( $guide ) ) : ?>
				<?php
				echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core responsive image markup.
					$guide,
					'medium',
					array(
						'class'    => 'home-briefing__guide-image',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				?>
			<?php endif; ?>
			<div class="home-briefing__guide-content">
				<p class="home-briefing__guide-name"><?php echo esc_html( $guide_name ); ?></p>
				<?php $guide_role = trim( (string) $guide->post_excerpt ); ?>
				<?php if ( '' !== $guide_role ) : ?>
					<p class="home-briefing__guide-role"><?php echo esc_html( $guide_role ); ?></p>
				<?php endif; ?>
				<a class="home-briefing__guide-link" href="<?php echo esc_url( get_permalink( $guide ) ); ?>">
					<?php esc_html_e( 'View editorial profile', 'mumega-motion' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $briefing_post instanceof WP_Post ) : ?>
		<?php
		$primary_category = mumega_motion_primary_category( $briefing_post->ID, $menu_category_ids );
		$summary          = mumega_motion_card_summary( $briefing_post );
		$author_name      = get_the_author_meta( 'display_name', (int) $briefing_post->post_author );
		$reading_time     = mumega_motion_reading_time( $briefing_post->post_content );
		/* translators: %s: Estimated reading time in minutes. */
		$reading_label = sprintf( _n( '%s min read', '%s min read', $reading_time, 'mumega-motion' ), number_format_i18n( $reading_time ) );
		?>
		<article class="home-briefing__post">
			<?php if ( $primary_category instanceof WP_Term ) : ?>
				<a class="content-card__category" href="<?php echo esc_url( get_category_link( $primary_category->term_id ) ); ?>">
					<?php echo esc_html( $primary_category->name ); ?>
				</a>
			<?php endif; ?>
			<h3 class="home-briefing__title">
				<a href="<?php echo esc_url( get_permalink( $briefing_post ) ); ?>">
					<?php echo esc_html( get_the_title( $briefing_post ) ); ?>
				</a>
			</h3>
			<?php if ( '' !== $summary ) : ?>
				<p class="home-briefing__summary"><?php echo esc_html( $summary ); ?></p>
			<?php endif; ?>
			<div class="content-card__meta">
				<?php if ( '' !== $author_name ) : ?>
					<span class="content-card__author"><?php echo esc_html( $author_name ); ?></span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $briefing_post ) ); ?>">
					<?php echo esc_html( get_the_date( '', $briefing_post ) ); ?>
				</time>
				<span><?php echo esc_html( $reading_label ); ?></span>
			</div>
		</article>

		<?php if ( ! empty( $related ) ) : ?>
			<ul class="home-briefing__related">
				<?php foreach ( $related as $related_post ) : ?>
					<?php if ( $related_post instanceof WP_Post ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $related_post ) ); ?>">
								<?php echo esc_html( get_the_title( $related_post ) ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty-state' ); ?>
	<?php endif; ?>
</section>
