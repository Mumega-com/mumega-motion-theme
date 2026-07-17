<?php
/**
 * Fixed-purpose update administration endpoints and integrations.
 *
 * @package Mumega_Motion
 */

/**
 * Exposes the verified Mumega Motion updater without accepting update sources.
 */
final class Mumega_Motion_Update_Api {
	private const THEME_SLUG     = 'mumega-motion-theme';
	private const REST_NAMESPACE = 'mumega-motion/v1';
	private const REPOSITORY_URL = 'https://github.com/Mumega-com/mumega-motion-theme';

	/**
	 * Fixed updater transaction.
	 *
	 * @var object
	 */
	private $updater;

	/**
	 * Fixed GitHub release client.
	 *
	 * @var object
	 */
	private $release_client;

	/**
	 * Native dashboard package verifier.
	 *
	 * @var Mumega_Motion_Dashboard_Package_Verifier
	 */
	private $dashboard_package_verifier;

	/**
	 * Whether the installed MCPWP version supports custom scope elevation.
	 *
	 * @var bool
	 */
	private $mcpwp_scope_support;

	/**
	 * Creates the controller with its fixed internal collaborators.
	 *
	 * @param object $updater              Fixed update transaction.
	 * @param object $release_client       Fixed release discovery client.
	 * @param bool   $mcpwp_scope_support          Whether MCPWP custom scope support is available.
	 * @param object $dashboard_package_verifier   Optional native dashboard verifier.
	 */
	public function __construct( $updater, $release_client, $mcpwp_scope_support = false, $dashboard_package_verifier = null ) {
		$this->updater                    = $updater;
		$this->release_client             = $release_client;
		$this->mcpwp_scope_support        = true === $mcpwp_scope_support;
		$this->dashboard_package_verifier = is_object( $dashboard_package_verifier )
			? $dashboard_package_verifier
			: new Mumega_Motion_Dashboard_Package_Verifier( $release_client );
	}

	/**
	 * Registers WordPress dashboard integration and, when supported, MCPWP tools.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'discover_theme_update' ) );
		add_filter( 'themes_api', array( $this, 'theme_information' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this->dashboard_package_verifier, 'verify_dashboard_package' ), 10, 4 );

		if ( ! $this->mcpwp_scope_support ) {
			return;
		}

		add_filter( 'mcpwp_register_tools', array( $this, 'register_mcpwp_tools' ) );
		add_filter( 'mcpwp_required_scope_for_tool', array( $this, 'required_mcpwp_scope' ), 10, 2 );
	}

	/**
	 * Registers the two fixed-purpose, privileged REST operations.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'can_update_themes' ),
				'args'                => array(
					'force_check' => array(
						'type'              => 'boolean',
						'required'          => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback' ),
				'permission_callback' => array( $this, 'can_update_themes' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Gates both operations with WordPress's native theme-update capability.
	 *
	 * @return bool
	 */
	public function can_update_themes() {
		return current_user_can( 'update_themes' );
	}

	/**
	 * Runs the updater against its fixed release repository.
	 *
	 * @param WP_REST_Request $request REST request containing only force_check.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( $request ) {
		$force_check = true;

		if ( is_object( $request ) && method_exists( $request, 'get_param' ) && null !== $request->get_param( 'force_check' ) ) {
			$force_check = rest_sanitize_boolean( $request->get_param( 'force_check' ) );
		}

		return rest_ensure_response( $this->updater->update( $force_check ) );
	}

	/**
	 * Rolls back using only the updater's newest local backup.
	 *
	 * @param WP_REST_Request $request Unused fixed-purpose REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollback( $request ) {
		unset( $request );

		return rest_ensure_response( $this->updater->rollback() );
	}

	/**
	 * Appends exactly the two fixed update tools to MCPWP's registry.
	 *
	 * @param array $tools Existing MCPWP tool registrations.
	 * @return array
	 */
	public function register_mcpwp_tools( $tools ) {
		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$tools[] = array(
			'name'        => 'wp_update_mumega_motion',
			'description' => 'Install the latest verified Mumega Motion theme release from its fixed repository.',
			'callback'    => array( $this, 'mcp_update' ),
			'method'      => 'POST',
			'category'    => 'admin',
			'input_props' => array(
				'force_check' => array( 'type' => 'boolean' ),
			),
			'destructive' => true,
			'open_world'  => true,
		);
		$tools[] = array(
			'name'        => 'wp_rollback_mumega_motion',
			'description' => 'Restore the newest verified local Mumega Motion theme backup.',
			'callback'    => array( $this, 'mcp_rollback' ),
			'method'      => 'POST',
			'category'    => 'admin',
			'input_props' => array(),
			'destructive' => true,
			'open_world'  => false,
		);

		return $tools;
	}

	/**
	 * Runs the fixed update transaction after MCPWP has authorized the tool.
	 *
	 * This callback deliberately does not reuse the public REST permission
	 * callback: direct REST calls require a WordPress user with update_themes,
	 * while MCPWP has already applied its exact admin-scope and category gates.
	 *
	 * @param array $arguments Validated MCP tool arguments.
	 * @return array|WP_Error Fixed updater result.
	 */
	public function mcp_update( $arguments = array() ) {
		$force_check = true;

		if ( is_array( $arguments ) && array_key_exists( 'force_check', $arguments ) ) {
			$force_check = rest_sanitize_boolean( $arguments['force_check'] );
		}

		return $this->updater->update( $force_check );
	}

	/**
	 * Runs the fixed rollback transaction after MCPWP has authorized the tool.
	 *
	 * @param array $arguments Validated MCP tool arguments, intentionally unused.
	 * @return array|WP_Error Fixed updater result.
	 */
	public function mcp_rollback( $arguments = array() ) {
		unset( $arguments );

		return $this->updater->rollback();
	}

	/**
	 * Raises only the two fixed update tools to MCPWP's admin scope.
	 *
	 * @param string $scope     Inferred MCPWP scope.
	 * @param string $tool_name Tool being authorized.
	 * @return string
	 */
	public function required_mcpwp_scope( $scope, $tool_name ) {
		return in_array( $tool_name, array( 'wp_update_mumega_motion', 'wp_rollback_mumega_motion' ), true ) ? 'admin' : $scope;
	}

	/**
	 * Adds a verified fixed-repository update to the native dashboard transient.
	 *
	 * @param object $transient WordPress theme-update transient.
	 * @return object
	 */
	public function discover_theme_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) || ! isset( $transient->checked[ self::THEME_SLUG ] ) ) {
			return $transient;
		}

		$installed_version = $transient->checked[ self::THEME_SLUG ];
		$manifest          = $this->verified_manifest();

		if ( ! is_array( $manifest ) || ! is_string( $installed_version ) || 0 >= version_compare( $manifest['version'], $installed_version ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ self::THEME_SLUG ] = $this->theme_update_data( $manifest );

		return $transient;
	}

	/**
	 * Supplies native theme-detail metadata from the same verified manifest.
	 *
	 * @param mixed  $result Existing themes API result.
	 * @param string $action Requested themes API action.
	 * @param object $args   Requested theme details.
	 * @return mixed
	 */
	public function theme_information( $result, $action, $args ) {
		if ( 'theme_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || self::THEME_SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = $this->verified_manifest();

		if ( ! is_array( $manifest ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Mumega Motion',
			'slug'          => self::THEME_SLUG,
			'version'       => $manifest['version'],
			'homepage'      => self::REPOSITORY_URL,
			'download_link' => $manifest['package_url'],
			'requires'      => $manifest['requires_wp'],
			'requires_php'  => $manifest['requires_php'],
			'sections'      => array(
				'description' => 'Verified Mumega Motion theme release from its fixed GitHub repository.',
			),
		);
	}

	/**
	 * Loads a release only when its fixed immutable bindings remain valid.
	 *
	 * @return array|null
	 */
	private function verified_manifest() {
		$manifest = $this->release_client->latest();

		if ( is_wp_error( $manifest ) || ! Mumega_Motion_Release_Client::valid_manifest_binding( $manifest ) ) {
			return null;
		}

		if ( ! isset( $manifest['requires_wp'], $manifest['requires_php'] ) || ! is_string( $manifest['requires_wp'] ) || ! is_string( $manifest['requires_php'] ) ) {
			return null;
		}

		return $manifest;
	}

	/**
	 * Converts a verified manifest into WordPress's native update shape.
	 *
	 * @param array $manifest Verified normalized release manifest.
	 * @return array
	 */
	private function theme_update_data( array $manifest ) {
		return array(
			'theme'        => self::THEME_SLUG,
			'new_version'  => $manifest['version'],
			'url'          => self::REPOSITORY_URL,
			'package'      => $manifest['package_url'],
			'requires'     => $manifest['requires_wp'],
			'requires_php' => $manifest['requires_php'],
		);
	}
}
