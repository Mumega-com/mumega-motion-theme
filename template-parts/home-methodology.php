<?php
/**
 * Editorial homepage methodology — links to the trust page instead of embedding it,
 * and shows the theme's fixed editorial test vocabulary. Never implies a per-article
 * guarantee: individual posts only carry a "tested" claim when their own visible
 * methodology says so.
 *
 * @package Mumega_Motion
 */

$methodology_page = isset( $args['page'] ) && $args['page'] instanceof WP_Post ? $args['page'] : null;

if ( ! $methodology_page ) {
	return;
}

$excerpt = trim( (string) $methodology_page->post_excerpt );
?>

<section class="home-methodology">
	<?php
	get_template_part(
		'template-parts/section-heading',
		null,
		array(
			'title' => get_the_title( $methodology_page ),
			'url'   => get_permalink( $methodology_page ),
		)
	);
	?>
	<?php if ( '' !== $excerpt ) : ?>
		<p class="home-methodology__excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php endif; ?>
	<ol class="home-methodology__steps">
		<li class="home-methodology__step"><?php esc_html_e( 'Install', 'mumega-motion' ); ?></li>
		<li class="home-methodology__step"><?php esc_html_e( 'Connect', 'mumega-motion' ); ?></li>
		<li class="home-methodology__step"><?php esc_html_e( 'Verify', 'mumega-motion' ); ?></li>
		<li class="home-methodology__step"><?php esc_html_e( 'Recover', 'mumega-motion' ); ?></li>
	</ol>
	<p class="home-methodology__marker"><?php esc_html_e( 'Tested on WordPress', 'mumega-motion' ); ?></p>
</section>
