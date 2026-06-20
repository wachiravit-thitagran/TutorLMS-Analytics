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
	define( 'TUTORLMS_ANALYTICS_VERSION', '1.0.0' );
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

// Load the main plugin file
require dirname( __DIR__ ) . '/tutorlms-analytics.php';
