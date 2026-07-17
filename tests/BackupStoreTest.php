<?php
// phpcs:ignoreFile -- Fixtures deliberately exercise local filesystem failure and recovery behavior.
/**
 * Tests for protected local theme backups.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises backup creation, retention, and atomic restore behavior.
 */
final class BackupStoreTest extends TestCase {
	/**
	 * Isolated test root.
	 *
	 * @var string
	 */
	private $temporary_directory;

	/**
	 * Upload base directory.
	 *
	 * @var string
	 */
	private $uploads_directory;

	/**
	 * Creates an isolated filesystem and a fresh WordPress filesystem double.
	 */
	protected function setUp(): void {
		global $wp_filesystem;

		$this->temporary_directory = sys_get_temp_dir() . '/mumega-motion-backups-' . bin2hex( random_bytes( 8 ) );
		$this->uploads_directory   = $this->temporary_directory . '/uploads';
		mkdir( $this->uploads_directory, 0700, true );

		$GLOBALS['mumega_motion_test_upload_basedir']  = $this->uploads_directory;
		$GLOBALS['mumega_motion_test_copy_fail_after'] = null;
		$GLOBALS['mumega_motion_test_copy_count']      = 0;
		$GLOBALS['mumega_motion_test_copy_after_file'] = null;
		$wp_filesystem                                 = new Mumega_Motion_Test_Filesystem();
	}

	/**
	 * Removes the isolated filesystem without following symbolic links.
	 */
	protected function tearDown(): void {
		$this->remove_path( $this->temporary_directory );
	}

	/**
	 * Creates protected backups with random identifiers and path-free metadata.
	 */
	public function test_create_copies_theme_and_writes_protection_and_atomic_metadata(): void {
		$theme = $this->create_theme( 'current', 'first' );
		$store = new Mumega_Motion_Backup_Store();

		ob_start();
		$first  = $store->create( $theme, '0.1.100' );
		$second = $store->create( $theme, '0.1.100' );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertMatchesRegularExpression( '/\A[a-f0-9]{32}\z/', $first['id'] );
		$this->assertMatchesRegularExpression( '/\A[a-f0-9]{32}\z/', $second['id'] );
		$this->assertNotSame( $first['id'], $second['id'] );
		$this->assertSame( '0.1.100', $first['version'] );
		$this->assertIsFloat( $first['created_at'] );

		$root        = $this->backup_root();
		$backup_path = $root . '/' . $first['id'];
		$this->assertSame( realpath( $this->uploads_directory ), dirname( realpath( $root ) ) );
		$this->assertFileExists( $backup_path . '/theme/functions.php' );
		$this->assertSame( 'first', file_get_contents( $backup_path . '/theme/marker.txt' ) );
		$this->assertFileExists( $root . '/index.php' );
		$this->assertSame( '', file_get_contents( $root . '/index.php' ) );
		$this->assertStringContainsString( 'Deny from all', file_get_contents( $root . '/.htaccess' ) );
		$this->assertFileExists( $root . '/web.config' );
		$this->assertStringContainsString( 'accessType="Deny"', file_get_contents( $root . '/web.config' ) );
		$this->assertFileExists( $backup_path . '/index.php' );
		$this->assertSame( '', file_get_contents( $backup_path . '/index.php' ) );
		$this->assertFileExists( $backup_path . '/.htaccess' );
		$this->assertStringContainsString( 'Deny from all', file_get_contents( $backup_path . '/.htaccess' ) );
		$this->assertFileExists( $backup_path . '/web.config' );
		$this->assertStringContainsString( 'accessType="Deny"', file_get_contents( $backup_path . '/web.config' ) );
		$this->assertSame( $first, json_decode( file_get_contents( $backup_path . '/metadata.json' ), true ) );
		$this->assertStringNotContainsString( $theme, file_get_contents( $backup_path . '/metadata.json' ) );
		$this->assertStringNotContainsString( 'mumega-motion-test-secret', file_get_contents( $backup_path . '/metadata.json' ) );
		$this->assertSame( array(), glob( $backup_path . '/.metadata-*' ) );
	}

	/**
	 * Restricts the store and each backup directory to the current filesystem owner.
	 */
	public function test_create_restricts_store_and_backup_directories_to_the_owner(): void {
		$metadata = ( new Mumega_Motion_Backup_Store() )->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assertIsArray( $metadata );
		$this->assertSame( 0, fileperms( $this->backup_root() ) & 0077 );
		$this->assertSame( 0, fileperms( $this->backup_root() . '/' . $metadata['id'] ) & 0077 );
	}

	/**
	 * Rejects a temporary metadata path replaced by a symbolic link before publication.
	 */
	public function test_create_rejects_substituted_temporary_metadata_path(): void {
		$outside = $this->temporary_directory . '/outside-temporary';
		file_put_contents( $outside, 'safe' );
		$store = new class( $outside ) extends Mumega_Motion_Backup_Store {
			private $outside;
			private $substituted = false;

			public function __construct( $outside ) {
				parent::__construct();
				$this->outside = $outside;
			}

			public function was_substituted() {
				return $this->substituted;
			}

			protected function before_atomic_publish( $temporary ) {
				if ( 0 === strpos( basename( $temporary ), '.metadata-' ) ) {
					unlink( $temporary );
					symlink( $this->outside, $temporary );
					$this->substituted = true;
				}

				return true;
			}
		};

		$result = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_metadata_failed', $result );
		$this->assertTrue( $store->was_substituted() );
		$this->assertSame( 'safe', file_get_contents( $outside ) );
		$this->assertSame( array(), glob( $this->backup_root() . '/*/.metadata-*' ) );
	}

	/**
	 * Rejects a symlinked store root instead of writing outside uploads.
	 */
	public function test_create_rejects_symlinked_backup_root(): void {
		$outside = $this->temporary_directory . '/outside';
		mkdir( $outside );
		symlink( $outside, $this->backup_root() );

		$result = ( new Mumega_Motion_Backup_Store() )->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_store_unavailable', $result );
		$this->assertSame( array(), array_values( array_diff( scandir( $outside ), array( '.', '..' ) ) ) );
	}

	/**
	 * Cleans an incomplete backup when WordPress copy_dir() fails midway.
	 */
	public function test_create_cleans_partial_copy_failure(): void {
		$GLOBALS['mumega_motion_test_copy_fail_after'] = 2;

		$result = ( new Mumega_Motion_Backup_Store() )->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_copy_failed', $result );
		$this->assertSame( array( '.htaccess', 'index.php', 'web.config' ), $this->backup_root_entries() );
	}

	/**
	 * Reports cleanup failure with the original operation context and quarantines the partial backup.
	 */
	public function test_create_reports_cleanup_failure_and_quarantines_partial_backup(): void {
		$GLOBALS['mumega_motion_test_copy_fail_after'] = 2;
		$store = new class() extends Mumega_Motion_Backup_Store {
			protected function delete_recursively( $path, $allowed_parent = null ) {
				return false;
			}
		};

		$result = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_cleanup_failed', $result );
		$this->assertSame( 'create_copy', $result->get_error_data()['operation'] );
		$this->assertSame( 'mumega_motion_backup_copy_failed', $result->get_error_data()['original_code'] );
		$this->assertCount( 1, glob( $this->backup_root() . '/.incomplete-*', GLOB_ONLYDIR ) );
		$this->assertSame( array(), glob( $this->backup_root() . '/[a-f0-9]*', GLOB_ONLYDIR ) );
	}

	/**
	 * Never follows a cleanup target replaced with a symlink outside the store.
	 */
	public function test_partial_cleanup_unlinks_replaced_symlink_without_escaping_store(): void {
		$GLOBALS['mumega_motion_test_copy_fail_after'] = 2;
		$outside = $this->temporary_directory . '/outside-cleanup';
		mkdir( $outside );
		file_put_contents( $outside . '/sentinel', 'safe' );
		$store = new class( $outside ) extends Mumega_Motion_Backup_Store {
			private $outside;
			private $mutated = false;

			public function __construct( $outside ) {
				parent::__construct( 'cleanup-symlink-secret' );
				$this->outside = $outside;
			}

			public function was_mutated() {
				return $this->mutated;
			}

			protected function delete_recursively( $path, $allowed_parent = null ) {
				if ( ! $this->mutated && 1 === preg_match( '/\A[a-f0-9]{32}\z/', basename( $path ) ) ) {
					$this->remove_fixture_path( $path );
				symlink( $this->outside, $path );
				$this->mutated = true;
				}

				return parent::delete_recursively( $path, $allowed_parent );
			}

			private function remove_fixture_path( $path ) {
				if ( is_file( $path ) || is_link( $path ) ) {
					unlink( $path );
					return;
				}

				foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
					$this->remove_fixture_path( $path . '/' . $entry );
				}

				rmdir( $path );
			}
		};

		$result = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_copy_failed', $result );
		$this->assertTrue( $store->was_mutated() );
		$this->assertSame( 'safe', file_get_contents( $outside . '/sentinel' ) );
		$this->assertSame( array(), glob( $this->backup_root() . '/[a-f0-9]*' ) );
	}

	/**
	 * Cleans the backup and temporary metadata when the atomic publish rename fails.
	 */
	public function test_create_cleans_atomic_metadata_rename_failure(): void {
		$store = new class() extends Mumega_Motion_Backup_Store {
			/**
			 * Fails only the metadata publication rename.
			 */
			protected function rename_path( $from, $to ) {
				if ( 'metadata.json' === basename( $to ) ) {
					return false;
				}

				return parent::rename_path( $from, $to );
			}
		};

		$result = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assert_error_code( 'mumega_motion_backup_metadata_failed', $result );
		$this->assertSame(
			array( '.htaccess', '.sequence.json', '.sequence.lock', 'index.php', 'web.config' ),
			$this->backup_root_entries()
		);
	}

	/**
	 * Skips and quarantines an identifier directory left before metadata publication.
	 */
	public function test_latest_and_prune_skip_incomplete_identifier_directory(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$metadata = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );
		$partial  = $this->backup_root() . '/' . str_repeat( 'a', 32 );
		mkdir( $partial );
		file_put_contents( $partial . '/partial', 'incomplete' );

		$this->assertSame( $metadata, $store->latest() );
		$this->assertTrue( $store->prune() );
		$this->assertDirectoryDoesNotExist( $partial );
		$this->assertNotEmpty( glob( $this->backup_root() . '/.incomplete-*', GLOB_ONLYDIR ) );
	}

	/**
	 * Detects schema-valid metadata field tampering through its keyed signature.
	 *
	 * @dataProvider signed_metadata_tampering_provider
	 *
	 * @param string $field Tampered field.
	 * @param mixed  $value Replacement value.
	 */
	public function test_latest_rejects_signed_metadata_tampering( $field, $value ): void {
		$store     = new Mumega_Motion_Backup_Store( 'injected-test-secret' );
		$metadata  = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );
		$directory = $this->backup_root() . '/' . $metadata['id'];
		$stored    = json_decode( file_get_contents( $directory . '/metadata.json' ), true );
		$stored[ $field ] = $value;
		file_put_contents( $directory . '/metadata.json', wp_json_encode( $stored ) );

		$this->assert_error_code( 'mumega_motion_backup_integrity_failed', $store->latest() );
	}

	/**
	 * Signed metadata tampering cases.
	 *
	 * @return array
	 */
	public function signed_metadata_tampering_provider() {
		return array(
			'version'    => array( 'version', '9.9.9' ),
			'created_at' => array( 'created_at', 1.25 ),
			'sequence'   => array( 'sequence', 999 ),
		);
	}

	/**
	 * Rejects backup content changed after its deterministic manifest was signed.
	 */
	public function test_latest_and_restore_reject_tampered_backup_content(): void {
		$store     = new Mumega_Motion_Backup_Store( 'injected-test-secret' );
		$theme     = $this->create_theme( 'current', 'safe' );
		$metadata  = $store->create( $theme, '0.1.100' );
		$directory = $this->backup_root() . '/' . $metadata['id'];
		file_put_contents( $directory . '/theme/functions.php', '<?php // Tampered.' );

		$this->assert_error_code( 'mumega_motion_backup_integrity_failed', $store->latest() );
		$this->assert_error_code( 'mumega_motion_backup_integrity_failed', $store->restore( $metadata['id'], $theme ) );
	}

	/**
	 * Rejects valid signed metadata when verified under a different injected secret.
	 */
	public function test_injected_secret_binds_backup_metadata_without_being_stored(): void {
		$writer   = new Mumega_Motion_Backup_Store( 'first-secret' );
		$metadata = $writer->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );
		$contents = file_get_contents( $this->backup_root() . '/' . $metadata['id'] . '/metadata.json' );

		$this->assertStringNotContainsString( 'first-secret', $contents );
		$this->assert_error_code(
			'mumega_motion_backup_integrity_failed',
			( new Mumega_Motion_Backup_Store( 'second-secret' ) )->latest()
		);
	}

	/**
	 * Orders equal and decreasing wall-clock timestamps by a shared monotonic sequence.
	 */
	public function test_sequence_orders_timestamp_ties_across_instances_and_clock_rollback(): void {
		$theme  = $this->create_theme( 'current', 'safe' );
		$first  = $this->store_at_time( 1000.0 )->create( $theme, '0.1.100' );
		$second = $this->store_at_time( 1000.0 )->create( $theme, '0.1.101' );
		$third  = $this->store_at_time( 900.0 )->create( $theme, '0.1.102' );

		$this->assertArrayHasKey( 'sequence', $first );
		$this->assertSame( 1, $first['sequence'] );
		$this->assertSame( 2, $second['sequence'] );
		$this->assertSame( 3, $third['sequence'] );
		$this->assertSame( 1000.0, $first['created_at'] );
		$this->assertSame( 1000.0, $second['created_at'] );
		$this->assertSame( 900.0, $third['created_at'] );

		$reader = new Mumega_Motion_Backup_Store( 'sequence-test-secret' );
		$this->assertSame( $third, $reader->latest() );
		$this->assertTrue( $reader->prune( 2 ) );
		$this->assertDirectoryDoesNotExist( $this->backup_root() . '/' . $first['id'] );
		$this->assertDirectoryExists( $this->backup_root() . '/' . $second['id'] );
		$this->assertDirectoryExists( $this->backup_root() . '/' . $third['id'] );
	}

	/**
	 * Rejects rollback or tampering of the store-level sequence state.
	 */
	public function test_create_rejects_tampered_sequence_state(): void {
		$theme = $this->create_theme( 'current', 'safe' );
		$store = new Mumega_Motion_Backup_Store( 'sequence-test-secret' );
		$store->create( $theme, '0.1.100' );
		$sequence_path = $this->backup_root() . '/.sequence.json';

		$this->assertFileExists( $sequence_path );
		$state             = json_decode( file_get_contents( $sequence_path ), true );
		$state['sequence'] = 0;
		file_put_contents( $sequence_path, wp_json_encode( $state ) );

		$this->assert_error_code(
			'mumega_motion_backup_sequence_integrity_failed',
			$store->create( $theme, '0.1.101' )
		);
	}

	/**
	 * Returns the newest valid metadata and rejects tampering without leaking paths.
	 */
	public function test_latest_rejects_malformed_or_tampered_metadata(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$metadata = $store->create( $this->create_theme( 'current', 'safe' ), '0.1.100' );

		$this->assertSame( $metadata, $store->latest() );

		$metadata['id'] = '../outside';
		$backup_directories = glob( $this->backup_root() . '/[a-f0-9]*', GLOB_ONLYDIR );
		$this->assertCount( 1, $backup_directories );
		file_put_contents( $backup_directories[0] . '/metadata.json', wp_json_encode( $metadata ) );

		ob_start();
		$result = $store->latest();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assert_error_code( 'mumega_motion_backup_invalid_metadata', $result );
		$this->assertStringNotContainsString( $this->temporary_directory, $result->get_error_message() );
		$this->assertNull( $result->get_error_data() );
	}

	/**
	 * Rejects malformed IDs and valid-looking unknown IDs.
	 */
	public function test_restore_rejects_invalid_and_unknown_backup_ids(): void {
		$store = new Mumega_Motion_Backup_Store();
		$theme = $this->create_theme( 'current', 'safe' );

		$this->assert_error_code( 'mumega_motion_backup_invalid_id', $store->restore( '../outside', $theme ) );
		$this->assert_error_code(
			'mumega_motion_backup_not_found',
			$store->restore( str_repeat( 'a', 32 ), $theme )
		);
	}

	/**
	 * Restores through a sibling staging directory and leaves the backup intact.
	 */
	public function test_restore_replaces_theme_and_round_trips_metadata(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $store->create( $theme, '0.1.100' );
		file_put_contents( $theme . '/marker.txt', 'new' );
		file_put_contents( $theme . '/new-only.txt', 'remove me' );

		$result = $store->restore( $metadata['id'], $theme );

		$this->assertSame( $metadata, $result );
		$this->assertSame( 'old', file_get_contents( $theme . '/marker.txt' ) );
		$this->assertFileDoesNotExist( $theme . '/new-only.txt' );
		$this->assertFileExists( $this->backup_root() . '/' . $metadata['id'] . '/theme/marker.txt' );
		$this->assertSame( array(), glob( dirname( $theme ) . '/.mumega-motion-*' ) );
	}

	/**
	 * Does not swap the live theme when a backup changes while being staged.
	 */
	public function test_restore_rechecks_staged_manifest_before_swapping_live_theme(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $store->create( $theme, '0.1.100' );
		$backup   = $this->backup_root() . '/' . $metadata['id'] . '/theme';
		file_put_contents( $theme . '/marker.txt', 'current' );

		$GLOBALS['mumega_motion_test_copy_after_file'] = static function ( $source ) use ( $backup ) {
			if ( 'functions.php' === basename( $source ) ) {
				file_put_contents( $backup . '/marker.txt', 'changed-during-copy' );
			}
		};

		$result = $store->restore( $metadata['id'], $theme );

		$this->assert_error_code( 'mumega_motion_backup_integrity_failed', $result );
		$this->assertSame( 'current', file_get_contents( $theme . '/marker.txt' ) );
		$this->assertSame( array(), glob( dirname( $theme ) . '/.mumega-motion-*' ) );
	}

	/**
	 * Rejects a backup whose required runtime files are missing or empty.
	 */
	public function test_restore_rejects_backup_missing_exact_required_runtime_file(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $store->create( $theme, '0.1.100' );
		unlink( $this->backup_root() . '/' . $metadata['id'] . '/theme/build/index.js' );
		file_put_contents( $theme . '/marker.txt', 'current' );

		$result = $store->restore( $metadata['id'], $theme );

		$this->assert_error_code( 'mumega_motion_backup_invalid_contents', $result );
		$this->assertSame( 'current', file_get_contents( $theme . '/marker.txt' ) );
	}

	/**
	 * Rejects symbolic links in backup contents without following them.
	 */
	public function test_restore_rejects_symlinked_backup_content(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $store->create( $theme, '0.1.100' );
		$target   = $this->temporary_directory . '/outside.php';
		file_put_contents( $target, 'outside' );
		unlink( $this->backup_root() . '/' . $metadata['id'] . '/theme/functions.php' );
		symlink( $target, $this->backup_root() . '/' . $metadata['id'] . '/theme/functions.php' );

		$result = $store->restore( $metadata['id'], $theme );

		$this->assert_error_code( 'mumega_motion_backup_invalid_contents', $result );
		$this->assertSame( 'outside', file_get_contents( $target ) );
	}

	/**
	 * Rolls the displaced theme back when the restored-directory rename fails.
	 */
	public function test_restore_rolls_back_failed_second_rename(): void {
		$normal   = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $normal->create( $theme, '0.1.100' );
		file_put_contents( $theme . '/marker.txt', 'current' );
		$store = $this->rename_failing_store( array( 2 ) );

		$result = $store->restore( $metadata['id'], $theme );

		$this->assert_error_code( 'mumega_motion_backup_restore_swap_failed', $result );
		$this->assertSame( 'current', file_get_contents( $theme . '/marker.txt' ) );
		$this->assertSame( array(), glob( dirname( $theme ) . '/.mumega-motion-*' ) );
	}

	/**
	 * Falls back to copying the displaced theme if its rollback rename also fails.
	 */
	public function test_restore_recovers_current_theme_when_rollback_rename_fails(): void {
		$normal   = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'old' );
		$metadata = $normal->create( $theme, '0.1.100' );
		file_put_contents( $theme . '/marker.txt', 'current' );
		$store = $this->rename_failing_store( array( 2, 3 ) );

		$result = $store->restore( $metadata['id'], $theme );

		$this->assert_error_code( 'mumega_motion_backup_restore_swap_failed', $result );
		$this->assertSame( 'current', file_get_contents( $theme . '/marker.txt' ) );
		$this->assertSame( array(), glob( dirname( $theme ) . '/.mumega-motion-*' ) );
	}

	/**
	 * Keeps the newest three valid backups and never follows a symlink candidate.
	 */
	public function test_prune_removes_oldest_backups_and_retains_newest_three(): void {
		$store    = new Mumega_Motion_Backup_Store();
		$theme    = $this->create_theme( 'current', 'safe' );
		$backups  = array();

		for ( $index = 0; $index < 5; $index++ ) {
			$backups[] = $store->create( $theme, '0.1.' . ( 100 + $index ) );
			usleep( 1000 );
		}

		$outside = $this->temporary_directory . '/outside-prune';
		mkdir( $outside );
		file_put_contents( $outside . '/sentinel', 'safe' );
		symlink( $outside, $this->backup_root() . '/' . str_repeat( 'f', 32 ) );

		$this->assertTrue( $store->prune() );
		$this->assertDirectoryDoesNotExist( $this->backup_root() . '/' . $backups[0]['id'] );
		$this->assertDirectoryDoesNotExist( $this->backup_root() . '/' . $backups[1]['id'] );

		foreach ( array_slice( $backups, 2 ) as $retained ) {
			$this->assertDirectoryExists( $this->backup_root() . '/' . $retained['id'] );
		}

		$this->assertSame( 'safe', file_get_contents( $outside . '/sentinel' ) );
		$this->assertTrue( is_link( $this->backup_root() . '/' . str_repeat( 'f', 32 ) ) );
	}

	/**
	 * Builds a store with a controlled wall-clock reading.
	 *
	 * @param float $time Wall-clock value.
	 * @return Mumega_Motion_Backup_Store
	 */
	private function store_at_time( $time ) {
		return new class( $time ) extends Mumega_Motion_Backup_Store {
			private $time;

			public function __construct( $time ) {
				parent::__construct( 'sequence-test-secret' );
				$this->time = $time;
			}

			protected function current_time() {
				return $this->time;
			}
		};
	}

	/**
	 * Builds a store whose selected rename calls fail.
	 *
	 * @param array $failed_calls One-based calls that should fail.
	 * @return Mumega_Motion_Backup_Store
	 */
	private function rename_failing_store( array $failed_calls ) {
		return new class( $failed_calls ) extends Mumega_Motion_Backup_Store {
			private $calls = 0;
			private $failed_calls;

			public function __construct( array $failed_calls ) {
				parent::__construct();
				$this->failed_calls = $failed_calls;
			}

			protected function rename_path( $from, $to ) {
				$this->calls++;

				if ( in_array( $this->calls, $this->failed_calls, true ) ) {
					return false;
				}

				return parent::rename_path( $from, $to );
			}
		};
	}

	/**
	 * Creates a valid theme fixture.
	 *
	 * @param string $name   Directory name.
	 * @param string $marker Marker contents.
	 * @return string
	 */
	private function create_theme( $name, $marker ) {
		$directory = $this->temporary_directory . '/' . $name;
		mkdir( $directory . '/build', 0700, true );
		file_put_contents( $directory . '/style.css', '/* Theme Name: Mumega Motion */' );
		file_put_contents( $directory . '/functions.php', '<?php // Theme functions.' );
		file_put_contents( $directory . '/index.php', '<?php // Theme index.' );
		file_put_contents( $directory . '/build/index.js', 'console.log("theme");' );
		file_put_contents( $directory . '/build/index.asset.php', '<?php return array();' );
		file_put_contents( $directory . '/marker.txt', $marker );

		return $directory;
	}

	/**
	 * Returns the fixed protected backup root.
	 *
	 * @return string
	 */
	private function backup_root() {
		return $this->uploads_directory . '/mumega-motion-backups';
	}

	/**
	 * Lists sorted backup root entries.
	 *
	 * @return array
	 */
	private function backup_root_entries() {
		$entries = array_values( array_diff( scandir( $this->backup_root() ), array( '.', '..' ) ) );
		sort( $entries );

		return $entries;
	}

	/**
	 * Asserts a stable WordPress error code.
	 *
	 * @param string $expected Expected code.
	 * @param mixed  $actual   Actual result.
	 */
	private function assert_error_code( $expected, $actual ) {
		$this->assertInstanceOf( WP_Error::class, $actual );
		$this->assertSame( $expected, $actual->get_error_code() );
	}

	/**
	 * Removes a fixture path without following links.
	 *
	 * @param string $path Path to remove.
	 */
	private function remove_path( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) {
			unlink( $path );
			return;
		}

		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
			$this->remove_path( $path . '/' . $entry );
		}

		rmdir( $path );
	}
}
