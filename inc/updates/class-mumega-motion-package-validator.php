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

		$raw_name_result = self::inspect_raw_filenames( $zip_path, $entry_count );

		if ( is_wp_error( $raw_name_result ) ) {
			$archive->close();

			return $raw_name_result;
		}

		$roots   = array();
		$entries = array();

		for ( $index = 0; $index < $entry_count; $index++ ) {
			$name = $archive->getNameIndex( $index );

			if ( false === $name || self::is_unsafe_path( $name ) ) {
				$archive->close();

				return self::error( 'mumega_motion_package_unsafe_path', 'The downloaded theme package contains an unsafe path.' );
			}

			$root           = explode( '/', rtrim( $name, '/' ), 2 )[0];
			$roots[ $root ] = true;

			if ( self::entry_is_symlink( $archive, $index ) ) {
				$archive->close();

				return self::error( 'mumega_motion_package_symlink', 'The downloaded theme package contains a symbolic link.' );
			}

			$stat = $archive->statIndex( $index );

			if ( false === $stat ) {
				$archive->close();

				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package contains an invalid entry.' );
			}

			$entries[ $name ] = isset( $stat['size'] ) ? (int) $stat['size'] : 0;
		}

		$archive->close();
		$slug = isset( $manifest['slug'] ) && is_string( $manifest['slug'] ) ? $manifest['slug'] : '';

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

		$trimmed_name = rtrim( $name, '/' );

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
	private static function inspect_raw_filenames( $zip_path, $entry_count ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bounded ZIP metadata read.
		$contents = file_get_contents( $zip_path );

		if ( false === $contents ) {
			return self::error( 'mumega_motion_package_unreadable', 'The downloaded theme package could not be inspected.' );
		}

		$end_signature = "PK\x05\x06";
		$end_offset    = strrpos( $contents, $end_signature );

		if ( false === $end_offset || strlen( $contents ) < $end_offset + 22 ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has invalid ZIP metadata.' );
		}

		$end = unpack(
			'vdisk/vcentral_disk/ventries_on_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length',
			substr( $contents, $end_offset + 4, 18 )
		);

		if (
			false === $end ||
			0 !== $end['disk'] ||
			0 !== $end['central_disk'] ||
			$end['entries_on_disk'] !== $end['entries'] ||
			$entry_count !== $end['entries'] ||
			strlen( $contents ) !== $end_offset + 22 + $end['comment_length'] ||
			$end['central_offset'] + $end['central_size'] > $end_offset
		) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has inconsistent ZIP metadata.' );
		}

		$offset      = $end['central_offset'];
		$central_end = $offset + $end['central_size'];

		for ( $index = 0; $index < $entry_count; $index++ ) {
			if ( $offset + 46 > $central_end || "PK\x01\x02" !== substr( $contents, $offset, 4 ) ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has an invalid central directory.' );
			}

			$lengths = unpack( 'vname/vextra/vcomment', substr( $contents, $offset + 28, 6 ) );

			if ( false === $lengths ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has invalid entry metadata.' );
			}

			$entry_size = 46 + $lengths['name'] + $lengths['extra'] + $lengths['comment'];

			if ( $offset + $entry_size > $central_end ) {
				return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has a truncated central directory.' );
			}

			$raw_name = substr( $contents, $offset + 46, $lengths['name'] );

			if ( false !== strpos( $raw_name, "\0" ) ) {
				return self::error( 'mumega_motion_package_unsafe_path', 'The downloaded theme package contains a null byte in an entry path.' );
			}

			$offset += $entry_size;
		}

		if ( $offset !== $central_end ) {
			return self::error( 'mumega_motion_package_invalid_archive', 'The downloaded theme package has unexpected central-directory data.' );
		}

		return true;
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
