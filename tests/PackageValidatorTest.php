<?php
// phpcs:ignoreFile -- ZIP fixtures deliberately use direct temporary filesystem operations.
/**
 * Tests for downloaded theme package validation.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises ZIP validation before any package is extracted.
 */
final class PackageValidatorTest extends TestCase {
	private const SLUG = 'mumega-motion-theme';

	/**
	 * Directory containing this test's generated archives.
	 *
	 * @var string
	 */
	private $temporary_directory;

	/**
	 * Creates an isolated fixture directory.
	 */
	protected function setUp(): void {
		$this->temporary_directory = sys_get_temp_dir() . '/mumega-motion-package-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->temporary_directory, 0700, true );
	}

	/**
	 * Removes all generated archives.
	 */
	protected function tearDown(): void {
		$paths = glob( $this->temporary_directory . '/*' );

		if ( false !== $paths ) {
			foreach ( $paths as $path ) {
				unlink( $path );
			}
		}

		rmdir( $this->temporary_directory );
	}

	/**
	 * Accepts a matching, bounded package with one correctly named root.
	 */
	public function test_accepts_valid_package(): void {
		$zip_path = $this->create_package();

		$this->assertTrue( Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) ) );
	}

	/**
	 * Accepts a safe optional filename containing a space.
	 */
	public function test_accepts_safe_optional_filename_with_space(): void {
		$zip_path = $this->create_package(
			array_merge(
				$this->required_entries(),
				array( self::SLUG . '/release notes.txt' => 'Optional notes.' )
			)
		);

		$this->assertTrue( Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) ) );
	}

	/**
	 * Rejects package integrity and size violations before installation.
	 *
	 * @dataProvider integrity_failure_provider
	 *
	 * @param string $case          Fixture mutation to apply.
	 * @param string $expected_code Expected stable error code.
	 */
	public function test_rejects_integrity_failures( string $case, string $expected_code ): void {
		$zip_path = $this->create_package();
		$manifest = $this->manifest_for( $zip_path );

		if ( 'checksum' === $case ) {
			$manifest['sha256'] = str_repeat( '0', 64 );
		} else {
			file_put_contents( $zip_path, str_repeat( "\0", ( 20 * 1024 * 1024 ) + 1 ), FILE_APPEND );
			$manifest = $this->manifest_for( $zip_path );
		}

		$this->assert_error_code( $expected_code, Mumega_Motion_Package_Validator::validate( $zip_path, $manifest ) );
	}

	/**
	 * Integrity failure cases.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public function integrity_failure_provider(): array {
		return array(
			'checksum mismatch'  => array( 'checksum', 'mumega_motion_package_checksum_mismatch' ),
			'larger than 20 MiB' => array( 'oversize', 'mumega_motion_package_too_large' ),
		);
	}

	/**
	 * Rejects packages whose entry layout is not the exact theme layout.
	 *
	 * @dataProvider layout_failure_provider
	 *
	 * @param string $case          Fixture layout to create.
	 * @param string $expected_code Expected stable error code.
	 */
	public function test_rejects_invalid_layouts( string $case, string $expected_code ): void {
		$entries = $this->required_entries();

		switch ( $case ) {
			case 'multiple_roots':
				$entries['another-theme/readme.txt'] = 'unexpected root';
				break;
			case 'wrong_slug':
				$entries = $this->replace_root( $entries, 'renamed-theme' );
				break;
			case 'missing_required':
				unset( $entries[ self::SLUG . '/build/index.js' ] );
				break;
			case 'empty_required':
				$entries[ self::SLUG . '/functions.php' ] = '';
				break;
		}

		$zip_path = $this->create_package( $entries );

		$this->assert_error_code(
			$expected_code,
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Invalid layout cases.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public function layout_failure_provider(): array {
		return array(
			'multiple roots'        => array( 'multiple_roots', 'mumega_motion_package_invalid_root' ),
			'wrong root slug'       => array( 'wrong_slug', 'mumega_motion_package_invalid_root' ),
			'missing required file' => array( 'missing_required', 'mumega_motion_package_required_file_missing' ),
			'empty required file'   => array( 'empty_required', 'mumega_motion_package_required_file_empty' ),
		);
	}

	/**
	 * Rejects unsafe names before considering package contents.
	 *
	 * @dataProvider unsafe_path_provider
	 *
	 * @param string $entry_name Unsafe ZIP entry name.
	 */
	public function test_rejects_unsafe_entry_paths( string $entry_name ): void {
		$zip_path = $this->create_package(
			array_merge(
				$this->required_entries(),
				array( $entry_name => 'unsafe' )
			)
		);

		$this->assert_error_code(
			'mumega_motion_package_unsafe_path',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Unsafe entry path cases.
	 *
	 * @return array<string,array{0:string}>
	 */
	public function unsafe_path_provider(): array {
		return array(
			'parent traversal' => array( self::SLUG . '/../outside.php' ),
			'absolute path'    => array( '/tmp/outside.php' ),
			'Windows path'     => array( self::SLUG . '\\outside.php' ),
		);
	}

	/**
	 * Rejects a filename containing a null byte.
	 */
	public function test_rejects_null_byte_in_entry_path(): void {
		$safe_name = self::SLUG . '/unsafeXname.php';
		$zip_path  = $this->create_package(
			array_merge(
				$this->required_entries(),
				array( $safe_name => 'unsafe' )
			)
		);
		$this->replace_archive_bytes( $zip_path, $safe_name, self::SLUG . "/unsafe\0name.php" );

		$this->assert_error_code(
			'mumega_motion_package_unsafe_path',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Rejects Unix symlink entries using ZIP external attributes.
	 */
	public function test_rejects_symlink_entry(): void {
		$zip_path = $this->create_package();
		$archive  = new ZipArchive();
		$this->assertTrue( $archive->open( $zip_path ) );
		$this->assertTrue( $archive->addFromString( self::SLUG . '/linked.php', 'functions.php' ) );
		$this->assertTrue(
			$archive->setExternalAttributesName(
				self::SLUG . '/linked.php',
				ZipArchive::OPSYS_UNIX,
				( 0120777 << 16 )
			)
		);
		$this->assertTrue( $archive->close() );

		$this->assert_error_code(
			'mumega_motion_package_symlink',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Rejects unreadable, corrupt, and empty archive inputs.
	 *
	 * @dataProvider invalid_archive_provider
	 *
	 * @param string $case          Invalid archive case.
	 * @param string $expected_code Expected stable error code.
	 */
	public function test_rejects_invalid_archives( string $case, string $expected_code ): void {
		$zip_path = $this->temporary_directory . '/' . $case . '.zip';

		if ( 'corrupt' === $case ) {
			file_put_contents( $zip_path, 'not a ZIP archive' );
		} elseif ( 'empty' === $case ) {
			// A valid ZIP end-of-central-directory record with zero entries.
			file_put_contents( $zip_path, "PK\x05\x06" . str_repeat( "\0", 18 ) );
		}

		$manifest = array(
			'slug'   => self::SLUG,
			'sha256' => file_exists( $zip_path ) ? hash_file( 'sha256', $zip_path ) : str_repeat( '0', 64 ),
		);

		$this->assert_error_code( $expected_code, Mumega_Motion_Package_Validator::validate( $zip_path, $manifest ) );
	}

	/**
	 * Invalid archive cases.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public function invalid_archive_provider(): array {
		return array(
			'unreadable path' => array( 'unreadable', 'mumega_motion_package_unreadable' ),
			'corrupt ZIP'     => array( 'corrupt', 'mumega_motion_package_invalid_archive' ),
			'empty ZIP'       => array( 'empty', 'mumega_motion_package_empty_archive' ),
		);
	}

	/**
	 * Creates a package containing the supplied entries.
	 *
	 * @param array<string,string>|null $entries Entry contents keyed by path.
	 * @return string Archive path.
	 */
	private function create_package( ?array $entries = null ): string {
		$zip_path = $this->temporary_directory . '/package-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$archive  = new ZipArchive();

		$entries = null === $entries ? $this->required_entries() : $entries;
		$root    = strtok( (string) array_key_first( $entries ), '/' );

		$this->assertTrue( $archive->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		$this->assertTrue( $archive->addEmptyDir( $root ) );

		foreach ( $entries as $name => $contents ) {
			$this->assertTrue( $archive->addFromString( $name, $contents ) );
		}

		$this->assertTrue( $archive->close() );

		return $zip_path;
	}

	/**
	 * Returns the required non-empty package contents.
	 *
	 * @return array<string,string>
	 */
	private function required_entries(): array {
		return array(
			self::SLUG . '/style.css'             => '/* Theme Name: Mumega Motion */',
			self::SLUG . '/functions.php'         => '<?php // Theme functions.',
			self::SLUG . '/index.php'             => '<?php // Theme index.',
			self::SLUG . '/build/index.js'        => 'window.mumegaMotion = {};',
			self::SLUG . '/build/index.asset.php' => '<?php return array();',
		);
	}

	/**
	 * Replaces the root component in package entries.
	 *
	 * @param array<string,string> $entries Entry contents keyed by path.
	 * @param string               $root    Replacement root.
	 * @return array<string,string>
	 */
	private function replace_root( array $entries, string $root ): array {
		$replaced = array();

		foreach ( $entries as $name => $contents ) {
			$replaced[ $root . substr( $name, strlen( self::SLUG ) ) ] = $contents;
		}

		return $replaced;
	}

	/**
	 * Creates a validator manifest for an on-disk package.
	 *
	 * @param string $zip_path Archive path.
	 * @return array{slug:string,sha256:string}
	 */
	private function manifest_for( string $zip_path ): array {
		return array(
			'slug'   => self::SLUG,
			'sha256' => hash_file( 'sha256', $zip_path ),
		);
	}

	/**
	 * Replaces equal-length bytes in both ZIP filename records.
	 *
	 * @param string $zip_path    Archive path.
	 * @param string $safe_name   Original filename.
	 * @param string $unsafe_name Replacement filename.
	 */
	private function replace_archive_bytes( string $zip_path, string $safe_name, string $unsafe_name ): void {
		$this->assertSame( strlen( $safe_name ), strlen( $unsafe_name ) );
		$contents = file_get_contents( $zip_path );
		$this->assertIsString( $contents );
		$this->assertSame( 2, substr_count( $contents, $safe_name ) );
		$contents = str_replace( $safe_name, $unsafe_name, $contents );
		$this->assertSame( 2, substr_count( $contents, $unsafe_name ) );
		file_put_contents( $zip_path, $contents );
	}

	/**
	 * Asserts a stable WordPress error code.
	 *
	 * @param string $expected_code Expected error code.
	 * @param mixed  $result        Validator result.
	 */
	private function assert_error_code( string $expected_code, $result ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_code, $result->get_error_code() );
	}
}
