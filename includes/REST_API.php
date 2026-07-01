<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class REST_API {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints(): void {
		register_rest_route(
			'tutor-analytics/v1',
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_track' ),
				'permission_callback' => '__return_true', // Open tracking, relies on user ID or anonymous session
			)
		);
	}

	public function handle_track( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$params = $request->get_params();
		if ( empty( $params['event_type'] ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Missing event_type' ) );
		}

		$user_id    = get_current_user_id();
		$course_id  = isset( $params['course_id'] ) ? (int) $params['course_id'] : 0;
		$lesson_id  = isset( $params['lesson_id'] ) ? (int) $params['lesson_id'] : 0;
		$event_type = sanitize_text_field( $params['event_type'] );
		$event_val  = isset( $params['event_value'] ) ? sanitize_text_field( (string) $params['event_value'] ) : '';
		
		// Parse minimal User-Agent
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$device  = $this->parse_device( $ua );
		$browser = $this->parse_browser( $ua );

		$wpdb->insert(
			Database::get_events_table_name(),
			array(
				'user_id'     => $user_id,
				'course_id'   => $course_id,
				'lesson_id'   => $lesson_id,
				'event_type'  => $event_type,
				'event_value' => $event_val,
				'user_agent'  => $ua,
				'device_type' => $device,
				'browser'     => $browser,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return rest_ensure_response( array( 'success' => true ) );
	}

	private function parse_device( string $ua ): string {
		if ( preg_match( '/(tablet|ipad|playbook)|(android(?!.*mobi))/i', $ua ) ) {
			return 'tablet';
		}
		if ( preg_match( '/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/', $ua ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	private function parse_browser( string $ua ): string {
		if ( stripos( $ua, 'Edg' ) !== false || stripos( $ua, 'Edge' ) !== false ) return 'Edge';
		if ( stripos( $ua, 'Firefox' ) !== false ) return 'Firefox';
		if ( stripos( $ua, 'Chrome' ) !== false ) return 'Chrome';
		if ( stripos( $ua, 'Safari' ) !== false ) return 'Safari';
		return 'Other';
	}
}
