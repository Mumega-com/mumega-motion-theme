<?php
/**
 * Theme fallback and posts-index template.
 *
 * @package Mumega_Motion
 */

get_header();
?>

<main id="primary" class="site-main">
	<header class="archive-header">
		<h1 class="archive-title"><?php esc_html_e( 'Latest stories', 'mumega-motion' ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="posts-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-summary' ); ?>>
					<h2 class="post-summary__title">
						<a href="<?php echo esc_url( get_permalink() ); ?>">
							<?php echo esc_html( get_the_title() ); ?>
						</a>
					</h2>
					<div class="post-summary__content">
						<?php the_content(); ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty-state' ); ?>
	<?php endif; ?>
</main>

<?php
get_footer();
