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
	 * Accepts descendants when the optional explicit root entry is absent.
	 */
	public function test_accepts_package_without_explicit_root_directory(): void {
		$zip_path = $this->create_package( null, false );

		$this->assertTrue( Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) ) );
	}

	/**
	 * Accepts a valid archive when its comment contains a false EOCD signature.
	 */
	public function test_accepts_archive_comment_containing_eocd_signature(): void {
		$zip_path = $this->create_package();
		$this->append_archive_comment( $zip_path, 'comment' . "PK\x05\x06" . str_repeat( "\0", 18 ) );

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
			case 'slug_file_collision':
				$entries[ self::SLUG ] = 'not a directory';
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
			'slug file collision'   => array( 'slug_file_collision', 'mumega_motion_package_invalid_root' ),
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
			'repeated slash'   => array( self::SLUG . '/foo//' ),
		);
	}

	/**
	 * Rejects a ZIP64 extra field even when the archive remains under 20 MiB.
	 */
	public function test_rejects_zip64_extra_field(): void {
		$zip_path = $this->create_package();
		$this->add_central_extra_field(
			$zip_path,
			self::SLUG . '/build/index.asset.php',
			pack( 'vvV2', 0x0001, 8, 32 * 1024 * 1024, 0 )
		);

		$this->assertLessThan( 20 * 1024 * 1024, filesize( $zip_path ) );
		$this->assert_error_code(
			'mumega_motion_package_invalid_archive',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Rejects a compressed package entry whose declared size exceeds the cap.
	 */
	public function test_rejects_oversized_declared_entry(): void {
		$entries                                      = $this->required_entries();
		$entries[ self::SLUG . '/build/index.js' ] = str_repeat( 'A', ( 20 * 1024 * 1024 ) + 1 );
		$zip_path                                     = $this->create_package( $entries );

		$this->assertLessThan( 20 * 1024 * 1024, filesize( $zip_path ) );
		$this->assert_error_code(
			'mumega_motion_package_too_large',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Rejects ZIP64 sentinels and out-of-bounds unsigned EOCD fields.
	 *
	 * @dataProvider invalid_eocd_field_provider
	 *
	 * @param string $case EOCD mutation case.
	 */
	public function test_rejects_invalid_eocd_fields( string $case ): void {
		$zip_path = $this->create_package();

		if ( 'entry_sentinels' === $case ) {
			$this->replace_eocd_field( $zip_path, 8, pack( 'v', 0xffff ) );
			$this->replace_eocd_field( $zip_path, 10, pack( 'v', 0xffff ) );
		} elseif ( 'size_sentinel' === $case ) {
			$this->replace_eocd_field( $zip_path, 12, pack( 'V', 0xffffffff ) );
		} elseif ( 'offset_sentinel' === $case ) {
			$this->replace_eocd_field( $zip_path, 16, pack( 'V', 0xffffffff ) );
		} elseif ( 'size_out_of_bounds' === $case ) {
			$this->replace_eocd_field( $zip_path, 12, pack( 'V', 0x80000000 ) );
		} else {
			$this->replace_eocd_field( $zip_path, 16, pack( 'V', 0x80000000 ) );
		}

		$this->assert_error_code(
			'mumega_motion_package_invalid_archive',
			Mumega_Motion_Package_Validator::validate( $zip_path, $this->manifest_for( $zip_path ) )
		);
	}

	/**
	 * Invalid unsigned EOCD field cases.
	 *
	 * @return array<string,array{0:string}>
	 */
	public function invalid_eocd_field_provider(): array {
		return array(
			'entry-count sentinels' => array( 'entry_sentinels' ),
			'size sentinel'         => array( 'size_sentinel' ),
			'offset sentinel'       => array( 'offset_sentinel' ),
			'size out of bounds'    => array( 'size_out_of_bounds' ),
			'offset out of bounds'  => array( 'offset_out_of_bounds' ),
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
	 * @param array<string,string>|null $entries      Entry contents keyed by path.
	 * @param bool                      $include_root Whether to add an explicit root directory entry.
	 * @return string Archive path.
	 */
	private function create_package( ?array $entries = null, bool $include_root = true ): string {
		$zip_path = $this->temporary_directory . '/package-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$archive  = new ZipArchive();

		$entries = null === $entries ? $this->required_entries() : $entries;
		$root    = strtok( (string) array_key_first( $entries ), '/' );

		$this->assertTrue( $archive->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );

		if ( $include_root ) {
			$this->assertTrue( $archive->addEmptyDir( $root ) );
		}

		foreach ( $entries as $name => $contents ) {
			$this->assertTrue( $archive->addFromString( $name, $contents ) );
			$this->assertTrue( $archive->setCompressionName( $name, ZipArchive::CM_DEFLATE ) );
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
	 * Adds an extra field to one central-directory entry and repairs EOCD size.
	 *
	 * @param string $zip_path   Archive path.
	 * @param string $entry_name Target entry name.
	 * @param string $extra      Complete extra-field record.
	 */
	private function add_central_extra_field( string $zip_path, string $entry_name, string $extra ): void {
		$contents    = file_get_contents( $zip_path );
		$end_offset  = strrpos( $contents, "PK\x05\x06" );
		$end         = unpack( 'Vcentral_size/Vcentral_offset', substr( $contents, $end_offset + 12, 8 ) );
		$name_offset = strpos( $contents, $entry_name, $end['central_offset'] );
		$header      = $name_offset - 46;

		$this->assertSame( "PK\x01\x02", substr( $contents, $header, 4 ) );
		$lengths       = unpack( 'vname/vextra', substr( $contents, $header + 28, 4 ) );
		$insert_offset = $header + 46 + $lengths['name'] + $lengths['extra'];
		$contents      = substr_replace( $contents, pack( 'v', $lengths['extra'] + strlen( $extra ) ), $header + 30, 2 );
		$contents      = substr_replace( $contents, $extra, $insert_offset, 0 );
		$end_offset   += strlen( $extra );
		$contents      = substr_replace( $contents, pack( 'V', $end['central_size'] + strlen( $extra ) ), $end_offset + 12, 4 );
		file_put_contents( $zip_path, $contents );
	}

	/**
	 * Replaces bytes in the actual EOCD record.
	 *
	 * @param string $zip_path Archive path.
	 * @param int    $offset   Field offset from the EOCD signature.
	 * @param string $bytes    Replacement bytes.
	 */
	private function replace_eocd_field( string $zip_path, int $offset, string $bytes ): void {
		$contents   = file_get_contents( $zip_path );
		$end_offset = strrpos( $contents, "PK\x05\x06" );
		$contents   = substr_replace( $contents, $bytes, $end_offset + $offset, strlen( $bytes ) );
		file_put_contents( $zip_path, $contents );
	}

	/**
	 * Appends raw comment bytes to a comment-free archive.
	 *
	 * @param string $zip_path Archive path.
	 * @param string $comment  Raw comment bytes.
	 */
	private function append_archive_comment( string $zip_path, string $comment ): void {
		$contents   = file_get_contents( $zip_path );
		$end_offset = strrpos( $contents, "PK\x05\x06" );
		$this->assertSame( strlen( $contents ) - 22, $end_offset );
		$contents = substr_replace( $contents, pack( 'v', strlen( $comment ) ), $end_offset + 20, 2 ) . $comment;
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
