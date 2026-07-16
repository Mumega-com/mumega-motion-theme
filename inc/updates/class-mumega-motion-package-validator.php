<?php
/**
 * Downloaded theme package validation.
 *
 * @package Mumega_Motion
 */

/**
 * Validates a release ZIP without extracting it.
 */
final class Mumega_Motion_Package_Validator {
	private const MAX_PACKAGE_BYTES = 20 * 1024 * 1024;
	private const REQUIRED_FILES    = array(
		'style.css',
		'functions.php',
		'index.php',
		'build/index.js',
		'build/index.asset.php',
	);

	/**
	 * Validates package integrity and entry metadata before installation.
	 *
	 * @param string $zip_path Downloaded ZIP path.
	 * @param array  $manifest Normalized release manifest.
	 * @return true|WP_Error
	 */
	public static function validate( $zip_path, array $manifest ) {
		if ( ! is_string( $zip_path ) || ! is_file( $zip_path ) || ! is_readable( $zip_path ) ) {
			return self::error( 'mumega_motion_package_unreadable', 'The downloaded theme package is not readable.' );
		}

		$package_size = filesize( $zip_path );

		if ( false === $package_size ) {
			return self::error( 'mumega_motion_package_unreadable', 'The downloaded theme package size could not be read.' );
		}

		if ( self::MAX_PACKAGE_BYTES < $package_size ) {
			return self::error( 'mumega_motion_package_too_large', 'The downloaded theme package exceeds 20 MiB.' );
		}

		$expected_checksum = isset( $manifest['sha256'] ) && is_string( $manifest['sha256'] ) ? $manifest['sha256'] : '';
		$actual_checksum   = hash_file( 'sha256', $zip_path );

		if ( false === $actual_checksum || ! hash_equals( $expected_checksum, $actual_checksum ) ) {
			return self::error( 'mumega_motion_package_checksum_mismatch', 'The downloaded theme package checksum does not match.' );
		}

		$archive = new ZipArchive();

		if ( true !== $archive->open( $zip_path, ZipArchive::RDONLY ) ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package is not a valid ZIP archive.' );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive's public API.
		$entry_count = $archive->numFiles;

		if ( 0 === $entry_count ) {
			$archive->close();

			return self::error( 'mumega_motion_package_empty_archive', 'The downloaded theme package is empty.' );
		}

		$raw_name_result = self::inspect_zip_metadata( $zip_path, $entry_count );

		if ( is_wp_error( $raw_name_result ) ) {
			$archive->close();

			return $raw_name_result;
		}

		$roots   = array();
		$entries = array();
		$slug    = isset( $manifest['slug'] ) && is_string( $manifest['slug'] ) ? $manifest['slug'] : '';

		for ( $index = 0; $index < $entry_count; $index++ ) {
			$name = $archive->getNameIndex( $index );

			if ( false === $name || self::is_unsafe_path( $name ) ) {
				$archive->close();

				return self::error( 'mumega_motion_package_unsafe_path', 'The downloaded theme package contains an unsafe path.' );
			}

			$is_directory   = '/' === substr( $name, -1 );
			$entry_path     = $is_directory ? substr( $name, 0, -1 ) : $name;
			$root           = explode( '/', $entry_path, 2 )[0];
			$roots[ $root ] = true;

			if ( $slug === $entry_path && ! $is_directory ) {
				$archive->close();

				return self::error( 'mumega_motion_package_invalid_root', 'The downloaded theme package contains a file colliding with its root directory.' );
			}

			if ( self::entry_is_symlink( $archive, $index ) ) {
				$archive->close();

				return self::error( 'mumega_motion_package_symlink', 'The downloaded theme package contains a symbolic link.' );
			}

			$stat = $archive->statIndex( $index );

			if ( false === $stat ) {
				$archive->close();

				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains an invalid entry.' );
			}

			$entry_size = isset( $stat['size'] ) ? $stat['size'] : 0;

			if ( ! is_int( $entry_size ) || 0 > $entry_size ) {
				$archive->close();

				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains an invalid entry size.' );
			}

			if ( self::MAX_PACKAGE_BYTES < $entry_size ) {
				$archive->close();

				return self::error( 'mumega_motion_package_too_large', 'The downloaded theme package declares an oversized entry.' );
			}

			$entries[ $name ] = $entry_size;
		}

		$archive->close();

		if ( 1 !== count( $roots ) || array( $slug ) !== array_keys( $roots ) ) {
			return self::error( 'mumega_motion_package_invalid_root', 'The downloaded theme package has an invalid root directory.' );
		}

		foreach ( self::REQUIRED_FILES as $required_file ) {
			$required_path = $slug . '/' . $required_file;

			if ( ! array_key_exists( $required_path, $entries ) ) {
				return self::error( 'mumega_motion_package_required_file_missing', 'The downloaded theme package is missing a required file.' );
			}

			if ( 0 === $entries[ $required_path ] ) {
				return self::error( 'mumega_motion_package_required_file_empty', 'The downloaded theme package contains an empty required file.' );
			}
		}

		return true;
	}

	/**
	 * Reports whether an archive entry could escape or confuse its root.
	 *
	 * @param string $name ZIP entry name.
	 * @return bool
	 */
	private static function is_unsafe_path( $name ) {
		if ( '' === $name || 1 === preg_match( '/[\x00-\x1f\x7f]/', $name ) || false !== strpos( $name, '\\' ) || '/' === $name[0] ) {
			return true;
		}

		$trimmed_name = '/' === substr( $name, -1 ) ? substr( $name, 0, -1 ) : $name;

		if ( '' === $trimmed_name ) {
			return true;
		}

		foreach ( explode( '/', $trimmed_name ) as $component ) {
			if ( '' === $component || '.' === $component || '..' === $component ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks central-directory filename bytes that libzip may normalize.
	 *
	 * The package size limit bounds this binary read to 20 MiB. Only ZIP
	 * metadata is parsed; entry contents are never decompressed or extracted.
	 *
	 * @param string $zip_path    Archive path.
	 * @param int    $entry_count Entry count reported by ZipArchive.
	 * @return true|WP_Error
	 */
	private static function inspect_zip_metadata( $zip_path, $entry_count ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bounded ZIP metadata read.
		$contents = file_get_contents( $zip_path );

		if ( false === $contents ) {
			return self::error( 'mumega_motion_package_unreadable', 'The downloaded theme package could not be inspected.' );
		}

		$archive_length  = strlen( $contents );
		$search_start    = max( 0, $archive_length - 65557 );
		$search_tail     = substr( $contents, $search_start );
		$candidates      = array();
		$cursor          = 0;
		$relative_offset = strpos( $search_tail, "PK\x05\x06", $cursor );

		while ( false !== $relative_offset ) {
			$candidates[]    = $search_start + $relative_offset;
			$cursor          = $relative_offset + 1;
			$relative_offset = strpos( $search_tail, "PK\x05\x06", $cursor );
		}

		$first_error = null;

		foreach ( array_reverse( $candidates ) as $end_offset ) {
			$end = self::parse_end_record( $contents, $end_offset, $entry_count );

			if ( false === $end ) {
				continue;
			}

			$result = self::inspect_central_directory( $contents, $end, $entry_count );

			if ( true === $result ) {
				return true;
			}

			if ( null === $first_error ) {
				$first_error = $result;
			}
		}

		return null !== $first_error
			? $first_error
			: self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has invalid ZIP metadata.' );
	}

	/**
	 * Parses one bounded end-of-central-directory candidate.
	 *
	 * @param string $contents    Complete bounded archive bytes.
	 * @param int    $end_offset  Candidate EOCD offset.
	 * @param int    $entry_count Entry count reported by ZipArchive.
	 * @return array|false
	 */
	private static function parse_end_record( $contents, $end_offset, $entry_count ) {
		$archive_length = strlen( $contents );

		if ( ! self::range_fits( $end_offset, 22, $archive_length ) ) {
			return false;
		}

		$end = unpack(
			'vdisk/vcentral_disk/ventries_on_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length',
			substr( $contents, $end_offset + 4, 18 )
		);

		if (
			false === $end ||
			0xffff === $end['entries_on_disk'] ||
			0xffff === $end['entries'] ||
			0xffffffff === $end['central_size'] ||
			0xffffffff === $end['central_offset'] ||
			0 !== $end['disk'] ||
			0 !== $end['central_disk'] ||
			$end['entries_on_disk'] !== $end['entries'] ||
			$entry_count !== $end['entries'] ||
			! self::bounded_unsigned( $end['comment_length'], $archive_length - $end_offset - 22 ) ||
			$end['comment_length'] !== $archive_length - $end_offset - 22 ||
			! self::bounded_unsigned( $end['central_offset'], $end_offset ) ||
			! self::bounded_unsigned( $end['central_size'], $end_offset ) ||
			! self::range_fits( $end['central_offset'], $end['central_size'], $end_offset )
		) {
			return false;
		}

		$end['end_offset'] = $end_offset;

		return $end;
	}

	/**
	 * Validates central and matching local entry metadata without decompression.
	 *
	 * @param string $contents    Complete bounded archive bytes.
	 * @param array  $end         Parsed EOCD fields.
	 * @param int    $entry_count Expected entry count.
	 * @return true|WP_Error
	 */
	private static function inspect_central_directory( $contents, array $end, $entry_count ) {
		$offset      = $end['central_offset'];
		$central_end = $offset + $end['central_size'];

		for ( $index = 0; $index < $entry_count; $index++ ) {
			if ( ! self::range_fits( $offset, 46, $central_end ) || "PK\x01\x02" !== substr( $contents, $offset, 4 ) ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has an invalid central directory.' );
			}

			$entry = unpack(
				'Vcompressed/Vuncompressed/vname/vextra/vcomment/vdisk/vinternal/x4/Vlocal_offset',
				substr( $contents, $offset + 20, 26 )
			);

			if ( false === $entry || ! self::central_entry_fields_are_valid( $entry, $end['central_offset'] ) ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains unsupported ZIP64 or invalid entry metadata.' );
			}

			if ( self::MAX_PACKAGE_BYTES < $entry['uncompressed'] ) {
				return self::error( 'mumega_motion_package_too_large', 'The downloaded theme package declares an oversized entry.' );
			}

			$variable_length = $entry['name'] + $entry['extra'] + $entry['comment'];

			if ( ! self::range_fits( $offset + 46, $variable_length, $central_end ) ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has a truncated central directory.' );
			}

			$name_offset  = $offset + 46;
			$extra_offset = $name_offset + $entry['name'];
			$raw_name     = substr( $contents, $name_offset, $entry['name'] );
			$raw_extra    = substr( $contents, $extra_offset, $entry['extra'] );

			if ( false !== strpos( $raw_name, "\0" ) ) {
				return self::error( 'mumega_motion_package_unsafe_path', 'The downloaded theme package contains a null byte in an entry path.' );
			}

			if ( ! self::extra_fields_are_supported( $raw_extra ) ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains unsupported ZIP64 or malformed extra metadata.' );
			}

			$local_result = self::inspect_local_entry( $contents, $entry['local_offset'], $end['central_offset'], $raw_name );

			if ( true !== $local_result ) {
				return $local_result;
			}

			$offset += 46 + $variable_length;
		}

		if ( $offset !== $central_end || $central_end !== $end['end_offset'] ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has unexpected central-directory data.' );
		}

		return true;
	}

	/**
	 * Checks fixed-width central-directory fields before arithmetic.
	 *
	 * @param array $entry          Parsed central entry fields.
	 * @param int   $central_offset Start of the central directory.
	 * @return bool
	 */
	private static function central_entry_fields_are_valid( array $entry, $central_offset ) {
		return 0xffffffff !== $entry['compressed'] &&
			0xffffffff !== $entry['uncompressed'] &&
			0xffffffff !== $entry['local_offset'] &&
			0xffff !== $entry['disk'] &&
			0 === $entry['disk'] &&
			self::bounded_unsigned( $entry['compressed'], self::MAX_PACKAGE_BYTES ) &&
			self::bounded_unsigned( $entry['uncompressed'], 0xffffffff ) &&
			self::bounded_unsigned( $entry['local_offset'], $central_offset ) &&
			self::bounded_unsigned( $entry['name'], 0xffff ) &&
			self::bounded_unsigned( $entry['extra'], 0xffff ) &&
			self::bounded_unsigned( $entry['comment'], 0xffff );
	}

	/**
	 * Validates one local header and its raw extra metadata.
	 *
	 * @param string $contents       Complete bounded archive bytes.
	 * @param int    $local_offset   Local header offset.
	 * @param int    $central_offset Central-directory boundary.
	 * @param string $central_name   Raw central filename.
	 * @return true|WP_Error
	 */
	private static function inspect_local_entry( $contents, $local_offset, $central_offset, $central_name ) {
		if ( ! self::range_fits( $local_offset, 30, $central_offset ) || "PK\x03\x04" !== substr( $contents, $local_offset, 4 ) ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has an invalid local entry header.' );
		}

		$local = unpack( 'Vcompressed/Vuncompressed/vname/vextra', substr( $contents, $local_offset + 18, 12 ) );

		if (
			false === $local ||
			0xffffffff === $local['compressed'] ||
			0xffffffff === $local['uncompressed'] ||
			! self::bounded_unsigned( $local['compressed'], self::MAX_PACKAGE_BYTES ) ||
			! self::bounded_unsigned( $local['uncompressed'], 0xffffffff ) ||
			! self::bounded_unsigned( $local['name'], 0xffff ) ||
			! self::bounded_unsigned( $local['extra'], 0xffff )
		) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains unsupported ZIP64 or invalid local metadata.' );
		}

		if ( self::MAX_PACKAGE_BYTES < $local['uncompressed'] ) {
			return self::error( 'mumega_motion_package_too_large', 'The downloaded theme package declares an oversized entry.' );
		}

		$variable_length = $local['name'] + $local['extra'];

		if ( ! self::range_fits( $local_offset + 30, $variable_length, $central_offset ) ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has truncated local metadata.' );
		}

		$local_name  = substr( $contents, $local_offset + 30, $local['name'] );
		$local_extra = substr( $contents, $local_offset + 30 + $local['name'], $local['extra'] );

		if ( $central_name !== $local_name || ! self::extra_fields_are_supported( $local_extra ) ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has inconsistent or unsupported local metadata.' );
		}

		return true;
	}

	/**
	 * Rejects malformed and ZIP64 (0x0001) extra fields.
	 *
	 * @param string $extra Raw extra-field bytes.
	 * @return bool
	 */
	private static function extra_fields_are_supported( $extra ) {
		$offset = 0;
		$length = strlen( $extra );

		while ( $offset < $length ) {
			if ( ! self::range_fits( $offset, 4, $length ) ) {
				return false;
			}

			$field = unpack( 'vid/vsize', substr( $extra, $offset, 4 ) );

			if ( false === $field || 0x0001 === $field['id'] || ! self::range_fits( $offset + 4, $field['size'], $length ) ) {
				return false;
			}

			$offset += 4 + $field['size'];
		}

		return true;
	}

	/**
	 * Checks an unpacked unsigned field against a runtime-safe bound.
	 *
	 * @param mixed $value Parsed value.
	 * @param int   $limit Inclusive maximum.
	 * @return bool
	 */
	private static function bounded_unsigned( $value, $limit ) {
		return is_int( $value ) && is_int( $limit ) && 0 <= $value && 0 <= $limit && $value <= $limit;
	}

	/**
	 * Checks a byte range using subtraction before addition.
	 *
	 * @param mixed $offset Range start.
	 * @param mixed $length Range length.
	 * @param mixed $limit  Exclusive upper bound.
	 * @return bool
	 */
	private static function range_fits( $offset, $length, $limit ) {
		return self::bounded_unsigned( $offset, $limit ) &&
			self::bounded_unsigned( $length, $limit ) &&
			$length <= $limit - $offset;
	}

	/**
	 * Detects Unix symlinks from an entry's external mode attributes.
	 *
	 * @param ZipArchive $archive Open archive.
	 * @param int        $index   Entry index.
	 * @return bool
	 */
	private static function entry_is_symlink( ZipArchive $archive, $index ) {
		$operating_system = 0;
		$attributes       = 0;

		if ( ! $archive->getExternalAttributesIndex( $index, $operating_system, $attributes ) ) {
			return false;
		}

		return ZipArchive::OPSYS_UNIX === $operating_system && 0120000 === ( ( $attributes >> 16 ) & 0170000 );
	}

	/**
	 * Creates a stable package validation error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private static function error( $code, $message ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Callers supply fixed, developer-owned literals.
		return new WP_Error( $code, __( $message, 'mumega-motion' ) );
	}
}
