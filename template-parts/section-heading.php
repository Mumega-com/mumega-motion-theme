<?php
/**
 * Shared homepage section heading.
 *
 * @package Mumega_Motion
 */

$section_title = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$section_url   = isset( $args['url'] ) ? (string) $args['url'] : '';

if ( '' === $section_title ) {
	return;
}
?>

<header class="section-heading">
	<h2 class="section-heading__title">
		<?php if ( '' !== $section_url ) : ?>
			<a href="<?php echo esc_url( $section_url ); ?>"><?php echo esc_html( $section_title ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $section_title ); ?>
		<?php endif; ?>
	</h2>
</header>
