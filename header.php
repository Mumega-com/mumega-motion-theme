<?php
/**
 * Site header.
 *
 * @package Mumega_Motion
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'mumega-motion' ); ?></a>

<?php
$elementor_header_rendered = mumega_motion_render_elementor_location( 'header' );

if ( ! $elementor_header_rendered ) :
	$primary_menu_assigned = has_nav_menu( 'primary' );
	$primary_menu_markup   = $primary_menu_assigned
		? wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'fallback_cb'    => false,
				'menu_class'     => 'primary-navigation__list',
				'menu_id'        => 'primary-menu',
				'echo'           => false,
			)
		)
		: '';
	$mobile_menu_markup    = $primary_menu_assigned
		? wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'fallback_cb'    => false,
				'menu_class'     => 'mobile-navigation__list',
				'menu_id'        => 'mobile-menu',
				'echo'           => false,
			)
		)
		: '';
	$header_categories     = $primary_menu_assigned
		? array()
		: get_categories(
			array(
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
	$has_primary_links     = ! empty( trim( (string) $primary_menu_markup ) ) || ! empty( $header_categories );
	$newsletter_page       = mumega_motion_newsletter_page();
	?>
<header class="site-header">
	<div class="site-header__identity">
		<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
		<?php if ( get_bloginfo( 'description', 'display' ) ) : ?>
			<p class="site-tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $has_primary_links ) : ?>
		<nav class="primary-navigation" aria-label="<?php esc_attr_e( 'Primary Navigation', 'mumega-motion' ); ?>">
			<?php if ( ! empty( trim( (string) $primary_menu_markup ) ) ) : ?>
				<?php echo $primary_menu_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted core menu markup. ?>
			<?php else : ?>
				<ul class="primary-navigation__list">
					<?php foreach ( $header_categories as $header_category ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $header_category->term_id ) ); ?>">
								<?php echo esc_html( $header_category->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<div class="site-header__search">
		<?php get_search_form(); ?>
	</div>

	<?php if ( $newsletter_page ) : ?>
		<a class="subscribe-link" href="<?php echo esc_url( get_permalink( $newsletter_page ) ); ?>">
			<?php esc_html_e( 'Subscribe', 'mumega-motion' ); ?>
		</a>
	<?php endif; ?>

	<?php if ( $has_primary_links ) : ?>
		<details class="mobile-navigation">
			<summary><?php esc_html_e( 'Menu', 'mumega-motion' ); ?></summary>
			<nav aria-label="<?php esc_attr_e( 'Mobile Navigation', 'mumega-motion' ); ?>">
				<?php if ( ! empty( trim( (string) $mobile_menu_markup ) ) ) : ?>
					<?php echo $mobile_menu_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted core menu markup. ?>
				<?php else : ?>
					<ul class="mobile-navigation__list">
						<?php foreach ( $header_categories as $header_category ) : ?>
							<li>
								<a href="<?php echo esc_url( get_category_link( $header_category->term_id ) ); ?>">
									<?php echo esc_html( $header_category->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</nav>
		</details>
	<?php endif; ?>
</header>
<?php endif; ?>
