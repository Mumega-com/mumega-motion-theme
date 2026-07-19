<?php
/**
 * Tests for the safe editorial homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/editorial-helpers.php';
require_once dirname( __DIR__ ) . '/inc/editorial-queries.php';

/**
 * Exercises the homepage template contract and global-post safety boundary.
 */
final class EditorialHomeTemplateTest extends TestCase {
	/**
	 * Resets template and post fixtures before each assertion.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']                   = array();
		$GLOBALS['mumega_motion_test_current_post']              = null;
		$GLOBALS['mumega_motion_test_postdata_events']           = array();
		$GLOBALS['mumega_motion_test_options']                   = array();
		$GLOBALS['mumega_motion_test_posts']                     = array();
		$GLOBALS['mumega_motion_test_post_terms']                = array();
		$GLOBALS['mumega_motion_test_post_queries']              = array();
		$GLOBALS['mumega_motion_test_get_posts_requests']        = array();
		$GLOBALS['mumega_motion_test_categories_by_slug']        = array();
		$GLOBALS['mumega_motion_test_category_by_slug_requests'] = array();
		$GLOBALS['mumega_motion_test_categories']                = array();
		$GLOBALS['mumega_motion_test_nav_menu_locations']        = array();
		$GLOBALS['mumega_motion_test_nav_menu_objects']          = array();
		$GLOBALS['mumega_motion_test_nav_menu_items']            = array();
		$GLOBALS['mumega_motion_test_queried_object_id']         = 0;
		$GLOBALS['post'] = null;
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
	 * Gives the rendered lead sole ownership of the homepage document heading.
	 */
	public function test_lead_heading_is_the_only_home_h1(): void {
		$output = $this->render_editorial_home(
			$this->post( 10, 'Lead investigation' )
		);

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>.*Lead investigation.*<\/h1>/s', $output );
	}

	/**
	 * Preserves a single WordPress-sourced H1 when no post can become the lead.
	 */
	public function test_empty_editorial_home_uses_page_title_as_sole_h1(): void {
		$output = $this->render_editorial_home();

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>.*Editorial Desk.*<\/h1>/s', $output );
		$this->assertStringContainsString( 'Nothing found', $output );
	}

	/**
	 * Introduces supporting-card H3 titles with an associated section H2.
	 */
	public function test_supporting_stories_heading_precedes_card_headings(): void {
		$output = $this->render_editorial_home(
			$this->post( 10, 'Lead investigation' ),
			array( $this->post( 11, 'Supporting report' ) )
		);

		$this->assertMatchesRegularExpression(
			'/<section\b[^>]*aria-labelledby="supporting-stories-heading"[^>]*>.*<h2\b[^>]*id="supporting-stories-heading"[^>]*>\s*Supporting stories\s*<\/h2>.*<h3\b[^>]*>.*Supporting report.*<\/h3>/s',
			$output
		);
	}

	/**
	 * Declares Motion only when the template will render a bounded mount.
	 */
	public function test_rendered_template_mount_declares_motion_before_the_header(): void {
		$this->render_editorial_home(
			$this->post( 10, 'Lead investigation' ),
			array( $this->post( 11, 'Supporting report' ) )
		);

		$this->assertTrue( apply_filters( 'mumega_motion_enqueue_motion', false ) );
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

	/**
	 * Renders the full Editorial Home with deterministic query results.
	 *
	 * @param WP_Post|null $lead       Optional lead post.
	 * @param array        $supporting Supporting posts.
	 * @return string
	 */
	private function render_editorial_home( $lead = null, $supporting = array() ): string {
		$page = $this->post( 80, 'Editorial Desk' );

		$GLOBALS['post']                                 = $page;
		$GLOBALS['mumega_motion_test_current_post']      = $page;
		$GLOBALS['mumega_motion_test_queried_object_id'] = 80;
		$GLOBALS['mumega_motion_test_posts'][80]         = $page;
		$GLOBALS['mumega_motion_test_post_queries']      = array(
			$lead instanceof WP_Post ? array( $lead ) : array(),
			$supporting,
			array(),
		);

		ob_start();
		require dirname( __DIR__ ) . '/page-templates/editorial-home.php';

		return (string) ob_get_clean();
	}

	/**
	 * Creates a complete post fixture for rendered card output.
	 *
	 * @param int    $id    Post identifier.
	 * @param string $title Post title.
	 * @return WP_Post
	 */
	private function post( $id, $title ): WP_Post {
		return new WP_Post(
			array(
				'ID'            => $id,
				'post_title'    => $title,
				'post_author'   => 1,
				'post_content'  => 'Useful editorial context.',
				'post_excerpt'  => 'A concise editorial summary.',
				'post_date'     => 'July 18, 2026',
				'post_date_gmt' => '2026-07-18 12:00:00',
			)
		);
	}
}
