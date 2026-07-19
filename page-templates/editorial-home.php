<?php
/**
 * Template Name: Editorial Home
 * Template Post Type: page
 *
 * @package Mumega_Motion
 */

$used_ids    = array();
$lead        = mumega_motion_select_lead_post( $used_ids );
$supporting  = mumega_motion_select_supporting_posts( $used_ids, 3 );
$test_lab    = mumega_motion_select_special_posts( 'test-lab', $used_ids, 1 );
$rail_groups = mumega_motion_select_rail_groups( $used_ids, 3 );
$field_notes = mumega_motion_select_special_posts( 'field-notes', $used_ids, 5 );

$menu_category_ids = mumega_motion_menu_category_ids();
$test_lab_term     = ! empty( $test_lab ) ? get_category_by_slug( 'test-lab' ) : false;
$field_notes_term  = ! empty( $field_notes ) ? get_category_by_slug( 'field-notes' ) : false;
foreach ( $rail_groups as $rail_group_index => $rail_group ) {
	$rail_term = get_term( $rail_group['term_id'], 'category' );

	if ( ! $rail_term instanceof WP_Term ) {
		unset( $rail_groups[ $rail_group_index ] );
		continue;
	}

	$rail_groups[ $rail_group_index ]['term'] = $rail_term;
}

$newsletter_page = mumega_motion_newsletter_page();

$has_template_motion_mount = ! empty( $supporting ) ||
	( ! empty( $test_lab ) && $test_lab_term instanceof WP_Term ) ||
	! empty( $rail_groups ) ||
	( ! empty( $field_notes ) && $field_notes_term instanceof WP_Term );

if ( $has_template_motion_mount ) {
	add_filter(
		'mumega_motion_enqueue_motion',
		static function () {
			return true;
		}
	);
}

mumega_motion_get_header();
?>

<main id="primary" class="site-main editorial-home">
	<?php if ( $lead instanceof WP_Post ) : ?>
		<?php
		get_template_part(
			'template-parts/lead-story',
			null,
			array(
				'post'              => $lead,
				'menu_category_ids' => $menu_category_ids,
			)
		);
		?>
	<?php else : ?>
		<section class="editorial-home__empty">
			<h1 class="page-entry__title"><?php echo esc_html( get_the_title( get_queried_object_id() ) ); ?></h1>
			<?php get_template_part( 'template-parts/empty-state' ); ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $supporting ) ) : ?>
		<section class="home-supporting" aria-labelledby="supporting-stories-heading" data-motion="fade-in">
			<h2 id="supporting-stories-heading" class="screen-reader-text"><?php esc_html_e( 'Supporting stories', 'mumega-motion' ); ?></h2>
			<div class="home-supporting__grid">
				<?php foreach ( $supporting as $supporting_post ) : ?>
					<?php
					get_template_part(
						'template-parts/content-card',
						null,
						array(
							'post'              => $supporting_post,
							'menu_category_ids' => $menu_category_ids,
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $test_lab ) && $test_lab_term instanceof WP_Term ) : ?>
		<?php $test_lab_post = $test_lab[0]; ?>
		<section class="home-test-lab" data-motion="fade-in">
			<?php
			get_template_part(
				'template-parts/section-heading',
				null,
				array(
					'title' => $test_lab_term->name,
					'url'   => get_category_link( $test_lab_term->term_id ),
				)
			);
			?>
			<article class="test-lab-feature">
				<?php if ( has_post_thumbnail( $test_lab_post ) ) : ?>
					<a class="test-lab-feature__media" href="<?php echo esc_url( get_permalink( $test_lab_post ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php
						echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core responsive image markup.
							$test_lab_post,
							'large',
							array(
								'class'    => 'test-lab-feature__image',
								'loading'  => 'lazy',
								'decoding' => 'async',
							)
						);
						?>
					</a>
				<?php endif; ?>
				<div class="test-lab-feature__content">
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $test_lab_post ) ); ?>">
						<?php echo esc_html( get_the_date( '', $test_lab_post ) ); ?>
					</time>
					<h3 class="test-lab-feature__title">
						<a href="<?php echo esc_url( get_permalink( $test_lab_post ) ); ?>">
							<?php echo esc_html( get_the_title( $test_lab_post ) ); ?>
						</a>
					</h3>
					<?php if ( '' !== mumega_motion_card_summary( $test_lab_post ) ) : ?>
						<p class="test-lab-feature__summary"><?php echo esc_html( mumega_motion_card_summary( $test_lab_post ) ); ?></p>
					<?php endif; ?>
				</div>
			</article>
		</section>
	<?php endif; ?>

	<?php foreach ( $rail_groups as $rail_group ) : ?>
		<section class="topic-rail" data-motion="fade-in">
			<?php
			get_template_part(
				'template-parts/section-heading',
				null,
				array(
					'title' => $rail_group['term']->name,
					'url'   => get_category_link( $rail_group['term']->term_id ),
				)
			);
			?>
			<div class="topic-rail__grid">
				<?php
				get_template_part(
					'template-parts/content-card',
					null,
					array(
						'post'              => $rail_group['posts'][0],
						'menu_category_ids' => $menu_category_ids,
					)
				);
				?>
				<div class="topic-rail__compact">
					<?php foreach ( array_slice( $rail_group['posts'], 1 ) as $rail_post ) : ?>
						<?php
						get_template_part(
							'template-parts/content-card-compact',
							null,
							array(
								'post'              => $rail_post,
								'menu_category_ids' => $menu_category_ids,
							)
						);
						?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( ! empty( $field_notes ) && $field_notes_term instanceof WP_Term ) : ?>
		<section class="field-notes" data-motion="fade-in">
			<?php
			get_template_part(
				'template-parts/section-heading',
				null,
				array(
					'title' => $field_notes_term->name,
					'url'   => get_category_link( $field_notes_term->term_id ),
				)
			);
			?>
			<div class="field-notes__list">
				<?php foreach ( $field_notes as $field_note ) : ?>
					<?php
					get_template_part(
						'template-parts/content-card-compact',
						null,
						array(
							'post'              => $field_note,
							'menu_category_ids' => $menu_category_ids,
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

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
