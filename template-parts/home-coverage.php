<?php
/**
 * Editorial homepage latest-coverage grid — one feature plus supporting stories.
 *
 * @package Mumega_Motion
 */

$feature_post      = isset( $args['feature'] ) && $args['feature'] instanceof WP_Post ? $args['feature'] : null;
$support_posts     = isset( $args['support'] ) ? array_values( (array) $args['support'] ) : array();
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

if ( ! $feature_post && empty( $support_posts ) ) {
	return;
}
?>

<section class="home-coverage">
	<?php
	get_template_part(
		'template-parts/section-heading',
		null,
		array( 'title' => __( 'Latest coverage', 'mumega-motion' ) )
	);
	?>
	<div class="home-coverage__grid">
		<?php
		get_template_part(
			'template-parts/content-card',
			null,
			array(
				'post'              => $feature_post,
				'menu_category_ids' => $menu_category_ids,
			)
		);
		?>
		<?php if ( ! empty( $support_posts ) ) : ?>
			<div class="home-coverage__support">
				<?php foreach ( $support_posts as $support_post ) : ?>
					<?php
					get_template_part(
						'template-parts/content-card-compact',
						null,
						array(
							'post'              => $support_post,
							'menu_category_ids' => $menu_category_ids,
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
