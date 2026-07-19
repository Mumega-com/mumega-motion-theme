<?php
/**
 * Native category and tag archive template.
 *
 * @package Mumega_Motion
 */

get_header();
$archive_description = get_the_archive_description();
$menu_category_ids   = mumega_motion_menu_category_ids();
?>

<main id="primary" class="site-main editorial-listing">
	<header class="archive-header">
		<?php the_archive_title( '<h1 class="archive-title">', '</h1>' ); ?>
		<?php if ( '' !== trim( wp_strip_all_tags( $archive_description ) ) ) : ?>
			<div class="archive-description"><?php echo wp_kses_post( $archive_description ); ?></div>
		<?php endif; ?>
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
get_footer();
