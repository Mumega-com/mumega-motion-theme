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
	private const SEQUENCE_FILE   = '.sequence.json';
	private const SEQUENCE_LOCK   = '.sequence.lock';
	private const THEME_DIRECTORY = 'theme';
	private const HTACCESS        = "Order allow,deny\nDeny from all\n";
	private const WEB_CONFIG      = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>\n";
	private const REQUIRED_FILES  = array(
		'style.css',
		'functions.php',
		'index.php',
		'build/index.js',
		'build/index.asset.php',
	);

	/**
	 * Secret used only to authenticate local metadata.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * Creates a store with an injected or WordPress-derived secret.
	 *
	 * @param string|null $secret Optional injected secret.
	 */
	public function __construct( $secret = null ) {
		$this->secret = is_string( $secret ) ? $secret : $this->default_secret();
	}

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

		if (
			! wp_mkdir_p( $backup_directory ) ||
			is_link( $backup_directory ) ||
			! $this->restrict_directory_to_owner( $backup_directory ) ||
			! $this->protect_directory( $backup_directory, true )
		) {
			$this->delete_recursively( $backup_directory, $root );
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store is unavailable.' );
		}

		$copy_result = copy_dir( $theme_directory, $theme_backup );

		if ( is_wp_error( $copy_result ) || true !== $copy_result || ! $this->valid_theme_directory( $theme_backup ) ) {
			if ( ! $this->cleanup_or_quarantine( $backup_directory, $root, $root ) ) {
				return $this->cleanup_error( 'create_copy', 'mumega_motion_backup_copy_failed' );
			}

			return $this->error( 'mumega_motion_backup_copy_failed', 'The installed theme could not be backed up.' );
		}

		$manifest = $this->build_manifest( $theme_backup );

		if ( is_wp_error( $manifest ) ) {
			if ( ! $this->cleanup_or_quarantine( $backup_directory, $root, $root ) ) {
				return $this->cleanup_error( 'create_manifest', 'mumega_motion_backup_copy_failed' );
			}

			return $manifest;
		}

		$sequence = $this->next_sequence( $root );

		if ( is_wp_error( $sequence ) ) {
			if ( ! $this->cleanup_or_quarantine( $backup_directory, $root, $root ) ) {
				return $this->cleanup_error( 'create_sequence', $sequence->get_error_code() );
			}

			return $sequence;
		}

		$metadata              = array(
			'id'         => $backup_id,
			'version'    => $version,
			'created_at' => $this->current_time(),
			'sequence'   => $sequence,
			'manifest'   => $manifest,
		);
		$metadata['signature'] = $this->sign_metadata( $metadata );

		if ( ! $this->write_metadata( $backup_directory, $metadata ) ) {
			if ( ! $this->cleanup_or_quarantine( $backup_directory, $root, $root ) ) {
				return $this->cleanup_error( 'create_metadata', 'mumega_motion_backup_metadata_failed' );
			}

			return $this->error( 'mumega_motion_backup_metadata_failed', 'The backup metadata could not be stored safely.' );
		}

		$stored_metadata = $this->read_metadata( $backup_directory, $backup_id );

		if ( is_wp_error( $stored_metadata ) || $metadata !== $stored_metadata ) {
			if ( ! $this->cleanup_or_quarantine( $backup_directory, $root, $root ) ) {
				return $this->cleanup_error( 'create_metadata_verify', 'mumega_motion_backup_metadata_failed' );
			}

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

		$backup_theme = $backup_directory . '/' . self::THEME_DIRECTORY;

		if ( ! $this->safe_child_directory( $backup_theme, $backup_directory ) || ! $this->valid_theme_directory( $backup_theme ) ) {
			return $this->error( 'mumega_motion_backup_invalid_contents', 'The requested backup is incomplete or unsafe.' );
		}

		$metadata = $this->read_metadata( $backup_directory, $backup_id );

		if ( is_wp_error( $metadata ) ) {
			return $metadata;
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
			if ( ! $this->cleanup_or_quarantine( $staging, $parent, $root ) ) {
				return $this->cleanup_error( 'restore_stage', 'mumega_motion_backup_restore_copy_failed' );
			}

			return $this->error( 'mumega_motion_backup_restore_copy_failed', 'The backup could not be staged for restoration.' );
		}

		$staged_manifest = $this->build_manifest( $staging );

		if ( is_wp_error( $staged_manifest ) || $metadata['manifest'] !== $staged_manifest ) {
			if ( ! $this->cleanup_or_quarantine( $staging, $parent, $root ) ) {
				return $this->cleanup_error( 'restore_integrity', 'mumega_motion_backup_integrity_failed' );
			}

			return $this->error( 'mumega_motion_backup_integrity_failed', 'Backup integrity verification failed.' );
		}

		if ( ! $this->rename_path( $theme_real, $displaced ) ) {
			if ( ! $this->cleanup_or_quarantine( $staging, $parent, $root ) ) {
				return $this->cleanup_error( 'restore_displace', 'mumega_motion_backup_restore_swap_failed' );
			}

			return $this->error( 'mumega_motion_backup_restore_swap_failed', 'The installed theme could not be displaced for restoration.' );
		}

		if ( ! $this->rename_path( $staging, $theme_real ) ) {
			$recovered = $this->recover_displaced_theme( $displaced, $theme_real );
			$cleaned   = $this->cleanup_or_quarantine( $staging, $parent, $root );

			if ( ! $recovered ) {
				return $this->error( 'mumega_motion_backup_restore_rollback_failed', 'The restore failed and the installed theme could not be recovered automatically.' );
			}

			if ( ! $cleaned ) {
				return $this->cleanup_error( 'restore_swap', 'mumega_motion_backup_restore_swap_failed' );
			}

			return $this->error( 'mumega_motion_backup_restore_swap_failed', 'The backup could not replace the installed theme; the installed theme was recovered.' );
		}

		if ( ! $this->cleanup_or_quarantine( $displaced, $parent, $root ) ) {
			return $this->cleanup_error( 'restore_displaced', 'mumega_motion_backup_cleanup_failed' );
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
			$root = dirname( $backup['directory'] );

			if ( ! $this->cleanup_or_quarantine( $backup['directory'], $root, $root ) ) {
				return $this->cleanup_error( 'prune', 'mumega_motion_backup_prune_failed' );
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
		if ( '' === $this->secret || ! $this->ensure_filesystem_helpers() ) {
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

		if ( ! $this->restrict_directory_to_owner( $root_real ) || ! $this->protect_directory( $root_real, true ) ) {
			return $this->error( 'mumega_motion_backup_store_unavailable', 'The backup store could not be protected.' );
		}

		return $root_real;
	}

	/**
	 * Installs server and directory-index protection in a local directory.
	 *
	 * @param string $directory Directory to protect.
	 * @param bool   $empty_index Whether index.php must be an empty blocker.
	 * @return bool
	 */
	private function protect_directory( $directory, $empty_index ) {
		if (
			( $empty_index && ! $this->write_protection_file( $directory . '/index.php', '' ) ) ||
			! $this->write_protection_file( $directory . '/.htaccess', self::HTACCESS ) ||
			! $this->write_protection_file( $directory . '/web.config', self::WEB_CONFIG )
		) {
			return false;
		}

		return true;
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

		return $this->write_atomic_file( dirname( $path ), '.protection-', $path, $contents );
	}

	/**
	 * Restricts a store-controlled directory to its filesystem owner.
	 *
	 * The uploads base can have broader permissions, but all mutable backup
	 * state lives beneath this directory. Checking its inode both before and
	 * after chmod prevents a replacement from being accepted as protected.
	 *
	 * @param string $directory Store-controlled directory.
	 * @return bool
	 */
	private function restrict_directory_to_owner( $directory ) {
		$before = $this->safe_lstat( $directory );

		if ( false === $before || ! $this->stat_is_directory( $before ) || $this->stat_is_link( $before ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Backup state must not be writable by other local users.
		if ( ! chmod( $directory, 0700 ) ) {
			return false;
		}

		$after = $this->safe_lstat( $directory );

		return false !== $after &&
			$this->same_inode( $after, $before ) &&
			$this->stat_is_directory( $after ) &&
			0 === ( $after['mode'] & 0077 );
	}

	/**
	 * Publishes a local file from an exclusively-created same-directory temporary.
	 *
	 * Exclusive fopen('x') rejects a pre-existing temporary name, including a symlink.
	 * Direct lstat-safe cleanup is deliberate here: WP_Filesystem helpers do not
	 * expose no-follow or inode checks needed to clean an attacker-substituted
	 * temporary path safely.
	 *
	 * @param string $directory   Containing protected directory.
	 * @param string $temporary_prefix Opaque temporary filename prefix.
	 * @param string $destination Final file path.
	 * @param string $contents    Exact file contents.
	 * @return bool
	 */
	private function write_atomic_file( $directory, $temporary_prefix, $destination, $contents ) {
		$directory_stat = $this->safe_lstat( $directory );

		if (
			false === $directory_stat ||
			! $this->stat_is_directory( $directory_stat ) ||
			! $this->restrict_directory_to_owner( $directory )
		) {
			return false;
		}

		$directory_stat = $this->safe_lstat( $directory );

		if ( false === $directory_stat || ! $this->stat_is_directory( $directory_stat ) ) {
			return false;
		}

		$temporary = $directory . '/' . $temporary_prefix . bin2hex( random_bytes( 16 ) );
		$handler   = static function () {
			return true;
		};

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exclusive temporary creation failure is handled locally.
		set_error_handler( $handler );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fopen('x') provides exclusive, non-traversing temporary creation.
		$handle = fopen( $temporary, 'x' );
		restore_error_handler();

		if ( false === $handle ) {
			return false;
		}

		$temporary_stat = $this->safe_lstat( $temporary );

		if ( false === $temporary_stat || ! $this->stat_is_regular_file( $temporary_stat ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the exclusively-created temporary file.
			fclose( $handle );
			$this->delete_recursively( $temporary, $directory );
			return false;
		}

		$length  = strlen( $contents );
		$written = 0;

		while ( $written < $length ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes through the exclusively-created temporary descriptor.
			$chunk = fwrite( $handle, substr( $contents, $written ) );

			if ( false === $chunk || 0 === $chunk ) {
				break;
			}

			$written += $chunk;
		}

		$flushed = fflush( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the exclusively-created temporary file.
		fclose( $handle );

		$destination_stat = $this->safe_lstat( $destination );

		if ( ! $this->before_atomic_publish( $temporary ) ) {
			$this->delete_recursively( $temporary, $directory );
			return false;
		}

		if (
			$length !== $written ||
			! $flushed ||
			! $this->same_lstat( $directory, $directory_stat ) ||
			! $this->same_lstat( $temporary, $temporary_stat ) ||
			( false !== $destination_stat && ! $this->stat_is_regular_file( $destination_stat ) )
		) {
			$this->delete_recursively( $temporary, $directory );
			return false;
		}

		if ( ! $this->rename_path( $temporary, $destination ) ) {
			$this->delete_recursively( $temporary, $directory );
			return false;
		}

		return true;
	}

	/**
	 * Provides a deterministic test seam before the final no-follow checks.
	 *
	 * Production does not alter the temporary path here. Keeping the seam
	 * protected permits regression coverage for a path substitution race.
	 *
	 * @param string $temporary Exclusively-created temporary path.
	 * @return bool
	 */
	protected function before_atomic_publish( $temporary ) {
		unset( $temporary );

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
		$encoded = wp_json_encode( $metadata, JSON_PRESERVE_ZERO_FRACTION );

		if ( false === $encoded ) {
			return false;
		}

		return $this->write_atomic_file(
			$backup_directory,
			'.metadata-',
			$backup_directory . '/' . self::METADATA_FILE,
			$encoded . "\n"
		);
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

		if ( false === $size || 0 === $size || ( 4 * 1024 * 1024 ) < $size ) {
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
			array( 'created_at', 'id', 'manifest', 'sequence', 'signature', 'version' ) !== $keys ||
			$backup_id !== $metadata['id'] ||
			! $this->valid_backup_id( $metadata['id'] ) ||
			! $this->valid_version( $metadata['version'] ) ||
			! is_float( $metadata['created_at'] ) ||
			! is_finite( $metadata['created_at'] ) ||
			0 >= $metadata['created_at'] ||
			! is_int( $metadata['sequence'] ) ||
			1 > $metadata['sequence'] ||
			! is_array( $metadata['manifest'] ) ||
			! is_string( $metadata['signature'] ) ||
			1 !== preg_match( '/\A[a-f0-9]{64}\z/', $metadata['signature'] )
		) {
			return $this->error( 'mumega_motion_backup_invalid_metadata', 'Backup metadata is missing or invalid.' );
		}

		$unsigned = $metadata;
		unset( $unsigned['signature'] );

		if ( ! hash_equals( $metadata['signature'], $this->sign_metadata( $unsigned ) ) ) {
			return $this->error( 'mumega_motion_backup_integrity_failed', 'Backup integrity verification failed.' );
		}

		$actual_manifest = $this->build_manifest( $backup_directory . '/' . self::THEME_DIRECTORY );

		if ( is_wp_error( $actual_manifest ) || $metadata['manifest'] !== $actual_manifest ) {
			return $this->error( 'mumega_motion_backup_integrity_failed', 'Backup integrity verification failed.' );
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

			if ( false === $this->safe_lstat( $directory . '/' . self::METADATA_FILE ) ) {
				$this->quarantine_path( $directory, $root );
				continue;
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
				return $right['metadata']['sequence'] <=> $left['metadata']['sequence'];
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
			$this->delete_recursively( $theme, dirname( $theme ) );
			return false;
		}

		return $this->delete_recursively( $displaced, dirname( $displaced ) );
	}

	/**
	 * Builds a deterministic relative-path to SHA-256 manifest.
	 *
	 * @param string $directory Theme directory.
	 * @return array|WP_Error
	 */
	private function build_manifest( $directory ) {
		$root = realpath( $directory );

		if ( false === $root || is_link( $directory ) || ! is_dir( $root ) ) {
			return $this->error( 'mumega_motion_backup_integrity_failed', 'Backup integrity verification failed.' );
		}

		$manifest = array();

		if ( ! $this->collect_manifest( $root, $root, $manifest ) ) {
			return $this->error( 'mumega_motion_backup_integrity_failed', 'Backup integrity verification failed.' );
		}

		ksort( $manifest, SORT_STRING );

		return $manifest;
	}

	/**
	 * Recursively hashes regular files without following symbolic links.
	 *
	 * @param string $directory Current directory.
	 * @param string $root      Manifest root.
	 * @param array  $manifest  Collected manifest.
	 * @return bool
	 */
	private function collect_manifest( $directory, $root, array &$manifest ) {
		$directory_stat = $this->safe_lstat( $directory );
		$directory_real = realpath( $directory );

		if (
			false === $directory_stat ||
			false === $directory_real ||
			! $this->path_is_within_or_same( $directory_real, $root ) ||
			! $this->stat_is_directory( $directory_stat )
		) {
			return false;
		}

		$entries = scandir( $directory );

		if ( false === $entries || ! $this->same_lstat( $directory, $directory_stat ) ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			if ( ! $this->same_lstat( $directory, $directory_stat ) || realpath( $directory ) !== $directory_real ) {
				return false;
			}

			$path = $directory . '/' . $entry;
			$stat = $this->safe_lstat( $path );

			if ( false === $stat || $this->stat_is_link( $stat ) ) {
				return false;
			}

			if ( $this->stat_is_directory( $stat ) ) {
				if ( ! $this->collect_manifest( $path, $root, $manifest ) ) {
					return false;
				}
			} elseif ( $this->stat_is_regular_file( $stat ) ) {
				$hash = hash_file( 'sha256', $path );

				if ( false === $hash || ! $this->same_lstat( $path, $stat ) ) {
					return false;
				}

				$manifest[ substr( $path, strlen( $root ) + 1 ) ] = $hash;
			} else {
				return false;
			}
		}

		return $this->same_lstat( $directory, $directory_stat );
	}

	/**
	 * Signs canonical path-free metadata.
	 *
	 * @param array $metadata Metadata without signature.
	 * @return string
	 */
	private function sign_metadata( array $metadata ) {
		return hash_hmac( 'sha256', (string) wp_json_encode( $metadata, JSON_PRESERVE_ZERO_FRACTION ), $this->secret );
	}

	/**
	 * Returns the wall-clock evidence recorded alongside monotonic ordering.
	 *
	 * @return float
	 */
	protected function current_time() {
		return microtime( true );
	}

	/**
	 * Allocates a signed monotonic sequence under a store-level exclusive lock.
	 *
	 * @param string $root Protected store root.
	 * @return int|WP_Error
	 */
	private function next_sequence( $root ) {
		$lock_path = $root . '/' . self::SEQUENCE_LOCK;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local flock is required for cross-request ordering.
		$lock = fopen( $lock_path, 'c' );

		if ( false === $lock ) {
			return $this->error( 'mumega_motion_backup_sequence_failed', 'The backup sequence could not be locked.' );
		}

		if ( ! flock( $lock, LOCK_EX ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the local flock handle above.
			fclose( $lock );

			return $this->error( 'mumega_motion_backup_sequence_failed', 'The backup sequence could not be locked.' );
		}

		$state_path = $root . '/' . self::SEQUENCE_FILE;
		$current    = 0;
		$result     = null;

		if ( false !== $this->safe_lstat( $state_path ) ) {
			$size = filesize( $state_path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small signed local sequence state.
			$contents = false === $size || 1024 < $size ? false : file_get_contents( $state_path );
			$state    = false === $contents ? null : json_decode( $contents, true );

			if (
				! is_array( $state ) ||
				array( 'sequence', 'signature' ) !== array_keys( $state ) ||
				! is_int( $state['sequence'] ) ||
				0 > $state['sequence'] ||
				! is_string( $state['signature'] ) ||
				! hash_equals( $this->sign_sequence( $state['sequence'] ), $state['signature'] )
			) {
				$result = $this->error( 'mumega_motion_backup_sequence_integrity_failed', 'The backup sequence integrity check failed.' );
			} else {
				$current = $state['sequence'];
			}
		}

		if ( null === $result ) {
			if ( PHP_INT_MAX === $current ) {
				$result = $this->error( 'mumega_motion_backup_sequence_failed', 'The backup sequence is exhausted.' );
			} else {
				$next    = $current + 1;
				$state   = array(
					'sequence'  => $next,
					'signature' => $this->sign_sequence( $next ),
				);
				$encoded = wp_json_encode( $state );

				if (
					false === $encoded ||
					! $this->write_atomic_file( $root, '.sequence-', $state_path, $encoded . "\n" )
				) {
					$result = $this->error( 'mumega_motion_backup_sequence_failed', 'The backup sequence could not be stored safely.' );
				} else {
					$result = $next;
				}
			}
		}

		flock( $lock, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the local flock handle above.
		fclose( $lock );

		return $result;
	}

	/**
	 * Signs the store-level sequence state in a separate HMAC domain.
	 *
	 * @param int $sequence Monotonic sequence.
	 * @return string
	 */
	private function sign_sequence( $sequence ) {
		return hash_hmac( 'sha256', "mumega-motion-backup-sequence\0" . $sequence, $this->secret );
	}

	/**
	 * Derives the default authentication key from WordPress secrets.
	 *
	 * @return string
	 */
	private function default_secret() {
		if ( function_exists( 'wp_salt' ) ) {
			$secret = wp_salt( 'auth' );

			if ( is_string( $secret ) && '' !== $secret ) {
				return $secret;
			}
		}

		$secret = '';

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
			if ( defined( $constant ) ) {
				$secret .= constant( $constant );
			}
		}

		return $secret;
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
	 * Tests canonical equality or containment.
	 *
	 * @param string $path            Canonical candidate.
	 * @param string $expected_parent Canonical parent.
	 * @return bool
	 */
	private function path_is_within_or_same( $path, $expected_parent ) {
		return $path === $expected_parent || $this->path_is_within( $path, $expected_parent );
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
	 * Deletes a contained tree without following symbolic links.
	 *
	 * Each descent verifies the canonical parent plus lstat device/inode before
	 * and after directory reads and immediately before unlink/rmdir. PHP does
	 * not expose unlinkat(), so any detected replacement aborts safely.
	 *
	 * @param string      $path           File or directory to remove.
	 * @param string|null $allowed_parent Canonical containment boundary.
	 * @return bool
	 */
	protected function delete_recursively( $path, $allowed_parent = null ) {
		$stat = $this->safe_lstat( $path );

		if ( false === $stat ) {
			return true;
		}

		$allowed_real = realpath( null === $allowed_parent ? dirname( $path ) : $allowed_parent );
		$parent       = dirname( $path );
		$parent_real  = realpath( $parent );
		$parent_stat  = $this->safe_lstat( $parent );

		if (
			false === $allowed_real ||
			false === $parent_real ||
			false === $parent_stat ||
			! $this->path_is_within_or_same( $parent_real, $allowed_real ) ||
			! $this->stat_is_directory( $parent_stat )
		) {
			return false;
		}

		if ( $this->stat_is_link( $stat ) || $this->stat_is_regular_file( $stat ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Deletes only after containment and inode checks.
			return $this->same_lstat( $parent, $parent_stat ) && $this->same_lstat( $path, $stat ) && unlink( $path );
		}

		if ( ! $this->stat_is_directory( $stat ) ) {
			return false;
		}

		$path_real = realpath( $path );

		if ( false === $path_real || ! $this->path_is_within( $path_real, $allowed_real ) ) {
			return false;
		}

		$entries = scandir( $path );

		if ( false === $entries || ! $this->same_lstat( $path, $stat ) || realpath( $path ) !== $path_real ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			if (
				! $this->same_lstat( $parent, $parent_stat ) ||
				! $this->same_lstat( $path, $stat ) ||
				realpath( $path ) !== $path_real ||
				! $this->delete_recursively( $path . '/' . $entry, $allowed_real )
			) {
				return false;
			}
		}

		if (
			! $this->same_lstat( $parent, $parent_stat ) ||
			! $this->same_lstat( $path, $stat ) ||
			realpath( $path ) !== $path_real
		) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Deletes only after containment and inode checks.
		return rmdir( $path );
	}

	/**
	 * Deletes an operation artifact or moves it under the protected store.
	 *
	 * @param string $path           Artifact path.
	 * @param string $allowed_parent Deletion containment boundary.
	 * @param string $store_root     Protected store root.
	 * @return bool True only when deletion completed.
	 */
	private function cleanup_or_quarantine( $path, $allowed_parent, $store_root ) {
		if ( $this->delete_recursively( $path, $allowed_parent ) ) {
			return true;
		}

		$this->quarantine_path( $path, $store_root );

		return false;
	}

	/**
	 * Moves an undeletable artifact to an opaque non-backup name.
	 *
	 * @param string $path       Artifact path.
	 * @param string $store_root Protected store root.
	 * @return bool
	 */
	private function quarantine_path( $path, $store_root ) {
		if ( false === $this->safe_lstat( $path ) || is_link( $path ) ) {
			return false;
		}

		$root_real   = realpath( $store_root );
		$parent_real = realpath( dirname( $path ) );

		if ( false === $root_real || false === $parent_real ) {
			return false;
		}

		$destination = $root_real . '/.incomplete-' . bin2hex( random_bytes( 16 ) );

		if ( ! $this->rename_path( $path, $destination ) ) {
			return false;
		}

		$this->protect_directory( $destination, false );

		return true;
	}

	/**
	 * Creates a cleanup error with only path-free operation context.
	 *
	 * @param string $operation     Stable operation name.
	 * @param string $original_code Original failure code.
	 * @return WP_Error
	 */
	private function cleanup_error( $operation, $original_code ) {
		return new WP_Error(
			'mumega_motion_backup_cleanup_failed',
			'Backup cleanup did not complete; the artifact was isolated when possible.',
			array(
				'operation'     => $operation,
				'original_code' => $original_code,
			)
		);
	}

	/**
	 * Reads link metadata without emitting path-dependent warnings.
	 *
	 * @param string $path Path to inspect.
	 * @return array|false
	 */
	private function safe_lstat( $path ) {
		$handler = static function () {
			return true;
		};

		clearstatcache( true, $path );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- A disappearing path is represented by false.
		set_error_handler( $handler );
		$stat = lstat( $path );
		restore_error_handler();

		return $stat;
	}

	/**
	 * Compares an lstat snapshot by type, device, and inode.
	 *
	 * @param string $path     Path to inspect.
	 * @param array  $expected Earlier lstat result.
	 * @return bool
	 */
	private function same_lstat( $path, array $expected ) {
		$actual = $this->safe_lstat( $path );

		return false !== $actual &&
			$this->same_inode( $actual, $expected ) &&
			$actual['mode'] === $expected['mode'];
	}

	/**
	 * Compares the device and inode of two lstat snapshots.
	 *
	 * @param array $left  First lstat snapshot.
	 * @param array $right Second lstat snapshot.
	 * @return bool
	 */
	private function same_inode( array $left, array $right ) {
		return $left['dev'] === $right['dev'] && $left['ino'] === $right['ino'];
	}

	/**
	 * Checks an lstat result for a symbolic link.
	 *
	 * @param array $stat lstat result.
	 * @return bool
	 */
	private function stat_is_link( array $stat ) {
		return 0120000 === ( $stat['mode'] & 0170000 );
	}

	/**
	 * Checks an lstat result for a directory.
	 *
	 * @param array $stat lstat result.
	 * @return bool
	 */
	private function stat_is_directory( array $stat ) {
		return 0040000 === ( $stat['mode'] & 0170000 );
	}

	/**
	 * Checks an lstat result for a regular file.
	 *
	 * @param array $stat lstat result.
	 * @return bool
	 */
	private function stat_is_regular_file( array $stat ) {
		return 0100000 === ( $stat['mode'] & 0170000 );
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
