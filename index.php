<?php
/**
 * Theme fallback template.
 *
 * @package Mumega_Motion
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

	<header>
		<div
			data-motion="fade-in"
			data-motion-y="16"
			data-motion-duration="0.6"
		>
			<h1><?php bloginfo( 'name' ); ?></h1>
			<p><?php bloginfo( 'description' ); ?></p>
		</div>
	</header>

	<main>
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
					<div
						data-motion="fade-in"
						data-motion-delay="0.1"
					>
						<h2><?php the_title(); ?></h2>
						<?php the_content(); ?>
					</div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing found.', 'mumega-motion' ); ?></p>
		<?php endif; ?>

		<section id="ai-stream-demo" style="max-width:260px;">
			<div
				data-motion-stream="<?php echo esc_url( get_template_directory_uri() . '/stream-demo.php' ); ?>"
				data-motion-stream-sibling="<?php esc_attr_e( 'This card sits below the streaming text — its position is under Motion\'s control too, so it animates as the streaming content above it grows.', 'mumega-motion' ); ?>"
			></div>
		</section>
	</main>

	<?php wp_footer(); ?>
</body>
</html>
