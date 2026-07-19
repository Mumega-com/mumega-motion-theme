<?php
/**
 * Tests for the safe editorial homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the homepage template contract and global-post safety boundary.
 */
final class EditorialHomeTemplateTest extends TestCase {
	/**
	 * Resets template and post fixtures before each assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']         = array();
		$GLOBALS['mumega_motion_test_current_post']    = null;
		$GLOBALS['mumega_motion_test_postdata_events'] = array();
		$GLOBALS['post']                               = null;
	}

	/**
	 * Exposes the homepage as an opt-in native WordPress page template.
	 */
	public function test_editorial_home_is_a_named_page_template(): void {
		$source = $this->theme_source( 'page-templates/editorial-home.php' );

		$this->assertStringStartsWith(
			"<?php\n/**\n * Template Name: Editorial Home\n * Template Post Type: page\n *\n * @package Mumega_Motion\n */",
			$source
		);
	}

	/**
	 * Keeps homepage activation reversible through native Reading Settings.
	 */
	public function test_front_page_php_is_absent(): void {
		$this->assertFileExists( dirname( __DIR__ ) . '/page-templates/editorial-home.php' );
		$this->assertFileDoesNotExist( dirname( __DIR__ ) . '/front-page.php' );
	}

	/**
	 * Preserves one ordered exclusion list through every homepage selection.
	 */
	public function test_home_selection_uses_one_shared_used_id_list(): void {
		$source             = $this->theme_source( 'page-templates/editorial-home.php' );
		$selection_contract = <<<'PHP'
$used_ids    = array();
$lead        = mumega_motion_select_lead_post( $used_ids );
$supporting  = mumega_motion_select_supporting_posts( $used_ids, 3 );
$test_lab    = mumega_motion_select_special_posts( 'test-lab', $used_ids, 1 );
$rails       = mumega_motion_select_rail_categories( $used_ids, 3 );
$field_notes = mumega_motion_select_special_posts( 'field-notes', $used_ids, 5 );
PHP;

		$this->assertStringContainsString( $selection_contract, $source );
		$this->assertMatchesRegularExpression(
			'/foreach\s*\(\s*\$rails\s+as\s+\$rail_term_id\s*\).*?mumega_motion_select_category_posts\(\s*\$rail_term_id,\s*\$used_ids,\s*3\s*\)/s',
			$source
		);
	}

	/**
	 * Gives the selected lead sole ownership of the homepage document heading.
	 */
	public function test_lead_heading_is_the_only_home_h1(): void {
		$files  = array(
			'page-templates/editorial-home.php',
			'template-parts/lead-story.php',
			'template-parts/content-card.php',
			'template-parts/content-card-compact.php',
			'template-parts/section-heading.php',
			'template-parts/newsletter.php',
		);
		$source = '';

		foreach ( $files as $file ) {
			$source .= $this->theme_source( $file );
		}

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $source ) );
		$this->assertStringContainsString( '<h1', $this->theme_source( 'template-parts/lead-story.php' ) );
		$this->assertStringNotContainsString( 'data-motion=', $this->theme_source( 'template-parts/lead-story.php' ) );
	}

	/**
	 * Renders block-aware newsletter content without leaking its post globally.
	 */
	public function test_newsletter_render_restores_the_previous_global_post(): void {
		$previous                                   = new WP_Post(
			array(
				'ID'           => 12,
				'post_content' => 'Homepage content.',
			)
		);
		$newsletter                                 = new WP_Post(
			array(
				'ID'           => 41,
				'post_content' => '<!-- wp:wpforms/form-selector -->Newsletter form<!-- /wp:wpforms/form-selector -->',
			)
		);
		$GLOBALS['post']                            = $previous;
		$GLOBALS['mumega_motion_test_current_post'] = $previous;
		add_filter(
			'the_content',
			static function ( $content ) {
				return '<div class="rendered-content">' . $content . '</div>';
			}
		);

		$output = $this->render_template_part(
			'template-parts/newsletter.php',
			array( 'post' => $newsletter )
		);

		$this->assertStringContainsString( 'rendered-content', $output );
		$this->assertStringContainsString( 'wp:wpforms/form-selector', $output );
		$this->assertSame( $previous, $GLOBALS['post'] );
		$this->assertSame( $previous, $GLOBALS['mumega_motion_test_current_post'] );
		$this->assertSame(
			array(
				array( 'setup', 41 ),
				array( 'reset' ),
				array( 'setup', 12 ),
			),
			$GLOBALS['mumega_motion_test_postdata_events']
		);
	}

	/**
	 * Reads one theme source file without turning absence into a PHP warning.
	 *
	 * @param string $filename Theme-relative filename.
	 * @return string
	 */
	private function theme_source( $filename ): string {
		$path = dirname( __DIR__ ) . '/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This is a local test fixture, not a remote URL.
		return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Captures a template part with WordPress-style local arguments.
	 *
	 * @param string $filename Theme-relative filename.
	 * @param array  $args     Template-part arguments.
	 * @return string
	 */
	private function render_template_part( $filename, $args ): string {
		$path = dirname( __DIR__ ) . '/' . $filename;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Mirrors get_template_part arguments.
		ob_start();
		require $path;

		return (string) ob_get_clean();
	}
}
