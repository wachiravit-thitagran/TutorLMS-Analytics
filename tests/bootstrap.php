<?php
/**
 * PHPUnit bootstrap file.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WP constants if not defined
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/stubs/' );
}

if ( ! defined( 'TUTORLMS_ANALYTICS_VERSION' ) ) {
	define( 'TUTORLMS_ANALYTICS_VERSION', '1.0.7' );
}

if ( ! defined( 'TUTORLMS_ANALYTICS_DIR' ) ) {
	define( 'TUTORLMS_ANALYTICS_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'TUTORLMS_ANALYTICS_URL' ) ) {
	define( 'TUTORLMS_ANALYTICS_URL', 'http://example.org/wp-content/plugins/tutorlms-analytics/' );
}

// Basic WP Stubs
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $function ) {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $function ) {}
}
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) { return TUTORLMS_ANALYTICS_URL . $path; }
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) { return TUTORLMS_ANALYTICS_DIR; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) { return TUTORLMS_ANALYTICS_URL; }
}

// WPDB Mock
class Mock_WPDB {
	public $posts = 'wp_posts';
	public $comments = 'wp_comments';
	public $postmeta = 'wp_postmeta';
	public $prefix = 'wp_';
	public $mock_results = array();
	public $mock_var = array();
	public $default_var = 0;

	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( '%d', '%d', $query ), $args ); // Simple mock prepare
	}

	public function get_results( $query, $output = 'OBJECT' ) {
		// Attempt to map query to mock results
		foreach ( $this->mock_results as $pattern => $result ) {
			if ( strpos( $query, $pattern ) !== false ) {
				return is_array($result) ? $result : array();
			}
		}
		return array();
	}

	public function insert( $table, $data, $format = null ) {
		$this->mock_results['last_insert'] = $data;
		return 1;
	}

	public function get_var( $query ) {
		foreach ( $this->mock_var as $pattern => $result ) {
			if ( strpos( $query, $pattern ) !== false ) {
				return $result;
			}
		}
		return $this->default_var;
	}
}

global $wpdb;
$wpdb = new Mock_WPDB();

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		if ( isset( $GLOBALS['mock_post_titles'][$post] ) ) {
			return $GLOBALS['mock_post_titles'][$post];
		}
		return 'Course ' . $post;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return $GLOBALS['mock_users'][ $user_id ] ?? false;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['mock_user_meta'][ $user_id ][ $key ] ) ) {
			return $GLOBALS['mock_user_meta'][ $user_id ][ $key ];
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.org/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = strip_tags( (string) $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		return (bool) preg_match( '/^([adObis]):/', $data );
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array() ) {
		$GLOBALS['mock_routes'][ $namespace . $route ] = $args;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200, $headers = array() ) {
		$this->data = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( $response );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return stripslashes( $value );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return date( 'Y-m-d H:i:s' );
	}
}

class WP_REST_Request {
	private $json_params = array();
	private $headers     = array();

	public function set_json_params( $params ) {
		$this->json_params = $params;
	}

	public function get_json_params() {
		return $this->json_params;
	}

	public function get_params() {
		return $this->json_params;
	}

	public function set_param( $key, $value ) {
		$this->json_params[ $key ] = $value;
	}

	public function get_param( $key ) {
		return $this->json_params[ $key ] ?? null;
	}

	public function set_header( $key, $value ) {
		$this->headers[ strtolower( $key ) ] = $value;
	}

	public function get_header( $key ) {
		return $this->headers[ strtolower( $key ) ] ?? null;
	}
}

class WP_REST_Server {
	const CREATABLE = 'POST';
	const READABLE  = 'GET';
}

// WPDB Mock Database specific additions
class Mock_Database {
	public static function get_events_table_name() {
		return 'wp_tutorlms_events';
	}
}
if (!class_exists('TutorLMS_Analytics\Database')) {
	class_alias('Mock_Database', 'TutorLMS_Analytics\Database');
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['mock_post_meta'][ $post_id ][ $key ] ) ) {
			return $GLOBALS['mock_post_meta'][ $post_id ][ $key ];
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( is_string( $data ) && preg_match( '/^[aOs]:/', trim( $data ) ) ) {
			$un = @unserialize( trim( $data ) );
			if ( false !== $un ) {
				return $un;
			}
		}
		return $data;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['mock_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['mock_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['mock_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['mock_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_gmt_from_date' ) ) {
	function get_gmt_from_date( $date, $format = 'Y-m-d H:i:s' ) {
		return $date; // Tests run in UTC.
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) {
		return $url . ( strpos( $url, '?' ) !== false ? '&' : '?' ) . '_wpnonce=test-nonce';
	}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://example.org/wp-json/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'th';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( $file );
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
		return true;
	}
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test-nonce';
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 'test-nonce' === $nonce ? 1 : false;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return $GLOBALS['mock_user_can'][ $cap ] ?? true;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['mock_transients'][ $key ] );
		return true;
	}
}

// Load the main plugin file
require dirname( __DIR__ ) . '/tutorlms-analytics.php';
