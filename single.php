<?php
/**
 * Native editorial article template.
 *
 * @package Mumega_Motion
 */

mumega_motion_get_header();
?>

<main id="primary" class="site-main single-article">
	<?php
	while ( have_posts() ) :
		the_post();

		$article                 = get_post();
		$primary_category        = mumega_motion_primary_category( $article->ID, mumega_motion_menu_category_ids() );
		$public_entity_tags      = mumega_motion_public_entity_tags( $article->ID );
		$all_tags                = get_the_tags( $article->ID );
		$has_affiliate_tag       = false;
		$affiliate_disclosure    = __( 'This article may contain affiliate links. Our editorial conclusions are independent, and we may earn a commission when you purchase through a link.', 'mumega-motion' );
		$normalized_article_text = strtolower( trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $article->post_content ) ) ) );
		$normalized_disclosure   = strtolower( trim( (string) preg_replace( '/\s+/', ' ', $affiliate_disclosure ) ) );
		$has_disclosure_marker   = false !== stripos( $article->post_content, 'mumega-motion-affiliate-disclosure' );
		$has_authored_disclosure = $has_disclosure_marker || false !== strpos( $normalized_article_text, $normalized_disclosure );

		foreach ( (array) $all_tags as $article_tag ) {
			if ( $article_tag instanceof WP_Term && 'affiliate' === strtolower( trim( $article_tag->slug ) ) ) {
				$has_affiliate_tag = true;
				break;
			}
		}
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'article-entry' ); ?>>
			<header class="article-header">
				<?php if ( $primary_category instanceof WP_Term ) : ?>
					<a class="article-header__topic" href="<?php echo esc_url( get_category_link( $primary_category->term_id ) ); ?>">
						<?php echo esc_html( $primary_category->name ); ?>
					</a>
				<?php endif; ?>
				<h1 class="article-header__title"><?php echo esc_html( get_the_title( $article ) ); ?></h1>
				<?php if ( '' !== trim( (string) $article->post_excerpt ) ) : ?>
					<p class="article-header__excerpt"><?php echo esc_html( $article->post_excerpt ); ?></p>
				<?php endif; ?>
				<?php get_template_part( 'template-parts/article-meta', null, array( 'post' => $article ) ); ?>
			</header>

			<?php if ( has_post_thumbnail( $article ) ) : ?>
				<figure class="article-featured-image">
					<?php
					echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core responsive image markup.
						$article,
						'full',
						array(
							'class'    => 'article-featured-image__image',
							'decoding' => 'async',
						)
					);
					$featured_caption = wp_get_attachment_caption( get_post_thumbnail_id( $article ) );
					?>
					<?php if ( '' !== trim( (string) $featured_caption ) ) : ?>
						<figcaption><?php echo wp_kses_post( $featured_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="article-body">
				<?php the_content(); ?>
			</div>

			<?php $author_bio = get_the_author_meta( 'description', (int) $article->post_author ); ?>
			<?php if ( '' !== trim( (string) $author_bio ) ) : ?>
				<aside class="article-author-bio" aria-label="<?php esc_attr_e( 'About the author', 'mumega-motion' ); ?>">
					<h2><?php esc_html_e( 'About the author', 'mumega-motion' ); ?></h2>
					<p><?php echo esc_html( $author_bio ); ?></p>
				</aside>
			<?php endif; ?>

			<?php if ( ! empty( $public_entity_tags ) ) : ?>
				<section class="article-entities" aria-labelledby="article-entities-heading">
					<h2 id="article-entities-heading"><?php esc_html_e( 'Entities', 'mumega-motion' ); ?></h2>
					<ul class="article-entities__list">
						<?php foreach ( $public_entity_tags as $entity_tag ) : ?>
							<li>
								<a class="article-entities__link" href="<?php echo esc_url( get_tag_link( $entity_tag->term_id ) ); ?>">
									<?php echo esc_html( $entity_tag->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( $has_affiliate_tag && ! $has_authored_disclosure ) : ?>
				<?php $affiliate_policy_page = mumega_motion_affiliate_policy_page(); ?>
				<aside class="affiliate-disclosure mumega-motion-affiliate-disclosure">
					<p>
						<?php echo esc_html( $affiliate_disclosure ); ?>
						<?php if ( $affiliate_policy_page instanceof WP_Post ) : ?>
							<a href="<?php echo esc_url( get_permalink( $affiliate_policy_page ) ); ?>"><?php esc_html_e( 'Read our affiliate disclosure', 'mumega-motion' ); ?></a>
						<?php endif; ?>
					</p>
				</aside>
			<?php endif; ?>

			<?php
			$more_from_topic = $primary_category instanceof WP_Term
				? mumega_motion_select_more_from_topic_posts( $article->ID, $primary_category->term_id, 3 )
				: array();
			?>
			<?php if ( ! empty( $more_from_topic ) ) : ?>
				<section class="more-from-topic" aria-labelledby="more-from-topic-heading">
					<h2 id="more-from-topic-heading"><?php esc_html_e( 'More from this topic', 'mumega-motion' ); ?></h2>
					<div class="more-from-topic__grid">
						<?php foreach ( $more_from_topic as $related_post ) : ?>
							<?php
							get_template_part(
								'template-parts/content-card',
								null,
								array(
									'post'              => $related_post,
									'menu_category_ids' => array( $primary_category->term_id ),
								)
							);
							?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</article>

		<?php the_post_navigation(); ?>
	<?php endwhile; ?>
</main>

<?php
mumega_motion_get_footer();
