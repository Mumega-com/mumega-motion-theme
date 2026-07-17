<?php
/**
 * Verified Mumega Motion update and rollback transactions.
 *
 * @package Mumega_Motion
 */

/**
 * Updates the fixed Mumega Motion theme and recovers from failed changes.
 */
final class Mumega_Motion_Updater {
	private const THEME_SLUG     = 'mumega-motion-theme';
	private const REQUIRED_FILES = array(
		'style.css',
		'functions.php',
		'index.php',
		'build/index.js',
		'build/index.asset.php',
	);

	/**
	 * Runtime effects, retained as callables to support deterministic tests.
	 *
	 * @var array<string,callable>
	 */
	private $collaborators;

	/**
	 * Creates an updater. Collaborators are internal runtime effects, not input
	 * accepted by either update operation.
	 *
	 * @param array<string,callable> $collaborators Optional test collaborators.
	 */
	public function __construct( array $collaborators = array() ) {
		$this->collaborators = array_merge( $this->default_collaborators(), $collaborators );
	}

	/**
	 * Downloads, verifies, installs, and verifies the fixed release transaction.
	 *
	 * @param bool $force_check Whether release discovery bypasses its cache.
	 * @return array|WP_Error
	 */
	public function update( $force_check = true ) {
		$installed = $this->inspect();

		if ( is_wp_error( $installed ) ) {
			return $installed;
		}

		if ( self::THEME_SLUG !== $installed['slug'] ) {
			return $this->error( 'mumega_motion_update_invalid_theme', 'The active theme is not Mumega Motion.' );
		}

		$manifest = call_user_func( $this->collaborators['release'], (bool) $force_check );

		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		if ( ! $this->valid_manifest( $manifest ) ) {
			return $this->error( 'mumega_motion_update_invalid_release', 'The release manifest is invalid.' );
		}

		if ( version_compare( $manifest['version'], $installed['version'], '<=' ) ) {
			return array(
				'status'           => 'up_to_date',
				'previous_version' => $installed['version'],
				'current_version'  => $installed['version'],
				'release_tag'      => $manifest['release_tag'],
				'verified'         => true,
			);
		}

		$package = call_user_func( $this->collaborators['download'], $manifest['package_url'] );

		if ( is_wp_error( $package ) ) {
			return $package;
		}

		if ( ! is_string( $package ) || '' === $package ) {
			return $this->error( 'mumega_motion_update_download_failed', 'The theme package could not be downloaded.' );
		}

		$result = call_user_func( $this->collaborators['validate'], $package, $manifest );

		if ( is_wp_error( $result ) ) {
			return $this->cleanup_result( $result, $package );
		}

		if ( true !== $result ) {
			return $this->cleanup_result( $this->error( 'mumega_motion_update_validation_failed', 'The theme package could not be validated.' ), $package );
		}

		$backup = call_user_func( $this->collaborators['backup_create'], $installed['directory'], $installed['version'] );

		if ( is_wp_error( $backup ) ) {
			return $this->cleanup_result( $backup, $package );
		}

		if ( ! is_array( $backup ) || empty( $backup['id'] ) || ! is_string( $backup['id'] ) ) {
			return $this->cleanup_result( $this->error( 'mumega_motion_update_backup_failed', 'The installed theme could not be backed up.' ), $package );
		}

		$result = call_user_func( $this->collaborators['install'], $package, $manifest );

		if ( is_wp_error( $result ) ) {
			return $this->cleanup_result( $this->recover_update_failure( $result, $backup, $installed ), $package );
		}

		if ( true !== $result ) {
			return $this->cleanup_result( $this->recover_update_failure( $this->error( 'mumega_motion_update_install_failed', 'The theme package could not be installed.' ), $backup, $installed ), $package );
		}

		call_user_func( $this->collaborators['flush'] );
		$updated      = $this->inspect();
		$verification = $this->verify_updated_theme( $updated, $manifest );

		if ( is_wp_error( $verification ) ) {
			return $this->cleanup_result( $this->recover_update_failure( $verification, $backup, $installed ), $package );
		}

		$result = call_user_func( $this->collaborators['backup_prune'] );

		if ( is_wp_error( $result ) || true !== $result ) {
			$failure = is_wp_error( $result ) ? $result : $this->error( 'mumega_motion_update_prune_failed', 'Old backups could not be pruned.' );
			return $this->cleanup_result( $this->recover_update_failure( $failure, $backup, $installed ), $package );
		}

		return $this->cleanup_result(
			array(
				'status'           => 'updated',
				'previous_version' => $installed['version'],
				'current_version'  => $updated['version'],
				'release_tag'      => $manifest['release_tag'],
				'backup_id'        => $backup['id'],
				'checksum'         => $manifest['sha256'],
				'verified'         => true,
			),
			$package,
			$backup,
			$installed
		);
	}

	/**
	 * Restores only the newest local backup and recovers the pre-rollback state
	 * if the restored theme cannot be verified.
	 *
	 * @return array|WP_Error
	 */
	public function rollback() {
		$installed = $this->inspect();

		if ( is_wp_error( $installed ) ) {
			return $installed;
		}

		if ( self::THEME_SLUG !== $installed['slug'] ) {
			return $this->error( 'mumega_motion_rollback_invalid_theme', 'The active theme is not Mumega Motion.' );
		}

		$prior = call_user_func( $this->collaborators['backup_latest'] );

		if ( is_wp_error( $prior ) ) {
			return $prior;
		}

		if ( ! is_array( $prior ) || empty( $prior['id'] ) || empty( $prior['version'] ) ) {
			return $this->error( 'mumega_motion_rollback_backup_invalid', 'The newest backup is invalid.' );
		}

		$safety = call_user_func( $this->collaborators['backup_create'], $installed['directory'], $installed['version'] );

		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! is_array( $safety ) || empty( $safety['id'] ) || ! is_string( $safety['id'] ) ) {
			return $this->error( 'mumega_motion_rollback_safety_backup_failed', 'The current theme could not be backed up before rollback.' );
		}

		$restored = call_user_func( $this->collaborators['backup_restore'], $prior['id'], $installed['directory'] );

		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		call_user_func( $this->collaborators['flush'] );
		$inspection   = $this->inspect();
		$verification = $this->verify_restored_theme( $inspection, $prior['version'] );

		if ( ! is_wp_error( $verification ) ) {
			return array(
				'status'           => 'rolled_back',
				'previous_version' => $installed['version'],
				'current_version'  => $inspection['version'],
				'backup_id'        => $prior['id'],
				'safety_backup_id' => $safety['id'],
				'verified'         => true,
			);
		}

		$recovered = call_user_func( $this->collaborators['backup_restore'], $safety['id'], $installed['directory'] );

		if ( is_wp_error( $recovered ) ) {
			return new WP_Error(
				'mumega_motion_rollback_and_recovery_failed',
				__( 'Rollback verification failed and the safety backup could not be restored.', 'mumega-motion' ),
				array(
					'rollback_error' => $this->error_evidence( $verification ),
					'recovery_error' => $this->error_evidence( $recovered ),
					'safety_backup'  => $safety,
				)
			);
		}

		return new WP_Error(
			'mumega_motion_rollback_failed_recovered',
			__( 'Rollback verification failed; the current theme was restored from its safety backup.', 'mumega-motion' ),
			array(
				'rollback_error' => $this->error_evidence( $verification ),
				'safety_backup'  => $safety,
			)
		);
	}

	/**
	 * Recovers a failed update from its transaction backup.
	 *
	 * @param WP_Error $failure   Failed update operation.
	 * @param array    $backup    Transaction backup metadata.
	 * @param array    $installed Pre-update inspection.
	 * @return WP_Error
	 */
	private function recover_update_failure( WP_Error $failure, array $backup, array $installed ) {
		$restored = call_user_func( $this->collaborators['backup_restore'], $backup['id'], $installed['directory'] );

		if ( is_wp_error( $restored ) ) {
			return new WP_Error(
				'mumega_motion_update_and_restore_failed',
				__( 'The theme update failed and its automatic restore also failed.', 'mumega-motion' ),
				array(
					'update_error'  => $this->error_evidence( $failure ),
					'restore_error' => $this->error_evidence( $restored ),
					'backup'        => $backup,
				)
			);
		}

		return new WP_Error(
			'mumega_motion_update_failed_restored',
			__( 'The theme update failed and the previous version was restored automatically.', 'mumega-motion' ),
			array(
				'update_error' => $this->error_evidence( $failure ),
				'backup'       => $backup,
				'restored'     => is_array( $restored ) ? $restored : array(),
			)
		);
	}

	/**
	 * Deletes a downloaded package and gives cleanup failures transaction evidence.
	 *
	 * @param array|WP_Error $result    Operation result.
	 * @param string         $package   Downloaded temporary package.
	 * @param array|null     $backup    Transaction backup, when one exists.
	 * @param array|null     $installed Pre-update inspection, when one exists.
	 * @return array|WP_Error
	 */
	private function cleanup_result( $result, $package, $backup = null, $installed = null ) {
		$cleaned = call_user_func( $this->collaborators['cleanup'], $package );

		if ( true === $cleaned ) {
			return $result;
		}

		$failure = is_wp_error( $cleaned ) ? $cleaned : $this->error( 'mumega_motion_update_temp_cleanup_failed', 'The downloaded package could not be removed.' );

		if ( is_array( $backup ) && is_array( $installed ) && ! is_wp_error( $result ) ) {
			return $this->recover_update_failure( $failure, $backup, $installed );
		}

		return new WP_Error(
			'mumega_motion_update_temp_cleanup_failed',
			__( 'The downloaded theme package could not be removed.', 'mumega-motion' ),
			array(
				'cleanup_error' => $this->error_evidence( $failure ),
				'operation'     => is_wp_error( $result ) ? $this->error_evidence( $result ) : $result,
			)
		);
	}

	/**
	 * Reads the currently active theme through the injected inspector.
	 *
	 * @return array|WP_Error
	 */
	private function inspect() {
		$inspection = call_user_func( $this->collaborators['inspect'] );

		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		if (
			! is_array( $inspection ) ||
			! isset( $inspection['slug'], $inspection['version'], $inspection['directory'], $inspection['required_files'] ) ||
			! is_string( $inspection['slug'] ) ||
			! is_string( $inspection['version'] ) ||
			! is_string( $inspection['directory'] ) ||
			! is_bool( $inspection['required_files'] )
		) {
			return $this->error( 'mumega_motion_update_inspection_failed', 'The installed theme could not be inspected.' );
		}

		return $inspection;
	}

	/**
	 * Verifies all update postconditions against the immutable manifest.
	 *
	 * @param array|WP_Error $inspection Installed theme inspection.
	 * @param array          $manifest   Fixed release manifest.
	 * @return true|WP_Error
	 */
	private function verify_updated_theme( $inspection, array $manifest ) {
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		if ( self::THEME_SLUG !== $inspection['slug'] ) {
			return $this->error( 'mumega_motion_update_inactive_theme', 'The updated Mumega Motion theme is not active.' );
		}

		if ( $manifest['version'] !== $inspection['version'] ) {
			return $this->error( 'mumega_motion_update_version_mismatch', 'The installed theme version does not match the release.' );
		}

		if ( ! $inspection['required_files'] ) {
			return $this->error( 'mumega_motion_update_required_files_missing', 'The updated theme is missing required files.' );
		}

		return true;
	}

	/**
	 * Verifies rollback postconditions.
	 *
	 * @param array|WP_Error $inspection Installed theme inspection.
	 * @param string         $version    Expected backup version.
	 * @return true|WP_Error
	 */
	private function verify_restored_theme( $inspection, $version ) {
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		if ( self::THEME_SLUG !== $inspection['slug'] ) {
			return $this->error( 'mumega_motion_rollback_inactive_theme', 'The restored Mumega Motion theme is not active.' );
		}

		if ( $version !== $inspection['version'] ) {
			return $this->error( 'mumega_motion_rollback_version_mismatch', 'The restored theme version does not match the backup.' );
		}

		if ( ! $inspection['required_files'] ) {
			return $this->error( 'mumega_motion_rollback_required_files_missing', 'The restored theme is missing required files.' );
		}

		return true;
	}

	/**
	 * Ensures release data is sufficient for the updater transaction.
	 *
	 * @param mixed $manifest Candidate manifest.
	 * @return bool
	 */
	private function valid_manifest( $manifest ) {
		return is_array( $manifest ) &&
			isset( $manifest['slug'], $manifest['version'], $manifest['package_url'], $manifest['sha256'], $manifest['release_tag'] ) &&
			self::THEME_SLUG === $manifest['slug'] &&
			is_string( $manifest['version'] ) &&
			is_string( $manifest['package_url'] ) &&
			is_string( $manifest['release_tag'] ) &&
			1 === preg_match( '/^[a-f0-9]{64}$/', $manifest['sha256'] );
	}

	/**
	 * Converts a WordPress error to serializable operation evidence.
	 *
	 * @param WP_Error $error Error value.
	 * @return array
	 */
	private function error_evidence( WP_Error $error ) {
		return array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'data'    => $error->get_error_data(),
		);
	}

	/**
	 * Creates an updater-specific error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Internal error helper translates caller-owned literals.
		return new WP_Error( $code, __( $message, 'mumega-motion' ) );
	}

	/**
	 * Builds production WordPress runtime effects.
	 *
	 * @return array<string,callable>
	 */
	private function default_collaborators() {
		$store = new Mumega_Motion_Backup_Store();

		return array(
			'release'        => static function ( $force ) {
				return ( new Mumega_Motion_Release_Client() )->latest( $force );
			},
			'download'       => array( $this, 'download_package' ),
			'validate'       => static function ( $package, $manifest ) {
				return Mumega_Motion_Package_Validator::validate( $package, $manifest );
			},
			'backup_create'  => array( $store, 'create' ),
			'backup_restore' => array( $store, 'restore' ),
			'backup_latest'  => array( $store, 'latest' ),
			'backup_prune'   => array( $store, 'prune' ),
			'install'        => array( $this, 'install_package' ),
			'flush'          => array( $this, 'flush_theme_caches' ),
			'inspect'        => array( $this, 'inspect_active_theme' ),
			'cleanup'        => array( $this, 'delete_package' ),
		);
	}

	/**
	 * Downloads an immutable package using WordPress's temporary downloader.
	 *
	 * @param string $url Immutable release package URL.
	 * @return string|WP_Error
	 */
	private function download_package( $url ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return download_url( $url );
	}

	/**
	 * Installs a validated package with WordPress's theme upgrader overwrite mode.
	 *
	 * @param string $package Local validated package path.
	 * @param array  $manifest Validated release manifest.
	 * @return true|WP_Error
	 */
	private function install_package( $package, array $manifest ) {
		unset( $manifest );

		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install(
			$package,
			array(
				'overwrite_package' => true,
				'clear_destination' => true,
				'clear_working'     => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true === $result ? true : $this->error( 'mumega_motion_update_install_failed', 'The theme package could not be installed.' );
	}

	/**
	 * Clears cached theme metadata after a filesystem mutation.
	 *
	 * @return void
	 */
	private function flush_theme_caches() {
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache( true );
		}
	}

	/**
	 * Inspects the active stylesheet and required installed files.
	 *
	 * @return array|WP_Error
	 */
	private function inspect_active_theme() {
		if ( ! function_exists( 'get_stylesheet' ) || ! function_exists( 'get_stylesheet_directory' ) || ! function_exists( 'wp_get_theme' ) ) {
			return $this->error( 'mumega_motion_update_inspection_failed', 'The active theme could not be inspected.' );
		}

		$slug      = get_stylesheet();
		$directory = get_stylesheet_directory();
		$theme     = wp_get_theme( $slug );

		if ( ! is_string( $slug ) || ! is_string( $directory ) || ! is_object( $theme ) || ! method_exists( $theme, 'get' ) ) {
			return $this->error( 'mumega_motion_update_inspection_failed', 'The active theme could not be inspected.' );
		}

		$required_files = true;

		foreach ( self::REQUIRED_FILES as $file ) {
			$path = $directory . '/' . $file;

			if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || 0 === filesize( $path ) ) {
				$required_files = false;
				break;
			}
		}

		return array(
			'slug'           => $slug,
			'version'        => (string) $theme->get( 'Version' ),
			'directory'      => $directory,
			'required_files' => $required_files,
		);
	}

	/**
	 * Deletes a temporary downloaded package without following a link.
	 *
	 * @param string $package Temporary package path.
	 * @return true|WP_Error
	 */
	private function delete_package( $package ) {
		if ( ! is_string( $package ) || '' === $package || ! file_exists( $package ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- The downloaded temporary package must be removed on every exit path.
		if ( is_link( $package ) || ! is_file( $package ) || ! unlink( $package ) ) {
			return $this->error( 'mumega_motion_update_temp_cleanup_failed', 'The downloaded theme package could not be removed.' );
		}

		return true;
	}
}
