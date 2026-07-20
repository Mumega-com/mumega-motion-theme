<?php
/**
 * Editorial homepage audience pathways — menu-derived title, description and link only.
 *
 * @package Mumega_Motion
 */

$audience_items = isset( $args['items'] ) ? array_values( (array) $args['items'] ) : array();

$audience_items = array_filter(
	$audience_items,
	static function ( $item ) {
		return is_array( $item ) && isset( $item['title'], $item['url'] ) && '' !== trim( (string) $item['title'] ) && '' !== trim( (string) $item['url'] );
	}
);

if ( empty( $audience_items ) ) {
	return;
}
?>

<section class="home-audiences">
	<?php
	get_template_part(
		'template-parts/section-heading',
		null,
		array( 'title' => __( 'Explore by audience', 'mumega-motion' ) )
	);
	?>
	<ul class="home-audiences__list">
		<?php foreach ( $audience_items as $audience_item ) : ?>
			<?php $description = isset( $audience_item['description'] ) ? trim( (string) $audience_item['description'] ) : ''; ?>
			<li class="home-audiences__item">
				<a class="home-audiences__link" href="<?php echo esc_url( (string) $audience_item['url'] ); ?>">
					<span class="home-audiences__title"><?php echo esc_html( (string) $audience_item['title'] ); ?></span>
					<?php if ( '' !== $description ) : ?>
						<span class="home-audiences__description"><?php echo esc_html( $description ); ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
