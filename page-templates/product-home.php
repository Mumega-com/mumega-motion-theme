<?php
/**
 * Template Name: Product Home
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */

mumega_motion_get_header();
?>
<main id="primary" class="site-main product-home-shell">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'product-home-entry' ); ?>>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
mumega_motion_get_footer();
