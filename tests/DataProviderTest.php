<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Data_Provider;

class DataProviderTest extends TestCase {

	private $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new Data_Provider();
		global $wpdb;
		$wpdb->mock_results = array();
		$wpdb->mock_var = array();
		$wpdb->default_var = 0;
		$GLOBALS['mock_post_titles'] = array();
	}

	public function test_get_all_courses_empty_db(): void {
		$courses = $this->provider->get_all_courses();
		$this->assertIsArray( $courses );
		$this->assertEmpty( $courses );
	}

	public function test_get_all_courses_with_data(): void {
		global $wpdb;
		$wpdb->mock_results["post_type = 'courses'"] = array(
			array( 'ID' => 1, 'post_title' => 'Course 1' ),
			array( 'ID' => 2, 'post_title' => 'Course 2' ),
		);

		$courses = $this->provider->get_all_courses();
		$this->assertCount( 2, $courses );
		$this->assertEquals( 'Course 1', $courses[0]['post_title'] );
	}

	public function test_get_all_stats_empty_state(): void {
		$stats = $this->provider->get_all_stats( 0 );
		$this->assertEquals( 0, $stats['total_students'] );
		$this->assertEquals( 0, $stats['total_completions'] );
		$this->assertEmpty( $stats['enrollment_trend']['data'] );
		$this->assertEmpty( $stats['top_courses'] );
	}

	public function test_get_all_stats_filtered_by_course(): void {
		global $wpdb;
		$wpdb->mock_var["post_parent = 123"] = 50; 
		$wpdb->mock_var["comment_post_ID = 123"] = 25; 
		
		$wpdb->default_var = 15; 
		
		$stats = $this->provider->get_all_stats( 123 );
		$this->assertEquals( 50, $stats['total_students'] );
		$this->assertEquals( 25, $stats['total_completions'] );
	}

	public function test_get_course_popularity_less_than_6(): void {
		global $wpdb;
		$GLOBALS['mock_post_titles'] = array(
			1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E'
		);
		
		$wpdb->mock_results["GROUP BY post_parent"] = array(
			array( 'course_id' => 1, 'count' => 10 ),
			array( 'course_id' => 2, 'count' => 9 ),
			array( 'course_id' => 3, 'count' => 8 ),
			array( 'course_id' => 4, 'count' => 7 ),
			array( 'course_id' => 5, 'count' => 6 ),
		);

		// Use reflection to call private method
		$reflection = new ReflectionClass( $this->provider );
		$method = $reflection->getMethod( 'get_course_popularity' );
		$method->setAccessible( true );

		$popularity = $method->invoke( $this->provider );
		
		$this->assertCount( 5, $popularity );
		$this->assertArrayNotHasKey( 'อื่นๆ (Others)', $popularity );
	}

	public function test_get_course_popularity_more_than_6_groups_others(): void {
		global $wpdb;
		$GLOBALS['mock_post_titles'] = array(
			1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G'
		);
		
		$wpdb->mock_results["GROUP BY post_parent"] = array(
			array( 'course_id' => 1, 'count' => 10 ),
			array( 'course_id' => 2, 'count' => 9 ),
			array( 'course_id' => 3, 'count' => 8 ),
			array( 'course_id' => 4, 'count' => 7 ),
			array( 'course_id' => 5, 'count' => 6 ),
			array( 'course_id' => 6, 'count' => 5 ), // Should be others
			array( 'course_id' => 7, 'count' => 1 ), // Should be others
		);

		$reflection = new ReflectionClass( $this->provider );
		$method = $reflection->getMethod( 'get_course_popularity' );
		$method->setAccessible( true );

		$popularity = $method->invoke( $this->provider );
		
		$this->assertCount( 6, $popularity );
		$this->assertArrayHasKey( 'อื่นๆ (Others)', $popularity );
		$this->assertEquals( 6, $popularity['อื่นๆ (Others)'] ); // 5 + 1
	}

	public function test_get_activity_by_day_of_week(): void {
		global $wpdb;
		$wpdb->mock_results["GROUP BY DAYOFWEEK"] = array(
			array( 'day_num' => 1, 'count' => 5 ), // Sunday
			array( 'day_num' => 4, 'count' => 10 ), // Wednesday
		);

		$reflection = new ReflectionClass( $this->provider );
		$method = $reflection->getMethod( 'get_activity_by_day_of_week' );
		$method->setAccessible( true );

		$activity = $method->invoke( $this->provider, 0 );
		
		$this->assertEquals( 5, $activity['อาทิตย์'] );
		$this->assertEquals( 10, $activity['พุธ'] );
		$this->assertEquals( 0, $activity['จันทร์'] );
	}
}
