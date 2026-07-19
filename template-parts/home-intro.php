<?php
/**
 * Editorial homepage intro — the page-owned hero.
 *
 * @package Mumega_Motion
 */

$intro_page = isset( $args['page'] ) && $args['page'] instanceof WP_Post ? $args['page'] : null;

if ( ! $intro_page ) {
	return;
}

$excerpt = trim( (string) $intro_page->post_excerpt );
$content = mumega_motion_render_post_content( $intro_page );
?>

<section class="home-intro">
	<h1 class="home-intro__title"><?php echo esc_html( get_the_title( $intro_page ) ); ?></h1>
	<?php if ( '' !== $excerpt ) : ?>
		<p class="home-intro__excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $content ) : ?>
		<div class="home-intro__content">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native block markup filtered by mumega_motion_render_post_content(), the documented content-rendering boundary. ?>
		</div>
	<?php endif; ?>
</section>
