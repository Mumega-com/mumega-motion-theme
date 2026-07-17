<?php
// phpcs:ignoreFile -- Transaction fixtures deliberately use direct temporary filesystem operations.
/**
 * Tests for the verified theme update transaction.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises update recovery and constrained rollback using injected effects.
 */
final class UpdaterTest extends TestCase {
	/**
	 * Temporary package path used by the downloader double.
	 *
	 * @var string
	 */
	private $package_path;

	/**
	 * Observable collaborator state.
	 *
	 * @var array
	 */
	private $state;

	/**
	 * Creates a downloaded-package fixture.
	 */
	protected function setUp(): void {
		$this->package_path = tempnam( sys_get_temp_dir(), 'mumega-motion-update-' );
		file_put_contents( $this->package_path, 'package' );
		$this->state = array(
			'inspections' => array( $this->inspection( '0.1.100' ) ),
			'calls'       => array(),
			'backup_id'   => '0123456789abcdef0123456789abcdef',
		);
	}

	/**
	 * Removes the fixture if a failing test left it behind.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->package_path ) ) {
			unlink( $this->package_path );
		}
	}

	/**
	 * Does not download or mutate files when the release is not newer.
	 */
	public function test_update_is_a_noop_when_the_release_is_not_newer(): void {
		$updater = $this->updater( array( 'version' => '0.1.100' ) );

		$result = $updater->update();

		$this->assertSame(
			array(
				'status'          => 'up_to_date',
				'previous_version'=> '0.1.100',
				'current_version' => '0.1.100',
				'release_tag'     => 'edge-v0.1.100',
				'verified'        => true,
			),
			$result
		);
		$this->assertSame( array( 'inspect', 'release' ), $this->state['calls'] );
	}

	/**
	 * Installs, verifies, prunes, and returns evidence for a successful update.
	 */
	public function test_update_returns_the_verified_success_contract(): void {
		$this->state['inspections'][] = $this->inspection( '0.1.101' );
		$updater                      = $this->updater();

		$result = $updater->update();

		$this->assertSame(
			array(
				'status'           => 'updated',
				'previous_version' => '0.1.100',
				'current_version'  => '0.1.101',
				'release_tag'      => 'edge-v0.1.101',
				'backup_id'        => '0123456789abcdef0123456789abcdef',
				'checksum'         => str_repeat( 'a', 64 ),
				'verified'         => true,
			),
			$result
		);
		$this->assertSame( array( 'inspect', 'release', 'download', 'validate', 'backup', 'install', 'flush', 'inspect', 'prune', 'cleanup' ), $this->state['calls'] );
		$this->assertFileDoesNotExist( $this->package_path );
	}

	/**
	 * Returns pre-backup failures unchanged and always deletes a downloaded package.
	 *
	 * @dataProvider pre_backup_failure_provider
	 *
	 * @param string $failure Failed collaborator.
	 */
	public function test_update_returns_pre_backup_failures_without_restore( string $failure ): void {
		$updater = $this->updater( array( 'failure' => $failure ) );

		$result = $updater->update();

		$this->assert_error_code( 'mumega_motion_test_' . $failure . '_failed', $result );
		$this->assertNotContains( 'restore', $this->state['calls'] );
		if ( 'download' !== $failure ) {
			$this->assertFileDoesNotExist( $this->package_path );
		}
	}

	/**
	 * Pre-backup failure cases.
	 *
	 * @return array<string,array{0:string}>
	 */
	public function pre_backup_failure_provider(): array {
		return array(
			'download' => array( 'download' ),
			'validate' => array( 'validate' ),
			'backup'   => array( 'backup' ),
		);
	}

	/**
	 * Automatically restores a created backup after each later update failure.
	 *
	 * @dataProvider post_backup_failure_provider
	 *
	 * @param string $failure       Failed collaborator or verification case.
	 * @param string $expected_code Expected update error code.
	 */
	public function test_update_restores_after_every_post_backup_failure( string $failure, string $expected_code ): void {
		$options = array( 'failure' => $failure );
		if ( 'version' === $failure ) {
			$this->state['inspections'][] = $this->inspection( '0.1.102' );
		} elseif ( 'inactive' === $failure ) {
			$this->state['inspections'][] = $this->inspection( '0.1.101', false );
		} elseif ( 'required_files' === $failure ) {
			$this->state['inspections'][] = $this->inspection( '0.1.101', true, false );
		} elseif ( 'prune' === $failure ) {
			$this->state['inspections'][] = $this->inspection( '0.1.101' );
		}
		$updater = $this->updater( $options );

		$result = $updater->update();

		$this->assert_error_code( 'mumega_motion_update_failed_restored', $result );
		$this->assertSame( $expected_code, $result->get_error_data()['update_error']['code'] );
		$this->assertSame( $this->state['backup_id'], $result->get_error_data()['backup']['id'] );
		$this->assertContains( 'restore', $this->state['calls'] );
		if ( 'prune' !== $failure ) {
			$this->assertNotContains( 'prune', $this->state['calls'] );
		}
		$this->assertFileDoesNotExist( $this->package_path );
	}

	/**
	 * Failures that happen only after a backup is created.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public function post_backup_failure_provider(): array {
		return array(
			'installer'       => array( 'install', 'mumega_motion_test_install_failed' ),
			'version mismatch' => array( 'version', 'mumega_motion_update_version_mismatch' ),
			'inactive theme'  => array( 'inactive', 'mumega_motion_update_inactive_theme' ),
			'missing files'   => array( 'required_files', 'mumega_motion_update_required_files_missing' ),
			'prune failure'   => array( 'prune', 'mumega_motion_test_prune_failed' ),
		);
	}

	/**
	 * Distinguishes a failed recovery from an update that was restored.
	 */
	public function test_update_reports_when_automatic_restore_also_fails(): void {
		$updater = $this->updater( array( 'failure' => 'install', 'restore_failure' => true ) );

		$result = $updater->update();

		$this->assert_error_code( 'mumega_motion_update_and_restore_failed', $result );
		$this->assertSame( 'mumega_motion_test_install_failed', $result->get_error_data()['update_error']['code'] );
		$this->assertSame( 'mumega_motion_test_restore_failed', $result->get_error_data()['restore_error']['code'] );
		$this->assertFileDoesNotExist( $this->package_path );
	}

	/**
	 * Uses only the latest backup and automatically restores the safety backup on failed rollback verification.
	 */
	public function test_rollback_uses_latest_backup_and_recovers_with_safety_backup(): void {
		$this->state['latest']         = array( 'id' => 'abcdefabcdefabcdefabcdefabcdefab', 'version' => '0.1.99' );
		$this->state['inspections'][]  = $this->inspection( '0.1.98' );
		$this->state['inspections'][]  = $this->inspection( '0.1.100' );
		$updater                       = $this->updater();

		$result = $updater->rollback();

		$this->assert_error_code( 'mumega_motion_rollback_failed_recovered', $result );
		$this->assertSame(
			array( 'inspect', 'latest', 'backup', 'restore', 'flush', 'inspect', 'restore' ),
			$this->state['calls']
		);
		$this->assertSame( $this->state['backup_id'], $result->get_error_data()['safety_backup']['id'] );
	}

	/**
	 * Builds an updater with deterministic, observable collaborators.
	 *
	 * @param array $options Fixture overrides.
	 * @return Mumega_Motion_Updater
	 */
	private function updater( array $options = array() ): Mumega_Motion_Updater {
		$state =& $this->state;
		$package_path = $this->package_path;
		$manifest = array(
			'slug'        => 'mumega-motion-theme',
			'version'     => isset( $options['version'] ) ? $options['version'] : '0.1.101',
			'release_tag' => isset( $options['version'] ) ? 'edge-v' . $options['version'] : 'edge-v0.1.101',
			'package_url' => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.101/mumega-motion-theme-0.1.101.zip',
			'sha256'      => str_repeat( 'a', 64 ),
		);
		$failure = isset( $options['failure'] ) ? $options['failure'] : '';

		return new Mumega_Motion_Updater(
			array(
				'release' => static function ( $force ) use ( &$state, $manifest ) {
					$state['calls'][] = 'release';
					return $manifest;
				},
				'download' => static function ( $url ) use ( &$state, $package_path, $failure ) {
					$state['calls'][] = 'download';
					return 'download' === $failure ? new WP_Error( 'mumega_motion_test_download_failed', 'Download failed.' ) : $package_path;
				},
				'validate' => static function ( $path, $manifest_arg ) use ( &$state, $failure ) {
					$state['calls'][] = 'validate';
					return 'validate' === $failure ? new WP_Error( 'mumega_motion_test_validate_failed', 'Validation failed.' ) : true;
				},
				'backup_create' => static function ( $directory, $version ) use ( &$state, $failure ) {
					$state['calls'][] = 'backup';
					return 'backup' === $failure ? new WP_Error( 'mumega_motion_test_backup_failed', 'Backup failed.' ) : array( 'id' => $state['backup_id'], 'version' => $version );
				},
				'backup_restore' => static function ( $id, $directory ) use ( &$state, $options ) {
					$state['calls'][] = 'restore';
					return ! empty( $options['restore_failure'] ) ? new WP_Error( 'mumega_motion_test_restore_failed', 'Restore failed.' ) : array( 'id' => $id, 'version' => '0.1.100' );
				},
				'backup_latest' => static function () use ( &$state ) {
					$state['calls'][] = 'latest';
					return isset( $state['latest'] ) ? $state['latest'] : new WP_Error( 'mumega_motion_backup_not_found', 'No backup.' );
				},
				'backup_prune' => static function () use ( &$state, $failure ) {
					$state['calls'][] = 'prune';
					return 'prune' === $failure ? new WP_Error( 'mumega_motion_test_prune_failed', 'Pruning failed.' ) : true;
				},
				'install' => static function ( $package, $manifest_arg ) use ( &$state, $failure ) {
					$state['calls'][] = 'install';
					return 'install' === $failure ? new WP_Error( 'mumega_motion_test_install_failed', 'Install failed.' ) : true;
				},
				'flush' => static function () use ( &$state ) {
					$state['calls'][] = 'flush';
				},
				'inspect' => static function () use ( &$state ) {
					$state['calls'][] = 'inspect';
					return array_shift( $state['inspections'] );
				},
				'cleanup' => static function ( $path ) use ( &$state ) {
					$state['calls'][] = 'cleanup';
					if ( file_exists( $path ) ) {
						unlink( $path );
					}
					return true;
				},
			)
		);
	}

	/**
	 * Creates an installed-theme inspection.
	 *
	 * @param string $version Installed version.
	 * @param bool   $active Whether the correct stylesheet is active.
	 * @param bool   $required_files Whether the required files are present.
	 * @return array
	 */
	private function inspection( string $version, bool $active = true, bool $required_files = true ): array {
		return array(
			'slug'           => $active ? 'mumega-motion-theme' : 'another-theme',
			'version'        => $version,
			'directory'      => sys_get_temp_dir(),
			'required_files' => $required_files,
		);
	}

	/**
	 * Asserts a stable WordPress error code.
	 *
	 * @param string         $code Expected code.
	 * @param mixed|WP_Error $result Result value.
	 */
	private function assert_error_code( string $code, $result ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}
}
