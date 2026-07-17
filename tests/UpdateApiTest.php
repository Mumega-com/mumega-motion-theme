<?php
/**
 * Tests for fixed-purpose theme update administration APIs.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises REST, MCPWP, and native dashboard update boundaries.
 */
final class UpdateApiTest extends TestCase {
	/**
	 * Resets the observable WordPress registrations.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_filters']           = array();
		$GLOBALS['mumega_motion_test_actions']           = array();
		$GLOBALS['mumega_motion_test_routes']            = array();
		$GLOBALS['mumega_motion_test_tools']             = array();
		$GLOBALS['mumega_motion_test_capabilities']      = array();
		$GLOBALS['mumega_motion_test_download_requests'] = array();
		$GLOBALS['mumega_motion_test_download_results']  = array();
	}

	/**
	 * Registers exactly two capability-gated, fixed-purpose REST routes.
	 */
	public function test_registers_only_fixed_update_and_rollback_rest_routes(): void {
		$updater = new Mumega_Motion_Update_Api_Test_Updater();
		$api     = new Mumega_Motion_Update_Api( $updater, new Mumega_Motion_Update_Api_Test_Release_Client(), false );

		$api->register_rest_routes();

		$this->assertCount( 2, $GLOBALS['mumega_motion_test_routes'] );
		$this->assertSame( 'mumega-motion/v1', $GLOBALS['mumega_motion_test_routes'][0]['namespace'] );
		$this->assertSame( '/update', $GLOBALS['mumega_motion_test_routes'][0]['route'] );
		$this->assertSame( WP_REST_Server::CREATABLE, $GLOBALS['mumega_motion_test_routes'][0]['args']['methods'] );
		$this->assertSame( '/rollback', $GLOBALS['mumega_motion_test_routes'][1]['route'] );
		$this->assertSame( WP_REST_Server::CREATABLE, $GLOBALS['mumega_motion_test_routes'][1]['args']['methods'] );

		foreach ( $GLOBALS['mumega_motion_test_routes'] as $route ) {
			$this->assertArrayHasKey( 'permission_callback', $route['args'] );
			$this->assertFalse( call_user_func( $route['args']['permission_callback'] ) );
			$this->assertArrayNotHasKey( 'package_url', $route['args']['args'] );
			$this->assertArrayNotHasKey( 'slug', $route['args']['args'] );
			$this->assertArrayNotHasKey( 'checksum', $route['args']['args'] );
			$this->assertArrayNotHasKey( 'backup_path', $route['args']['args'] );
		}

		$GLOBALS['mumega_motion_test_capabilities']['update_themes'] = true;
		$this->assertTrue( call_user_func( $GLOBALS['mumega_motion_test_routes'][0]['args']['permission_callback'] ) );
	}

	/**
	 * Delegates only to fixed updater operations and sanitizes the sole boolean.
	 */
	public function test_rest_callbacks_delegate_only_to_fixed_updater_operations(): void {
		$updater = new Mumega_Motion_Update_Api_Test_Updater();
		$api     = new Mumega_Motion_Update_Api( $updater, new Mumega_Motion_Update_Api_Test_Release_Client(), false );
		$request = new WP_REST_Request();
		$request->set_param( 'force_check', 'false' );
		$request->set_param( 'package_url', 'https://evil.example/theme.zip' );
		$request->set_param( 'slug', 'evil-theme' );

		$this->assertSame( array( 'status' => 'updated' ), $api->update( $request )->get_data() );
		$this->assertSame( array( false ), $updater->update_calls );
		$this->assertSame( array( 'status' => 'rolled_back' ), $api->rollback( new WP_REST_Request() )->get_data() );
		$this->assertSame( 1, $updater->rollback_calls );
	}

	/**
	 * Registers only the expected elevated MCPWP tool definitions.
	 */
	public function test_registers_exact_admin_mcp_tools_when_custom_scope_support_is_enabled(): void {
		$api = new Mumega_Motion_Update_Api(
			new Mumega_Motion_Update_Api_Test_Updater(),
			new Mumega_Motion_Update_Api_Test_Release_Client(),
			true
		);
		$api->register();

		$tools = apply_filters( 'mcpwp_register_tools', array() );

		$this->assertCount( 2, $tools );
		$this->assertSame( 'wp_update_mumega_motion', $tools[0]['name'] );
		$this->assertSame( 'admin', $tools[0]['category'] );
		$this->assertTrue( $tools[0]['destructive'] );
		$this->assertTrue( $tools[0]['open_world'] );
		$this->assertSame( '/mumega-motion/v1/update', $tools[0]['rest_path'] );
		$this->assertSame( array( 'force_check' => array( 'type' => 'boolean' ) ), $tools[0]['input_props'] );
		$this->assertSame( 'wp_rollback_mumega_motion', $tools[1]['name'] );
		$this->assertSame( 'admin', $tools[1]['category'] );
		$this->assertTrue( $tools[1]['destructive'] );
		$this->assertFalse( $tools[1]['open_world'] );
		$this->assertSame( array(), $tools[1]['input_props'] );

		$this->assertSame( 'admin', apply_filters( 'mcpwp_required_scope_for_tool', 'read', 'wp_update_mumega_motion' ) );
		$this->assertSame( 'admin', apply_filters( 'mcpwp_required_scope_for_tool', 'write', 'wp_rollback_mumega_motion' ) );
		$this->assertSame( 'write', apply_filters( 'mcpwp_required_scope_for_tool', 'write', 'wp_update_post' ) );
	}

	/**
	 * Keeps dashboard discovery available while custom MCPWP support is absent.
	 */
	public function test_skips_mcpwp_tools_without_custom_scope_support_but_keeps_dashboard_hooks(): void {
		$api = new Mumega_Motion_Update_Api(
			new Mumega_Motion_Update_Api_Test_Updater(),
			new Mumega_Motion_Update_Api_Test_Release_Client(),
			false
		);
		$api->register();

		$this->assertArrayHasKey( 'rest_api_init', $GLOBALS['mumega_motion_test_actions'] );
		$this->assertArrayHasKey( 'pre_set_site_transient_update_themes', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayHasKey( 'themes_api', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayHasKey( 'upgrader_pre_download', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayNotHasKey( 'mcpwp_register_tools', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayNotHasKey( 'mcpwp_required_scope_for_tool', $GLOBALS['mumega_motion_test_filters'] );
	}

	/**
	 * Verifies the bootstrap's feature detection enables only the optional
	 * MCPWP integration when the installed feature flag explicitly opts in.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_bootstrap_detects_the_explicit_mcpwp_scope_feature_flag(): void {
		define( 'MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER', true );
		require dirname( __DIR__ ) . '/inc/updates/bootstrap.php';

		$this->assertArrayHasKey( 'pre_set_site_transient_update_themes', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayHasKey( 'upgrader_pre_download', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayHasKey( 'mcpwp_register_tools', $GLOBALS['mumega_motion_test_filters'] );
		$this->assertArrayHasKey( 'mcpwp_required_scope_for_tool', $GLOBALS['mumega_motion_test_filters'] );
	}

	/**
	 * Provides native dashboard update metadata only from a verified fixed manifest.
	 */
	public function test_dashboard_update_discovery_and_theme_information_use_fixed_verified_manifest(): void {
		$release = new Mumega_Motion_Update_Api_Test_Release_Client();
		$api     = new Mumega_Motion_Update_Api( new Mumega_Motion_Update_Api_Test_Updater(), $release, false );
		$state   = (object) array(
			'checked'  => array( 'mumega-motion-theme' => '0.1.100' ),
			'response' => array(),
		);

		$result = $api->discover_theme_update( $state );

		$this->assertSame( '0.1.101', $result->response['mumega-motion-theme']['new_version'] );
		$this->assertSame( 'https://github.com/Mumega-com/mumega-motion-theme', $result->response['mumega-motion-theme']['url'] );
		$this->assertSame( $release->manifest['package_url'], $result->response['mumega-motion-theme']['package'] );
		$this->assertSame( '6.5', $result->response['mumega-motion-theme']['requires'] );
		$this->assertSame( '7.4', $result->response['mumega-motion-theme']['requires_php'] );

		$info = $api->theme_information( false, 'theme_information', (object) array( 'slug' => 'mumega-motion-theme' ) );
		$this->assertSame( 'mumega-motion-theme', $info->slug );
		$this->assertSame( '0.1.101', $info->version );
		$this->assertSame( $release->manifest['package_url'], $info->download_link );
		$this->assertSame( false, $api->theme_information( false, 'theme_information', (object) array( 'slug' => 'other-theme' ) ) );
	}

	/**
	 * Rejects a dashboard download whose bytes do not match the freshly
	 * revalidated fixed manifest, before WordPress can unpack or install it.
	 */
	public function test_dashboard_core_download_rejects_a_checksum_mismatch_and_removes_the_temporary_file(): void {
		$release = new Mumega_Motion_Update_Api_Test_Release_Client();
		$api     = new Mumega_Motion_Update_Api( new Mumega_Motion_Update_Api_Test_Updater(), $release, false );
		$package = tempnam( sys_get_temp_dir(), 'mumega-motion-dashboard-' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture bytes must deliberately fail checksum validation.
		file_put_contents( $package, 'tampered dashboard package' );
		$GLOBALS['mumega_motion_test_download_results'][] = $package;
		$api->register();

		$result = apply_filters(
			'upgrader_pre_download',
			false,
			$release->manifest['package_url'],
			new stdClass(),
			array(
				'type'  => 'theme',
				'theme' => 'mumega-motion-theme',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'mumega_motion_package_checksum_mismatch', $result->get_error_code() );
		$this->assertSame( array( true ), $release->latest_calls );
		$this->assertSame( $release->manifest['package_url'], $GLOBALS['mumega_motion_test_download_requests'][0]['url'] );
		$this->assertFileDoesNotExist( $package );
	}

	/**
	 * Gives Core a locally downloaded ZIP only after it is verified against the
	 * freshly revalidated fixed manifest. Core then owns normal temp cleanup.
	 */
	public function test_dashboard_core_download_hands_a_verified_package_to_core_for_cleanup(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for the verified package fixture.' );
		}

		$release                     = new Mumega_Motion_Update_Api_Test_Release_Client();
		$api                         = new Mumega_Motion_Update_Api( new Mumega_Motion_Update_Api_Test_Updater(), $release, false );
		$package                     = $this->create_valid_dashboard_package();
		$release->manifest['sha256'] = hash_file( 'sha256', $package );
		$GLOBALS['mumega_motion_test_download_results'][] = $package;
		$api->register();

		$result = apply_filters(
			'upgrader_pre_download',
			false,
			$release->manifest['package_url'],
			new stdClass(),
			array(
				'type'  => 'theme',
				'theme' => 'mumega-motion-theme',
			)
		);

		$this->assertSame( $package, $result );
		$this->assertFileExists( $package );
		$this->assertSame( array( true ), $release->latest_calls );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Simulates Core's ownership of successful package cleanup.
		unlink( $package );
	}

	/**
	 * Builds the smallest valid theme package for the dashboard handoff test.
	 *
	 * @return string
	 */
	private function create_valid_dashboard_package(): string {
		$path    = tempnam( sys_get_temp_dir(), 'mumega-motion-dashboard-' );
		$archive = new ZipArchive();

		$this->assertTrue( $archive->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		foreach ( array( 'style.css', 'functions.php', 'index.php', 'build/index.js', 'build/index.asset.php' ) as $file ) {
			$this->assertTrue( $archive->addFromString( 'mumega-motion-theme/' . $file, 'fixture' ) );
		}
		$this->assertTrue( $archive->close() );

		return $path;
	}
}
