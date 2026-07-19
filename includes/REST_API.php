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
		// Frontend event tracking. Kept public (anonymous learners are tracked)
		// but hardened: the localized nonce is verified and events are rate-limited.
		register_rest_route(
			'tutor-analytics/v1',
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_track' ),
				'permission_callback' => array( $this, 'can_track' ),
			)
		);

		// Dashboard section data — powers lazy loading, so each tab only
		// computes when opened. Admin-only.
		register_rest_route(
			'tutor-analytics/v1',
			'/section',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_section' ),
				'permission_callback' => array( $this, 'can_view_analytics' ),
				'args'                => array(
					'section'   => array( 'required' => true, 'type' => 'string' ),
					'course_id' => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
					'from'      => array( 'required' => false, 'type' => 'string' ),
					'to'        => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);
	}

	/** Only users who can see the analytics menu may pull section data. */
	public function can_view_analytics(): bool {
		return current_user_can( 'manage_tutor' ) || current_user_can( 'manage_options' ) || current_user_can( 'tutor_instructor' );
	}

	/**
	 * Gate the tracking endpoint: valid REST nonce (when present) and a soft
	 * per-user/IP rate limit to prevent the open endpoint being flooded.
	 */
	public function can_track( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return $this->within_rate_limit();
	}

	private function within_rate_limit(): bool {
		$user_id = get_current_user_id();
		$bucket  = $user_id > 0 ? 'u' . $user_id : 'ip' . md5( $this->client_ip() );
		$key     = 'tla_rl_' . $bucket;

		$count = (int) get_transient( $key );
		if ( $count >= 120 ) { // Max 120 events/minute per user or IP.
			return false;
		}
		set_transient( $key, $count + 1, 60 );

		return true;
	}

	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip ?: 'unknown';
	}

	public function handle_section( WP_REST_Request $request ): WP_REST_Response {
		$section   = sanitize_key( (string) $request->get_param( 'section' ) );
		$course_id = absint( $request->get_param( 'course_id' ) );
		$range     = Date_Range::from_request(
			$request->get_param( 'from' ) ? sanitize_text_field( (string) $request->get_param( 'from' ) ) : null,
			$request->get_param( 'to' ) ? sanitize_text_field( (string) $request->get_param( 'to' ) ) : null
		);

		$service = new Analytics_Service();
		if ( ! $service->is_valid_section( $course_id, $section ) ) {
			return new WP_REST_Response(
				array( 'success' => false, 'error' => 'invalid_section' ),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'section' => $section,
				'range'   => array( 'from' => $range->from(), 'to' => $range->to() ),
				'data'    => $service->get_section( $section, $course_id, $range ),
			),
			200
		);
	}

	public function handle_track( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$params = $request->get_params();
		if ( empty( $params['event_type'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'Missing event_type' ), 400 );
		}

		$user_id    = get_current_user_id();
		$course_id  = isset( $params['course_id'] ) ? (int) $params['course_id'] : 0;
		$lesson_id  = isset( $params['lesson_id'] ) ? (int) $params['lesson_id'] : 0;
		$event_type = sanitize_text_field( (string) $params['event_type'] );
		$event_val  = isset( $params['event_value'] ) ? sanitize_text_field( (string) $params['event_value'] ) : '';

		// Parse minimal User-Agent.
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

		return new WP_REST_Response( array( 'success' => true ), 201 );
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
		if ( stripos( $ua, 'Edg' ) !== false || stripos( $ua, 'Edge' ) !== false ) {
			return 'Edge';
		}
		if ( stripos( $ua, 'Firefox' ) !== false ) {
			return 'Firefox';
		}
		if ( stripos( $ua, 'Chrome' ) !== false ) {
			return 'Chrome';
		}
		if ( stripos( $ua, 'Safari' ) !== false ) {
			return 'Safari';
		}
		return 'Other';
	}
}
