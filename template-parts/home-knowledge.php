<?php
/**
 * Editorial homepage knowledge continuity — promotes an authored knowledge-map page.
 * The featured media is an authored topic-map illustration; it does not claim that
 * WordPress stores or computes graph edges.
 *
 * @package Mumega_Motion
 */

$knowledge_page = isset( $args['page'] ) && $args['page'] instanceof WP_Post ? $args['page'] : null;

if ( ! $knowledge_page ) {
	return;
}

$excerpt = trim( (string) $knowledge_page->post_excerpt );
?>

<section class="home-knowledge">
	<?php if ( has_post_thumbnail( $knowledge_page ) ) : ?>
		<a class="home-knowledge__media" href="<?php echo esc_url( get_permalink( $knowledge_page ) ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core responsive image markup.
				$knowledge_page,
				'large',
				array(
					'class'    => 'home-knowledge__image',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			?>
		</a>
	<?php endif; ?>
	<div class="home-knowledge__content">
		<?php
		get_template_part(
			'template-parts/section-heading',
			null,
			array(
				'title' => get_the_title( $knowledge_page ),
				'url'   => get_permalink( $knowledge_page ),
			)
		);
		?>
		<?php if ( '' !== $excerpt ) : ?>
			<p class="home-knowledge__excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
	</div>
</section>
