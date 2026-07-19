<?php
/**
 * Template Name: Editorial Page
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */

mumega_motion_get_header();
?>

<main id="primary" class="site-main editorial-page">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-entry' ); ?>>
			<h1 class="page-entry__title"><?php echo esc_html( get_the_title() ); ?></h1>
			<div class="page-entry__content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
mumega_motion_get_footer();
