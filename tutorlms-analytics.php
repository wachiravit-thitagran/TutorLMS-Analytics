<?php
/**
 * Plugin Name: TutorLMS Analytics
 * Plugin URI: https://example.com
 * Description: In-depth statistics and analytics dashboard for Tutor LMS.
 * Version: 1.0.0
 * Author: BIA
 * Text Domain: tutorlms-analytics
 *
 * @package TutorLMS_Analytics
 */

declare(strict_types=1);

namespace TutorLMS_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TUTORLMS_ANALYTICS_VERSION', '1.0.0' );
define( 'TUTORLMS_ANALYTICS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TUTORLMS_ANALYTICS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'TutorLMS_Analytics\\';
		$base_dir = TUTORLMS_ANALYTICS_DIR . 'includes/';
		$len      = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function init() {
	if ( ! function_exists( 'tutor_utils' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\missing_tutor_notice' );
		return;
	}

	( new Admin_Menu() )->register();
	( new REST_API() )->register();
	( new Export_Handler() )->register();

	// Enqueue tracker on frontend
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_tracker' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Enqueue frontend tracker.
 */
function enqueue_tracker() {
	// Only run on course/lesson/quiz pages
	if ( is_singular( array( 'courses', 'lesson', 'tutor_quiz' ) ) ) {
		wp_enqueue_script( 'tutorlms-analytics-tracker', TUTORLMS_ANALYTICS_URL . 'assets/tracker.js', array(), TUTORLMS_ANALYTICS_VERSION, true );
		wp_localize_script( 'tutorlms-analytics-tracker', 'TutorLMSAnalyticsData', array(
			'rest_url'  => esc_url_raw( rest_url( 'tutor-analytics/v1/track' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'course_id' => get_queried_object_id(), // Depends on context, tracker.js will also parse from DOM if needed
		) );
	}
}

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
function activate_plugin() {
	Database::create_tables();
}

/**
 * Admin notice if Tutor LMS is missing.
 */
function missing_tutor_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'TutorLMS Analytics requires Tutor LMS to be installed and activated.', 'tutorlms-analytics' ); ?></p>
	</div>
	<?php
}
