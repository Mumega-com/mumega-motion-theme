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

global $post;

$previous_post = $post;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The content filter requires the newsletter as WordPress's current global post.
$post = $newsletter_post;
setup_postdata( $post );
?>

<div class="home-newsletter__inner">
	<h2 class="home-newsletter__title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
	<div class="home-newsletter__content">
		<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native block and form markup. ?>
	</div>
</div>

<?php
wp_reset_postdata();
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Explicitly restore the homepage post after the temporary newsletter context.
$post = $previous_post;

if ( $previous_post instanceof WP_Post ) {
	setup_postdata( $previous_post );
}
