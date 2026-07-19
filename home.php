<?php
/**
 * Native posts-index template.
 *
 * @package Mumega_Motion
 */

mumega_motion_get_header();
$menu_category_ids = mumega_motion_menu_category_ids();
?>

<main id="primary" class="site-main editorial-listing">
	<header class="archive-header">
		<h1 class="archive-title"><?php esc_html_e( 'Latest stories', 'mumega-motion' ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<h2 id="listing-stories-heading" class="screen-reader-text"><?php esc_html_e( 'Stories', 'mumega-motion' ); ?></h2>
		<div class="editorial-listing__grid" aria-labelledby="listing-stories-heading">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part(
					'template-parts/content-card',
					null,
					array(
						'post'              => get_post(),
						'menu_category_ids' => $menu_category_ids,
					)
				);
			endwhile;
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty-state' ); ?>
	<?php endif; ?>
</main>

<?php
mumega_motion_get_footer();
