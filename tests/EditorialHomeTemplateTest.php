<?php
/**
 * Tests for the safe editorial homepage template.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/inc/editorial-helpers.php';
require_once dirname( __DIR__ ) . '/inc/editorial-queries.php';
require_once dirname( __DIR__ ) . '/inc/editorial-patterns.php';

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
		$GLOBALS['mumega_motion_test_post_thumbnails']           = array();
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
$used_ids          = array();
$briefing           = mumega_motion_select_lead_post( $used_ids );
$briefing_related   = mumega_motion_select_related_posts( $used_ids );
$coverage_feature   = mumega_motion_select_coverage_feature( $used_ids );
$coverage_support   = mumega_motion_select_supporting_posts( $used_ids, 4 );
$guide_groups       = mumega_motion_select_rail_groups( $used_ids, 4, 2 );
$tool_posts         = mumega_motion_select_special_posts( 'tools-reviews', $used_ids, 3 );
PHP;

		$this->assertStringContainsString( $selection_contract, $source );
	}

	/**
	 * Keeps the page-owned intro as the sole H1 when Newsletter pattern content is embedded.
	 */
	public function test_embedded_newsletter_pattern_preserves_single_home_h1(): void {
		$newsletter               = $this->post( 41, 'Newsletter landing page' );
		$newsletter->post_content = mumega_motion_newsletter_page_pattern_content();

		$output = $this->render_editorial_home(
			array(
				'page'        => $this->post( 80, 'Editorial Desk' ),
				'briefing'    => array( $this->post( 10, 'Lead investigation' ) ),
				'newsletter'  => array( $newsletter ),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertStringContainsString( 'Stay informed with reporting delivered to your inbox.', $output );
	}

	/**
	 * Gives the page-owned intro sole ownership of the homepage H1, ahead of the briefing.
	 */
	public function test_home_intro_owns_the_sole_h1_and_precedes_the_briefing(): void {
		$page              = $this->post( 80, 'Editorial Desk' );
		$page->post_excerpt = 'A promise about what this desk covers.';
		$page->post_content = 'Read on for the full mission.';

		$output = $this->render_editorial_home(
			array(
				'page'     => $page,
				'briefing' => array( $this->post( 10, 'Lead investigation' ) ),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>.*Editorial Desk.*<\/h1>/s', $output );
		$this->assertDoesNotMatchRegularExpression( '/<h1\b[^>]*>.*Lead investigation.*<\/h1>/s', $output );
		$this->assertMatchesRegularExpression( '/<h3\b[^>]*>.*Lead investigation.*<\/h3>/s', $output );
		$this->assertStringContainsString( 'A promise about what this desk covers.', $output );
		$this->assertStringContainsString( 'Read on for the full mission.', $output );

		$intro_position    = strpos( $output, 'home-intro' );
		$briefing_position = strpos( $output, 'home-briefing' );

		$this->assertIsInt( $intro_position );
		$this->assertIsInt( $briefing_position );
		$this->assertLessThan( $briefing_position, $intro_position );
	}

	/**
	 * Preserves the page promise and offers a useful empty state when no post can brief.
	 */
	public function test_missing_briefing_still_shows_the_page_promise_and_an_empty_state(): void {
		$page              = $this->post( 80, 'Editorial Desk' );
		$page->post_excerpt = 'A promise about what this desk covers.';

		$output = $this->render_editorial_home( array( 'page' => $page ) );

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>.*Editorial Desk.*<\/h1>/s', $output );
		$this->assertStringContainsString( 'A promise about what this desk covers.', $output );
		$this->assertStringContainsString( 'Nothing found', $output );
	}

	/**
	 * Renders the page-owned title, manual excerpt, and filtered block content.
	 */
	public function test_home_intro_renders_page_title_excerpt_and_content(): void {
		$page              = $this->post( 80, 'Editorial Desk' );
		$page->post_excerpt = 'A promise about what this desk covers.';
		$page->post_content = 'Read on for the full mission.';

		$output = $this->render_template_part(
			'template-parts/home-intro.php',
			array( 'page' => $page )
		);

		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );
		$this->assertMatchesRegularExpression( '/<h1\b[^>]*>.*Editorial Desk.*<\/h1>/s', $output );
		$this->assertStringContainsString( 'A promise about what this desk covers.', $output );
		$this->assertStringContainsString( 'Read on for the full mission.', $output );
	}

	/**
	 * Renders the guide profile, possessive label, responsive image, and related links.
	 */
	public function test_home_briefing_renders_guide_profile_possessive_label_and_related_links(): void {
		$guide               = $this->post( 90, 'Jordan Lee' );
		$guide->post_excerpt = 'Site editor and disclosure lead.';
		$GLOBALS['mumega_motion_test_post_thumbnails'][90] = true;

		$briefing_post = $this->post( 10, 'Lead investigation' );
		$related_a     = $this->post( 11, 'Related report one' );
		$related_b     = $this->post( 12, 'Related report two' );

		$output = $this->render_template_part(
			'template-parts/home-briefing.php',
			array(
				'post'    => $briefing_post,
				'guide'   => $guide,
				'related' => array( $related_a, $related_b ),
			)
		);

		$this->assertStringContainsString( 'Jordan Lee&#039;s briefing', $output );
		$this->assertStringContainsString( 'Site editor and disclosure lead.', $output );
		$this->assertStringContainsString( 'https://example.test/?p=90', $output );
		$this->assertMatchesRegularExpression( '/<img\b[^>]*srcset="[^"]+"/', $output );
		$this->assertMatchesRegularExpression( '/<h3\b[^>]*>.*Lead investigation.*<\/h3>/s', $output );
		$this->assertStringNotContainsString( '<h1', $output );
		$this->assertStringContainsString( 'Related report one', $output );
		$this->assertStringContainsString( 'Related report two', $output );
	}

	/**
	 * Omits the guide profile cleanly — generic label, no image, no dead link — when absent.
	 */
	public function test_home_briefing_without_a_guide_page_omits_the_profile_cleanly(): void {
		$briefing_post = $this->post( 10, 'Lead investigation' );

		$output = $this->render_template_part(
			'template-parts/home-briefing.php',
			array( 'post' => $briefing_post )
		);

		$this->assertStringContainsString( 'Editor&#039;s briefing', $output );
		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringNotContainsString( 'home-briefing__guide-link', $output );
		$this->assertMatchesRegularExpression( '/<h3\b[^>]*>.*Lead investigation.*<\/h3>/s', $output );
	}

	/**
	 * Falls back to the shared empty state when no post is available to brief.
	 */
	public function test_home_briefing_without_a_post_shows_the_useful_empty_state(): void {
		$output = $this->render_template_part( 'template-parts/home-briefing.php', array() );

		$this->assertStringContainsString( 'Nothing found', $output );
		$this->assertStringNotContainsString( '<h3', $output );
	}

	/**
	 * Leaves interactive plugin forms outside React-owned Motion mounts.
	 */
	public function test_newsletter_section_is_not_a_motion_mount(): void {
		$source = $this->theme_source( 'page-templates/editorial-home.php' );

		$this->assertStringContainsString( '<section class="home-newsletter">', $source );
	}

	/**
	 * Delegates newsletter content filtering to the shared restoration helper.
	 */
	public function test_newsletter_delegates_content_rendering_to_shared_helper(): void {
		$source = $this->theme_source( 'template-parts/newsletter.php' );

		$this->assertStringContainsString( 'mumega_motion_render_post_content( $newsletter_post )', $source );
		$this->assertStringNotContainsString( 'setup_postdata(', $source );
		$this->assertStringNotContainsString( 'wp_reset_postdata(', $source );
		$this->assertStringNotContainsString( "apply_filters( 'the_content'", $source );
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
	 * Each `get_posts()` result queues in the exact order the template resolves
	 * its data: briefing lead, related pair, coverage feature, coverage support,
	 * the editorial guide page, the methodology page, the knowledge map page, and
	 * finally the newsletter page.
	 *
	 * @param array $overrides Named query-result overrides.
	 * @return string
	 */
	private function render_editorial_home( array $overrides = array() ): string {
		$defaults = array(
			'page'             => $this->post( 80, 'Editorial Desk' ),
			'briefing'         => array(),
			'related'          => array(),
			'coverage_feature' => array(),
			'coverage_support' => array(),
			'guide'            => array(),
			'methodology'      => array(),
			'knowledge_map'    => array(),
			'newsletter'       => array(),
		);
		$config = array_merge( $defaults, $overrides );
		$page   = $config['page'];

		$GLOBALS['post']                                  = $page;
		$GLOBALS['mumega_motion_test_current_post']       = $page;
		$GLOBALS['mumega_motion_test_queried_object_id']  = $page->ID;
		$GLOBALS['mumega_motion_test_posts'][ $page->ID ] = $page;
		$GLOBALS['mumega_motion_test_post_queries']       = array(
			$config['briefing'],
			$config['related'],
			$config['coverage_feature'],
			$config['coverage_support'],
			$config['guide'],
			$config['methodology'],
			$config['knowledge_map'],
			$config['newsletter'],
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
