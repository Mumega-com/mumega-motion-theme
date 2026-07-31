<?php
/**
 * Template Name: MCPWP Control Plane Home
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */

mumega_motion_get_header();
?>
<main id="primary" class="site-main mcpwp-control-home-shell">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'mcpwp-control-home-entry' ); ?>>
			<?php mumega_motion_the_control_home_content(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
mumega_motion_get_footer();
