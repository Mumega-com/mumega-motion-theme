<?php
// phpcs:ignoreFile -- Test doubles deliberately use WordPress's global names and signatures.
/**
 * PHPUnit bootstrap with lightweight WordPress test doubles.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['mumega_motion_test_filters']          = array();
$GLOBALS['mumega_motion_test_actions']          = array();
$GLOBALS['mumega_motion_test_routes']           = array();
$GLOBALS['mumega_motion_test_tools']            = array();
$GLOBALS['mumega_motion_test_site_transients']  = array();
$GLOBALS['mumega_motion_test_remote_requests']  = array();
$GLOBALS['mumega_motion_test_remote_responses'] = array();
$GLOBALS['mumega_motion_test_download_requests'] = array();
$GLOBALS['mumega_motion_test_download_results']  = array();
$GLOBALS['mumega_motion_test_capabilities']     = array();
$GLOBALS['mumega_motion_test_upload_basedir']   = sys_get_temp_dir();
$GLOBALS['mumega_motion_test_copy_fail_after']  = null;
$GLOBALS['mumega_motion_test_copy_count']       = 0;
$GLOBALS['mumega_motion_test_copy_after_file']  = null;
$GLOBALS['mumega_motion_test_salt']             = 'mumega-motion-test-secret';
$GLOBALS['mumega_motion_test_posts']            = array();
$GLOBALS['mumega_motion_test_post_terms']       = array();
$GLOBALS['mumega_motion_test_post_tags']        = array();
$GLOBALS['mumega_motion_test_options']          = array();
$GLOBALS['mumega_motion_test_post_queries']     = array();
$GLOBALS['mumega_motion_test_get_posts_requests'] = array();

/**
 * Minimal post value used by editorial helper tests.
 */
class WP_Post {
	/** @var int */
	public $ID = 0;

	/** @var string */
	public $post_content = '';

	/** @var string */
	public $post_excerpt = '';

	/** @var string */
	public $post_date_gmt = '';

	/** @var string */
	public $post_modified_gmt = '';

	/** @var string */
	public $post_status = 'publish';

	/** @var string */
	public $post_password = '';

	/**
	 * Creates a test post from the supplied property values.
	 *
	 * @param array $values Post property values.
	 */
	public function __construct( $values = array() ) {
		foreach ( $values as $property => $value ) {
			$this->$property = $value;
		}
	}
}

/**
 * Minimal term value used by editorial helper tests.
 */
class WP_Term {
	/** @var int */
	public $term_id = 0;

	/** @var string */
	public $name = '';

	/** @var string */
	public $slug = '';

	/**
	 * Creates a test term from the supplied property values.
	 *
	 * @param array $values Term property values.
	 */
	public function __construct( $values = array() ) {
		foreach ( $values as $property => $value ) {
			$this->$property = $value;
		}
	}
}

/**
 * Minimal WP_Error implementation for unit tests.
 */
class WP_Error {
	/**
	 * Error messages keyed by code.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Error data keyed by code.
	 *
	 * @var array
	 */
	private $error_data = array();

	/**
	 * Creates an error value.
	 *
	 * @param string|int $code    Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data    Optional error data.
	 */
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( empty( $code ) ) {
			return;
		}

		$this->add( $code, $message, $data );
	}

	/**
	 * Adds an error.
	 *
	 * @param string|int $code    Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data    Optional error data.
	 * @return void
	 */
	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;

		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}

	/**
	 * Returns the first error code.
	 *
	 * @return string|int Empty string when no error is stored.
	 */
	public function get_error_code() {
		$codes = array_keys( $this->errors );

		return empty( $codes ) ? '' : $codes[0];
	}

	/**
	 * Returns all error codes.
	 *
	 * @return array
	 */
	public function get_error_codes() {
		return array_keys( $this->errors );
	}

	/**
	 * Returns the first message for a code.
	 *
	 * @param string|int $code Optional error code.
	 * @return string
	 */
	public function get_error_message( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		$messages = $this->get_error_messages( $code );

		return empty( $messages ) ? '' : $messages[0];
	}

	/**
	 * Returns all messages for a code, or every message.
	 *
	 * @param string|int $code Optional error code.
	 * @return array
	 */
	public function get_error_messages( $code = '' ) {
		if ( ! empty( $code ) ) {
			return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
		}

		$messages = array();

		foreach ( $this->errors as $code_messages ) {
			$messages = array_merge( $messages, $code_messages );
		}

		return $messages;
	}

	/**
	 * Returns data for a code.
	 *
	 * @param string|int $code Optional error code.
	 * @return mixed|null
	 */
	public function get_error_data( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}

	/**
	 * Reports whether the object contains an error.
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}
}

/**
 * Minimal REST response used by controller tests.
 */
class WP_REST_Response {
	/**
	 * Response data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Creates a REST response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status.
	 */
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = (int) $status;
	}

	/**
	 * Returns the response data.
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Returns the HTTP status.
	 *
	 * @return int
	 */
	public function get_status() {
		return $this->status;
	}
}

/**
 * Minimal REST request used by controller tests.
 */
class WP_REST_Request {
	/**
	 * Request parameters.
	 *
	 * @var array
	 */
	private $params = array();

	/**
	 * Returns a request parameter.
	 *
	 * @param string $key Parameter name.
	 * @return mixed|null
	 */
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}

	/**
	 * Sets a request parameter.
	 *
	 * @param string $key   Parameter name.
	 * @param mixed  $value Parameter value.
	 * @return void
	 */
	public function set_param( $key, $value ) {
		$this->params[ $key ] = $value;
	}
}

/**
 * REST method constants used by registrations.
 */
class WP_REST_Server {
	const CREATABLE = 'POST';
}

/**
 * Checks whether a value is a WordPress error.
 *
 * @param mixed $thing Candidate value.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Returns a translated string unchanged in tests.
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $text;
}

/**
 * Registers a filter callback and preserves WordPress's return contract.
 *
 * @param string   $hook_name     Filter name.
 * @param callable $callback      Filter callback.
 * @param int      $priority      Callback priority.
 * @param int      $accepted_args Accepted argument count.
 * @return true
 */
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mumega_motion_test_filters'][ $hook_name ][ (int) $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => (int) $accepted_args,
	);

	return true;
}

/**
 * Applies callbacks registered for a filter.
 *
 * @param string $hook_name Filter name.
 * @param mixed  $value     Filtered value.
 * @param mixed  ...$args   Additional arguments.
 * @return mixed
 */
function apply_filters( $hook_name, $value, ...$args ) {
	if ( empty( $GLOBALS['mumega_motion_test_filters'][ $hook_name ] ) ) {
		return $value;
	}

	$callbacks = $GLOBALS['mumega_motion_test_filters'][ $hook_name ];
	ksort( $callbacks );

	foreach ( $callbacks as $priority_callbacks ) {
		foreach ( $priority_callbacks as $registration ) {
			$callback_args = array_merge( array( $value ), $args );
			$callback_args = array_slice( $callback_args, 0, $registration['accepted_args'] );
			$value         = call_user_func_array( $registration['callback'], $callback_args );
		}
	}

	if ( 'mcpwp_register_tools' === $hook_name && is_array( $value ) ) {
		$GLOBALS['mumega_motion_test_tools'] = $value;
	}

	return $value;
}

/**
 * Removes shortcodes from a test content string.
 *
 * @param string $content Content to strip.
 * @return string
 */
function strip_shortcodes( $content ) {
	return preg_replace( '/\[[^\]]*\]/', '', (string) $content );
}

/**
 * Removes HTML tags from a test content string.
 *
 * @param string $content       Content to strip.
 * @param bool   $remove_breaks Whether to remove line breaks.
 * @return string
 */
function wp_strip_all_tags( $content, $remove_breaks = false ) {
	$text = strip_tags( (string) $content );

	return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $text ) : $text;
}

/**
 * Retrieves a post excerpt from a test post value.
 *
 * @param WP_Post|int|null $post Post value or ID.
 * @return string
 */
function get_the_excerpt( $post = null ) {
	if ( is_numeric( $post ) ) {
		$post = isset( $GLOBALS['mumega_motion_test_posts'][ (int) $post ] )
			? $GLOBALS['mumega_motion_test_posts'][ (int) $post ]
			: null;
	}

	return $post instanceof WP_Post ? $post->post_excerpt : '';
}

/**
 * Retrieves a post field from the configured test post values.
 *
 * @param string      $field   Field name.
 * @param WP_Post|int $post_id Post value or ID.
 * @return mixed
 */
function get_post_field( $field, $post_id ) {
	$post = $post_id instanceof WP_Post
		? $post_id
		: ( isset( $GLOBALS['mumega_motion_test_posts'][ (int) $post_id ] ) ? $GLOBALS['mumega_motion_test_posts'][ (int) $post_id ] : null );

	return $post instanceof WP_Post && isset( $post->$field ) ? $post->$field : '';
}

/**
 * Retrieves configured taxonomy terms for a post.
 *
 * @param int    $post_id Post ID.
 * @param string $taxonomy Taxonomy name.
 * @return array|false
 */
function get_the_terms( $post_id, $taxonomy ) {
	return isset( $GLOBALS['mumega_motion_test_post_terms'][ (int) $post_id ][ $taxonomy ] )
		? $GLOBALS['mumega_motion_test_post_terms'][ (int) $post_id ][ $taxonomy ]
		: false;
}

/**
 * Retrieves configured native tags for a post.
 *
 * @param int $post_id Post ID.
 * @return array|false
 */
function get_the_tags( $post_id ) {
	return isset( $GLOBALS['mumega_motion_test_post_tags'][ (int) $post_id ] )
		? $GLOBALS['mumega_motion_test_post_tags'][ (int) $post_id ]
		: false;
}

/**
 * Retrieves a configured option or its fallback.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $option, $default = false ) {
	return array_key_exists( $option, $GLOBALS['mumega_motion_test_options'] )
		? $GLOBALS['mumega_motion_test_options'][ $option ]
		: $default;
}

/**
 * Returns queued posts and records each editorial convention query.
 *
 * @param array $args Query arguments.
 * @return array
 */
function get_posts( $args = array() ) {
	$GLOBALS['mumega_motion_test_get_posts_requests'][] = $args;

	return empty( $GLOBALS['mumega_motion_test_post_queries'] )
		? array()
		: array_shift( $GLOBALS['mumega_motion_test_post_queries'] );
}

/**
 * Registers an action callback and preserves WordPress's return contract.
 *
 * @param string   $hook_name     Action name.
 * @param callable $callback      Action callback.
 * @param int      $priority      Callback priority.
 * @param int      $accepted_args Accepted argument count.
 * @return true
 */
function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mumega_motion_test_actions'][ $hook_name ][ (int) $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => (int) $accepted_args,
	);

	return true;
}

/**
 * Invokes callbacks registered for an action.
 *
 * @param string $hook_name Action name.
 * @param mixed  ...$args   Action arguments.
 * @return void
 */
function do_action( $hook_name, ...$args ) {
	if ( empty( $GLOBALS['mumega_motion_test_actions'][ $hook_name ] ) ) {
		return;
	}

	$callbacks = $GLOBALS['mumega_motion_test_actions'][ $hook_name ];
	ksort( $callbacks );

	foreach ( $callbacks as $priority_callbacks ) {
		foreach ( $priority_callbacks as $registration ) {
			call_user_func_array(
				$registration['callback'],
				array_slice( $args, 0, $registration['accepted_args'] )
			);
		}
	}
}

/**
 * Captures a REST route registration.
 *
 * @param string $route_namespace Route namespace.
 * @param string $route           Route path.
 * @param array  $args            Route arguments.
 * @param bool   $override        Whether to replace an existing route.
 * @return bool
 */
function register_rest_route( $route_namespace, $route, $args = array(), $override = false ) {
	$GLOBALS['mumega_motion_test_routes'][] = array(
		'namespace' => $route_namespace,
		'route'     => $route,
		'args'      => $args,
		'override'  => (bool) $override,
	);

	return true;
}

/**
 * Reads a capability result configured by the current test.
 *
 * @param string $capability Capability name.
 * @param mixed  ...$args    Optional object arguments.
 * @return bool
 */
function current_user_can( $capability, ...$args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return ! empty( $GLOBALS['mumega_motion_test_capabilities'][ $capability ] );
}

/**
 * Converts a value to a REST boolean.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function rest_sanitize_boolean( $value ) {
	if ( is_string( $value ) && in_array( strtolower( $value ), array( 'false', '0', '' ), true ) ) {
		return false;
	}

	return (bool) $value;
}

/**
 * Wraps response data in a REST response.
 *
 * @param mixed $response Response data.
 * @return WP_REST_Response|WP_Error
 */
function rest_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response instanceof WP_REST_Response ? $response : new WP_REST_Response( $response );
}

/**
 * Returns a stored site transient.
 *
 * @param string $transient Transient name.
 * @return mixed
 */
function get_site_transient( $transient ) {
	return array_key_exists( $transient, $GLOBALS['mumega_motion_test_site_transients'] )
		? $GLOBALS['mumega_motion_test_site_transients'][ $transient ]['value']
		: false;
}

/**
 * Stores a site transient.
 *
 * @param string $transient  Transient name.
 * @param mixed  $value      Transient value.
 * @param int    $expiration Expiration in seconds.
 * @return bool
 */
function set_site_transient( $transient, $value, $expiration = 0 ) {
	$GLOBALS['mumega_motion_test_site_transients'][ $transient ] = array(
		'value'      => $value,
		'expiration' => (int) $expiration,
	);

	return true;
}

/**
 * Deletes a site transient.
 *
 * @param string $transient Transient name.
 * @return bool
 */
function delete_site_transient( $transient ) {
	unset( $GLOBALS['mumega_motion_test_site_transients'][ $transient ] );

	return true;
}

/**
 * Returns a queued HTTP response and records the request.
 *
 * @param string $url  Request URL.
 * @param array  $args Request arguments.
 * @return array|WP_Error
 */
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['mumega_motion_test_remote_requests'][] = array(
		'url'  => $url,
		'args' => $args,
	);

	if ( empty( $GLOBALS['mumega_motion_test_remote_responses'] ) ) {
		return new WP_Error( 'http_request_failed', 'No test response was queued.' );
	}

	return array_shift( $GLOBALS['mumega_motion_test_remote_responses'] );
}

/**
 * Returns a queued temporary download and records the requested URL.
 *
 * @param string $url     Download URL.
 * @param int    $timeout Request timeout.
 * @return string|WP_Error
 */
function download_url( $url, $timeout = 300 ) {
	$GLOBALS['mumega_motion_test_download_requests'][] = array(
		'url'     => $url,
		'timeout' => (int) $timeout,
	);

	if ( empty( $GLOBALS['mumega_motion_test_download_results'] ) ) {
		return new WP_Error( 'download_failed', 'No test download was queued.' );
	}

	return array_shift( $GLOBALS['mumega_motion_test_download_results'] );
}

/**
 * Retrieves an HTTP response code.
 *
 * @param array|WP_Error $response HTTP response.
 * @return int|string Response code, or an empty string for an invalid response.
 */
function wp_remote_retrieve_response_code( $response ) {
	if ( is_wp_error( $response ) || ! isset( $response['response'] ) || ! is_array( $response['response'] ) ) {
		return '';
	}

	return $response['response']['code'];
}

/**
 * Retrieves an HTTP response body.
 *
 * @param array|WP_Error $response HTTP response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * Returns WordPress runtime information used by compatibility checks.
 *
 * @param string $show Information field.
 * @param string $filter Optional output filter.
 * @return string
 */
function get_bloginfo( $show = '', $filter = 'raw' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return 'version' === $show ? '6.5' : '';
}

/**
 * Parses a URL using WordPress's return contract.
 *
 * @param string $url       URL to parse.
 * @param int    $component Optional PHP_URL_* component.
 * @return array|string|int|null|false
 */
function wp_parse_url( $url, $component = -1 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	return parse_url( $url, $component );
}

/**
 * Encodes data as JSON.
 *
 * @param mixed $value   Value to encode.
 * @param int   $flags   Encoding flags.
 * @param int   $depth   Maximum depth.
 * @return string|false
 */
function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	return json_encode( $value, $flags, $depth );
}

/**
 * Returns the deterministic secret configured by the current test.
 *
 * @param string $scheme Salt scheme.
 * @return string
 */
function wp_salt( $scheme = 'auth' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return $GLOBALS['mumega_motion_test_salt'];
}

/**
 * Returns the uploads location configured by the current test.
 *
 * @return array
 */
function wp_upload_dir() {
	return array(
		'basedir' => $GLOBALS['mumega_motion_test_upload_basedir'],
		'error'   => false,
	);
}

/**
 * Creates a directory recursively.
 *
 * @param string $target Directory to create.
 * @return bool
 */
function wp_mkdir_p( $target ) {
	return is_dir( $target ) || mkdir( $target, 0755, true );
}

/**
 * Copies a directory recursively, with deterministic failure injection.
 *
 * @param string $from Source directory.
 * @param string $to   Destination directory.
 * @return true|WP_Error
 */
function copy_dir( $from, $to ) {
	if ( ! is_dir( $from ) || is_link( $from ) || ! wp_mkdir_p( $to ) ) {
		return new WP_Error( 'copy_failed', 'The directory could not be copied.' );
	}

	$entries = scandir( $from );

	if ( false === $entries ) {
		return new WP_Error( 'copy_failed', 'The source directory could not be read.' );
	}

	foreach ( $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}

		$source      = $from . '/' . $entry;
		$destination = $to . '/' . $entry;

		if ( is_link( $source ) ) {
			return new WP_Error( 'copy_failed', 'Symbolic links are not copied.' );
		}

		if ( is_dir( $source ) ) {
			$result = copy_dir( $source, $destination );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			continue;
		}

		$GLOBALS['mumega_motion_test_copy_count']++;

		if (
			null !== $GLOBALS['mumega_motion_test_copy_fail_after'] &&
			$GLOBALS['mumega_motion_test_copy_count'] > $GLOBALS['mumega_motion_test_copy_fail_after']
		) {
			return new WP_Error( 'copy_failed', 'The injected copy failure occurred.' );
		}

		if ( ! copy( $source, $destination ) ) {
			return new WP_Error( 'copy_failed', 'A file could not be copied.' );
		}

		if ( is_callable( $GLOBALS['mumega_motion_test_copy_after_file'] ) ) {
			call_user_func( $GLOBALS['mumega_motion_test_copy_after_file'], $source, $destination );
		}
	}

	return true;
}

/**
 * Lightweight direct filesystem implementation used for recursive cleanup.
 */
class Mumega_Motion_Test_Filesystem {
	/**
	 * Deletes a file or directory.
	 *
	 * @param string $path      Path to delete.
	 * @param bool   $recursive Whether to recurse.
	 * @return bool
	 */
	public function delete( $path, $recursive = false ) {
		if ( is_link( $path ) || is_file( $path ) ) {
			return unlink( $path );
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		if ( ! is_dir( $path ) ) {
			return false;
		}

		if ( $recursive ) {
			$entries = scandir( $path );

			if ( false === $entries ) {
				return false;
			}

			foreach ( $entries as $entry ) {
				if ( '.' !== $entry && '..' !== $entry && ! $this->delete( $path . '/' . $entry, true ) ) {
					return false;
				}
			}
		}

		return rmdir( $path );
	}
}

/**
 * Initializes the test filesystem global.
 *
 * @return bool
 */
function WP_Filesystem() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	global $wp_filesystem;

	if ( ! is_object( $wp_filesystem ) ) {
		$wp_filesystem = new Mumega_Motion_Test_Filesystem();
	}

	return true;
}

/**
 * Fixed-purpose updater double used by the update API tests.
 */
final class Mumega_Motion_Update_Api_Test_Updater {
	/** @var array */
	public $update_calls = array();

	/** @var int */
	public $rollback_calls = 0;

	/** @return array */
	public function update( $force_check = true ) {
		$this->update_calls[] = $force_check;

		return array( 'status' => 'updated' );
	}

	/** @return array */
	public function rollback() {
		++$this->rollback_calls;

		return array( 'status' => 'rolled_back' );
	}
}

/**
 * Fixed verified manifest double used by the update API tests.
 */
final class Mumega_Motion_Update_Api_Test_Release_Client {
	/** @var array */
	public $latest_calls = array();

	/** @var array */
	public $manifest = array(
		'slug'         => 'mumega-motion-theme',
		'version'      => '0.1.101',
		'package_url'  => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.101/mumega-motion-theme-0.1.101.zip',
		'sha256'       => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		'requires_wp'  => '6.5',
		'requires_php' => '7.4',
		'published_at' => '2026-01-01T00:00:00Z',
		'release_tag'  => 'edge-v0.1.101',
		'manifest_url' => 'https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v0.1.101/manifest.json',
	);

	/** @return array */
	public function latest( $force = false ) {
		$this->latest_calls[] = (bool) $force;

		return $this->manifest;
	}
}

// Load update classes in dependency order, after their WordPress dependencies.
$mumega_motion_update_classes = array(
	'inc/updates/class-mumega-motion-release-client.php',
	'inc/updates/class-mumega-motion-package-validator.php',
	'inc/updates/class-mumega-motion-backup-store.php',
	'inc/updates/class-mumega-motion-updater.php',
	'inc/updates/class-mumega-motion-dashboard-package-verifier.php',
	'inc/updates/class-mumega-motion-update-api.php',
);

foreach ( $mumega_motion_update_classes as $mumega_motion_update_class ) {
	$mumega_motion_update_class_path = dirname( __DIR__ ) . '/' . $mumega_motion_update_class;

	if ( file_exists( $mumega_motion_update_class_path ) ) {
		require_once $mumega_motion_update_class_path;
	}
}

unset( $mumega_motion_update_class, $mumega_motion_update_class_path, $mumega_motion_update_classes );
