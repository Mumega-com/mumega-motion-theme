<?php
/**
 * Not-found template.
 *
 * @package Mumega_Motion
 */

$recovery_categories = get_categories(
	array(
		'hide_empty' => true,
		'number'     => 6,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);

get_header();
?>

<main id="primary" class="site-main">
	<section class="error-404">
		<h1><?php esc_html_e( 'Page not found', 'mumega-motion' ); ?></h1>
		<p><?php esc_html_e( 'The page may have moved. Search the site or continue with a topic below.', 'mumega-motion' ); ?></p>

		<?php get_search_form(); ?>

		<?php if ( ! empty( $recovery_categories ) ) : ?>
			<nav class="recovery-links" aria-label="<?php esc_attr_e( 'Browse categories', 'mumega-motion' ); ?>">
				<ul>
					<?php foreach ( $recovery_categories as $recovery_category ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $recovery_category->term_id ) ); ?>">
								<?php echo esc_html( $recovery_category->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</section>
</main>

<?php
get_footer();
