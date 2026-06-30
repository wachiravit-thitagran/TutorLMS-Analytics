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

	public function set_json_params( $params ) {
		$this->json_params = $params;
	}

	public function get_json_params() {
		return $this->json_params;
	}
}

class WP_REST_Server {
	const CREATABLE = 'POST';
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

// Load the main plugin file
require dirname( __DIR__ ) . '/tutorlms-analytics.php';
