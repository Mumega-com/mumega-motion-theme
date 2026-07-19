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
$GLOBALS['mumega_motion_test_translations']     = array();
$GLOBALS['mumega_motion_test_pattern_categories'] = array();
$GLOBALS['mumega_motion_test_patterns']         = array();
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
$GLOBALS['mumega_motion_test_generated_excerpts'] = array();
$GLOBALS['mumega_motion_test_post_terms']       = array();
$GLOBALS['mumega_motion_test_post_tags']        = array();
$GLOBALS['mumega_motion_test_options']          = array();
$GLOBALS['mumega_motion_test_post_queries']     = array();
$GLOBALS['mumega_motion_test_get_posts_requests'] = array();
$GLOBALS['mumega_motion_test_categories_by_slug'] = array();
$GLOBALS['mumega_motion_test_category_by_slug_requests'] = array();
$GLOBALS['mumega_motion_test_categories']       = array();
$GLOBALS['mumega_motion_test_get_categories_requests'] = array();
$GLOBALS['mumega_motion_test_nav_menu_items']   = array();
$GLOBALS['mumega_motion_test_nav_menu_item_requests'] = array();
$GLOBALS['mumega_motion_test_nav_menu_locations'] = array();
$GLOBALS['mumega_motion_test_nav_menu_location_requests'] = array();
$GLOBALS['mumega_motion_test_nav_menu_objects'] = array();
$GLOBALS['mumega_motion_test_nav_menu_object_requests'] = array();
$GLOBALS['mumega_motion_test_theme_supports']   = array();
$GLOBALS['mumega_motion_test_menu_locations']   = array();
$GLOBALS['mumega_motion_test_enqueued_styles']  = array();
$GLOBALS['mumega_motion_test_enqueued_scripts'] = array();
$GLOBALS['mumega_motion_test_dequeued_styles']  = array();
$GLOBALS['mumega_motion_test_dequeued_scripts'] = array();
$GLOBALS['mumega_motion_test_conditionals']     = array();
$GLOBALS['mumega_motion_test_page_template']    = '';
$GLOBALS['mumega_motion_test_queried_object_id'] = 0;
$GLOBALS['mumega_motion_test_queried_object']   = null;
$GLOBALS['mumega_motion_test_search_query']     = '';
$GLOBALS['mumega_motion_test_author_meta']      = array();
$GLOBALS['mumega_motion_test_adjacent_post_navigation'] = '';
$GLOBALS['mumega_motion_test_bloginfo']         = array(
	'name'        => 'Mumega',
	'description' => 'Independent technology reporting.',
	'charset'     => 'UTF-8',
);
$GLOBALS['mumega_motion_test_has_nav_menu']     = array();
$GLOBALS['mumega_motion_test_nav_menu_markup']  = '<ul class="menu"><li><a href="https://example.test/topic/">Topic</a></li></ul>';
$GLOBALS['mumega_motion_test_loop_posts']       = array();
$GLOBALS['mumega_motion_test_loop_index']       = 0;
$GLOBALS['mumega_motion_test_current_post']     = null;
$GLOBALS['mumega_motion_test_main_query_post']  = null;
$GLOBALS['mumega_motion_test_setup_postdata_exceptions'] = array();
$GLOBALS['mumega_motion_test_reset_postdata_exception']  = null;
$GLOBALS['mumega_motion_test_elementor_edit_mode'] = '';
$GLOBALS['mumega_motion_test_elementor_location_output'] = array();
$GLOBALS['mumega_motion_test_postdata_events']  = array();

/**
 * Minimal post value used by editorial helper tests.
 */
class WP_Post {
	/** @var int */
	public $ID = 0;

	/** @var string */
	public $post_title = '';

	/** @var int */
	public $post_author = 0;

	/** @var string */
	public $post_date = '';

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

	/** @var string */
	public $taxonomy = '';

	/** @var string */
	public $description = '';

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
function __( $text, $domain = 'default' ) {
	return isset( $GLOBALS['mumega_motion_test_translations'][ $domain ][ $text ] )
		? $GLOBALS['mumega_motion_test_translations'][ $domain ][ $text ]
		: $text;
}

/**
 * Returns a translated singular or plural string unchanged in tests.
 *
 * @param string $single Singular string.
 * @param string $plural Plural string.
 * @param int    $number Quantity.
 * @param string $domain Optional text domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return 1 === (int) $number ? $single : $plural;
}

/**
 * Formats a number for deterministic test output.
 *
 * @param int|float $number Number to format.
 * @return string
 */
function number_format_i18n( $number ) {
	return number_format( $number );
}

/**
 * Prints a translated string unchanged in tests.
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional text domain.
 * @return void
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}

/**
 * Escapes visible HTML text.
 *
 * @param string $text Text to escape.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Returns a translated and escaped string unchanged in tests.
 *
 * @param string $text   Text to translate and escape.
 * @param string $domain Optional text domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}

/**
 * Preserves safe post HTML in deterministic template fixtures.
 *
 * @param string $content Post HTML.
 * @return string
 */
function wp_kses_post( $content ) {
	return (string) $content;
}

/**
 * Minimal explicit-allowlist escaping double.
 *
 * @param string $content      HTML content.
 * @param array  $allowed_html Allowed elements and attributes.
 * @return string
 */
function wp_kses( $content, $allowed_html ) {
	$GLOBALS['mumega_motion_test_wp_kses_calls'][] = array( $content, $allowed_html );

	return (string) $content;
}

/**
 * Normalizes a key using the subset needed by token fixtures.
 *
 * @param string $key Raw key.
 * @return string
 */
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
}

/**
 * Normalizes a title using the subset needed by page-slug fixtures.
 *
 * @param string $title Raw title.
 * @return string
 */
function sanitize_title( $title ) {
	$title = strtolower( trim( (string) $title ) );
	$title = preg_replace( '/[^a-z0-9\s\-]/', '', $title );
	$title = preg_replace( '/[\s\-]+/', '-', $title );

	return trim( $title, '-' );
}

/**
 * Validates an absolute HTTP(S) URL for audience-menu fixtures.
 *
 * @param string $url Candidate URL.
 * @return string|false
 */
function wp_http_validate_url( $url ) {
	if ( ! is_string( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return false;
	}

	$scheme = parse_url( $url, PHP_URL_SCHEME );

	return in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ? $url : false;
}

/**
 * Prints a translated attribute string unchanged in tests.
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional text domain.
 * @return void
 */
function esc_attr_e( $text, $domain = 'default' ) {
	echo esc_attr( __( $text, $domain ) );
}

/**
 * Returns a translated and escaped attribute string in tests.
 *
 * @param string $text Text to translate and escape.
 * @param string $domain Optional text domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( __( $text, $domain ) );
}

/**
 * Escapes an HTML attribute.
 *
 * @param string $text Text to escape.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escapes a URL for deterministic template assertions.
 *
 * @param string $url URL to escape.
 * @return string
 */
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

/**
 * Returns a deterministic site URL.
 *
 * @param string $path Optional path.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.test' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) );
}

/**
 * Prints configured language attributes.
 *
 * @return void
 */
function language_attributes() {
	echo 'lang="en-CA"';
}

/**
 * Prints configured site identity values.
 *
 * @param string $show Identity field.
 * @return void
 */
function bloginfo( $show = '' ) {
	echo isset( $GLOBALS['mumega_motion_test_bloginfo'][ $show ] )
		? esc_html( $GLOBALS['mumega_motion_test_bloginfo'][ $show ] )
		: '';
}

/**
 * Prints deterministic body classes.
 *
 * @return void
 */
function body_class() {
	echo 'class="test-body"';
}

/**
 * Marks the WordPress head hook in rendered template output.
 *
 * @return void
 */
function wp_head() {
	echo '<!-- wp_head -->';
}

/**
 * Marks the body-open hook in rendered template output.
 *
 * @return void
 */
function wp_body_open() {
	echo '<!-- wp_body_open -->';
}

/**
 * Marks the WordPress footer hook in rendered template output.
 *
 * @return void
 */
function wp_footer() {
	echo '<!-- wp_footer -->';
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

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	return array_key_exists( $post->ID, $GLOBALS['mumega_motion_test_generated_excerpts'] )
		? $GLOBALS['mumega_motion_test_generated_excerpts'][ $post->ID ]
		: $post->post_excerpt;
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
 * Returns the configured Elementor edit mode for template compatibility tests.
 *
 * @param int    $post_id Post identifier.
 * @param string $key     Metadata key.
 * @param bool   $single  Whether a scalar is requested.
 * @return string|array
 */
function get_post_meta( $post_id, $key = '', $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	$value = '_elementor_edit_mode' === $key ? $GLOBALS['mumega_motion_test_elementor_edit_mode'] : '';

	return $single ? $value : array( $value );
}

/**
 * Reports whether a menu is assigned to a location.
 *
 * @param string $location Menu location.
 * @return bool
 */
function has_nav_menu( $location ) {
	return ! empty( $GLOBALS['mumega_motion_test_has_nav_menu'][ $location ] );
}

/**
 * Prints deterministic native menu markup.
 *
 * @param array $args Menu arguments.
 * @return void
 */
function wp_nav_menu( $args = array() ) {
	$markup = $GLOBALS['mumega_motion_test_nav_menu_markup'];

	if ( isset( $args['echo'] ) && false === $args['echo'] ) {
		return $markup;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native menu markup fixture.
}

/**
 * Prints a native search form marker.
 *
 * @return void
 */
function get_search_form() {
	echo '<form role="search" class="search-form"><label>Search<input type="search"></label></form>';
}

/**
 * Returns a deterministic category URL.
 *
 * @param int $category_id Category identifier.
 * @return string
 */
function get_category_link( $category_id ) {
	return 'https://example.test/category/' . (int) $category_id . '/';
}

/**
 * Returns a deterministic native tag archive URL.
 *
 * @param int $tag_id Tag identifier.
 * @return string
 */
function get_tag_link( $tag_id ) {
	return 'https://example.test/tag/' . (int) $tag_id . '/';
}

/**
 * Returns a deterministic post permalink.
 *
 * @param WP_Post|int $post Post value or identifier.
 * @return string
 */
function get_permalink( $post = 0 ) {
	if ( 0 === $post && $GLOBALS['mumega_motion_test_current_post'] instanceof WP_Post ) {
		$post = $GLOBALS['mumega_motion_test_current_post'];
	}

	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

	return 'https://example.test/?p=' . $post_id;
}

/**
 * Reports whether another configured loop post is available.
 *
 * @return bool
 */
function have_posts() {
	return $GLOBALS['mumega_motion_test_loop_index'] < count( $GLOBALS['mumega_motion_test_loop_posts'] );
}

/**
 * Advances the configured template loop.
 *
 * @return void
 */
function the_post() {
	$GLOBALS['mumega_motion_test_current_post'] = $GLOBALS['mumega_motion_test_loop_posts'][ $GLOBALS['mumega_motion_test_loop_index'] ];
	$GLOBALS['post']                            = $GLOBALS['mumega_motion_test_current_post'];
	++$GLOBALS['mumega_motion_test_loop_index'];
}

/**
 * Returns the configured current post.
 *
 * @return WP_Post|null
 */
function get_post() {
	return $GLOBALS['mumega_motion_test_current_post'];
}

/**
 * Prints the current post identifier.
 *
 * @return void
 */
function the_ID() {
	echo (int) $GLOBALS['mumega_motion_test_current_post']->ID;
}

/**
 * Prints deterministic post classes.
 *
 * @return void
 */
function post_class( $class = '', $post_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$classes = trim( 'post ' . $class );

	echo 'class="' . esc_attr( $classes ) . '"';
}

/**
 * Returns a configured post title.
 *
 * @param WP_Post|int|null $post Post value or identifier.
 * @return string
 */
function get_the_title( $post = null ) {
	if ( is_numeric( $post ) ) {
		$post = isset( $GLOBALS['mumega_motion_test_posts'][ (int) $post ] )
			? $GLOBALS['mumega_motion_test_posts'][ (int) $post ]
			: null;
	}

	if ( ! $post instanceof WP_Post ) {
		$post = $GLOBALS['mumega_motion_test_current_post'];
	}

	return $post instanceof WP_Post ? $post->post_title : '';
}

/**
 * Reports that rendered test posts do not have featured images by default.
 *
 * @param WP_Post|int|null $post Post value or identifier.
 * @return bool
 */
function has_post_thumbnail( $post = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return false;
}

/**
 * Returns a deterministic author display name.
 *
 * @param string $field   Author field.
 * @param int    $user_id Author identifier.
 * @return string
 */
function get_the_author_meta( $field = '', $user_id = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( isset( $GLOBALS['mumega_motion_test_author_meta'][ (int) $user_id ][ $field ] ) ) {
		return $GLOBALS['mumega_motion_test_author_meta'][ (int) $user_id ][ $field ];
	}

	if ( 'description' === $field ) {
		return '';
	}

	return (int) $user_id > 0 ? 'Test Author' : '';
}

/**
 * Returns a deterministic date for an explicit test post.
 *
 * @param string           $format Date format.
 * @param WP_Post|int|null $post   Post value or identifier.
 * @return string
 */
function get_the_date( $format = '', $post = null ) {
	if ( is_numeric( $post ) ) {
		$post = isset( $GLOBALS['mumega_motion_test_posts'][ (int) $post ] )
			? $GLOBALS['mumega_motion_test_posts'][ (int) $post ]
			: null;
	}

	if ( ! $post instanceof WP_Post ) {
		$post = $GLOBALS['mumega_motion_test_current_post'];
	}

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	return 'c' === $format ? $post->post_date_gmt . 'Z' : $post->post_date;
}

/**
 * Returns a deterministic modified date for an explicit test post.
 *
 * @param string           $format Date format.
 * @param WP_Post|int|null $post   Post value or identifier.
 * @return string
 */
function get_the_modified_date( $format = '', $post = null ) {
	if ( is_numeric( $post ) ) {
		$post = isset( $GLOBALS['mumega_motion_test_posts'][ (int) $post ] )
			? $GLOBALS['mumega_motion_test_posts'][ (int) $post ]
			: null;
	}

	if ( ! $post instanceof WP_Post ) {
		$post = $GLOBALS['mumega_motion_test_current_post'];
	}

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	return 'c' === $format ? $post->post_modified_gmt . 'Z' : $post->post_date;
}

/**
 * Prints the current post content.
 *
 * @return void
 */
function the_content() {
	echo $GLOBALS['mumega_motion_test_current_post']->post_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress content contract.
}

/**
 * Marks a header template call without nesting template fixtures.
 *
 * @return void
 */
function get_header() {
	echo '<!-- get_header -->';
}

/**
 * Marks a footer template call without nesting template fixtures.
 *
 * @return void
 */
function get_footer() {
	echo '<!-- get_footer -->';
}

/**
 * Simulates Elementor Pro's conditional theme-location renderer.
 *
 * @param string $location Theme Builder location.
 * @return bool
 */
function elementor_theme_do_location( $location ) {
	$rendered = ! empty( $GLOBALS['mumega_motion_test_elementor_locations'][ $location ] );

	if ( $rendered ) {
		$GLOBALS['mumega_motion_test_elementor_shell_calls'][] = $location;
		$output = array_key_exists( $location, $GLOBALS['mumega_motion_test_elementor_location_output'] )
			? $GLOBALS['mumega_motion_test_elementor_location_output'][ $location ]
			: sprintf(
				'<!-- elementor_%1$s --><%1$s data-elementor-type="%1$s"></%1$s>',
				esc_attr( $location )
			);
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Deterministic test fixture.
	}

	return $rendered;
}

/**
 * Includes a theme template part when it exists.
 *
 * @param string $slug Template-part slug.
 * @param string $name Optional specialized name.
 * @return void
 */
function get_template_part( $slug, $name = null, $args = array() ) {
	$path = get_template_directory() . '/' . $slug . ( null === $name ? '' : '-' . $name ) . '.php';

	if ( file_exists( $path ) ) {
		if ( is_array( $args ) ) {
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Mirrors the native template-part argument contract.
		}

		require $path;
	}
}

/**
 * Populates the canonical globals changed by WP_Query::setup_postdata().
 *
 * @param WP_Post $post Post value.
 * @return void
 */
function mumega_motion_test_populate_postdata_globals( $post ) {
	$post_date = (string) $post->post_date;
	$pages     = false !== strpos( $post->post_content, '<!--nextpage-->' )
		? explode( '<!--nextpage-->', $post->post_content )
		: array( $post->post_content );

	$GLOBALS['id']           = (int) $post->ID;
	$GLOBALS['authordata']   = (object) array( 'ID' => (int) $post->post_author );
	$GLOBALS['currentday']   = '' !== $post_date && '0000-00-00 00:00:00' !== $post_date
		? sprintf( '%s.%s.%s', substr( $post_date, 8, 2 ), substr( $post_date, 5, 2 ), substr( $post_date, 2, 2 ) )
		: false;
	$GLOBALS['currentmonth'] = '' !== $post_date && '0000-00-00 00:00:00' !== $post_date ? substr( $post_date, 5, 2 ) : false;
	$GLOBALS['page']         = 1;
	$GLOBALS['pages']        = $pages;
	$GLOBALS['multipage']    = count( $pages ) > 1 ? 1 : 0;
	$GLOBALS['more']         = 0;
	$GLOBALS['numpages']     = count( $pages );
}

/**
 * Sets up a post as the current template context and records the transition.
 *
 * @param WP_Post $post Post value.
 * @return true
 */
function setup_postdata( $post ) {
	$GLOBALS['mumega_motion_test_postdata_events'][] = array( 'setup', (int) $post->ID );
	$GLOBALS['mumega_motion_test_current_post']       = $post;
	mumega_motion_test_populate_postdata_globals( $post );

	if ( isset( $GLOBALS['mumega_motion_test_setup_postdata_exceptions'][ (int) $post->ID ] ) ) {
		throw $GLOBALS['mumega_motion_test_setup_postdata_exceptions'][ (int) $post->ID ];
	}

	return true;
}

/**
 * Records WordPress's restoration of the main query post.
 *
 * @return void
 */
function wp_reset_postdata() {
	$GLOBALS['mumega_motion_test_postdata_events'][] = array( 'reset' );

	if ( $GLOBALS['mumega_motion_test_main_query_post'] instanceof WP_Post ) {
		$GLOBALS['post']                            = $GLOBALS['mumega_motion_test_main_query_post'];
		$GLOBALS['mumega_motion_test_current_post'] = $GLOBALS['mumega_motion_test_main_query_post'];
		mumega_motion_test_populate_postdata_globals( $GLOBALS['mumega_motion_test_main_query_post'] );
	}

	if ( $GLOBALS['mumega_motion_test_reset_postdata_exception'] instanceof Throwable ) {
		throw $GLOBALS['mumega_motion_test_reset_postdata_exception'];
	}
}

/**
 * Prints deterministic posts pagination.
 *
 * @return void
 */
function the_posts_pagination() {
	echo '<nav class="pagination" aria-label="Posts"><a href="#page-2">Next</a></nav>';
}

/**
 * Prints configured native previous/next article navigation.
 *
 * @return void
 */
function the_post_navigation() {
	echo $GLOBALS['mumega_motion_test_adjacent_post_navigation']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native navigation fixture.
}

/**
 * Returns the configured native queried object.
 *
 * @return WP_Term|null
 */
function get_queried_object() {
	return $GLOBALS['mumega_motion_test_queried_object'];
}

/**
 * Returns a deterministic native archive heading.
 *
 * @return string
 */
function get_the_archive_title() {
	$term = get_queried_object();

	if ( ! $term instanceof WP_Term ) {
		return 'Archive';
	}

	$prefix = 'post_tag' === $term->taxonomy ? 'Tag: ' : 'Category: ';

	return $prefix . $term->name;
}

/**
 * Prints the deterministic native archive heading.
 *
 * @param string $before Markup before the title.
 * @param string $after  Markup after the title.
 * @return void
 */
function the_archive_title( $before = '', $after = '' ) {
	echo $before . wp_kses_post( get_the_archive_title() ) . $after; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native archive-title fixture.
}

/**
 * Returns the configured native archive description.
 *
 * @return string
 */
function get_the_archive_description() {
	$term = get_queried_object();

	return $term instanceof WP_Term ? (string) $term->description : '';
}

/**
 * Returns the configured native search query.
 *
 * @return string
 */
function get_search_query() {
	return $GLOBALS['mumega_motion_test_search_query'];
}

/**
 * Returns the current year for footer fixtures.
 *
 * @param string $format Date format.
 * @return string
 */
function wp_date( $format ) {
	return 'Y' === $format ? '2026' : '';
}

/**
 * Registers a theme feature for assertions.
 *
 * @param string $feature Theme feature name.
 * @param mixed  ...$args Feature arguments.
 * @return true
 */
function add_theme_support( $feature, ...$args ) {
	$GLOBALS['mumega_motion_test_theme_supports'][ $feature ] = $args;

	return true;
}

/**
 * Registers navigation locations for assertions.
 *
 * @param array $locations Menu locations.
 * @return void
 */
function register_nav_menus( $locations = array() ) {
	$GLOBALS['mumega_motion_test_menu_locations'] = $locations;
}

/**
 * Reports whether the configured request is singular.
 *
 * @return bool
 */
function is_singular( $post_types = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return ! empty( $GLOBALS['mumega_motion_test_conditionals']['is_singular'] );
}

/**
 * Reports whether the configured request is the posts index.
 *
 * @return bool
 */
function is_home() {
	return ! empty( $GLOBALS['mumega_motion_test_conditionals']['is_home'] );
}

/**
 * Reports whether the configured request is an archive.
 *
 * @return bool
 */
function is_archive() {
	return ! empty( $GLOBALS['mumega_motion_test_conditionals']['is_archive'] );
}

/**
 * Reports whether the configured request is search results.
 *
 * @return bool
 */
function is_search() {
	return ! empty( $GLOBALS['mumega_motion_test_conditionals']['is_search'] );
}

/**
 * Reports whether the configured request is a 404 response.
 *
 * @return bool
 */
function is_404() {
	return ! empty( $GLOBALS['mumega_motion_test_conditionals']['is_404'] );
}

/**
 * Reports whether the configured page template matches.
 *
 * @param string $template Template path.
 * @return bool
 */
function is_page_template( $template = '' ) {
	return $template === $GLOBALS['mumega_motion_test_page_template'];
}

/**
 * Returns the current queried object identifier.
 *
 * @return int
 */
function get_queried_object_id() {
	return (int) $GLOBALS['mumega_motion_test_queried_object_id'];
}

/**
 * Enqueues a stylesheet for assertions.
 *
 * @param string $handle Stylesheet handle.
 * @param string $src Source URL.
 * @param array  $deps Dependencies.
 * @param mixed  $ver Version.
 * @param string $media Media query.
 * @return void
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	$GLOBALS['mumega_motion_test_enqueued_styles'][ $handle ] = array(
		'src'   => $src,
		'deps'  => $deps,
		'ver'   => $ver,
		'media' => $media,
	);
}

/**
 * Enqueues a script for assertions.
 *
 * @param string $handle Script handle.
 * @param string $src Source URL.
 * @param array  $deps Dependencies.
 * @param mixed  $ver Version.
 * @param bool   $in_footer Footer flag.
 * @return void
 */
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['mumega_motion_test_enqueued_scripts'][ $handle ] = array(
		'src'       => $src,
		'deps'      => $deps,
		'ver'       => $ver,
		'in_footer' => $in_footer,
	);
}

/**
 * Records a dequeued stylesheet for assertions.
 *
 * @param string $handle Stylesheet handle.
 * @return void
 */
function wp_dequeue_style( $handle ) {
	$GLOBALS['mumega_motion_test_dequeued_styles'][] = $handle;
}

/**
 * Records a dequeued script for assertions.
 *
 * @param string $handle Script handle.
 * @return void
 */
function wp_dequeue_script( $handle ) {
	$GLOBALS['mumega_motion_test_dequeued_scripts'][] = $handle;
}

/**
 * Returns the test theme directory.
 *
 * @return string
 */
function get_template_directory() {
	return dirname( __DIR__ );
}

/**
 * Returns the test theme directory URI.
 *
 * @return string
 */
function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/mumega-motion';
}

/**
 * Returns the test stylesheet URI.
 *
 * @return string
 */
function get_stylesheet_uri() {
	return get_template_directory_uri() . '/style.css';
}

/**
 * Returns a theme object with a stable version.
 *
 * @return object
 */
function wp_get_theme() {
	return new class() {
		/**
		 * Returns the test theme version.
		 *
		 * @param string $header Theme header name.
		 * @return string
		 */
		public function get( $header ) {
			return 'Version' === $header ? '0.1.0' : '';
		}
	};
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
 * Retrieves a configured category convention term.
 *
 * @param string $slug Category slug.
 * @return WP_Term|false
 */
function get_category_by_slug( $slug ) {
	$GLOBALS['mumega_motion_test_category_by_slug_requests'][] = $slug;

	return isset( $GLOBALS['mumega_motion_test_categories_by_slug'][ $slug ] )
		? $GLOBALS['mumega_motion_test_categories_by_slug'][ $slug ]
		: false;
}

/**
 * Retrieves configured category values and records query arguments.
 *
 * @param array $args Category query arguments.
 * @return array
 */
function get_categories( $args = array() ) {
	$GLOBALS['mumega_motion_test_get_categories_requests'][] = $args;

	return $GLOBALS['mumega_motion_test_categories'];
}

/**
 * Retrieves configured menu-location assignments.
 *
 * @return array
 */
function get_nav_menu_locations() {
	$GLOBALS['mumega_motion_test_nav_menu_location_requests'][] = true;

	return $GLOBALS['mumega_motion_test_nav_menu_locations'];
}

/**
 * Retrieves a configured navigation-menu object.
 *
 * @param int $menu Menu identifier.
 * @return WP_Term|false
 */
function wp_get_nav_menu_object( $menu ) {
	$GLOBALS['mumega_motion_test_nav_menu_object_requests'][] = (int) $menu;

	return isset( $GLOBALS['mumega_motion_test_nav_menu_objects'][ (int) $menu ] )
		? $GLOBALS['mumega_motion_test_nav_menu_objects'][ (int) $menu ]
		: false;
}

/**
 * Retrieves configured navigation-menu items and records menu identifiers.
 *
 * @param int $menu Menu identifier.
 * @return array|false
 */
function wp_get_nav_menu_items( $menu ) {
	$GLOBALS['mumega_motion_test_nav_menu_item_requests'][] = (int) $menu;

	return isset( $GLOBALS['mumega_motion_test_nav_menu_items'][ (int) $menu ] )
		? $GLOBALS['mumega_motion_test_nav_menu_items'][ (int) $menu ]
		: false;
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
 * Records a registered block-pattern category for assertions.
 *
 * @param string $category_name Pattern category slug.
 * @param array  $category_properties Pattern category properties.
 * @return bool
 */
function register_block_pattern_category( $category_name, $category_properties = array() ) {
	$GLOBALS['mumega_motion_test_pattern_categories'][ $category_name ] = $category_properties;

	return true;
}

/**
 * Records a registered block pattern for assertions.
 *
 * @param string $pattern_name Pattern slug.
 * @param array  $pattern_properties Pattern properties.
 * @return bool
 */
function register_block_pattern( $pattern_name, $pattern_properties = array() ) {
	$GLOBALS['mumega_motion_test_patterns'][ $pattern_name ] = $pattern_properties;

	return true;
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
	if ( 'version' === $show ) {
		return '6.5';
	}

	return isset( $GLOBALS['mumega_motion_test_bloginfo'][ $show ] )
		? $GLOBALS['mumega_motion_test_bloginfo'][ $show ]
		: '';
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
