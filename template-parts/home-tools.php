<?php
/**
 * Editorial homepage tool intelligence — up to three unused tools-and-reviews posts.
 * Category labels come from real post categories; the theme never adds scores,
 * rankings, endorsements, or affiliate claims.
 *
 * @package Mumega_Motion
 */

$tool_posts        = isset( $args['posts'] ) ? array_values( (array) $args['posts'] ) : array();
$menu_category_ids = isset( $args['menu_category_ids'] ) ? (array) $args['menu_category_ids'] : array();

$tool_posts = array_values(
	array_filter(
		$tool_posts,
		static function ( $tool_post ) {
			return $tool_post instanceof WP_Post;
		}
	)
);

if ( count( $tool_posts ) < 2 ) {
	return;
}

$tool_posts = array_slice( $tool_posts, 0, 3 );
?>

<section class="home-tools">
	<?php
	get_template_part(
		'template-parts/section-heading',
		null,
		array( 'title' => __( 'Tool intelligence', 'mumega-motion' ) )
	);
	?>
	<div class="home-tools__grid">
		<?php foreach ( $tool_posts as $tool_post ) : ?>
			<?php
			get_template_part(
				'template-parts/content-card-compact',
				null,
				array(
					'post'              => $tool_post,
					'menu_category_ids' => $menu_category_ids,
				)
			);
			?>
		<?php endforeach; ?>
	</div>
</section>
