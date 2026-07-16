<?php
/**
 * Protected, bounded local theme backups.
 *
 * @package Mumega_Motion
 */

/**
 * Creates and restores local backups for the Mumega Motion update transaction.
 */
class Mumega_Motion_Backup_Store {
	private const STORE_DIRECTORY = 'mumega-motion-backups';
	private const METADATA_FILE   = 'metadata.json';
	private const THEME_DIRECTORY = 'theme';
	private const REQUIRED_FILES  = array(
		'style.css',
		'functions.php',
		'index.php',
		'build/index.js',
		'build/index.asset.php',
	);

	/**
	 * Creates a protected backup of an installed theme.
	 *
	 * @param string $theme_directory Installed theme directory.
	 * @param string $version         Installed theme version.
	 * @return array|WP_Error Path-free backup metadata, or an error.
	 */
	public function create( string $theme_directory, string $version ) {
		if ( ! $this->valid_version( $version ) || ! $this->valid_theme_directory( $theme_directory ) ) {
			return $this->error( 'mumega_motion_backup_invalid_source', 'The installed theme cannot be backed up safely.' );
		}

		$root = $this->prepare_store();

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$backup_id = $this->new_backup_id( $root );

		if ( is_wp_error( $backup_id ) ) {
			return $backup_id;
		}

		$backup_directory = $root . '/' . $backup_id;
		$theme_backup     = $backup_directory . '/' . self::THEME_DIRECTORY;

		if ( ! wp_mkdir_p( $backup_directory ) || is_link( $backup_directory ) ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$copy_result = copy_dir( $theme_directory, $theme_backup );

		if ( is_wp_error( $copy_result ) || true !== $copy_result || ! $this->valid_theme_directory( $theme_backup ) ) {
			$this->delete_recursively( $backup_directory );

			return $this->error( 'mumega_motion_backup_copy_failed', 'The installed theme could not be backed up.' );
		}

		$metadata = array(
			'id'         => $backup_id,
			'version'    => $version,
			'created_at' => microtime( true ),
		);

		if ( ! $this->write_metadata( $backup_directory, $metadata ) ) {
			$this->delete_recursively( $backup_directory );

			return $this->error( 'mumega_motion_backup_metadata_failed', 'The backup metadata could not be stored safely.' );
		}

		$stored_metadata = $this->read_metadata( $backup_directory, $backup_id );

		if ( is_wp_error( $stored_metadata ) || $metadata !== $stored_metadata ) {
			$this->delete_recursively( $backup_directory );

			return $this->error( 'mumega_motion_backup_metadata_failed', 'The backup metadata could not be verified.' );
		}

		return $metadata;
	}

	/**
	 * Restores one backup without exposing or accepting a filesystem path.
	 *
	 * @param string $backup_id      Random backup identifier.
	 * @param string $theme_directory Installed theme directory.
	 * @return array|WP_Error Restored backup metadata, or an error.
	 */
	public function restore( string $backup_id, string $theme_directory ) {
		if ( ! $this->valid_backup_id( $backup_id ) ) {
			return $this->error( 'mumega_motion_backup_invalid_id', 'The requested backup identifier is invalid.' );
		}

		$root = $this->prepare_store();

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$backup_directory = $root . '/' . $backup_id;

		if ( ! $this->safe_child_directory( $backup_directory, $root ) ) {
			return $this->error( 'mumega_motion_backup_not_found', 'The requested backup was not found.' );
		}

		$metadata = $this->read_metadata( $backup_directory, $backup_id );

		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		$backup_theme = $backup_directory . '/' . self::THEME_DIRECTORY;

		if ( ! $this->safe_child_directory( $backup_theme, $backup_directory ) || ! $this->valid_theme_directory( $backup_theme ) ) {
			return $this->error( 'mumega_motion_backup_invalid_contents', 'The requested backup is incomplete or unsafe.' );
		}

		if ( ! $this->valid_theme_directory( $theme_directory ) ) {
			return $this->error( 'mumega_motion_backup_invalid_destination', 'The installed theme cannot be replaced safely.' );
		}

		$theme_real = realpath( $theme_directory );

		if ( false === $theme_real ) {
			return $this->error( 'mumega_motion_backup_invalid_destination', 'The installed theme cannot be replaced safely.' );
		}

		$parent    = dirname( $theme_real );
		$nonce     = bin2hex( random_bytes( 16 ) );
		$staging   = $parent . '/.mumega-motion-restore-' . $nonce;
		$displaced = $parent . '/.mumega-motion-displaced-' . $nonce;

		if ( file_exists( $staging ) || is_link( $staging ) || file_exists( $displaced ) || is_link( $displaced ) ) {
			return $this->error( 'mumega_motion_backup_restore_copy_failed', 'The backup could not be staged for restoration.' );
		}

		$copy_result = copy_dir( $backup_theme, $staging );

		if ( is_wp_error( $copy_result ) || true !== $copy_result || ! $this->valid_theme_directory( $staging ) ) {
			$this->delete_recursively( $staging );

			return $this->error( 'mumega_motion_backup_restore_copy_failed', 'The backup could not be staged for restoration.' );
		}

		if ( ! $this->rename_path( $theme_real, $displaced ) ) {
			$this->delete_recursively( $staging );

			return $this->error( 'mumega_motion_backup_restore_swap_failed', 'The installed theme could not be displaced for restoration.' );
		}

		if ( ! $this->rename_path( $staging, $theme_real ) ) {
			$recovered = $this->recover_displaced_theme( $displaced, $theme_real );
			$this->delete_recursively( $staging );

			if ( ! $recovered ) {
				return $this->error( 'mumega_motion_backup_restore_rollback_failed', 'The restore failed and the installed theme could not be recovered automatically.' );
			}

			return $this->error( 'mumega_motion_backup_restore_swap_failed', 'The backup could not replace the installed theme; the installed theme was recovered.' );
		}

		if ( ! $this->delete_recursively( $displaced ) ) {
			return $this->error( 'mumega_motion_backup_cleanup_failed', 'The backup was restored, but displaced files could not be removed.' );
		}

		return $metadata;
	}

	/**
	 * Returns metadata for the newest valid backup.
	 *
	 * @return array|WP_Error Path-free backup metadata, or an error.
	 */
	public function latest() {
		$backups = $this->list_backups();

		if ( is_wp_error( $backups ) ) {
			return $backups;
		}

		if ( empty( $backups ) ) {
			return $this->error( 'mumega_motion_backup_not_found', 'No valid theme backup was found.' );
		}

		return $backups[0]['metadata'];
	}

	/**
	 * Removes older valid backups while retaining the newest requested count.
	 *
	 * @param int $keep Number of newest valid backups to retain.
	 * @return true|WP_Error
	 */
	public function prune( int $keep = 3 ) {
		if ( 0 > $keep ) {
			return $this->error( 'mumega_motion_backup_invalid_retention', 'The backup retention count is invalid.' );
		}

		$backups = $this->list_backups( true );

		if ( is_wp_error( $backups ) ) {
			return $backups;
		}

		foreach ( array_slice( $backups, $keep ) as $backup ) {
			if ( ! $this->delete_recursively( $backup['directory'] ) ) {
				return $this->error( 'mumega_motion_backup_prune_failed', 'An old theme backup could not be removed.' );
			}
		}

		return true;
	}

	/**
	 * Renames a path atomically where supported by the local filesystem.
	 *
	 * Protected visibility permits deterministic failure coverage without
	 * exposing a production configuration hook.
	 *
	 * @param string $from Existing path.
	 * @param string $to   New path.
	 * @return bool
	 */
	protected function rename_path( $from, $to ) {
		$renamed = false;
		$handler = static function () {
			return true;
		};

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Expected rename failures are handled through the return value.
		set_error_handler( $handler );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic local swap is required for restore and metadata publication.
		$renamed = rename( $from, $to );
		restore_error_handler();

		return $renamed;
	}

	/**
	 * Creates and protects the fixed store below the uploads base directory.
	 *
	 * @return string|WP_Error
	 */
	private function prepare_store() {
		if ( ! $this->ensure_filesystem_helpers() ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$upload = wp_upload_dir();

		if (
			! is_array( $upload ) ||
			! empty( $upload['error'] ) ||
			empty( $upload['basedir'] ) ||
			! is_string( $upload['basedir'] ) ||
			false !== strpos( $upload['basedir'], "\0" )
		) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$base = realpath( $upload['basedir'] );

		if ( false === $base || ! is_dir( $base ) ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$root = $base . '/' . self::STORE_DIRECTORY;

		if ( is_link( $root ) || ( file_exists( $root ) && ! is_dir( $root ) ) || ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$root_real = realpath( $root );

		if ( false === $root_real || ! $this->path_is_within( $root_real, $base ) ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		if (
			! $this->write_protection_file( $root_real . '/index.php', '' ) ||
			! $this->write_protection_file( $root_real . '/.htaccess', "Order allow,deny\nDeny from all\n" )
		) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store could not be protected.' );
		}

		return $root_real;
	}

	/**
	 * Writes a protection file without opening a pre-existing symlink.
	 *
	 * @param string $path     Destination path.
	 * @param string $contents Exact contents.
	 * @return bool
	 */
	private function write_protection_file( $path, $contents ) {
		if ( is_link( $path ) || ( file_exists( $path ) && ! is_file( $path ) ) ) {
			return false;
		}

		if ( is_file( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small fixed local protection file.
			$existing = file_get_contents( $path );

			if ( $contents === $existing ) {
				return true;
			}
		}

		$temporary = dirname( $path ) . '/.protection-' . bin2hex( random_bytes( 16 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Atomic local protection file publication.
		$written = file_put_contents( $temporary, $contents, LOCK_EX );

		if ( strlen( $contents ) !== $written ) {
			$this->delete_recursively( $temporary );
			return false;
		}

		if ( ! $this->rename_path( $temporary, $path ) ) {
			$this->delete_recursively( $temporary );
			return false;
		}

		return true;
	}

	/**
	 * Generates a fresh 128-bit backup identifier.
	 *
	 * @param string $root Store root.
	 * @return string|WP_Error
	 */
	private function new_backup_id( $root ) {
		for ( $attempt = 0; 5 > $attempt; $attempt++ ) {
			$backup_id = bin2hex( random_bytes( 16 ) );

			if ( ! file_exists( $root . '/' . $backup_id ) && ! is_link( $root . '/' . $backup_id ) ) {
				return $backup_id;
			}
		}

		return $this->error( 'mumega_motion_backup_id_failed', 'A unique backup identifier could not be generated.' );
	}

	/**
	 * Writes metadata through a same-directory temporary file and atomic rename.
	 *
	 * @param string $backup_directory Backup directory.
	 * @param array  $metadata         Path-free metadata.
	 * @return bool
	 */
	private function write_metadata( $backup_directory, array $metadata ) {
		$temporary = $backup_directory . '/.metadata-' . bin2hex( random_bytes( 16 ) );
		$encoded   = wp_json_encode( $metadata );

		if ( false === $encoded ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Atomic local metadata publication.
		$written = file_put_contents( $temporary, $encoded . "\n", LOCK_EX );

		if ( strlen( $encoded ) + 1 !== $written ) {
			$this->delete_recursively( $temporary );
			return false;
		}

		if ( ! $this->rename_path( $temporary, $backup_directory . '/' . self::METADATA_FILE ) ) {
			$this->delete_recursively( $temporary );
			return false;
		}

		return true;
	}

	/**
	 * Reads and strictly validates metadata bound to its directory identifier.
	 *
	 * @param string $backup_directory Backup directory.
	 * @param string $backup_id        Expected backup identifier.
	 * @return array|WP_Error
	 */
	private function read_metadata( $backup_directory, $backup_id ) {
		$path = $backup_directory . '/' . self::METADATA_FILE;

		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
		}

		$size = filesize( $path );

		if ( false === $size || 0 === $size || 4096 < $size ) {
			return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local metadata read.
		$contents = file_get_contents( $path );
		$metadata = false === $contents ? null : json_decode( $contents, true );

		if ( ! is_array( $metadata ) ) {
			return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
		}

		$keys = array_keys( $metadata );
		sort( $keys );

		if (
			array( 'created_at', 'id', 'version' ) !== $keys ||
			$backup_id !== $metadata['id'] ||
			! $this->valid_backup_id( $metadata['id'] ) ||
			! $this->valid_version( $metadata['version'] ) ||
			! is_float( $metadata['created_at'] ) ||
			! is_finite( $metadata['created_at'] ) ||
			0 >= $metadata['created_at']
		) {
			return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
		}

		return $metadata;
	}

	/**
	 * Lists valid backup directories newest first.
	 *
	 * @param bool $skip_unsafe_candidates Whether prune should leave unsafe candidates untouched.
	 * @return array|WP_Error
	 */
	private function list_backups( $skip_unsafe_candidates = false ) {
		$root = $this->prepare_store();

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$entries = scandir( $root );

		if ( false === $entries ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$backups = array();

		foreach ( $entries as $entry ) {
			if ( ! $this->valid_backup_id( $entry ) ) {
				continue;
			}

			$directory = $root . '/' . $entry;

			if ( ! $this->safe_child_directory( $directory, $root ) ) {
				if ( $skip_unsafe_candidates ) {
					continue;
				}

				return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
			}

			$metadata = $this->read_metadata( $directory, $entry );

			if ( is_wp_error( $metadata ) ) {
				return $metadata;
			}

			$backups[] = array(
				'directory' => $directory,
				'metadata'  => $metadata,
			);
		}

		usort(
			$backups,
			static function ( $left, $right ) {
				if ( $left['metadata']['created_at'] === $right['metadata']['created_at'] ) {
					return strcmp( $right['metadata']['id'], $left['metadata']['id'] );
				}

				return $left['metadata']['created_at'] < $right['metadata']['created_at'] ? 1 : -1;
			}
		);

		return $backups;
	}

	/**
	 * Recovers the displaced live theme after a failed second swap rename.
	 *
	 * @param string $displaced Displaced live theme.
	 * @param string $theme     Original live theme path.
	 * @return bool
	 */
	private function recover_displaced_theme( $displaced, $theme ) {
		if ( $this->rename_path( $displaced, $theme ) ) {
			return true;
		}

		if ( file_exists( $theme ) || is_link( $theme ) ) {
			return false;
		}

		$copy_result = copy_dir( $displaced, $theme );

		if ( is_wp_error( $copy_result ) || true !== $copy_result || ! $this->valid_theme_directory( $theme ) ) {
			$this->delete_recursively( $theme );
			return false;
		}

		return $this->delete_recursively( $displaced );
	}

	/**
	 * Checks required files and rejects every symbolic link in a theme tree.
	 *
	 * @param string $directory Theme directory.
	 * @return bool
	 */
	private function valid_theme_directory( $directory ) {
		if ( ! is_string( $directory ) || '' === $directory || is_link( $directory ) || ! is_dir( $directory ) ) {
			return false;
		}

		foreach ( self::REQUIRED_FILES as $required_file ) {
			$path = $directory . '/' . $required_file;

			if ( is_link( $path ) || ! is_file( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}

		return $this->tree_has_only_regular_entries( $directory );
	}

	/**
	 * Rejects links and special files anywhere in a copied tree.
	 *
	 * @param string $directory Directory to inspect.
	 * @return bool
	 */
	private function tree_has_only_regular_entries( $directory ) {
		$entries = scandir( $directory );

		if ( false === $entries ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $directory . '/' . $entry;

			if ( is_link( $path ) ) {
				return false;
			}

			if ( is_dir( $path ) ) {
				if ( ! $this->tree_has_only_regular_entries( $path ) ) {
					return false;
				}
			} elseif ( ! is_file( $path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks a directory is a real direct descendant of an expected parent.
	 *
	 * @param string $directory Candidate directory.
	 * @param string $expected_parent Expected real parent.
	 * @return bool
	 */
	private function safe_child_directory( $directory, $expected_parent ) {
		if ( is_link( $directory ) || ! is_dir( $directory ) ) {
			return false;
		}

		$real = realpath( $directory );

		return false !== $real && $this->path_is_within( $real, $expected_parent ) && dirname( $real ) === $expected_parent;
	}

	/**
	 * Tests canonical containment below a parent path.
	 *
	 * @param string $path   Canonical candidate.
	 * @param string $expected_parent Canonical parent.
	 * @return bool
	 */
	private function path_is_within( $path, $expected_parent ) {
		$expected_parent = rtrim( $expected_parent, '/' );

		return '' === $expected_parent ? 0 === strpos( $path, '/' ) : 0 === strpos( $path, $expected_parent . '/' );
	}

	/**
	 * Validates a random backup identifier.
	 *
	 * @param mixed $backup_id Candidate identifier.
	 * @return bool
	 */
	private function valid_backup_id( $backup_id ) {
		return is_string( $backup_id ) && 1 === preg_match( '/\A[a-f0-9]{32}\z/', $backup_id );
	}

	/**
	 * Validates path-free version metadata.
	 *
	 * @param mixed $version Candidate version.
	 * @return bool
	 */
	private function valid_version( $version ) {
		return is_string( $version ) && '' !== $version && 128 >= strlen( $version ) && 0 === preg_match( '/[\x00-\x1f\x7f]/', $version );
	}

	/**
	 * Deletes a local path through the initialized WordPress filesystem API.
	 *
	 * @param string $path File or directory.
	 * @return bool
	 */
	private function delete_recursively( $path ) {
		global $wp_filesystem;

		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			return true;
		}

		if ( ! $this->ensure_filesystem_helpers() ) {
			return false;
		}

		return is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'delete' ) && $wp_filesystem->delete( $path, true );
	}

	/**
	 * Loads and initializes WordPress's local filesystem helpers.
	 *
	 * @return bool
	 */
	private function ensure_filesystem_helpers() {
		global $wp_filesystem;

		if ( ( ! function_exists( 'copy_dir' ) || ! function_exists( 'WP_Filesystem' ) ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'copy_dir' ) || ! function_exists( 'WP_Filesystem' ) ) {
			return false;
		}

		return is_object( $wp_filesystem ) || WP_Filesystem();
	}

	/**
	 * Creates a stable error without filesystem paths or backup identifiers.
	 *
	 * @param string $code    Stable error code.
	 * @param string $message Safe public message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( $code, $message );
	}
}
