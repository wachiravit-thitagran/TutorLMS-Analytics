<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Tests;

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\REST_API;
use WP_REST_Request;

final class RESTAPIAuthorizationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['mock_user_can'] = array(
			'manage_options'   => false,
			'manage_tutor'     => false,
			'tutor_instructor' => false,
			'edit_post'        => false,
		);
	}

	public function test_staff_can_view_global_analytics(): void {
		$GLOBALS['mock_user_can']['manage_tutor'] = true;
		$request = new WP_REST_Request();
		$request->set_param( 'course_id', 0 );

		$this->assertTrue( ( new REST_API() )->can_view_analytics( $request ) );
	}

	public function test_instructor_cannot_view_global_analytics(): void {
		$GLOBALS['mock_user_can']['tutor_instructor'] = true;
		$request = new WP_REST_Request();
		$request->set_param( 'course_id', 0 );

		$this->assertFalse( ( new REST_API() )->can_view_analytics( $request ) );
	}

	public function test_instructor_cannot_view_course_without_edit_rights(): void {
		$GLOBALS['mock_user_can']['tutor_instructor'] = true;
		$GLOBALS['mock_user_can']['edit_post'] = false;
		$request = new WP_REST_Request();
		$request->set_param( 'course_id', 123 );

		$this->assertFalse( ( new REST_API() )->can_view_analytics( $request ) );
	}

	public function test_instructor_can_view_course_they_can_edit(): void {
		$GLOBALS['mock_user_can']['tutor_instructor'] = true;
		$GLOBALS['mock_user_can']['edit_post'] = true;
		$request = new WP_REST_Request();
		$request->set_param( 'course_id', 123 );

		$this->assertTrue( ( new REST_API() )->can_view_analytics( $request ) );
	}
}
