<?php
/**
 * Site footer.
 *
 * @package Mumega_Motion
 */

$elementor_footer_rendered = function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'footer' );

if ( ! $elementor_footer_rendered ) :
	$footer_menu_markup = has_nav_menu( 'footer' )
		? wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'fallback_cb'    => false,
				'menu_class'     => 'footer-navigation__list',
				'menu_id'        => 'footer-menu',
				'echo'           => false,
			)
		)
		: '';
	?>

<footer class="site-footer">
	<div class="site-footer__identity">
		<a class="site-footer__title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
		<?php if ( get_bloginfo( 'description', 'display' ) ) : ?>
			<p class="site-footer__tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( trim( (string) $footer_menu_markup ) ) ) : ?>
		<nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Footer Navigation', 'mumega-motion' ); ?>">
			<?php echo $footer_menu_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted core menu markup. ?>
		</nav>
	<?php endif; ?>

	<p class="site-footer__copyright">
		&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
	</p>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
