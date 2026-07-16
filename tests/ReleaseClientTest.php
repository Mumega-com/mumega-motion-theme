<?php
/**
 * Tests for immutable GitHub edge-release discovery.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the fixed-repository release client.
 */
final class ReleaseClientTest extends TestCase {
	private const REPOSITORY = 'Mumega-com/mumega-motion-theme';
	private const SLUG       = 'mumega-motion-theme';
	private const API_URL    = 'https://api.github.com/repos/Mumega-com/mumega-motion-theme/releases?per_page=10';

	/**
	 * Resets observable WordPress test doubles.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_site_transients']  = array();
		$GLOBALS['mumega_motion_test_remote_requests']  = array();
		$GLOBALS['mumega_motion_test_remote_responses'] = array();
	}

	/**
	 * Selects the highest valid semantic version from eligible edge prereleases.
	 */
	public function test_selects_highest_semantic_edge_prerelease(): void {
		$releases = array(
			$this->release( 'edge-v0.1.9' ),
			$this->release( 'edge-v9.0.0', array( 'draft' => true ) ),
			$this->release( 'edge-v8.0.0', array( 'prerelease' => false ) ),
			$this->release( 'v7.0.0' ),
			$this->release( 'edge-vnot-semver' ),
			$this->release( 'edge-v0.1.123' ),
		);

		$this->queue_json_response( $releases );
		$this->queue_json_response( $this->manifest( '0.1.123' ) );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assertSame( $this->normalized_manifest( '0.1.123' ), $result );
		$this->assertSame( self::API_URL, $GLOBALS['mumega_motion_test_remote_requests'][0]['url'] );
		$this->assertSame( $this->manifest_url( '0.1.123' ), $GLOBALS['mumega_motion_test_remote_requests'][1]['url'] );
		$this->assertGreaterThan( 0, $GLOBALS['mumega_motion_test_remote_requests'][0]['args']['timeout'] );
		$this->assertLessThanOrEqual( 15, $GLOBALS['mumega_motion_test_remote_requests'][0]['args']['timeout'] );
		$this->assertStringContainsString( 'Mumega', $GLOBALS['mumega_motion_test_remote_requests'][0]['args']['user-agent'] );
	}

	/**
	 * Rejects a manifest asset outside the fixed public release path.
	 */
	public function test_rejects_manifest_asset_url_outside_fixed_repository(): void {
		$release                                      = $this->release( 'edge-v0.1.123' );
		$release['assets'][0]['browser_download_url'] = 'https://evil.example/manifest.json';
		$this->queue_json_response( array( $release ) );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( 'mumega_motion_release_asset_url_invalid', $result );
		$this->assertCount( 1, $GLOBALS['mumega_motion_test_remote_requests'] );
	}

	/**
	 * Rejects an eligible release that has no manifest asset.
	 */
	public function test_rejects_release_without_manifest_asset(): void {
		$release           = $this->release( 'edge-v0.1.123' );
		$release['assets'] = array();
		$this->queue_json_response( array( $release ) );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( 'mumega_motion_release_manifest_missing', $result );
	}

	/**
	 * Rejects malformed or incompatible manifests with stable error codes.
	 *
	 * @dataProvider invalid_manifest_provider
	 *
	 * @param array  $changes       Manifest fields to replace.
	 * @param string $expected_code Expected WordPress error code.
	 */
	public function test_rejects_invalid_or_incompatible_manifest( array $changes, string $expected_code ): void {
		$this->queue_json_response( array( $this->release( 'edge-v0.1.123' ) ) );
		$this->queue_json_response( array_merge( $this->manifest( '0.1.123' ), $changes ) );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( $expected_code, $result );
	}

	/**
	 * Invalid manifest cases.
	 *
	 * @return array<string,array{0:array,1:string}>
	 */
	public function invalid_manifest_provider(): array {
		return array(
			'different slug'     => array( array( 'slug' => 'another-theme' ), 'mumega_motion_release_manifest_invalid' ),
			'invalid version'    => array( array( 'version' => '0.1' ), 'mumega_motion_release_manifest_invalid' ),
			'mismatched version' => array( array( 'version' => '0.1.124' ), 'mumega_motion_release_manifest_invalid' ),
			'uppercase checksum' => array( array( 'sha256' => str_repeat( 'A', 64 ) ), 'mumega_motion_release_manifest_invalid' ),
			'short checksum'     => array( array( 'sha256' => str_repeat( 'a', 63 ) ), 'mumega_motion_release_manifest_invalid' ),
			'off-repository ZIP' => array( array( 'package_url' => 'https://evil.example/theme.zip' ), 'mumega_motion_release_asset_url_invalid' ),
			'incompatible PHP'   => array( array( 'requires_php' => '99.0' ), 'mumega_motion_release_incompatible_php' ),
			'incompatible WP'    => array( array( 'requires_wordpress' => '99.0' ), 'mumega_motion_release_incompatible_wp' ),
		);
	}

	/**
	 * Caches only the normalized result for exactly fifteen minutes.
	 */
	public function test_uses_normalized_manifest_cache_for_fifteen_minutes(): void {
		$this->queue_json_response( array( $this->release( 'edge-v0.1.123' ) ) );
		$this->queue_json_response( $this->manifest( '0.1.123', array( 'commit' => 'not-normalized' ) ) );
		$client = new Mumega_Motion_Release_Client();

		$first  = $client->latest();
		$second = $client->latest();

		$this->assertSame( $first, $second );
		$this->assertCount( 2, $GLOBALS['mumega_motion_test_remote_requests'] );
		$this->assertCount( 1, $GLOBALS['mumega_motion_test_site_transients'] );
		$cached = reset( $GLOBALS['mumega_motion_test_site_transients'] );
		$this->assertSame( 900, $cached['expiration'] );
		$this->assertSame( $this->normalized_manifest( '0.1.123' ), $cached['value'] );
		$this->assertArrayNotHasKey( 'commit', $cached['value'] );
	}

	/**
	 * A forced check discards cached discovery and performs fresh requests.
	 */
	public function test_force_deletes_cache_and_makes_fresh_requests(): void {
		$this->queue_json_response( array( $this->release( 'edge-v0.1.123' ) ) );
		$this->queue_json_response( $this->manifest( '0.1.123' ) );
		$client = new Mumega_Motion_Release_Client();
		$this->assertSame( '0.1.123', $client->latest()['version'] );

		$this->queue_json_response( array( $this->release( 'edge-v0.1.124' ) ) );
		$this->queue_json_response( $this->manifest( '0.1.124' ) );

		$result = $client->latest( true );

		$this->assertSame( '0.1.124', $result['version'] );
		$this->assertCount( 4, $GLOBALS['mumega_motion_test_remote_requests'] );
		$cached = reset( $GLOBALS['mumega_motion_test_site_transients'] );
		$this->assertSame( '0.1.124', $cached['value']['version'] );
	}

	/**
	 * Converts HTTP transport failures to a stable client error.
	 */
	public function test_converts_transport_errors_to_stable_error_code(): void {
		$GLOBALS['mumega_motion_test_remote_responses'][] = new WP_Error( 'http_request_failed', 'Network down.' );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( 'mumega_motion_release_transport_error', $result );
	}

	/**
	 * Converts non-successful HTTP responses to a stable client error.
	 */
	public function test_converts_non_200_responses_to_stable_error_code(): void {
		$GLOBALS['mumega_motion_test_remote_responses'][] = array(
			'response' => array( 'code' => 503 ),
			'body'     => 'Unavailable',
		);

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( 'mumega_motion_release_http_error', $result );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	/**
	 * Converts invalid JSON from either endpoint to a stable client error.
	 *
	 * @dataProvider invalid_json_endpoint_provider
	 *
	 * @param bool $manifest_response Whether discovery succeeds before invalid JSON.
	 */
	public function test_converts_invalid_json_to_stable_error_code( bool $manifest_response ): void {
		if ( $manifest_response ) {
			$this->queue_json_response( array( $this->release( 'edge-v0.1.123' ) ) );
		}

		$GLOBALS['mumega_motion_test_remote_responses'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{invalid-json',
		);

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assert_error_code( 'mumega_motion_release_invalid_json', $result );
	}

	/**
	 * JSON endpoint cases.
	 *
	 * @return array<string,array{0:bool}>
	 */
	public function invalid_json_endpoint_provider(): array {
		return array(
			'releases API' => array( false ),
			'manifest'     => array( true ),
		);
	}

	/**
	 * Builds a GitHub release fixture.
	 *
	 * @param string $tag     Release tag.
	 * @param array  $changes Release fields to replace.
	 * @return array
	 */
	private function release( string $tag, array $changes = array() ): array {
		$version = 0 === strpos( $tag, 'edge-v' ) ? substr( $tag, 6 ) : '0.0.0';

		return array_merge(
			array(
				'tag_name'     => $tag,
				'draft'        => false,
				'prerelease'   => true,
				'published_at' => '2026-07-16T12:00:00Z',
				'assets'       => array(
					array(
						'name'                 => 'manifest.json',
						'browser_download_url' => $this->manifest_url( $version ),
					),
				),
			),
			$changes
		);
	}

	/**
	 * Builds a manifest fixture.
	 *
	 * @param string $version Version.
	 * @param array  $changes Manifest fields to replace.
	 * @return array
	 */
	private function manifest( string $version, array $changes = array() ): array {
		return array_merge(
			array(
				'slug'               => self::SLUG,
				'version'            => $version,
				'commit'             => str_repeat( '1', 40 ),
				'package_url'        => $this->package_url( $version ),
				'sha256'             => str_repeat( 'a', 64 ),
				'requires_wordpress' => '6.5',
				'requires_php'       => '7.4',
				'published_at'       => '2026-07-16T12:00:00Z',
			),
			$changes
		);
	}

	/**
	 * Builds the expected public return contract.
	 *
	 * @param string $version Version.
	 * @return array
	 */
	private function normalized_manifest( string $version ): array {
		return array(
			'slug'         => self::SLUG,
			'version'      => $version,
			'package_url'  => $this->package_url( $version ),
			'sha256'       => str_repeat( 'a', 64 ),
			'requires_wp'  => '6.5',
			'requires_php' => '7.4',
			'release_tag'  => 'edge-v' . $version,
			'published_at' => '2026-07-16T12:00:00Z',
			'manifest_url' => $this->manifest_url( $version ),
		);
	}

	/**
	 * Returns the immutable manifest URL.
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private function manifest_url( string $version ): string {
		return 'https://github.com/' . self::REPOSITORY . '/releases/download/edge-v' . $version . '/manifest.json';
	}

	/**
	 * Returns the immutable package URL.
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private function package_url( string $version ): string {
		return 'https://github.com/' . self::REPOSITORY . '/releases/download/edge-v' . $version . '/' . self::SLUG . '-' . $version . '.zip';
	}

	/**
	 * Queues an encoded successful response.
	 *
	 * @param mixed $body Response body.
	 */
	private function queue_json_response( $body ): void {
		$GLOBALS['mumega_motion_test_remote_responses'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}

	/**
	 * Asserts a result is a WordPress error with a stable code.
	 *
	 * @param string $expected_code Expected error code.
	 * @param mixed  $result        Client result.
	 */
	private function assert_error_code( string $expected_code, $result ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_code, $result->get_error_code() );
	}
}
