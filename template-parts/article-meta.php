<?php
/**
 * Article author, date, and reading-time details.
 *
 * @package Mumega_Motion
 */

$article_meta_post = isset( $args['post'] ) && $args['post'] instanceof WP_Post ? $args['post'] : null;

if ( ! $article_meta_post ) {
	return;
}

$author_name  = get_the_author_meta( 'display_name', (int) $article_meta_post->post_author );
$reading_time = mumega_motion_reading_time( $article_meta_post->post_content );
/* translators: %s: Estimated reading time in minutes. */
$reading_label = sprintf( _n( '%s min read', '%s min read', $reading_time, 'mumega-motion' ), number_format_i18n( $reading_time ) );
?>

<div class="article-meta">
	<?php if ( '' !== $author_name ) : ?>
		<span class="article-meta__author"><?php echo esc_html( $author_name ); ?></span>
	<?php endif; ?>
	<span class="article-meta__published">
		<?php esc_html_e( 'Published', 'mumega-motion' ); ?>
		<time datetime="<?php echo esc_attr( get_the_date( 'c', $article_meta_post ) ); ?>">
			<?php echo esc_html( get_the_date( '', $article_meta_post ) ); ?>
		</time>
	</span>
	<?php if ( mumega_motion_has_meaningful_modified_date( $article_meta_post->ID ) ) : ?>
		<span class="article-meta__modified">
			<?php esc_html_e( 'Updated', 'mumega-motion' ); ?>
			<time datetime="<?php echo esc_attr( get_the_modified_date( 'c', $article_meta_post ) ); ?>">
				<?php echo esc_html( get_the_modified_date( '', $article_meta_post ) ); ?>
			</time>
		</span>
	<?php endif; ?>
	<span class="article-meta__reading-time"><?php echo esc_html( $reading_label ); ?></span>
</div>
