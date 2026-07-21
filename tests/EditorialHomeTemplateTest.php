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
		$GLOBALS['mumega_motion_test_terms']                     = array();
		$GLOBALS['mumega_motion_test_term_requests']             = array();
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
	 * Renders up to three menu-derived audience pathways behind one labelled heading.
	 */
	public function test_home_audiences_renders_pathways_with_one_heading_in_menu_order(): void {
		$output = $this->render_template_part(
			'template-parts/home-audiences.php',
			array(
				'items' => array(
					array(
						'title'       => 'Team leads',
						'description' => 'Playbooks for people managers.',
						'url'         => 'https://example.test/audience/team-leads/',
					),
					array(
						'title'       => 'Analysts',
						'description' => '',
						'url'         => 'https://example.test/audience/analysts/',
					),
					array(
						'title'       => 'Executives',
						'description' => 'Board-level briefings.',
						'url'         => 'https://example.test/audience/executives/',
					),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'Explore by audience', $output );
		$this->assertSame( 3, substr_count( $output, 'home-audiences__item' ) );
		$this->assertSame( 3, substr_count( $output, '<a class="home-audiences__link"' ) );
		$this->assertStringContainsString( 'href="https://example.test/audience/team-leads/"', $output );
		$this->assertStringContainsString( 'href="https://example.test/audience/analysts/"', $output );
		$this->assertStringContainsString( 'href="https://example.test/audience/executives/"', $output );
		$this->assertStringContainsString( 'Playbooks for people managers.', $output );
		$this->assertStringContainsString( 'Board-level briefings.', $output );
		$this->assertSame( 2, substr_count( $output, 'home-audiences__description' ) );

		$team_leads_position = strpos( $output, 'Team leads' );
		$analysts_position   = strpos( $output, 'Analysts' );
		$executives_position = strpos( $output, 'Executives' );

		$this->assertLessThan( $analysts_position, $team_leads_position );
		$this->assertLessThan( $executives_position, $analysts_position );
	}

	/**
	 * Omits the audiences section entirely when no pathways are configured.
	 */
	public function test_home_audiences_with_no_items_renders_nothing(): void {
		$output = $this->render_template_part( 'template-parts/home-audiences.php', array( 'items' => array() ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Renders the one-plus-four coverage grid behind the translatable fallback heading.
	 */
	public function test_home_coverage_renders_feature_and_support_grid(): void {
		$output = $this->render_template_part(
			'template-parts/home-coverage.php',
			array(
				'feature' => $this->post( 21, 'Feature Investigation' ),
				'support' => array(
					$this->post( 22, 'Support One' ),
					$this->post( 23, 'Support Two' ),
					$this->post( 24, 'Support Three' ),
					$this->post( 25, 'Support Four' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'Latest coverage', $output );
		$this->assertSame( 5, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringContainsString( 'Support One', $output );
		$this->assertStringContainsString( 'Support Two', $output );
		$this->assertStringContainsString( 'Support Three', $output );
		$this->assertStringContainsString( 'Support Four', $output );

		$feature_position = strpos( $output, 'Feature Investigation' );
		$support_position = strpos( $output, 'Support One' );

		$this->assertLessThan( $support_position, $feature_position );
	}

	/**
	 * Omits the empty support wrapper when only the feature card is available.
	 */
	public function test_home_coverage_with_feature_only_omits_the_empty_support_wrapper(): void {
		$output = $this->render_template_part(
			'template-parts/home-coverage.php',
			array( 'feature' => $this->post( 21, 'Feature Investigation' ) )
		);

		$this->assertSame( 1, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringNotContainsString( 'home-coverage__support', $output );
	}

	/**
	 * Renders the compact support grid alone when only supporting posts are available.
	 *
	 * Guards the `content-card.php` null-post no-op: with no feature post, the feature
	 * slot must render nothing (no bare `content-card` article) while the support posts
	 * still render as `content-card--compact` cards beneath the section heading.
	 */
	public function test_home_coverage_with_support_only_renders_compact_cards_without_a_feature_card(): void {
		$output = $this->render_template_part(
			'template-parts/home-coverage.php',
			array(
				'support' => array(
					$this->post( 22, 'Support One' ),
					$this->post( 23, 'Support Two' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'Latest coverage', $output );
		$this->assertStringContainsString( 'home-coverage__support', $output );
		$this->assertStringNotContainsString( '<article class="content-card">', $output );
		$this->assertSame( 2, preg_match_all( '/<article class="content-card content-card--compact">/', $output ) );
		$this->assertSame( 2, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringContainsString( 'Support One', $output );
		$this->assertStringContainsString( 'Support Two', $output );
	}

	/**
	 * Omits the coverage section entirely when neither the feature nor support posts exist.
	 */
	public function test_home_coverage_with_no_feature_and_no_support_renders_nothing(): void {
		$output = $this->render_template_part( 'template-parts/home-coverage.php', array() );

		$this->assertSame( '', $output );
	}

	/**
	 * Renders complete two-post field-guide groups with category title, description and link.
	 */
	public function test_home_guides_renders_complete_groups_with_category_details_in_order(): void {
		$GLOBALS['mumega_motion_test_terms'][5] = new WP_Term(
			array(
				'term_id'     => 5,
				'name'        => 'Deep Dives',
				'description' => 'Long-form investigations.',
			)
		);
		$GLOBALS['mumega_motion_test_terms'][6] = new WP_Term(
			array(
				'term_id'     => 6,
				'name'        => 'Quick Takes',
				'description' => '',
			)
		);

		$output = $this->render_template_part(
			'template-parts/home-guides.php',
			array(
				'groups' => array(
					array(
						'term_id' => 5,
						'posts'   => array( $this->post( 30, 'Deep Dive One' ), $this->post( 31, 'Deep Dive Two' ) ),
					),
					array(
						'term_id' => 6,
						'posts'   => array( $this->post( 32, 'Quick Take One' ), $this->post( 33, 'Quick Take Two' ) ),
					),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'Field guides', $output );
		$this->assertSame( 4, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringContainsString( 'Deep Dives', $output );
		$this->assertStringContainsString( 'https://example.test/category/5/', $output );
		$this->assertStringContainsString( 'Long-form investigations.', $output );
		$this->assertStringContainsString( 'Quick Takes', $output );
		$this->assertSame( 1, substr_count( $output, 'home-guides__group-description' ) );
		$this->assertStringContainsString( 'Deep Dive One', $output );
		$this->assertStringContainsString( 'Deep Dive Two', $output );
		$this->assertStringContainsString( 'Quick Take One', $output );
		$this->assertStringContainsString( 'Quick Take Two', $output );

		$deep_dives_position  = strpos( $output, 'Deep Dives' );
		$quick_takes_position = strpos( $output, 'Quick Takes' );

		$this->assertLessThan( $quick_takes_position, $deep_dives_position );
	}

	/**
	 * Skips a group whose category term cannot be resolved without breaking sibling groups.
	 */
	public function test_home_guides_skips_a_group_with_an_unresolvable_term(): void {
		$GLOBALS['mumega_motion_test_terms'][6] = new WP_Term(
			array(
				'term_id' => 6,
				'name'    => 'Quick Takes',
			)
		);

		$output = $this->render_template_part(
			'template-parts/home-guides.php',
			array(
				'groups' => array(
					array(
						'term_id' => 99,
						'posts'   => array( $this->post( 30, 'Deep Dive One' ), $this->post( 31, 'Deep Dive Two' ) ),
					),
					array(
						'term_id' => 6,
						'posts'   => array( $this->post( 32, 'Quick Take One' ), $this->post( 33, 'Quick Take Two' ) ),
					),
				),
			)
		);

		$this->assertStringNotContainsString( 'Deep Dive One', $output );
		$this->assertStringContainsString( 'Quick Take One', $output );
	}

	/**
	 * Omits the guides section entirely when no complete groups are configured.
	 */
	public function test_home_guides_with_no_groups_renders_nothing(): void {
		$output = $this->render_template_part( 'template-parts/home-guides.php', array( 'groups' => array() ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Places pathways, coverage and guides between the briefing and the newsletter in source
	 * order, and never repeats the briefing post inside either module.
	 */
	public function test_home_pathways_coverage_and_guides_render_in_order_without_repeating_the_briefing_post(): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['audiences'] = 31;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][31]            = new WP_Term( array( 'term_id' => 31 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][31]              = array(
			(object) array(
				'title'       => 'Team leads',
				'description' => 'Playbooks for people managers.',
				'url'         => 'https://example.test/audience/team-leads/',
			),
		);

		$GLOBALS['mumega_motion_test_nav_menu_locations']['primary'] = 50;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][50]           = new WP_Term( array( 'term_id' => 50 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][50]             = array(
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 7,
			),
		);
		$GLOBALS['mumega_motion_test_terms'][7] = new WP_Term(
			array(
				'term_id'     => 7,
				'name'        => 'Field Category',
				'description' => 'A category description.',
			)
		);

		$output = $this->render_editorial_home(
			array(
				'briefing'         => array( $this->post( 10, 'Lead investigation' ) ),
				'coverage_feature' => array( $this->post( 21, 'Feature Investigation' ) ),
				'coverage_support' => array( $this->post( 22, 'Support One' ) ),
				'guide_groups'     => array(
					array( $this->post( 60, 'Guide Post One' ), $this->post( 61, 'Guide Post Two' ) ),
				),
				'newsletter'       => array( $this->post( 41, 'Newsletter landing page' ) ),
			)
		);

		$briefing_position   = strpos( $output, 'home-briefing' );
		$audiences_position  = strpos( $output, 'home-audiences' );
		$coverage_position   = strpos( $output, 'home-coverage' );
		$guides_position     = strpos( $output, 'home-guides' );
		$newsletter_position = strpos( $output, 'home-newsletter' );

		foreach ( array( $briefing_position, $audiences_position, $coverage_position, $guides_position, $newsletter_position ) as $position ) {
			$this->assertIsInt( $position );
		}

		$this->assertLessThan( $audiences_position, $briefing_position );
		$this->assertLessThan( $coverage_position, $audiences_position );
		$this->assertLessThan( $guides_position, $coverage_position );
		$this->assertLessThan( $newsletter_position, $guides_position );

		$this->assertStringContainsString( 'Feature Investigation', $output );
		$this->assertStringContainsString( 'Guide Post One', $output );

		$coverage_and_guides = substr( $output, $coverage_position, $newsletter_position - $coverage_position );
		$this->assertStringNotContainsString( 'Lead investigation', $coverage_and_guides );
	}

	/**
	 * Renders the methodology page's title, excerpt, permalink and the theme's fixed
	 * Install/Connect/Verify/Recover vocabulary plus the Tested on WordPress marker.
	 */
	public function test_home_methodology_renders_page_link_excerpt_and_fixed_vocabulary(): void {
		$methodology               = $this->post( 70, 'How we test WordPress plugins' );
		$methodology->post_excerpt = 'Every review follows the same repeatable steps.';

		$output = $this->render_template_part(
			'template-parts/home-methodology.php',
			array( 'page' => $methodology )
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'How we test WordPress plugins', $output );
		$this->assertStringContainsString( 'Every review follows the same repeatable steps.', $output );
		$this->assertStringContainsString( 'href="https://example.test/?p=70"', $output );
		$this->assertStringContainsString( 'Install', $output );
		$this->assertStringContainsString( 'Connect', $output );
		$this->assertStringContainsString( 'Verify', $output );
		$this->assertStringContainsString( 'Recover', $output );
		$this->assertStringContainsString( 'Tested on WordPress', $output );
	}

	/**
	 * Omits the methodology module, its fixed vocabulary and its marker cleanly when no
	 * editorial-methodology page exists.
	 */
	public function test_home_methodology_without_a_page_renders_nothing(): void {
		$output = $this->render_template_part( 'template-parts/home-methodology.php', array() );

		$this->assertSame( '', $output );
		$this->assertStringNotContainsString( 'Tested on WordPress', $output );
	}

	/**
	 * Renders up to three tool-intelligence cards using real category labels.
	 */
	public function test_home_tools_renders_up_to_three_unused_posts(): void {
		$output = $this->render_template_part(
			'template-parts/home-tools.php',
			array(
				'posts' => array(
					$this->post( 50, 'Backup Plugin Field Test' ),
					$this->post( 51, 'Caching Plugin Field Test' ),
					$this->post( 52, 'Security Plugin Field Test' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'Tool intelligence', $output );
		$this->assertSame( 3, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringContainsString( 'Backup Plugin Field Test', $output );
		$this->assertStringContainsString( 'Caching Plugin Field Test', $output );
		$this->assertStringContainsString( 'Security Plugin Field Test', $output );
		$this->assertStringNotContainsString( 'score', strtolower( $output ) );
		$this->assertStringNotContainsString( 'rank', strtolower( $output ) );
		$this->assertStringNotContainsString( 'affiliate', strtolower( $output ) );
	}

	/**
	 * Caps the tool-intelligence module at three cards even when more posts are supplied.
	 */
	public function test_home_tools_caps_at_three_posts(): void {
		$output = $this->render_template_part(
			'template-parts/home-tools.php',
			array(
				'posts' => array(
					$this->post( 50, 'Tool One' ),
					$this->post( 51, 'Tool Two' ),
					$this->post( 52, 'Tool Three' ),
					$this->post( 53, 'Tool Four' ),
				),
			)
		);

		$this->assertSame( 3, preg_match_all( '/<h3\b/i', $output ) );
		$this->assertStringNotContainsString( 'Tool Four', $output );
	}

	/**
	 * Omits the tool-intelligence module entirely when fewer than two eligible posts exist.
	 */
	public function test_home_tools_with_fewer_than_two_posts_renders_nothing(): void {
		$output_with_one  = $this->render_template_part(
			'template-parts/home-tools.php',
			array( 'posts' => array( $this->post( 50, 'Solo Tool Review' ) ) )
		);
		$output_with_none = $this->render_template_part( 'template-parts/home-tools.php', array( 'posts' => array() ) );

		$this->assertSame( '', $output_with_one );
		$this->assertSame( '', $output_with_none );
	}

	/**
	 * Renders the knowledge-map page's title, excerpt, featured media and permalink.
	 */
	public function test_home_knowledge_renders_page_title_excerpt_media_and_url(): void {
		$knowledge_page                                    = $this->post( 60, 'How our topics connect' );
		$knowledge_page->post_excerpt                      = 'A map of how our guides and reports relate.';
		$GLOBALS['mumega_motion_test_post_thumbnails'][60] = true;

		$output = $this->render_template_part(
			'template-parts/home-knowledge.php',
			array( 'page' => $knowledge_page )
		);

		$this->assertSame( 1, preg_match_all( '/<h2\b/i', $output ) );
		$this->assertStringContainsString( 'How our topics connect', $output );
		$this->assertStringContainsString( 'A map of how our guides and reports relate.', $output );
		$this->assertStringContainsString( 'href="https://example.test/?p=60"', $output );
		$this->assertMatchesRegularExpression( '/<img\b[^>]*srcset="[^"]+"/', $output );
	}

	/**
	 * Renders the knowledge module without an image when the page has no featured media.
	 */
	public function test_home_knowledge_without_featured_media_omits_the_image(): void {
		$knowledge_page = $this->post( 60, 'How our topics connect' );

		$output = $this->render_template_part(
			'template-parts/home-knowledge.php',
			array( 'page' => $knowledge_page )
		);

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringContainsString( 'How our topics connect', $output );
	}

	/**
	 * Omits the knowledge module cleanly, without a stray heading, when no knowledge-map
	 * page exists.
	 */
	public function test_home_knowledge_without_a_page_renders_nothing(): void {
		$output = $this->render_template_part( 'template-parts/home-knowledge.php', array() );

		$this->assertSame( '', $output );
	}

	/**
	 * Places methodology, tools and knowledge between guides and the newsletter in
	 * rendered source order, completing the full lower-homepage sequence.
	 */
	public function test_home_methodology_tools_and_knowledge_render_between_guides_and_newsletter(): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['primary'] = 50;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][50]           = new WP_Term( array( 'term_id' => 50 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][50]             = array(
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 7,
			),
		);
		$GLOBALS['mumega_motion_test_terms'][7] = new WP_Term(
			array(
				'term_id'     => 7,
				'name'        => 'Field Category',
				'description' => 'A category description.',
			)
		);

		$methodology               = $this->post( 70, 'How we test WordPress plugins' );
		$methodology->post_excerpt = 'Every review follows the same repeatable steps.';

		$knowledge_page               = $this->post( 71, 'How our topics connect' );
		$knowledge_page->post_excerpt = 'A map of how our guides and reports relate.';

		$output = $this->render_editorial_home(
			array(
				'briefing'      => array( $this->post( 10, 'Lead investigation' ) ),
				'guide_groups'  => array(
					array( $this->post( 60, 'Guide Post One' ), $this->post( 61, 'Guide Post Two' ) ),
				),
				'tools'         => array(
					$this->post( 50, 'Backup Plugin Field Test' ),
					$this->post( 51, 'Caching Plugin Field Test' ),
				),
				'methodology'   => array( $methodology ),
				'knowledge_map' => array( $knowledge_page ),
				'newsletter'    => array( $this->post( 41, 'Newsletter landing page' ) ),
			)
		);

		$guides_position      = strpos( $output, 'home-guides' );
		$methodology_position = strpos( $output, 'home-methodology' );
		$tools_position       = strpos( $output, 'home-tools' );
		$knowledge_position   = strpos( $output, 'home-knowledge' );
		$newsletter_position  = strpos( $output, 'home-newsletter' );

		foreach ( array( $guides_position, $methodology_position, $tools_position, $knowledge_position, $newsletter_position ) as $position ) {
			$this->assertIsInt( $position );
		}

		$this->assertLessThan( $methodology_position, $guides_position );
		$this->assertLessThan( $tools_position, $methodology_position );
		$this->assertLessThan( $knowledge_position, $tools_position );
		$this->assertLessThan( $newsletter_position, $knowledge_position );

		$this->assertStringContainsString( 'Guide Post One', $output );
		$this->assertStringContainsString( 'How we test WordPress plugins', $output );
		$this->assertStringContainsString( 'Backup Plugin Field Test', $output );
		$this->assertStringContainsString( 'How our topics connect', $output );
	}

	/**
	 * Leaves no methodology, tools or knowledge markers or headings on the full homepage
	 * when their backing pages and categories are absent — the same honest-omission
	 * convention as the guides and coverage modules.
	 */
	public function test_home_methodology_tools_and_knowledge_omit_cleanly_when_absent(): void {
		$output = $this->render_editorial_home(
			array(
				'briefing'   => array( $this->post( 10, 'Lead investigation' ) ),
				'newsletter' => array( $this->post( 41, 'Newsletter landing page' ) ),
			)
		);

		$this->assertStringNotContainsString( 'home-methodology', $output );
		$this->assertStringNotContainsString( 'home-tools', $output );
		$this->assertStringNotContainsString( 'home-knowledge', $output );
		$this->assertStringNotContainsString( 'Tested on WordPress', $output );
		$this->assertStringNotContainsString( 'Tool intelligence', $output );
		$this->assertStringContainsString( 'home-newsletter', $output );
	}

	/**
	 * Keeps the new methodology, tools and knowledge template-part calls positioned
	 * before the newsletter section's exact non-Motion wrapper in source order.
	 */
	public function test_home_methodology_tools_and_knowledge_precede_the_newsletter_section_in_source(): void {
		$source = $this->theme_source( 'page-templates/editorial-home.php' );

		$methodology_position = strpos( $source, 'template-parts/home-methodology' );
		$tools_position       = strpos( $source, 'template-parts/home-tools' );
		$knowledge_position   = strpos( $source, 'template-parts/home-knowledge' );
		$newsletter_position  = strpos( $source, '<section class="home-newsletter">' );

		foreach ( array( $methodology_position, $tools_position, $knowledge_position, $newsletter_position ) as $position ) {
			$this->assertIsInt( $position );
		}

		$this->assertLessThan( $tools_position, $methodology_position );
		$this->assertLessThan( $knowledge_position, $tools_position );
		$this->assertLessThan( $newsletter_position, $knowledge_position );
	}

	/**
	 * Renders all nine homepage sections in one pass with every section's data
	 * fixture populated simultaneously — the individual per-section tests above
	 * each leave most sections empty, so none of them can catch a cross-section
	 * interaction (an ID collision in the shared $used_ids list, a stray global
	 * left over from an earlier section's render, a heading count that only
	 * goes wrong once every module is present at once). This is the one test
	 * that exercises the real, fully-populated homepage.
	 */
	public function test_all_nine_homepage_sections_render_together_without_interference(): void {
		$GLOBALS['mumega_motion_test_nav_menu_locations']['audiences'] = 31;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][31]            = new WP_Term( array( 'term_id' => 31 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][31]              = array(
			(object) array(
				'title'       => 'Team leads',
				'description' => 'Playbooks for people managers.',
				'url'         => 'https://example.test/audience/team-leads/',
			),
		);

		$GLOBALS['mumega_motion_test_nav_menu_locations']['primary'] = 50;
		$GLOBALS['mumega_motion_test_nav_menu_objects'][50]           = new WP_Term( array( 'term_id' => 50 ) );
		$GLOBALS['mumega_motion_test_nav_menu_items'][50]             = array(
			(object) array(
				'type'      => 'taxonomy',
				'object'    => 'category',
				'object_id' => 7,
			),
		);
		$GLOBALS['mumega_motion_test_terms'][7] = new WP_Term(
			array(
				'term_id'     => 7,
				'name'        => 'Field Category',
				'description' => 'A category description.',
			)
		);

		$page               = $this->post( 80, 'Editorial Desk' );
		$page->post_excerpt = 'A promise about what this desk covers.';
		$page->post_content = 'Read on for the full mission.';

		$guide               = $this->post( 90, 'Jordan Lee' );
		$guide->post_excerpt = 'Site editor and disclosure lead.';

		$methodology               = $this->post( 70, 'How we test WordPress plugins' );
		$methodology->post_excerpt = 'Every review follows the same repeatable steps.';

		$knowledge_page               = $this->post( 71, 'How our topics connect' );
		$knowledge_page->post_excerpt = 'A map of how our guides and reports relate.';

		$output = $this->render_editorial_home(
			array(
				'page'             => $page,
				'briefing'         => array( $this->post( 10, 'Lead investigation' ) ),
				'related'          => array( $this->post( 11, 'Related report one' ), $this->post( 12, 'Related report two' ) ),
				'guide'            => array( $guide ),
				'coverage_feature' => array( $this->post( 21, 'Feature Investigation' ) ),
				'coverage_support' => array( $this->post( 22, 'Support One' ) ),
				'guide_groups'     => array(
					array( $this->post( 60, 'Guide Post One' ), $this->post( 61, 'Guide Post Two' ) ),
				),
				'tools'            => array(
					$this->post( 50, 'Backup Plugin Field Test' ),
					$this->post( 51, 'Caching Plugin Field Test' ),
				),
				'methodology'      => array( $methodology ),
				'knowledge_map'    => array( $knowledge_page ),
				'newsletter'       => array( $this->post( 41, 'Newsletter landing page' ) ),
			)
		);

		$positions = array(
			'home-intro'       => strpos( $output, 'home-intro' ),
			'home-briefing'    => strpos( $output, 'home-briefing' ),
			'home-audiences'   => strpos( $output, 'home-audiences' ),
			'home-coverage'    => strpos( $output, 'home-coverage' ),
			'home-guides'      => strpos( $output, 'home-guides' ),
			'home-methodology' => strpos( $output, 'home-methodology' ),
			'home-tools'       => strpos( $output, 'home-tools' ),
			'home-knowledge'   => strpos( $output, 'home-knowledge' ),
			'home-newsletter'  => strpos( $output, 'home-newsletter' ),
		);

		foreach ( $positions as $section => $position ) {
			$this->assertIsInt( $position, $section . ' marker is missing from the fully-populated homepage' );
		}

		$ordered = array_values( $positions );
		for ( $i = 1, $count = count( $ordered ); $i < $count; $i++ ) {
			$this->assertLessThan(
				$ordered[ $i ],
				$ordered[ $i - 1 ],
				array_keys( $positions )[ $i - 1 ] . ' must precede ' . array_keys( $positions )[ $i ]
			);
		}

		// One representative, section-exclusive string per module confirms real
		// content rendered — not just an empty wrapper landing at the right spot.
		$this->assertStringContainsString( 'Editorial Desk', $output );
		$this->assertStringContainsString( 'Lead investigation', $output );
		$this->assertStringContainsString( 'Related report one', $output );
		$this->assertStringContainsString( 'Related report two', $output );
		$this->assertStringContainsString( 'Team leads', $output );
		$this->assertStringContainsString( 'Feature Investigation', $output );
		$this->assertStringContainsString( 'Support One', $output );
		$this->assertStringContainsString( 'Guide Post One', $output );
		$this->assertStringContainsString( 'How we test WordPress plugins', $output );
		$this->assertStringContainsString( 'Backup Plugin Field Test', $output );
		$this->assertStringContainsString( 'How our topics connect', $output );
		$this->assertStringContainsString( 'Newsletter landing page', $output );

		// The page-owned intro keeps the sole <h1> even with every module present.
		$this->assertSame( 1, preg_match_all( '/<h1\b/i', $output ) );

		// The shared $used_ids exclusion list must hold across all nine modules at
		// once: the briefing lead post (10) is deliberately reused nowhere else.
		$after_briefing = substr( $output, $positions['home-audiences'] );
		$this->assertStringNotContainsString( 'Lead investigation', $after_briefing );
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
	 * one result per matched rail-group category (only consumed when a `primary`
	 * nav menu fixture supplies matching category items), the tool-intelligence
	 * posts (only consumed when the `tools` override is explicitly supplied,
	 * mirroring `tools-reviews` category resolution), the editorial guide page,
	 * the methodology page, the knowledge map page, and finally the newsletter
	 * page.
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
			'guide_groups'     => array(),
			'guide'            => array(),
			'tools'            => array(),
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

		$post_queries = array_merge(
			array(
				$config['briefing'],
				$config['related'],
				$config['coverage_feature'],
				$config['coverage_support'],
			),
			array_values( $config['guide_groups'] )
		);

		if ( array_key_exists( 'tools', $overrides ) ) {
			$GLOBALS['mumega_motion_test_categories_by_slug']['tools-reviews'] = new WP_Term( array( 'term_id' => 9 ) );
			$post_queries[] = $config['tools'];
		}

		$GLOBALS['mumega_motion_test_post_queries'] = array_merge(
			$post_queries,
			array(
				$config['guide'],
				$config['methodology'],
				$config['knowledge_map'],
				$config['newsletter'],
			)
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
