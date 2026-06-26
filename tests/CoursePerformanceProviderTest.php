<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Providers\Course_Performance_Provider;

class CoursePerformanceProviderTest extends TestCase {

	private $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new Course_Performance_Provider();
		global $wpdb;
		$wpdb->mock_results = array();
		$wpdb->mock_var = array();
		$wpdb->default_var = 0;
	}

	public function test_get_course_table_empty(): void {
		$table = $this->provider->get_course_table();
		$this->assertIsArray( $table );
		$this->assertEmpty( $table );
	}

	public function test_get_course_table_learners_query_uses_wp_posts(): void {
		global $wpdb;

		// Mock the courses query
		$wpdb->mock_results["post_type = 'courses'"] = array(
			array( 'ID' => 101, 'post_title' => 'Test Course' ),
		);

		// Mock learners query (should check wp_posts with tutor_enrolled)
		$wpdb->mock_var["post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')"] = 5;

		// Mock completions query
		$wpdb->mock_var["comment_type = 'course_completed'"] = 2;

		// Default var to cover SHOW TABLES and rating
		$wpdb->default_var = 0;

		$table = $this->provider->get_course_table();

		$this->assertCount( 1, $table );
		$this->assertEquals( 101, $table[0]['id'] );
		$this->assertEquals( 'Test Course', $table[0]['title'] );
		$this->assertEquals( 5, $table[0]['learners'], 'Learners should be correctly retrieved from wp_posts.' );
		$this->assertEquals( 40.0, $table[0]['completion_rate'], 'Completion rate should be calculated correctly.' );
	}
}
