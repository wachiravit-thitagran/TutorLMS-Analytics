<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Providers\Content_Gap_Provider;
use TutorLMS_Analytics\Providers\Engagement_Provider;
use TutorLMS_Analytics\Providers\Quiz_Provider;
use TutorLMS_Analytics\Providers\Time_Analytics_Provider;

class AdvancedAnalyticsProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->mock_results = array();
		$wpdb->mock_var     = array();
		$wpdb->default_var  = 0;
		$GLOBALS['mock_post_titles'] = array();
	}

	public function test_engagement_data_exposes_segment_keys(): void {
		$provider = new Engagement_Provider();

		$data = $provider->get_engagement_data( 123 );

		$this->assertArrayHasKey( 'engagement_trends', $data );
		$this->assertArrayHasKey( 'high_intent_stuck', $data );
		$this->assertArrayHasKey( 'power_learners', $data );
		$this->assertArrayHasKey( 'low_engagement_high_score', $data );
		$this->assertIsArray( $data['engagement_trends'] );
		$this->assertIsArray( $data['high_intent_stuck'] );
		$this->assertIsArray( $data['power_learners'] );
		$this->assertIsArray( $data['low_engagement_high_score'] );
	}

	public function test_time_analytics_exposes_revisit_and_time_to_complete_keys(): void {
		$provider = new Time_Analytics_Provider();

		$data = $provider->get_time_analytics( 123 );

		$this->assertArrayHasKey( 'lesson_revisit_rate', $data );
		$this->assertArrayHasKey( 'time_to_complete_per_lesson', $data );
		$this->assertIsArray( $data['lesson_revisit_rate'] );
		$this->assertIsArray( $data['time_to_complete_per_lesson'] );
	}

	public function test_content_gaps_exposes_content_quality_keys(): void {
		$provider = new Content_Gap_Provider();

		$data = $provider->get_content_gaps( 123 );

		$this->assertArrayHasKey( 'exit_lessons', $data );
		$this->assertArrayHasKey( 'difficulty_index', $data );
		$this->assertArrayHasKey( 'engagement_index', $data );
		$this->assertIsArray( $data['exit_lessons'] );
		$this->assertIsArray( $data['difficulty_index'] );
		$this->assertIsArray( $data['engagement_index'] );
	}

	public function test_quiz_diagnostics_exposes_diagnostic_keys(): void {
		$provider = new Quiz_Provider();

		$data = $provider->get_quiz_diagnostics( 123 );

		$this->assertArrayHasKey( 'question_difficulty', $data );
		$this->assertArrayHasKey( 'common_wrong_answers', $data );
		$this->assertArrayHasKey( 'attempts_before_pass', $data );
		$this->assertArrayHasKey( 'retry_behavior', $data );
		$this->assertIsArray( $data['question_difficulty'] );
		$this->assertIsArray( $data['common_wrong_answers'] );
		$this->assertIsArray( $data['attempts_before_pass'] );
		$this->assertIsArray( $data['retry_behavior'] );
	}

	public function test_seeded_content_quality_indexes_are_calculated_from_dropoff_and_completion_data(): void {
		global $wpdb;
		$GLOBALS['mock_post_titles'] = array(
			501 => 'Seeded Hard Lesson',
		);
		$wpdb->mock_var['post_parent = 123 AND post_type = \'tutor_enrolled\''] = 10;
		$wpdb->mock_results["post_type = 'topics' AND post_parent = 123"] = array(
			array( 'ID' => 1001, 'post_title' => 'Topic 1' ),
		);
		$wpdb->mock_results["post_type = 'lesson' AND post_parent IN (1001)"] = array(
			array( 'ID' => 501, 'post_title' => 'Seeded Hard Lesson' ),
		);
		$wpdb->mock_var['comment_post_ID = 501'] = 4;

		$provider = new Content_Gap_Provider();
		$data = $provider->get_content_gaps( 123 );

		$this->assertSame( 'Seeded Hard Lesson', $data['highest_dropoff_lessons'][0]['title'] );
		$this->assertSame( 60.0, $data['highest_dropoff_lessons'][0]['drop_pct'] );
		$this->assertSame( 100, $data['difficulty_index'][0]['score'] );
		$this->assertSame( 40, $data['engagement_index'][0]['score'] );
		$this->assertSame( 40.0, $data['engagement_index'][0]['signals']['completion_pct'] );
	}
}
