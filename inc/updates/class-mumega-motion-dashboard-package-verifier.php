<?php
/**
 * Verified package handoff for native WordPress theme updates.
 *
 * @package Mumega_Motion
 */

/**
 * Replaces only Mumega Motion's dashboard download with a verified local ZIP.
 */
final class Mumega_Motion_Dashboard_Package_Verifier {
	private const THEME_SLUG = 'mumega-motion-theme';

	/**
	 * Fixed GitHub release client.
	 *
	 * @var object
	 */
	private $release_client;

	/**
	 * Runtime effects retained as callables for deterministic tests.
	 *
	 * @var array<string,callable>
	 */
	private $collaborators;

	/**
	 * Creates the narrow native-updater package verifier.
	 *
	 * @param object                 $release_client Fixed release discovery client.
	 * @param array<string,callable> $collaborators  Optional test collaborators.
	 */
	public function __construct( $release_client, array $collaborators = array() ) {
		$this->release_client = $release_client;
		$this->collaborators  = array_merge(
			array(
				'download' => array( $this, 'download_package' ),
				'validate' => static function ( $package, $manifest ) {
					return Mumega_Motion_Package_Validator::validate( $package, $manifest );
				},
				'cleanup'  => array( $this, 'delete_package' ),
			),
			$collaborators
		);
	}

	/**
	 * Supplies a locally verified package to Core for this theme only.
	 *
	 * Core deletes the returned path after unpacking it. This verifier deletes a
	 * path itself only when it fails before handing it to Core.
	 *
	 * @param false|string|WP_Error $reply      Existing pre-download reply.
	 * @param string                $package    Package requested by Core.
	 * @param WP_Upgrader           $upgrader   Current Core upgrader.
	 * @param array                 $hook_extra Core upgrade metadata.
	 * @return false|string|WP_Error
	 */
	public function verify_dashboard_package( $reply, $package, $upgrader, $hook_extra = array() ) {
		unset( $upgrader );

		if ( ! $this->is_mumega_motion_theme_update( $hook_extra ) ) {
			return $reply;
		}

		$manifest = $this->release_client->latest( true );

		if ( is_wp_error( $manifest ) || ! Mumega_Motion_Release_Client::valid_manifest_binding( $manifest ) ) {
			return $this->error( 'mumega_motion_dashboard_release_invalid', 'The current Mumega Motion release could not be verified.' );
		}

		if ( ! is_string( $package ) || ! hash_equals( $manifest['package_url'], $package ) ) {
			return $this->error( 'mumega_motion_dashboard_package_unverified', 'The requested theme package does not match the current verified release.' );
		}

		$download = call_user_func( $this->collaborators['download'], $manifest['package_url'] );

		if ( is_wp_error( $download ) ) {
			return $download;
		}

		if ( ! is_string( $download ) || '' === $download ) {
			return $this->error( 'mumega_motion_dashboard_download_failed', 'The theme package could not be downloaded.' );
		}

		$validation = call_user_func( $this->collaborators['validate'], $download, $manifest );

		if ( is_wp_error( $validation ) ) {
			return $this->cleanup_failed_download( $validation, $download );
		}

		if ( true !== $validation ) {
			return $this->cleanup_failed_download(
				$this->error( 'mumega_motion_dashboard_validation_failed', 'The theme package could not be validated.' ),
				$download
			);
		}

		return $download;
	}

	/**
	 * Limits the hook to Core's Mumega Motion theme update operations.
	 *
	 * @param mixed $hook_extra Core upgrade metadata.
	 * @return bool
	 */
	private function is_mumega_motion_theme_update( $hook_extra ) {
		if ( ! is_array( $hook_extra ) || ! isset( $hook_extra['theme'] ) || self::THEME_SLUG !== $hook_extra['theme'] ) {
			return false;
		}

		if ( array_key_exists( 'type', $hook_extra ) ) {
			return 'theme' === $hook_extra['type'];
		}

		return isset( $hook_extra['temp_backup'] ) &&
			is_array( $hook_extra['temp_backup'] ) &&
			isset( $hook_extra['temp_backup']['slug'], $hook_extra['temp_backup']['src'], $hook_extra['temp_backup']['dir'] ) &&
			self::THEME_SLUG === $hook_extra['temp_backup']['slug'] &&
			is_string( $hook_extra['temp_backup']['src'] ) &&
			'themes' === $hook_extra['temp_backup']['dir'];
	}

	/**
	 * Removes a local temporary package that Core never received.
	 *
	 * @param WP_Error $failure Failed verification.
	 * @param string   $package Local temporary package.
	 * @return WP_Error
	 */
	private function cleanup_failed_download( WP_Error $failure, $package ) {
		$cleaned = call_user_func( $this->collaborators['cleanup'], $package );

		if ( true === $cleaned ) {
			return $failure;
		}

		$cleanup_failure = is_wp_error( $cleaned )
			? $cleaned
			: $this->error( 'mumega_motion_dashboard_temp_cleanup_failed', 'The downloaded theme package could not be removed.' );

		return new WP_Error(
			'mumega_motion_dashboard_temp_cleanup_failed',
			__( 'The downloaded theme package could not be removed after verification failed.', 'mumega-motion' ),
			array(
				'validation_error' => $this->error_evidence( $failure ),
				'cleanup_error'    => $this->error_evidence( $cleanup_failure ),
			)
		);
	}

	/**
	 * Downloads the fixed package to a Core-managed temporary path.
	 *
	 * @param string $url Fixed immutable package URL.
	 * @return string|WP_Error
	 */
	private function download_package( $url ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return download_url( $url );
	}

	/**
	 * Deletes a local temporary package without following a link.
	 *
	 * @param string $package Temporary package path.
	 * @return true|WP_Error
	 */
	private function delete_package( $package ) {
		if ( ! is_string( $package ) || '' === $package || ! file_exists( $package ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- This verifier owns only failed local download cleanup.
		if ( is_link( $package ) || ! is_file( $package ) || ! unlink( $package ) ) {
			return $this->error( 'mumega_motion_dashboard_temp_cleanup_failed', 'The downloaded theme package could not be removed.' );
		}

		return true;
	}

	/**
	 * Converts an error to serializable cleanup evidence.
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
	 * Creates a verifier-specific error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Internal error helper translates caller-owned literals.
		return new WP_Error( $code, __( $message, 'mumega-motion' ) );
	}
}
