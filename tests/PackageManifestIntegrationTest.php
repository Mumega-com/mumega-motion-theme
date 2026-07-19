<?php
/**
 * Integration coverage for the packaged edge manifest contract.
 *
 * @package Mumega_Motion
 */

use PHPUnit\Framework\TestCase;

/**
 * Exercises the real packager output through the installed release consumer.
 */
final class PackageManifestIntegrationTest extends TestCase {
	private const VERSION      = '0.1.986';
	private const PUBLISHED_AT = '2026-07-19T01:02:03Z';

	/**
	 * Resets network doubles before each integration run.
	 */
	protected function setUp(): void {
		$GLOBALS['mumega_motion_test_site_transients']  = array();
		$GLOBALS['mumega_motion_test_remote_requests']  = array();
		$GLOBALS['mumega_motion_test_remote_responses'] = array();
	}

	/**
	 * Removes package output created by the integration run.
	 */
	protected function tearDown(): void {
		$dist = dirname( __DIR__ ) . '/dist';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Integration test cleans its local package output.
		exec( 'rm -rf ' . escapeshellarg( $dist ) );
	}

	/**
	 * The generated manifest is accepted by the already-installed consumer.
	 */
	public function test_generated_manifest_is_accepted_by_release_client(): void {
		$root   = dirname( __DIR__ );
		$result = $this->run_packager( self::PUBLISHED_AT );

		$this->assertSame( 0, $result['status'], implode( "\n", $result['output'] ) );

		$manifest_path = $root . '/dist/manifest.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local generated JSON.
		$manifest     = json_decode( (string) file_get_contents( $manifest_path ), true );
		$tag          = 'edge-v' . self::VERSION;
		$manifest_url = 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/' . $tag . '/manifest.json';

		$GLOBALS['mumega_motion_test_remote_responses'][] = $this->json_response(
			array(
				array(
					'tag_name'   => $tag,
					'draft'      => false,
					'prerelease' => true,
					'assets'     => array(
						array(
							'name'                 => 'manifest.json',
							'browser_download_url' => $manifest_url,
						),
					),
				),
			)
		);
		$GLOBALS['mumega_motion_test_remote_responses'][] = $this->json_response( $manifest );

		$result = ( new Mumega_Motion_Release_Client() )->latest();

		$this->assertSame(
			array(
				'slug'         => 'mumega-motion-theme',
				'version'      => self::VERSION,
				'package_url'  => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/' . $tag . '/mumega-motion-theme-' . self::VERSION . '.zip',
				'sha256'       => $manifest['sha256'],
				'requires_wp'  => '6.5',
				'requires_php' => '7.4',
				'release_tag'  => $tag,
				'published_at' => self::PUBLISHED_AT,
				'manifest_url' => $manifest_url,
			),
			$result
		);
	}

	/**
	 * An invalid timestamp override is rejected instead of being published.
	 */
	public function test_packager_rejects_invalid_manifest_timestamp_override(): void {
		$result = $this->run_packager( 'July 19, 2026' );

		$this->assertSame( 64, $result['status'] );
		$this->assertStringContainsString( 'ISO-8601', implode( "\n", $result['output'] ) );
	}

	/**
	 * Without an override the manifest uses the source commit timestamp.
	 */
	public function test_manifest_defaults_to_source_commit_timestamp(): void {
		$root   = dirname( __DIR__ );
		$result = $this->run_packager( null );

		$this->assertSame( 0, $result['status'], implode( "\n", $result['output'] ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local generated JSON.
		$manifest = json_decode( (string) file_get_contents( $root . '/dist/manifest.json' ), true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Integration test reads the local source commit.
		$expected = trim( (string) shell_exec( 'cd ' . escapeshellarg( $root ) . ' && git show -s --format=%cI HEAD' ) );

		$this->assertSame( $expected, $manifest['published_at'] );
	}

	/**
	 * Runs the real package producer.
	 *
	 * @param string|null $published_at Optional manifest timestamp override.
	 * @return array{status:int,output:array}
	 */
	private function run_packager( $published_at ): array {
		$root    = dirname( __DIR__ );
		$script  = $root . '/scripts/package-theme.sh';
		$prefix  = null === $published_at
			? 'env -u MUMEGA_MOTION_MANIFEST_PUBLISHED_AT'
			: 'MUMEGA_MOTION_MANIFEST_PUBLISHED_AT=' . escapeshellarg( $published_at );
		$command = $prefix . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( self::VERSION ) . ' 2>&1';
		$output  = array();
		$status  = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Integration test must exercise the real package script.
		exec( $command, $output, $status );

		return array(
			'status' => $status,
			'output' => $output,
		);
	}

	/**
	 * Builds a successful HTTP JSON response for the WordPress test double.
	 *
	 * @param mixed $body Response body.
	 * @return array
	 */
	private function json_response( $body ): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}
}
