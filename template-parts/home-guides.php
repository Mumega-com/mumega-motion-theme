<?php
/**
 * Editorial homepage field guides — complete two-post category groups.
 *
 * @package Mumega_Motion
 */

$raw_groups        = isset( $args['groups'] ) ? array_values( (array) $args['groups'] ) : array();
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

$guide_groups = array();

foreach ( $raw_groups as $raw_group ) {
	if ( ! is_array( $raw_group ) || empty( $raw_group['term_id'] ) || empty( $raw_group['posts'] ) ) {
		continue;
	}

	$group_posts = array_values(
		array_filter(
			(array) $raw_group['posts'],
			static function ( $group_post ) {
				return $group_post instanceof WP_Post;
			}
		)
	);

	if ( count( $group_posts ) < 2 ) {
		continue;
	}

	$term = get_term( (int) $raw_group['term_id'], 'category' );

	if ( ! $term instanceof WP_Term ) {
		continue;
	}

	$guide_groups[] = array(
		'term'  => $term,
		'posts' => array_slice( $group_posts, 0, 2 ),
	);
}

if ( empty( $guide_groups ) ) {
	return;
}
?>

<section class="home-guides">
	<?php
	get_template_part(
		'template-parts/section-heading',
		null,
		array( 'title' => __( 'Field guides', 'mumega-motion' ) )
	);
	?>
	<div class="home-guides__list">
		<?php foreach ( $guide_groups as $guide_group ) : ?>
			<?php
			$term             = $guide_group['term'];
			$term_description = trim( (string) $term->description );
			?>
			<div class="home-guides__group">
				<p class="home-guides__group-category">
					<a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>">
						<?php echo esc_html( $term->name ); ?>
					</a>
				</p>
				<?php if ( '' !== $term_description ) : ?>
					<p class="home-guides__group-description"><?php echo esc_html( $term_description ); ?></p>
				<?php endif; ?>
				<div class="home-guides__group-posts">
					<?php
					get_template_part(
						'template-parts/content-card',
						null,
						array(
							'post'              => $guide_group['posts'][0],
							'menu_category_ids' => $menu_category_ids,
						)
					);
					get_template_part(
						'template-parts/content-card-compact',
						null,
						array(
							'post'              => $guide_group['posts'][1],
							'menu_category_ids' => $menu_category_ids,
						)
					);
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
