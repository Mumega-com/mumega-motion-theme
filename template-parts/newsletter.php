<?php
/**
 * Newsletter page content embedded on the editorial homepage.
 *
 * @package Mumega_Motion
 */

$newsletter_post = isset( $args['post'] ) && $args['post'] instanceof WP_Post ? $args['post'] : null;

if ( ! $newsletter_post ) {
	return;
}

$newsletter_content = mumega_motion_render_post_content( $newsletter_post );
?>

<div class="home-newsletter__inner">
	<h2 class="home-newsletter__title"><?php echo esc_html( get_the_title( $newsletter_post ) ); ?></h2>
	<div class="home-newsletter__content">
		<?php echo $newsletter_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native block and form markup filtered by the shared content helper. ?>
	</div>
</div>
