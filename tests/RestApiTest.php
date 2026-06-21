<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\REST_API;

class RestApiTest extends TestCase {

	private $api;

	protected function setUp(): void {
		parent::setUp();
		$this->api = new REST_API();
		$GLOBALS['mock_routes'] = array();
		global $wpdb;
		$wpdb->mock_results = array();
	}

	public function test_register_endpoints(): void {
		$this->api->register_endpoints();
		$this->assertArrayHasKey( 'tutor-analytics/v1/track', $GLOBALS['mock_routes'] );
	}

	public function test_handle_track_returns_error_if_missing_event_type(): void {
		$request = new WP_REST_Request();
		$request->set_json_params( array( 'course_id' => 123 ) ); // Missing event_type

		$response = $this->api->handle_track( $request );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'Missing event_type', $data['error'] );
	}

	public function test_handle_track_inserts_data_and_returns_success(): void {
		global $wpdb;
		
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)'; // Should parse as mobile
		
		$request = new WP_REST_Request();
		$request->set_json_params( array(
			'event_type'  => 'course_view',
			'course_id'   => 10,
			'event_value' => 'test'
		) );

		$response = $this->api->handle_track( $request );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['success'] );
		
		$this->assertArrayHasKey( 'last_insert', $wpdb->mock_results );
		$insert = $wpdb->mock_results['last_insert'];
		
		$this->assertEquals( 10, $insert['course_id'] );
		$this->assertEquals( 'course_view', $insert['event_type'] );
		$this->assertEquals( 'test', $insert['event_value'] );
		$this->assertEquals( 'mobile', $insert['device_type'] );
	}

	public function test_parse_browser(): void {
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'parse_browser' );
		$method->setAccessible( true );

		$this->assertEquals( 'Chrome', $method->invoke( $this->api, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36' ) );
		$this->assertEquals( 'Safari', $method->invoke( $this->api, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15' ) );
		$this->assertEquals( 'Firefox', $method->invoke( $this->api, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' ) );
		$this->assertEquals( 'Edge', $method->invoke( $this->api, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59' ) );
		$this->assertEquals( 'Other', $method->invoke( $this->api, 'Some Unknown Browser v1.0' ) );
	}

	public function test_parse_device(): void {
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'parse_device' );
		$method->setAccessible( true );

		$this->assertEquals( 'tablet', $method->invoke( $this->api, 'Mozilla/5.0 (iPad; CPU OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15' ) );
		$this->assertEquals( 'mobile', $method->invoke( $this->api, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)' ) );
		$this->assertEquals( 'desktop', $method->invoke( $this->api, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' ) );
	}
}
