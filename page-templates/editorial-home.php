<?php
/**
 * Template Name: Editorial Home
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */

$page = get_post( get_queried_object_id() );

$used_ids          = array();
$briefing           = mumega_motion_select_lead_post( $used_ids );
$briefing_related   = mumega_motion_select_related_posts( $used_ids );
$coverage_feature   = mumega_motion_select_coverage_feature( $used_ids );
$coverage_support   = mumega_motion_select_supporting_posts( $used_ids, 4 );
$guide_groups       = mumega_motion_select_rail_groups( $used_ids, 4, 2 );
$tool_posts         = mumega_motion_select_special_posts( 'tools-reviews', $used_ids, 3 );

$menu_category_ids  = mumega_motion_menu_category_ids();
$guide              = mumega_motion_editorial_guide_page();
$methodology_page   = mumega_motion_methodology_page();
$knowledge_map_page = mumega_motion_knowledge_map_page();
$newsletter_page    = mumega_motion_newsletter_page();
$audience_items     = mumega_motion_audience_menu_items();

mumega_motion_get_header();
?>

<main id="primary" class="site-main editorial-home">
	<?php
	get_template_part(
		'template-parts/home-intro',
		null,
		array( 'page' => $page )
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-briefing',
		null,
		array(
			'post'              => $briefing,
			'guide'             => $guide,
			'related'           => $briefing_related,
			'menu_category_ids' => $menu_category_ids,
		)
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-audiences',
		null,
		array( 'items' => $audience_items )
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-coverage',
		null,
		array(
			'feature'           => $coverage_feature,
			'support'           => $coverage_support,
			'menu_category_ids' => $menu_category_ids,
		)
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-guides',
		null,
		array(
			'groups'            => $guide_groups,
			'menu_category_ids' => $menu_category_ids,
		)
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-methodology',
		null,
		array( 'page' => $methodology_page )
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-tools',
		null,
		array(
			'posts'             => $tool_posts,
			'menu_category_ids' => $menu_category_ids,
		)
	);
	?>

	<?php
	get_template_part(
		'template-parts/home-knowledge',
		null,
		array( 'page' => $knowledge_map_page )
	);
	?>

	<?php if ( $newsletter_page instanceof WP_Post ) : ?>
		<section class="home-newsletter">
			<?php
			get_template_part(
				'template-parts/newsletter',
				null,
				array( 'post' => $newsletter_page )
			);
			?>
		</section>
	<?php endif; ?>
</main>

<?php
mumega_motion_get_footer();
