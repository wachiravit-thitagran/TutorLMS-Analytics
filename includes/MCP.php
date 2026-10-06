<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

defined( 'ABSPATH' ) || exit;

final class MCP {
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			'tutorlms-analytics',
			array(
				'label'       => 'TutorLMS Analytics',
				'description' => 'Tutor LMS analytics and reporting abilities.',
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'tutorlms-analytics/get-section',
			array(
				'label'       => 'Get Analytics Section',
				'description' => 'Return a TutorLMS Analytics dashboard section for a date range.',
				'category'    => 'tutorlms-analytics',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'section'   => array( 'type' => 'string' ),
						'course_id' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
						'from'      => array( 'type' => 'string' ),
						'to'        => array( 'type' => 'string' ),
					),
					'required' => array( 'section' ),
				),
				'execute_callback'    => array( self::class, 'get_section' ),
				'permission_callback' => array( self::class, 'can_view' ),
				'meta'                => self::meta(),
			)
		);
	}

	public static function can_view(): bool {
		return current_user_can( 'manage_tutor' ) || current_user_can( 'manage_options' ) || current_user_can( 'tutor_instructor' );
	}

	public static function get_section( array $input ) {
		$request = new \WP_REST_Request( 'GET', '/tutor-analytics/v1/section' );
		foreach ( array( 'section', 'course_id', 'from', 'to' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$request->set_param( $key, $input[ $key ] );
			}
		}

		return self::rest_data( rest_do_request( $request ) );
	}

	private static function rest_data( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response instanceof \WP_REST_Response ) {
			if ( $response->get_status() >= 400 ) {
				$data = $response->get_data();
				return new \WP_Error( 'tutorlms_analytics_mcp_rest_error', is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : 'Analytics request failed.', array( 'status' => $response->get_status(), 'response' => $data ) );
			}
			return $response->get_data();
		}
		return $response;
	}

	private static function meta(): array {
		return array(
			'mcp' => array( 'public' => true, 'type' => 'tool' ),
			'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'openWorldHint' => false ),
		);
	}
}
