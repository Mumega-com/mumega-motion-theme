<?php
/**
 * Shared no-results state.
 *
 * @package Mumega_Motion
 */

?>

<section class="empty-state">
	<h2><?php esc_html_e( 'Nothing found', 'mumega-motion' ); ?></h2>
	<p><?php esc_html_e( 'Try a different search or browse another topic.', 'mumega-motion' ); ?></p>
	<?php get_search_form(); ?>
</section>
