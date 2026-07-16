<?php
/**
 * Immutable Mumega Motion GitHub release discovery.
 *
 * @package Mumega_Motion
 */

/**
 * Discovers and validates the latest fixed-repository edge release.
 */
final class Mumega_Motion_Release_Client {
	private const REPOSITORY    = 'Mumega-com/mumega-motion-theme';
	private const THEME_SLUG    = 'mumega-motion-theme';
	private const API_URL       = 'https://api.github.com/repos/Mumega-com/mumega-motion-theme/releases?per_page=10';
	private const TRANSIENT_KEY = 'mumega_motion_latest_edge_release';
	private const CACHE_TTL     = 900;
	private const TIMEOUT       = 10;
	private const USER_AGENT    = 'Mumega-Motion-Theme-Updater/1.0 (+https://github.com/Mumega-com/mumega-motion-theme)';
	private const TAG_PREFIX    = 'edge-v';
	private const SEMVER_REGEX  = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-((?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

	/**
	 * Returns the latest normalized release manifest.
	 *
	 * @param bool $force Whether to discard cached discovery first.
	 * @return array|WP_Error
	 */
	public function latest( $force = false ) {
		if ( $force ) {
			delete_site_transient( self::TRANSIENT_KEY );
		} else {
			$cached = get_site_transient( self::TRANSIENT_KEY );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$releases = $this->fetch_json( self::API_URL );

		if ( is_wp_error( $releases ) ) {
			return $releases;
		}

		$release = $this->select_latest_release( $releases );

		if ( is_wp_error( $release ) ) {
			return $release;
		}

		$manifest_url = $this->find_manifest_url( $release );

		if ( is_wp_error( $manifest_url ) ) {
			return $manifest_url;
		}

		$manifest = $this->fetch_json( $manifest_url );

		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$normalized = $this->normalize_manifest( $manifest, $release, $manifest_url );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		set_site_transient( self::TRANSIENT_KEY, $normalized, self::CACHE_TTL );

		return $normalized;
	}

	/**
	 * Fetches an array-shaped JSON document using the safe WordPress client.
	 *
	 * @param string $url Fixed URL to request.
	 * @return array|WP_Error
	 */
	private function fetch_json( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => self::USER_AGENT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mumega_motion_release_transport_error',
				__( 'Mumega Motion release discovery could not reach GitHub.', 'mumega-motion' )
			);
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $status ) {
			return new WP_Error(
				'mumega_motion_release_http_error',
				__( 'GitHub returned an unsuccessful release response.', 'mumega-motion' ),
				array( 'status' => (int) $status )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error(
				'mumega_motion_release_invalid_json',
				__( 'GitHub returned invalid release JSON.', 'mumega-motion' )
			);
		}

		return $data;
	}

	/**
	 * Selects the highest SemVer eligible edge prerelease.
	 *
	 * @param array $releases GitHub releases.
	 * @return array|WP_Error
	 */
	private function select_latest_release( array $releases ) {
		$selected         = null;
		$selected_version = null;

		foreach ( $releases as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || empty( $release['prerelease'] ) ) {
				continue;
			}

			$tag = isset( $release['tag_name'] ) && is_string( $release['tag_name'] ) ? $release['tag_name'] : '';

			if ( 0 !== strpos( $tag, self::TAG_PREFIX ) ) {
				continue;
			}

			$version = substr( $tag, strlen( self::TAG_PREFIX ) );

			if ( ! $this->is_semver( $version ) ) {
				continue;
			}

			if ( null === $selected_version || version_compare( $version, $selected_version, '>' ) ) {
				$selected         = $release;
				$selected_version = $version;
			}
		}

		if ( null === $selected ) {
			return new WP_Error(
				'mumega_motion_release_not_found',
				__( 'No eligible Mumega Motion edge release was found.', 'mumega-motion' )
			);
		}

		return $selected;
	}

	/**
	 * Finds and validates the manifest release asset.
	 *
	 * @param array $release Selected GitHub release.
	 * @return string|WP_Error
	 */
	private function find_manifest_url( array $release ) {
		$assets = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || ! isset( $asset['name'] ) || 'manifest.json' !== $asset['name'] ) {
				continue;
			}

			$url = isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] )
				? $asset['browser_download_url']
				: '';
			$tag = isset( $release['tag_name'] ) && is_string( $release['tag_name'] ) ? $release['tag_name'] : '';

			if ( $this->release_download_url( $url, $tag . '/manifest.json' ) ) {
				return $url;
			}

			return $this->invalid_asset_url_error();
		}

		return new WP_Error(
			'mumega_motion_release_manifest_missing',
			__( 'The selected Mumega Motion release has no manifest.', 'mumega-motion' )
		);
	}

	/**
	 * Validates and normalizes a release manifest.
	 *
	 * @param array  $manifest     Decoded manifest.
	 * @param array  $release      Selected GitHub release.
	 * @param string $manifest_url Validated manifest URL.
	 * @return array|WP_Error
	 */
	private function normalize_manifest( array $manifest, array $release, $manifest_url ) {
		$required_string_fields = array(
			'slug',
			'version',
			'package_url',
			'sha256',
			'requires_wordpress',
			'requires_php',
			'published_at',
		);

		foreach ( $required_string_fields as $field ) {
			if ( ! isset( $manifest[ $field ] ) || ! is_string( $manifest[ $field ] ) || '' === $manifest[ $field ] ) {
				return $this->invalid_manifest_error();
			}
		}

		$tag     = isset( $release['tag_name'] ) && is_string( $release['tag_name'] ) ? $release['tag_name'] : '';
		$version = substr( $tag, strlen( self::TAG_PREFIX ) );

		if (
			self::THEME_SLUG !== $manifest['slug'] ||
			! $this->is_semver( $manifest['version'] ) ||
			$version !== $manifest['version'] ||
			1 !== preg_match( '/^[a-f0-9]{64}$/', $manifest['sha256'] )
		) {
			return $this->invalid_manifest_error();
		}

		$package_file = self::THEME_SLUG . '-' . $manifest['version'] . '.zip';

		if ( ! $this->release_download_url( $manifest['package_url'], $tag . '/' . $package_file ) ) {
			return $this->invalid_asset_url_error();
		}

		if ( ! $this->is_version_requirement( $manifest['requires_php'] ) ) {
			return $this->invalid_manifest_error();
		}

		if ( version_compare( PHP_VERSION, $manifest['requires_php'], '<' ) ) {
			return new WP_Error(
				'mumega_motion_release_incompatible_php',
				__( 'The latest Mumega Motion release requires a newer PHP version.', 'mumega-motion' )
			);
		}

		if ( ! $this->is_version_requirement( $manifest['requires_wordpress'] ) ) {
			return $this->invalid_manifest_error();
		}

		if ( version_compare( get_bloginfo( 'version' ), $manifest['requires_wordpress'], '<' ) ) {
			return new WP_Error(
				'mumega_motion_release_incompatible_wp',
				__( 'The latest Mumega Motion release requires a newer WordPress version.', 'mumega-motion' )
			);
		}

		return array(
			'slug'         => self::THEME_SLUG,
			'version'      => $manifest['version'],
			'package_url'  => $manifest['package_url'],
			'sha256'       => $manifest['sha256'],
			'requires_wp'  => $manifest['requires_wordpress'],
			'requires_php' => $manifest['requires_php'],
			'release_tag'  => $tag,
			'published_at' => $manifest['published_at'],
			'manifest_url' => $manifest_url,
		);
	}

	/**
	 * Checks an exact immutable path on the fixed GitHub repository.
	 *
	 * @param string $url          Candidate URL.
	 * @param string $release_path Expected path beneath releases/download.
	 * @return bool
	 */
	private function release_download_url( $url, $release_path ) {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return false;
		}

		$expected_path = '/' . self::REPOSITORY . '/releases/download/' . $release_path;

		return isset( $parts['scheme'], $parts['host'], $parts['path'] ) &&
			'https' === strtolower( $parts['scheme'] ) &&
			'github.com' === strtolower( $parts['host'] ) &&
			$expected_path === $parts['path'] &&
			empty( $parts['user'] ) &&
			empty( $parts['pass'] ) &&
			empty( $parts['port'] ) &&
			empty( $parts['query'] ) &&
			empty( $parts['fragment'] );
	}

	/**
	 * Checks a semantic version.
	 *
	 * @param string $version Version string.
	 * @return bool
	 */
	private function is_semver( $version ) {
		return is_string( $version ) && 1 === preg_match( self::SEMVER_REGEX, $version );
	}

	/**
	 * Checks a simple minimum runtime version value.
	 *
	 * @param string $version Minimum runtime version.
	 * @return bool
	 */
	private function is_version_requirement( $version ) {
		return is_string( $version ) && 1 === preg_match( '/^(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*)){1,2}$/', $version );
	}

	/**
	 * Returns the shared invalid-manifest error.
	 *
	 * @return WP_Error
	 */
	private function invalid_manifest_error() {
		return new WP_Error(
			'mumega_motion_release_manifest_invalid',
			__( 'The selected Mumega Motion release manifest is invalid.', 'mumega-motion' )
		);
	}

	/**
	 * Returns the shared invalid-release-URL error.
	 *
	 * @return WP_Error
	 */
	private function invalid_asset_url_error() {
		return new WP_Error(
			'mumega_motion_release_asset_url_invalid',
			__( 'The selected Mumega Motion release contains an invalid asset URL.', 'mumega-motion' )
		);
	}
}
